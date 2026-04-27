<?php

namespace BuyGoPlus\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SellerGrantService {
	const DEFAULT_PRODUCT_LIMIT = 3;
	private const GRANTED_ROLE = 'buygo_admin';

	public function process_order( int $order_id ): bool {
		if ( $this->is_order_processed( $order_id ) ) {
			return true;
		}

		$product_id = $this->get_seller_product_id();
		if ( ! $product_id || ! $this->order_contains_product( $order_id, $product_id ) ) {
			return false;
		}

		$customer = $this->get_order_customer( $order_id );
		if ( ! $customer || empty( $customer->user_id ) ) {
			$this->record_grant( $order_id, 0, $product_id, 'failed', 'Customer not linked to WordPress user' );
			$this->notify_admin_failure( $order_id, 0, 'Customer not linked to WordPress user' );
			return false;
		}

		$user_id = (int) $customer->user_id;
		$user    = $this->load_user( $user_id );
		if ( ! $user ) {
			$this->record_grant( $order_id, $user_id, $product_id, 'failed', 'WordPress user not found' );
			$this->notify_admin_failure( $order_id, $user_id, 'WordPress user not found' );
			return false;
		}

		if ( in_array( self::GRANTED_ROLE, (array) ( $user->roles ?? [] ), true ) ) {
			$this->record_grant( $order_id, $user_id, $product_id, 'skipped', 'User already has buygo_admin role' );
			return true;
		}

		if ( ! $this->grant_seller_role( $user_id ) ) {
			$this->record_grant( $order_id, $user_id, $product_id, 'failed', 'Failed to save buygo_admin role' );
			$this->notify_admin_failure( $order_id, $user_id, 'Failed to save buygo_admin role' );
			return false;
		}

		$grant_id            = $this->record_grant( $order_id, $user_id, $product_id, 'success', null );
		$notification_result = $this->send_seller_grant_notification( $user_id );
		if ( $notification_result['sent'] ) {
			$this->update_notification_status( $grant_id, true, $notification_result['channel'] );
		}

		return true;
	}

	public function process_refund( int $order_id, string $type = 'unknown' ): void {
		$product_id = $this->get_seller_product_id();
		if ( ! $product_id || ! $this->order_contains_product( $order_id, $product_id ) ) {
			return;
		}

		$grant_record = $this->get_successful_grant_record( $order_id );
		if ( ! $grant_record ) {
			return;
		}

		$user_id = (int) $grant_record->user_id;
		$user    = $this->load_user( $user_id );
		if ( ! $user ) {
			return;
		}

		if ( method_exists( $user, 'remove_role' ) ) {
			$user->remove_role( self::GRANTED_ROLE );
		}

		delete_user_meta( $user_id, 'buygo_product_limit' );
		delete_user_meta( $user_id, 'buygo_seller_type' );

		$this->record_grant(
			$order_id,
			$user_id,
			$product_id,
			'revoked',
			sprintf( 'Order refunded (%s refund)', $type )
		);
	}

	public function is_order_processed( int $order_id ): bool {
		global $wpdb;

		$table_name = $wpdb->prefix . 'buygo_seller_grants';
		$exists     = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table_name} WHERE order_id = %d",
				$order_id
			)
		);

		return (int) $exists > 0;
	}

	public function get_seller_product_id(): ?int {
		$product_id = get_option( 'buygo_seller_product_id', '' );

		if ( $product_id === '' || $product_id === null ) {
			return null;
		}

		return (int) $product_id;
	}

	public function order_contains_product( int $order_id, int $product_id ): bool {
		global $wpdb;

		$order_items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->prefix}fct_order_items WHERE order_id = %d",
				$order_id
			)
		);

		foreach ( $order_items as $item ) {
			if ( (int) $item->post_id === $product_id ) {
				return true;
			}
		}

		return false;
	}

	public function grant_seller_role( int $user_id ): bool {
		global $wpdb;

		$user = $this->load_user( $user_id );
		if ( ! $user ) {
			return false;
		}

		if ( in_array( self::GRANTED_ROLE, (array) ( $user->roles ?? [] ), true ) ) {
			return true;
		}

		if ( method_exists( $user, 'add_role' ) ) {
			$user->add_role( self::GRANTED_ROLE );
		}

		$verified_user = $this->load_user( $user_id );
		$role_saved    = $verified_user && in_array( self::GRANTED_ROLE, (array) ( $verified_user->roles ?? [] ), true );
		if ( ! $role_saved ) {
			$caps_key     = $wpdb->prefix . 'capabilities';
			$current_caps = get_user_meta( $user_id, $caps_key, true );
			if ( ! is_array( $current_caps ) ) {
				$current_caps = [];
			}
			$current_caps[ self::GRANTED_ROLE ] = true;
			update_user_meta( $user_id, $caps_key, $current_caps );
		}

		update_user_meta( $user_id, 'buygo_product_limit', self::DEFAULT_PRODUCT_LIMIT );
		update_user_meta( $user_id, 'buygo_seller_type', 'test' );

		$verified_user = $this->load_user( $user_id );
		return $verified_user && in_array( self::GRANTED_ROLE, (array) ( $verified_user->roles ?? [] ), true );
	}

	public function record_grant(
		int $order_id,
		int $user_id,
		int $product_id,
		string $status,
		?string $error_message = null
	): int {
		global $wpdb;

		$table_name = $wpdb->prefix . 'buygo_seller_grants';

		$wpdb->insert(
			$table_name,
			[
				'order_id'      => $order_id,
				'user_id'       => $user_id,
				'product_id'    => $product_id,
				'status'        => $status,
				'error_message' => $error_message,
				'granted_role'  => self::GRANTED_ROLE,
				'granted_quota' => self::DEFAULT_PRODUCT_LIMIT,
				'created_at'    => current_time( 'mysql' ),
			],
			[ '%d', '%d', '%d', '%s', '%s', '%s', '%d', '%s' ]
		);

		return (int) $wpdb->insert_id;
	}

	private function get_order_customer( int $order_id ) {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT c.* FROM {$wpdb->prefix}fct_orders o
				INNER JOIN {$wpdb->prefix}fct_customers c ON o.customer_id = c.id
				WHERE o.id = %d
				LIMIT 1",
				$order_id
			)
		);
	}

	private function get_successful_grant_record( int $order_id ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'buygo_seller_grants';

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE order_id = %d AND status = 'success' ORDER BY created_at DESC LIMIT 1",
				$order_id
			)
		);
	}

	private function load_user( int $user_id ) {
		if ( function_exists( 'get_user_by' ) ) {
			$user = get_user_by( 'ID', $user_id );
			if ( $user ) {
				return $user;
			}
		}

		if ( function_exists( 'get_userdata' ) ) {
			return get_userdata( $user_id );
		}

		return null;
	}

	private function send_seller_grant_notification( int $user_id ): array {
		$user = $this->load_user( $user_id );
		if ( ! $user ) {
			return [ 'sent' => false, 'channel' => null ];
		}

		$template_args = [
			'display_name'  => $user->display_name ?: '新賣家',
			'product_limit' => self::DEFAULT_PRODUCT_LIMIT,
			'dashboard_url' => home_url( '/buygo-portal/dashboard/' ),
		];

		if ( class_exists( IdentityService::class ) && IdentityService::hasLineBinding( $user_id ) && class_exists( NotificationTemplates::class ) && class_exists( NotificationService::class ) ) {
			$template = NotificationTemplates::get( 'system_seller_grant_line', $template_args );
			$message  = $template['line']['text'] ?? $template['line']['message'] ?? '';

			if ( $message ) {
				try {
					$sent = $this->execute_with_retry(
						function () use ( $user_id, $message ) {
							return NotificationService::sendRawText( $user_id, $message );
						}
					);
					if ( $sent ) {
						return [ 'sent' => true, 'channel' => 'line' ];
					}
				} catch ( \Exception $e ) {
					error_log( sprintf( '[BuyGo+1][SellerGrant] LINE notification failed: %s', $e->getMessage() ) );
				}
			}
		}

		if ( ! empty( $user->user_email ) && $this->send_seller_grant_email( $user, $template_args ) ) {
			return [ 'sent' => true, 'channel' => 'email' ];
		}

		return [ 'sent' => false, 'channel' => null ];
	}

	private function send_seller_grant_email( $user, array $template_args ): bool {
		if ( ! function_exists( 'wp_mail' ) ) {
			return false;
		}

		$message = '';
		if ( class_exists( NotificationTemplates::class ) ) {
			$template = NotificationTemplates::get( 'system_seller_grant_email', $template_args );
			$message  = $template['line']['text'] ?? $template['line']['message'] ?? '';
		}

		if ( $message === '' ) {
			$message = '恭喜成為 BuyGo 賣家。';
		}

		return wp_mail( $user->user_email, '🎉 恭喜成為 BuyGo 賣家！', $message );
	}

	private function update_notification_status( int $grant_id, bool $sent, ?string $channel ): void {
		global $wpdb;

		$wpdb->update(
			$wpdb->prefix . 'buygo_seller_grants',
			[
				'notification_sent'    => $sent ? 1 : 0,
				'notification_channel' => $channel,
			],
			[ 'id' => $grant_id ],
			[ '%d', '%s' ],
			[ '%d' ]
		);
	}

	private function execute_with_retry( callable $operation, int $max_retries = 3, int $delay_ms = 500 ) {
		$last_exception = null;

		for ( $attempt = 1; $attempt <= $max_retries; $attempt++ ) {
			try {
				return $operation();
			} catch ( \Exception $e ) {
				$last_exception = $e;

				if ( $attempt < $max_retries ) {
					usleep( $delay_ms * 1000 );
				}
			}
		}

		throw $last_exception;
	}

	private function notify_admin_failure( int $order_id, int $user_id, string $reason ): void {
		$admin_email = get_option( 'admin_email' );
		if ( ! $admin_email || ! function_exists( 'wp_mail' ) ) {
			return;
		}

		$message  = "BuyGo 賣家權限自動賦予流程失敗\n\n";
		$message .= "訂單 ID：{$order_id}\n";
		$message .= '使用者 ID：' . ( $user_id ?: '（無）' ) . "\n";
		$message .= "失敗原因：{$reason}\n";

		wp_mail( $admin_email, '[BuyGo] 賣家權限賦予失敗通知', $message );
	}
}

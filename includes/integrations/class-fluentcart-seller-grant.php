<?php
/**
 * FluentCart Seller Grant Integration
 *
 * 監聽 FluentCart 訂單事件，當顧客購買賣家商品並付款完成時，
 * 自動賦予 buygo_admin 角色和預設配額
 *
 * @package BuygoPlus
 * @since Phase 39
 */

namespace BuygoPlus\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FluentCartSellerGrantIntegration
 *
 * 功能：
 * 1. 監聽 fluent_cart/order_created 和 fluent_cart/order_paid 事件
 * 2. 檢查訂單是否包含賣家商品
 * 3. 自動賦予 buygo_admin 角色
 * 4. 設定預設配額（buygo_product_limit = 3, buygo_seller_type = 'test'）
 * 5. 記錄賦予歷史到 wp_buygo_seller_grants 表
 * 6. 使用去重機制防止重複處理
 */
class FluentCartSellerGrantIntegration {

	/**
	 * 註冊 hooks
	 */
	public static function register_hooks(): void {
		// 監聽訂單建立事件（記錄訂單資訊）
		\add_action( 'fluent_cart/order_created', [ __CLASS__, 'handle_order_created' ], 20 );

		// 監聽訂單付款完成事件（執行賦予）
		\add_action( 'fluent_cart/order_paid', [ __CLASS__, 'handle_order_paid' ], 20 );

		// 監聽訂單退款事件（撤銷賦予）
		\add_action( 'fluent_cart/order_refunded', [ __CLASS__, 'handle_order_refunded' ], 20 );
	}

	/**
	 * 處理訂單建立事件
	 *
	 * @param array $data FluentCart 事件資料陣列
	 */
	public static function handle_order_created( $data ): void {
		$order = $data['order'] ?? null;

		if ( ! $order ) {
			error_log( '[BuyGo+1][SellerGrant] order_created: no order data' );
			return;
		}

		error_log( sprintf(
			'[BuyGo+1][SellerGrant] order_created: Order #%d (payment_method: %s, payment_status: %s)',
			$order->id,
			$order->payment_method ?? 'unknown',
			$order->payment_status ?? 'unknown'
		) );
	}

	/**
	 * 處理訂單付款完成事件
	 *
	 * @param array $data FluentCart 事件資料陣列
	 */
	public static function handle_order_paid( $data ): void {
		$order = $data['order'] ?? null;

		if ( ! $order ) {
			error_log( '[BuyGo+1][SellerGrant] order_paid: no order data' );
			return;
		}

		error_log( sprintf(
			'[BuyGo+1][SellerGrant] order_paid: Order #%d (payment_status: %s)',
			$order->id,
			$order->payment_status ?? 'unknown'
		) );

		// 檢查是否已處理過
		if ( self::is_order_processed( $order->id ) ) {
			error_log( sprintf(
				'[BuyGo+1][SellerGrant] Order #%d already processed, skipping',
				$order->id
			) );
			return;
		}

		// 檢查訂單是否包含賣家商品
		$seller_product_id = self::get_seller_product_id();
		if ( ! $seller_product_id ) {
			error_log( '[BuyGo+1][SellerGrant] No seller product configured, skipping' );
			return;
		}

		if ( ! self::order_contains_product( $order, $seller_product_id ) ) {
			error_log( sprintf(
				'[BuyGo+1][SellerGrant] Order #%d does not contain seller product (ID: %d)',
				$order->id,
				$seller_product_id
			) );
			return;
		}

		error_log( sprintf(
			'[BuyGo+1][SellerGrant] Order #%d contains seller product, granting seller role',
			$order->id
		) );

		// 執行賦予
		self::grant_seller_role( $order, $seller_product_id );
	}

	/**
	 * 檢查訂單是否已處理過
	 *
	 * @param int $order_id FluentCart 訂單 ID
	 * @return bool
	 */
	private static function is_order_processed( int $order_id ): bool {
		global $wpdb;

		$table_name = $wpdb->prefix . 'buygo_seller_grants';

		$exists = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table_name} WHERE order_id = %d",
			$order_id
		) );

		return $exists > 0;
	}

	/**
	 * 取得賣家商品 ID
	 *
	 * @return int|null
	 */
	private static function get_seller_product_id(): ?int {
		$product_id = get_option( 'buygo_seller_product_id', '' );

		if ( empty( $product_id ) ) {
			return null;
		}

		return (int) $product_id;
	}

	/**
	 * 檢查訂單是否包含指定商品
	 *
	 * @param object $order 訂單物件
	 * @param int $product_id 商品 ID (WordPress Post ID)
	 * @return bool
	 */
	private static function order_contains_product( $order, int $product_id ): bool {
		global $wpdb;

		// FluentCart 使用 post_id 欄位儲存 WordPress Post ID
		$order_items = $wpdb->get_results( $wpdb->prepare(
			"SELECT post_id FROM {$wpdb->prefix}fct_order_items WHERE order_id = %d",
			$order->id
		) );

		foreach ( $order_items as $item ) {
			if ( (int) $item->post_id === $product_id ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * 賣家賦予常數
	 */
	const DEFAULT_PRODUCT_LIMIT = 3;

	/**
	 * 賦予賣家角色和配額
	 *
	 * @param object $order 訂單物件
	 * @param int $product_id 商品 ID
	 */
	private static function grant_seller_role( $order, int $product_id ): void {
		global $wpdb;

		// 取得顧客資料
		$customer = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}fct_customers WHERE id = %d",
			$order->customer_id
		) );

		if ( ! $customer || ! $customer->user_id ) {
			error_log( sprintf(
				'[BuyGo+1][SellerGrant] Order #%d: customer not linked to WordPress user',
				$order->id
			) );
			self::record_grant( $order->id, 0, $product_id, 'failed', 'Customer not linked to WordPress user' );
			self::notify_admin_failure( $order->id, 0, 'Customer not linked to WordPress user' );
			return;
		}

		$user_id = $customer->user_id;
		$user = get_user_by( 'ID', $user_id );

		if ( ! $user ) {
			error_log( sprintf(
				'[BuyGo+1][SellerGrant] Order #%d: WordPress user not found (ID: %d)',
				$order->id,
				$user_id
			) );
			self::record_grant( $order->id, $user_id, $product_id, 'failed', 'WordPress user not found' );
			self::notify_admin_failure( $order->id, $user_id, 'WordPress user not found' );
			return;
		}

		// 檢查是否已有 buygo_admin 角色
		if ( in_array( 'buygo_admin', $user->roles, true ) ) {
			error_log( sprintf(
				'[BuyGo+1][SellerGrant] Order #%d: User #%d already has buygo_admin role, skipping',
				$order->id,
				$user_id
			) );
			self::record_grant( $order->id, $user_id, $product_id, 'skipped', 'User already has buygo_admin role' );
			return;
		}

		// 賦予 buygo_admin 角色
		$user->add_role( 'buygo_admin' );

		// 設定預設配額
		update_user_meta( $user_id, 'buygo_product_limit', self::DEFAULT_PRODUCT_LIMIT );
		update_user_meta( $user_id, 'buygo_seller_type', 'test' );

		// 記錄成功
		$grant_id = self::record_grant( $order->id, $user_id, $product_id, 'success', null );

		// 發送通知
		$notification_result = self::send_seller_grant_notification( $user_id, $grant_id );

		// 更新通知狀態
		if ( $notification_result['sent'] ) {
			self::update_notification_status( $grant_id, true, $notification_result['channel'] );
		}

		error_log( sprintf(
			'[BuyGo+1][SellerGrant] Order #%d: Successfully granted buygo_admin role to user #%d (email: %s)',
			$order->id,
			$user_id,
			$user->user_email
		) );
	}

	/**
	 * 發送賣家權限賦予通知
	 *
	 * 通知管道判斷：
	 * - 已綁定 LINE：發送 LINE 通知（帶重試機制）
	 * - 未綁定 LINE：發送 Email
	 *
	 * @param int $user_id WordPress 使用者 ID
	 * @param int $grant_id 賦予記錄 ID
	 * @return array ['sent' => bool, 'channel' => string|null]
	 */
	private static function send_seller_grant_notification( int $user_id, int $grant_id ): array {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			error_log( sprintf( '[BuyGo+1][SellerGrant] Cannot send notification: user #%d not found', $user_id ) );
			return [ 'sent' => false, 'channel' => null ];
		}

		// 準備通知內容
		$message = self::get_notification_message( $user_id );
		$dashboard_url = home_url( '/buygo-portal/dashboard/' );
		$line_official_url = 'https://line.me/ti/p/@317qvsmj'; // BuyGo LINE 官方帳號

		// 檢查是否有 LINE 綁定（使用現有的 IdentityService）
		$has_line_binding = \BuygoPlus\Services\IdentityService::hasLineBinding( $user_id );

		if ( $has_line_binding ) {
			// 發送 LINE 通知（帶重試機制）
			try {
				$line_message = $message . "\n\n";
				$line_message .= "📲 後台管理：\n{$dashboard_url}\n\n";
				$line_message .= "💬 加入 BuyGo LINE 官方帳號：\n{$line_official_url}\n\n";
				$line_message .= "💡 提示：在 LINE 輸入 /id 可查詢您的身份";

				$result = self::execute_with_retry(
					function () use ( $user_id, $line_message ) {
						return \BuyGoPlus\Services\NotificationService::sendRawText( $user_id, $line_message );
					},
					3,
					500
				);

				if ( $result ) {
					error_log( sprintf( '[BuyGo+1][SellerGrant] LINE notification sent to user #%d', $user_id ) );
					return [ 'sent' => true, 'channel' => 'line' ];
				}
			} catch ( \Exception $e ) {
				error_log( sprintf( '[BuyGo+1][SellerGrant] LINE notification failed after retries: %s', $e->getMessage() ) );
			}
		}

		// Fallback 到 Email
		if ( $user->user_email ) {
			$email_result = self::send_seller_grant_email( $user, $dashboard_url, $line_official_url );
			if ( $email_result ) {
				error_log( sprintf( '[BuyGo+1][SellerGrant] Email notification sent to %s', $user->user_email ) );
				return [ 'sent' => true, 'channel' => 'email' ];
			}
		}

		error_log( sprintf( '[BuyGo+1][SellerGrant] Failed to send notification to user #%d', $user_id ) );
		return [ 'sent' => false, 'channel' => null ];
	}

	/**
	 * 執行帶重試機制的操作
	 *
	 * @param callable $operation 要執行的操作
	 * @param int $max_retries 最大重試次數
	 * @param int $delay_ms 重試延遲（毫秒）
	 * @return mixed 操作結果
	 * @throws \Exception 所有重試都失敗時拋出最後的例外
	 */
	private static function execute_with_retry( callable $operation, int $max_retries = 3, int $delay_ms = 500 ) {
		$last_exception = null;

		for ( $attempt = 1; $attempt <= $max_retries; $attempt++ ) {
			try {
				return $operation();
			} catch ( \Exception $e ) {
				$last_exception = $e;
				error_log(
					sprintf(
						'[BuyGo+1][SellerGrant] Operation failed (attempt %d/%d): %s',
						$attempt,
						$max_retries,
						$e->getMessage()
					)
				);

				if ( $attempt < $max_retries ) {
					usleep( $delay_ms * 1000 ); // 轉換為微秒
				}
			}
		}

		throw $last_exception;
	}

	/**
	 * 取得通知訊息內容
	 *
	 * @param int $user_id 使用者 ID
	 * @return string
	 */
	private static function get_notification_message( int $user_id ): string {
		$user = get_userdata( $user_id );
		$display_name = $user ? $user->display_name : '新賣家';

		return "🎉 恭喜 {$display_name} 成為 BuyGo 賣家！\n\n" .
			"您已獲得以下權限：\n" .
			"✅ BuyGo 管理員角色\n" .
			"✅ 商品配額：" . self::DEFAULT_PRODUCT_LIMIT . " 個\n\n" .
			"您現在可以開始上架商品了！";
	}

	/**
	 * 發送賣家權限賦予 Email
	 *
	 * @param \WP_User $user 使用者物件
	 * @param string $dashboard_url 後台連結
	 * @param string $line_official_url LINE 官方帳號連結
	 * @return bool
	 */
	private static function send_seller_grant_email( $user, string $dashboard_url, string $line_official_url ): bool {
		$subject = '🎉 恭喜成為 BuyGo 賣家！';

		$message = "親愛的 {$user->display_name}，\n\n";
		$message .= "恭喜您成為 BuyGo 賣家！\n\n";
		$message .= "您已獲得以下權限：\n";
		$message .= "• BuyGo 管理員角色\n";
		$message .= "• 商品配額：" . self::DEFAULT_PRODUCT_LIMIT . " 個\n\n";
		$message .= "開始使用：\n";
		$message .= "• 後台管理：{$dashboard_url}\n\n";
		$message .= "加入 BuyGo LINE 官方帳號獲得更多支援：\n";
		$message .= "{$line_official_url}\n\n";
		$message .= "綁定 LINE 後，您可以：\n";
		$message .= "• 直接在 LINE 上架商品\n";
		$message .= "• 使用 /id 指令查詢身份\n";
		$message .= "• 接收訂單和出貨通知\n\n";
		$message .= "祝您生意興隆！\n";
		$message .= "BuyGo 團隊";

		return wp_mail( $user->user_email, $subject, $message );
	}

	/**
	 * 更新通知狀態
	 *
	 * @param int $grant_id 賦予記錄 ID
	 * @param bool $sent 是否已發送
	 * @param string|null $channel 通知管道
	 */
	private static function update_notification_status( int $grant_id, bool $sent, ?string $channel ): void {
		global $wpdb;
		$wpdb->update(
			$wpdb->prefix . 'buygo_seller_grants',
			[
				'notification_sent' => $sent ? 1 : 0,
				'notification_channel' => $channel,
			],
			[ 'id' => $grant_id ],
			[ '%d', '%s' ],
			[ '%d' ]
		);
	}

	/**
	 * 通知管理員賦予失敗
	 *
	 * @param int $order_id 訂單 ID
	 * @param int $user_id 使用者 ID（可能為 0）
	 * @param string $reason 失敗原因
	 */
	private static function notify_admin_failure( int $order_id, int $user_id, string $reason ): void {
		$admin_email = get_option( 'admin_email' );
		if ( ! $admin_email ) {
			error_log( '[BuyGo+1][SellerGrant] Cannot notify admin: no admin email configured' );
			return;
		}

		$subject = '[BuyGo] 賣家權限賦予失敗通知';

		$message = "BuyGo 賣家權限自動賦予流程失敗\n\n";
		$message .= "訂單 ID：{$order_id}\n";
		$message .= "使用者 ID：" . ( $user_id ?: '（無）' ) . "\n";
		$message .= "失敗原因：{$reason}\n\n";
		$message .= "請登入後台檢查並手動處理：\n";
		$message .= admin_url( 'admin.php?page=buygo-settings&tab=roles' ) . "\n\n";
		$message .= "時間：" . current_time( 'Y-m-d H:i:s' ) . "\n";
		$message .= "---\n";
		$message .= "此郵件由 BuyGo+1 外掛自動發送";

		$sent = wp_mail( $admin_email, $subject, $message );

		if ( $sent ) {
			error_log( sprintf( '[BuyGo+1][SellerGrant] Admin notified about failure for order #%d', $order_id ) );
		} else {
			error_log( sprintf( '[BuyGo+1][SellerGrant] Failed to notify admin about order #%d', $order_id ) );
		}
	}

	/**
	 * 處理訂單退款事件
	 *
	 * @param array $data FluentCart 事件資料陣列
	 */
	public static function handle_order_refunded( $data ): void {
		$order = $data['order'] ?? null;
		$type  = $data['type'] ?? 'unknown'; // 'full' or 'partial'

		if ( ! $order ) {
			error_log( '[BuyGo+1][SellerGrant] order_refunded: no order data' );
			return;
		}

		error_log( sprintf(
			'[BuyGo+1][SellerGrant] order_refunded: Order #%d (type: %s, refunded_amount: %d)',
			$order->id,
			$type,
			$data['refunded_amount'] ?? 0
		) );

		// 檢查訂單是否包含賣家商品
		$seller_product_id = self::get_seller_product_id();
		if ( ! $seller_product_id ) {
			error_log( '[BuyGo+1][SellerGrant] No seller product configured, skipping' );
			return;
		}

		if ( ! self::order_contains_product( $order, $seller_product_id ) ) {
			error_log( sprintf(
				'[BuyGo+1][SellerGrant] Order #%d does not contain seller product (ID: %d)',
				$order->id,
				$seller_product_id
			) );
			return;
		}

		// 查詢原始賦予記錄
		global $wpdb;
		$table_name = $wpdb->prefix . 'buygo_seller_grants';

		$grant_record = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table_name} WHERE order_id = %d AND status = 'success' ORDER BY created_at DESC LIMIT 1",
			$order->id
		) );

		if ( ! $grant_record ) {
			error_log( sprintf(
				'[BuyGo+1][SellerGrant] Order #%d: No successful grant record found, nothing to revoke',
				$order->id
			) );
			return;
		}

		$user_id = $grant_record->user_id;
		$user = get_user_by( 'ID', $user_id );

		if ( ! $user ) {
			error_log( sprintf(
				'[BuyGo+1][SellerGrant] Order #%d: WordPress user not found (ID: %d)',
				$order->id,
				$user_id
			) );
			return;
		}

		// 移除 buygo_admin 角色
		if ( in_array( 'buygo_admin', $user->roles, true ) ) {
			$user->remove_role( 'buygo_admin' );
			error_log( sprintf(
				'[BuyGo+1][SellerGrant] Order #%d: Removed buygo_admin role from user #%d',
				$order->id,
				$user_id
			) );
		}

		// 移除相關 user meta
		delete_user_meta( $user_id, 'buygo_product_limit' );
		delete_user_meta( $user_id, 'buygo_seller_type' );

		// 記錄撤銷
		self::record_grant(
			$order->id,
			$user_id,
			$seller_product_id,
			'revoked',
			sprintf( 'Order refunded (%s refund)', $type )
		);

		error_log( sprintf(
			'[BuyGo+1][SellerGrant] Order #%d: Successfully revoked seller role from user #%d (email: %s)',
			$order->id,
			$user_id,
			$user->user_email
		) );
	}

	/**
	 * 記錄賦予歷史
	 *
	 * @param int $order_id 訂單 ID
	 * @param int $user_id 使用者 ID
	 * @param int $product_id 商品 ID
	 * @param string $status 'success'|'skipped'|'failed'|'revoked'
	 * @param string|null $error_message 錯誤訊息
	 * @return int 插入的記錄 ID
	 */
	private static function record_grant( int $order_id, int $user_id, int $product_id, string $status, ?string $error_message = null ): int {
		global $wpdb;

		$table_name = $wpdb->prefix . 'buygo_seller_grants';

		$wpdb->insert(
			$table_name,
			[
				'order_id'       => $order_id,
				'user_id'        => $user_id,
				'product_id'     => $product_id,
				'status'         => $status,
				'error_message'  => $error_message,
				'granted_role'   => 'buygo_admin',
				'granted_quota'  => self::DEFAULT_PRODUCT_LIMIT,
				'created_at'     => current_time( 'mysql' ),
			],
			[ '%d', '%d', '%d', '%s', '%s', '%s', '%d', '%s' ]
		);

		return $wpdb->insert_id;
	}
}

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
	 * @param int $product_id 商品 ID
	 * @return bool
	 */
	private static function order_contains_product( $order, int $product_id ): bool {
		global $wpdb;

		$order_items = $wpdb->get_results( $wpdb->prepare(
			"SELECT product_id FROM {$wpdb->prefix}fct_order_items WHERE order_id = %d",
			$order->id
		) );

		foreach ( $order_items as $item ) {
			if ( (int) $item->product_id === $product_id ) {
				return true;
			}
		}

		return false;
	}

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
		update_user_meta( $user_id, 'buygo_product_limit', 3 );
		update_user_meta( $user_id, 'buygo_seller_type', 'test' );

		// 記錄成功
		self::record_grant( $order->id, $user_id, $product_id, 'success', null );

		error_log( sprintf(
			'[BuyGo+1][SellerGrant] Order #%d: Successfully granted buygo_admin role to user #%d (email: %s)',
			$order->id,
			$user_id,
			$user->user_email
		) );
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
	 */
	private static function record_grant( int $order_id, int $user_id, int $product_id, string $status, ?string $error_message = null ): void {
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
				'granted_quota'  => 3,
				'created_at'     => current_time( 'mysql' ),
			],
			[ '%d', '%d', '%d', '%s', '%s', '%s', '%d', '%s' ]
		);
	}
}

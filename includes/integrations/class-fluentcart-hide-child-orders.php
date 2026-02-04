<?php
/**
 * FluentCart Hide Child Orders Integration
 *
 * 在 FluentCart 會員中心（My Account）隱藏子訂單，只顯示父訂單
 *
 * @package BuygoPlus
 */

namespace BuygoPlus\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FluentCartHideChildOrders
 *
 * 使用 WordPress query filter 在 FluentCart 會員中心隱藏子訂單
 *
 * 設計理念：
 * - 會員中心購買歷史只顯示父訂單
 * - 子訂單可以透過父訂單詳情頁的「查看子訂單」按鈕查看
 * - 使用 posts_where filter 修改 SQL WHERE 條件
 */
class FluentCartHideChildOrders {

	/**
	 * 註冊 hooks
	 */
	public static function register_hooks(): void {
		// 使用 posts_where filter 修改 FluentCart 訂單查詢
		\add_filter( 'posts_where', [ __CLASS__, 'filter_customer_orders' ], 10, 2 );
	}

	/**
	 * 過濾會員中心的訂單查詢，隱藏子訂單
	 *
	 * @param string    $where SQL WHERE 條件
	 * @param \WP_Query $query WordPress query 物件
	 * @return string 修改後的 WHERE 條件
	 */
	public static function filter_customer_orders( string $where, \WP_Query $query ): string {
		global $wpdb;

		// 只在前台執行
		if ( \is_admin() ) {
			return $where;
		}

		// 只在已登入使用者執行
		if ( ! \is_user_logged_in() ) {
			return $where;
		}

		// 檢查是否為訂單查詢
		$post_type = $query->get( 'post_type' );
		if ( $post_type !== 'fct_order' ) {
			return $where;
		}

		// 檢查是否在會員中心頁面
		// FluentCart 會員中心 URL 通常包含 /my-account/ 或 /customer-profile/
		$current_url = filter_input( INPUT_SERVER, 'REQUEST_URI', FILTER_SANITIZE_URL ) ?? '';
		$is_my_account = strpos( $current_url, '/my-account/' ) !== false ||
		                  strpos( $current_url, '/customer-profile/' ) !== false;

		if ( ! $is_my_account ) {
			return $where;
		}

		// 加入 parent_id IS NULL 條件，只顯示父訂單
		// 使用 LEFT JOIN 確保能正確過濾
		$where .= " AND {$wpdb->prefix}posts.ID IN (
			SELECT p.ID
			FROM {$wpdb->prefix}posts AS p
			LEFT JOIN {$wpdb->prefix}fct_orders AS o ON p.ID = o.id
			WHERE p.post_type = 'fct_order'
			AND (o.parent_id IS NULL OR o.parent_id = 0)
		)";

		return $where;
	}
}

<?php
/**
 * Seller Grant Service
 *
 * 負責「賣家資格授予」的商業邏輯層。
 * 原始邏輯來自 FluentCartSellerGrantIntegration（Integration 類），
 * 此 Service 將核心邏輯解耦，使其可脫離 WordPress hook 獨立測試。
 *
 * 資料表依賴：
 *   - wp_buygo_seller_grants — 賦予歷史紀錄
 *   - wp_fct_order_items     — FluentCart 訂單項目
 *   - wp_fct_customers       — FluentCart 顧客
 *
 * @package BuyGoPlus\Services
 * @since Phase 40
 */

namespace BuyGoPlus\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SellerGrantService
 *
 * 職責：
 * 1. 去重檢查（is_order_processed）
 * 2. 取得設定的賣家商品 ID（get_seller_product_id）
 * 3. 檢查訂單是否包含賣家商品（order_contains_product）
 * 4. 賦予 buygo_admin 角色（grant_seller_role）
 * 5. 記錄賦予歷史（record_grant）
 * 6. 處理完整訂單流程（process_order）
 */
class SellerGrantService {

	/**
	 * 賣家預設商品數量配額
	 */
	const DEFAULT_PRODUCT_LIMIT = 3;

	// ────────────────────────────────────────
	// 公開 API（供 Integration 類或其他呼叫端使用）
	// ────────────────────────────────────────

	/**
	 * 處理訂單：執行完整的賣家資格授予流程
	 *
	 * 流程：
	 *   1. 去重檢查（已處理則跳過）
	 *   2. 取得設定的賣家商品 ID
	 *   3. 確認訂單包含該商品
	 *   4. 賦予角色並記錄
	 *
	 * @param int $order_id FluentCart 訂單 ID
	 * @return bool 是否執行了授予動作（true = 已授予 or 已跳過，false = 無法處理）
	 */
	public function process_order( int $order_id ): bool {
		// TODO: 遷移 FluentCartSellerGrantIntegration::process_seller_grant() 邏輯
		return false;
	}

	/**
	 * 檢查訂單是否已處理過（去重機制）
	 *
	 * 查詢 wp_buygo_seller_grants 表，若已有對應 order_id 的記錄則回傳 true。
	 *
	 * @param int $order_id FluentCart 訂單 ID
	 * @return bool true = 已處理過，false = 尚未處理
	 */
	public function is_order_processed( int $order_id ): bool {
		// TODO: 遷移 FluentCartSellerGrantIntegration::is_order_processed() 邏輯
		return false;
	}

	/**
	 * 取得設定的賣家商品 ID
	 *
	 * 從 WordPress options 表讀取 buygo_seller_product_id 設定值。
	 * 未設定或空值時回傳 null。
	 *
	 * @return int|null 商品 ID，或 null（未設定）
	 */
	public function get_seller_product_id(): ?int {
		// TODO: 遷移 FluentCartSellerGrantIntegration::get_seller_product_id() 邏輯
		return null;
	}

	/**
	 * 檢查訂單是否包含指定商品
	 *
	 * 查詢 wp_fct_order_items 表，判斷 order_id 對應的訂單項目中
	 * 是否有 post_id 等於 $product_id 的項目。
	 *
	 * @param int $order_id  FluentCart 訂單 ID
	 * @param int $product_id WordPress Post ID（賣家商品）
	 * @return bool true = 包含，false = 不包含
	 */
	public function order_contains_product( int $order_id, int $product_id ): bool {
		// TODO: 遷移 FluentCartSellerGrantIntegration::order_contains_product() 邏輯
		return false;
	}

	/**
	 * 賦予指定用戶的賣家角色與預設配額
	 *
	 * 執行步驟：
	 *   1. 取得 WordPress 用戶物件
	 *   2. 若已有 buygo_admin 角色則跳過（回傳 true）
	 *   3. 呼叫 $user->add_role('buygo_admin')
	 *   4. 設定 user meta: buygo_product_limit = 3, buygo_seller_type = 'test'
	 *
	 * @param int $user_id WordPress 用戶 ID
	 * @return bool true = 成功授予（或已有角色），false = 失敗
	 */
	public function grant_seller_role( int $user_id ): bool {
		// TODO: 遷移 FluentCartSellerGrantIntegration::grant_seller_role() 相關邏輯
		return false;
	}

	/**
	 * 記錄賦予歷史到 wp_buygo_seller_grants 表
	 *
	 * @param int         $order_id      FluentCart 訂單 ID
	 * @param int         $user_id       WordPress 用戶 ID（失敗時可能為 0）
	 * @param int         $product_id    賣家商品 ID
	 * @param string      $status        'success'|'skipped'|'failed'|'revoked'
	 * @param string|null $error_message 錯誤訊息（成功時為 null）
	 * @return int 插入的記錄 ID（$wpdb->insert_id）
	 */
	public function record_grant(
		int $order_id,
		int $user_id,
		int $product_id,
		string $status,
		?string $error_message = null
	): int {
		// TODO: 遷移 FluentCartSellerGrantIntegration::record_grant() 邏輯
		return 0;
	}
}

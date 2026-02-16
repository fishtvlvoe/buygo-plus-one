<?php
/**
 * LINE Permission Validator
 *
 * 權限驗證邏輯 - 檢查使用者是否有上傳商品和與 Bot 互動的權限
 *
 * @package BuyGoPlus
 */

namespace BuyGoPlus\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class LinePermissionValidator
 *
 * 職責：
 * - 檢查使用者是否有商品上傳權限
 * - 判斷商品擁有者（賣家 vs 小幫手）
 * - 身份過濾（賣家/小幫手 vs 買家/未綁定）
 */
class LinePermissionValidator {

	/**
	 * Webhook Logger
	 *
	 * @var WebhookLogger
	 */
	private $logger;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->logger = WebhookLogger::get_instance();
	}

	/**
	 * 檢查使用者是否有上傳權限
	 *
	 * 允許三種人上傳：
	 * 1. WordPress 管理員（administrator）
	 * 2. buygo 管理員（buygo_admin）
	 * 3. buygo_helper 小幫手（buygo_helper 角色或 wp_buygo_helpers 資料表中）
	 *
	 * @param \WP_User $user WordPress 使用者物件
	 * @return bool 是否有權限
	 */
	public function can_upload_product( $user ) {
		if ( ! $user || ! $user->ID ) {
			return false;
		}

		$user_data = get_userdata( $user->ID );
		if ( ! $user_data || empty( $user_data->roles ) ) {
			return false;
		}

		$roles = $user_data->roles;

		// 1. WordPress 管理員
		if ( in_array( 'administrator', $roles, true ) ) {
			return true;
		}

		// 2. buygo 管理員
		if ( in_array( 'buygo_admin', $roles, true ) ) {
			return true;
		}

		// 3. buygo_helper 小幫手（檢查角色）
		if ( in_array( 'buygo_helper', $roles, true ) ) {
			return true;
		}

		// 4. 檢查是否在 wp_buygo_helpers 資料表中（新版權限系統）
		global $wpdb;
		$table_name = $wpdb->prefix . 'buygo_helpers';

		// 檢查資料表是否存在
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name ) {
			// 查詢資料表，檢查該用戶是否為任何賣家的小幫手
			$is_helper = $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$table_name} WHERE helper_id = %d",
				$user->ID
			) );

			if ( $is_helper > 0 ) {
				return true;
			}
		}

		// 5. 向後相容：檢查舊的 buygo_helpers option
		$helper_ids = get_option( 'buygo_helpers', [] );
		if ( is_array( $helper_ids ) && in_array( $user->ID, $helper_ids, true ) ) {
			return true;
		}

		return false;
	}

	/**
	 * 取得商品擁有者（賣家 ID）
	 *
	 * 根據使用者身份判斷商品的真正擁有者：
	 * - 如果是賣家本人 → 返回賣家 ID
	 * - 如果是小幫手 → 從 wp_buygo_helpers 表查詢對應的賣家 ID
	 * - 如果是管理員 → 返回管理員 ID（作為賣家）
	 *
	 * @param \WP_User $user WordPress 使用者物件
	 * @return int 商品擁有者的 User ID（0 表示無法判斷）
	 */
	public function get_product_owner( $user ) {
		if ( ! $user || ! $user->ID ) {
			return 0;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'buygo_helpers';

		// 檢查資料表是否存在
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name ) {
			// 查詢該使用者是否為小幫手（從 helper_id 欄位查詢對應的 seller_id）
			$seller_id = $wpdb->get_var( $wpdb->prepare(
				"SELECT seller_id FROM {$table_name} WHERE helper_id = %d LIMIT 1",
				$user->ID
			) );

			if ( $seller_id ) {
				$this->logger->log( 'product_owner_identified', array(
					'helper_id' => $user->ID,
					'seller_id' => $seller_id,
					'source' => 'buygo_helpers_table',
				) );
				return (int) $seller_id;
			}
		}

		// 如果不是小幫手，則該使用者就是賣家本人
		$this->logger->log( 'product_owner_identified', array(
			'user_id' => $user->ID,
			'is_seller' => true,
			'source' => 'user_self',
		) );
		return $user->ID;
	}

	/**
	 * 檢查 Bot 是否應該回應此用戶
	 *
	 * Phase 29: 根據身份決定是否處理訊息
	 * - 賣家/小幫手：正常處理
	 * - 買家/未綁定用戶：靜默不回應
	 *
	 * @param string   $line_uid     LINE User ID
	 * @param int|null $user_id      WordPress User ID（可能為 null）
	 * @param string   $message_type 訊息類型（用於日誌）
	 * @return bool 是否應該處理
	 */
	public function shouldBotRespond( $line_uid, $user_id, $message_type = '' ) {
		// 使用 IdentityService 判斷是否可以與 Bot 互動
		$can_interact = IdentityService::canInteractWithBotByLineUid( $line_uid );

		if ( ! $can_interact ) {
			// 取得身份資訊用於日誌記錄
			$identity = IdentityService::getIdentityByLineUid( $line_uid );

			$this->logger->log( 'bot_response_filtered', array(
				'line_uid'     => $line_uid,
				'user_id'      => $identity['user_id'],
				'role'         => $identity['role'],
				'is_bound'     => $identity['is_bound'],
				'message_type' => $message_type,
				'action'       => 'silent_ignore',
			), $identity['user_id'], $line_uid );

			return false;
		}

		return true;
	}
}

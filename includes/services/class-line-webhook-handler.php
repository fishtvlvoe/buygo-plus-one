<?php
/**
 * LINE Webhook Handler
 *
 * @package BuyGoPlus
 */

namespace BuyGoPlus\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// 檢查 buygo-line-notify 外掛是否啟用（在 admin_init 時檢查，確保所有外掛已載入）
add_action( 'admin_init', function() {
	if ( ! class_exists( '\BuygoLineNotify\BuygoLineNotify' ) ) {
		add_action( 'admin_notices', function() {
			echo '<div class="notice notice-error"><p>';
			echo 'BuyGo+ Plus One 需要啟用 BuyGo Line Notify 外掛才能正常運作 LINE 相關功能。';
			echo '</p></div>';
		} );
	}
} );

/**
 * Class LineWebhookHandler
 *
 * Handles LINE Webhook events: image upload, text messages, product creation
 */
class LineWebhookHandler {

	/**
	 * Product Data Parser
	 *
	 * @var ProductDataParser
	 */
	private $product_data_parser;

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
		$this->product_data_parser = new ProductDataParser();
		$this->logger = WebhookLogger::get_instance();

		// 註冊 Hook：監聯 buygo-line-notify 的 webhook 訊息事件
		// 圖片訊息 → 觸發商品圖片上傳流程
		add_action( 'buygo_line_notify/webhook_message_image', array( $this, 'handleImageUpload' ), 10, 4 );
		// 文字訊息 → 處理關鍵字回應、命令、商品資訊等
		add_action( 'buygo_line_notify/webhook_message_text', array( $this, 'handleTextMessage' ), 10, 4 );
		// Postback 事件 → 處理商品類型選擇（單一/多樣）
		add_action( 'buygo_line_notify/webhook_postback', array( $this, 'handlePostback' ), 10, 3 );
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
	private function can_upload_product( $user ) {
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
		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ) === $table_name ) {
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
	private function get_product_owner( $user ) {
		if ( ! $user || ! $user->ID ) {
			return 0;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'buygo_helpers';

		// 檢查資料表是否存在
		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ) === $table_name ) {
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
	private function shouldBotRespond( $line_uid, $user_id, $message_type = '' ) {
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

	/**
	 * 處理 Postback 事件（商品類型選擇）
	 *
	 * 當使用者點擊 Flex Message 選單選擇商品類型時觸發
	 *
	 * @param array  $event    Event data
	 * @param string $line_uid LINE user ID
	 * @param int    $user_id  WordPress user ID
	 * @return void
	 */
	public function handlePostback( $event, $line_uid, $user_id ) {
		// Phase 29: 身份過濾 - 買家和未綁定用戶靜默不回應
		if ( ! $this->shouldBotRespond( $line_uid, $user_id, 'postback' ) ) {
			return;
		}

		// 解析 postback data
		$postback_data = $event['postback']['data'] ?? '';
		parse_str( $postback_data, $params );

		$this->logger->log( 'postback_received', array(
			'action' => $params['action'] ?? '',
			'type'   => $params['type'] ?? '',
			'value'  => $params['value'] ?? '',
		), $user_id, $line_uid );

		// 處理商品類型選擇
		// 注意：Flex Message 按鈕使用 'type' 參數（例如 action=product_type&type=simple）
		if ( isset( $params['action'] ) && $params['action'] === 'product_type' ) {
			$product_type = $params['type'] ?? $params['value'] ?? 'simple';

			// 記錄使用者選擇的商品類型
			update_user_meta( $user_id, 'pending_product_type', $product_type );

			// 發送輸入格式提示訊息
			$message = $this->getInputFormatMessage( $product_type );
			$this->send_reply_via_facade( $event['replyToken'] ?? '', $message, $line_uid );

			$this->logger->log( 'product_type_selected', array(
				'type' => $product_type,
			), $user_id, $line_uid );
		}
	}

	/**
	 * 處理文字訊息（由 buygo-line-notify 的 webhook_message_text hook 觸發）
	 *
	 * 這是處理所有文字訊息的入口點，包含：
	 * - 關鍵字回應（後台設定的關鍵字模板）
	 * - 命令處理（/one、/many、/help 等）
	 * - 綁定碼流程
	 * - 商品資訊輸入
	 *
	 * @param array       $event      LINE Webhook 事件資料
	 * @param string      $line_uid   LINE User ID
	 * @param int|null    $user_id    WordPress User ID（未綁定時為 null）
	 * @param string      $message_id LINE Message ID
	 * @return void
	 */
	public function handleTextMessage( $event, $line_uid, $user_id, $message_id ) {
		$text = $event['message']['text'] ?? '';

		// 特例：綁定碼流程不受身份過濾限制（讓未綁定用戶也能綁定）
		$is_binding_code = preg_match( '/^\s*(?:綁定|bind)?\s*([0-9]{6})\s*$/iu', $text );

		// Phase 29: 身份過濾 - 買家和未綁定用戶靜默不回應（綁定碼除外）
		if ( ! $is_binding_code && ! $this->shouldBotRespond( $line_uid, $user_id, 'text' ) ) {
			return;
		}

		$this->logger->log( 'text_message_hook_received', array(
			'line_uid'   => $line_uid,
			'user_id'    => $user_id,
			'message_id' => $message_id,
			'text'       => substr( $text, 0, 50 ),
		), $user_id, $line_uid );

		// 呼叫內部的文字訊息處理方法
		$this->handle_text_message( $event );
	}

	/**
	 * 處理商品資訊文字訊息
	 *
	 * 當使用者發送商品資訊文字時：
	 * 1. 檢查是否有待處理的商品圖片
	 * 2. 解析商品資訊
	 * 3. 呼叫 FluentCartService 建立商品
	 * 4. 清除待處理狀態
	 *
	 * @param array  $event    Event data
	 * @param string $line_uid LINE user ID
	 * @param int    $user_id  WordPress user ID
	 * @return void
	 */
	public function handleProductInfo( $event, $line_uid, $user_id ) {
		// 檢查是否為文字訊息
		if ( ! isset( $event['message']['type'] ) || $event['message']['type'] !== 'text' ) {
			return;
		}

		// 檢查是否有待處理的商品圖片
		$image_id = get_user_meta( $user_id, 'pending_product_image', true );
		$product_type = get_user_meta( $user_id, 'pending_product_type', true );

		if ( empty( $image_id ) || empty( $product_type ) ) {
			// 沒有待處理商品，可能是其他對話
			return;
		}

		// 檢查是否超時 (30 分鐘)
		$timestamp = get_user_meta( $user_id, 'pending_product_timestamp', true );
		if ( $timestamp && ( time() - $timestamp ) > 1800 ) {
			// 超時，清除待處理狀態
			delete_user_meta( $user_id, 'pending_product_image' );
			delete_user_meta( $user_id, 'pending_product_type' );
			delete_user_meta( $user_id, 'pending_product_timestamp' );

			$this->logger->log( 'product_creation_timeout', array(
				'elapsed' => time() - $timestamp,
			), $user_id, $line_uid );

			$this->send_reply_via_facade( $event['replyToken'] ?? '', '商品建立已超時，請重新上傳圖片。', $line_uid );
			return;
		}

		// 解析文字訊息
		$text = $event['message']['text'] ?? '';
		$parsed = $this->product_data_parser->parse( $text );

		if ( is_wp_error( $parsed ) ) {
			$this->logger->log( 'product_parse_failed', array(
				'error' => $parsed->get_error_message(),
			), $user_id, $line_uid );

			$this->send_reply_via_facade( $event['replyToken'] ?? '', '商品資訊解析失敗：' . $parsed->get_error_message(), $line_uid );
			return;
		}

		// 驗證解析結果與使用者選擇的類型是否匹配
		$parsed_type = $parsed['type'] ?? 'simple';
		if ( $parsed_type !== $product_type ) {
			$this->logger->log( 'product_type_mismatch', array(
				'expected' => $product_type,
				'parsed'   => $parsed_type,
			), $user_id, $line_uid );

			// 類型不匹配，但繼續建立（使用解析出的類型）
		}

		// 呼叫 FluentCartService 建立商品
		$service = new FluentCartService();
		// 準備 product_data (符合 create_product 方法的參數格式)
		$product_data = array_merge( $parsed, array(
			'image_attachment_id' => $image_id,
			'user_id'             => $user_id,
			'line_uid'            => $line_uid,
		) );
		$product_id = $service->create_product( $product_data, array( $image_id ) );

		// 轉換回 handleProductInfo 期望的格式
		if ( is_wp_error( $product_id ) ) {
			$result = array(
				'success' => false,
				'error'   => $product_id->get_error_message(),
			);
		} else {
			$result = array(
				'success'    => true,
				'product_id' => $product_id,
				'type'       => $parsed['type'] ?? 'simple',
			);
		}

		// 清除待處理狀態
		delete_user_meta( $user_id, 'pending_product_image' );
		delete_user_meta( $user_id, 'pending_product_type' );
		delete_user_meta( $user_id, 'pending_product_timestamp' );

		// 發送結果訊息
		if ( ! empty( $result['success'] ) ) {
			$product_id = $result['product_id'];
			$product_url = $this->getProductUrl( $product_id );

			// 取得圖片 URL
			$image_url = wp_get_attachment_url( $image_id );

			// 組裝確認訊息資料
			$confirm_data = array(
				'name'      => $parsed['name'],
				'url'       => $product_url,
				'image_url' => $image_url ?: '',
			);

			if ( $result['type'] === 'simple' ) {
				$confirm_data['price']          = $parsed['price'];
				$confirm_data['original_price'] = $parsed['original_price'] ?? null;
				$confirm_data['quantity']       = $parsed['quantity'];
			} else {
				// 多樣式商品：從 FluentCart 資料庫取得 variations
				$variations = $this->getProductVariations( $product_id );
				$confirm_data['variations'] = $variations;
			}

			// 發送確認訊息 Flex Message
			$flex_contents = LineFlexTemplates::getProductConfirmation( $confirm_data, $result['type'] );
			$messaging = \BuygoLineNotify\BuygoLineNotify::messaging();
			$messaging->replyFlex( $event['replyToken'] ?? '', $flex_contents );

			$this->logger->log( 'product_created', array(
				'product_id' => $product_id,
				'type'       => $result['type'],
			), $user_id, $line_uid );
		} else {
			$message = "❌ 商品建立失敗：" . ( $result['error'] ?? '未知錯誤' );

			$this->logger->log( 'product_creation_failed', array(
				'error' => $result['error'] ?? 'unknown',
			), $user_id, $line_uid );

			$this->send_reply_via_facade( $event['replyToken'] ?? '', $message, $line_uid );
		}
	}

	/**
	 * 取得商品 URL
	 *
	 * @param int $product_id 商品 ID
	 * @return string 商品 URL
	 */
	private function getProductUrl( $product_id ) {
		// 使用短連結格式 /item/{product_id}
		// 加上 openExternalBrowser=1 讓 LINE 內點擊時自動開啟外部瀏覽器
		return home_url( "/item/{$product_id}?openExternalBrowser=1" );
	}

	/**
	 * 取得商品樣式列表（從 FluentCart 資料庫）
	 *
	 * @param int $product_id 商品 ID
	 * @return array 樣式列表
	 */
	private function getProductVariations( $product_id ) {
		global $wpdb;

		$variations = $wpdb->get_results( $wpdb->prepare(
			"SELECT variation_title, item_price, total_stock
			FROM {$wpdb->prefix}fct_product_variations
			WHERE post_id = %d
			ORDER BY id ASC",
			$product_id
		), ARRAY_A );

		if ( empty( $variations ) ) {
			return array();
		}

		// 轉換格式：price 從分轉為元
		$result = array();
		foreach ( $variations as $variation ) {
			$result[] = array(
				'variation_title' => $variation['variation_title'],
				'price'           => intval( $variation['item_price'] ) / 100,
				'quantity'        => intval( $variation['total_stock'] ),
			);
		}

		return $result;
	}

	/**
	 * 取得輸入格式說明訊息
	 *
	 * @param string $type 商品類型 (simple/variable)
	 * @return string 格式說明訊息
	 */
	private function getInputFormatMessage( $type ) {
		if ( $type === 'variable' ) {
			return "請發送多樣式商品資訊，格式：\n\n"
				. "商品名稱\n"
				. "分類：A款/B款/C款\n"
				. "價格：100/150/200\n"
				. "數量：10/5/8\n"
				. "原價：150/200/250（可選）\n"
				. "描述：商品說明（可選）";
		} else {
			return "請發送單一商品資訊，格式：\n\n"
				. "商品名稱\n"
				. "價格：100\n"
				. "數量：10\n"
				. "原價：150（可選）\n"
				. "描述：商品說明（可選）";
		}
	}

	/**
	 * 處理圖片上傳（監聯 buygo_line_notify/webhook_message_image Hook）
	 *
	 * 當賣家在 LINE 上傳圖片時：
	 * 1. 檢查賣家權限
	 * 2. 下載圖片到 Media Library
	 * 3. 儲存待處理狀態到 user_meta
	 * 4. 發送商品類型選單 Flex Message
	 *
	 * @param array  $event      Event data
	 * @param string $line_uid   LINE user ID
	 * @param int    $user_id    WordPress user ID (may be 0 if not bound)
	 * @param string $message_id LINE message ID
	 * @return void
	 */
	public function handleImageUpload( $event, $line_uid, $user_id, $message_id ) {
		// Phase 29: 身份過濾 - 買家和未綁定用戶靜默不回應
		if ( ! $this->shouldBotRespond( $line_uid, $user_id, 'image' ) ) {
			return;
		}

		// 檢查是否為圖片訊息（雙重確認）
		if ( isset( $event['message']['type'] ) && $event['message']['type'] !== 'image' ) {
			return;
		}

		// 檢查 buygo-line-notify 是否啟用
		if ( ! class_exists( '\BuygoLineNotify\BuygoLineNotify' ) || ! \BuygoLineNotify\BuygoLineNotify::is_active() ) {
			$this->logger->log( 'error', array(
				'message' => 'BuyGo Line Notify plugin is not active',
				'line_uid' => $line_uid,
				'step' => 'plugin_check',
			), $user_id, $line_uid );
			return;
		}

		// 取得 WordPress 用戶（如果 Hook 沒有提供 user_id，則查詢）
		if ( ! $user_id || $user_id === 0 ) {
			$user = \BuyGoPlus\Core\BuyGoPlus_Core::line()->get_user_by_line_uid( $line_uid );
			if ( ! $user ) {
				// 用戶未綁定 - 這種情況不應該發生，因為 shouldBotRespond 已經過濾
				// 但保留此檢查以防萬一
				$this->logger->log( 'error', array(
					'message' => 'User not bound (should not reach here after shouldBotRespond)',
					'line_uid' => $line_uid,
					'step' => 'user_lookup',
				), null, $line_uid );
				return;
			}
			$user_id = $user->ID;
		} else {
			$user = get_userdata( $user_id );
		}

		// 檢查賣家權限
		if ( ! $this->can_upload_product( $user ) ) {
			$this->logger->log( 'permission_denied', array(
				'message' => 'User does not have permission to upload products',
				'user_id' => $user->ID,
				'user_login' => $user->user_login,
				'roles' => $user->roles ?? [],
				'display_name' => $user->display_name,
			), $user->ID, $line_uid );

			// 取得虛擬商品購買連結
			$seller_product_id = get_option( 'buygo_seller_product_id' );
			$purchase_url = $seller_product_id ? home_url( "/product/{$seller_product_id}/?openExternalBrowser=1" ) : home_url();

			$template = \BuyGoPlus\Services\NotificationTemplates::get( 'system_permission_denied', array(
				'display_name' => $user->display_name ?: $user->user_login,
				'purchase_url' => $purchase_url,
			) );
			$message = $template && isset( $template['line']['text'] ) ? $template['line']['text'] : '抱歉，您目前沒有商品上傳權限。請聯絡管理員開通權限。';
			$this->send_reply_via_facade( $event['replyToken'] ?? '', $message, $line_uid );
			return;
		}

		// 下載圖片到 Media Library
		$this->logger->log( 'image_download_start', array(
			'message_id' => $message_id,
			'user_id' => $user->ID,
			'line_uid' => $line_uid,
			'step' => 'download_image',
		), $user->ID, $line_uid );

		$imageService = \BuygoLineNotify\BuygoLineNotify::image_uploader();
		// 跳過縮圖生成以加速處理，避免 Reply Token 過期（主機會自動處理縮圖）
		$attachment_id = $imageService->download_and_upload( $message_id, $user->ID, true );

		if ( is_wp_error( $attachment_id ) ) {
			$this->logger->log( 'error', array(
				'message' => 'Image upload failed',
				'error' => $attachment_id->get_error_message(),
			), $user->ID, $line_uid );

			$template = \BuyGoPlus\Services\NotificationTemplates::get( 'system_image_upload_failed', array(
				'display_name' => $user->display_name ?: $user->user_login,
			) );
			$message = $template && isset( $template['line']['text'] ) ? $template['line']['text'] : '圖片上傳失敗，請稍後再試。';
			$this->send_reply_via_facade( $event['replyToken'] ?? '', $message, $line_uid );
			return;
		}

		$this->logger->log( 'image_uploaded_success', array(
			'attachment_id' => $attachment_id,
			'user_id' => $user->ID,
			'step' => 'image_uploaded',
		), $user->ID, $line_uid );

		// 儲存上傳狀態到 user_meta
		update_user_meta( $user->ID, 'pending_product_image', $attachment_id );
		update_user_meta( $user->ID, 'pending_product_timestamp', time() );

		// 發送商品類型選單 Flex Message
		// 優先使用後台設定的模板 (flex_image_upload_menu)，如果不存在則使用 LineFlexTemplates 的硬編碼模板
		$this->logger->log( 'sending_product_type_menu', array(
			'user_id' => $user->ID,
			'step' => 'send_reply',
			'template_key' => 'flex_image_upload_menu',
		), $user->ID, $line_uid );

		// 嘗試從 NotificationTemplates 系統取得後台設定的模板
		$template = \BuyGoPlus\Services\NotificationTemplates::get( 'flex_image_upload_menu', [] );

		if ( $template && isset( $template['line']['flex_template'] ) ) {
			// 使用後台設定的模板
			$this->logger->log( 'using_custom_template', array(
				'template_key' => 'flex_image_upload_menu',
				'source' => 'NotificationTemplates',
			), $user->ID, $line_uid );

			$flexMessage = \BuyGoPlus\Services\NotificationTemplates::build_flex_message( $template['line']['flex_template'] );
		} else {
			// Fallback: 使用 LineFlexTemplates 的硬編碼模板
			$this->logger->log( 'using_fallback_template', array(
				'template_key' => 'flex_image_upload_menu',
				'source' => 'LineFlexTemplates',
				'reason' => 'custom_template_not_found',
			), $user->ID, $line_uid );

			$flexContents = LineFlexTemplates::getProductTypeMenu();
			$flexMessage = array(
				'type' => 'flex',
				'altText' => '請選擇商品類型',
				'contents' => $flexContents,
			);
		}

		// 使用 send_reply_via_facade() 以支援 Reply Token 過期時自動切換到 Push API
		$result = $this->send_reply_via_facade( $event['replyToken'] ?? '', $flexMessage, $line_uid );

		if ( ! $result ) {
			$this->logger->log( 'error', array(
				'message' => 'Failed to send product type menu',
			), $user->ID, $line_uid );
		} else {
			$this->logger->log( 'product_type_menu_sent', array(
				'user_id' => $user->ID,
			), $user->ID, $line_uid );
		}
	}

	/**
	 * Process webhook events
	 *
	 * @param array $events Events array
	 * @param bool $return_response Whether to return response (false if response already sent)
	 * @return \WP_REST_Response|null
	 */
	public function process_events( $events, $return_response = true ) {
		// Prevent client disconnect from terminating script
		ignore_user_abort( true );
		set_time_limit( 0 );

		// Log webhook received
		$this->logger->log( 'webhook_received', array( 'event_count' => count( $events ) ) );

		foreach ( $events as $event ) {
			// Check for Verify Event (Dummy Token)
			$reply_token = isset( $event['replyToken'] ) ? $event['replyToken'] : '';
			if ( '00000000000000000000000000000000' === $reply_token ) {
				// Verification event - no need to process
				if ( $return_response ) {
					return rest_ensure_response( array( 'success' => true ) );
				}
				return null;
			}

			// Deduplication using Webhook Event ID
			$event_id = isset( $event['webhookEventId'] ) ? $event['webhookEventId'] : '';
			if ( $event_id ) {
				$cache_key = 'buygo_line_event_' . $event_id;
				if ( get_transient( $cache_key ) ) {
					continue;
				}
				set_transient( $cache_key, true, 60 );
			}

			$this->handle_event( $event );
		}

		if ( $return_response ) {
			return rest_ensure_response( array( 'success' => true ) );
		}
		
		return null;
	}

	/**
	 * Handle event
	 *
	 * @param array $event Event data
	 */
	private function handle_event( $event ) {
		$event_type = $event['type'] ?? '';

		switch ( $event_type ) {
			case 'message':
				$this->handle_message( $event );
				break;

			case 'follow':
				$this->handle_follow( $event );
				break;

			case 'unfollow':
				$this->handle_unfollow( $event );
				break;

			default:
				// Unhandled event type
				break;
		}
	}

	/**
	 * Handle message event
	 *
	 * @param array $event Event data
	 */
	private function handle_message( $event ) {
		$message_type = $event['message']['type'] ?? '';
		$reply_token  = $event['replyToken'] ?? '';

		switch ( $message_type ) {
			case 'image':
				// 圖片訊息由 buygo-line-notify 的 hook 觸發 handleImageUpload() 處理
				// 不要在這裡重複處理，避免發送兩次訊息
				// $this->handle_image_message( $event );
				break;

			case 'text':
				$this->handle_text_message( $event );
				break;

			default:
				// Unhandled message type
				break;
		}
	}

	/**
	 * Handle image message
	 *
	 * @param array $event Event data
	 */
	private function handle_image_message( $event ) {
		$message_id  = $event['message']['id'] ?? '';
		$line_uid    = $event['source']['userId'] ?? '';
		$reply_token = $event['replyToken'] ?? '';

		// Log image message received
		$this->logger->log( 'image_uploaded', array(
			'message_id' => $message_id,
			'line_uid' => $line_uid,
		), null, $line_uid );

		// Get WordPress user from LINE UID
		// 使用新外掛的 BuyGoPlus_Core（不再依賴舊外掛）
		$user = \BuyGoPlus\Core\BuyGoPlus_Core::line()->get_user_by_line_uid( $line_uid );

		if ( ! $user ) {
			// User not bound
			$this->logger->log( 'error', array(
				'message' => 'User not bound',
				'line_uid' => $line_uid,
				'step' => 'user_lookup',
			), null, $line_uid );

			$template = \BuyGoPlus\Services\NotificationTemplates::get( 'system_account_not_bound', [] );
			$message = $template && isset( $template['line']['text'] ) ? $template['line']['text'] : '請先使用 LINE Login 綁定您的帳號。';
			$this->send_reply_via_facade( $reply_token, $message, $line_uid );
			return;
		}

		$this->logger->log( 'user_found', array(
			'user_id' => $user->ID,
			'line_uid' => $line_uid,
			'step' => 'user_lookup',
		), $user->ID, $line_uid );

		// Check permissions
		// 允許三種人上傳：
		// 1. WordPress 管理員（administrator）
		// 2. buygo 管理員（buygo_admin）
		// 3. buygo_helper 小幫手（buygo_helper 角色或 wp_buygo_helpers 資料表中）
		if ( ! $this->can_upload_product( $user ) ) {
			// 記錄權限被拒絕的詳細資訊
			$this->logger->log( 'permission_denied', array(
				'message' => 'User does not have permission to upload products',
				'user_id' => $user->ID,
				'user_login' => $user->user_login,
				'roles' => $user->roles ?? [],
				'display_name' => $user->display_name,
			), $user->ID, $line_uid );

			// 取得虛擬商品購買連結
			$seller_product_id = get_option( 'buygo_seller_product_id' );
			$purchase_url = $seller_product_id ? home_url( "/product/{$seller_product_id}/?openExternalBrowser=1" ) : home_url();

			// 發送權限不足訊息給用戶（不再是 silent）
			$template = \BuyGoPlus\Services\NotificationTemplates::get( 'system_permission_denied', array(
				'display_name' => $user->display_name ?: $user->user_login,
				'purchase_url' => $purchase_url,
			) );
			$message = $template && isset( $template['line']['text'] ) ? $template['line']['text'] : '抱歉，您目前沒有商品上傳權限。請聯絡管理員開通權限。';
			$this->send_reply_via_facade( $reply_token, $message, $line_uid );
			return;
		}

		$this->logger->log( 'permission_granted', array(
			'user_id' => $user->ID,
			'roles' => $user->roles ?? [],
		), $user->ID, $line_uid );

		// 檢查 buygo-line-notify 是否啟用
		if ( ! class_exists( '\BuygoLineNotify\BuygoLineNotify' ) || ! \BuygoLineNotify\BuygoLineNotify::is_active() ) {
			$this->logger->log( 'error', array(
				'message' => 'BuyGo Line Notify plugin is not active',
				'line_uid' => $line_uid,
				'step' => 'plugin_check',
			), $user->ID, $line_uid );

			$template = \BuyGoPlus\Services\NotificationTemplates::get( 'system_image_upload_failed', array(
				'display_name' => $user->display_name ?: $user->user_login,
			) );
			$message = $template && isset( $template['line']['text'] ) ? $template['line']['text'] : '圖片上傳功能暫時無法使用，請聯絡管理員。';
			// 無法使用 Facade，直接返回
			return;
		}

		$this->logger->log( 'image_download_start', array(
			'message_id' => $message_id,
			'user_id' => $user->ID,
			'line_uid' => $line_uid,
			'step' => 'download_image',
		), $user->ID, $line_uid );

		// 使用 buygo-line-notify 的 ImageUploader
		$image_uploader = \BuygoLineNotify\BuygoLineNotify::image_uploader();
		$attachment_id = $image_uploader->download_and_upload( $message_id, $user->ID );

		if ( is_wp_error( $attachment_id ) ) {
			$this->logger->log( 'error', array(
				'message' => 'Image upload failed',
				'error' => $attachment_id->get_error_message(),
			), $user->ID, $line_uid );

			$template = \BuyGoPlus\Services\NotificationTemplates::get( 'system_image_upload_failed', array(
				'display_name' => $user->display_name ?: $user->user_login,
			) );
			$message = $template && isset( $template['line']['text'] ) ? $template['line']['text'] : '圖片上傳失敗，請稍後再試。';
			$this->send_reply_via_facade( $reply_token, $message, $line_uid );
			return;
		}

		$this->logger->log( 'image_uploaded_success', array(
			'attachment_id' => $attachment_id,
			'user_id' => $user->ID,
			'step' => 'image_uploaded',
		), $user->ID, $line_uid );

		// Send Flex Message menu
		$this->logger->log( 'template_lookup_start', array(
			'template_key' => 'flex_image_upload_menu',
			'step' => 'send_reply',
		), $user->ID, $line_uid );

		$template = \BuyGoPlus\Services\NotificationTemplates::get('flex_image_upload_menu', []);
		
		if ( $template && isset( $template['line']['flex_template'] ) ) {
			$this->logger->log( 'flex_template_found', array(
				'template_key' => 'flex_image_upload_menu',
				'step' => 'send_reply',
			), $user->ID, $line_uid );

			$flex_message = \BuyGoPlus\Services\NotificationTemplates::build_flex_message( $template['line']['flex_template'] );
			$this->send_reply_via_facade( $reply_token, $flex_message, $line_uid );
		} else {
			$this->logger->log( 'flex_template_not_found', array(
				'template_key' => 'flex_image_upload_menu',
				'step' => 'send_reply_fallback',
			), $user->ID, $line_uid );

			// Fallback to text message if flex template not found
			$this->send_reply_via_facade( $reply_token, '圖片已收到！請發送商品資訊。', $line_uid );
		}
	}

	/**
	 * Handle text message
	 *
	 * @param array $event Event data
	 */
	private function handle_text_message( $event ) {
		$text        = $event['message']['text'] ?? '';
		$line_uid     = $event['source']['userId'] ?? '';
		$reply_token  = $event['replyToken'] ?? '';

		// 綁定碼流程（用 Messaging API 的 source.userId，確保可推播）
		// 支援：
		// - 直接輸入 6 位數：123456
		// - 或輸入：綁定 123456 / bind 123456
		$maybe_code = null;
		if ( preg_match( '/^\s*(?:綁定|bind)?\s*([0-9]{6})\s*$/iu', $text, $m ) ) {
			$maybe_code = $m[1];
		}

		if ( $maybe_code ) {
			$line_service = new LineService();
			$verify = $line_service->verify_binding_code( $maybe_code, $line_uid );

			if ( is_wp_error( $verify ) ) {
				$this->logger->log( 'binding_failed', array(
					'code' => $maybe_code,
					'line_uid' => $line_uid,
					'error_code' => $verify->get_error_code(),
					'error_message' => $verify->get_error_message(),
				), null, $line_uid );

				$this->send_reply_via_facade( $reply_token, '綁定失敗：' . $verify->get_error_message(), $line_uid );
				return;
			}

			$user_id = intval( $verify['user_id'] ?? 0 );

			if ( $user_id > 0 ) {
				// 向後相容：同步寫入 user_meta（其他模組可能依賴）
				update_user_meta( $user_id, 'buygo_line_user_id', $line_uid );
			}

			$this->logger->log( 'binding_completed', array(
				'code' => $maybe_code,
				'user_id' => $user_id,
				'line_uid' => $line_uid,
			), $user_id ?: null, $line_uid );

			$this->send_reply_via_facade( $reply_token, '綁定成功！之後下單與出貨通知都會推播到這個 LINE。', $line_uid );
			return;
		}

		// Log text message received
		$this->logger->log( 'text_message_received', array(
			'text' => substr( $text, 0, 100 ), // Log first 100 characters
			'line_uid' => $line_uid,
		), null, $line_uid );

		// 優先檢查關鍵字回應系統（後台設定的關鍵字模板）
		$keyword_reply = $this->handle_keyword_reply( $text, $line_uid );
		if ( $keyword_reply !== null ) {
			$this->send_reply_via_facade( $reply_token, $keyword_reply, $line_uid );
			return;
		}

		// 檢查是否為系統指令（由 LineKeywordResponder filter 處理）
		// 這些指令會透過 buygo_line_notify/get_response filter 處理
		// 此處不處理，讓 buygo-line-notify 的 filter 機制接手
		$system_commands = array( '/id', '/綁定', '/狀態', '/help', '/說明', '/指令' );
		if ( in_array( strtolower( trim( $text ) ), $system_commands, true ) ) {
			$this->logger->log( 'system_command_detected', array(
				'command'  => $text,
				'line_uid' => $line_uid,
			), null, $line_uid );
			return; // 不處理，讓 filter 機制處理
		}

		// Get WordPress user from LINE UID（提前取得，供後續命令和商品資訊處理使用）
		// 使用新外掛的 BuyGoPlus_Core（不再依賴舊外掛）
		$user = \BuyGoPlus\Core\BuyGoPlus_Core::line()->get_user_by_line_uid( $line_uid );

		// 如果關鍵字回應系統沒有匹配，再檢查是否為命令
		if ( $this->product_data_parser->is_command( $text ) ) {
			$user_id = $user ? $user->ID : 0;
			$this->handle_command( $text, $reply_token, $line_uid, $user_id );
			return;
		}

		if ( ! $user ) {
			$template = \BuyGoPlus\Services\NotificationTemplates::get( 'system_account_not_bound', [] );
			$message = $template && isset( $template['line']['text'] ) ? $template['line']['text'] : '請先使用 LINE Login 綁定您的帳號。';
			$this->send_reply_via_facade( $reply_token, $message, $line_uid );
			return;
		}

		// Check permissions (使用統一的權限檢查方法)
		if ( ! $this->can_upload_product( $user ) ) {
			// 記錄權限被拒絕的詳細資訊
			$this->logger->log( 'permission_denied', array(
				'message' => 'User does not have permission to upload products',
				'user_id' => $user->ID,
				'user_login' => $user->user_login,
				'roles' => $user->roles ?? [],
				'display_name' => $user->display_name,
				'message_type' => 'text',
			), $user->ID, $line_uid );

			// 取得虛擬商品購買連結
			$seller_product_id = get_option( 'buygo_seller_product_id' );
			$purchase_url = $seller_product_id ? home_url( "/product/{$seller_product_id}/?openExternalBrowser=1" ) : home_url();

			// 發送權限不足訊息給用戶（不再是 silent）
			$template = \BuyGoPlus\Services\NotificationTemplates::get( 'system_permission_denied', array(
				'display_name' => $user->display_name ?: $user->user_login,
				'purchase_url' => $purchase_url,
			) );
			$message = $template && isset( $template['line']['text'] ) ? $template['line']['text'] : '抱歉，您目前沒有商品上傳權限。請聯絡管理員開通權限。';
			$this->send_reply_via_facade( $reply_token, $message, $line_uid );
			return;
		}

		// Parse product data
		$product_data = $this->product_data_parser->parse( $text );
		$validation   = $this->product_data_parser->validate( $product_data );

		if ( ! $validation['valid'] ) {
			// 清除待處理狀態，避免無限循環
			// 當用戶發送的訊息不是有效的商品資訊時，應該清除待處理狀態
			// 讓用戶可以重新開始上架流程
			if ( class_exists( '\BuygoLineNotify\BuygoLineNotify' ) && \BuygoLineNotify\BuygoLineNotify::is_active() ) {
				$image_uploader = \BuygoLineNotify\BuygoLineNotify::image_uploader();
				$image_uploader->clear_temp_images( $user->ID );
			}
			delete_user_meta( $user->ID, 'pending_product_image' );
			delete_user_meta( $user->ID, 'pending_product_type' );
			delete_user_meta( $user->ID, 'pending_product_timestamp' );

			$this->logger->log( 'product_validation_failed_cleared_state', array(
				'missing_fields' => $validation['missing'],
				'user_id' => $user->ID,
			), $user->ID, $line_uid );

			// 不發送「資料不完整」訊息 - 用戶可能只是在普通對話
			// 狀態已清除，用戶可以重新開始上架流程
			return;
		}

		// 判斷商品擁有者（賣家 ID）
		// 如果是小幫手上架，商品擁有者應該是賣家，而不是小幫手
		$seller_id = $this->get_product_owner( $user );

		// Add user_id (seller_id), uploader_id, and line_uid to product data
		$product_data['user_id'] = $seller_id;  // 商品擁有者（賣家 ID）
		$product_data['uploader_id'] = $user->ID;  // 實際上架者（可能是小幫手）
		$product_data['line_uid'] = $line_uid;

		// Get temporary images
		// 檢查 buygo-line-notify 是否啟用
		$image_ids = array();
		if ( class_exists( '\BuygoLineNotify\BuygoLineNotify' ) && \BuygoLineNotify\BuygoLineNotify::is_active() ) {
			$image_uploader = \BuygoLineNotify\BuygoLineNotify::image_uploader();
			$image_ids = $image_uploader->get_temp_images( $user->ID );
			
			// 將第一個圖片 ID 加入 product_data（FluentCartService 會使用）
			if ( ! empty( $image_ids ) ) {
				$product_data['image_attachment_id'] = $image_ids[0];
			}
		}

		// Log product creation attempt
		$this->logger->log( 'product_creating', array(
			'product_name' => $product_data['name'] ?? '',
			'user_id' => $user->ID,
		), $user->ID, $line_uid );

		// Create product using FluentCart Service
		$fluentcart_service = new FluentCartService();
		$post_id = $fluentcart_service->create_product( $product_data, $image_ids );

		if ( is_wp_error( $post_id ) ) {
			$this->logger->log( 'error', array(
				'message' => 'Product creation failed',
				'error' => $post_id->get_error_message(),
				'product_data' => $product_data,
			), $user->ID, $line_uid );

			$template = \BuyGoPlus\Services\NotificationTemplates::get( 'system_product_publish_failed', array(
				'error_message' => $post_id->get_error_message(),
			) );
			$message = $template && isset( $template['line']['text'] ) ? $template['line']['text'] : '商品建立失敗：' . $post_id->get_error_message();
			$this->send_reply_via_facade( $reply_token, $message, $line_uid );
			return;
		}

		// Clear temporary images
		if ( ! empty( $image_ids ) && class_exists( '\BuygoLineNotify\BuygoLineNotify' ) && \BuygoLineNotify\BuygoLineNotify::is_active() ) {
			$image_uploader = \BuygoLineNotify\BuygoLineNotify::image_uploader();
			$image_uploader->clear_temp_images( $user->ID );
		}

		// Log success
		$this->logger->log( 'product_created', array(
			'product_id' => $post_id,
			'product_name' => $product_data['name'] ?? '',
			'user_id' => $user->ID,
		), $user->ID, $line_uid );

		// Get product URL
		// 強制使用 /item/{post_id} 格式（短連結）
		// 不使用 get_permalink() 因為它會返回商品名稱的 slug，不是我們要的短連結格式
		$product_url = home_url( "/item/{$post_id}?openExternalBrowser=1" );

		// 記錄日誌以便除錯
		$this->logger->log( 'product_url_generated', array(
			'product_id' => $post_id,
			'permalink' => get_permalink( $post_id ),
			'short_url' => $product_url,
		), $user->ID, $line_uid );

		// Prepare template arguments
		// 根據幣別設定符號（支援多種貨幣）
		$currency = $product_data['currency'] ?? 'TWD';
		$currency_map = array(
			'JPY' => 'JPY',
			'日幣' => 'JPY',
			'TWD' => 'NT$',
			'台幣' => 'NT$',
			'USD' => 'US$',
			'美金' => 'US$',
			'CNY' => '¥',
			'人民幣' => '¥',
			'EUR' => '€',
			'歐元' => '€',
			'KRW' => '₩',
			'韓幣' => '₩',
		);
		$currency_symbol = isset( $currency_map[ $currency ] ) ? $currency_map[ $currency ] : $currency;
		
		// 產生原價區塊（如果有原價）
		// 支援多樣式產品的多個原價（用斜線分隔顯示）
		$original_price_section = '';
		if ( ! empty( $product_data['original_price'] ) || ! empty( $product_data['compare_price'] ) ) {
			// 如果是多樣式產品，顯示所有原價
			if ( ! empty( $product_data['variations'] ) && is_array( $product_data['variations'] ) ) {
				$original_prices = array();
				foreach ( $product_data['variations'] as $variation ) {
					if ( ! empty( $variation['compare_price'] ) ) {
						$original_prices[] = number_format( $variation['compare_price'] );
					}
				}
				if ( ! empty( $original_prices ) ) {
					$original_price_section = "\n原價：{$currency_symbol} " . implode( '/', $original_prices );
				}
			} else {
				// 單一商品的原價
				$original_price = $product_data['original_price'] ?? $product_data['compare_price'] ?? 0;
				if ( $original_price > 0 ) {
					$original_price_section = "\n原價：{$currency_symbol} " . number_format( $original_price );
				}
			}
		}
		
		// 產生分類區塊（如果有分類）
		// 支援多樣式產品的多個分類（用斜線分隔顯示）
		$category_section = '';
		if ( ! empty( $product_data['variations'] ) && is_array( $product_data['variations'] ) ) {
			// 多樣式產品：顯示所有分類
			$categories = array();
			foreach ( $product_data['variations'] as $variation ) {
				if ( ! empty( $variation['name'] ) ) {
					$categories[] = $variation['name'];
				}
			}
			if ( ! empty( $categories ) ) {
				$category_section = "\n分類：" . implode( '/', $categories );
			}
		} elseif ( ! empty( $product_data['category'] ) ) {
			// 單一商品的分類
			$category_section = "\n分類：{$product_data['category']}";
		}
		
		// 產生到貨日期區塊（如果有到貨日期）
		$arrival_date_section = '';
		if ( ! empty( $product_data['arrival_date'] ) ) {
			// 格式化日期顯示（如果是 YYYY-MM-DD 格式，轉換為 YYYY/MM/DD）
			$arrival_date = $product_data['arrival_date'];
			if ( preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $arrival_date, $matches ) ) {
				$arrival_date = "{$matches[1]}/{$matches[2]}/{$matches[3]}";
			}
			$arrival_date_section = "\n到貨日期：{$arrival_date}";
		}
		
		// 產生預購日期區塊（如果有預購日期）
		$preorder_date_section = '';
		if ( ! empty( $product_data['preorder_date'] ) ) {
			// 格式化日期顯示（如果是 YYYY-MM-DD 格式，轉換為 YYYY/MM/DD）
			$preorder_date = $product_data['preorder_date'];
			if ( preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $preorder_date, $matches ) ) {
				$preorder_date = "{$matches[1]}/{$matches[2]}/{$matches[3]}";
			}
			$preorder_date_section = "\n預購日期：{$preorder_date}";
		}
		
		// 處理多樣式產品的價格和數量顯示
		$price_display = '';
		$quantity_display = '';
		
		if ( ! empty( $product_data['variations'] ) && is_array( $product_data['variations'] ) ) {
			// 多樣式產品：顯示所有價格和數量（用斜線分隔）
			$prices = array();
			$quantities = array();
			foreach ( $product_data['variations'] as $variation ) {
				$variation_price = $variation['price'] ?? $product_data['price'] ?? 0;
				$variation_quantity = $variation['quantity'] ?? 0;
				$prices[] = number_format( $variation_price );
				$quantities[] = $variation_quantity;
			}
			$price_display = implode( '/', $prices );
			// 注意：模板中已經有「個」字，所以這裡只傳數字
			$quantity_display = implode( '/', $quantities );
		} else {
			// 單一商品
			$price_display = number_format( $product_data['price'] ?? 0 );
			// 注意：模板中已經有「個」字，所以這裡只傳數字
			$quantity_display = $product_data['quantity'] ?? 0;
		}

		// 計算 original_price 變數（用於向後兼容舊模板）
		$original_price_value = '';
		if ( ! empty( $product_data['original_price'] ) || ! empty( $product_data['compare_price'] ) ) {
			$original_price_value = number_format( $product_data['original_price'] ?? $product_data['compare_price'] ?? 0 );
		}

		$template_args = array(
			'product_name' => $product_data['name'] ?? '',
			'price' => $price_display,
			'quantity' => $quantity_display,
			'product_url' => $product_url,
			'currency_symbol' => $currency_symbol,
			// 同時提供 original_price 和 original_price_section 以保持向後兼容
			'original_price' => $original_price_value,
			'original_price_section' => $original_price_section,
			'category_section' => $category_section,
			'arrival_date_section' => $arrival_date_section,
			'preorder_date_section' => $preorder_date_section,
		);

		$template = \BuyGoPlus\Services\NotificationTemplates::get( 'system_product_published', $template_args );
		$message = $template && isset( $template['line']['text'] ) ? $template['line']['text'] : '商品建立成功';
		$this->send_reply_via_facade( $reply_token, $message, $line_uid );
	}

	/**
	 * 取得商品上架順序（用於生成短連結）
	 * 參考舊外掛邏輯：依據該使用者上架商品的順序生成短連結
	 *
	 * @param int $user_id WordPress 使用者 ID
	 * @param int $product_id 商品 ID
	 * @return int 上架順序（從 1 開始）
	 */
	private function get_listing_order( $user_id, $product_id ) {
		global $wpdb;

		// 查詢該使用者上架的所有商品（使用 post_date 排序，因為是上架順序）
		// 計算在這個商品之前（包含自己）有多少個商品
		$product = get_post( $product_id );
		if ( ! $product ) {
			return $product_id; // Fallback
		}

		$count = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->posts} 
			WHERE post_author = %d 
			AND post_type = 'fluent-products' 
			AND (
				post_date < %s 
				OR (post_date = %s AND ID <= %d)
			)",
			$user_id,
			$product->post_date,
			$product->post_date,
			$product_id
		) );

		// 如果查詢失敗，使用商品 ID 作為順序（fallback）
		return $count > 0 ? intval( $count ) : $product_id;
	}

	/**
	 * 處理關鍵字回應（從後台設定的關鍵字模板讀取）
	 *
	 * @param string $text 使用者輸入的文字
	 * @param string $line_uid LINE UID
	 * @return string|null 如果有匹配的關鍵字，返回回應訊息；否則返回 null
	 */
	private function handle_keyword_reply( $text, $line_uid ) {
		$keywords = get_option( 'buygo_line_keywords', [] );
		
		if ( empty( $keywords ) || ! is_array( $keywords ) ) {
			return null;
		}

		$text_trimmed = trim( $text );

		// 檢查是否匹配關鍵字或別名
		foreach ( $keywords as $keyword_data ) {
			$keyword = trim( $keyword_data['keyword'] ?? '' );
			$aliases = $keyword_data['aliases'] ?? [];
			$message = $keyword_data['message'] ?? '';

			// 檢查是否匹配主關鍵字
			if ( $text_trimmed === $keyword ) {
				$this->logger->log( 'keyword_matched', array(
					'keyword' => $keyword,
					'line_uid' => $line_uid,
				), null, $line_uid );
				return $message;
			}

			// 檢查是否匹配別名
			foreach ( $aliases as $alias ) {
				$alias_trimmed = trim( $alias );
				if ( $text_trimmed === $alias_trimmed ) {
					$this->logger->log( 'keyword_alias_matched', array(
						'keyword' => $keyword,
						'alias' => $alias,
						'line_uid' => $line_uid,
					), null, $line_uid );
					return $message;
				}
			}
		}

		return null;
	}

	/**
	 * Handle command
	 *
	 * @param string $command Command text
	 * @param string $reply_token Reply token
	 * @param string $line_uid LINE UID
	 * @param int    $user_id WordPress user ID
	 */
	private function handle_command( $command, $reply_token, $line_uid = null, $user_id = 0 ) {
		$command = trim( $command );

		// Handle /one command - 從模板系統讀取
		if ( $command === '/one' ) {
			// 設定商品類型為單一商品
			if ( $user_id > 0 ) {
				update_user_meta( $user_id, 'pending_product_type', 'simple' );
				$this->logger->log( 'product_type_set_by_command', array(
					'command' => '/one',
					'type'    => 'simple',
				), $user_id, $line_uid );
			}

			$template = \BuyGoPlus\Services\NotificationTemplates::get( 'system_command_one_template', [] );
			$message = $template && isset( $template['line']['text'] )
				? $template['line']['text']
				: "📋 複製以下格式發送：\n\n商品名稱\n價格：\n數量：";
			$this->send_reply_via_facade( $reply_token, $message, $line_uid );
			return;
		}

		// Handle /many command - 從模板系統讀取
		if ( $command === '/many' ) {
			// 設定商品類型為多樣式商品
			if ( $user_id > 0 ) {
				update_user_meta( $user_id, 'pending_product_type', 'variable' );
				$this->logger->log( 'product_type_set_by_command', array(
					'command' => '/many',
					'type'    => 'variable',
				), $user_id, $line_uid );
			}

			$template = \BuyGoPlus\Services\NotificationTemplates::get( 'system_command_many_template', [] );
			$message = $template && isset( $template['line']['text'] )
				? $template['line']['text']
				: "📋 複製以下格式發送 (多樣)：\n\n商品名稱\n價格：\n數量：\n款式1：\n款式2：";
			$this->send_reply_via_facade( $reply_token, $message, $line_uid );
			return;
		}

		// Handle /help
		if ( in_array( $command, array( '/help', '/幫助', '?help', '幫助' ), true ) ) {
			$this->send_help( $reply_token );
			return;
		}
	}

	/**
	 * Handle follow event
	 *
	 * @param array $event Event data
	 */
	private function handle_follow( $event ) {
		$reply_token = $event['replyToken'] ?? '';
		$template = \BuyGoPlus\Services\NotificationTemplates::get( 'system_line_follow', [] );
		$message = $template && isset( $template['line']['text'] ) ? $template['line']['text'] : "歡迎使用 BuyGo 商品上架 🎉";
		$this->send_reply_via_facade( $reply_token, $message );
	}

	/**
	 * Handle unfollow event
	 *
	 * @param array $event Event data
	 */
	private function handle_unfollow( $event ) {
		// Silent processing
	}

	/**
	 * Send reply message via buygo-line-notify Facade
	 *
	 * @param string $reply_token Reply token
	 * @param string|array $message Message content
	 * @param string $line_uid LINE user ID (optional, for logging)
	 * @return bool
	 */
	private function send_reply_via_facade( $reply_token, $message, $line_uid = null ) {
		// 檢查 buygo-line-notify 是否啟用
		if ( ! class_exists( '\BuygoLineNotify\BuygoLineNotify' ) || ! \BuygoLineNotify\BuygoLineNotify::is_active() ) {
			$this->logger->log( 'error', array(
				'message' => 'BuyGo Line Notify plugin is not active, cannot send reply',
				'action' => 'send_reply_via_facade',
			), null, $line_uid );
			return false;
		}

		// 使用 buygo-line-notify 的 LineMessagingService
		$messaging = \BuygoLineNotify\BuygoLineNotify::messaging();

		// 先嘗試 Reply（如果 Token 有效）
		$result = false;
		if ( ! empty( $reply_token ) ) {
			$result = $messaging->send_reply( $reply_token, $message, $line_uid );
		}

		// 如果 Reply 失敗（Token 無效或為空），改用 Push Message
		if ( is_wp_error( $result ) || empty( $reply_token ) ) {
			if ( is_wp_error( $result ) ) {
				$this->logger->log( 'reply_failed_fallback_to_push', array(
					'error' => $result->get_error_message(),
					'fallback' => 'push_message',
				), null, $line_uid );
			}

			// 確保有 LINE UID 才能使用 Push
			if ( ! empty( $line_uid ) ) {
				// 將訊息包裝成 LINE 訊息格式
				$push_message = is_array( $message ) ? $message : array(
					'type' => 'text',
					'text' => $message,
				);

				$result = $messaging->push_message( $line_uid, $push_message );

				if ( is_wp_error( $result ) ) {
					$this->logger->log( 'error', array(
						'message' => 'Failed to send LINE message (both reply and push failed)',
						'error' => $result->get_error_message(),
						'action' => 'send_reply_via_facade',
					), null, $line_uid );
					return false;
				}
			} else {
				$this->logger->log( 'error', array(
					'message' => 'Cannot send message: no reply token and no LINE UID',
					'action' => 'send_reply_via_facade',
				), null, $line_uid );
				return false;
			}
		}

		return $result;
	}

	/**
	 * Get field names
	 *
	 * @param array $fields Field array
	 * @return array
	 */
	private function get_field_names( $fields ) {
		$names = array();
		foreach ( $fields as $field ) {
			switch ( $field ) {
				case 'name':
					$names[] = '商品名稱';
					break;
				case 'price':
					$names[] = '價格';
					break;
				case 'quantity':
					$names[] = '數量';
					break;
				default:
					$names[] = $field;
					break;
			}
		}
		return $names;
	}

	/**
	 * Send help message
	 *
	 * @param string $reply_token Reply token
	 */
	private function send_help( $reply_token ) {
		$message  = "📱 商品上架說明\n\n";
		$message .= "【步驟】\n";
		$message .= "1️⃣ 發送商品圖片\n";
		$message .= "2️⃣ 發送商品資訊\n\n";
		$message .= "【必填欄位】\n";
		$message .= "商品名稱\n";
		$message .= "價格：350\n";
		$message .= "數量：20\n\n";
		$message .= "💡 輸入 /分類 查看可用分類";

		$this->send_reply_via_facade( $reply_token, $message );
	}
}

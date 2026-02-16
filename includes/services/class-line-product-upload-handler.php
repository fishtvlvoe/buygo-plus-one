<?php
/**
 * LINE Product Upload Handler
 *
 * 圖片上傳流程 + 商品類型選擇
 *
 * @package BuyGoPlus
 */

namespace BuyGoPlus\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class LineProductUploadHandler
 *
 * 職責：
 * - 處理圖片上傳（handleImageUpload）
 * - 處理商品類型選擇 Postback（handlePostback）
 * - 發送輸入格式說明
 */
class LineProductUploadHandler {

	/**
	 * Webhook Logger
	 *
	 * @var WebhookLogger
	 */
	private $logger;

	/**
	 * Permission Validator
	 *
	 * @var LinePermissionValidator
	 */
	private $validator;

	/**
	 * Messaging Facade
	 *
	 * @var LineMessagingFacade
	 */
	private $messaging;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->logger = WebhookLogger::get_instance();
		$this->validator = new LinePermissionValidator();
		$this->messaging = new LineMessagingFacade();
	}

	/**
	 * 處理圖片上傳（監聯 LINE Webhook Hook）
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
		if ( ! $this->validator->shouldBotRespond( $line_uid, $user_id, 'image' ) ) {
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
		if ( ! $this->validator->can_upload_product( $user ) ) {
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
			$this->messaging->send_reply( $event['replyToken'] ?? '', $message, $line_uid );
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
			$this->messaging->send_reply( $event['replyToken'] ?? '', $message, $line_uid );
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

		// 使用 messaging facade 支援 Reply Token 過期時自動切換到 Push API
		$result = $this->messaging->send_reply( $event['replyToken'] ?? '', $flexMessage, $line_uid );

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
		if ( ! $this->validator->shouldBotRespond( $line_uid, $user_id, 'postback' ) ) {
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
			$this->messaging->send_reply( $event['replyToken'] ?? '', $message, $line_uid );

			$this->logger->log( 'product_type_selected', array(
				'type' => $product_type,
			), $user_id, $line_uid );
		}
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
}

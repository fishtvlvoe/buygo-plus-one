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
	 * Logger
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
				"SELECT COUNT(*) FROM {$table_name} WHERE user_id = %d",
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
				$this->handle_image_message( $event );
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
			$this->send_reply( $reply_token, $message, $line_uid );
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

			// 發送權限不足訊息給用戶（不再是 silent）
			$template = \BuyGoPlus\Services\NotificationTemplates::get( 'system_permission_denied', array(
				'display_name' => $user->display_name ?: $user->user_login,
			) );
			$message = $template && isset( $template['line']['text'] ) ? $template['line']['text'] : '抱歉，您目前沒有商品上傳權限。請聯絡管理員開通權限。';
			$this->send_reply( $reply_token, $message, $line_uid );
			return;
		}

		$this->logger->log( 'permission_granted', array(
			'user_id' => $user->ID,
			'roles' => $user->roles ?? [],
		), $user->ID, $line_uid );

		// Download and upload image
		// 取得 Channel Access Token（自動從 buygo_core_settings 或獨立 option 讀取並解密）
		$token = \BuyGoPlus\Services\SettingsService::get( 'line_channel_access_token', '' );

		// Debug: 記錄 token 狀態
		$this->logger->log( 'token_retrieved', array(
			'has_token' => ! empty( $token ),
			'token_length' => ! empty( $token ) ? strlen( $token ) : 0,
			'token_preview' => ! empty( $token ) ? substr( $token, 0, 20 ) . '...' : '[empty]',
			'step' => 'get_token',
		), $user->ID, $line_uid );

		if ( empty( $token ) ) {
			$this->logger->log( 'error', array(
				'message' => 'Channel Access Token is empty',
				'line_uid' => $line_uid,
				'step' => 'get_token',
			), $user->ID, $line_uid );
			return;
		}

		$this->logger->log( 'image_download_start', array(
			'message_id' => $message_id,
			'user_id' => $user->ID,
			'line_uid' => $line_uid,
			'step' => 'download_image',
		), $user->ID, $line_uid );

		$image_uploader = new ImageUploader( $token );
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
			$this->send_reply( $reply_token, $message, $line_uid );
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
			$this->send_reply( $reply_token, $flex_message, $line_uid );
		} else {
			$this->logger->log( 'flex_template_not_found', array(
				'template_key' => 'flex_image_upload_menu',
				'step' => 'send_reply_fallback',
			), $user->ID, $line_uid );

			// Fallback to text message if flex template not found
			$this->send_reply( $reply_token, '圖片已收到！請發送商品資訊。', $line_uid );
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

		// Log text message received
		$this->logger->log( 'text_message_received', array(
			'text' => substr( $text, 0, 100 ), // Log first 100 characters
			'line_uid' => $line_uid,
		), null, $line_uid );

		// 優先檢查關鍵字回應系統（後台設定的關鍵字模板）
		$keyword_reply = $this->handle_keyword_reply( $text, $line_uid );
		if ( $keyword_reply !== null ) {
			$this->send_reply( $reply_token, $keyword_reply, $line_uid );
			return;
		}

		// 如果關鍵字回應系統沒有匹配，再檢查是否為命令
		if ( $this->product_data_parser->is_command( $text ) ) {
			$this->handle_command( $text, $reply_token );
			return;
		}

		// Get WordPress user from LINE UID
		// 使用新外掛的 BuyGoPlus_Core（不再依賴舊外掛）
		$user = \BuyGoPlus\Core\BuyGoPlus_Core::line()->get_user_by_line_uid( $line_uid );

		if ( ! $user ) {
			$template = \BuyGoPlus\Services\NotificationTemplates::get( 'system_account_not_bound', [] );
			$message = $template && isset( $template['line']['text'] ) ? $template['line']['text'] : '請先使用 LINE Login 綁定您的帳號。';
			$this->send_reply( $reply_token, $message, $line_uid );
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

			// 發送權限不足訊息給用戶（不再是 silent）
			$template = \BuyGoPlus\Services\NotificationTemplates::get( 'system_permission_denied', array(
				'display_name' => $user->display_name ?: $user->user_login,
			) );
			$message = $template && isset( $template['line']['text'] ) ? $template['line']['text'] : '抱歉，您目前沒有商品上傳權限。請聯絡管理員開通權限。';
			$this->send_reply( $reply_token, $message, $line_uid );
			return;
		}

		// Parse product data
		$product_data = $this->product_data_parser->parse( $text );
		$validation   = $this->product_data_parser->validate( $product_data );

		if ( ! $validation['valid'] ) {
			$missing_fields = $this->get_field_names( $validation['missing'] );
			$template_args = array(
				'missing_fields' => implode( '、', $missing_fields ),
			);
			$template = \BuyGoPlus\Services\NotificationTemplates::get( 'system_product_data_incomplete', $template_args );
			$message = $template && isset( $template['line']['text'] ) ? $template['line']['text'] : "商品資料不完整，缺少：" . implode( '、', $missing_fields );
			$this->send_reply( $reply_token, $message, $line_uid );
			return;
		}

		// Add user_id and line_uid to product data
		$product_data['user_id'] = $user->ID;
		$product_data['line_uid'] = $line_uid;

		// Get temporary images
		// 取得 Channel Access Token（自動從 buygo_core_settings 或獨立 option 讀取並解密）
		$token = \BuyGoPlus\Services\SettingsService::get( 'line_channel_access_token', '' );
		$image_ids = array();
		if ( ! empty( $token ) ) {
			$image_uploader = new ImageUploader( $token );
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
			$this->send_reply( $reply_token, $message, $line_uid );
			return;
		}

		// Clear temporary images
		if ( ! empty( $token ) && ! empty( $image_ids ) ) {
			$image_uploader = new ImageUploader( $token );
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
		$product_url = home_url( "/item/{$post_id}" );
		
		// 記錄日誌以便除錯
		$this->logger->log( 'product_url_generated', array(
			'product_id' => $post_id,
			'permalink' => get_permalink( $post_id ),
			'short_url' => $product_url,
		), $user->ID, $line_uid );

		// Prepare template arguments
		// 根據幣別設定符號（日幣用 JPY，台幣用 NT$）
		$currency = $product_data['currency'] ?? 'TWD';
		if ( $currency === 'JPY' || $currency === '日幣' ) {
			$currency_symbol = 'JPY';
		} elseif ( $currency === 'TWD' || $currency === '台幣' ) {
			$currency_symbol = 'NT$';
		} else {
			$currency_symbol = $currency;
		}
		
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
		
		// 產生社群連結區塊（如果有社群連結）
		$community_url_section = '';
		if ( ! empty( $product_data['community_url'] ) ) {
			$community_url_section = "\n\n社群討論：\n{$product_data['community_url']}";
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
			'community_url_section' => $community_url_section,
		);

		$template = \BuyGoPlus\Services\NotificationTemplates::get( 'system_product_published', $template_args );
		$message = $template && isset( $template['line']['text'] ) ? $template['line']['text'] : '商品建立成功';
		$this->send_reply( $reply_token, $message, $line_uid );
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
	 */
	private function handle_command( $command, $reply_token ) {
		$command = trim( $command );
		$line_uid = null; // 命令處理時可能沒有 line_uid，先設為 null

		// Handle /one command - 從模板系統讀取
		if ( $command === '/one' ) {
			$template = \BuyGoPlus\Services\NotificationTemplates::get( 'system_command_one_template', [] );
			$message = $template && isset( $template['line']['text'] ) 
				? $template['line']['text'] 
				: "📋 複製以下格式發送：\n\n商品名稱\n價格：\n數量：";
			$this->send_reply( $reply_token, $message, $line_uid );
			return;
		}

		// Handle /many command - 從模板系統讀取
		if ( $command === '/many' ) {
			$template = \BuyGoPlus\Services\NotificationTemplates::get( 'system_command_many_template', [] );
			$message = $template && isset( $template['line']['text'] ) 
				? $template['line']['text'] 
				: "📋 複製以下格式發送 (多樣)：\n\n商品名稱\n價格：\n數量：\n款式1：\n款式2：";
			$this->send_reply( $reply_token, $message, $line_uid );
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
		$this->send_reply( $reply_token, $message );
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
	 * Send reply message
	 *
	 * @param string $reply_token Reply token
	 * @param string|array $message Message content
	 * @param string $line_uid LINE user ID (optional, for logging)
	 * @return bool
	 */
	private function send_reply( $reply_token, $message, $line_uid = null ) {
		// 使用新外掛的 SettingsService（自動從 buygo_core_settings 或獨立 option 讀取並解密）
		$token = \BuyGoPlus\Services\SettingsService::get( 'line_channel_access_token', '' );

		if ( empty( $token ) ) {
			$this->logger->log( 'error', array(
				'message' => 'Channel Access Token is empty',
				'action' => 'send_reply',
			), null, $line_uid );
			return false;
		}

		$url = 'https://api.line.me/v2/bot/message/reply';

		// Handle Text vs Flex/Array
		$messages_payload = [];
		if ( is_array( $message ) ) {
			if ( isset( $message['type'] ) ) {
				$messages_payload = array( $message );
			} else {
				$messages_payload = $message;
			}
		} else {
			$messages_payload = array(
				array(
					'type' => 'text',
					'text' => $message,
				)
			);
		}

		$data = array(
			'replyToken' => $reply_token,
			'messages'   => $messages_payload,
		);

		$response = wp_remote_post(
			$url,
			array(
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $token,
				),
				'body'    => wp_json_encode( $data ),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->logger->log( 'error', array(
				'message' => 'Failed to send LINE reply',
				'error' => $response->get_error_message(),
				'action' => 'send_reply',
				'reply_token' => substr( $reply_token, 0, 10 ) . '...',
			), null, $line_uid );
			return false;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );
		
		if ( $status_code === 200 ) {
			// 記錄成功發送
			$message_type = is_array( $message ) ? ( isset( $message['type'] ) ? $message['type'] : 'array' ) : 'text';
			$this->logger->log( 'reply_sent', array(
				'message' => 'LINE reply sent successfully',
				'message_type' => $message_type,
				'status_code' => $status_code,
			), null, $line_uid );
			return true;
		} else {
			// 記錄失敗
			$this->logger->log( 'error', array(
				'message' => 'LINE API returned error',
				'status_code' => $status_code,
				'response' => $response_body,
				'action' => 'send_reply',
			), null, $line_uid );
			return false;
		}
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

		$this->send_reply( $reply_token, $message );
	}
}

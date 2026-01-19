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
	 * 3. buygo_helper 小幫手（buygo_helper 角色或 buygo_helpers 列表中）
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

		// 3. buygo_helper 小幫手（檢查角色或列表）
		if ( in_array( 'buygo_helper', $roles, true ) ) {
			return true;
		}

		// 檢查是否在小幫手列表中（可能沒有角色但有記錄）
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
		// 3. buygo_helper 小幫手（buygo_helper 角色或 buygo_helpers 列表中）
		if ( ! $this->can_upload_product( $user ) ) {
			// Silent processing for unauthorized users
			$this->logger->log( 'permission_denied', array(
				'message' => 'User does not have permission to upload products',
				'user_id' => $user->ID,
				'roles' => $user->roles ?? [],
			), $user->ID, $line_uid );
			return;
		}

		$this->logger->log( 'permission_granted', array(
			'user_id' => $user->ID,
			'roles' => $user->roles ?? [],
		), $user->ID, $line_uid );

		// Download and upload image
		// 使用新外掛的 BuyGoPlus_Core（不再依賴舊外掛）
		$token = \BuyGoPlus\Core\BuyGoPlus_Core::settings()->get( 'line_channel_access_token', '' );
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
			// Silent processing for unauthorized users
			$this->logger->log( 'permission_denied', array(
				'message' => 'User does not have permission to upload products',
				'user_id' => $user->ID,
				'roles' => $user->roles ?? [],
			), $user->ID, $line_uid );
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
		// 使用新外掛的 BuyGoPlus_Core（不再依賴舊外掛）
		$token = \BuyGoPlus\Core\BuyGoPlus_Core::settings()->get( 'line_channel_access_token', '' );
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
		$product_url = get_permalink( $post_id );

		// Prepare template arguments
		$currency_symbol = $product_data['currency'] === 'TWD' ? 'NT$' : ( $product_data['currency'] ?? 'NT$' );
		
		// 產生原價區塊（如果有原價）
		$original_price_section = '';
		if ( ! empty( $product_data['original_price'] ) || ! empty( $product_data['compare_price'] ) ) {
			$original_price = $product_data['original_price'] ?? $product_data['compare_price'] ?? 0;
			$original_price_section = "\n原價：{$currency_symbol} " . number_format( $original_price );
		}
		
		// 產生分類區塊（如果有分類）
		$category_section = '';
		if ( ! empty( $product_data['category'] ) ) {
			$category_section = "\n分類：{$product_data['category']}";
		}
		
		// 產生到貨日期區塊（如果有到貨日期）
		$arrival_date_section = '';
		if ( ! empty( $product_data['arrival_date'] ) ) {
			$arrival_date_section = "\n到貨日期：{$product_data['arrival_date']}";
		}
		
		// 產生預購日期區塊（如果有預購日期）
		$preorder_date_section = '';
		if ( ! empty( $product_data['preorder_date'] ) ) {
			$preorder_date_section = "\n預購日期：{$product_data['preorder_date']}";
		}
		
		// 產生社群連結區塊（如果有社群連結）
		$community_url_section = '';
		if ( ! empty( $product_data['community_url'] ) ) {
			$community_url_section = "\n\n社群討論：\n{$product_data['community_url']}";
		}
		
		$template_args = array(
			'product_name' => $product_data['name'] ?? '',
			'price' => number_format( $product_data['price'] ?? 0 ),
			'quantity' => $product_data['quantity'] ?? 0,
			'product_url' => $product_url,
			'currency_symbol' => $currency_symbol,
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
		// 使用新外掛的 BuyGoPlus_Core（不再依賴舊外掛）
		$token = \BuyGoPlus\Core\BuyGoPlus_Core::settings()->get( 'line_channel_access_token', '' );

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

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
	 * Process webhook events
	 *
	 * @param array $events Events array
	 * @return \WP_REST_Response
	 */
	public function process_events( $events ) {
		// Prevent client disconnect from terminating script
		ignore_user_abort( true );
		set_time_limit( 0 );

		// Log webhook received
		$this->logger->log( 'webhook_received', array( 'event_count' => count( $events ) ) );

		foreach ( $events as $event ) {
			// Check for Verify Event (Dummy Token)
			$reply_token = isset( $event['replyToken'] ) ? $event['replyToken'] : '';
			if ( '00000000000000000000000000000000' === $reply_token ) {
				return rest_ensure_response( array( 'success' => true ) );
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

		return rest_ensure_response( array( 'success' => true ) );
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
		if ( ! class_exists( 'BuyGo_Core' ) ) {
			return;
		}

		$user = \BuyGo_Core::line()->get_user_by_line_uid( $line_uid );

		if ( ! $user ) {
			// User not bound
			$this->logger->log( 'error', array(
				'message' => 'User not bound',
				'line_uid' => $line_uid,
			), null, $line_uid );

			$template = \BuyGoPlus\Services\NotificationTemplates::get( 'system_account_not_bound', [] );
			$message = $template && isset( $template['line']['text'] ) ? $template['line']['text'] : '請先使用 LINE Login 綁定您的帳號。';
			$this->send_reply( $reply_token, $message );
			return;
		}

		// Check permissions (admin or helper)
	// Note: In webhook context, we check the WordPress user's role, not current_user
	// For testing: define BUYGO_WEBHOOK_TEST_MODE in wp-config.php to skip permission check
	if ( ! defined( 'BUYGO_WEBHOOK_TEST_MODE' ) || ! BUYGO_WEBHOOK_TEST_MODE ) {
		$user_meta = get_userdata( $user->ID );
		$can_upload = false;
		
		if ( $user_meta && ! empty( $user_meta->roles ) ) {
			$can_upload = in_array( 'administrator', $user_meta->roles, true ) || 
			              in_array( 'buygo_admin', $user_meta->roles, true );
		}
		
		if ( ! $can_upload ) {
			// Silent processing for regular users
			$this->logger->log( 'permission_denied', array(
				'user_id' => $user->ID,
				'roles' => $user_meta->roles ?? [],
			), $user->ID, $line_uid );
			return;
		}
	} else {
		// Test mode: allow all users
		$this->logger->log( 'test_mode_active', array(
			'message' => 'Permission check skipped (test mode)',
			'user_id' => $user->ID,
		), $user->ID, $line_uid );
	}

		// Download and upload image
		if ( ! class_exists( 'BuyGo_Core' ) ) {
			return;
		}

		$token = \BuyGo_Core::settings()->get( 'line_channel_access_token', '' );
		if ( empty( $token ) ) {
			return;
		}

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
			$this->send_reply( $reply_token, $message );
			return;
		}

		$this->logger->log( 'image_uploaded', array(
			'attachment_id' => $attachment_id,
			'user_id' => $user->ID,
		), $user->ID, $line_uid );

		// Send Flex Message menu
		$flex_message = \BuyGoPlus\Templates\LineFlexTemplates::get_product_upload_menu();
		$this->send_reply( $reply_token, $flex_message );
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

		// Check if it's a command
		if ( $this->product_data_parser->is_command( $text ) ) {
			$this->handle_command( $text, $reply_token );
			return;
		}

		// Get WordPress user from LINE UID
		if ( ! class_exists( 'BuyGo_Core' ) ) {
			return;
		}

		$user = \BuyGo_Core::line()->get_user_by_line_uid( $line_uid );

		if ( ! $user ) {
			$template = \BuyGoPlus\Services\NotificationTemplates::get( 'system_account_not_bound', [] );
			$message = $template && isset( $template['line']['text'] ) ? $template['line']['text'] : '請先使用 LINE Login 綁定您的帳號。';
			$this->send_reply( $reply_token, $message );
			return;
		}

		// Check permissions
	// Note: In webhook context, we check the WordPress user's role, not current_user
	if ( ! defined( 'BUYGO_WEBHOOK_TEST_MODE' ) || ! BUYGO_WEBHOOK_TEST_MODE ) {
		$user_meta = get_userdata( $user->ID );
		$can_upload = false;
		
		if ( $user_meta && ! empty( $user_meta->roles ) ) {
			$can_upload = in_array( 'administrator', $user_meta->roles, true ) || 
			              in_array( 'buygo_admin', $user_meta->roles, true );
		}
		
		if ( ! $can_upload ) {
			// Silent processing for regular users
			$this->logger->log( 'permission_denied', array(
				'user_id' => $user->ID,
				'roles' => $user_meta->roles ?? [],
			), $user->ID, $line_uid );
			return;
		}
	} else {
		// Test mode: allow all users
		$this->logger->log( 'test_mode_active', array(
			'message' => 'Permission check skipped (test mode)',
			'user_id' => $user->ID,
		), $user->ID, $line_uid );
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
			$this->send_reply( $reply_token, $message );
			return;
		}

		// Add user_id to product data
		$product_data['user_id'] = $user->ID;

		// Get temporary images
		if ( ! class_exists( 'BuyGo_Core' ) ) {
			return;
		}

		$token = \BuyGo_Core::settings()->get( 'line_channel_access_token', '' );
		$image_ids = array();
		if ( ! empty( $token ) ) {
			$image_uploader = new ImageUploader( $token );
			$image_ids = $image_uploader->get_temp_images( $user->ID );
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
			$this->send_reply( $reply_token, $message );
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
		$template_args = array(
			'product_name' => $product_data['name'] ?? '',
			'price' => $product_data['price'] ?? 0,
			'quantity' => $product_data['quantity'] ?? 0,
			'product_url' => $product_url,
		);

		$template = \BuyGoPlus\Services\NotificationTemplates::get( 'system_product_published', $template_args );
		$message = $template && isset( $template['line']['text'] ) ? $template['line']['text'] : '商品建立成功';
		$this->send_reply( $reply_token, $message );
	}

	/**
	 * Handle command
	 *
	 * @param string $command Command text
	 * @param string $reply_token Reply token
	 */
	private function handle_command( $command, $reply_token ) {
		$command = trim( $command );

		// Handle /one and /many commands
		if ( $command === '/one' ) {
			$msg = "📋 複製以下格式發送：\n\n商品名稱\n價格：\n數量：";
			$this->send_reply( $reply_token, $msg );
			return;
		}

		if ( $command === '/many' ) {
			$msg = "📋 複製以下格式發送 (多樣)：\n\n商品名稱\n價格：\n數量：\n款式1：\n款式2：";
			$this->send_reply( $reply_token, $msg );
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
	 * @return bool
	 */
	private function send_reply( $reply_token, $message ) {
		if ( ! class_exists( 'BuyGo_Core' ) ) {
			return false;
		}

		$token = \BuyGo_Core::settings()->get( 'line_channel_access_token', '' );

		if ( empty( $token ) ) {
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
			return false;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		return $status_code === 200;
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

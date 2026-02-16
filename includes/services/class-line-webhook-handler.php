<?php
/**
 * LINE Webhook Handler
 *
 * 協調器角色 - 接收 Webhook 事件並分發給專門的處理類別
 *
 * @package BuyGoPlus
 */

namespace BuyGoPlus\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// 檢查 LINE 發送管道是否可用
add_action( 'admin_init', function() {
	if ( ! class_exists( '\\LineHub\\Plugin' ) ) {
		add_action( 'admin_notices', function() {
			echo '<div class="notice notice-warning"><p>';
			echo 'BuyGo+ Plus One 建議啟用 LINE Hub 外掛以使用 LINE Webhook 功能。';
			echo '</p></div>';
		} );
	}
} );

/**
 * Class LineWebhookHandler
 *
 * 職責：
 * - 註冊 Webhook hooks（Phase 5B 雙軌監聽）
 * - 協調事件分派到各專門處理類別
 * - 保留簡單的 follow/unfollow 處理
 * - 保留 process_events() 入口（BuyGo 自己的 cron 用）
 */
class LineWebhookHandler {

	/**
	 * 防止重複註冊 hooks
	 *
	 * @var bool
	 */
	private static $hooks_registered = false;

	/**
	 * @var WebhookLogger
	 */
	private $logger;

	/**
	 * @var LineProductUploadHandler
	 */
	private $upload_handler;

	/**
	 * @var LineTextRouter
	 */
	private $text_router;

	/**
	 * @var LineMessagingFacade
	 */
	private $messaging;

	/**
	 * Constructor
	 */
	public function __construct() {
		// 防止雙重實例化導致 hooks 重複註冊
		if ( self::$hooks_registered ) {
			return;
		}
		self::$hooks_registered = true;

		$this->logger = WebhookLogger::get_instance();
		$this->upload_handler = new LineProductUploadHandler();
		$this->text_router = new LineTextRouter();
		$this->messaging = new LineMessagingFacade();

		// LineHub Hook 註冊
		add_action( 'line_hub/webhook/message/image', array( $this->upload_handler, 'handleImageUpload' ), 10, 4 );
		add_action( 'line_hub/webhook/message/text', array( $this->text_router, 'handleTextMessage' ), 10, 4 );
		add_action( 'line_hub/webhook/postback', array( $this->upload_handler, 'handlePostback' ), 10, 3 );
		add_action( 'line_hub/webhook/follow', array( $this, 'handle_follow' ), 10, 1 );
		add_action( 'line_hub/webhook/unfollow', array( $this, 'handle_unfollow' ), 10, 1 );
	}

	/**
	 * Process webhook events（BuyGo 自己的 cron 入口）
	 *
	 * @param array $events Events array
	 * @param bool $return_response Whether to return response
	 * @return \WP_REST_Response|null
	 */
	public function process_events( $events, $return_response = true ) {
		ignore_user_abort( true );
		set_time_limit( 0 );

		$this->logger->log( 'webhook_received', array( 'event_count' => count( $events ) ) );

		foreach ( $events as $event ) {
			$reply_token = $event['replyToken'] ?? '';
			if ( $reply_token === str_repeat( '0', 32 ) || $reply_token === str_repeat( '0', 64 ) ) {
				if ( $return_response ) {
					return rest_ensure_response( array( 'success' => true ) );
				}
				return null;
			}

			$event_id = $event['webhookEventId'] ?? '';
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
	 * Handle event（事件類型分派）
	 *
	 * @param array $event Event data
	 */
	private function handle_event( $event ) {
		$event_type = $event['type'] ?? '';

		switch ( $event_type ) {
			case 'message':
				// image 和 text 由 hooks 處理（upload_handler / text_router）
				// 這裡不需要再分派，避免重複處理
				break;

			case 'follow':
				$this->handle_follow( $event );
				break;

			case 'unfollow':
				$this->handle_unfollow( $event );
				break;

			default:
				break;
		}
	}

	/**
	 * Handle follow event
	 *
	 * @param array $event Event data
	 */
	public function handle_follow( $event ) {
		$reply_token = $event['replyToken'] ?? '';
		$line_uid = $event['source']['userId'] ?? '';
		$template = NotificationTemplates::get( 'system_line_follow', [] );
		$message = $template && isset( $template['line']['text'] ) ? $template['line']['text'] : "歡迎使用 BuyGo 商品上架 🎉";
		$this->messaging->send_reply( $reply_token, $message, $line_uid );
	}

	/**
	 * Handle unfollow event
	 *
	 * @param array $event Event data
	 */
	public function handle_unfollow( $event ) {
		// Silent processing
	}
}

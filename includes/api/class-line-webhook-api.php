<?php
/**
 * LINE Webhook API
 *
 * @package BuyGoPlus
 */

namespace BuyGoPlus\Api;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Line_Webhook_API
 */
class Line_Webhook_API {

	/**
	 * Webhook Handler
	 *
	 * @var \BuyGoPlus\Services\LineWebhookHandler
	 */
	private $webhook_handler;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->webhook_handler = new \BuyGoPlus\Services\LineWebhookHandler();
	}

	/**
	 * Register routes
	 */
	public function register_routes() {
		register_rest_route(
			'buygo-plus-one/v1',
			'/line/webhook',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_webhook' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Handle webhook
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function handle_webhook( $request ) {
		$logger = \BuyGoPlus\Services\WebhookLogger::get_instance();

		// 驗證簽章（必須在處理事件之前）
		if ( ! $this->verify_signature( $request ) ) {
			$logger->log( 'signature_verification_failed', array(
				'reason' => 'Signature verification failed in handle_webhook',
			) );
			return new \WP_Error( 'invalid_signature', 'Invalid signature', array( 'status' => 401 ) );
		}

		$body = $request->get_body();
		$data = json_decode( $body, true );

		// 詳細記錄請求體內容以診斷空事件問題
		$logger->log( 'webhook_body_parsed', array(
			'body_length' => strlen( $body ),
			'body_preview' => substr( $body, 0, 500 ), // 記錄前 500 字元
			'json_decode_success' => is_array( $data ),
			'has_events' => isset( $data['events'] ),
			'events_count' => isset( $data['events'] ) ? count( $data['events'] ) : 0,
			'data_keys' => is_array( $data ) ? array_keys( $data ) : null,
			'json_last_error' => json_last_error_msg(),
		) );

		if ( ! isset( $data['events'] ) ) {
			$logger->log( 'webhook_no_events', array(
				'reason' => 'No events array in webhook data',
				'data_structure' => is_array( $data ) ? array_keys( $data ) : gettype( $data ),
			) );
			return rest_ensure_response( array( 'success' => false ) );
		}

		// 檢查是否為 LINE Verify Event（當 LINE Developers Console 點擊「驗證」按鈕時）
		// Verify event 的 replyToken 是固定的 32 個 0
		foreach ( $data['events'] as $event ) {
			$reply_token = isset( $event['replyToken'] ) ? $event['replyToken'] : '';
			if ( '00000000000000000000000000000000' === $reply_token ) {
				$logger->log( 'line_verify_event_detected', array(
					'message' => 'LINE Verify Event detected (replyToken: 000...000), returning success immediately',
				) );
				return rest_ensure_response( array( 'success' => true ) );
			}
		}

		// 立即處理事件，但使用輕量級處理避免 timeout
		// 如果處理時間可能超過 30 秒，使用 fastcgi_finish_request 在背景處理
		if ( function_exists( 'fastcgi_finish_request' ) ) {
			// FastCGI 環境：先返回響應，然後在背景處理
			// 注意：必須先返回 WP_REST_Response，讓 WordPress 處理權限和響應
			// 然後再調用 fastcgi_finish_request 在背景處理事件

			// 使用 shutdown hook 在背景處理
			$webhook_handler = $this->webhook_handler; // 避免閉包中 $this 上下文丟失
			add_action( 'shutdown', function() use ( $data, $webhook_handler ) {
				if ( function_exists( 'fastcgi_finish_request' ) ) {
					fastcgi_finish_request();
					$webhook_handler->process_events( $data['events'], false );
				}
			} );
		} else {
			// 非 FastCGI 環境：使用 WordPress Cron 在背景處理
			wp_schedule_single_event( time(), 'buygo_process_line_webhook', array( $data['events'] ) );
		}

		// 立即返回 200 響應
		return rest_ensure_response( array( 'success' => true ) );
	}

	/**
	 * Verify LINE signature
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return bool
	 */
	private function verify_signature( $request ) {
		$logger = \BuyGoPlus\Services\WebhookLogger::get_instance();
		$signature = $request->get_header( 'x-line-signature' );

		// 嘗試多種方式獲取簽名 header
		$signature_alternatives = array(
			'x-line-signature' => $request->get_header( 'x-line-signature' ),
			'X-LINE-Signature' => $request->get_header( 'X-LINE-Signature' ),
			'X-Line-Signature' => $request->get_header( 'X-Line-Signature' ),
			'HTTP_X_LINE_SIGNATURE' => isset( $_SERVER['HTTP_X_LINE_SIGNATURE'] ) ? $_SERVER['HTTP_X_LINE_SIGNATURE'] : null,
		);

		// 使用第一個非空的簽名
		foreach ( $signature_alternatives as $key => $value ) {
			if ( ! empty( $value ) ) {
				$signature = $value;
				break;
			}
		}

		// 記錄所有 webhook 請求（包括失敗的）
		$logger->log( 'webhook_request_received', array(
			'has_signature' => ! empty( $signature ),
			'signature_preview' => $signature ? substr( $signature, 0, 20 ) . '...' : null,
			'signature_alternatives' => $signature_alternatives,
			'request_method' => $request->get_method(),
			'content_type' => $request->get_header( 'Content-Type' ),
		) );

		// 取得 channel secret（使用新外掛的 SettingsService）
		// SettingsService 會自動從 buygo_core_settings 或獨立 option 讀取並解密
		$channel_secret = \BuyGoPlus\Services\SettingsService::get( 'line_channel_secret', '' );

		// 如果沒有設定 channel secret，根據環境決定是否跳過驗證
		if ( empty( $channel_secret ) ) {
			$is_dev = $this->is_development_mode();

			if ( $is_dev ) {
				// 開發環境：允許跳過驗證
				$logger->log( 'signature_verification_skipped', array(
					'reason' => 'Development mode: Channel secret not configured',
					'mode' => 'development',
				) );
				return true;
			} else {
				// 正式環境：拒絕請求
				$logger->log( 'signature_verification_failed', array(
					'reason' => 'Production mode: Channel secret not configured',
					'mode' => 'production',
					'instruction' => 'Please configure LINE Channel Secret in plugin settings',
				) );
				return false;
			}
		}

		// 如果沒有簽名，拒絕請求
		if ( empty( $signature ) ) {
			$logger->log( 'signature_verification_failed', array(
				'reason' => 'Missing x-line-signature header',
			) );
			return false;
		}

		// 計算簽名
		$body         = $request->get_body();
		$hash         = hash_hmac( 'sha256', $body, $channel_secret, true );
		$computed_sig = base64_encode( $hash );

		// 使用安全的字串比較防止時序攻擊
		$is_valid = hash_equals( $signature, $computed_sig );

		if ( ! $is_valid ) {
			$logger->log( 'signature_verification_failed', array(
				'reason' => 'Signature mismatch',
				'received_signature' => substr( $signature, 0, 20 ) . '...',
				'computed_signature' => substr( $computed_sig, 0, 20 ) . '...',
			) );
		} else {
			$logger->log( 'signature_verification_success', array(
				'message' => 'Signature verified successfully',
			) );
		}

		return $is_valid;
	}

	/**
	 * 檢查是否為開發模式
	 *
	 * @return bool
	 */
	private function is_development_mode() {
		// 方法1: 檢查 WP_DEBUG（最常用）
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG === true ) {
			return true;
		}

		// 方法2: 檢查環境類型（WordPress 5.5+）
		if ( function_exists( 'wp_get_environment_type' ) ) {
			$env_type = wp_get_environment_type();
			if ( in_array( $env_type, array( 'development', 'local' ), true ) ) {
				return true;
			}
		}

		// 方法3: 檢查伺服器名稱（補充判斷）
		if ( isset( $_SERVER['SERVER_NAME'] ) ) {
			$server_name = sanitize_text_field( wp_unslash( $_SERVER['SERVER_NAME'] ) );
			if ( in_array( $server_name, array( 'localhost', '127.0.0.1', '::1' ), true ) ) {
				return true;
			}
		}

		// 預設為正式環境（安全優先）
		return false;
	}
}

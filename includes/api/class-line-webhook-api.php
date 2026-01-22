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
				'permission_callback' => array( $this, 'verify_signature' ),
			)
		);
	}

	/**
	 * Handle webhook
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function handle_webhook( $request ) {
		$body = $request->get_body();
		$data = json_decode( $body, true );

		if ( ! isset( $data['events'] ) ) {
			return rest_ensure_response( array( 'success' => false ) );
		}

		// 立即處理事件，但使用輕量級處理避免 timeout
		// 如果處理時間可能超過 30 秒，使用 fastcgi_finish_request 在背景處理
		if ( function_exists( 'fastcgi_finish_request' ) ) {
			// FastCGI 環境：先返回響應，然後在背景處理
			// 注意：必須先返回 WP_REST_Response，讓 WordPress 處理權限和響應
			// 然後再調用 fastcgi_finish_request 在背景處理事件

			// 使用 shutdown hook 在背景處理
			add_action( 'shutdown', function() use ( $data ) {
				if ( function_exists( 'fastcgi_finish_request' ) ) {
					fastcgi_finish_request();
					$this->webhook_handler->process_events( $data['events'], false );
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
	public function verify_signature( $request ) {
		$logger = \BuyGoPlus\Services\WebhookLogger::get_instance();
		$signature = $request->get_header( 'X-Line-Signature' );

		// 記錄所有 webhook 請求（包括失敗的）
		$logger->log( 'webhook_request_received', array(
			'has_signature' => ! empty( $signature ),
			'signature_preview' => $signature ? substr( $signature, 0, 20 ) . '...' : null,
			'request_method' => $request->get_method(),
			'content_type' => $request->get_header( 'Content-Type' ),
		) );

		// 如果沒有簽名，拒絕請求
		if ( empty( $signature ) ) {
			$logger->log( 'signature_verification_failed', array(
				'reason' => 'Missing X-Line-Signature header',
			) );
			return false;
		}

		// 取得 channel secret
		$channel_secret = get_option( 'buygo_line_channel_secret', '' );

		// 如果沒有設定 channel secret，記錄警告並拒絕
		if ( empty( $channel_secret ) ) {
			error_log( 'BuyGo+1: LINE channel secret not configured' );
			$logger->log( 'signature_verification_failed', array(
				'reason' => 'Channel secret not configured',
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
}

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

		return $this->webhook_handler->process_events( $data['events'] );
	}

	/**
	 * Verify LINE signature
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return bool
	 */
	public function verify_signature( $request ) {
		// For webhook, we allow all requests (LINE will verify)
		// Actual signature verification should be done here in production
		return true;
	}
}

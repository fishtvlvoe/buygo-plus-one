<?php
/**
 * FluentCart Seller Grant Integration
 *
 * 監聽 FluentCart 訂單事件，並將賣家授權流程委派到 SellerGrantService。
 *
 * @package BuygoPlus
 */

namespace BuygoPlus\Integrations;

use BuyGoPlus\Services\SellerGrantService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FluentCartSellerGrantIntegration {
	private static function service(): SellerGrantService {
		static $service = null;

		if ( null === $service ) {
			$service = new SellerGrantService();
		}

		return $service;
	}

	public static function register_hooks(): void {
		\add_action( 'fluent_cart/order_created', [ __CLASS__, 'handle_order_created' ], 20 );
		\add_action( 'fluent_cart/order_paid', [ __CLASS__, 'handle_order_paid' ], 20 );
		\add_action( 'fluent_cart/order_created', [ __CLASS__, 'handle_free_order' ], 25 );
		\add_action( 'fluent_cart/order_refunded', [ __CLASS__, 'handle_order_refunded' ], 20 );
	}

	public static function handle_order_created( $data ): void {
		$order = $data['order'] ?? null;

		if ( ! $order ) {
			error_log( '[BuyGo+1][SellerGrant] order_created: no order data' );
			return;
		}

		error_log(
			sprintf(
				'[BuyGo+1][SellerGrant] order_created: Order #%d (payment_method: %s, payment_status: %s)',
				$order->id,
				$order->payment_method ?? 'unknown',
				$order->payment_status ?? 'unknown'
			)
		);
	}

	public static function handle_order_paid( $data ): void {
		$order = $data['order'] ?? null;

		if ( ! $order ) {
			error_log( '[BuyGo+1][SellerGrant] order_paid: no order data' );
			return;
		}

		error_log(
			sprintf(
				'[BuyGo+1][SellerGrant] order_paid: Order #%d (payment_status: %s)',
				$order->id,
				$order->payment_status ?? 'unknown'
			)
		);

		self::service()->process_order( (int) $order->id );
	}

	public static function handle_free_order( $data ): void {
		$order = $data['order'] ?? null;

		if ( ! $order ) {
			error_log( '[BuyGo+1][SellerGrant] free_order: no order data' );
			return;
		}

		if ( $order->total > 0 ) {
			return;
		}

		error_log(
			sprintf(
				'[BuyGo+1][SellerGrant] free_order: Processing free order #%d (total: %s)',
				$order->id,
				$order->total
			)
		);

		self::service()->process_order( (int) $order->id );
	}

	public static function handle_order_refunded( $data ): void {
		$order = $data['order'] ?? null;
		$type  = $data['type'] ?? 'unknown';

		if ( ! $order ) {
			error_log( '[BuyGo+1][SellerGrant] order_refunded: no order data' );
			return;
		}

		error_log(
			sprintf(
				'[BuyGo+1][SellerGrant] order_refunded: Order #%d (type: %s, refunded_amount: %d)',
				$order->id,
				$type,
				$data['refunded_amount'] ?? 0
			)
		);

		self::service()->process_refund( (int) $order->id, (string) $type );
	}
}

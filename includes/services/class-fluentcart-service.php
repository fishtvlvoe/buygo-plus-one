<?php
/**
 * FluentCart Service
 *
 * @package BuyGoPlus
 */

namespace BuyGoPlus\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FluentCartService
 *
 * Service for creating FluentCart products
 * 遷移自舊外掛（buygo）的 FluentCartService
 */
class FluentCartService {

	/**
	 * Logger
	 *
	 * @var object
	 */
	private $logger;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->logger = WebhookLogger::get_instance();
	}

	/**
	 * Create product in FluentCart
	 *
	 * @param array $product_data Product data (name, price, quantity, currency, description, image_attachment_id)
	 * @param array $image_ids Image attachment IDs (deprecated, use image_attachment_id in product_data)
	 * @return int|\WP_Error Product ID or error
	 */
	public function create_product( $product_data, $image_ids = array() ) {
		if ( ! class_exists( 'FluentCart\App\App' ) ) {
			$this->logger->log( 'error', array(
				'message' => 'FluentCart not installed',
				'product_data' => $product_data,
			), $product_data['user_id'] ?? null, null );
			return new \WP_Error( 'fluentcart_not_installed', 'FluentCart 未安裝' );
		}

		try {
			// 使用 WordPress 原生函數建立商品
			$post_data = array(
				'post_title' => sanitize_text_field( $product_data['name'] ?? '' ),
				'post_content' => sanitize_textarea_field( $product_data['description'] ?? '' ),
				'post_excerpt' => '',
				'post_status' => 'publish',
				'post_type' => 'fluent-products',
				'comment_status' => 'open',
				'ping_status' => 'closed',
			);

			$this->logger->log( 'product_post_creating', array(
				'post_data' => $post_data,
			), $product_data['user_id'] ?? null, null );

			$product_id = wp_insert_post( $post_data, true );

			if ( is_wp_error( $product_id ) ) {
				$this->logger->log( 'error', array(
					'message' => 'wp_insert_post failed',
					'error' => $product_id->get_error_message(),
				), $product_data['user_id'] ?? null, null );
				return $product_id;
			}

			$this->logger->log( 'product_post_created', array(
				'product_id' => $product_id,
			), $product_data['user_id'] ?? null, null );

			// 建立 FluentCart 商品詳情
			$this->create_product_details( $product_id, $product_data );

			// 設定圖片
			$image_attachment_id = $product_data['image_attachment_id'] ?? ( ! empty( $image_ids ) ? $image_ids[0] : null );
			if ( ! empty( $image_attachment_id ) ) {
				set_post_thumbnail( $product_id, intval( $image_attachment_id ) );
				$this->logger->log( 'product_image_set', array(
					'product_id' => $product_id,
					'attachment_id' => $image_attachment_id,
				), $product_data['user_id'] ?? null, null );
			}

			// 儲存 meta（LINE UID 等）
			$line_uid = $product_data['line_uid'] ?? null;
			if ( $line_uid ) {
				update_post_meta( $product_id, '_mygo_line_user_id', $line_uid );
			}
			update_post_meta( $product_id, '_mygo_currency', $product_data['currency'] ?? 'TWD' );
			if ( isset( $product_data['arrival_date'] ) ) {
				update_post_meta( $product_id, '_mygo_arrival_date', $product_data['arrival_date'] );
			}
			if ( isset( $product_data['preorder_date'] ) ) {
				update_post_meta( $product_id, '_mygo_preorder_date', $product_data['preorder_date'] );
			}

			do_action( 'buygo/product/created', $product_id, $product_data, $line_uid );

			$this->logger->log( 'product_created_success', array(
				'product_id' => $product_id,
				'product_name' => $product_data['name'] ?? '',
			), $product_data['user_id'] ?? null, $line_uid );

			return $product_id;

		} catch ( \Exception $e ) {
			$this->logger->log( 'error', array(
				'message' => 'Product creation exception',
				'error' => $e->getMessage(),
				'trace' => $e->getTraceAsString(),
			), $product_data['user_id'] ?? null, null );
			return new \WP_Error( 'exception', $e->getMessage() );
		}
	}

	/**
	 * 建立 FluentCart 商品詳情
	 *
	 * @param int $product_id Product ID
	 * @param array $data Product data
	 */
	private function create_product_details( $product_id, $data ) {
		global $wpdb;

		// FluentCart 使用「分」為單位儲存價格，所以 350 元要存成 35000
		$price = intval( $data['price'] ?? 0 ) * 100;
		$quantity = intval( $data['quantity'] ?? 0 );

		$detail_data = array(
			'post_id' => $product_id,
			'fulfillment_type' => 'physical',
			'min_price' => $price,
			'max_price' => $price,
			'default_variation_id' => null,
			'default_media' => null,
			'manage_stock' => 1,
			'stock_availability' => $quantity > 0 ? 'in-stock' : 'out-of-stock',
			'variation_type' => 'simple',
			'manage_downloadable' => 0,
			'other_info' => wp_json_encode( array(
				'stock_quantity' => $quantity,
			) ),
			'created_at' => current_time( 'mysql' ),
			'updated_at' => current_time( 'mysql' ),
		);

		$table_name = $wpdb->prefix . 'fct_product_details';

		// 檢查表是否存在
		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ) !== $table_name ) {
			$this->logger->log( 'error', array(
				'message' => 'fct_product_details table not found',
			), null, null );
			return;
		}

		$wpdb->insert( $table_name, $detail_data );

		$this->logger->log( 'product_details_created', array(
			'product_id' => $product_id,
			'insert_id' => $wpdb->insert_id,
		), null, null );

		// 建立預設變體（FluentCart 需要）
		$this->create_default_variation( $product_id, $data );
	}

	/**
	 * 建立預設商品變體
	 *
	 * @param int $product_id Product ID
	 * @param array $data Product data
	 */
	private function create_default_variation( $product_id, $data ) {
		global $wpdb;

		// FluentCart 使用「分」為單位儲存價格，所以 350 元要存成 35000
		$price = intval( $data['price'] ?? 0 ) * 100;
		$quantity = intval( $data['quantity'] ?? 0 );

		$table_name = $wpdb->prefix . 'fct_product_variations';

		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ) !== $table_name ) {
			$this->logger->log( 'error', array(
				'message' => 'fct_product_variations table not found',
			), null, null );
			return;
		}

		$variation_data = array(
			'post_id' => $product_id,
			'variation_title' => sanitize_text_field( $data['name'] ?? '' ),
			'variation_identifier' => 'BUYGO-' . $product_id,
			'manage_stock' => 1,
			'stock_status' => $quantity > 0 ? 'in-stock' : 'out-of-stock',
			'total_stock' => $quantity,
			'available' => $quantity,
			'item_status' => 'active',
			'item_price' => $price,
			'created_at' => current_time( 'mysql' ),
			'updated_at' => current_time( 'mysql' ),
		);

		$result = $wpdb->insert( $table_name, $variation_data );

		if ( $result === false ) {
			$this->logger->log( 'error', array(
				'message' => 'Product variation insert failed',
				'error' => $wpdb->last_error,
			), null, null );
		} else {
			$this->logger->log( 'product_variation_created', array(
				'product_id' => $product_id,
				'insert_id' => $wpdb->insert_id,
			), null, null );
		}
	}
}

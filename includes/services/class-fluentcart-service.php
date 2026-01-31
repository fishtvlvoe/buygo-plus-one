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
	 * Debug Service
	 *
	 * @var DebugService
	 */
	private $debugService;

	/**
	 * Webhook Logger
	 *
	 * @var WebhookLogger
	 */
	private $webhookLogger;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->debugService = DebugService::get_instance();
		$this->webhookLogger = WebhookLogger::get_instance();
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
			$error_message = 'FluentCart 未安裝';
			$this->debugService->log( 'FluentCartService', $error_message, array(
				'product_data' => $product_data,
			), 'error' );
			$this->webhookLogger->log( 'error', array(
				'message' => $error_message,
				'product_data' => $product_data,
			), $product_data['user_id'] ?? null, $product_data['line_uid'] ?? null );
			return new \WP_Error( 'fluentcart_not_installed', $error_message );
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

			$this->debugService->log( 'FluentCartService', '開始建立商品 Post', array(
				'post_data' => $post_data,
			), 'info' );

			$product_id = wp_insert_post( $post_data, true );

			if ( is_wp_error( $product_id ) ) {
				$error_message = 'wp_insert_post 失敗：' . $product_id->get_error_message();
				$this->debugService->log( 'FluentCartService', $error_message, array(
					'error' => $product_id->get_error_message(),
					'error_code' => $product_id->get_error_code(),
				), 'error' );
				$this->webhookLogger->log( 'error', array(
					'message' => $error_message,
					'error' => $product_id->get_error_message(),
					'error_code' => $product_id->get_error_code(),
				), $product_data['user_id'] ?? null, $product_data['line_uid'] ?? null );
				return $product_id;
			}

			$this->debugService->log( 'FluentCartService', '商品 Post 建立成功', array(
				'product_id' => $product_id,
			), 'info' );

		// 建立 FluentCart 商品詳情
		// 檢查是否為多樣式產品
		if ( ! empty( $product_data['variations'] ) && is_array( $product_data['variations'] ) ) {
			// 多樣式產品：建立多個變體
			$details_result = $this->create_variable_product( $product_id, $product_data );
			if ( is_wp_error( $details_result ) ) {
				// 如果建立失敗，刪除已建立的 post
				wp_delete_post( $product_id, true );
				// 記錄錯誤到 WebhookLogger
				$this->webhookLogger->log( 'error', array(
					'message' => '多樣式商品建立失敗',
					'error' => $details_result->get_error_message(),
					'error_code' => $details_result->get_error_code(),
					'product_id' => $product_id,
				), $product_data['user_id'] ?? null, $product_data['line_uid'] ?? null );
				return $details_result;
			}
		} else {
			// 單一商品：建立單一變體
			$details_result = $this->create_product_details( $product_id, $product_data );
			if ( is_wp_error( $details_result ) ) {
				// 如果建立失敗，刪除已建立的 post
				wp_delete_post( $product_id, true );
				// 記錄錯誤到 WebhookLogger
				$this->webhookLogger->log( 'error', array(
					'message' => '商品詳情建立失敗',
					'error' => $details_result->get_error_message(),
					'error_code' => $details_result->get_error_code(),
					'product_id' => $product_id,
				), $product_data['user_id'] ?? null, $product_data['line_uid'] ?? null );
				return $details_result;
			}
		}

		// 設定圖片
			$image_attachment_id = $product_data['image_attachment_id'] ?? ( ! empty( $image_ids ) ? $image_ids[0] : null );
			if ( ! empty( $image_attachment_id ) ) {
				set_post_thumbnail( $product_id, intval( $image_attachment_id ) );
				$this->debugService->log( 'FluentCartService', '商品圖片已設定', array(
					'product_id' => $product_id,
					'attachment_id' => $image_attachment_id,
				), 'info' );
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

			// 設定 FluentCart 產品為「單次付款」而非「訂閱商品」
			// 確保客戶可以選擇數量並加入購物車
			// 注意：FluentCart 的付款期限可能儲存在 other_info 或其他 meta 中
			// 先嘗試設定常見的 meta key
			update_post_meta( $product_id, '_fct_payment_term', 'one_time' );
			update_post_meta( $product_id, '_fct_billing_interval', '' );
			update_post_meta( $product_id, '_fct_billing_period', '' );
			update_post_meta( $product_id, '_fct_subscription_enabled', 'no' );
			
			// 記錄日誌以便除錯
			$this->debugService->log( 'FluentCartService', '商品付款期限已設定', array(
				'product_id' => $product_id,
				'payment_term' => 'one_time',
			), 'info' );

			do_action( 'buygo/product/created', $product_id, $product_data, $line_uid );

			$this->debugService->log( 'FluentCartService', '商品建立成功', array(
				'product_id' => $product_id,
				'product_name' => $product_data['name'] ?? '',
			), 'info' );

			return $product_id;

		} catch ( \Exception $e ) {
			$error_message = '商品建立發生例外：' . $e->getMessage();
			$this->debugService->log( 'FluentCartService', $error_message, array(
				'error' => $e->getMessage(),
				'trace' => $e->getTraceAsString(),
			), 'error' );
			$this->webhookLogger->log( 'error', array(
				'message' => $error_message,
				'error' => $e->getMessage(),
				'trace' => $e->getTraceAsString(),
			), $product_data['user_id'] ?? null, $product_data['line_uid'] ?? null );
			return new \WP_Error( 'exception', $e->getMessage() );
		}
	}

	/**
	 * 建立 FluentCart 商品詳情
	 *
	 * @param int $product_id Product ID
	 * @param array $data Product data
	 * @return bool|\WP_Error 成功返回 true，失敗返回 WP_Error
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

		// 檢查表是否存在（使用 prepare 確保安全性）
		$table_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) );
		if ( $table_exists !== $table_name ) {
			$error = new \WP_Error( 'table_not_found', 'fct_product_details 資料表不存在' );
			$this->debugService->log( 'FluentCartService', 'fct_product_details 資料表不存在', array(
				'error' => $error->get_error_message(),
				'table_name' => $table_name,
			), 'error' );
			$this->webhookLogger->log( 'error', array(
				'message' => 'fct_product_details 資料表不存在',
				'error' => $error->get_error_message(),
				'table_name' => $table_name,
			), $data['user_id'] ?? null, $data['line_uid'] ?? null );
			return $error;
		}

		$result = $wpdb->insert( $table_name, $detail_data );

		if ( $result === false ) {
			$error = new \WP_Error( 'insert_failed', '商品詳情插入失敗：' . $wpdb->last_error );
			$this->debugService->log( 'FluentCartService', '商品詳情插入失敗', array(
				'error' => $wpdb->last_error,
				'product_id' => $product_id,
				'table_name' => $table_name,
				'detail_data' => $detail_data,
			), 'error' );
			$this->webhookLogger->log( 'error', array(
				'message' => '商品詳情插入失敗',
				'error' => $wpdb->last_error,
				'error_code' => 'insert_failed',
				'product_id' => $product_id,
				'table_name' => $table_name,
			), $data['user_id'] ?? null, $data['line_uid'] ?? null );
			return $error;
		}

		$this->debugService->log( 'FluentCartService', '商品詳情建立成功', array(
			'product_id' => $product_id,
			'insert_id' => $wpdb->insert_id,
		), 'info' );

		// 建立預設變體（FluentCart 需要）
		$variation_result = $this->create_default_variation( $product_id, $data );
		if ( is_wp_error( $variation_result ) ) {
			// 記錄錯誤到 WebhookLogger
			$this->webhookLogger->log( 'error', array(
				'message' => '預設變體建立失敗',
				'error' => $variation_result->get_error_message(),
				'error_code' => $variation_result->get_error_code(),
				'product_id' => $product_id,
			), $data['user_id'] ?? null, $data['line_uid'] ?? null );
			return $variation_result;
		}

		return true;
	}

	/**
	 * 建立多樣式商品（含多個變體）
	 *
	 * @param int $product_id Product ID
	 * @param array $data Product data (包含 variations 陣列)
	 * @return bool|\WP_Error 成功返回 true，失敗返回 WP_Error
	 */
	private function create_variable_product( $product_id, $data ) {
		global $wpdb;

		// 計算最小和最大價格
		$prices = array();
		$total_quantity = 0;
		
		foreach ( $data['variations'] as $variation ) {
			$variation_price = intval( $variation['price'] ?? $data['price'] ?? 0 );
			$variation_quantity = intval( $variation['quantity'] ?? 0 );
			$prices[] = $variation_price;
			$total_quantity += $variation_quantity;
		}

		$min_price = ! empty( $prices ) ? min( $prices ) * 100 : 0; // 轉換為分
		$max_price = ! empty( $prices ) ? max( $prices ) * 100 : 0; // 轉換為分

		// 建立商品詳情
		$detail_data = array(
			'post_id' => $product_id,
			'fulfillment_type' => 'physical',
			'min_price' => $min_price,
			'max_price' => $max_price,
			'default_variation_id' => null,
			'default_media' => null,
			'manage_stock' => 1,
			'stock_availability' => $total_quantity > 0 ? 'in-stock' : 'out-of-stock',
			'variation_type' => 'variable', // 多樣式商品
			'manage_downloadable' => 0,
			'other_info' => wp_json_encode( array(
				'stock_quantity' => $total_quantity,
			) ),
			'created_at' => current_time( 'mysql' ),
			'updated_at' => current_time( 'mysql' ),
		);

		$table_name = $wpdb->prefix . 'fct_product_details';

		// 檢查表是否存在（使用 prepare 確保安全性）
		$table_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) );
		if ( $table_exists !== $table_name ) {
			$error = new \WP_Error( 'table_not_found', 'fct_product_details 資料表不存在' );
			$this->debugService->log( 'FluentCartService', 'fct_product_details 資料表不存在', array(
				'error' => $error->get_error_message(),
				'table_name' => $table_name,
			), 'error' );
			$this->webhookLogger->log( 'error', array(
				'message' => 'fct_product_details 資料表不存在',
				'error' => $error->get_error_message(),
				'table_name' => $table_name,
			), $data['user_id'] ?? null, $data['line_uid'] ?? null );
			return $error;
		}

		$result = $wpdb->insert( $table_name, $detail_data );

		if ( $result === false ) {
			$error = new \WP_Error( 'insert_failed', '多樣式商品詳情插入失敗：' . $wpdb->last_error );
			$this->debugService->log( 'FluentCartService', '多樣式商品詳情插入失敗', array(
				'error' => $wpdb->last_error,
				'product_id' => $product_id,
				'table_name' => $table_name,
			), 'error' );
			$this->webhookLogger->log( 'error', array(
				'message' => '多樣式商品詳情插入失敗',
				'error' => $wpdb->last_error,
				'error_code' => 'insert_failed',
				'product_id' => $product_id,
				'table_name' => $table_name,
			), $data['user_id'] ?? null, $data['line_uid'] ?? null );
			return $error;
		}

		$this->debugService->log( 'FluentCartService', '多樣式商品詳情建立成功', array(
			'product_id' => $product_id,
			'variations_count' => count( $data['variations'] ),
		), 'info' );

		// 為每個變體建立 variation
		$default_variation_id = null;
		foreach ( $data['variations'] as $index => $variation ) {
			$variation_id = $this->create_variation( $product_id, $variation, $data );
			if ( is_wp_error( $variation_id ) ) {
				// 記錄錯誤到 WebhookLogger
				$this->webhookLogger->log( 'error', array(
					'message' => '變體建立失敗',
					'error' => $variation_id->get_error_message(),
					'error_code' => $variation_id->get_error_code(),
					'product_id' => $product_id,
					'variation_index' => $index,
				), $data['user_id'] ?? null, $data['line_uid'] ?? null );
				// 如果變體建立失敗，返回錯誤
				return $variation_id;
			}
			if ( $variation_id && $index === 0 ) {
				$default_variation_id = $variation_id;
			}
		}

		// 更新預設變體 ID
		if ( $default_variation_id ) {
			$wpdb->update(
				$table_name,
				array( 'default_variation_id' => $default_variation_id ),
				array( 'post_id' => $product_id )
			);
		}

		return true;
	}

	/**
	 * 建立單一變體
	 *
	 * @param int $product_id Product ID
	 * @param array $variation Variation data
	 * @param array $product_data Full product data
	 * @return int|\WP_Error Variation ID 或錯誤
	 */
	private function create_variation( $product_id, $variation, $product_data ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'fct_product_variations';

		// 檢查表是否存在（使用 prepare 確保安全性）
		$table_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) );
		if ( $table_exists !== $table_name ) {
			$error = new \WP_Error( 'table_not_found', 'fct_product_variations 資料表不存在' );
			$this->debugService->log( 'FluentCartService', 'fct_product_variations 資料表不存在', array(
				'error' => $error->get_error_message(),
				'table_name' => $table_name,
			), 'error' );
			$this->webhookLogger->log( 'error', array(
				'message' => 'fct_product_variations 資料表不存在',
				'error' => $error->get_error_message(),
				'table_name' => $table_name,
			), $product_data['user_id'] ?? null, $product_data['line_uid'] ?? null );
			return $error;
		}

		$price = intval( $variation['price'] ?? $product_data['price'] ?? 0 ) * 100; // 轉換為分
		$quantity = intval( $variation['quantity'] ?? 0 );
		$variation_title = $variation['variation_title'] ?? $variation['name'] ?? $product_data['name'] ?? '';

		// 處理原價 (compare_price / original_price)
		$compare_price = null;
		if ( ! empty( $variation['original_price'] ) || ! empty( $variation['compare_price'] ) ) {
			$compare_price = intval( $variation['original_price'] ?? $variation['compare_price'] ?? 0 ) * 100;
		}

		// 建立 other_info JSON
		$other_info = array(
			'payment_type' => 'onetime', // 【關鍵修復】設定為單次購買
			'description' => $variation['description'] ?? $product_data['description'] ?? '',
		);

		// 如果有原價，加入到 other_info
		if ( $compare_price && $compare_price > $price ) {
			$other_info['compare_price'] = $compare_price;
		}

		$variation_data = array(
			'post_id' => $product_id,
			'variation_title' => sanitize_text_field( $variation_title ),
			'variation_identifier' => 'BUYGO-' . $product_id . '-' . ( $variation['code'] ?? '' ),
			'manage_stock' => 1,
			'stock_status' => $quantity > 0 ? 'in-stock' : 'out-of-stock',
			'total_stock' => $quantity,
			'available' => $quantity,
			'item_status' => 'active',
			'item_price' => $price,
			'compare_price' => $compare_price ?? 0, // 【關鍵修復】儲存原價到資料庫欄位
			'payment_type' => 'onetime', // 【關鍵修復】FluentCart 核心欄位
			'other_info' => wp_json_encode( $other_info ), // 【新增】包含 payment_type 和原價
			'created_at' => current_time( 'mysql' ),
			'updated_at' => current_time( 'mysql' ),
		);

		$result = $wpdb->insert( $table_name, $variation_data );

		if ( $result === false ) {
			$error = new \WP_Error( 'insert_failed', '變體插入失敗：' . $wpdb->last_error );
			$this->debugService->log( 'FluentCartService', '變體插入失敗', array(
				'error' => $wpdb->last_error,
				'product_id' => $product_id,
				'table_name' => $table_name,
			), 'error' );
			$this->webhookLogger->log( 'error', array(
				'message' => '變體插入失敗',
				'error' => $wpdb->last_error,
				'error_code' => 'insert_failed',
				'product_id' => $product_id,
				'table_name' => $table_name,
			), $product_data['user_id'] ?? null, $product_data['line_uid'] ?? null );
			return $error;
		}

		$this->debugService->log( 'FluentCartService', '變體建立成功', array(
			'product_id' => $product_id,
			'variation_id' => $wpdb->insert_id,
			'variation_title' => $variation_title,
		), 'info' );

		return $wpdb->insert_id;
	}

	/**
	 * 建立預設商品變體
	 *
	 * @param int $product_id Product ID
	 * @param array $data Product data
	 * @return bool|\WP_Error 成功返回 true，失敗返回 WP_Error
	 */
	private function create_default_variation( $product_id, $data ) {
		global $wpdb;

		// FluentCart 使用「分」為單位儲存價格，所以 350 元要存成 35000
		$price = intval( $data['price'] ?? 0 ) * 100;
		$quantity = intval( $data['quantity'] ?? 0 );

		// 處理原價 (compare_price / original_price)
		$compare_price = null;
		if ( ! empty( $data['original_price'] ) || ! empty( $data['compare_price'] ) ) {
			$compare_price = intval( $data['original_price'] ?? $data['compare_price'] ?? 0 ) * 100;
		}

		// 建立 other_info JSON (包含 payment_type 和原價)
		$other_info = array(
			'payment_type' => 'onetime', // 【關鍵修復】設定為單次購買
			'description' => $data['description'] ?? '',
		);

		// 如果有原價，加入到 other_info
		if ( $compare_price && $compare_price > $price ) {
			$other_info['compare_price'] = $compare_price;
		}

		$table_name = $wpdb->prefix . 'fct_product_variations';

		// 檢查表是否存在（使用 prepare 確保安全性）
		$table_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) );
		if ( $table_exists !== $table_name ) {
			$error = new \WP_Error( 'table_not_found', 'fct_product_variations 資料表不存在' );
			$this->debugService->log( 'FluentCartService', 'fct_product_variations 資料表不存在', array(
				'error' => $error->get_error_message(),
				'table_name' => $table_name,
			), 'error' );
			$this->webhookLogger->log( 'error', array(
				'message' => 'fct_product_variations 資料表不存在',
				'error' => $error->get_error_message(),
				'table_name' => $table_name,
			), $product_data['user_id'] ?? null, $product_data['line_uid'] ?? null );
			return $error;
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
			'compare_price' => $compare_price ?? 0, // 【關鍵修復】儲存原價到資料庫欄位
			'payment_type' => 'onetime', // 【關鍵修復】FluentCart 核心欄位
			'other_info' => wp_json_encode( $other_info ), // 【新增】包含 payment_type 和原價
			'created_at' => current_time( 'mysql' ),
			'updated_at' => current_time( 'mysql' ),
		);

		$result = $wpdb->insert( $table_name, $variation_data );

		if ( $result === false ) {
			$error = new \WP_Error( 'insert_failed', '商品變體插入失敗：' . $wpdb->last_error );
			$this->debugService->log( 'FluentCartService', '商品變體插入失敗', array(
				'error' => $wpdb->last_error,
				'product_id' => $product_id,
				'table_name' => $table_name,
			), 'error' );
			$this->webhookLogger->log( 'error', array(
				'message' => '商品變體插入失敗',
				'error' => $wpdb->last_error,
				'error_code' => 'insert_failed',
				'product_id' => $product_id,
				'table_name' => $table_name,
			), $data['user_id'] ?? null, $data['line_uid'] ?? null );
			return $error;
		}

		$this->debugService->log( 'FluentCartService', '商品變體建立成功', array(
			'product_id' => $product_id,
			'insert_id' => $wpdb->insert_id,
		), 'info' );

		return true;
	}
}

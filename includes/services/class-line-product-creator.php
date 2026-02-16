<?php
/**
 * LINE Product Creator
 *
 * 從文字訊息解析並建立商品
 *
 * @package BuyGoPlus
 */

namespace BuyGoPlus\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class LineProductCreator
 *
 * 職責：
 * - 處理商品資訊文字（handleProductInfo）
 * - 建立商品並發送確認訊息
 * - 取得商品 URL 和樣式列表
 */
class LineProductCreator {

	/**
	 * Product Data Parser
	 *
	 * @var ProductDataParser
	 */
	private $product_data_parser;

	/**
	 * Webhook Logger
	 *
	 * @var WebhookLogger
	 */
	private $logger;

	/**
	 * Permission Validator
	 *
	 * @var LinePermissionValidator
	 */
	private $validator;

	/**
	 * Messaging Facade
	 *
	 * @var LineMessagingFacade
	 */
	private $messaging;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->product_data_parser = new ProductDataParser();
		$this->logger = WebhookLogger::get_instance();
		$this->validator = new LinePermissionValidator();
		$this->messaging = new LineMessagingFacade();
	}

	/**
	 * 處理商品資訊文字訊息
	 *
	 * 當使用者發送商品資訊文字時：
	 * 1. 檢查是否有待處理的商品圖片
	 * 2. 解析商品資訊
	 * 3. 呼叫 FluentCartService 建立商品
	 * 4. 清除待處理狀態
	 *
	 * @param array  $event    Event data
	 * @param string $line_uid LINE user ID
	 * @param int    $user_id  WordPress user ID
	 * @return void
	 */
	public function handleProductInfo( $event, $line_uid, $user_id ) {
		// 檢查是否為文字訊息
		if ( ! isset( $event['message']['type'] ) || $event['message']['type'] !== 'text' ) {
			return;
		}

		// 檢查是否有待處理的商品圖片
		$image_id = get_user_meta( $user_id, 'pending_product_image', true );
		$product_type = get_user_meta( $user_id, 'pending_product_type', true );

		if ( empty( $image_id ) || empty( $product_type ) ) {
			// 沒有待處理商品，可能是其他對話
			return;
		}

		// 檢查是否超時 (30 分鐘)
		$timestamp = get_user_meta( $user_id, 'pending_product_timestamp', true );
		if ( $timestamp && ( time() - $timestamp ) > 1800 ) {
			// 超時，清除待處理狀態
			delete_user_meta( $user_id, 'pending_product_image' );
			delete_user_meta( $user_id, 'pending_product_type' );
			delete_user_meta( $user_id, 'pending_product_timestamp' );

			$this->logger->log( 'product_creation_timeout', array(
				'elapsed' => time() - $timestamp,
			), $user_id, $line_uid );

			$this->messaging->send_reply( $event['replyToken'] ?? '', '商品建立已超時，請重新上傳圖片。', $line_uid );
			return;
		}

		// 解析文字訊息
		$text = $event['message']['text'] ?? '';
		$parsed = $this->product_data_parser->parse( $text );

		if ( is_wp_error( $parsed ) ) {
			$this->logger->log( 'product_parse_failed', array(
				'error' => $parsed->get_error_message(),
			), $user_id, $line_uid );

			$this->messaging->send_reply( $event['replyToken'] ?? '', '商品資訊解析失敗：' . $parsed->get_error_message(), $line_uid );
			return;
		}

		// 驗證解析結果與使用者選擇的類型是否匹配
		$parsed_type = $parsed['type'] ?? 'simple';
		if ( $parsed_type !== $product_type ) {
			$this->logger->log( 'product_type_mismatch', array(
				'expected' => $product_type,
				'parsed'   => $parsed_type,
			), $user_id, $line_uid );

			// 類型不匹配，但繼續建立（使用解析出的類型）
		}

		// 呼叫 FluentCartService 建立商品
		$service = new FluentCartService();
		// 準備 product_data (符合 create_product 方法的參數格式)
		$product_data = array_merge( $parsed, array(
			'image_attachment_id' => $image_id,
			'user_id'             => $user_id,
			'line_uid'            => $line_uid,
		) );
		$product_id = $service->create_product( $product_data, array( $image_id ) );

		// 轉換回 handleProductInfo 期望的格式
		if ( is_wp_error( $product_id ) ) {
			$result = array(
				'success' => false,
				'error'   => $product_id->get_error_message(),
			);
		} else {
			$result = array(
				'success'    => true,
				'product_id' => $product_id,
				'type'       => $parsed['type'] ?? 'simple',
			);
		}

		// 清除待處理狀態
		delete_user_meta( $user_id, 'pending_product_image' );
		delete_user_meta( $user_id, 'pending_product_type' );
		delete_user_meta( $user_id, 'pending_product_timestamp' );

		// 發送結果訊息
		if ( ! empty( $result['success'] ) ) {
			$product_id = $result['product_id'];
			$product_url = $this->getProductUrl( $product_id );

			// 取得圖片 URL
			$image_url = wp_get_attachment_url( $image_id );

			// 組裝確認訊息資料
			$confirm_data = array(
				'name'      => $parsed['name'],
				'url'       => $product_url,
				'image_url' => $image_url ?: '',
			);

			if ( $result['type'] === 'simple' ) {
				$confirm_data['price']          = $parsed['price'];
				$confirm_data['original_price'] = $parsed['original_price'] ?? null;
				$confirm_data['quantity']       = $parsed['quantity'];
			} else {
				// 多樣式商品：從 FluentCart 資料庫取得 variations
				$variations = $this->getProductVariations( $product_id );
				$confirm_data['variations'] = $variations;
			}

			// 發送確認訊息 Flex Message（透過 facade，支援 Reply → Push fallback）
			$flex_contents = LineFlexTemplates::getProductConfirmation( $confirm_data, $result['type'] );
			$this->messaging->send_flex( $event['replyToken'] ?? '', $flex_contents, $line_uid, '商品上架成功' );

			$this->logger->log( 'product_created', array(
				'product_id' => $product_id,
				'type'       => $result['type'],
			), $user_id, $line_uid );
		} else {
			$message = "❌ 商品建立失敗：" . ( $result['error'] ?? '未知錯誤' );

			$this->logger->log( 'product_creation_failed', array(
				'error' => $result['error'] ?? 'unknown',
			), $user_id, $line_uid );

			$this->messaging->send_reply( $event['replyToken'] ?? '', $message, $line_uid );
		}
	}

	/**
	 * 取得商品 URL（LINE 內使用 LIFF 開啟）
	 *
	 * @param int $product_id 商品 ID
	 * @return string 商品 URL
	 */
	public function getProductUrl( $product_id ) {
		return self::buildProductUrl( $product_id );
	}

	/**
	 * 生成商品連結（靜態方法，供其他類別共用）
	 *
	 * 如果 LineHub 已設定 LIFF ID，生成 LIFF URL（LINE 內部瀏覽器開啟）
	 * 否則使用一般短連結
	 *
	 * @param int $product_id 商品 ID
	 * @return string 商品 URL
	 */
	public static function buildProductUrl( $product_id ) {
		// 優先使用 LIFF URL（在 LINE 內部瀏覽器開啟）
		if ( class_exists( '\\LineHub\\Services\\SettingsService' ) ) {
			$liff_id = \LineHub\Services\SettingsService::get( 'general', 'liff_id', '' );
			if ( ! empty( $liff_id ) ) {
				$redirect = urlencode( "/item/{$product_id}" );
				return "https://liff.line.me/{$liff_id}?redirect={$redirect}";
			}
		}

		// Fallback：一般短連結
		return home_url( "/item/{$product_id}" );
	}

	/**
	 * 取得商品樣式列表（從 FluentCart 資料庫）
	 *
	 * @param int $product_id 商品 ID
	 * @return array 樣式列表
	 */
	public function getProductVariations( $product_id ) {
		global $wpdb;

		$variations = $wpdb->get_results( $wpdb->prepare(
			"SELECT variation_title, item_price, total_stock
			FROM {$wpdb->prefix}fct_product_variations
			WHERE post_id = %d
			ORDER BY id ASC",
			$product_id
		), ARRAY_A );

		if ( empty( $variations ) ) {
			return array();
		}

		// 轉換格式：price 從分轉為元
		$result = array();
		foreach ( $variations as $variation ) {
			$result[] = array(
				'variation_title' => $variation['variation_title'],
				'price'           => intval( $variation['item_price'] ) / 100,
				'quantity'        => intval( $variation['total_stock'] ),
			);
		}

		return $result;
	}

}

<?php
/**
 * LINE Text Router
 *
 * 文字訊息分派（關鍵字、命令、綁定碼、商品資訊）
 *
 * @package BuyGoPlus
 */

namespace BuyGoPlus\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class LineTextRouter
 *
 * 職責：
 * - 處理文字訊息 hook 入口（handleTextMessage）
 * - 綁定碼流程
 * - 關鍵字回應
 * - 命令處理
 * - 商品資訊路由
 */
class LineTextRouter {

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
	 * Product Creator
	 *
	 * @var LineProductCreator
	 */
	private $product_creator;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->product_data_parser = new ProductDataParser();
		$this->logger = WebhookLogger::get_instance();
		$this->validator = new LinePermissionValidator();
		$this->messaging = new LineMessagingFacade();
		$this->product_creator = new LineProductCreator();
	}

	/**
	 * 處理文字訊息（由 LINE Webhook Hook 觸發）
	 *
	 * 這是處理所有文字訊息的入口點，包含：
	 * - 關鍵字回應（後台設定的關鍵字模板）
	 * - 命令處理（/one、/many、/help 等）
	 * - 綁定碼流程
	 * - 商品資訊輸入
	 *
	 * @param array       $event      LINE Webhook 事件資料
	 * @param string      $line_uid   LINE User ID
	 * @param int|null    $user_id    WordPress User ID（未綁定時為 null）
	 * @param string      $message_id LINE Message ID
	 * @return void
	 */
	public function handleTextMessage( $event, $line_uid, $user_id, $message_id ) {
		$text = $event['message']['text'] ?? '';

		// 特例：綁定碼流程不受身份過濾限制（讓未綁定用戶也能綁定）
		$is_binding_code = preg_match( '/^\s*(?:綁定|bind)?\s*([0-9]{6})\s*$/iu', $text );

		// Phase 29: 身份過濾 - 買家和未綁定用戶靜默不回應（綁定碼除外）
		if ( ! $is_binding_code && ! $this->validator->shouldBotRespond( $line_uid, $user_id, 'text' ) ) {
			return;
		}

		$this->logger->log( 'text_message_hook_received', array(
			'line_uid'   => $line_uid,
			'user_id'    => $user_id,
			'message_id' => $message_id,
			'text'       => substr( $text, 0, 50 ),
		), $user_id, $line_uid );

		// 呼叫內部的文字訊息處理方法
		$this->handle_text_message( $event );
	}

	/**
	 * 處理文字訊息（核心路由邏輯）
	 *
	 * @param array $event Event data
	 */
	private function handle_text_message( $event ) {
		$text        = $event['message']['text'] ?? '';
		$line_uid     = $event['source']['userId'] ?? '';
		$reply_token  = $event['replyToken'] ?? '';

		// 綁定碼流程（用 Messaging API 的 source.userId，確保可推播）
		// 支援：
		// - 直接輸入 6 位數：123456
		// - 或輸入：綁定 123456 / bind 123456
		$maybe_code = null;
		if ( preg_match( '/^\s*(?:綁定|bind)?\s*([0-9]{6})\s*$/iu', $text, $m ) ) {
			$maybe_code = $m[1];
		}

		if ( $maybe_code ) {
			$line_service = new LineService();
			$verify = $line_service->verify_binding_code( $maybe_code, $line_uid );

			if ( is_wp_error( $verify ) ) {
				$this->logger->log( 'binding_failed', array(
					'code' => $maybe_code,
					'line_uid' => $line_uid,
					'error_code' => $verify->get_error_code(),
					'error_message' => $verify->get_error_message(),
				), null, $line_uid );

				$this->messaging->send_reply( $reply_token, '綁定失敗：' . $verify->get_error_message(), $line_uid );
				return;
			}

			$user_id = intval( $verify['user_id'] ?? 0 );

			if ( $user_id > 0 ) {
				// 向後相容：同步寫入 user_meta（其他模組可能依賴）
				update_user_meta( $user_id, 'buygo_line_user_id', $line_uid );
			}

			$this->logger->log( 'binding_completed', array(
				'code' => $maybe_code,
				'user_id' => $user_id,
				'line_uid' => $line_uid,
			), $user_id ?: null, $line_uid );

			$this->messaging->send_reply( $reply_token, '綁定成功！之後下單與出貨通知都會推播到這個 LINE。', $line_uid );
			return;
		}

		// Log text message received
		$this->logger->log( 'text_message_received', array(
			'text' => substr( $text, 0, 100 ), // Log first 100 characters
			'line_uid' => $line_uid,
		), null, $line_uid );

		// 優先檢查關鍵字回應系統（後台設定的關鍵字模板）
		$keyword_reply = $this->handle_keyword_reply( $text, $line_uid );
		if ( $keyword_reply !== null ) {
			$this->messaging->send_reply( $reply_token, $keyword_reply, $line_uid );
			return;
		}

		// 系統指令由 LineKeywordResponder 通過 line_hub/webhook/message/text action 處理（優先級 5）
		// 此處 return 避免 LineTextRouter（優先級 10）重複處理
		$system_commands = array( '/id', '/綁定', '/狀態', '/help', '/說明', '/指令' );
		if ( in_array( strtolower( trim( $text ) ), $system_commands, true ) ) {
			$this->logger->log( 'system_command_detected', array(
				'command'  => $text,
				'line_uid' => $line_uid,
			), null, $line_uid );
			return;
		}

		// Get WordPress user from LINE UID（提前取得，供後續命令和商品資訊處理使用）
		$user = \BuyGoPlus\Core\BuyGoPlus_Core::line()->get_user_by_line_uid( $line_uid );

		// 如果關鍵字回應系統沒有匹配，再檢查是否為命令
		if ( $this->product_data_parser->is_command( $text ) ) {
			$user_id = $user ? $user->ID : 0;
			$this->handle_command( $text, $reply_token, $line_uid, $user_id );
			return;
		}

		if ( ! $user ) {
			$template = \BuyGoPlus\Services\NotificationTemplates::get( 'system_account_not_bound', [] );
			$message = $template && isset( $template['line']['text'] ) ? $template['line']['text'] : '請先使用 LINE Login 綁定您的帳號。';
			$this->messaging->send_reply( $reply_token, $message, $line_uid );
			return;
		}

		// Check permissions (使用統一的權限檢查方法)
		if ( ! $this->validator->can_upload_product( $user ) ) {
			// 記錄權限被拒絕的詳細資訊
			$this->logger->log( 'permission_denied', array(
				'message' => 'User does not have permission to upload products',
				'user_id' => $user->ID,
				'user_login' => $user->user_login,
				'roles' => $user->roles ?? [],
				'display_name' => $user->display_name,
				'message_type' => 'text',
			), $user->ID, $line_uid );

			// 取得虛擬商品購買連結
			$seller_product_id = get_option( 'buygo_seller_product_id' );
			$purchase_url = $seller_product_id ? home_url( "/product/{$seller_product_id}/?openExternalBrowser=1" ) : home_url();

			// 發送權限不足訊息給用戶
			$template = \BuyGoPlus\Services\NotificationTemplates::get( 'system_permission_denied', array(
				'display_name' => $user->display_name ?: $user->user_login,
				'purchase_url' => $purchase_url,
			) );
			$message = $template && isset( $template['line']['text'] ) ? $template['line']['text'] : '抱歉，您目前沒有商品上傳權限。請聯絡管理員開通權限。';
			$this->messaging->send_reply( $reply_token, $message, $line_uid );
			return;
		}

		// Parse product data
		$product_data = $this->product_data_parser->parse( $text );
		$validation   = $this->product_data_parser->validate( $product_data );

		if ( ! $validation['valid'] ) {
			// 清除待處理狀態，避免無限循環
			// 當用戶發送的訊息不是有效的商品資訊時，應該清除待處理狀態
			// 讓用戶可以重新開始上架流程
			if ( class_exists( '\\LineHub\\Services\\ContentService' ) ) {
				( new \LineHub\Services\ContentService() )->clearTempImages( $user->ID );
			}
			delete_user_meta( $user->ID, 'pending_product_image' );
			delete_user_meta( $user->ID, 'pending_product_type' );
			delete_user_meta( $user->ID, 'pending_product_timestamp' );

			$this->logger->log( 'product_validation_failed_cleared_state', array(
				'missing_fields' => $validation['missing'],
				'user_id' => $user->ID,
			), $user->ID, $line_uid );

			// 不發送「資料不完整」訊息 - 用戶可能只是在普通對話
			// 狀態已清除，用戶可以重新開始上架流程
			return;
		}

		// 判斷商品擁有者（賣家 ID）
		// 如果是小幫手上架，商品擁有者應該是賣家，而不是小幫手
		$seller_id = $this->validator->get_product_owner( $user );

		// Add user_id (seller_id), uploader_id, and line_uid to product data
		$product_data['user_id'] = $seller_id;  // 商品擁有者（賣家 ID）
		$product_data['uploader_id'] = $user->ID;  // 實際上架者（可能是小幫手）
		$product_data['line_uid'] = $line_uid;

		// Get temporary images
		$image_ids = array();
		if ( class_exists( '\\LineHub\\Services\\ContentService' ) ) {
			$image_ids = ( new \LineHub\Services\ContentService() )->getTempImages( $user->ID );

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
			$this->messaging->send_reply( $reply_token, $message, $line_uid );
			return;
		}

		// Clear temporary images
		if ( ! empty( $image_ids ) && class_exists( '\\LineHub\\Services\\ContentService' ) ) {
			( new \LineHub\Services\ContentService() )->clearTempImages( $user->ID );
		}

		// Log success
		$this->logger->log( 'product_created', array(
			'product_id' => $post_id,
			'product_name' => $product_data['name'] ?? '',
			'user_id' => $user->ID,
		), $user->ID, $line_uid );

		// Get product URL
		$product_url = $this->product_creator->getProductUrl( $post_id );

		// 記錄日誌以便除錯
		$this->logger->log( 'product_url_generated', array(
			'product_id' => $post_id,
			'permalink' => get_permalink( $post_id ),
			'short_url' => $product_url,
		), $user->ID, $line_uid );

		// Prepare template arguments
		// 根據幣別設定符號（支援多種貨幣）
		$currency = $product_data['currency'] ?? 'TWD';
		$currency_map = array(
			'JPY' => 'JPY',
			'日幣' => 'JPY',
			'TWD' => 'NT$',
			'台幣' => 'NT$',
			'USD' => 'US$',
			'美金' => 'US$',
			'CNY' => '¥',
			'人民幣' => '¥',
			'EUR' => '€',
			'歐元' => '€',
			'KRW' => '₩',
			'韓幣' => '₩',
		);
		$currency_symbol = isset( $currency_map[ $currency ] ) ? $currency_map[ $currency ] : $currency;

		// 產生原價區塊（如果有原價）
		// 支援多樣式產品的多個原價（用斜線分隔顯示）
		$original_price_section = '';
		if ( ! empty( $product_data['original_price'] ) || ! empty( $product_data['compare_price'] ) ) {
			// 如果是多樣式產品，顯示所有原價
			if ( ! empty( $product_data['variations'] ) && is_array( $product_data['variations'] ) ) {
				$original_prices = array();
				foreach ( $product_data['variations'] as $variation ) {
					if ( ! empty( $variation['compare_price'] ) ) {
						$original_prices[] = number_format( $variation['compare_price'] );
					}
				}
				if ( ! empty( $original_prices ) ) {
					$original_price_section = "\n原價：{$currency_symbol} " . implode( '/', $original_prices );
				}
			} else {
				// 單一商品的原價
				$original_price = $product_data['original_price'] ?? $product_data['compare_price'] ?? 0;
				if ( $original_price > 0 ) {
					$original_price_section = "\n原價：{$currency_symbol} " . number_format( $original_price );
				}
			}
		}

		// 產生分類區塊（如果有分類）
		// 支援多樣式產品的多個分類（用斜線分隔顯示）
		$category_section = '';
		if ( ! empty( $product_data['variations'] ) && is_array( $product_data['variations'] ) ) {
			// 多樣式產品：顯示所有分類
			$categories = array();
			foreach ( $product_data['variations'] as $variation ) {
				if ( ! empty( $variation['name'] ) ) {
					$categories[] = $variation['name'];
				}
			}
			if ( ! empty( $categories ) ) {
				$category_section = "\n分類：" . implode( '/', $categories );
			}
		} elseif ( ! empty( $product_data['category'] ) ) {
			// 單一商品的分類
			$category_section = "\n分類：{$product_data['category']}";
		}

		// 產生到貨日期區塊（如果有到貨日期）
		$arrival_date_section = '';
		if ( ! empty( $product_data['arrival_date'] ) ) {
			// 格式化日期顯示（如果是 YYYY-MM-DD 格式，轉換為 YYYY/MM/DD）
			$arrival_date = $product_data['arrival_date'];
			if ( preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $arrival_date, $matches ) ) {
				$arrival_date = "{$matches[1]}/{$matches[2]}/{$matches[3]}";
			}
			$arrival_date_section = "\n到貨日期：{$arrival_date}";
		}

		// 產生預購日期區塊（如果有預購日期）
		$preorder_date_section = '';
		if ( ! empty( $product_data['preorder_date'] ) ) {
			// 格式化日期顯示（如果是 YYYY-MM-DD 格式，轉換為 YYYY/MM/DD）
			$preorder_date = $product_data['preorder_date'];
			if ( preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $preorder_date, $matches ) ) {
				$preorder_date = "{$matches[1]}/{$matches[2]}/{$matches[3]}";
			}
			$preorder_date_section = "\n預購日期：{$preorder_date}";
		}

		// 處理多樣式產品的價格和數量顯示
		$price_display = '';
		$quantity_display = '';

		if ( ! empty( $product_data['variations'] ) && is_array( $product_data['variations'] ) ) {
			// 多樣式產品：顯示所有價格和數量（用斜線分隔）
			$prices = array();
			$quantities = array();
			foreach ( $product_data['variations'] as $variation ) {
				$variation_price = $variation['price'] ?? $product_data['price'] ?? 0;
				$variation_quantity = $variation['quantity'] ?? 0;
				$prices[] = number_format( $variation_price );
				$quantities[] = $variation_quantity;
			}
			$price_display = implode( '/', $prices );
			$quantity_display = implode( '/', $quantities );
		} else {
			// 單一商品
			$price_display = number_format( $product_data['price'] ?? 0 );
			$quantity_display = $product_data['quantity'] ?? 0;
		}

		// 計算 original_price 變數（用於向後兼容舊模板）
		$original_price_value = '';
		if ( ! empty( $product_data['original_price'] ) || ! empty( $product_data['compare_price'] ) ) {
			$original_price_value = number_format( $product_data['original_price'] ?? $product_data['compare_price'] ?? 0 );
		}

		$template_args = array(
			'product_name' => $product_data['name'] ?? '',
			'price' => $price_display,
			'quantity' => $quantity_display,
			'product_url' => $product_url,
			'currency_symbol' => $currency_symbol,
			// 同時提供 original_price 和 original_price_section 以保持向後兼容
			'original_price' => $original_price_value,
			'original_price_section' => $original_price_section,
			'category_section' => $category_section,
			'arrival_date_section' => $arrival_date_section,
			'preorder_date_section' => $preorder_date_section,
		);

		$template = \BuyGoPlus\Services\NotificationTemplates::get( 'system_product_published', $template_args );
		$message = $template && isset( $template['line']['text'] ) ? $template['line']['text'] : '商品建立成功';
		$this->messaging->send_reply( $reply_token, $message, $line_uid );
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
	 * @param string $line_uid LINE UID
	 * @param int    $user_id WordPress user ID
	 */
	private function handle_command( $command, $reply_token, $line_uid = null, $user_id = 0 ) {
		$command = trim( $command );

		// Handle /one command - 從模板系統讀取
		if ( $command === '/one' ) {
			// 設定商品類型為單一商品
			if ( $user_id > 0 ) {
				update_user_meta( $user_id, 'pending_product_type', 'simple' );
				$this->logger->log( 'product_type_set_by_command', array(
					'command' => '/one',
					'type'    => 'simple',
				), $user_id, $line_uid );
			}

			$template = \BuyGoPlus\Services\NotificationTemplates::get( 'system_command_one_template', [] );
			$message = $template && isset( $template['line']['text'] )
				? $template['line']['text']
				: "📋 複製以下格式發送：\n\n商品名稱\n價格：\n數量：";
			$this->messaging->send_reply( $reply_token, $message, $line_uid );
			return;
		}

		// Handle /many command - 從模板系統讀取
		if ( $command === '/many' ) {
			// 設定商品類型為多樣式商品
			if ( $user_id > 0 ) {
				update_user_meta( $user_id, 'pending_product_type', 'variable' );
				$this->logger->log( 'product_type_set_by_command', array(
					'command' => '/many',
					'type'    => 'variable',
				), $user_id, $line_uid );
			}

			$template = \BuyGoPlus\Services\NotificationTemplates::get( 'system_command_many_template', [] );
			$message = $template && isset( $template['line']['text'] )
				? $template['line']['text']
				: "📋 複製以下格式發送 (多樣)：\n\n商品名稱\n價格：\n數量：\n款式1：\n款式2：";
			$this->messaging->send_reply( $reply_token, $message, $line_uid );
			return;
		}

		// Handle /help
		if ( in_array( $command, array( '/help', '/幫助', '?help', '幫助' ), true ) ) {
			$this->messaging->send_help( $reply_token, $line_uid );
			return;
		}
	}
}

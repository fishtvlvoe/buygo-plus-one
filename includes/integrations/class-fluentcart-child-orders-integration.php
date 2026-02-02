<?php
/**
 * FluentCart Child Orders Integration
 *
 * 使用 WordPress hooks 在 FluentCart 客戶檔案頁面中顯示子訂單資訊
 *
 * @package BuygoPlus
 */

namespace BuygoPlus\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FluentCartChildOrdersIntegration
 *
 * 在 FluentCart 客戶檔案頁面注入子訂單查看功能
 */
class FluentCartChildOrdersIntegration {

	/**
	 * 註冊 hooks
	 */
	public static function register_hooks(): void {
		// 在 FluentCart 客戶檔案頁面的 Vue app 之後注入子訂單區塊
		// 使用 fluent_cart/customer_app hook，priority 100 確保在 Vue App 之後載入
		\add_action( 'fluent_cart/customer_app', [ __CLASS__, 'render_child_orders_section' ], 100 );

		// 載入 JavaScript 和 CSS
		\add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
	}

	/**
	 * 渲染子訂單區塊
	 */
	public static function render_child_orders_section(): void {
		if ( ! \is_user_logged_in() ) {
			return;
		}

		?>
		<div id="buygo-child-orders-widget" class="buygo-child-orders-widget">
			<button id="buygo-view-child-orders-btn" class="buygo-btn buygo-btn-primary" data-expanded="false">
				查看子訂單
			</button>
			<div id="buygo-child-orders-container" class="buygo-child-orders-container" style="display: none;">
				<p class="buygo-child-orders-loading">載入中...</p>
			</div>
		</div>
		<?php
	}

	/**
	 * 載入 JavaScript 和 CSS
	 */
	public static function enqueue_assets(): void {
		// 只在客戶檔案頁面載入（僅檢查 URL，登入檢查由 render 方法處理）
		if ( ! self::is_customer_profile_page() ) {
			return;
		}

		// 註冊 JavaScript
		\wp_enqueue_script(
			'buygo-child-orders',
			BUYGO_PLUS_ONE_PLUGIN_URL . 'assets/js/fluentcart-child-orders.js',
			[],
			BUYGO_PLUS_ONE_VERSION,
			true
		);

		// 傳遞配置到 JavaScript（為 Phase 36 API 整合做準備）
		\wp_localize_script(
			'buygo-child-orders',
			'buygoChildOrders',
			[
				'apiBase' => \rest_url( 'buygo-plus-one/v1' ),
				'nonce'   => \wp_create_nonce( 'wp_rest' ),
			]
		);

		// 載入樣式（使用 wp_enqueue_style + wp_add_inline_style）
		\wp_register_style(
			'buygo-child-orders-widget',
			false // 不需要外部 CSS 檔案
		);
		\wp_enqueue_style( 'buygo-child-orders-widget' );
		\wp_add_inline_style(
			'buygo-child-orders-widget',
			self::get_inline_css()
		);
	}

	/**
	 * 檢查是否為客戶檔案頁面
	 *
	 * @return bool
	 */
	private static function is_customer_profile_page(): bool {
		// 檢查是否為 FluentCart 客戶檔案頁面
		// 僅檢查 URL，不檢查登入狀態（登入檢查由 render 方法處理）
		// 這樣可以避免 wp_enqueue_scripts 執行時登入狀態尚未初始化的問題
		$current_url = $_SERVER['REQUEST_URI'] ?? '';

		return (
			strpos( $current_url, '/my-account/' ) !== false ||
			strpos( $current_url, '/customer-profile/' ) !== false
		);
	}

	/**
	 * 取得內聯 CSS
	 *
	 * @return string
	 */
	private static function get_inline_css(): string {
		return '
		/* BuyGo 子訂單區塊樣式 */
		.buygo-child-orders-widget {
			margin: 20px 0;
			padding: 20px;
			background: #fff;
			border: 1px solid #e0e0e0;
			border-radius: 8px;
		}

		/* 按鈕樣式 */
		.buygo-btn {
			padding: 12px 24px;
			border: none;
			border-radius: 6px;
			cursor: pointer;
			font-size: 14px;
			font-weight: 500;
			transition: all 0.2s ease;
			display: inline-flex;
			align-items: center;
			justify-content: center;
			gap: 8px;
			text-decoration: none;
		}

		.buygo-btn-primary {
			background: #3b82f6;
			color: white;
		}

		.buygo-btn-primary:hover {
			background: #2563eb;
		}

		.buygo-btn:disabled {
			opacity: 0.6;
			cursor: not-allowed;
		}

		/* 子訂單容器 */
		.buygo-child-orders-container {
			margin-top: 16px;
			padding: 16px;
			background: #f9fafb;
			border-radius: 6px;
			border: 1px solid #e5e7eb;
		}

		.buygo-child-orders-loading {
			text-align: center;
			color: #6b7280;
			margin: 0;
			padding: 20px;
		}

		/* 響應式設計 */
		@media (max-width: 768px) {
			.buygo-child-orders-widget {
				padding: 12px;
			}

			.buygo-btn {
				width: 100%;
				font-size: 14px;
			}

			.buygo-child-orders-container {
				padding: 12px;
			}
		}
		';
	}
}

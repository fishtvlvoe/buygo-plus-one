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

		// 從 URL 解析訂單 ID（用於訂單詳情頁）
		$order_id = self::get_order_id_from_url();

		// 檢查是否為購買歷史列表頁
		$is_purchase_history = self::is_purchase_history_page();

		// 如果不在訂單詳情頁也不在購買歷史頁，不顯示
		if ( ! $order_id && ! $is_purchase_history ) {
			return;
		}

		?>
		<div id="buygo-child-orders-widget" class="buygo-child-orders-widget">
			<?php if ( $order_id ) : ?>
				<!-- 訂單詳情頁模式：直接顯示該訂單的子訂單 -->
				<button id="buygo-view-child-orders-btn" class="buygo-btn buygo-btn-primary" data-expanded="false" data-order-id="<?php echo \esc_attr( $order_id ); ?>">
					查看子訂單
				</button>
			<?php else : ?>
				<!-- 購買歷史頁模式：使用 MutationObserver 動態注入按鈕到訂單列表 -->
				<div class="buygo-child-orders-header">
					<h3>子訂單查詢</h3>
					<p>點擊訂單列表中的「子訂單」按鈕查看詳情</p>
				</div>
			<?php endif; ?>
			<div id="buygo-child-orders-container" class="buygo-child-orders-container" style="display: none;">
				<!-- 內容由 JavaScript 動態載入 -->
			</div>
		</div>
		<?php
	}

	/**
	 * 檢查是否為購買歷史列表頁（非訂單詳情頁）
	 *
	 * @return bool
	 */
	private static function is_purchase_history_page(): bool {
		$current_url = $_SERVER['REQUEST_URI'] ?? '';

		// 購買歷史列表頁 URL
		if ( strpos( $current_url, '/my-account/purchase-history' ) !== false ) {
			// 排除訂單詳情頁（URL 包含 /order/）
			if ( strpos( $current_url, '/order/' ) === false ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * 從 URL 解析訂單 ID
	 *
	 * FluentCart 訂單詳情頁面 URL 格式：
	 * - /my-account/order/{order_hash}
	 * - /my-account/purchase-history/order/{order_id}
	 * - /customer-profile/orders/{order_id}
	 *
	 * @return string|null 訂單 ID（數字或 hash），或 null 如果無法解析
	 */
	private static function get_order_id_from_url(): ?string {
		$current_url = $_SERVER['REQUEST_URI'] ?? '';

		// 嘗試匹配多種 URL 格式（支援數字和 hash 格式）
		$patterns = [
			'/\/order\/([a-f0-9]{32})/',     // /order/{32字元hash}（FluentCart hash 格式）
			'/\/order\/(\d+)/',              // /order/123（數字格式）
			'/\/orders\/([a-f0-9]{32})/',    // /orders/{32字元hash}
			'/\/orders\/(\d+)/',             // /orders/123
			'/[?&]order_id=([a-f0-9]{32})/', // ?order_id={hash}
			'/[?&]order_id=(\d+)/',          // ?order_id=123
		];

		foreach ( $patterns as $pattern ) {
			if ( preg_match( $pattern, $current_url, $matches ) ) {
				return $matches[1];
			}
		}

		return null;
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
			min-height: 44px;
		}

		.buygo-btn-primary {
			background: var(--buygo-primary, #3b82f6);
			color: white;
		}

		.buygo-btn-primary:hover {
			background: var(--buygo-primary-dark, #2563eb);
		}

		.buygo-btn-secondary {
			background: var(--buygo-secondary, #6b7280);
			color: white;
		}

		.buygo-btn-secondary:hover {
			background: var(--buygo-secondary-dark, #4b5563);
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

		/* 子訂單列表容器 - Mobile First */
		.buygo-child-orders-list {
			display: flex;
			flex-direction: column;
			gap: 16px;
		}

		/* 子訂單卡片 */
		.buygo-child-order-card {
			background: #fff;
			border: 1px solid #e5e7eb;
			border-radius: 12px;
			box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
			overflow: hidden;
		}

		.buygo-card-header {
			display: flex;
			flex-direction: column;
			gap: 8px;
			padding: 16px;
			background: #f9fafb;
			border-bottom: 1px solid #e5e7eb;
		}

		.buygo-card-seller {
			display: flex;
			align-items: center;
			gap: 8px;
		}

		.buygo-seller-label {
			font-size: 12px;
			color: #6b7280;
		}

		.buygo-seller-name {
			font-size: 14px;
			font-weight: 600;
			color: #111827;
		}

		.buygo-card-badges {
			display: flex;
			flex-wrap: wrap;
			gap: 6px;
		}

		.buygo-card-body {
			padding: 16px;
		}

		.buygo-order-items {
			display: flex;
			flex-direction: column;
			gap: 12px;
		}

		.buygo-order-item {
			display: flex;
			align-items: center;
			gap: 12px;
			padding: 8px 0;
			border-bottom: 1px solid #f3f4f6;
		}

		.buygo-order-item:last-child {
			border-bottom: none;
		}

		.buygo-item-title {
			flex: 1;
			font-size: 14px;
			color: #374151;
		}

		.buygo-item-qty {
			font-size: 13px;
			color: #6b7280;
			white-space: nowrap;
		}

		.buygo-item-price {
			font-size: 14px;
			font-weight: 500;
			color: #111827;
			white-space: nowrap;
		}

		.buygo-card-footer {
			display: flex;
			justify-content: space-between;
			align-items: center;
			padding: 16px;
			background: #f9fafb;
			border-top: 1px solid #e5e7eb;
		}

		.buygo-subtotal-label {
			font-size: 14px;
			color: #6b7280;
		}

		.buygo-subtotal-amount {
			font-size: 18px;
			font-weight: 700;
			color: var(--buygo-primary, #3b82f6);
		}

		/* 狀態標籤 */
		.buygo-badge {
			display: inline-flex;
			align-items: center;
			padding: 2px 10px;
			font-size: 12px;
			font-weight: 500;
			border-radius: 9999px;
			white-space: nowrap;
		}

		.buygo-badge-success {
			background: #d1fae5;
			color: #065f46;
		}

		.buygo-badge-warning {
			background: #fef3c7;
			color: #92400e;
		}

		.buygo-badge-danger {
			background: #fee2e2;
			color: #991b1b;
		}

		.buygo-badge-info {
			background: #dbeafe;
			color: #1e40af;
		}

		.buygo-badge-neutral {
			background: #f3f4f6;
			color: #374151;
		}

		/* Loading 狀態 */
		.buygo-loading {
			display: flex;
			flex-direction: column;
			align-items: center;
			justify-content: center;
			padding: 48px 16px;
			text-align: center;
		}

		.buygo-loading p {
			margin: 16px 0 0;
			color: #6b7280;
			font-size: 14px;
		}

		.buygo-loading-spinner {
			width: 32px;
			height: 32px;
			border: 3px solid #e5e7eb;
			border-top-color: var(--buygo-primary, #3b82f6);
			border-radius: 50%;
			animation: buygo-spin 0.8s linear infinite;
		}

		@keyframes buygo-spin {
			to {
				transform: rotate(360deg);
			}
		}

		/* 空狀態/錯誤狀態 */
		.buygo-empty-state {
			display: flex;
			flex-direction: column;
			align-items: center;
			justify-content: center;
			padding: 48px 16px;
			text-align: center;
		}

		.buygo-empty-icon {
			width: 48px;
			height: 48px;
			color: #9ca3af;
			margin-bottom: 16px;
		}

		.buygo-empty-state p {
			margin: 0 0 16px;
			color: #6b7280;
			font-size: 14px;
		}

		.buygo-error-state .buygo-empty-icon {
			color: #ef4444;
		}

		/* 購買歷史頁面 - 標題區塊 */
		.buygo-child-orders-header {
			display: flex;
			justify-content: space-between;
			align-items: center;
			margin-bottom: 16px;
		}

		.buygo-child-orders-header h3 {
			margin: 0;
			font-size: 16px;
			font-weight: 600;
			color: #111827;
		}

		.buygo-child-orders-header p {
			margin: 4px 0 0;
			font-size: 13px;
			color: #6b7280;
		}

		/* 關閉按鈕 */
		.buygo-close-btn {
			background: transparent;
			border: none;
			padding: 8px;
			cursor: pointer;
			color: #6b7280;
			border-radius: 4px;
			transition: all 0.2s ease;
		}

		.buygo-close-btn:hover {
			background: #f3f4f6;
			color: #111827;
		}

		/* 小型按鈕 - 用於訂單列表中的內聯按鈕 */
		.buygo-btn-sm {
			padding: 6px 12px;
			font-size: 12px;
			min-height: 32px;
			border-radius: 4px;
		}

		/* 內聯子訂單按鈕 */
		.buygo-child-orders-inline-btn {
			margin-left: 8px;
			vertical-align: middle;
		}

		/* 響應式設計 - 桌面版 */
		@media (min-width: 768px) {
			.buygo-child-orders-list {
				display: grid;
				grid-template-columns: repeat(2, 1fr);
				gap: 20px;
			}

			.buygo-card-header {
				flex-direction: row;
				justify-content: space-between;
				align-items: center;
			}

			.buygo-child-order-card {
				padding: 0;
			}
		}

		/* 響應式設計 - 手機版 */
		@media (max-width: 767px) {
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

			.buygo-card-body {
				padding: 12px;
			}

			.buygo-card-header,
			.buygo-card-footer {
				padding: 12px;
			}

			.buygo-subtotal-amount {
				font-size: 16px;
			}
		}
		';
	}
}

<?php
/**
 * FluentCart Child Orders Integration
 *
 * 使用 WordPress hooks 在 FluentCart 會員中心頁面顯示訂單分配狀態摘要
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
 * 在 FluentCart 會員中心頁面注入「訂單分配狀態」摘要區塊
 *
 * 設計理念：
 * - 頁面載入後自動 fetch /allocation-summary API
 * - 顯示所有父訂單的分配數量摘要
 * - 點擊可展開查看子訂單明細
 * - 移除舊的「按鈕觸發」邏輯，改為自動載入
 */
class FluentCartChildOrdersIntegration {

	/**
	 * 註冊 hooks
	 */
	public static function register_hooks(): void {
		// 已搬移到 FluentCartCustomerPortal 的「訂單進度」獨立頁面
		// 不再注入到 FluentCart 儀表板
	}

	/**
	 * 渲染分配狀態摘要區塊
	 *
	 * 只要用戶已登入就渲染容器，內容由 JavaScript 動態載入
	 * 不再依賴 URL 中的 order_id（解決 SPA 路由抓不到 ID 的問題）
	 */
	public static function render_child_orders_section(): void {
		if ( ! \is_user_logged_in() ) {
			return;
		}

		?>
		<div id="buygo-allocation-summary" class="buygo-child-orders-widget">
			<div class="buygo-widget-header">
				<h3 class="buygo-widget-title">訂單分配狀態</h3>
			</div>
			<div id="buygo-allocation-content">
				<!-- JS 動態載入 -->
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

		// 傳遞配置到 JavaScript
		\wp_localize_script(
			'buygo-child-orders',
			'buygoChildOrders',
			[
				'apiBase' => \rest_url( 'buygo-plus-one/v1' ),
				'nonce'   => \wp_create_nonce( 'wp_rest' ),
			]
		);

		// 載入樣式（inline CSS，不需要外部 CSS 檔案）
		\wp_register_style(
			'buygo-child-orders-widget',
			false
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
		$current_url = filter_input( INPUT_SERVER, 'REQUEST_URI', FILTER_SANITIZE_URL ) ?? '';

		return (
			strpos( $current_url, '/my-account/' ) !== false ||
			strpos( $current_url, '/customer-profile/' ) !== false
		);
	}

	/**
	 * 取得內聯 CSS
	 *
	 * 保留基礎 widget 樣式與狀態樣式，移除不再使用的子訂單卡片樣式
	 *
	 * @return string
	 */
	private static function get_inline_css(): string {
		return '
		/* BuyGo 訂單分配摘要區塊基礎樣式 */
		.buygo-child-orders-widget {
			margin: 24px 0;
			padding: 20px;
			background: #fff;
			border: 1px solid #e5e7eb;
			border-radius: 12px;
			box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
		}

		/* Widget 標題 */
		.buygo-widget-header {
			margin-bottom: 16px;
		}

		.buygo-widget-title {
			margin: 0;
			font-size: 16px;
			font-weight: 600;
			color: #111827;
		}

		/* 分配摘要列表 */
		.buygo-allocation-list {
			display: flex;
			flex-direction: column;
			gap: 8px;
		}

		/* 分配摘要列 */
		.buygo-allocation-row {
			display: flex;
			align-items: center;
			justify-content: space-between;
			padding: 12px 16px;
			background: #f9fafb;
			border: 1px solid #e5e7eb;
			border-radius: 8px;
			cursor: pointer;
			transition: background 0.15s ease;
			gap: 12px;
		}

		.buygo-allocation-row:hover {
			background: #f3f4f6;
			border-color: #d1d5db;
		}

		.buygo-allocation-row.buygo-row-expanded {
			background: #eff6ff;
			border-color: #bfdbfe;
		}

		/* 分配摘要文字 */
		.buygo-allocation-label {
			font-size: 14px;
			color: #374151;
			flex: 1;
		}

		.buygo-allocation-label strong {
			color: #111827;
		}

		.buygo-allocation-meta {
			font-size: 13px;
			color: #6b7280;
			white-space: nowrap;
		}

		/* 展開圖示 */
		.buygo-chevron {
			width: 16px;
			height: 16px;
			color: #9ca3af;
			transition: transform 0.2s ease;
			flex-shrink: 0;
		}

		.buygo-allocation-row.buygo-row-expanded .buygo-chevron {
			transform: rotate(180deg);
		}

		/* 子訂單明細容器（展開時顯示） */
		.buygo-detail-container {
			margin-top: 4px;
			padding: 12px 16px;
			background: #f9fafb;
			border: 1px solid #e5e7eb;
			border-radius: 8px;
			display: none;
		}

		.buygo-detail-container.buygo-detail-visible {
			display: block;
		}

		/* 子訂單卡片 */
		.buygo-child-order-card {
			background: #fff;
			border: 1px solid #e5e7eb;
			border-radius: 8px;
			padding: 12px 16px;
			margin-bottom: 8px;
		}

		.buygo-child-order-card:last-child {
			margin-bottom: 0;
		}

		.buygo-card-header {
			display: flex;
			align-items: center;
			justify-content: space-between;
			gap: 8px;
			margin-bottom: 8px;
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
			padding: 0;
		}

		.buygo-order-items {
			display: flex;
			flex-direction: column;
			gap: 6px;
		}

		.buygo-order-item {
			display: flex;
			align-items: center;
			gap: 12px;
			padding: 6px 0;
			border-bottom: 1px solid #f3f4f6;
			font-size: 13px;
		}

		.buygo-order-item:last-child {
			border-bottom: none;
		}

		.buygo-item-title {
			flex: 1;
			color: #374151;
		}

		.buygo-item-qty {
			color: #6b7280;
			white-space: nowrap;
		}

		.buygo-item-price {
			font-weight: 500;
			color: #111827;
			white-space: nowrap;
		}

		/* 提示文字 */
		.buygo-hint {
			margin-top: 12px;
			font-size: 12px;
			color: #9ca3af;
			text-align: center;
		}

		/* 狀態標籤 */
		.buygo-badge {
			display: inline-flex;
			align-items: center;
			padding: 2px 8px;
			font-size: 11px;
			font-weight: 500;
			border-radius: 9999px;
			white-space: nowrap;
		}

		.buygo-badge-success { background: #d1fae5; color: #065f46; }
		.buygo-badge-warning { background: #fef3c7; color: #92400e; }
		.buygo-badge-danger  { background: #fee2e2; color: #991b1b; }
		.buygo-badge-info    { background: #dbeafe; color: #1e40af; }
		.buygo-badge-neutral { background: #f3f4f6; color: #374151; }

		/* Loading 狀態 */
		.buygo-loading {
			display: flex;
			flex-direction: column;
			align-items: center;
			justify-content: center;
			padding: 32px 16px;
			text-align: center;
		}

		.buygo-loading p {
			margin: 12px 0 0;
			color: #6b7280;
			font-size: 14px;
		}

		.buygo-loading-spinner {
			width: 28px;
			height: 28px;
			border: 3px solid #e5e7eb;
			border-top-color: #3b82f6;
			border-radius: 50%;
			animation: buygo-spin 0.8s linear infinite;
		}

		@keyframes buygo-spin {
			to { transform: rotate(360deg); }
		}

		/* 空狀態 / 錯誤狀態 */
		.buygo-empty-state {
			display: flex;
			flex-direction: column;
			align-items: center;
			justify-content: center;
			padding: 32px 16px;
			text-align: center;
			color: #6b7280;
			font-size: 14px;
		}

		/* 手機版調整 */
		@media (max-width: 767px) {
			.buygo-child-orders-widget {
				padding: 16px;
				margin: 16px 0;
			}

			.buygo-allocation-row {
				flex-wrap: wrap;
			}
		}
		';
	}
}

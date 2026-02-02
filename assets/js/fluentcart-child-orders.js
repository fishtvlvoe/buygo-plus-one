/**
 * FluentCart 子訂單展開/折疊功能
 *
 * 使用 Vanilla JavaScript 實作，避免與 FluentCart Vue 3 衝突
 * 只在訂單詳情頁運作，不嘗試在購買歷史列表中注入按鈕
 *
 * @package BuygoPlus
 */

(function() {
	'use strict';

	/**
	 * 狀態映射常數
	 * 將 API 回傳的狀態值對應到中文標籤和 CSS 類別
	 */
	var STATUS_MAP = {
		payment: {
			pending:  { label: '待付款', class: 'warning' },
			paid:     { label: '已付款', class: 'success' },
			failed:   { label: '付款失敗', class: 'danger' },
			refunded: { label: '已退款', class: 'neutral' }
		},
		shipping: {
			unshipped:  { label: '待出貨', class: 'warning' },
			preparing:  { label: '備貨中', class: 'info' },
			shipped:    { label: '已出貨', class: 'success' },
			completed:  { label: '已完成', class: 'success' }
		},
		fulfillment: {
			pending:    { label: '待處理', class: 'neutral' },
			processing: { label: '處理中', class: 'info' },
			completed:  { label: '已完成', class: 'success' },
			cancelled:  { label: '已取消', class: 'danger' }
		}
	};

	/**
	 * 格式化金額
	 *
	 * @param {number} amount - 金額數值
	 * @param {string} currency - 貨幣代碼，預設 TWD
	 * @return {string} 格式化後的金額字串
	 */
	function formatCurrency(amount, currency) {
		currency = currency || 'TWD';
		try {
			return new Intl.NumberFormat('zh-TW', {
				style: 'currency',
				currency: currency,
				minimumFractionDigits: 0,
				maximumFractionDigits: 0
			}).format(amount);
		} catch (e) {
			// Fallback for browsers without Intl support
			return currency + ' ' + amount;
		}
	}

	/**
	 * 取得狀態標籤 HTML
	 *
	 * @param {string} type - 狀態類型 (payment, shipping, fulfillment)
	 * @param {string} status - 狀態值
	 * @return {string} 狀態標籤 HTML
	 */
	function getStatusBadge(type, status) {
		var mapping = STATUS_MAP[type];
		if (!mapping || !mapping[status]) {
			return '<span class="buygo-badge buygo-badge-neutral">' + status + '</span>';
		}
		var info = mapping[status];
		return '<span class="buygo-badge buygo-badge-' + info.class + '">' + info.label + '</span>';
	}

	/**
	 * 渲染 Loading 狀態
	 *
	 * @return {string} Loading UI HTML
	 */
	function renderLoading() {
		return '<div class="buygo-loading">' +
			'<div class="buygo-loading-spinner"></div>' +
			'<p>載入子訂單中...</p>' +
		'</div>';
	}

	/**
	 * 渲染空狀態
	 *
	 * @return {string} 空狀態 UI HTML
	 */
	function renderEmpty() {
		return '<div class="buygo-empty-state">' +
			'<svg class="buygo-empty-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">' +
				'<path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>' +
			'</svg>' +
			'<p>此訂單沒有子訂單</p>' +
		'</div>';
	}

	/**
	 * 渲染錯誤狀態
	 *
	 * @param {string} orderId - 訂單 ID（用於重試）
	 * @return {string} 錯誤狀態 UI HTML
	 */
	function renderError(orderId) {
		return '<div class="buygo-empty-state buygo-error-state">' +
			'<svg class="buygo-empty-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">' +
				'<circle cx="12" cy="12" r="10"/>' +
				'<line x1="12" y1="8" x2="12" y2="12"/>' +
				'<line x1="12" y1="16" x2="12.01" y2="16"/>' +
			'</svg>' +
			'<p>載入失敗，請稍後再試</p>' +
			'<button type="button" class="buygo-btn buygo-btn-secondary buygo-retry-btn" data-order-id="' + orderId + '">' +
				'重試' +
			'</button>' +
		'</div>';
	}

	/**
	 * 渲染單張子訂單卡片
	 *
	 * @param {Object} order - 子訂單資料
	 * @param {string} currency - 貨幣代碼
	 * @return {string} 子訂單卡片 HTML
	 */
	function renderChildOrderCard(order, currency) {
		var itemsHtml = '';
		if (order.items && order.items.length > 0) {
			order.items.forEach(function(item) {
				itemsHtml += '<div class="buygo-order-item">' +
					'<span class="buygo-item-title">' + escapeHtml(item.title) + '</span>' +
					'<span class="buygo-item-qty">x' + item.quantity + '</span>' +
					'<span class="buygo-item-price">' + formatCurrency(item.line_total, currency) + '</span>' +
				'</div>';
			});
		}

		return '<div class="buygo-child-order-card">' +
			'<div class="buygo-card-header">' +
				'<div class="buygo-card-seller">' +
					'<span class="buygo-seller-label">賣家</span>' +
					'<span class="buygo-seller-name">' + escapeHtml(order.seller_name || '未知賣家') + '</span>' +
				'</div>' +
				'<div class="buygo-card-badges">' +
					getStatusBadge('payment', order.payment_status) +
					getStatusBadge('shipping', order.shipping_status) +
				'</div>' +
			'</div>' +
			'<div class="buygo-card-body">' +
				'<div class="buygo-order-items">' +
					itemsHtml +
				'</div>' +
			'</div>' +
			'<div class="buygo-card-footer">' +
				'<span class="buygo-subtotal-label">小計</span>' +
				'<span class="buygo-subtotal-amount">' + formatCurrency(order.total_amount, currency) + '</span>' +
			'</div>' +
		'</div>';
	}

	/**
	 * 渲染子訂單列表
	 *
	 * @param {Array} orders - 子訂單陣列
	 * @param {string} currency - 貨幣代碼
	 * @return {string} 子訂單列表 HTML
	 */
	function renderChildOrders(orders, currency) {
		var html = '<div class="buygo-child-orders-list">';
		orders.forEach(function(order) {
			html += renderChildOrderCard(order, currency);
		});
		html += '</div>';
		return html;
	}

	/**
	 * HTML 跳脫處理
	 *
	 * @param {string} text - 原始文字
	 * @return {string} 跳脫後的文字
	 */
	function escapeHtml(text) {
		if (!text) return '';
		var div = document.createElement('div');
		div.textContent = text;
		return div.innerHTML;
	}

	/**
	 * 載入子訂單資料
	 *
	 * @param {string} orderId - 父訂單 ID
	 * @param {HTMLElement} container - 容器元素
	 */
	function loadChildOrders(orderId, container) {
		// 顯示 Loading 狀態
		container.innerHTML = renderLoading();

		// 檢查必要的配置
		if (!window.buygoChildOrders || !window.buygoChildOrders.apiBase || !window.buygoChildOrders.nonce) {
			console.error('BuyGo Child Orders: Missing configuration');
			container.innerHTML = renderError(orderId);
			bindRetryEvent(container);
			return;
		}

		var apiUrl = window.buygoChildOrders.apiBase + '/child-orders/' + orderId;

		fetch(apiUrl, {
			method: 'GET',
			credentials: 'same-origin',
			headers: {
				'X-WP-Nonce': window.buygoChildOrders.nonce,
				'Content-Type': 'application/json'
			}
		})
		.then(function(response) {
			// 重要：fetch 不會在 4xx/5xx 時 reject，需要檢查 response.ok
			if (!response.ok) {
				throw new Error('HTTP ' + response.status);
			}
			return response.json();
		})
		.then(function(data) {
			if (data.success && data.data && data.data.child_orders && data.data.child_orders.length > 0) {
				// 有子訂單資料
				container.innerHTML = renderChildOrders(data.data.child_orders, data.data.currency || 'TWD');
				container.dataset.loaded = 'true';
			} else {
				// 沒有子訂單
				container.innerHTML = renderEmpty();
				container.dataset.loaded = 'true';
			}
		})
		.catch(function(error) {
			console.error('BuyGo Child Orders: Load failed', error);
			container.innerHTML = renderError(orderId);
			bindRetryEvent(container);
		});
	}

	/**
	 * 綁定重試按鈕事件
	 *
	 * @param {HTMLElement} container - 容器元素
	 */
	function bindRetryEvent(container) {
		var retryBtn = container.querySelector('.buygo-retry-btn');
		if (retryBtn) {
			retryBtn.addEventListener('click', function() {
				var orderId = this.dataset.orderId;
				if (orderId) {
					loadChildOrders(orderId, container);
				}
			});
		}
	}

	/**
	 * 初始化訂單詳情頁模式
	 */
	function init() {
		var button = document.getElementById('buygo-view-child-orders-btn');
		var container = document.getElementById('buygo-child-orders-container');

		if (!button || !container) {
			return;
		}

		// 取得訂單 ID
		var orderId = button.dataset.orderId;

		// 按鈕點擊事件
		button.addEventListener('click', function() {
			var isExpanded = button.getAttribute('data-expanded') === 'true';

			if (isExpanded) {
				// 收合
				container.style.display = 'none';
				button.setAttribute('data-expanded', 'false');
				button.querySelector('.buygo-btn-text').textContent = '查看子訂單';
			} else {
				// 展開
				container.style.display = 'block';
				button.setAttribute('data-expanded', 'true');
				button.querySelector('.buygo-btn-text').textContent = '隱藏子訂單';

				// 只在第一次展開時載入資料
				if (!container.dataset.loaded && orderId) {
					loadChildOrders(orderId, container);
				}
			}
		});
	}

	// 等待 DOM 載入完成
	document.addEventListener('DOMContentLoaded', init);

})();

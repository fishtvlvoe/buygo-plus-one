/**
 * FluentCart 訂單分配狀態摘要功能
 *
 * 頁面載入後自動 fetch /allocation-summary API，
 * 顯示當前客戶所有父訂單的分配數量摘要，
 * 點擊可展開查看子訂單明細。
 *
 * 使用 Vanilla JavaScript 實作，避免與 FluentCart Vue 3 衝突
 *
 * @package BuygoPlus
 */

(function() {
	'use strict';

	/**
	 * 狀態映射常數
	 */
	var STATUS_MAP = {
		payment: {
			pending:  { label: '待付款', class: 'warning' },
			paid:     { label: '已付款', class: 'success' },
			failed:   { label: '付款失敗', class: 'danger' },
			refunded: { label: '已退款', class: 'neutral' }
		},
		shipping: {
			unshipped: { label: '待出貨', class: 'warning' },
			preparing: { label: '備貨中', class: 'info' },
			shipped:   { label: '已出貨', class: 'success' },
			completed: { label: '已完成', class: 'success' }
		}
	};

	/**
	 * HTML 跳脫處理
	 *
	 * @param {string} text 原始文字
	 * @return {string} 跳脫後文字
	 */
	function escapeHtml(text) {
		if (!text && text !== 0) return '';
		var div = document.createElement('div');
		div.textContent = String(text);
		return div.innerHTML;
	}

	/**
	 * 取得狀態標籤 HTML
	 *
	 * @param {string} type   狀態類型 (payment, shipping)
	 * @param {string} status 狀態值
	 * @return {string} badge HTML
	 */
	function getStatusBadge(type, status) {
		var mapping = STATUS_MAP[type];
		if (!mapping || !mapping[status]) {
			return '<span class="buygo-badge buygo-badge-neutral">' + escapeHtml(status) + '</span>';
		}
		var info = mapping[status];
		return '<span class="buygo-badge buygo-badge-' + info.class + '">' + info.label + '</span>';
	}

	/**
	 * 渲染 Loading 狀態
	 *
	 * @return {string} Loading HTML
	 */
	function renderLoading() {
		return '<div class="buygo-loading">' +
			'<div class="buygo-loading-spinner"></div>' +
			'<p>載入分配狀態中...</p>' +
		'</div>';
	}

	/**
	 * 渲染空狀態（無任何分配資料）
	 *
	 * @return {string} 空狀態 HTML
	 */
	function renderEmpty() {
		return '<div class="buygo-empty-state">' +
			'<p>目前尚無訂單分配記錄</p>' +
		'</div>';
	}

	/**
	 * 渲染錯誤狀態
	 *
	 * @return {string} 錯誤狀態 HTML
	 */
	function renderError() {
		return '<div class="buygo-empty-state">' +
			'<p>載入失敗，請稍後再試</p>' +
		'</div>';
	}

	/**
	 * 渲染分配摘要列表
	 *
	 * 每筆父訂單顯示一行摘要，點擊可展開子訂單明細
	 *
	 * @param {Array} orders 分配摘要陣列
	 * @return {string} 摘要列表 HTML
	 */
	function renderAllocationList(orders) {
		// 只顯示有分配記錄的訂單
		var filtered = orders.filter(function(o) {
			return o.allocated_quantity > 0 || o.child_order_count > 0;
		});

		if (filtered.length === 0) {
			return '<div class="buygo-empty-state"><p>目前尚無訂單分配記錄</p></div>';
		}

		var html = '<div class="buygo-allocation-list">';

		filtered.forEach(function(order) {
			var orderId       = escapeHtml(order.order_id);
			var allocQty      = order.allocated_quantity || 0;
			var childCount    = order.child_order_count  || 0;

			html +=
				'<div class="buygo-allocation-row" data-order-id="' + orderId + '" data-expanded="false">' +
					'<span class="buygo-allocation-label">' +
						'訂單 <strong>#' + orderId + '</strong>' +
					'</span>' +
					'<span class="buygo-allocation-meta">' +
						'已分配 ' + allocQty + ' 件 &rarr; ' + childCount + ' 筆子單' +
					'</span>' +
					'<svg class="buygo-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">' +
						'<polyline points="6 9 12 15 18 9"/>' +
					'</svg>' +
				'</div>' +
				'<div class="buygo-detail-container" id="buygo-detail-' + orderId + '">' +
					'<!-- 子訂單明細（點擊後載入）-->' +
				'</div>';
		});

		html += '</div>';
		html += '<p class="buygo-hint">點擊上方訂單可查看分配明細</p>';

		return html;
	}

	/**
	 * 格式化金額
	 *
	 * @param {number} amount   金額（元）
	 * @param {string} currency 貨幣代碼
	 * @return {string} 格式化金額字串
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
			return currency + ' ' + amount;
		}
	}

	/**
	 * 渲染子訂單明細（展開後顯示）
	 *
	 * @param {Array}  childOrders 子訂單陣列
	 * @param {string} currency    貨幣代碼
	 * @return {string} 子訂單明細 HTML
	 */
	function renderChildOrderDetails(childOrders, currency) {
		if (!childOrders || childOrders.length === 0) {
			return '<p class="buygo-empty-state">此訂單沒有子訂單</p>';
		}

		var html = '';

		childOrders.forEach(function(order) {
			var itemsHtml = '';
			if (order.items && order.items.length > 0) {
				order.items.forEach(function(item) {
					itemsHtml +=
						'<div class="buygo-order-item">' +
							'<span class="buygo-item-title">' + escapeHtml(item.title) + '</span>' +
							'<span class="buygo-item-qty">x' + item.quantity + '</span>' +
							'<span class="buygo-item-price">' + formatCurrency(item.line_total, currency) + '</span>' +
						'</div>';
				});
			}

			html +=
				'<div class="buygo-child-order-card">' +
					'<div class="buygo-card-header">' +
						'<div class="buygo-card-seller">' +
							'<span class="buygo-seller-label">賣家</span>' +
							'<span class="buygo-seller-name">' + escapeHtml(order.seller_name || '未知賣家') + '</span>' +
						'</div>' +
						'<div class="buygo-card-badges">' +
							getStatusBadge('payment', order.payment_status) +
							' ' +
							getStatusBadge('shipping', order.shipping_status) +
						'</div>' +
					'</div>' +
					'<div class="buygo-card-body">' +
						'<div class="buygo-order-items">' + itemsHtml + '</div>' +
					'</div>' +
				'</div>';
		});

		return html;
	}

	/**
	 * 載入子訂單明細
	 *
	 * @param {string}      orderId   父訂單 ID
	 * @param {HTMLElement} container 明細容器
	 */
	function loadChildOrderDetails(orderId, container) {
		if (container.dataset.loaded === 'true') {
			// 已載入，僅切換顯示
			return;
		}

		// 顯示 loading
		container.innerHTML = '<div class="buygo-loading"><div class="buygo-loading-spinner"></div><p>載入中...</p></div>';

		var config = window.buygoChildOrders;
		if (!config || !config.apiBase || !config.nonce) {
			container.innerHTML = renderError();
			return;
		}

		fetch(config.apiBase + '/child-orders/' + orderId, {
			method: 'GET',
			credentials: 'same-origin',
			headers: {
				'X-WP-Nonce': config.nonce,
				'Content-Type': 'application/json'
			}
		})
		.then(function(response) {
			if (!response.ok) {
				throw new Error('HTTP ' + response.status);
			}
			return response.json();
		})
		.then(function(data) {
			if (data.success && data.data) {
				container.innerHTML = renderChildOrderDetails(
					data.data.child_orders,
					data.data.currency || 'TWD'
				);
			} else {
				container.innerHTML = '<p class="buygo-empty-state">此訂單沒有子訂單</p>';
			}
			container.dataset.loaded = 'true';
		})
		.catch(function(err) {
			console.error('BuyGo: 載入子訂單明細失敗', err);
			container.innerHTML = renderError();
		});
	}

	/**
	 * 綁定摘要列的點擊事件（展開 / 收合子訂單明細）
	 *
	 * @param {HTMLElement} listEl 摘要列表容器
	 */
	function bindRowClickEvents(listEl) {
		var rows = listEl.querySelectorAll('.buygo-allocation-row');

		rows.forEach(function(row) {
			row.addEventListener('click', function() {
				var orderId   = row.dataset.orderId;
				var isExpanded = row.dataset.expanded === 'true';
				var detail    = document.getElementById('buygo-detail-' + orderId);

				if (!detail) return;

				if (isExpanded) {
					// 收合
					row.dataset.expanded = 'false';
					row.classList.remove('buygo-row-expanded');
					detail.classList.remove('buygo-detail-visible');
				} else {
					// 展開
					row.dataset.expanded = 'true';
					row.classList.add('buygo-row-expanded');
					detail.classList.add('buygo-detail-visible');
					loadChildOrderDetails(orderId, detail);
				}
			});
		});
	}

	/**
	 * 主要載入函式
	 *
	 * 自動 fetch /allocation-summary API 並渲染摘要列表
	 *
	 * @param {HTMLElement} contentEl 內容容器（#buygo-allocation-content）
	 */
	function loadAllocationSummary(contentEl) {
		contentEl.innerHTML = renderLoading();

		var config = window.buygoChildOrders;
		if (!config || !config.apiBase || !config.nonce) {
			console.error('BuyGo: 缺少 API 配置');
			contentEl.innerHTML = renderError();
			return;
		}

		fetch(config.apiBase + '/allocation-summary', {
			method: 'GET',
			credentials: 'same-origin',
			headers: {
				'X-WP-Nonce': config.nonce,
				'Content-Type': 'application/json'
			}
		})
		.then(function(response) {
			if (!response.ok) {
				throw new Error('HTTP ' + response.status);
			}
			return response.json();
		})
		.then(function(data) {
			if (!data.success || !data.data || !data.data.orders) {
				contentEl.innerHTML = renderEmpty();
				return;
			}

			var orders = data.data.orders;

			// 過濾出有分配記錄的訂單（allocated_quantity > 0 或有子單）
			var hasData = orders.some(function(o) {
				return o.allocated_quantity > 0 || o.child_order_count > 0;
			});

			if (!hasData || orders.length === 0) {
				// 無任何分配資料，隱藏整個區塊
				var widget = document.getElementById('buygo-allocation-summary');
				if (widget) {
					widget.style.display = 'none';
				}
				return;
			}

			contentEl.innerHTML = renderAllocationList(orders);
			bindRowClickEvents(contentEl);
		})
		.catch(function(err) {
			console.error('BuyGo: 載入分配摘要失敗', err);
			contentEl.innerHTML = renderError();
		});
	}

	/**
	 * 初始化
	 *
	 * 使用輪詢等待 DOM 容器出現（Vue SPA 動態渲染）
	 */
	function init() {
		var contentEl = document.getElementById('buygo-allocation-content');

		if (contentEl) {
			loadAllocationSummary(contentEl);
			return true;
		}

		return false;
	}

	/**
	 * 啟動 — 等待 DOM 就緒後開始輪詢
	 */
	function start() {
		if (init()) return;

		// 容器尚未出現（Vue 還在渲染），輪詢等待（最多 5 秒）
		var attempts   = 0;
		var maxAttempts = 50; // 50 × 100ms = 5 秒
		var interval   = setInterval(function() {
			attempts++;
			if (init() || attempts >= maxAttempts) {
				clearInterval(interval);
			}
		}, 100);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', start);
	} else {
		start();
	}

})();

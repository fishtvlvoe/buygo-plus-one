/**
 * FluentCart 子訂單展開/折疊功能
 *
 * 使用 Vanilla JavaScript 實作，避免與 FluentCart Vue 3 衝突
 *
 * @package BuygoPlus
 */

(function() {
	'use strict';

	// 等待 DOM 載入完成
	document.addEventListener('DOMContentLoaded', function() {
		const button = document.getElementById('buygo-view-child-orders-btn');
		const container = document.getElementById('buygo-child-orders-container');

		// 如果元素不存在（不在客戶檔案頁面），直接返回
		if (!button || !container) {
			return;
		}

		// 按鈕點擊事件
		button.addEventListener('click', function() {
			const isExpanded = button.getAttribute('data-expanded') === 'true';

			if (isExpanded) {
				// 收合
				container.style.display = 'none';
				button.setAttribute('data-expanded', 'false');
				button.textContent = '查看子訂單';
			} else {
				// 展開
				container.style.display = 'block';
				button.setAttribute('data-expanded', 'true');
				button.textContent = '隱藏子訂單';

				// 預留 API 呼叫位置（Phase 36 實作）
				// 只在第一次展開時載入資料
				// if (!container.dataset.loaded) {
				//     loadChildOrders();
				// }
			}
		});

		/**
		 * 載入子訂單資料（Phase 36 實作）
		 *
		 * 使用 window.buygoChildOrders.apiBase 和 nonce
		 */
		// function loadChildOrders() {
		//     const orderId = button.dataset.orderId;
		//     const apiUrl = window.buygoChildOrders.apiBase + '/child-orders/' + orderId;
		//
		//     container.innerHTML = '<p class="buygo-child-orders-loading">載入中...</p>';
		//
		//     fetch(apiUrl, {
		//         method: 'GET',
		//         credentials: 'same-origin',
		//         headers: {
		//             'X-WP-Nonce': window.buygoChildOrders.nonce
		//         }
		//     })
		//     .then(response => response.json())
		//     .then(data => {
		//         if (data.success && data.child_orders) {
		//             renderChildOrders(data.child_orders);
		//             container.dataset.loaded = 'true';
		//         } else {
		//             container.innerHTML = '<p>此訂單沒有子訂單</p>';
		//         }
		//     })
		//     .catch(error => {
		//         console.error('載入子訂單失敗:', error);
		//         container.innerHTML = '<p>載入失敗，請稍後再試</p>';
		//     });
		// }

		/**
		 * 渲染子訂單列表（Phase 37 實作）
		 */
		// function renderChildOrders(orders) {
		//     let html = '<ul class="buygo-child-orders-list">';
		//     orders.forEach(order => {
		//         html += `
		//             <li class="buygo-child-order-item">
		//                 <span class="order-id">#${order.id}</span>
		//                 <span class="seller-name">${order.seller_name}</span>
		//                 <span class="amount">${order.total}</span>
		//             </li>
		//         `;
		//     });
		//     html += '</ul>';
		//     container.innerHTML = html;
		// }
	});

})();

# Phase 37: 前端 UI 元件與互動 - Research

**Researched:** 2026-02-02
**Domain:** Vanilla JavaScript + CSS Design System Integration
**Confidence:** HIGH

## Summary

本次研究聚焦於實作前端 UI 元件，整合 Phase 36 的 API 資料，在 FluentCart 客戶檔案頁面中提供流暢的子訂單顯示體驗。研究結果顯示 BuyGo+1 已有完整的設計系統（`design-system/` 目錄），包含按鈕、卡片、狀態標籤、表格等元件，可直接復用。

核心發現：
- **設計系統完備**：BuyGo+1 有兩套設計系統 - `design-system/` 目錄（CSS 檔案）和 `DesignSystem.js`（JavaScript 動態注入），前端前台應使用 `DesignSystem.js` 的 `.buygo-*` 命名空間
- **Phase 35 基礎已就緒**：按鈕和容器已在 `class-fluentcart-child-orders-integration.php` 中渲染，JavaScript 也已傳入 `apiBase` 和 `nonce`
- **RWD 策略明確**：使用 Mobile-First 設計，768px 為斷點；手機顯示卡片列表，桌面可選擇性顯示表格
- **狀態管理完整**：需處理 Loading、Success（有資料）、Empty（無子訂單）、Error 四種狀態

**Primary recommendation:** 修改現有的 `fluentcart-child-orders.js`，啟用被註解的 API 呼叫程式碼，使用 `DesignSystem.js` 的 `.buygo-*` 類別渲染子訂單卡片列表，實作 Loading 動畫和錯誤處理。CSS 樣式使用 Inline CSS 方式載入，確保與 FluentCart 樣式隔離。

## Standard Stack

### Core Technologies
| Technology | Version | Purpose | Why Standard |
|------------|---------|---------|--------------|
| Vanilla JavaScript | ES6+ | API 呼叫、DOM 操作、狀態切換 | Phase 35 已採用，避免與 FluentCart Vue 3 衝突 |
| Fetch API | Browser Native | REST API 請求 | 現代瀏覽器標準，無需額外依賴 |
| CSS Custom Properties | CSS3 | 設計 Token（顏色、間距、圓角） | 專案已定義 `--buygo-*` 變數系統 |
| BEM 命名 + `.buygo-` 前綴 | Convention | CSS 類別命名 | 確保樣式隔離，避免與 FluentCart 衝突 |

### Supporting Tools
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| `wp_add_inline_style()` | WordPress Core | 載入自訂 CSS | 避免額外 HTTP 請求，確保樣式在頁面載入時就緒 |
| `wp_localize_script()` | WordPress Core | 傳遞配置到 JavaScript | 已在 Phase 35 設定，傳遞 `apiBase` 和 `nonce` |
| `DesignSystem.js` | BuyGo 內部 | 設計系統變數和通用元件 | 可選：在前端注入 `.buygo-*` 類別樣式 |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Vanilla JavaScript | Alpine.js | 更好的宣告式語法，但增加依賴、可能與 FluentCart Vue 衝突 |
| CSS Custom Properties | Tailwind CSS | 更快速開發，但增加構建複雜度和檔案大小 |
| Inline CSS | 獨立 CSS 檔案 | 更好的快取，但增加 HTTP 請求、需要處理載入順序 |
| 卡片列表 | 表格 | 桌面版資訊密度更高，但手機版體驗差、RWD 複雜度增加 |

## Architecture Patterns

### Recommended File Structure
```
assets/
└── js/
    └── fluentcart-child-orders.js    # 現有檔案，需修改啟用 API 整合

includes/
└── integrations/
    └── class-fluentcart-child-orders-integration.php  # 現有檔案，需擴充 CSS
```

### Pattern 1: 四種 UI 狀態管理
**What:** 明確區分 Loading、Success、Empty、Error 四種狀態，每種狀態有對應的 UI 呈現

**When to use:** 所有需要 API 呼叫的前端 UI

**Example:**
```javascript
// Source: 最佳實踐
const UIState = {
    IDLE: 'idle',
    LOADING: 'loading',
    SUCCESS: 'success',
    EMPTY: 'empty',
    ERROR: 'error'
};

function renderByState(state, data) {
    switch (state) {
        case UIState.LOADING:
            return '<div class="buygo-loading"><div class="buygo-loading-spinner"></div><p>載入中...</p></div>';
        case UIState.SUCCESS:
            return renderChildOrders(data);
        case UIState.EMPTY:
            return '<div class="buygo-empty-state"><p>此訂單沒有子訂單</p></div>';
        case UIState.ERROR:
            return '<div class="buygo-error-state"><p>載入失敗</p><button onclick="retry()">重試</button></div>';
        default:
            return '';
    }
}
```

### Pattern 2: Mobile-First 卡片列表
**What:** 預設顯示為單欄卡片，手機體驗優先

**When to use:** 子訂單列表、商品列表等需要 RWD 的場景

**Example:**
```css
/* Source: MDN CSS Card Layout Cookbook */
/* Mobile First - 預設單欄 */
.buygo-child-orders-list {
    display: flex;
    flex-direction: column;
    gap: var(--buygo-space-md);
}

/* 桌面版 - 可選雙欄 */
@media (min-width: 768px) {
    .buygo-child-orders-list {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: var(--buygo-space-lg);
    }
}
```

### Pattern 3: Fetch API 錯誤處理
**What:** 使用 `response.ok` 檢查 HTTP 錯誤，結合 try-catch 處理網路錯誤

**When to use:** 所有 Fetch API 呼叫

**Example:**
```javascript
// Source: Go Make Things - Error handling with fetch
async function loadChildOrders(orderId) {
    const apiUrl = window.buygoChildOrders.apiBase + '/child-orders/' + orderId;

    try {
        const response = await fetch(apiUrl, {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                'X-WP-Nonce': window.buygoChildOrders.nonce
            }
        });

        // HTTP 錯誤檢查（fetch 不會在 4xx/5xx 時 reject）
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }

        const data = await response.json();
        return data;

    } catch (error) {
        // 網路錯誤或 JSON 解析錯誤
        console.error('載入子訂單失敗:', error);
        throw error;
    }
}
```

### Pattern 4: 狀態標籤映射
**What:** 將後端狀態代碼映射到對應的 CSS 類別和中文標籤

**When to use:** 顯示訂單狀態、付款狀態、出貨狀態

**Example:**
```javascript
// Source: 專案現有 ShippingStatusService 模式
const STATUS_MAP = {
    payment: {
        pending: { label: '待付款', class: 'buygo-badge-warning' },
        paid: { label: '已付款', class: 'buygo-badge-success' },
        failed: { label: '付款失敗', class: 'buygo-badge-danger' },
        refunded: { label: '已退款', class: 'buygo-badge-neutral' }
    },
    shipping: {
        unshipped: { label: '待出貨', class: 'buygo-badge-warning' },
        preparing: { label: '備貨中', class: 'buygo-badge-info' },
        shipped: { label: '已出貨', class: 'buygo-badge-success' },
        completed: { label: '已完成', class: 'buygo-badge-success' }
    },
    fulfillment: {
        pending: { label: '待處理', class: 'buygo-badge-neutral' },
        processing: { label: '處理中', class: 'buygo-badge-info' },
        completed: { label: '已完成', class: 'buygo-badge-success' },
        cancelled: { label: '已取消', class: 'buygo-badge-danger' }
    }
};

function getStatusBadge(type, status) {
    const config = STATUS_MAP[type]?.[status] || { label: status, class: 'buygo-badge-neutral' };
    return `<span class="buygo-badge ${config.class}">${config.label}</span>`;
}
```

### Anti-Patterns to Avoid
- **同步等待 API 回應**：會凍結 UI，必須使用 async/await 或 Promise
- **忽略 HTTP 錯誤狀態**：Fetch 不會在 4xx/5xx 時 reject，需檢查 `response.ok`
- **硬編碼 CSS 值**：應使用 `--buygo-*` CSS 變數確保一致性
- **使用全域 CSS 類別**：必須使用 `.buygo-` 前綴避免與 FluentCart 衝突
- **忽略 Loading 狀態**：使用者不知道操作是否進行中，體驗差
- **觸控目標過小**：手機版按鈕/連結至少 44x44px（Apple HIG 建議）

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| 設計系統變數 | 自己定義顏色、間距 | `--buygo-*` CSS 變數 | 專案已有完整設計 Token |
| Loading Spinner | 自己寫 CSS 動畫 | `.buygo-loading-spinner` | `DesignSystem.js` 已定義 |
| 狀態標籤樣式 | 自己寫顏色組合 | `.buygo-badge-*` | `DesignSystem.js` 已定義 |
| 卡片樣式 | 自己寫邊框圓角陰影 | `.buygo-card` | `DesignSystem.js` 已定義 |
| 錯誤提示 UI | 自己設計樣式 | `.buygo-empty-state` | `DesignSystem.js` 已定義 |
| 金額格式化 | 自己處理千分位 | `Intl.NumberFormat` | 瀏覽器原生 API |

**Key insight:** BuyGo+1 的 `DesignSystem.js` 已定義完整的 UI 元件類別，Phase 37 只需使用這些類別來渲染 HTML，不需要重新設計任何視覺元素。

## Common Pitfalls

### Pitfall 1: 忘記處理 Fetch 的 HTTP 錯誤
**What goes wrong:** API 回傳 403/404/500 時程式碼繼續執行，顯示空白或錯誤的資料

**Why it happens:** Fetch API 只在網路錯誤時 reject，HTTP 錯誤仍然 resolve

**How to avoid:**
```javascript
// ❌ 錯誤：假設 response 一定成功
const data = await response.json();

// ✅ 正確：檢查 response.ok
if (!response.ok) {
    throw new Error(`HTTP ${response.status}`);
}
const data = await response.json();
```

**Warning signs:**
- API 回傳錯誤訊息但 UI 顯示「載入中」
- 錯誤時沒有顯示錯誤訊息

### Pitfall 2: CSS 類別被 FluentCart 覆蓋
**What goes wrong:** 子訂單 UI 樣式與預期不符，顏色或間距被覆蓋

**Why it happens:** FluentCart 有自己的 CSS 樣式，選擇器優先級可能更高

**How to avoid:**
```css
/* ❌ 錯誤：使用通用類別名稱 */
.card { ... }
.btn { ... }

/* ✅ 正確：使用 .buygo- 前綴和更高優先級 */
.buygo-child-orders-widget .buygo-card { ... }
.buygo-child-orders-widget .buygo-btn { ... }
```

**Warning signs:**
- 樣式只在某些頁面生效
- 開發者工具顯示樣式被劃掉

### Pitfall 3: 手機版觸控目標過小
**What goes wrong:** 使用者在手機上無法準確點擊按鈕或連結

**Why it happens:** 桌面版設計的按鈕大小在手機上太小

**How to avoid:**
```css
/* ✅ 正確：確保最小觸控目標 */
.buygo-child-orders-widget .buygo-btn {
    min-height: 44px;
    min-width: 44px;
    padding: 12px 24px;
}

@media (max-width: 767px) {
    .buygo-child-orders-widget .buygo-btn {
        width: 100%;  /* 手機版全寬 */
    }
}
```

**Warning signs:**
- 手機測試時需要多次點擊才能觸發
- 使用者抱怨「按鈕按不到」

### Pitfall 4: 重複載入子訂單資料
**What goes wrong:** 每次展開都發送 API 請求，造成不必要的伺服器負擔

**Why it happens:** 沒有快取機制，每次點擊都重新載入

**How to avoid:**
```javascript
// ✅ 正確：使用 dataset.loaded 標記
if (!container.dataset.loaded) {
    await loadChildOrders();
    container.dataset.loaded = 'true';
}
```

**Warning signs:**
- Network tab 顯示重複的 API 請求
- 展開時總是顯示 Loading

### Pitfall 5: 金額顯示格式不一致
**What goes wrong:** 金額顯示為「350」而非「NT$ 350」或「$350」

**Why it happens:** 後端回傳數字，前端沒有格式化

**How to avoid:**
```javascript
// ✅ 正確：使用 Intl.NumberFormat
function formatCurrency(amount, currency = 'TWD') {
    return new Intl.NumberFormat('zh-TW', {
        style: 'currency',
        currency: currency,
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
    }).format(amount);
}

// 輸出：NT$350
```

**Warning signs:**
- 金額只顯示數字
- 不同地方的金額格式不一致

### Pitfall 6: 空狀態沒有友善提示
**What goes wrong:** 沒有子訂單時顯示空白或「undefined」

**Why it happens:** 直接渲染空陣列，沒有特別處理

**How to avoid:**
```javascript
// ✅ 正確：明確處理空狀態
if (!data.child_orders || data.child_orders.length === 0) {
    container.innerHTML = `
        <div class="buygo-empty-state">
            <svg>...</svg>
            <p>此訂單沒有子訂單</p>
        </div>
    `;
    return;
}
```

**Warning signs:**
- 空訂單顯示空白區域
- 使用者不知道是載入中還是真的沒資料

## Code Examples

### Example 1: 完整的子訂單載入邏輯
```javascript
// Source: 整合 Phase 35 程式碼和最佳實踐
(function() {
    'use strict';

    // 狀態映射
    const STATUS_MAP = {
        payment: {
            pending: { label: '待付款', class: 'buygo-badge-warning' },
            paid: { label: '已付款', class: 'buygo-badge-success' },
            failed: { label: '付款失敗', class: 'buygo-badge-danger' },
            refunded: { label: '已退款', class: 'buygo-badge-neutral' }
        },
        shipping: {
            unshipped: { label: '待出貨', class: 'buygo-badge-warning' },
            preparing: { label: '備貨中', class: 'buygo-badge-info' },
            shipped: { label: '已出貨', class: 'buygo-badge-success' },
            completed: { label: '已完成', class: 'buygo-badge-success' }
        }
    };

    // 金額格式化
    function formatCurrency(amount, currency = 'TWD') {
        return new Intl.NumberFormat('zh-TW', {
            style: 'currency',
            currency: currency,
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        }).format(amount);
    }

    // 取得狀態標籤 HTML
    function getStatusBadge(type, status) {
        const config = STATUS_MAP[type]?.[status] || { label: status, class: 'buygo-badge-neutral' };
        return `<span class="buygo-badge ${config.class}">${config.label}</span>`;
    }

    // 渲染 Loading 狀態
    function renderLoading() {
        return `
            <div class="buygo-loading">
                <div class="buygo-loading-spinner"></div>
                <p style="margin-top: 12px; color: var(--buygo-gray-500);">載入中...</p>
            </div>
        `;
    }

    // 渲染錯誤狀態
    function renderError(retryCallback) {
        return `
            <div class="buygo-empty-state">
                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/>
                    <line x1="12" y1="8" x2="12" y2="12"/>
                    <line x1="12" y1="16" x2="12.01" y2="16"/>
                </svg>
                <p style="margin: 16px 0;">載入失敗，請稍後再試</p>
                <button class="buygo-btn buygo-btn-secondary buygo-retry-btn">重試</button>
            </div>
        `;
    }

    // 渲染空狀態
    function renderEmpty() {
        return `
            <div class="buygo-empty-state">
                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/>
                    <polyline points="13 2 13 9 20 9"/>
                </svg>
                <p style="margin-top: 16px; color: var(--buygo-gray-500);">此訂單沒有子訂單</p>
            </div>
        `;
    }

    // 渲染子訂單卡片
    function renderChildOrderCard(order) {
        const paymentBadge = getStatusBadge('payment', order.payment_status);
        const shippingBadge = getStatusBadge('shipping', order.shipping_status);
        const total = formatCurrency(order.total_amount, order.currency);

        // 渲染商品列表
        const itemsHtml = order.items.map(item => `
            <div class="buygo-child-order-item">
                <span class="buygo-child-order-item-title">${item.title}</span>
                <span class="buygo-child-order-item-qty">x${item.quantity}</span>
                <span class="buygo-child-order-item-price">${formatCurrency(item.line_total, order.currency)}</span>
            </div>
        `).join('');

        return `
            <div class="buygo-card buygo-child-order-card">
                <div class="buygo-child-order-header">
                    <div class="buygo-child-order-seller">
                        <span class="buygo-child-order-seller-label">賣家</span>
                        <span class="buygo-child-order-seller-name">${order.seller_name}</span>
                    </div>
                    <div class="buygo-child-order-badges">
                        ${paymentBadge}
                        ${shippingBadge}
                    </div>
                </div>
                <div class="buygo-child-order-items">
                    ${itemsHtml}
                </div>
                <div class="buygo-child-order-footer">
                    <span class="buygo-child-order-total-label">小計</span>
                    <span class="buygo-child-order-total-value">${total}</span>
                </div>
            </div>
        `;
    }

    // 渲染子訂單列表
    function renderChildOrders(orders, currency) {
        const cardsHtml = orders.map(order => renderChildOrderCard(order)).join('');
        return `
            <div class="buygo-child-orders-list">
                ${cardsHtml}
            </div>
        `;
    }

    // 載入子訂單資料
    async function loadChildOrders(orderId, container) {
        const config = window.buygoChildOrders;
        if (!config) {
            console.error('[BuyGo] Missing buygoChildOrders config');
            return;
        }

        const apiUrl = config.apiBase + '/child-orders/' + orderId;

        // 顯示 Loading
        container.innerHTML = renderLoading();

        try {
            const response = await fetch(apiUrl, {
                method: 'GET',
                credentials: 'same-origin',
                headers: {
                    'X-WP-Nonce': config.nonce
                }
            });

            // 檢查 HTTP 狀態
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const data = await response.json();

            // 檢查 API 回應
            if (!data.success) {
                throw new Error(data.message || '載入失敗');
            }

            // 檢查是否有子訂單
            if (!data.data.child_orders || data.data.child_orders.length === 0) {
                container.innerHTML = renderEmpty();
                return;
            }

            // 渲染子訂單列表
            container.innerHTML = renderChildOrders(data.data.child_orders, data.data.currency);
            container.dataset.loaded = 'true';

        } catch (error) {
            console.error('[BuyGo] 載入子訂單失敗:', error);
            container.innerHTML = renderError();

            // 綁定重試按鈕
            const retryBtn = container.querySelector('.buygo-retry-btn');
            if (retryBtn) {
                retryBtn.addEventListener('click', function() {
                    loadChildOrders(orderId, container);
                });
            }
        }
    }

    // DOM 載入完成後初始化
    document.addEventListener('DOMContentLoaded', function() {
        const button = document.getElementById('buygo-view-child-orders-btn');
        const container = document.getElementById('buygo-child-orders-container');

        if (!button || !container) {
            return;
        }

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

                // 只在第一次展開時載入資料
                if (!container.dataset.loaded) {
                    const orderId = button.dataset.orderId;
                    loadChildOrders(orderId, container);
                }
            }
        });
    });

})();
```

### Example 2: 完整的 Inline CSS
```css
/* Source: 結合 DesignSystem.js 和手機優先設計 */

/* === 容器樣式 === */
.buygo-child-orders-widget {
    margin: 20px 0;
    padding: 20px;
    background: white;
    border: 1px solid var(--buygo-gray-200, #e5e7eb);
    border-radius: var(--buygo-radius-xl, 12px);
}

/* === 按鈕樣式（覆蓋 Phase 35 基礎樣式） === */
.buygo-child-orders-widget .buygo-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 12px 24px;
    border-radius: 8px;
    font-weight: 500;
    font-size: 14px;
    line-height: 1.25;
    transition: all 150ms ease;
    cursor: pointer;
    border: 1px solid transparent;
    text-decoration: none;
    white-space: nowrap;
    min-height: 44px;  /* 觸控目標最小高度 */
}

.buygo-child-orders-widget .buygo-btn-primary {
    background-color: var(--buygo-primary, #3b82f6);
    color: white;
    border-color: var(--buygo-primary, #3b82f6);
}

.buygo-child-orders-widget .buygo-btn-primary:hover {
    background-color: var(--buygo-primary-hover, #2563eb);
    border-color: var(--buygo-primary-hover, #2563eb);
}

.buygo-child-orders-widget .buygo-btn-secondary {
    background-color: white;
    color: var(--buygo-gray-700, #374151);
    border-color: var(--buygo-gray-300, #d1d5db);
}

.buygo-child-orders-widget .buygo-btn-secondary:hover {
    background-color: var(--buygo-gray-50, #f9fafb);
}

/* === 子訂單容器 === */
.buygo-child-orders-container {
    margin-top: 16px;
}

/* === 子訂單列表（Mobile First） === */
.buygo-child-orders-list {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

/* === 子訂單卡片 === */
.buygo-child-order-card {
    background: white;
    border: 1px solid var(--buygo-gray-200, #e5e7eb);
    border-radius: 12px;
    padding: 16px;
    box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05);
}

.buygo-child-order-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 12px;
    padding-bottom: 12px;
    border-bottom: 1px solid var(--buygo-gray-100, #f3f4f6);
}

.buygo-child-order-seller {
    display: flex;
    flex-direction: column;
}

.buygo-child-order-seller-label {
    font-size: 12px;
    color: var(--buygo-gray-500, #6b7280);
}

.buygo-child-order-seller-name {
    font-size: 14px;
    font-weight: 600;
    color: var(--buygo-gray-900, #111827);
}

.buygo-child-order-badges {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

/* === 狀態標籤 === */
.buygo-badge {
    display: inline-flex;
    align-items: center;
    padding: 2px 10px;
    font-size: 12px;
    font-weight: 500;
    border-radius: 9999px;
    border: 1px solid transparent;
}

.buygo-badge-success {
    background-color: #d1fae5;
    color: #065f46;
    border-color: #a7f3d0;
}

.buygo-badge-warning {
    background-color: #fef3c7;
    color: #92400e;
    border-color: #fde68a;
}

.buygo-badge-danger {
    background-color: #fee2e2;
    color: #991b1b;
    border-color: #fecaca;
}

.buygo-badge-info {
    background-color: #dbeafe;
    color: #1e40af;
    border-color: #93c5fd;
}

.buygo-badge-neutral {
    background-color: #f3f4f6;
    color: #374151;
    border-color: #e5e7eb;
}

/* === 商品項目 === */
.buygo-child-order-items {
    margin-bottom: 12px;
}

.buygo-child-order-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px solid var(--buygo-gray-50, #f9fafb);
}

.buygo-child-order-item:last-child {
    border-bottom: none;
}

.buygo-child-order-item-title {
    flex: 1;
    font-size: 14px;
    color: var(--buygo-gray-700, #374151);
    margin-right: 12px;
    /* 超長文字處理 */
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.buygo-child-order-item-qty {
    font-size: 14px;
    color: var(--buygo-gray-500, #6b7280);
    margin-right: 12px;
    white-space: nowrap;
}

.buygo-child-order-item-price {
    font-size: 14px;
    font-weight: 500;
    color: var(--buygo-gray-900, #111827);
    white-space: nowrap;
}

/* === 卡片底部 === */
.buygo-child-order-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 12px;
    border-top: 1px solid var(--buygo-gray-100, #f3f4f6);
}

.buygo-child-order-total-label {
    font-size: 14px;
    color: var(--buygo-gray-500, #6b7280);
}

.buygo-child-order-total-value {
    font-size: 16px;
    font-weight: 700;
    color: var(--buygo-primary, #3b82f6);
}

/* === Loading 狀態 === */
.buygo-loading {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 48px;
    color: var(--buygo-gray-500, #6b7280);
}

.buygo-loading-spinner {
    width: 32px;
    height: 32px;
    border: 2px solid var(--buygo-gray-200, #e5e7eb);
    border-top-color: var(--buygo-primary, #3b82f6);
    border-radius: 50%;
    animation: buygo-spin 0.8s linear infinite;
}

@keyframes buygo-spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

/* === 空狀態 / 錯誤狀態 === */
.buygo-empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 48px;
    text-align: center;
    color: var(--buygo-gray-500, #6b7280);
}

.buygo-empty-state svg {
    width: 48px;
    height: 48px;
    color: var(--buygo-gray-300, #d1d5db);
}

/* === RWD：手機版 === */
@media (max-width: 767px) {
    .buygo-child-orders-widget {
        padding: 12px;
        margin: 12px 0;
    }

    .buygo-child-orders-widget .buygo-btn {
        width: 100%;
    }

    .buygo-child-order-card {
        padding: 12px;
    }

    .buygo-child-order-header {
        flex-direction: column;
        gap: 8px;
    }

    .buygo-child-order-badges {
        align-self: flex-start;
    }
}

/* === RWD：桌面版 === */
@media (min-width: 768px) {
    .buygo-child-orders-list {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 20px;
    }
}
```

### Example 3: PHP 整合（修改 get_inline_css 方法）
```php
// Source: 修改 class-fluentcart-child-orders-integration.php
private static function get_inline_css(): string {
    // 回傳 Example 2 的完整 CSS
    return '/* 上方 Example 2 的所有 CSS */';
}
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| jQuery AJAX | Vanilla Fetch + async/await | 2020+ | 減少依賴，更好的 Promise 支援 |
| 直接操作 DOM 字串 | Template Literals | ES6 (2015) | 更清晰的 HTML 結構，更好的可讀性 |
| CSS 硬編碼值 | CSS Custom Properties | 2020+ 廣泛支援 | 主題一致性，易於維護 |
| Desktop First RWD | Mobile First RWD | 2015+ | 更好的手機體驗，漸進增強 |
| 同步 XHR | async/await + Fetch | 2017+ | 非阻塞 UI，更好的錯誤處理 |

**Deprecated/outdated:**
- **jQuery**：FluentCart 使用 Vue 3，不應再引入 jQuery
- **XHR (XMLHttpRequest)**：Fetch API 更現代、更好用
- **Desktop First CSS**：應使用 Mobile First，考慮 60%+ 手機流量
- **同步操作**：會阻塞 UI，必須使用非同步

## Open Questions

1. **訂單 ID 如何傳遞到按鈕**
   - What we know: Phase 35 的按鈕需要 `data-order-id` 屬性，但目前 `render_child_orders_section()` 沒有接收 order_id 參數
   - What's unclear: FluentCart 的 `fluent_cart/customer_app` hook 是否傳遞當前訂單 ID？還是需要從 URL 解析？
   - Recommendation: 需要在 Phase 37 實作時調查，可能需要修改 PHP 程式碼從 URL 或 global 變數取得當前訂單 ID

2. **設計系統整合方式**
   - What we know: 有兩個設計系統來源 - `design-system/` 目錄和 `DesignSystem.js`
   - What's unclear: 前端前台（非後台）是否有載入這些樣式？
   - Recommendation: Phase 37 使用 Inline CSS 確保樣式獨立，不依賴外部設計系統載入。CSS 值使用 `var(--buygo-*)` 語法，並提供 fallback 值

3. **子訂單列表位置**
   - What we know: Phase 35 將按鈕注入到 `fluent_cart/customer_app` hook
   - What's unclear: 這個位置是在所有客戶檔案頁面還是只在特定頁面？應該只在「訂單詳情」頁面顯示
   - Recommendation: 需要在 JavaScript 中檢查 URL 路徑，只在 `/my-account/purchase-history/order/{id}` 類似的頁面顯示

## Sources

### Primary (HIGH confidence)
- BuyGo+1 現有程式碼：
  - `assets/js/fluentcart-child-orders.js` - Phase 35 JavaScript 基礎
  - `includes/integrations/class-fluentcart-child-orders-integration.php` - PHP 整合類別
  - `admin/js/DesignSystem.js` - 設計系統定義
  - `design-system/` 目錄 - CSS Token 和元件樣式
- Phase 35 RESEARCH.md - FluentCart Hook 整合研究
- Phase 36 RESEARCH.md - API 回應格式定義

### Secondary (MEDIUM confidence)
- [MDN CSS Card Layout Cookbook](https://developer.mozilla.org/en-US/docs/Web/CSS/How_to/Layout_cookbook/Card) - 卡片佈局模式
- [Go Make Things - Error handling with fetch](https://gomakethings.com/error-handing-when-using-the-vanilla-js-fetch-method-with-async-and-await/) - Fetch 錯誤處理最佳實踐
- [DEV Community - Fetch API Error Handling](https://dev.to/dionarodrigues/fetch-api-do-you-really-know-how-to-handle-errors-2gj0) - HTTP 錯誤處理

### Tertiary (LOW confidence)
- [FreeFrontend CSS Cards](https://freefrontend.com/css-cards/) - CSS 卡片設計參考
- [Medium - React Responsive Design](https://medium.com/@dlrnjstjs/react-responsive-design-mobile-first-development-strategy-5292525fe108) - Mobile First 策略（React 文章但原則適用）

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - 完全基於專案現有架構和瀏覽器標準 API
- Architecture: HIGH - 設計系統已完備，只需組合使用
- Pitfalls: HIGH - 基於 Fetch API 文件和 Web 開發最佳實踐

**Research date:** 2026-02-02
**Valid until:** 2026-03-02 (30 days - 前端技術相對穩定)

**Key Takeaways:**
1. Phase 35 已完成按鈕和容器注入，Phase 37 主要工作是啟用 JavaScript API 呼叫和渲染邏輯
2. 使用 `DesignSystem.js` 的 `.buygo-*` 類別確保樣式一致性和隔離性
3. 四種 UI 狀態（Loading/Success/Empty/Error）必須完整處理
4. Mobile First 設計，使用卡片列表而非表格
5. Fetch API 需要手動檢查 `response.ok`，因為 HTTP 錯誤不會觸發 reject
6. 金額使用 `Intl.NumberFormat` 格式化，確保一致性

/* === admin/js/RouterMixin.js === */
/**
 * BuyGo Plus One - Router Mixin
 *
 * 用途：為所有頁面提供統一的 URL 路由功能
 * 使用方式：在 Vue app 中直接使用這些函數
 *
 * @version 1.0.0
 * @author BuyGo Team
 */

(function() {
    'use strict';

    /**
     * 初始化：讀取 URL 參數
     * @returns {Object} { view, id, action }
     */
    function checkUrlParams() {
        const urlParams = new URLSearchParams(window.location.search);
        const view = urlParams.get('view') || 'list';
        const id = urlParams.get('id');
        const action = urlParams.get('action');

        return { view, id, action };
    }

    /**
     * 導航：切換頁面並更新 URL
     * @param {string} view - 視圖名稱 ('list', 'edit', 'detail', 'allocation', 'buyers' 等)
     * @param {string|number|null} id - 項目 ID
     * @param {string|null} action - 額外動作參數
     * @param {boolean} updateHistory - 是否更新瀏覽器歷史記錄 (default: true)
     * @returns {Object} { view, id, action }
     */
    function navigateTo(view, id = null, action = null, updateHistory = true) {
        const url = new URL(window.location);

        if (view === 'list') {
            // 返回列表：清除所有參數
            url.searchParams.delete('view');
            url.searchParams.delete('id');
            url.searchParams.delete('action');
        } else {
            // 進入子頁面：設定參數
            url.searchParams.set('view', view);

            if (id !== null && id !== undefined) {
                url.searchParams.set('id', id);
            } else {
                url.searchParams.delete('id');
            }

            if (action !== null && action !== undefined) {
                url.searchParams.set('action', action);
            } else {
                url.searchParams.delete('action');
            }
        }

        if (updateHistory) {
            window.history.pushState({ view, id, action }, '', url);
        }

        return { view, id, action };
    }

    /**
     * 替換當前歷史記錄（不新增歷史條目）
     * @param {string} view - 視圖名稱
     * @param {string|number|null} id - 項目 ID
     * @param {string|null} action - 額外動作參數
     * @returns {Object} { view, id, action }
     */
    function replaceState(view, id = null, action = null) {
        const url = new URL(window.location);

        if (view === 'list') {
            url.searchParams.delete('view');
            url.searchParams.delete('id');
            url.searchParams.delete('action');
        } else {
            url.searchParams.set('view', view);

            if (id !== null && id !== undefined) {
                url.searchParams.set('id', id);
            } else {
                url.searchParams.delete('id');
            }

            if (action !== null && action !== undefined) {
                url.searchParams.set('action', action);
            } else {
                url.searchParams.delete('action');
            }
        }

        window.history.replaceState({ view, id, action }, '', url);

        return { view, id, action };
    }

    /**
     * 監聽：處理瀏覽器上一頁/下一頁
     * @param {Function} callback - 當 URL 變化時呼叫的回調函數
     * @returns {Function} 移除監聽器的函數
     */
    function setupPopstateListener(callback) {
        const handler = (event) => {
            const params = checkUrlParams();
            callback(params, event.state);
        };

        window.addEventListener('popstate', handler);

        // 返回移除監聽器的函數
        return () => {
            window.removeEventListener('popstate', handler);
        };
    }

    /**
     * 返回列表頁面的快捷方法
     * @param {boolean} updateHistory - 是否更新瀏覽器歷史記錄
     * @returns {Object} { view: 'list', id: null, action: null }
     */
    function goToList(updateHistory = true) {
        return navigateTo('list', null, null, updateHistory);
    }

    /**
     * 檢查當前是否在列表視圖
     * @returns {boolean}
     */
    function isListView() {
        const { view } = checkUrlParams();
        return view === 'list' || !view;
    }

    /**
     * 獲取當前視圖名稱
     * @returns {string}
     */
    function getCurrentView() {
        const { view } = checkUrlParams();
        return view || 'list';
    }

    /**
     * 獲取當前 ID
     * @returns {string|null}
     */
    function getCurrentId() {
        const { id } = checkUrlParams();
        return id;
    }

    // 導出到全域
    window.BuyGoRouter = {
        checkUrlParams,
        navigateTo,
        replaceState,
        setupPopstateListener,
        goToList,
        isListView,
        getCurrentView,
        getCurrentId
    };

    // 如果需要支援 ES6 模組
    if (typeof module !== 'undefined' && module.exports) {
        module.exports = window.BuyGoRouter;
    }

})();
;
/* === admin/js/DesignSystem.js === */
/**
 * BuyGo Plus One - Design System
 *
 * 統一的設計規範與樣式定義
 * 提供一致的視覺風格和動畫效果
 *
 * @version 1.0.0
 * @author BuyGo Team
 */

(function() {
    'use strict';

    // 1. CSS 變數定義
    const cssVariables = `
:root {
    /* 主色系 */
    --buygo-primary: #3b82f6;
    --buygo-primary-hover: #2563eb;
    --buygo-primary-light: #dbeafe;
    --buygo-primary-dark: #1d4ed8;

    /* 語意色彩 */
    --buygo-success: #10b981;
    --buygo-success-light: #d1fae5;
    --buygo-warning: #f59e0b;
    --buygo-warning-light: #fef3c7;
    --buygo-danger: #ef4444;
    --buygo-danger-light: #fee2e2;
    --buygo-info: #06b6d4;
    --buygo-info-light: #cffafe;

    /* CTA 強調色 */
    --buygo-accent: #f97316;
    --buygo-accent-hover: #ea580c;

    /* 中性色系 */
    --buygo-gray-50: #f9fafb;
    --buygo-gray-100: #f3f4f6;
    --buygo-gray-200: #e5e7eb;
    --buygo-gray-300: #d1d5db;
    --buygo-gray-400: #9ca3af;
    --buygo-gray-500: #6b7280;
    --buygo-gray-600: #4b5563;
    --buygo-gray-700: #374151;
    --buygo-gray-800: #1f2937;
    --buygo-gray-900: #111827;

    /* 間距系統 */
    --buygo-space-xs: 0.25rem;  /* 4px */
    --buygo-space-sm: 0.5rem;   /* 8px */
    --buygo-space-md: 1rem;     /* 16px */
    --buygo-space-lg: 1.5rem;   /* 24px */
    --buygo-space-xl: 2rem;     /* 32px */
    --buygo-space-2xl: 3rem;    /* 48px */

    /* 圓角 */
    --buygo-radius-sm: 0.375rem;  /* 6px */
    --buygo-radius-md: 0.5rem;    /* 8px */
    --buygo-radius-lg: 0.75rem;   /* 12px */
    --buygo-radius-xl: 1rem;      /* 16px */
    --buygo-radius-2xl: 1.5rem;   /* 24px */
    --buygo-radius-full: 9999px;

    /* 陰影 */
    --buygo-shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
    --buygo-shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
    --buygo-shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
    --buygo-shadow-xl: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1);

    /* 動畫時長 */
    --buygo-duration-fast: 150ms;
    --buygo-duration-normal: 300ms;
    --buygo-duration-slow: 500ms;

    /* 動畫緩動函數 */
    --buygo-ease-in: cubic-bezier(0.4, 0, 1, 1);
    --buygo-ease-out: cubic-bezier(0, 0, 0.2, 1);
    --buygo-ease-in-out: cubic-bezier(0.4, 0, 0.2, 1);

    /* Z-index 層級 */
    --buygo-z-dropdown: 1000;
    --buygo-z-sticky: 1020;
    --buygo-z-fixed: 1030;
    --buygo-z-modal-backdrop: 1040;
    --buygo-z-modal: 2000;
    --buygo-z-popover: 2010;
    --buygo-z-tooltip: 2020;
    --buygo-z-toast: 3000;

    /* 字體 */
    --buygo-font-sans: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    --buygo-font-mono: 'Fira Code', 'SF Mono', Monaco, monospace;
}
`;

    // 2. 動畫定義
    const animations = `
/* Fade In/Out */
@keyframes buygo-fade-in {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes buygo-fade-out {
    from { opacity: 1; }
    to { opacity: 0; }
}

/* Slide From Right */
@keyframes buygo-slide-in-right {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

@keyframes buygo-slide-out-right {
    from {
        transform: translateX(0);
        opacity: 1;
    }
    to {
        transform: translateX(100%);
        opacity: 0;
    }
}

/* Slide From Left */
@keyframes buygo-slide-in-left {
    from {
        transform: translateX(-100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

/* Slide From Bottom */
@keyframes buygo-slide-in-bottom {
    from {
        transform: translateY(20px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

/* Slide From Top */
@keyframes buygo-slide-in-top {
    from {
        transform: translateY(-20px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

/* Scale */
@keyframes buygo-scale-in {
    from {
        transform: scale(0.95);
        opacity: 0;
    }
    to {
        transform: scale(1);
        opacity: 1;
    }
}

@keyframes buygo-scale-out {
    from {
        transform: scale(1);
        opacity: 1;
    }
    to {
        transform: scale(0.95);
        opacity: 0;
    }
}

/* Spin */
@keyframes buygo-spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

/* Pulse */
@keyframes buygo-pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

/* Bounce */
@keyframes buygo-bounce {
    0%, 100% {
        transform: translateY(-5%);
        animation-timing-function: cubic-bezier(0.8, 0, 1, 1);
    }
    50% {
        transform: translateY(0);
        animation-timing-function: cubic-bezier(0, 0, 0.2, 1);
    }
}

/* Vue Transition Classes - Fade */
.buygo-fade-enter-active {
    animation: buygo-fade-in var(--buygo-duration-normal) var(--buygo-ease-out);
}

.buygo-fade-leave-active {
    animation: buygo-fade-out var(--buygo-duration-normal) var(--buygo-ease-in);
}

/* Vue Transition Classes - Slide */
.buygo-slide-enter-active {
    animation: buygo-slide-in-right var(--buygo-duration-normal) var(--buygo-ease-out);
}

.buygo-slide-leave-active {
    animation: buygo-slide-out-right var(--buygo-duration-normal) var(--buygo-ease-in);
}

/* Vue Transition Classes - Scale */
.buygo-scale-enter-active {
    animation: buygo-scale-in var(--buygo-duration-fast) var(--buygo-ease-out);
}

.buygo-scale-leave-active {
    animation: buygo-scale-out var(--buygo-duration-fast) var(--buygo-ease-in);
}

/* Vue Transition Classes - Slide Bottom */
.buygo-slide-bottom-enter-active {
    animation: buygo-slide-in-bottom var(--buygo-duration-normal) var(--buygo-ease-out);
}

.buygo-slide-bottom-leave-active {
    animation: buygo-fade-out var(--buygo-duration-fast) var(--buygo-ease-in);
}

/* Utility Animation Classes */
.buygo-animate-spin {
    animation: buygo-spin 1s linear infinite;
}

.buygo-animate-pulse {
    animation: buygo-pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

.buygo-animate-bounce {
    animation: buygo-bounce 1s infinite;
}
`;

    // 3. 通用 UI 組件樣式
    const componentStyles = `
/* ============================================
   按鈕樣式
   ============================================ */
.buygo-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: var(--buygo-space-xs);
    padding: var(--buygo-space-sm) var(--buygo-space-md);
    border-radius: var(--buygo-radius-md);
    font-weight: 500;
    font-size: 0.875rem;
    line-height: 1.25rem;
    transition: all var(--buygo-duration-fast) var(--buygo-ease-in-out);
    cursor: pointer;
    border: 1px solid transparent;
    text-decoration: none;
    white-space: nowrap;
}

.buygo-btn:focus {
    outline: none;
    box-shadow: 0 0 0 3px var(--buygo-primary-light);
}

.buygo-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* Primary Button */
.buygo-btn-primary {
    background-color: var(--buygo-primary);
    color: white;
    border-color: var(--buygo-primary);
}

.buygo-btn-primary:hover:not(:disabled) {
    background-color: var(--buygo-primary-hover);
    border-color: var(--buygo-primary-hover);
}

/* Secondary Button */
.buygo-btn-secondary {
    background-color: white;
    color: var(--buygo-gray-700);
    border-color: var(--buygo-gray-300);
}

.buygo-btn-secondary:hover:not(:disabled) {
    background-color: var(--buygo-gray-50);
    border-color: var(--buygo-gray-400);
}

/* Danger Button */
.buygo-btn-danger {
    background-color: var(--buygo-danger);
    color: white;
    border-color: var(--buygo-danger);
}

.buygo-btn-danger:hover:not(:disabled) {
    background-color: #dc2626;
    border-color: #dc2626;
}

/* Success Button */
.buygo-btn-success {
    background-color: var(--buygo-success);
    color: white;
    border-color: var(--buygo-success);
}

.buygo-btn-success:hover:not(:disabled) {
    background-color: #059669;
    border-color: #059669;
}

/* Accent/CTA Button */
.buygo-btn-accent {
    background-color: var(--buygo-accent);
    color: white;
    border-color: var(--buygo-accent);
    font-weight: 700;
    box-shadow: 0 2px 10px -3px rgba(249, 115, 22, 0.5);
}

.buygo-btn-accent:hover:not(:disabled) {
    background-color: var(--buygo-accent-hover);
    border-color: var(--buygo-accent-hover);
    transform: scale(1.02);
}

/* Ghost Button */
.buygo-btn-ghost {
    background-color: transparent;
    color: var(--buygo-gray-600);
    border-color: transparent;
}

.buygo-btn-ghost:hover:not(:disabled) {
    background-color: var(--buygo-gray-100);
    color: var(--buygo-gray-900);
}

/* Icon Button */
.buygo-btn-icon {
    padding: var(--buygo-space-sm);
    border-radius: var(--buygo-radius-lg);
}

/* Button Sizes */
.buygo-btn-sm {
    padding: var(--buygo-space-xs) var(--buygo-space-sm);
    font-size: 0.75rem;
    line-height: 1rem;
}

.buygo-btn-lg {
    padding: var(--buygo-space-md) var(--buygo-space-lg);
    font-size: 1rem;
    line-height: 1.5rem;
}

/* ============================================
   卡片樣式
   ============================================ */
.buygo-card {
    background: white;
    border-radius: var(--buygo-radius-xl);
    box-shadow: var(--buygo-shadow-sm);
    border: 1px solid var(--buygo-gray-200);
    padding: var(--buygo-space-lg);
}

.buygo-card-hover:hover {
    box-shadow: var(--buygo-shadow-md);
    border-color: var(--buygo-gray-300);
}

.buygo-card-header {
    padding-bottom: var(--buygo-space-md);
    margin-bottom: var(--buygo-space-md);
    border-bottom: 1px solid var(--buygo-gray-100);
}

.buygo-card-title {
    font-size: 1.125rem;
    font-weight: 700;
    color: var(--buygo-gray-900);
}

/* ============================================
   子頁面容器
   ============================================ */
.buygo-subpage {
    position: fixed;
    inset: 0;
    background: var(--buygo-gray-50);
    z-index: var(--buygo-z-modal);
    overflow-y: auto;
}

.buygo-subpage-content {
    max-width: 80rem;
    margin: 0 auto;
    padding: var(--buygo-space-xl);
}

.buygo-subpage-header {
    position: sticky;
    top: 0;
    z-index: 40;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(8px);
    border-bottom: 1px solid var(--buygo-gray-200);
    padding: var(--buygo-space-md) var(--buygo-space-lg);
    display: flex;
    align-items: center;
    justify-content: space-between;
    box-shadow: var(--buygo-shadow-sm);
}

/* ============================================
   返回按鈕
   ============================================ */
.buygo-back-btn {
    display: inline-flex;
    align-items: center;
    gap: var(--buygo-space-xs);
    color: var(--buygo-gray-600);
    text-decoration: none;
    font-weight: 500;
    font-size: 0.875rem;
    padding: var(--buygo-space-sm);
    margin-left: calc(var(--buygo-space-sm) * -1);
    border-radius: var(--buygo-radius-lg);
    transition: all var(--buygo-duration-fast);
    cursor: pointer;
    border: none;
    background: transparent;
}

.buygo-back-btn:hover {
    color: var(--buygo-gray-900);
    background-color: var(--buygo-gray-100);
}

.buygo-back-btn svg {
    transition: transform var(--buygo-duration-fast);
}

.buygo-back-btn:hover svg {
    transform: translateX(-4px);
}

/* ============================================
   表格樣式
   ============================================ */
.buygo-table {
    width: 100%;
    border-collapse: collapse;
}

.buygo-table th {
    padding: var(--buygo-space-md) var(--buygo-space-md);
    text-align: left;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--buygo-gray-500);
    background-color: var(--buygo-gray-50);
    border-bottom: 1px solid var(--buygo-gray-200);
}

.buygo-table td {
    padding: var(--buygo-space-md) var(--buygo-space-md);
    border-bottom: 1px solid var(--buygo-gray-100);
    color: var(--buygo-gray-700);
    font-size: 0.875rem;
}

.buygo-table tbody tr:hover {
    background-color: var(--buygo-gray-50);
}

/* ============================================
   狀態標籤
   ============================================ */
.buygo-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.125rem 0.625rem;
    font-size: 0.75rem;
    font-weight: 500;
    border-radius: var(--buygo-radius-full);
    border: 1px solid transparent;
}

.buygo-badge-success {
    background-color: var(--buygo-success-light);
    color: #065f46;
    border-color: #a7f3d0;
}

.buygo-badge-warning {
    background-color: var(--buygo-warning-light);
    color: #92400e;
    border-color: #fde68a;
}

.buygo-badge-danger {
    background-color: var(--buygo-danger-light);
    color: #991b1b;
    border-color: #fecaca;
}

.buygo-badge-info {
    background-color: var(--buygo-info-light);
    color: #155e75;
    border-color: #a5f3fc;
}

.buygo-badge-neutral {
    background-color: var(--buygo-gray-100);
    color: var(--buygo-gray-700);
    border-color: var(--buygo-gray-200);
}

/* ============================================
   輸入框樣式
   ============================================ */
.buygo-input {
    width: 100%;
    padding: var(--buygo-space-sm) var(--buygo-space-md);
    border: 1px solid var(--buygo-gray-300);
    border-radius: var(--buygo-radius-md);
    font-size: 0.875rem;
    color: var(--buygo-gray-900);
    background-color: white;
    transition: border-color var(--buygo-duration-fast), box-shadow var(--buygo-duration-fast);
}

.buygo-input:focus {
    outline: none;
    border-color: var(--buygo-primary);
    box-shadow: 0 0 0 3px var(--buygo-primary-light);
}

.buygo-input:disabled {
    background-color: var(--buygo-gray-100);
    cursor: not-allowed;
}

.buygo-input::placeholder {
    color: var(--buygo-gray-400);
}

/* ============================================
   Toast 通知
   ============================================ */
.buygo-toast {
    position: fixed;
    top: var(--buygo-space-md);
    right: var(--buygo-space-md);
    z-index: var(--buygo-z-toast);
    padding: var(--buygo-space-md) var(--buygo-space-lg);
    border-radius: var(--buygo-radius-lg);
    box-shadow: var(--buygo-shadow-lg);
    display: flex;
    align-items: center;
    gap: var(--buygo-space-sm);
    font-weight: 500;
    animation: buygo-slide-in-top var(--buygo-duration-normal) var(--buygo-ease-out);
}

.buygo-toast-success {
    background-color: var(--buygo-success);
    color: white;
}

.buygo-toast-error {
    background-color: var(--buygo-danger);
    color: white;
}

.buygo-toast-info {
    background-color: var(--buygo-primary);
    color: white;
}

.buygo-toast-warning {
    background-color: var(--buygo-warning);
    color: white;
}

/* ============================================
   載入狀態
   ============================================ */
.buygo-loading {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: var(--buygo-space-2xl);
    color: var(--buygo-gray-500);
}

.buygo-loading-spinner {
    width: 2rem;
    height: 2rem;
    border: 2px solid var(--buygo-gray-200);
    border-top-color: var(--buygo-primary);
    border-radius: 50%;
    animation: buygo-spin 0.8s linear infinite;
}

/* ============================================
   Modal 遮罩
   ============================================ */
.buygo-modal-backdrop {
    position: fixed;
    inset: 0;
    background-color: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(4px);
    z-index: var(--buygo-z-modal-backdrop);
}

.buygo-modal {
    position: fixed;
    inset: 0;
    z-index: var(--buygo-z-modal);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: var(--buygo-space-md);
}

.buygo-modal-content {
    background: white;
    border-radius: var(--buygo-radius-2xl);
    box-shadow: var(--buygo-shadow-xl);
    max-width: 32rem;
    width: 100%;
    max-height: 90vh;
    overflow-y: auto;
}

/* ============================================
   空狀態
   ============================================ */
.buygo-empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: var(--buygo-space-2xl);
    text-align: center;
    color: var(--buygo-gray-500);
}

.buygo-empty-state svg {
    width: 3rem;
    height: 3rem;
    margin-bottom: var(--buygo-space-md);
    color: var(--buygo-gray-300);
}

/* ============================================
   分隔線
   ============================================ */
.buygo-divider {
    height: 1px;
    background-color: var(--buygo-gray-200);
    margin: var(--buygo-space-lg) 0;
}

.buygo-divider-vertical {
    width: 1px;
    height: 1.25rem;
    background-color: var(--buygo-gray-200);
}
`;

    // 4. 插入樣式到頁面
    function injectStyles() {
        const styleId = 'buygo-design-system';

        // 避免重複插入
        if (document.getElementById(styleId)) {
            return;
        }

        const style = document.createElement('style');
        style.id = styleId;
        style.textContent = cssVariables + animations + componentStyles;
        document.head.appendChild(style);

        console.log('[BuyGo Design System] Styles injected successfully');
    }

    // 5. 取得設計 token
    function getToken(tokenName) {
        const root = document.documentElement;
        return getComputedStyle(root).getPropertyValue('--buygo-' + tokenName).trim();
    }

    // 6. 顯示 Toast 通知
    function showToast(message, type = 'success', duration = 3000) {
        // 移除現有的 toast
        const existingToast = document.querySelector('.buygo-toast');
        if (existingToast) {
            existingToast.remove();
        }

        // 建立新的 toast
        const toast = document.createElement('div');
        toast.className = `buygo-toast buygo-toast-${type}`;
        toast.textContent = message;
        document.body.appendChild(toast);

        // 自動移除
        setTimeout(() => {
            toast.style.animation = 'buygo-fade-out var(--buygo-duration-normal) var(--buygo-ease-in) forwards';
            setTimeout(() => toast.remove(), 300);
        }, duration);

        return toast;
    }

    // 7. 確認對話框
    function showConfirm(title, message, onConfirm, options = {}) {
        const {
            confirmText = '確認',
            cancelText = '取消',
            confirmClass = 'buygo-btn-danger'
        } = options;

        // 建立 Modal
        const backdrop = document.createElement('div');
        backdrop.className = 'buygo-modal-backdrop';

        const modal = document.createElement('div');
        modal.className = 'buygo-modal';
        modal.innerHTML = `
            <div class="buygo-modal-content" style="padding: var(--buygo-space-lg);">
                <h3 style="font-size: 1.125rem; font-weight: 600; color: var(--buygo-gray-900); margin-bottom: var(--buygo-space-md);">${title}</h3>
                <p style="color: var(--buygo-gray-600); margin-bottom: var(--buygo-space-lg);">${message}</p>
                <div style="display: flex; justify-content: flex-end; gap: var(--buygo-space-sm);">
                    <button class="buygo-btn buygo-btn-secondary" data-action="cancel">${cancelText}</button>
                    <button class="buygo-btn ${confirmClass}" data-action="confirm">${confirmText}</button>
                </div>
            </div>
        `;

        document.body.appendChild(backdrop);
        document.body.appendChild(modal);

        // 事件處理
        const cleanup = () => {
            backdrop.remove();
            modal.remove();
        };

        modal.querySelector('[data-action="cancel"]').addEventListener('click', cleanup);
        modal.querySelector('[data-action="confirm"]').addEventListener('click', () => {
            cleanup();
            if (onConfirm) onConfirm();
        });
        backdrop.addEventListener('click', cleanup);
    }

    // 導出到全域
    window.BuyGoDesignSystem = {
        injectStyles,
        getToken,
        showToast,
        showConfirm,
        // 公開樣式字串（供需要時使用）
        styles: {
            cssVariables,
            animations,
            componentStyles
        }
    };

    // 自動執行樣式注入
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', injectStyles);
    } else {
        injectStyles();
    }

    // 如果需要支援 ES6 模組
    if (typeof module !== 'undefined' && module.exports) {
        module.exports = window.BuyGoDesignSystem;
    }

})();
;
/* === admin/js/BuyGoCache.js === */
/**
 * BuyGo Frontend Cache v2
 *
 * 三層快取架構：
 * 1. 記憶體（RAM）— 頁面切換瞬間回應（0.01s）
 * 2. sessionStorage（磁碟）— 重新整理頁面時回應（0.1s）
 * 3. API fallback — 都沒有時打 API
 *
 * SWR 策略（Stale-While-Revalidate）：
 * - get() 立刻回傳快取資料（即使過期）
 * - 呼叫端自行決定是否背景更新
 * - isFresh() 判斷資料是否在 TTL 內
 *
 * 預載機制：
 * - preload() 登入後背景靜默抓取常用頁面資料
 */
window.BuyGoCache = {
    TTL: 30 * 1000,  // 30 秒視為「新鮮」— 切回頁面時更快看到最新資料

    // 記憶體快取（最快，分頁切換不消失，關瀏覽器才清）
    _mem: {},

    /**
     * 取得快取資料
     * 優先順序：記憶體 → sessionStorage
     * 回傳 null 表示完全沒有快取
     */
    get: function(key) {
        // Layer 1: 記憶體
        var memEntry = this._mem[key];
        if (memEntry) {
            return memEntry.data;
        }

        // Layer 2: sessionStorage
        try {
            var raw = sessionStorage.getItem('buygo_cache_' + key);
            if (!raw) return null;
            var parsed = JSON.parse(raw);
            // 寫回記憶體（下次更快）
            this._mem[key] = { data: parsed.data, ts: parsed.ts };
            return parsed.data;
        } catch (e) { return null; }
    },

    /**
     * 判斷快取是否在 TTL 內（新鮮）
     * true = 不需要背景更新
     * false = 資料過期，建議背景更新
     * null = 完全沒有快取
     */
    isFresh: function(key) {
        var memEntry = this._mem[key];
        if (memEntry) {
            return (Date.now() - memEntry.ts) < this.TTL;
        }

        try {
            var raw = sessionStorage.getItem('buygo_cache_' + key);
            if (!raw) return null;
            var parsed = JSON.parse(raw);
            return (Date.now() - parsed.ts) < this.TTL;
        } catch (e) { return null; }
    },

    /**
     * 寫入快取（同時寫入記憶體和 sessionStorage）
     */
    set: function(key, data) {
        var ts = Date.now();
        // 記憶體
        this._mem[key] = { data: data, ts: ts };
        // sessionStorage
        try {
            sessionStorage.setItem('buygo_cache_' + key, JSON.stringify({ data: data, ts: ts }));
        } catch (e) { /* sessionStorage 滿了就忽略，記憶體層仍有效 */ }
    },

    /**
     * 清除快取
     * @param {string} key - 指定 key，不傳則清除全部
     */
    clear: function(key) {
        if (key) {
            delete this._mem[key];
            sessionStorage.removeItem('buygo_cache_' + key);
        } else {
            this._mem = {};
            var keys = Object.keys(sessionStorage).filter(function(k) { return k.startsWith('buygo_cache_'); });
            keys.forEach(function(k) { sessionStorage.removeItem(k); });
        }
    },

    /**
     * 頁面→端點對應表
     * SPA 導航時根據頁面名稱取得對應的 API 端點
     * preload() 和 preloadPage() 共用此對應表
     */
    _pageEndpoints: {
        'orders': [
            { key: 'orders', url: '/wp-json/buygo-plus-one/v1/orders?page=1&per_page=30' }
        ],
        'products': [
            { key: 'products', url: '/wp-json/buygo-plus-one/v1/products' }
        ],
        'dashboard': [
            { key: 'dashboard-stats', url: '/wp-json/buygo-plus-one/v1/dashboard/stats' },
            { key: 'dashboard-revenue', url: '/wp-json/buygo-plus-one/v1/dashboard/revenue?period=30' },
            { key: 'dashboard-products', url: '/wp-json/buygo-plus-one/v1/dashboard/products' },
            { key: 'dashboard-activities', url: '/wp-json/buygo-plus-one/v1/dashboard/activities?limit=10' }
        ],
        'customers': [
            { key: 'customers', url: '/wp-json/buygo-plus-one/v1/customers?page=1&per_page=5' }
        ],
        'shipment-products': [
            { key: 'shipment-products', url: '/wp-json/buygo-plus-one/v1/shipments?per_page=-1' }
        ],
        'shipment-details': [
            { key: 'shipment-details', url: '/wp-json/buygo-plus-one/v1/shipments?per_page=-1' }
        ],
        'settings': [
            { key: 'settings-templates', url: '/wp-json/buygo-plus-one/v1/settings/templates' },
            { key: 'settings-helpers', url: '/wp-json/buygo-plus-one/v1/settings/helpers' }
        ],
        'search': []
    },

    /**
     * 預載常用頁面資料
     * 登入後呼叫，背景靜默抓取，不阻塞畫面
     * @param {string} wpNonce - WordPress REST API nonce
     */
    preload: function(wpNonce) {
        var self = this;

        // 從 _pageEndpoints 扁平化生成所有端點
        var endpoints = [];
        var pageKeys = Object.keys(this._pageEndpoints);
        for (var i = 0; i < pageKeys.length; i++) {
            var pageEps = this._pageEndpoints[pageKeys[i]];
            for (var j = 0; j < pageEps.length; j++) {
                // 避免重複（shipment-products 和 shipment-details 共用同 key）
                var exists = false;
                for (var k = 0; k < endpoints.length; k++) {
                    if (endpoints[k].key === pageEps[j].key) {
                        exists = true;
                        break;
                    }
                }
                if (!exists) {
                    endpoints.push(pageEps[j]);
                }
            }
        }

        // 延遲 500ms 後開始（優化：縮短等待時間，讓快取更快就緒）
        setTimeout(function() {
            endpoints.forEach(function(ep) {
                // 如果記憶體已有新鮮資料，跳過
                if (self.isFresh(ep.key)) return;

                fetch(ep.url, {
                    credentials: 'include',
                    headers: {
                        'X-WP-Nonce': wpNonce,
                        'X-BuyGo-Preload': '1'  // 標記為預載請求（方便 debug）
                    }
                })
                .then(function(res) { return res.json(); })
                .then(function(data) {
                    if (data && (data.success || data.data)) {
                        self.set(ep.key, data);
                    }
                })
                .catch(function() { /* 預載失敗靜默忽略 */ });
            });
        }, 500);
    },

    /**
     * 預載指定頁面的資料
     * SPA 導航時呼叫，提前載入下一頁資料
     * @param {string} pageKey - 頁面名稱（對應 _pageEndpoints 的 key）
     * @param {string} wpNonce - WordPress REST API nonce
     */
    preloadPage: function(pageKey, wpNonce) {
        var self = this;
        var pageEps = this._pageEndpoints[pageKey];

        // 無對應端點或空陣列，不做任何事
        if (!pageEps || pageEps.length === 0) return;

        pageEps.forEach(function(ep) {
            // 如果已有新鮮資料，跳過
            if (self.isFresh(ep.key)) return;

            fetch(ep.url, {
                credentials: 'include',
                headers: {
                    'X-WP-Nonce': wpNonce,
                    'X-BuyGo-Preload': '1'
                }
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data && (data.success || data.data)) {
                    self.set(ep.key, data);
                }
            })
            .catch(function() { /* 預載失敗靜默忽略 */ });
        });
    }
};
;
/* === includes/views/composables/useRouter.js === */
/**
 * BuyGo SPA Router Extension
 *
 * 擴展 RouterMixin.js 已建立的 window.BuyGoRouter，
 * 新增 SPA 頁面級路由（History API + 連結攔截）。
 * 保留原有的 checkUrlParams / navigateTo / setupPopstateListener 等子頁面路由方法。
 */
(function() {
    'use strict';

    // 確保 BuyGoRouter 已存在（由 RouterMixin.js 建立）
    if (!window.BuyGoRouter) {
        window.BuyGoRouter = {};
    }

    // 擴展 SPA 頁面路由功能
    Object.assign(window.BuyGoRouter, {
        // 路由表：path → component name
        routes: {
            'dashboard':        'DashboardPageComponent',
            'products':         'ProductsPageComponent',
            'orders':           'OrdersPageComponent',
            'shipment-products':'ShipmentProductsPageComponent',
            'shipment-details': 'ShipmentDetailsPageComponent',
            'customers':        'CustomersPageComponent',
            'settings':         'SettingsPageComponent',
            'search':           'SearchPageComponent',
            'batch-create':     'BatchCreatePageComponent'
        },

        // 頁面→權限對應（與 PHP 端一致）
        permissions: {
            'products':          'products',
            'orders':            'orders',
            'shipment-products': 'shipments',
            'shipment-details':  'shipments',
            'customers':         'customers',
            'settings':          'settings',
            'batch-create':      'products'
        },

        // 防重複初始化 flag
        _spaInitialized: false,

        // 頁面切換回調（可被 initSPA 更新）
        _onPageChange: null,

        /**
         * 從 URL 解析當前頁面名稱
         * /buygo-portal/orders/ → 'orders'
         * /buygo-portal/ → 'dashboard'
         */
        parsePath: function() {
            var path = window.location.pathname;
            var match = path.match(/\/buygo-portal\/([a-z-]+)/);
            if (match && this.routes[match[1]]) {
                return match[1];
            }
            return 'dashboard';
        },

        /**
         * SPA 頁面導航（不重新載入）
         * 注意：這是頁面級導航，不覆蓋 RouterMixin 的 navigateTo（子頁面級）
         */
        spaNavigate: function(page) {
            if (!this.routes[page]) return;
            var url = '/buygo-portal/' + page + '/';
            history.pushState({ page: page }, '', url);

            // SPA 導航時觸發預載下一頁資料
            if (window.BuyGoCache && window.BuyGoCache.preloadPage) {
                window.BuyGoCache.preloadPage(page, window.buygoWpNonce);
            }

            window.dispatchEvent(new CustomEvent('buygo-navigate', { detail: { page: page } }));
        },

        /**
         * 初始化 SPA 路由監聽
         * 支援防重複初始化：第二次呼叫只更新 onChange 回調，不重複綁定事件
         * @param {Function} onChange - 頁面切換回調 (pageName) => void
         */
        initSPA: function(onChange) {
            var self = this;

            // 防重複初始化：如果已初始化，只更新回調
            if (this._spaInitialized) {
                this._onPageChange = onChange;
                return this.parsePath();
            }
            this._spaInitialized = true;
            this._onPageChange = onChange;

            // 監聽瀏覽器前進/後退（頁面級）
            window.addEventListener('popstate', function() {
                var page = self.parsePath();

                // 觸發預載（瀏覽器前進/後退也需要）
                if (window.BuyGoCache && window.BuyGoCache.preloadPage) {
                    window.BuyGoCache.preloadPage(page, window.buygoWpNonce);
                }

                if (self._onPageChange) {
                    self._onPageChange(page);
                }
            });

            // 監聽自訂導航事件
            window.addEventListener('buygo-navigate', function(e) {
                if (self._onPageChange) {
                    self._onPageChange(e.detail.page);
                }
            });

            // 攔截側邊選單的連結點擊
            document.addEventListener('click', function(e) {
                var link = e.target.closest('a[href*="/buygo-portal/"]');
                if (!link) return;

                var href = link.getAttribute('href');
                // 排除外部連結和非 portal 連結
                if (!href || href.startsWith('http') && !href.includes(window.location.host)) return;

                var match = href.match(/\/buygo-portal\/([a-z-]+)/);
                if (match && self.routes[match[1]]) {
                    e.preventDefault();
                    self.spaNavigate(match[1]);
                }
            });

            return self.parsePath();
        }
    });
})();
;
/* === components/shared/header-component.js === */
/**
 * BuyGo Plus One - 獨立 Header Vue 元件
 *
 * 用途：提供完整的 Header 功能，包含搜尋、通知、幣別切換
 * 特色：自給自足，不需要頁面提供 data 或 methods
 *
 * 使用方式：
 * 1. 在 template.php 註冊：app.component('page-header-component', PageHeaderComponent);
 * 2. 在頁面使用：<page-header-component :title="..." :breadcrumb="..." :show-currency-toggle="..." />
 *
 * @version 2.0.0
 * @author BuyGo Team
 */

(function() {
    'use strict';

    window.PageHeaderComponent = {
        props: {
            title: {
                type: String,
                default: '頁面'
            },
            breadcrumb: {
                type: String,
                default: '<a href="/buygo-portal/dashboard" class="active">首頁</a>'
            },
            showCurrencyToggle: {
                type: Boolean,
                default: true  // 預設顯示幣別切換（所有頁面都可能需要）
            }
        },

        setup() {
            const { ref, computed, watch, onMounted } = Vue;

            // 使用 useCurrency Composable
            const {
                systemCurrency,
                setSystemCurrency,
                getCurrencySymbol
            } = useCurrency();

            // 全域搜尋
            const globalSearchQuery = ref('');
            const showMobileSearch = ref(false);
            const searchSuggestions = ref([]);
            const showSuggestions = ref(false);
            const isLoadingSuggestions = ref(false);
            const searchHistory = ref([]);
            const selectedSuggestionIndex = ref(-1);

            // 通知
            const unreadCount = ref(0);

            // 幣別選項（顯示完整名稱避免混淆）
            const currencyOptions = ref([
                { code: 'TWD', label: '台幣 (NT$)', symbol: 'NT$' },
                { code: 'JPY', label: '日幣 (¥)', symbol: '¥' },
                { code: 'USD', label: '美金 ($)', symbol: '$' },
                { code: 'CNY', label: '人民幣 (¥)', symbol: '¥' }
            ]);

            // 類型 icon 對照表
            const typeIcons = {
                order: '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>',
                product: '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>',
                customer: '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>',
                shipment: '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path></svg>'
            };

            // 從 localStorage 載入搜尋歷史並註冊事件監聽
            onMounted(() => {
                try {
                    const saved = localStorage.getItem('buygo_search_history');
                    if (saved) {
                        searchHistory.value = JSON.parse(saved);
                    }
                } catch (e) {
                    console.error('[PageHeader] 載入搜尋歷史失敗:', e);
                }

                // 註冊點擊外部關閉建議框的事件
                document.addEventListener('click', handleClickOutside);
            });

            // 清理事件監聽器
            const { onUnmounted } = Vue;
            onUnmounted(() => {
                document.removeEventListener('click', handleClickOutside);
            });

            // 點擊外部關閉建議框
            const handleClickOutside = (event) => {
                const searchContainer = document.querySelector('.global-search');
                if (searchContainer && !searchContainer.contains(event.target)) {
                    showSuggestions.value = false;
                    selectedSuggestionIndex.value = -1;
                }
            };

            // 儲存搜尋歷史到 localStorage
            const saveSearchHistory = () => {
                try {
                    localStorage.setItem('buygo_search_history', JSON.stringify(searchHistory.value));
                } catch (e) {
                    console.error('[PageHeader] 儲存搜尋歷史失敗:', e);
                }
            };

            return {
                // 狀態
                globalSearchQuery,
                showMobileSearch,
                searchSuggestions,
                showSuggestions,
                isLoadingSuggestions,
                searchHistory,
                selectedSuggestionIndex,
                unreadCount,
                systemCurrency,
                currencyOptions,
                typeIcons,

                // 方法
                setSystemCurrency,
                getCurrencySymbol,
                saveSearchHistory
            };
        },

        methods: {
            /**
             * 處理全域搜尋輸入（即時搜尋/建議）
             * 使用 debounce 避免過度請求
             */
            handleGlobalSearch() {
                const query = this.globalSearchQuery.trim();

                // 清空時隱藏建議框
                if (!query) {
                    this.showSuggestions = false;
                    this.searchSuggestions = [];
                    return;
                }

                // Debounce: 取消之前的計時器
                if (this.searchDebounceTimer) {
                    clearTimeout(this.searchDebounceTimer);
                }

                // 300ms 後才執行搜尋
                this.searchDebounceTimer = setTimeout(() => {
                    this.fetchSearchSuggestions(query);
                }, 300);
            },

            /**
             * 取得搜尋建議
             */
            async fetchSearchSuggestions(query) {
                this.isLoadingSuggestions = true;

                try {
                    const response = await fetch(
                        `/wp-json/buygo-plus-one/v1/global-search?query=${encodeURIComponent(query)}&per_page=10`,
                        {
                            headers: {
                                'X-WP-Nonce': window.buygoWpNonce
                            }
                        }
                    );

                    if (!response.ok) {
                        throw new Error('搜尋建議請求失敗');
                    }

                    const result = await response.json();

                    if (result.success && result.data) {
                        this.searchSuggestions = result.data;
                        this.showSuggestions = true;
                        this.selectedSuggestionIndex = -1;
                    }

                } catch (error) {
                    console.error('[PageHeader] 取得搜尋建議失敗:', error);
                } finally {
                    this.isLoadingSuggestions = false;
                }
            },

            /**
             * Enter 鍵跳轉搜尋結果頁面
             */
            goToSearchPage() {
                const query = this.globalSearchQuery.trim();
                if (query) {
                    // 加入搜尋歷史
                    this.addToSearchHistory(query);

                    const encodedQuery = encodeURIComponent(query);
                    window.location.href = `/buygo-portal/search?q=${encodedQuery}`;
                }
            },

            /**
             * 選擇建議項目
             */
            selectSuggestion(suggestion) {
                // 加入搜尋歷史
                this.addToSearchHistory(this.globalSearchQuery.trim());

                // 根據類型導航到對應頁面
                this.navigateToDetail(suggestion);
            },

            /**
             * 根據項目類型導航到詳情頁
             */
            navigateToDetail(item) {
                let url = '';

                switch (item.type) {
                    case 'order':
                        url = `/buygo-portal/orders?id=${item.id}`;
                        break;
                    case 'product':
                        url = `/buygo-portal/products?id=${item.id}`;
                        break;
                    case 'customer':
                        url = `/buygo-portal/customers?id=${item.id}`;
                        break;
                    case 'shipment':
                        url = `/buygo-portal/shipment-details?id=${item.id}`;
                        break;
                    default:
                        // 預設跳轉搜尋頁
                        url = `/buygo-portal/search?q=${encodeURIComponent(this.globalSearchQuery)}`;
                }

                window.location.href = url;
            },

            /**
             * 加入搜尋歷史
             */
            addToSearchHistory(query) {
                if (!query) return;

                // 移除重複項目
                const index = this.searchHistory.indexOf(query);
                if (index > -1) {
                    this.searchHistory.splice(index, 1);
                }

                // 加到最前面
                this.searchHistory.unshift(query);

                // 只保留最近 10 筆
                if (this.searchHistory.length > 10) {
                    this.searchHistory = this.searchHistory.slice(0, 10);
                }

                // 儲存到 localStorage
                this.saveSearchHistory();
            },

            /**
             * 點擊搜尋歷史項目
             */
            selectHistoryItem(query) {
                this.globalSearchQuery = query;
                this.goToSearchPage();
            },

            /**
             * 處理搜尋框 focus 事件
             */
            handleGlobalSearchFocus() {
                // 如果有搜尋文字或有搜尋歷史,顯示建議框
                if (this.globalSearchQuery || this.searchHistory.length > 0) {
                    this.showSuggestions = true;
                }
            },

            /**
             * 鍵盤導航（上下鍵選擇建議）
             */
            handleKeydown(event) {
                // Enter 鍵和 Escape 鍵在任何情況下都要處理
                if (event.key === 'Enter') {
                    event.preventDefault();
                    if (this.showSuggestions && this.selectedSuggestionIndex >= 0 && this.searchSuggestions.length > 0) {
                        this.selectSuggestion(this.searchSuggestions[this.selectedSuggestionIndex]);
                    } else {
                        this.goToSearchPage();
                    }
                    return;
                }

                if (event.key === 'Escape') {
                    this.showSuggestions = false;
                    this.selectedSuggestionIndex = -1;
                    return;
                }

                // 上下鍵導航只在有建議時才處理
                if (!this.showSuggestions || this.searchSuggestions.length === 0) {
                    return;
                }

                switch (event.key) {
                    case 'ArrowDown':
                        event.preventDefault();
                        this.selectedSuggestionIndex =
                            (this.selectedSuggestionIndex + 1) % this.searchSuggestions.length;
                        break;
                    case 'ArrowUp':
                        event.preventDefault();
                        this.selectedSuggestionIndex =
                            this.selectedSuggestionIndex <= 0
                                ? this.searchSuggestions.length - 1
                                : this.selectedSuggestionIndex - 1;
                        break;
                }
            },

            /**
             * 取得類型 icon HTML
             */
            getTypeIcon(type) {
                return this.typeIcons[type] || this.typeIcons.order;
            },

            /**
             * 切換手機版搜尋面板
             */
            toggleMobileSearch() {
                this.showMobileSearch = !this.showMobileSearch;
                console.log('[PageHeader] 手機版搜尋:', this.showMobileSearch);
                // TODO: 顯示全螢幕搜尋面板
            },

            /**
             * 切換通知面板
             */
            toggleNotifications() {
                console.log('[PageHeader] 切換通知面板');
                // TODO: 顯示通知下拉面板
                // TODO: 標記通知為已讀
            },

            /**
             * 處理幣別切換
             */
            handleCurrencyChange(event) {
                const newCurrency = event.target.value;

                // 更新全域幣別設定
                this.setSystemCurrency(newCurrency);

                console.log('[PageHeader] 切換幣別:', newCurrency);

                // 觸發事件讓父元件知道幣別改變了
                this.$emit('currency-changed', newCurrency);
            }
        },

        template: `
<!-- ============================================ -->
<!-- 頁首部分 (獨立 Header 元件) -->
<!-- ============================================ -->
<header class="page-header">
    <div class="flex items-center gap-3 md:gap-4 overflow-hidden flex-1">
        <div class="flex flex-col overflow-hidden min-w-0 pl-12 md:pl-0">
            <h1 class="page-header-title">{{ title }}</h1>
            <nav class="page-header-breadcrumb" v-html="breadcrumb"></nav>
        </div>
    </div>

    <!-- 右側操作區 -->
    <div class="flex items-center gap-2 md:gap-3 shrink-0">
        <!-- 手機版搜尋 icon (640px以下顯示) -->
        <button class="notification-bell sm:hidden" @click="toggleMobileSearch" title="搜尋">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
            </svg>
        </button>

        <!-- 桌面版全域搜尋框 (640px以上顯示) -->
        <div class="global-search" ref="searchContainer">
            <input type="text"
                   placeholder="搜尋訂單、商品、客戶、出貨單..."
                   v-model="globalSearchQuery"
                   @input="handleGlobalSearch"
                   @keydown="handleKeydown"
                   @focus="handleGlobalSearchFocus">
            <svg class="search-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
            </svg>

            <!-- 搜尋建議下拉框 -->
            <div v-if="showSuggestions" class="search-suggestions">
                <!-- 載入中 -->
                <div v-if="isLoadingSuggestions" class="suggestion-item loading">
                    <span class="loading-spinner"></span>
                    <span>搜尋中...</span>
                </div>

                <!-- 搜尋結果建議 -->
                <template v-else-if="searchSuggestions.length > 0">
                    <div class="suggestions-header">搜尋結果</div>
                    <div v-for="(suggestion, index) in searchSuggestions"
                         :key="suggestion.id + '-' + suggestion.type"
                         :class="['suggestion-item', { 'selected': index === selectedSuggestionIndex }]"
                         @click="selectSuggestion(suggestion)"
                         @mouseenter="selectedSuggestionIndex = index">
                        <span class="suggestion-icon" v-html="getTypeIcon(suggestion.type)"></span>
                        <div class="suggestion-content">
                            <div class="suggestion-title" v-html="suggestion.title"></div>
                            <div class="suggestion-meta">{{ suggestion.meta }}</div>
                        </div>
                        <span class="suggestion-type">{{ suggestion.type_label }}</span>
                    </div>
                </template>

                <!-- 搜尋歷史 -->
                <template v-else-if="!globalSearchQuery && searchHistory.length > 0">
                    <div class="suggestions-header">最近搜尋</div>
                    <div v-for="(historyItem, index) in searchHistory"
                         :key="'history-' + index"
                         class="suggestion-item history-item"
                         @click="selectHistoryItem(historyItem)">
                        <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <span class="history-text">{{ historyItem }}</span>
                    </div>
                </template>

                <!-- 無結果 -->
                <div v-else-if="globalSearchQuery && searchSuggestions.length === 0" class="suggestion-item no-results">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span>找不到相關結果</span>
                </div>
            </div>
        </div>

        <!-- 幣別切換下拉選單 -->
        <select v-if="showCurrencyToggle"
                class="currency-select"
                v-model="systemCurrency"
                @change="handleCurrencyChange"
                title="選擇幣別">
            <option v-for="option in currencyOptions"
                    :key="option.code"
                    :value="option.code">
                {{ option.label }}
            </option>
        </select>

        <!-- 通知鈴鐺 -->
        <button class="notification-bell" @click="toggleNotifications" title="通知">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
            </svg>
            <span v-if="unreadCount > 0" class="notification-badge">{{ unreadCount }}</span>
        </button>
    </div>
</header>
<!-- 結束:頁首部分 -->
        `
    };

    console.log('[PageHeaderComponent] 已載入獨立 Header Vue 元件');
})();
;
/* === includes/views/composables/useCurrency.js === */
/**
 * Currency Composable - 全站幣別處理統一邏輯
 *
 * 功能:
 * - 統一的幣別格式化
 * - 匯率轉換
 * - 幣別符號管理
 *
 * 使用方式:
 * const { formatPrice, convertCurrency, getCurrencySymbol } = useCurrency();
 * formatPrice(1000, 'JPY'); // "¥1,000"
 *
 * @version 1.0.0
 * @date 2026-01-20
 */

// 注意: 這是一個全局函數,不使用 ES6 import/export
// 因為 WordPress 環境中 ES6 模組可能不被支持

// 全域共享的幣別狀態（單例模式）
// 這樣所有使用 useCurrency 的元件都會共享同一個 ref
let _globalSystemCurrency = null;
let _globalExchangeRates = null;

function useCurrency() {
    const { ref, computed } = Vue;

    // 初始化全域幣別（只在第一次調用時創建）
    if (!_globalSystemCurrency) {
        _globalSystemCurrency = ref(window.buygoSettings?.currency || 'JPY');
        console.log('[useCurrency] 初始化全域幣別:', _globalSystemCurrency.value);
    }

    const systemCurrency = _globalSystemCurrency;

    // 幣別符號對照表
    const currencySymbols = {
        'JPY': '¥',
        'TWD': 'NT$',
        'USD': '$',
        'THB': '฿',
        'CNY': '¥',
        'EUR': '€',
        'GBP': '£'
    };

    // 匯率對照表 (基準: JPY = 1)
    // 注意: 實際應用中應該從 API 或設定檔讀取即時匯率
    if (!_globalExchangeRates) {
        _globalExchangeRates = ref({
            'JPY': 1,
            'TWD': 0.23,    // 1 JPY = 0.23 TWD
            'USD': 0.0067,  // 1 JPY = 0.0067 USD
            'THB': 0.24,    // 1 JPY = 0.24 THB
            'CNY': 0.048,   // 1 JPY = 0.048 CNY
            'EUR': 0.0062,  // 1 JPY = 0.0062 EUR
            'GBP': 0.0053   // 1 JPY = 0.0053 GBP
        });
    }

    const exchangeRates = _globalExchangeRates;

    /**
     * 取得幣別符號
     * @param {string} currency - 幣別代碼 (如: JPY, TWD)
     * @returns {string} 幣別符號
     */
    const getCurrencySymbol = (currency) => {
        return currencySymbols[currency] || '¥';
    };

    /**
     * 格式化價格 (不做匯率轉換)
     * @param {number|string} price - 價格
     * @param {string|null} currency - 幣別代碼，null 時使用系統預設
     * @returns {string} 格式化後的價格字串，如: "¥1,000"
     */
    const formatPrice = (price, currency = null) => {
        // 防護: 確保 price 是數字
        const safePrice = price ?? 0;
        const numPrice = typeof safePrice === 'string' ? parseFloat(safePrice) : safePrice;

        // 使用指定幣別或系統預設幣別
        const curr = currency || systemCurrency.value;

        // 取得幣別符號
        const symbol = getCurrencySymbol(curr);

        // 四捨五入到整數
        const roundedPrice = Math.round(numPrice);

        // 格式化數字 (加上千分位逗號)
        const formattedPrice = roundedPrice.toLocaleString('zh-TW');

        return `${symbol}${formattedPrice}`;
    };

    /**
     * 匯率轉換
     * @param {number} amount - 金額
     * @param {string} fromCurrency - 來源幣別
     * @param {string} toCurrency - 目標幣別
     * @returns {number} 轉換後的金額
     */
    const convertCurrency = (amount, fromCurrency, toCurrency) => {
        if (fromCurrency === toCurrency) {
            return amount;
        }

        // 先轉換為 JPY (基準幣別)
        const amountInJPY = amount / (exchangeRates.value[fromCurrency] || 1);

        // 再轉換為目標幣別
        const convertedAmount = amountInJPY * (exchangeRates.value[toCurrency] || 1);

        return convertedAmount;
    };

    /**
     * 格式化價格並做匯率轉換
     * @param {number|string} price - 原始價格
     * @param {string} fromCurrency - 原始幣別
     * @param {string|null} toCurrency - 目標幣別，null 時使用系統預設
     * @returns {string} 格式化後的價格字串
     */
    const formatPriceWithConversion = (price, fromCurrency, toCurrency = null) => {
        const safePrice = price ?? 0;
        const numPrice = typeof safePrice === 'string' ? parseFloat(safePrice) : safePrice;
        const targetCurrency = toCurrency || systemCurrency.value;

        // 執行匯率轉換
        const convertedPrice = convertCurrency(numPrice, fromCurrency, targetCurrency);

        // 格式化價格
        return formatPrice(convertedPrice, targetCurrency);
    };

    /**
     * 更新匯率表
     * @param {Object} newRates - 新的匯率對照表
     */
    const updateExchangeRates = (newRates) => {
        exchangeRates.value = { ...exchangeRates.value, ...newRates };
    };

    /**
     * 設定系統幣別
     * @param {string} currency - 新的系統幣別
     */
    const setSystemCurrency = (currency) => {
        systemCurrency.value = currency;
    };

    // 回傳所有公開的方法和狀態
    return {
        // 狀態
        systemCurrency,
        currencySymbols,
        exchangeRates,

        // 方法
        getCurrencySymbol,
        formatPrice,
        convertCurrency,
        formatPriceWithConversion,
        updateExchangeRates,
        setSystemCurrency
    };
}
;
/* === includes/views/composables/useApi.js === */
/**
 * API Composable - 統一 API 調用管理
 *
 * 功能:
 * - 自動管理 wpNonce 和 headers
 * - 統一 loading/error 狀態管理
 * - 統一錯誤處理與通知
 * - 自動防快取機制
 * - 提供 GET、POST、PUT、DELETE 快捷方法
 *
 * 使用方式:
 * const { get, post, put, delete: del, isLoading, error } = useApi();
 *
 * // GET 請求
 * const result = await get('/wp-json/buygo-plus-one/v1/orders');
 *
 * // POST 請求
 * const result = await post('/wp-json/buygo-plus-one/v1/orders', { data: '...' });
 *
 * // 自訂選項
 * const result = await get('/wp-json/buygo-plus-one/v1/orders', {
 *     showError: false,      // 不顯示錯誤 toast
 *     showSuccess: true,     // 顯示成功 toast
 *     successMessage: '操作成功',
 *     preventCache: true     // 防快取（預設開啟）
 * });
 *
 * @version 1.0.0
 * @date 2026-01-24
 */

// 注意: 這是一個全局函數,不使用 ES6 import/export
// 因為 WordPress 環境中 ES6 模組可能不被支持
function useApi() {
    const { ref } = Vue;

    // ============================================
    // 1. 認證與配置
    // ============================================

    /**
     * WordPress REST API Nonce
     * 從全局變數讀取，用於所有 API 請求的認證
     */
    const wpNonce = window.buygoWpNonce || '';

    /**
     * API 基礎路徑
     */
    const API_BASE = '/wp-json/buygo-plus-one/v1';

    // ============================================
    // 2. 全局狀態
    // ============================================

    /**
     * 全局 Loading 狀態
     * 任何 API 調用進行中時為 true
     */
    const isLoading = ref(false);

    /**
     * 全局錯誤訊息
     * 保存最近一次 API 錯誤
     */
    const error = ref(null);

    // ============================================
    // 3. 內部工具方法
    // ============================================

    /**
     * 生成 HTTP Headers
     * @param {boolean} isJson - 是否為 JSON 請求
     * @returns {Object} Headers 物件
     */
    const getHeaders = (isJson = true) => {
        const headers = {
            'X-WP-Nonce': wpNonce,
            'Cache-Control': 'no-cache',
            'Pragma': 'no-cache'
        };

        if (isJson) {
            headers['Content-Type'] = 'application/json';
        }

        return headers;
    };

    /**
     * 生成 Fetch 配置
     * @param {string} method - HTTP 方法 (GET, POST, PUT, DELETE)
     * @param {Object|null} body - 請求 body
     * @param {boolean} isJson - 是否為 JSON 請求
     * @returns {Object} Fetch 配置物件
     */
    const getFetchConfig = (method = 'GET', body = null, isJson = true) => {
        const config = {
            method,
            headers: getHeaders(isJson),
            credentials: 'include'
        };

        // GET 請求防快取
        if (method === 'GET') {
            config.cache = 'no-store';
        }

        // 添加 body（如果有）
        if (body) {
            config.body = isJson ? JSON.stringify(body) : body;
        }

        return config;
    };

    /**
     * 添加時間戳記防快取
     * @param {string} url - 原始 URL
     * @returns {string} 帶時間戳記的 URL
     */
    const addCacheBuster = (url) => {
        const separator = url.includes('?') ? '&' : '?';
        return `${url}${separator}_t=${Date.now()}`;
    };

    /**
     * 記錄錯誤到後端（可選）
     * @param {string} module - 模組名稱
     * @param {string} message - 錯誤訊息
     * @param {Object} data - 額外數據
     */
    const logError = async (module, message, data = {}) => {
        try {
            await window.fetch(`${API_BASE}/debug/log`, {
                method: 'POST',
                headers: getHeaders(true),
                credentials: 'include',
                body: JSON.stringify({
                    module,
                    message,
                    level: 'error',
                    data
                })
            });
        } catch (err) {
            console.error('無法記錄錯誤到後端:', err);
        }
    };

    // ============================================
    // 4. 核心 API 方法
    // ============================================

    /**
     * 統一的 API 請求包裝器
     *
     * @param {string} url - API 端點 URL
     * @param {Object} options - 配置選項
     * @param {string} options.method - HTTP 方法 (GET, POST, PUT, DELETE)
     * @param {Object|null} options.body - 請求 body
     * @param {boolean} options.isJson - 是否為 JSON 請求（預設 true）
     * @param {boolean} options.showError - 是否顯示錯誤 toast（預設 true）
     * @param {boolean} options.showSuccess - 是否顯示成功 toast（預設 false）
     * @param {string} options.successMessage - 成功訊息文字
     * @param {string} options.errorMessage - 錯誤訊息文字
     * @param {Function} options.onSuccess - 成功回調
     * @param {Function} options.onError - 失敗回調
     * @param {boolean} options.preventCache - 是否防快取（預設 true）
     * @param {boolean} options.logErrorToBackend - 是否記錄錯誤到後端（預設 false）
     * @param {string} options.module - 錯誤記錄的模組名稱
     *
     * @returns {Promise<Object>} API 響應結果
     * @throws {Error} API 錯誤
     */
    const request = async (url, options = {}) => {
        const {
            method = 'GET',
            body = null,
            isJson = true,
            showError = true,
            showSuccess = false,
            successMessage = '操作成功',
            errorMessage = '操作失敗',
            onSuccess = null,
            onError = null,
            preventCache = true,
            logErrorToBackend = false,
            module = 'API'
        } = options;

        // 添加防快取時間戳記
        let finalUrl = url;
        if (preventCache && method === 'GET') {
            finalUrl = addCacheBuster(url);
        }

        // 設置 loading 狀態
        isLoading.value = true;
        error.value = null;

        try {
            // 發送請求
            const response = await window.fetch(finalUrl, getFetchConfig(method, body, isJson));

            // HTTP 狀態碼檢查
            if (!response.ok) {
                // 未登入或權限不足：自動導向登入頁面
                if (response.status === 401 || response.status === 403) {
                    window.location.href = '/wp-login.php?redirect_to=' + encodeURIComponent(window.location.href);
                    return;
                }
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            // 解析 JSON 響應
            const result = await response.json();

            // 業務邏輯成功檢查
            if (!result.success) {
                throw new Error(result.message || errorMessage);
            }

            // 顯示成功訊息
            if (showSuccess && window.showToast) {
                window.showToast(result.message || successMessage, 'success');
            }

            // 調用成功回調
            if (onSuccess) {
                onSuccess(result);
            }

            return result;

        } catch (err) {
            // 保存錯誤狀態
            error.value = err.message;
            console.error(`[${module}] API 錯誤:`, err);

            // 顯示錯誤訊息
            if (showError && window.showToast) {
                window.showToast(err.message, 'error');
            }

            // 記錄到後端（可選）
            if (logErrorToBackend) {
                await logError(module, err.message, {
                    url: finalUrl,
                    method,
                    body
                });
            }

            // 調用失敗回調
            if (onError) {
                onError(err);
            }

            // 向外拋出錯誤
            throw err;

        } finally {
            // 重置 loading 狀態
            isLoading.value = false;
        }
    };

    // ============================================
    // 5. 快捷方法
    // ============================================

    /**
     * GET 請求
     * @param {string} url - API 端點
     * @param {Object} options - 配置選項
     * @returns {Promise<Object>} API 響應
     */
    const get = (url, options = {}) => {
        return request(url, { ...options, method: 'GET' });
    };

    /**
     * POST 請求
     * @param {string} url - API 端點
     * @param {Object} body - 請求 body
     * @param {Object} options - 配置選項
     * @returns {Promise<Object>} API 響應
     */
    const post = (url, body = {}, options = {}) => {
        return request(url, { ...options, method: 'POST', body });
    };

    /**
     * PUT 請求
     * @param {string} url - API 端點
     * @param {Object} body - 請求 body
     * @param {Object} options - 配置選項
     * @returns {Promise<Object>} API 響應
     */
    const put = (url, body = {}, options = {}) => {
        return request(url, { ...options, method: 'PUT', body });
    };

    /**
     * DELETE 請求
     * @param {string} url - API 端點
     * @param {Object} options - 配置選項
     * @returns {Promise<Object>} API 響應
     */
    const del = (url, options = {}) => {
        return request(url, { ...options, method: 'DELETE' });
    };

    // ============================================
    // 6. 公開接口
    // ============================================

    return {
        // 狀態
        isLoading,
        error,

        // 方法
        request,    // 核心方法（完整配置）
        get,        // GET 快捷方法
        post,       // POST 快捷方法
        put,        // PUT 快捷方法
        delete: del // DELETE 快捷方法
    };
}
;
/* === includes/views/composables/usePermissions.js === */
/**
 * Permissions Composable - 統一權限管理
 *
 * 功能:
 * - 檢查用戶是否為管理員
 * - 檢查用戶是否為小幫手
 * - 檢查特定功能權限
 * - 提供權限相關的 UI 控制
 *
 * 使用方式:
 * const { isAdmin, isHelper, can, loadPermissions } = usePermissions();
 *
 * // 檢查是否為管理員
 * if (isAdmin.value) {
 *     // 管理員專屬功能
 * }
 *
 * // 檢查特定權限
 * if (can('manage_helpers')) {
 *     // 可以管理小幫手
 * }
 *
 * @version 1.0.0
 * @date 2026-01-24
 */

// 注意: 這是一個全局函數,不使用 ES6 import/export
// 因為 WordPress 環境中 ES6 模組可能不被支持
function usePermissions() {
    const { ref, computed } = Vue;

    // ============================================
    // 1. 權限狀態
    // ============================================

    /**
     * 是否為管理員
     * @type {Ref<boolean>}
     */
    const isAdmin = ref(false);

    /**
     * 是否為小幫手
     * @type {Ref<boolean>}
     */
    const isHelper = ref(false);

    /**
     * 用戶角色
     * 可能的值: 'admin', 'helper', 'customer', null
     * @type {Ref<string|null>}
     */
    const userRole = ref(null);

    /**
     * 用戶 ID
     * @type {Ref<number|null>}
     */
    const userId = ref(null);

    /**
     * 用戶顯示名稱
     * @type {Ref<string>}
     */
    const displayName = ref('');

    /**
     * 權限載入狀態
     * @type {Ref<boolean>}
     */
    const loading = ref(false);

    /**
     * 權限載入錯誤
     * @type {Ref<string|null>}
     */
    const error = ref(null);

    /**
     * 具體權限清單
     * @type {Ref<Object>}
     */
    const permissions = ref({
        // 管理員專屬權限
        manage_helpers: false,      // 管理小幫手
        manage_settings: false,     // 管理設定
        view_all_orders: false,     // 查看所有訂單
        export_data: false,         // 匯出數據

        // 小幫手權限
        view_products: false,       // 查看商品
        manage_products: false,     // 管理商品
        view_orders: false,         // 查看訂單
        manage_orders: false,       // 管理訂單
        view_customers: false,      // 查看客戶
        manage_shipments: false     // 管理出貨
    });

    // ============================================
    // 2. 計算屬性
    // ============================================

    /**
     * 是否已登入
     */
    const isLoggedIn = computed(() => {
        return userId.value !== null && userId.value > 0;
    });

    /**
     * 是否有任何管理權限
     */
    const hasAnyPermission = computed(() => {
        return isAdmin.value || isHelper.value;
    });

    /**
     * 用戶角色顯示名稱
     */
    const roleDisplayName = computed(() => {
        const roleMap = {
            'admin': '管理員',
            'helper': '小幫手',
            'customer': '客戶'
        };
        return roleMap[userRole.value] || '訪客';
    });

    // ============================================
    // 3. 權限檢查方法
    // ============================================

    /**
     * 檢查是否擁有特定權限
     * @param {string} permission - 權限名稱
     * @returns {boolean} 是否擁有該權限
     */
    const can = (permission) => {
        // 管理員擁有所有權限
        if (isAdmin.value) {
            return true;
        }

        // 檢查具體權限
        return permissions.value[permission] === true;
    };

    /**
     * 檢查是否擁有任一權限（OR 邏輯）
     * @param {string[]} permissionList - 權限名稱陣列
     * @returns {boolean} 是否擁有任一權限
     */
    const canAny = (permissionList) => {
        if (isAdmin.value) {
            return true;
        }

        return permissionList.some(permission => permissions.value[permission] === true);
    };

    /**
     * 檢查是否擁有所有權限（AND 邏輯）
     * @param {string[]} permissionList - 權限名稱陣列
     * @returns {boolean} 是否擁有所有權限
     */
    const canAll = (permissionList) => {
        if (isAdmin.value) {
            return true;
        }

        return permissionList.every(permission => permissions.value[permission] === true);
    };

    /**
     * 檢查是否可以訪問某個頁面
     * @param {string} page - 頁面名稱 (products, orders, customers, shipments, settings)
     * @returns {boolean} 是否可以訪問
     */
    const canAccessPage = (page) => {
        // 管理員可以訪問所有頁面
        if (isAdmin.value) {
            return true;
        }

        // 根據頁面類型檢查權限
        const pagePermissions = {
            'products': 'view_products',
            'orders': 'view_orders',
            'customers': 'view_customers',
            'shipments': 'manage_shipments',
            'settings': 'manage_settings'
        };

        const requiredPermission = pagePermissions[page];
        return requiredPermission ? can(requiredPermission) : false;
    };

    // ============================================
    // 4. 權限載入方法
    // ============================================

    /**
     * 從 API 載入用戶權限
     * @returns {Promise<Object>} 權限數據
     */
    const loadPermissions = async () => {
        loading.value = true;
        error.value = null;

        try {
            const wpNonce = window.buygoWpNonce || '';

            const response = await window.fetch('/wp-json/buygo-plus-one/v1/settings/permissions', {
                headers: {
                    'X-WP-Nonce': wpNonce,
                    'Cache-Control': 'no-cache'
                },
                credentials: 'include'
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const result = await response.json();

            if (result.success && result.data) {
                // 更新基礎狀態
                isAdmin.value = result.data.is_admin || false;
                isHelper.value = result.data.is_helper || false;
                userRole.value = result.data.role || null;
                userId.value = result.data.user_id || null;
                displayName.value = result.data.display_name || '';

                // 更新具體權限
                if (result.data.permissions) {
                    permissions.value = {
                        ...permissions.value,
                        ...result.data.permissions
                    };
                } else {
                    // 根據角色設定預設權限
                    updatePermissionsByRole();
                }

                return result.data;
            } else {
                throw new Error(result.message || '載入權限失敗');
            }

        } catch (err) {
            error.value = err.message;
            console.error('載入權限錯誤:', err);

            // 失敗時設定為訪客權限
            resetPermissions();
            throw err;

        } finally {
            loading.value = false;
        }
    };

    /**
     * 根據角色更新權限（用於後端未提供具體權限時）
     */
    const updatePermissionsByRole = () => {
        if (isAdmin.value) {
            // 管理員擁有所有權限
            Object.keys(permissions.value).forEach(key => {
                permissions.value[key] = true;
            });
        } else if (isHelper.value) {
            // 小幫手擁有部分權限
            permissions.value = {
                manage_helpers: false,
                manage_settings: false,
                view_all_orders: false,
                export_data: false,

                view_products: true,
                manage_products: true,
                view_orders: true,
                manage_orders: true,
                view_customers: true,
                manage_shipments: true
            };
        } else {
            // 訪客/客戶無權限
            resetPermissions();
        }
    };

    /**
     * 重置為訪客權限（無權限）
     */
    const resetPermissions = () => {
        isAdmin.value = false;
        isHelper.value = false;
        userRole.value = null;
        userId.value = null;
        displayName.value = '';

        Object.keys(permissions.value).forEach(key => {
            permissions.value[key] = false;
        });
    };

    /**
     * 手動設定權限（用於測試或特殊情況）
     * @param {Object} data - 權限數據
     */
    const setPermissions = (data) => {
        if (data.is_admin !== undefined) {
            isAdmin.value = data.is_admin;
        }
        if (data.is_helper !== undefined) {
            isHelper.value = data.is_helper;
        }
        if (data.role !== undefined) {
            userRole.value = data.role;
        }
        if (data.user_id !== undefined) {
            userId.value = data.user_id;
        }
        if (data.display_name !== undefined) {
            displayName.value = data.display_name;
        }
        if (data.permissions) {
            permissions.value = {
                ...permissions.value,
                ...data.permissions
            };
        }
    };

    // ============================================
    // 5. UI 輔助方法
    // ============================================

    /**
     * 權限不足時的提示訊息
     * @param {string} action - 動作名稱
     */
    const showPermissionDenied = (action = '執行此操作') => {
        if (window.showToast) {
            window.showToast(`您沒有權限${action}`, 'error');
        } else {
            alert(`您沒有權限${action}`);
        }
    };

    /**
     * 需要權限時的確認檢查
     * @param {string|string[]} permission - 權限名稱或陣列
     * @param {string} action - 動作名稱（用於錯誤訊息）
     * @returns {boolean} 是否通過檢查
     */
    const requirePermission = (permission, action = '執行此操作') => {
        const hasPermission = Array.isArray(permission)
            ? canAny(permission)
            : can(permission);

        if (!hasPermission) {
            showPermissionDenied(action);
            return false;
        }

        return true;
    };

    // ============================================
    // 6. 公開接口
    // ============================================

    return {
        // 狀態
        isAdmin,
        isHelper,
        userRole,
        userId,
        displayName,
        permissions,
        loading,
        error,

        // 計算屬性
        isLoggedIn,
        hasAnyPermission,
        roleDisplayName,

        // 權限檢查方法
        can,
        canAny,
        canAll,
        canAccessPage,

        // 權限管理方法
        loadPermissions,
        setPermissions,
        resetPermissions,

        // UI 輔助方法
        showPermissionDenied,
        requirePermission
    };
}
;
/* === includes/views/composables/useDataLoader.js === */
/**
 * useDataLoader Composable
 * SPA 資料載入核心 — Cache-first + SWR 策略
 *
 * 載入優先順序：
 * 1. window.buygoInitialData[pageKey]（PHP 預注入，僅 landing page 有）
 * 2. BuyGoCache.get(pageKey)（記憶體 → sessionStorage）
 * 3. API fetch（都沒有時打 API）
 *
 * SWR（Stale-While-Revalidate）：
 * - 有快取時先渲染快取資料，背景靜默更新
 * - 無快取時顯示 loading 狀態
 *
 * 使用方式：
 * var loader = useDataLoader('orders');
 * loader.loadData(function() {
 *     return fetch('/wp-json/buygo-plus-one/v1/orders', { ... })
 *         .then(function(res) { return res.json(); });
 * });
 *
 * @param {string} pageKey - 頁面快取鍵名（對應 BuyGoCache 的 key）
 * @returns {Object} { data, loading, error, loadData, retryLoad, cleanup }
 */
function useDataLoader(pageKey) {
    var ref = Vue.ref;

    // ============================================
    // 響應式狀態
    // ============================================

    /** @type {import('vue').Ref} 頁面資料 */
    var data = ref(null);

    /** @type {import('vue').Ref<boolean>} 首次載入中（無快取時為 true） */
    var loading = ref(false);

    /** @type {import('vue').Ref<string|null>} 錯誤訊息 */
    var error = ref(null);

    // ============================================
    // 內部狀態
    // ============================================

    /** 上次使用的 fetchFn（用於 retryLoad） */
    var _lastFetchFn = null;

    /** AbortController 實例（用於取消進行中的 fetch） */
    var _abortController = null;

    // ============================================
    // 核心方法
    // ============================================

    /**
     * Cache-first 載入資料
     *
     * 流程：
     * (a) 查 buygoInitialData[pageKey] → 有則設 data、寫入 BuyGoCache、清除預注入
     * (b) 查 BuyGoCache.get(pageKey) → 有則先設 data、loading=false
     * (c) 呼叫 fetchFn() → 成功設 data + BuyGoCache.set
     * (d) 如果 (b) 有快取但不新鮮，背景靜默呼叫 fetchFn 更新
     *
     * @param {Function} fetchFn - async 函式，回傳 API 資料
     */
    function loadData(fetchFn) {
        _lastFetchFn = fetchFn;
        error.value = null;

        // (a) 查 PHP 預注入資料
        if (window.buygoInitialData && window.buygoInitialData[pageKey] !== undefined) {
            var preloaded = window.buygoInitialData[pageKey];
            data.value = preloaded;
            loading.value = false;

            // 寫入 BuyGoCache 供後續 SPA 導航使用
            if (window.BuyGoCache) {
                window.BuyGoCache.set(pageKey, preloaded);
            }

            // 清除預注入資料，避免重複使用
            delete window.buygoInitialData[pageKey];
            return;
        }

        // (b) 查 BuyGoCache
        var cached = null;
        var hasFreshCache = false;

        if (window.BuyGoCache) {
            cached = window.BuyGoCache.get(pageKey);
            if (cached !== null) {
                // 有快取：先渲染快取資料
                data.value = cached;
                loading.value = false;
                hasFreshCache = window.BuyGoCache.isFresh(pageKey) === true;
            }
        }

        // (c) 無快取 → 顯示 loading 並打 API
        if (cached === null) {
            loading.value = true;
            _fetchFromApi(fetchFn);
            return;
        }

        // (d) 有快取但不新鮮 → 背景靜默更新（SWR）
        if (!hasFreshCache) {
            _fetchFromApi(fetchFn, true);
        }
    }

    /**
     * 呼叫 API 取得資料
     * @param {Function} fetchFn - async fetch 函式
     * @param {boolean} silent - 靜默模式（不顯示 loading、失敗不覆蓋資料）
     */
    function _fetchFromApi(fetchFn, silent) {
        // 取消上一個進行中的 fetch
        if (_abortController) {
            _abortController.abort();
        }
        _abortController = new AbortController();

        var currentController = _abortController;

        // 將 signal 傳給 fetchFn（fetchFn 可選擇使用）
        var fetchPromise;
        try {
            fetchPromise = fetchFn(currentController.signal);
        } catch (e) {
            if (!silent) {
                error.value = e.message || '載入失敗';
                loading.value = false;
            }
            return;
        }

        // 處理 Promise
        if (fetchPromise && typeof fetchPromise.then === 'function') {
            fetchPromise
                .then(function(result) {
                    // 檢查是否已被取消
                    if (currentController.signal.aborted) return;

                    data.value = result;
                    error.value = null;

                    // 寫入 BuyGoCache
                    if (window.BuyGoCache) {
                        window.BuyGoCache.set(pageKey, result);
                    }
                })
                .catch(function(err) {
                    // 被取消的 fetch 不處理錯誤
                    if (err && err.name === 'AbortError') return;
                    if (currentController.signal.aborted) return;

                    if (!silent) {
                        error.value = err.message || '載入失敗';
                    }
                    console.error('[useDataLoader] ' + pageKey + ' 載入失敗:', err);
                })
                .finally(function() {
                    if (currentController.signal.aborted) return;
                    if (!silent) {
                        loading.value = false;
                    }
                });
        } else {
            // fetchFn 沒有回傳 Promise
            if (!silent) {
                loading.value = false;
            }
        }
    }

    /**
     * 重試上次失敗的載入
     */
    function retryLoad() {
        if (_lastFetchFn) {
            loadData(_lastFetchFn);
        }
    }

    /**
     * 取消進行中的 fetch 並清理資源
     */
    function cleanup() {
        if (_abortController) {
            _abortController.abort();
            _abortController = null;
        }
    }

    // ============================================
    // 公開介面
    // ============================================
    return {
        data: data,
        loading: loading,
        error: error,
        loadData: loadData,
        retryLoad: retryLoad,
        cleanup: cleanup
    };
}
;
/* === includes/views/composables/useOrders.js === */
/**
 * useOrders Composable
 * 訂單管理頁面的資料邏輯層
 *
 * 從 OrdersPage.js 的 setup() 提取而來
 * Dependencies: Vue 3, BuyGoRouter, useCurrency, BuyGoSmartSearchBox, BuyGoCache
 */
function useOrders() {
        const { ref, computed, onMounted, onUnmounted, watch } = Vue;

        // WordPress REST API nonce（用於 API 認證）
        const wpNonce = window.buygoWpNonce || '';

        // 使用 useCurrency Composable 處理幣別邏輯
        const {
            formatPrice: formatCurrency,
            formatPriceWithConversion,
            systemCurrency: systemCurrencyFromComposable,
            currencySymbols,
            exchangeRates
        } = useCurrency();

        // ============================================
        // 路由狀態（使用 BuyGoRouter 核心模組）
        // ============================================
        const currentView = ref('list');  // 'list' | 'detail'
        const currentOrderId = ref(null);

        // UI 狀態
        const showMobileSearch = ref(false);

        // 狀態變數
        const orders = ref([]);
        const loading = ref(false);
        const error = ref(null);

        // 分頁狀態
        const currentPage = ref(1);
        const perPage = ref(5);
        const totalOrders = ref(0);

        // 搜尋篩選狀態
        const searchFilter = ref(null);
        const searchFilterName = ref('');
        const searchQuery = ref('');

        // 狀態篩選（新增）
        const filterStatus = ref(null); // null (全部) | 'unshipped' | 'preparing' | 'shipped'
        const stats = ref({
            total: 0,
            unshipped: 0,
            preparing: 0,
            shipped: 0
        });

        // 幣別設定 - 使用 composable 的系統幣別
        const systemCurrency = systemCurrencyFromComposable; // 直接使用全域 ref
        const currentCurrency = ref(systemCurrencyFromComposable.value);

        // 監聽全域幣別變化
        watch(systemCurrency, (newCurrency) => {
            console.log('[OrdersPage] 偵測到幣別變化:', newCurrency);
            currentCurrency.value = newCurrency;
        });

        // 批次轉備貨
        const batchPrepare = async () => {
            if (selectedItems.value.length === 0) return;

            // 收集要處理的訂單（考慮父子訂單關係）
            // 如果父訂單有子訂單，應該處理子訂單而非父訂單
            const ordersToProcess = [];

            const skippedNoAllocation = []; // 記錄因無分配而跳過的訂單

            for (const orderId of selectedItems.value) {
                // 先在父訂單陣列中找
                let order = orders.value.find(o => o.id === orderId);

                if (!order) {
                    // 在「轉備貨」等篩選分頁中，子訂單會被提取為獨立項目顯示
                    // selectedItems 可能包含子訂單 ID，需要在篩選結果和父訂單的 children 中搜尋
                    order = allFilteredOrders.value.find(o => o.id === orderId);
                    if (!order) {
                        // 最後嘗試在所有父訂單的 children 中搜尋
                        for (const parentOrder of orders.value) {
                            if (parentOrder.children) {
                                const child = parentOrder.children.find(c => c.id === orderId);
                                if (child) {
                                    order = child;
                                    break;
                                }
                            }
                        }
                    }
                }
                if (!order) continue;

                // 如果父訂單有子訂單，處理其下的未出貨子訂單
                if (order.children && order.children.length > 0) {
                    for (const child of order.children) {
                        // 只處理未出貨的子訂單
                        if (!child.shipping_status || child.shipping_status === 'unshipped') {
                            // 檢查子訂單是否有分配
                            if (hasAllocatedItems(child)) {
                                ordersToProcess.push({
                                    id: child.id,
                                    invoice_no: child.invoice_no,
                                    isChild: true,
                                    parentInvoice: order.invoice_no || order.id
                                });
                            } else {
                                skippedNoAllocation.push(child.invoice_no || child.id);
                            }
                        }
                    }
                } else {
                    // 沒有子訂單的訂單（包含提取出的子訂單），直接處理
                    if (!order.shipping_status || order.shipping_status === 'unshipped') {
                        // 檢查訂單是否有分配
                        if (hasAllocatedItems(order)) {
                            ordersToProcess.push({
                                id: order.id,
                                invoice_no: order.invoice_no || order.id,
                                isChild: !!order._isExtractedChild || !!order.parent_id
                            });
                        } else {
                            skippedNoAllocation.push(order.invoice_no || order.id);
                        }
                    }
                }
            }

            if (ordersToProcess.length === 0) {
                // 根據跳過原因顯示不同訊息
                if (skippedNoAllocation.length > 0) {
                    showToast('所選訂單尚未分配庫存，無法轉備貨', 'error');
                } else {
                    showToast('所選訂單都不是「未出貨」狀態，無法轉備貨', 'error');
                }
                return;
            }

            const childCount = ordersToProcess.filter(o => o.isChild).length;
            const parentCount = ordersToProcess.filter(o => !o.isChild).length;

            let confirmMessage = `確定要將 ${ordersToProcess.length} 筆訂單轉為備貨狀態嗎？`;
            if (childCount > 0 && parentCount > 0) {
                confirmMessage += `\n（包含 ${parentCount} 筆父訂單、${childCount} 筆子訂單）`;
            } else if (childCount > 0) {
                confirmMessage += `\n（${childCount} 筆子訂單）`;
            }

            // 如果有被跳過的訂單（因無分配），提示使用者
            if (skippedNoAllocation.length > 0) {
                confirmMessage += `\n\n注意：${skippedNoAllocation.length} 筆訂單因尚未分配庫存而跳過`;
            }

            showConfirm(
                '批次轉備貨',
                confirmMessage,
                async () => {
                    batchProcessing.value = true;
                    let successCount = 0;
                    let failCount = 0;

                    try {
                        // 逐一呼叫 prepare API
                        for (const order of ordersToProcess) {
                            try {
                                const response = await fetch(`/wp-json/buygo-plus-one/v1/orders/${order.id}/prepare`, {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'X-WP-Nonce': wpNonce
                                    },
                                    credentials: 'include'
                                });

                                const result = await response.json();

                                if (result.success) {
                                    successCount++;
                                } else {
                                    failCount++;
                                    console.error(`訂單 #${order.invoice_no} 轉備貨失敗:`, result.message);
                                }
                            } catch (err) {
                                failCount++;
                                console.error(`訂單 #${order.invoice_no} 轉備貨錯誤:`, err);
                            }
                        }

                        // 顯示結果
                        if (failCount === 0) {
                            showToast(`成功將 ${successCount} 筆訂單轉為備貨狀態`, 'success');
                        } else {
                            showToast(`${successCount} 筆成功，${failCount} 筆失敗`, failCount > 0 ? 'error' : 'success');
                        }

                        // 清空選取並重新載入
                        selectedItems.value = [];
                        await loadOrders();

                    } catch (err) {
                        console.error('批次轉備貨錯誤:', err);
                        showToast('批次轉備貨失敗：' + err.message, 'error');
                    } finally {
                        batchProcessing.value = false;
                    }
                },
                { confirmText: '確認轉備貨', cancelText: '取消' }
            );
        };

        // 批次刪除
        const batchDelete = async () => {
            if(!confirm(`確認刪除 ${selectedItems.value.length} 項？`)) return;
            try {
                const res = await fetch('/wp-json/buygo-plus-one/v1/orders/batch-delete', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': wpNonce },
                    credentials: 'include',
                    body: JSON.stringify({ ids: selectedItems.value })
                });
                const data = await res.json();
                if (data.success) {
                    orders.value = orders.value.filter(o => !selectedItems.value.includes(o.id));
                    selectedItems.value = [];
                    showToast('批次刪除成功');
                    loadOrders();
                } else {
                    showToast(data.message || '刪除失敗', 'error');
                }
            } catch(e) { 
                console.error(e); 
                showToast('刪除錯誤', 'error'); 
            }
        };
        
        // 切換幣別
        const toggleCurrency = () => {
            // 在系統幣別和台幣之間切換
            if (currentCurrency.value === 'TWD') {
                currentCurrency.value = systemCurrencyFromComposable.value;
                systemCurrency.value = systemCurrencyFromComposable.value;
                showToast(`已切換為 ${currencySymbols[systemCurrencyFromComposable.value]} ${systemCurrencyFromComposable.value}`);
            } else {
                currentCurrency.value = 'TWD';
                systemCurrency.value = 'TWD';
                showToast(`已切換為 ${currencySymbols['TWD']} TWD`);
            }
        };

        // Modal 狀態（保留向下相容）
        const showOrderModal = ref(false);
        const currentOrder = ref(null);
        const shipping = ref(false);

        // OrderDetailModal 狀態（已改為 URL 驅動）
        const showModal = ref(false);
        const selectedOrderId = ref(null);

        // 批次操作
        const selectedItems = ref([]);
        const batchProcessing = ref(false);

        // 展開狀態（用於商品列表展開）
        const expandedOrders = ref(new Set());

        // 子訂單折疊狀態（預設展開）
        const collapsedChildren = ref(new Set());

        // 狀態下拉選單狀態
        const openStatusDropdown = ref(null);
        const dropdownPosition = ref({ top: 0, left: 0 });
        
        // 確認 Modal 狀態
        const confirmModal = ref({
            show: false,
            title: '',
            message: '',
            confirmText: '確認',
            cancelText: '取消',
            onConfirm: null
        });
        
        // Toast 通知狀態
        const toastMessage = ref({
            show: false,
            message: '',
            type: 'success' // 'success' | 'error' | 'info'
        });
        
        // 顯示確認對話框
        const showConfirm = (title, message, onConfirm, options = {}) => {
            confirmModal.value = {
                show: true,
                title,
                message,
                confirmText: options.confirmText || '確認',
                cancelText: options.cancelText || '取消',
                onConfirm
            };
        };
        
        // 關閉確認對話框
        const closeConfirmModal = () => {
            confirmModal.value.show = false;
        };
        
        // 確認按鈕處理
        const handleConfirm = () => {
            if (confirmModal.value.onConfirm) {
                confirmModal.value.onConfirm();
            }
            closeConfirmModal();
        };
        
        // 顯示 Toast 訊息
        const showToast = (message, type = 'success') => {
            toastMessage.value = { show: true, message, type };
            setTimeout(() => {
                toastMessage.value.show = false;
            }, 3000);
        };

        // 格式化價格（使用當前顯示幣別，並做匯率轉換）
        const formatPrice = (amount, originalCurrency = null) => {
            if (amount == null) return '-';

            // 如果有原始幣別且與當前顯示幣別不同，需要做匯率轉換
            if (originalCurrency && originalCurrency !== currentCurrency.value) {
                return formatPriceWithConversion(amount, originalCurrency, currentCurrency.value);
            }

            // 否則直接格式化（不轉換）
            return formatCurrency(amount, currentCurrency.value);
        };

        // 搜尋處理函數
        const handleSearchInput = (e) => {
            const query = e.target ? e.target.value : e;
            searchQuery.value = query;
            // 如果搜尋框有內容，嘗試找到對應的訂單
            if (query && query.trim()) {
                // 可以選擇是否要自動篩選，這裡先簡單處理為全域搜尋
                currentPage.value = 1;
                loadOrders();
            } else {
                // 清除搜尋時重置
                handleSearchClear();
            }
        };

        const handleSearchSelect = (item) => {
            searchFilter.value = item.id;
            searchFilterName.value = item.invoice_no || item.customer_name || '';
            searchQuery.value = item.invoice_no || item.customer_name || '';
            currentPage.value = 1;
            loadOrders();
        };

        const handleSearchClear = () => {
            searchFilter.value = null;
            searchFilterName.value = '';
            searchQuery.value = '';
            currentPage.value = 1;
            loadOrders();
        };

        // 載入訂單
        // 更新統計資料（使用 API 返回的全域統計）
        const updateStats = (apiStats) => {
            if (apiStats) {
                stats.value = {
                    total: apiStats.total || 0,
                    unshipped: apiStats.unshipped || 0,
                    preparing: apiStats.preparing || 0,
                    shipped: apiStats.shipped || 0
                };
            }
        };

        // 狀態對應函數（與後端 incrementStatsByStatus 保持一致）
        const getStatusCategory = (status) => {
            if (!status || status === 'unshipped' || status === 'pending') {
                return 'unshipped';
            } else if (status === 'preparing') {
                return 'preparing';
            } else if (['shipped', 'completed', 'processing', 'ready_to_ship'].includes(status)) {
                return 'shipped';
            }
            return 'unshipped';
        };

        // 檢查訂單是否已分配庫存
        const hasAllocatedItems = (order) => {
            if (!order) {
                return false;
            }

            // 【關鍵邏輯】如果父訂單已有子訂單，父訂單不應顯示「轉備貨」按鈕
            // 使用者應該在子訂單上執行轉備貨操作
            if (order.children && order.children.length > 0) {
                return false;
            }

            // 優先檢查 has_allocation 欄位（如果 API 有提供）
            if (order.has_allocation === true) {
                return true;
            }

            // 如果沒有 has_allocation 欄位，檢查 items 中的 allocated_quantity
            if (!order.items || !Array.isArray(order.items) || order.items.length === 0) {
                return false;
            }

            // 檢查每個 item 的 allocated_quantity
            return order.items.some(item => {
                // 處理各種可能的資料類型：數字、字串、null、undefined
                const allocatedQty = item.allocated_quantity != null
                    ? parseInt(item.allocated_quantity, 10)
                    : 0;

                // 確保是有效數字
                const isValidNumber = !isNaN(allocatedQty) && isFinite(allocatedQty);
                return isValidNumber && allocatedQty > 0;
            });
        };

        // 檢查是否可以顯示「轉備貨」按鈕
        // 只有在未出貨狀態才顯示按鈕，已備貨或已出貨則顯示狀態標籤
        const canShowShipButton = (order) => {
            if (!order) return false;
            const status = order.shipping_status || 'unshipped';
            // 只有 unshipped 狀態才顯示轉備貨按鈕
            return status === 'unshipped' || status === '';
        };

        // 篩選後的訂單（根據狀態分類，不含分頁）
        // 邏輯（2026-01-31 更新）：
        // - 「全部」分頁：顯示所有訂單（父訂單+子訂單階層結構）
        // - 「轉備貨」分頁：只顯示「已分配庫存且未出貨」的訂單（可操作的）
        // - 其他分頁（備貨中/已出貨）：只顯示符合狀態的訂單
        //   - 有子訂單的父訂單：只顯示符合條件的子訂單（作為獨立項目，不帶父訂單）
        //   - 沒有子訂單的父訂單：根據父訂單自己的狀態判斷
        const allFilteredOrders = computed(() => {
            if (!filterStatus.value) {
                return orders.value; // 顯示全部（保持原始階層結構）
            }

            // 過濾訂單：只顯示可操作的訂單
            const result = [];

            // 「轉備貨」分頁特殊處理：只顯示已分配庫存的訂單
            const isUnshippedFilter = filterStatus.value === 'unshipped';

            for (const order of orders.value) {
                const children = order.children || [];

                if (children.length > 0) {
                    // 有子訂單：找出符合狀態的子訂單
                    const matchingChildren = children.filter(child => {
                        const childCategory = getStatusCategory(child.shipping_status);
                        if (childCategory !== filterStatus.value) {
                            return false;
                        }
                        // 「轉備貨」分頁：額外檢查是否已分配庫存
                        if (isUnshippedFilter) {
                            return hasAllocatedItems(child);
                        }
                        return true;
                    });

                    // 將每個符合條件的子訂單作為獨立項目加入結果
                    // 不再以父訂單包裹子訂單的形式顯示
                    for (const child of matchingChildren) {
                        // 【修復 2026-01-31】確保提取的子訂單有 items 資料
                        // 如果子訂單沒有 items，從父訂單的子訂單中繼承
                        const childWithItems = {
                            ...child,
                            items: child.items || [], // 確保 items 存在
                            _isExtractedChild: true, // 標記這是從父訂單提取出來的子訂單
                            _parentOrder: order // 保留父訂單參考（如需要顯示父訂單資訊）
                        };
                        result.push(childWithItems);
                    }
                } else {
                    // 沒有子訂單：檢查父訂單自己的狀態
                    const parentCategory = getStatusCategory(order.shipping_status);
                    if (parentCategory !== filterStatus.value) {
                        continue;
                    }
                    // 「轉備貨」分頁：額外檢查是否已分配庫存
                    if (isUnshippedFilter && !hasAllocatedItems(order)) {
                        continue;
                    }
                    result.push(order);
                }
            }

            return result;
        });

        // 【修復 2026-01-31】加入分頁邏輯的篩選訂單
        // filteredOrders 是分頁後的結果，用於模板渲染
        const filteredOrders = computed(() => {
            const all = allFilteredOrders.value;

            // 如果 perPage 為 -1，表示顯示全部
            if (perPage.value === -1) {
                return all;
            }

            // 計算分頁起始和結束索引
            const start = (currentPage.value - 1) * perPage.value;
            const end = start + perPage.value;

            return all.slice(start, end);
        });

        // 根據當前篩選狀態，取得子訂單列表（用於模板）
        // 邏輯：
        // - 無篩選（全部）：返回所有子訂單
        // - 有篩選：返回空陣列（因為子訂單已經被提取為獨立項目顯示）
        const getFilteredChildren = (order) => {
            if (!filterStatus.value) {
                return order.children || [];
            }
            // 篩選模式下，子訂單已經被提取為獨立項目，不需要在父訂單下再顯示
            return [];
        };

        // 計算每個分類的實際可操作數量（用於標籤頁顯示）
        // 這與 filteredOrders 使用相同的邏輯，確保數字與實際顯示內容一致
        const tabCounts = computed(() => {
            const counts = {
                total: 0,
                unshipped: 0,   // 轉備貨（需要有已分配庫存）
                preparing: 0,   // 備貨中
                shipped: 0      // 已出貨
            };

            for (const order of orders.value) {
                const children = order.children || [];

                if (children.length > 0) {
                    // 有子訂單：計算各狀態的子訂單數量
                    for (const child of children) {
                        const childCategory = getStatusCategory(child.shipping_status);

                        // 「轉備貨」需要額外檢查是否已分配庫存
                        if (childCategory === 'unshipped') {
                            if (hasAllocatedItems(child)) {
                                counts.unshipped++;
                            }
                        } else if (childCategory === 'preparing') {
                            counts.preparing++;
                        } else if (childCategory === 'shipped') {
                            counts.shipped++;
                        }
                    }
                    counts.total++; // 父訂單計入總數
                } else {
                    // 沒有子訂單：根據父訂單狀態計算
                    const parentCategory = getStatusCategory(order.shipping_status);

                    if (parentCategory === 'unshipped') {
                        if (hasAllocatedItems(order)) {
                            counts.unshipped++;
                        }
                    } else if (parentCategory === 'preparing') {
                        counts.preparing++;
                    } else if (parentCategory === 'shipped') {
                        counts.shipped++;
                    }
                    counts.total++;
                }
            }

            return counts;
        });

        const loadOrders = async (options = {}) => {
            // silent 模式：背景刷新時不顯示 loading skeleton，避免切頁閃爍
            if (!options.silent) loading.value = true;
            error.value = null;

            try {
                // 始終請求所有資料（最多 100 筆），以便正確計算各分頁的數量
                // 因為前端需要完整資料來計算「轉備貨」等分頁的實際可操作數量
                const requestPerPage = 100;
                const requestPage = 1;

                // 使用 BuyGoCache 快取層，不再強制繞過瀏覽器快取
                let url = `/wp-json/buygo-plus-one/v1/orders?page=${requestPage}&per_page=${requestPerPage}`;

                if (searchFilter.value) {
                    url += `&id=${searchFilter.value}`;
                } else if (searchQuery.value && searchQuery.value.trim()) {
                    // 如果沒有特定篩選，但有搜尋關鍵字，使用 search 參數
                    url += `&search=${encodeURIComponent(searchQuery.value.trim())}`;
                }

                const response = await fetch(url, {
                    credentials: 'include',
                    headers: {
                        'X-WP-Nonce': wpNonce
                    }
                });

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const result = await response.json();

                if (result.success && result.data) {
                    // 為每個訂單加上 has_allocation 標記和確保 items 存在
                    orders.value = result.data.map(order => ({
                        ...order,
                        // 檢查是否有分配的商品
                        has_allocation: order.items && Array.isArray(order.items) && order.items.some(item => {
                            const allocatedQty = item.allocated_quantity != null
                                ? parseInt(item.allocated_quantity, 10)
                                : 0;
                            return !isNaN(allocatedQty) && isFinite(allocatedQty) && allocatedQty > 0;
                        }),
                        // 確保 items 陣列存在
                        items: order.items || []
                    }));
                    totalOrders.value = result.total || result.data.length;

                    // 更新統計資料（使用 API 返回的全域統計）
                    updateStats(result.stats);

                    // 預設折疊所有有子訂單的訂單
                    orders.value.forEach(order => {
                        if (order.children && order.children.length > 0) {
                            collapsedChildren.value.add(order.id);
                        }
                    });

                    // 儲存到 BuyGoCache
                    if (window.BuyGoCache) { window.BuyGoCache.set('orders', result); }
                } else {
                    throw new Error(result.message || '載入訂單失敗');
                }
            } catch (err) {
                console.error('載入訂單錯誤:', err);
                error.value = err.message;
                
                // 記錄到除錯中心（透過 API）
                fetch('/wp-json/buygo-plus-one/v1/debug/log', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': wpNonce },
                    credentials: 'include',
                    body: JSON.stringify({
                        module: 'Orders',
                        message: '載入訂單失敗',
                        level: 'error',
                        data: { error: err.message, url: url }
                    })
                }).catch(console.error);
                
                orders.value = [];
            } finally {
                loading.value = false;
            }
        };

        // 格式化日期
        const formatDate = (dateString) => {
            if (!dateString) return '';
            const date = new Date(dateString);
            return date.toLocaleDateString('zh-TW');
        };
        
        // 格式化商品列表顯示
        const formatItemsDisplay = (order, maxLength = 50) => {
            if (!order.items || !Array.isArray(order.items) || order.items.length === 0) {
                return `${order.total_items || 0} 件`;
            }

            // 【修復 2026-01-31】直接使用 quantity 顯示
            // 子訂單有自己正確的 quantity 值，不需要用 pending_quantity
            // pending_quantity 是「待分配數量」，對於已分配/已出貨的訂單會是 0
            const itemsText = order.items
                .map(item => {
                    const displayQty = item.quantity || 0;
                    return `${item.product_name || '未知商品'} x${displayQty}`;
                })
                .join(', ');

            // 如果文字太長，截斷並加上省略號
            if (itemsText.length > maxLength) {
                return itemsText.substring(0, maxLength) + '...';
            }

            return itemsText;
        };
        
        // 切換訂單展開狀態
        const toggleOrderExpand = async (orderId) => {
            if (expandedOrders.value.has(orderId)) {
                expandedOrders.value.delete(orderId);
            } else {
                expandedOrders.value.add(orderId);
                
                // 如果訂單沒有 items，載入詳細資料
                const order = orders.value.find(o => o.id === orderId);
                if (order && (!order.items || !Array.isArray(order.items) || order.items.length === 0)) {
                    try {
                        const response = await fetch(`/wp-json/buygo-plus-one/v1/orders?id=${orderId}`, {
                            credentials: 'include',
                            headers: { 'X-WP-Nonce': wpNonce }
                        });
                        
                        const result = await response.json();
                        
                        if (result.success && result.data && result.data.length > 0) {
                            const orderDetail = result.data[0];
                            // 更新 orders 陣列中的訂單資料
                            const index = orders.value.findIndex(o => o.id === orderId);
                            if (index !== -1) {
                                orders.value[index] = { ...orders.value[index], items: orderDetail.items };
                            }
                        }
                    } catch (err) {
                        console.error('載入訂單商品失敗:', err);
                    }
                }
            }
        };
        
        // 檢查訂單是否展開
        const isOrderExpanded = (orderId) => {
            return expandedOrders.value.has(orderId);
        };

        // 切換子訂單顯示/隱藏
        const toggleChildrenCollapse = (orderId) => {
            if (collapsedChildren.value.has(orderId)) {
                collapsedChildren.value.delete(orderId);
            } else {
                collapsedChildren.value.add(orderId);
            }
        };

        // 檢查子訂單是否已折疊
        const isChildrenCollapsed = (orderId) => {
            return collapsedChildren.value.has(orderId);
        };

        // 運送狀態選項（6個）
        const shippingStatuses = [
            { value: 'unshipped', label: '未出貨', color: 'bg-gray-100 text-gray-800 border border-gray-300' },
            { value: 'preparing', label: '備貨中', color: 'bg-yellow-100 text-yellow-800 border border-yellow-300' },
            { value: 'processing', label: '待出貨', color: 'bg-blue-100 text-blue-800 border border-blue-300' },
            { value: 'shipped', label: '已出貨', color: 'bg-purple-100 text-purple-800 border border-purple-300' },
            { value: 'completed', label: '交易完成', color: 'bg-green-100 text-green-800 border border-green-300' },
            { value: 'out_of_stock', label: '斷貨', color: 'bg-red-100 text-red-800 border border-red-300' }
        ];

        // 取得運送狀態樣式
        const getStatusClass = (status) => {
            const statusObj = shippingStatuses.find(s => s.value === status);
            return statusObj ? statusObj.color : 'bg-slate-100 text-slate-800 border border-slate-300';
        };

        // 取得運送狀態文字
        const getStatusText = (status) => {
            const statusObj = shippingStatuses.find(s => s.value === status);
            return statusObj ? statusObj.label : status;
        };

        // 切換狀態下拉選單
        const toggleStatusDropdown = (orderId, event) => {
            if (openStatusDropdown.value === orderId) {
                openStatusDropdown.value = null;
            } else {
                openStatusDropdown.value = orderId;

                // 計算下拉選單位置（fixed 定位）
                if (event && event.target) {
                    const button = event.target.closest('button');
                    if (button) {
                        const rect = button.getBoundingClientRect();
                        // 向上展開：選單底部對齊按鈕頂部
                        dropdownPosition.value = {
                            top: rect.top - 8, // 減去 mb-1 的間距
                            left: rect.left
                        };
                    }
                }
            }
        };

        // 檢查狀態下拉選單是否開啟
        const isStatusDropdownOpen = (orderId) => {
            return openStatusDropdown.value === orderId;
        };

        // 更新運送狀態
        const updateShippingStatus = async (orderId, newStatus) => {
            try {
                const response = await fetch(`/wp-json/buygo-plus-one/v1/orders/${orderId}/shipping-status`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': wpNonce
                    },
                    credentials: 'include',
                    body: JSON.stringify({ status: newStatus })
                });

                const result = await response.json();

                if (result.success) {
                    // 更新本地訂單資料
                    const order = orders.value.find(o => o.id === orderId);
                    if (order) {
                        order.shipping_status = newStatus;
                    }
                    showToast('運送狀態已更新', 'success');
                } else {
                    showToast('更新失敗：' + (result.message || '未知錯誤'), 'error');
                }
            } catch (err) {
                console.error('更新運送狀態失敗:', err);
                showToast('更新失敗：' + err.message, 'error');
            } finally {
                // 關閉下拉選單
                openStatusDropdown.value = null;
            }
        };

        // 查看訂單詳情
        const viewOrderDetails = async (order) => {
            showOrderModal.value = true;
            // 重新載入訂單詳情以取得最新的 allocated_quantity
            await loadOrderDetail(order.id);
        };
        
        // 關閉訂單詳情 Modal
        const closeOrderModal = () => {
            showOrderModal.value = false;
            currentOrder.value = null;
        };
        
        // ============================================
        // 路由邏輯（使用 BuyGoRouter 核心模組）
        // ============================================
        const checkUrlParams = () => {
            const params = window.BuyGoRouter.checkUrlParams();
            const { view, id } = params;

            if (view === 'detail' && id) {
                currentView.value = 'detail';
                currentOrderId.value = id;
                selectedOrderId.value = id;
                loadOrderDetail(id);
            } else {
                currentView.value = 'list';
                currentOrderId.value = null;
            }
        };

        const navigateTo = (view, orderId = null, updateUrl = true) => {
            currentView.value = view;

            if (orderId) {
                currentOrderId.value = orderId;
                selectedOrderId.value = orderId;
                loadOrderDetail(orderId);

                if (updateUrl) {
                    window.BuyGoRouter.navigateTo(view, orderId);
                }
            } else {
                currentOrderId.value = null;
                selectedOrderId.value = null;
                currentOrder.value = null;

                if (updateUrl) {
                    window.BuyGoRouter.goToList();
                }
            }
        };

        // 開啟訂單詳情（URL 驅動）
        const openOrderDetail = (orderId) => {
            navigateTo('detail', orderId);
        };

        // 關閉訂單詳情（返回列表）
        const closeOrderDetail = () => {
            navigateTo('list');
        };
        
        // 檢查訂單是否有可出貨的商品（用於父訂單）
        // 重要：如果父訂單已有子訂單（拆單），父訂單本身不應顯示「轉備貨」按鈕
        // 因為此時應該在子訂單上操作，而非父訂單
        // 執行訂單出貨
        const shipOrder = async (order) => {
            // 轉備貨：將訂單狀態更新為 'preparing'
            showConfirm(
                '確認轉備貨',
                `確定要將訂單 #${order.invoice_no || order.id} 轉為備貨狀態嗎？`,
                async () => {
                    shipping.value = true;

                    try {
                        const response = await fetch(`/wp-json/buygo-plus-one/v1/orders/${order.id}/prepare`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-WP-Nonce': wpNonce
                            },
                            credentials: 'include'
                        });

                        const result = await response.json();

                        if (result.success) {
                            showToast('已轉為備貨狀態', 'success');
                            // 刷新列表
                            await loadOrders();
                        } else {
                            showToast('轉備貨失敗：' + result.message, 'error');
                        }
                    } catch (err) {
                        console.error('轉備貨失敗:', err);
                        showToast('轉備貨失敗：' + err.message, 'error');

                        // 記錄到除錯中心
                        fetch('/wp-json/buygo-plus-one/v1/debug/log', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': wpNonce },
                            credentials: 'include',
                            body: JSON.stringify({
                                module: 'Orders',
                                message: '轉備貨失敗',
                                level: 'error',
                                data: { error: err.message, order_id: order.id }
                            })
                        }).catch(console.error);
                    } finally {
                        shipping.value = false;
                    }
                }
            );
        };
        
        // 執行出貨
        const shipOrderItem = (item) => {
            showConfirm(
                '確認出貨',
                `確定要出貨 ${item.allocated_quantity} 個「${item.product_name}」嗎？`,
                async () => {
                    shipping.value = true;
                    
                    try {
                        const response = await fetch(`/wp-json/buygo-plus-one/v1/orders/${item.order_id}/ship`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-WP-Nonce': wpNonce
                            },
                            credentials: 'include',
                            body: JSON.stringify({
                                items: [{
                                    order_item_id: item.id,
                                    quantity: item.allocated_quantity,
                                    product_id: item.product_id
                                }]
                            })
                        });
                        
                        const result = await response.json();
                        
                        if (result.success) {
                            showToast(`出貨成功！出貨單號：SH-${result.shipment_id}`, 'success');
                            // 重新載入訂單詳情
                            await loadOrderDetail(item.order_id);
                        } else {
                            showToast('出貨失敗：' + result.message, 'error');
                        }
                    } catch (err) {
                        console.error('出貨失敗:', err);
                        showToast('出貨失敗：' + err.message, 'error');
                
                        // 記錄到除錯中心
                        fetch('/wp-json/buygo-plus-one/v1/debug/log', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': wpNonce },
                            credentials: 'include',
                            body: JSON.stringify({
                                module: 'Orders',
                                message: '訂單商品出貨失敗',
                                level: 'error',
                                data: { error: err.message, order_id: item.order_id, item_id: item.id }
                            })
                        }).catch(console.error);
                    } finally {
                        shipping.value = false;
                    }
                }
            );
        };

        // 執行子訂單轉備貨（不是直接出貨）
        const shipChildOrder = async (childOrder, parentOrder) => {
            // 確認轉備貨
            showConfirm(
                '確認轉備貨',
                `確定要將指定單 #${childOrder.invoice_no} 轉為備貨狀態嗎？`,
                async () => {
                    shipping.value = true;

                    try {
                        // 呼叫 /prepare 端點，將狀態改為 'preparing'
                        const response = await fetch(`/wp-json/buygo-plus-one/v1/orders/${childOrder.id}/prepare`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-WP-Nonce': wpNonce
                            },
                            credentials: 'include'
                        });

                        const result = await response.json();

                        if (result.success) {
                            showToast('已轉為備貨狀態', 'success');
                            // 刷新列表
                            await loadOrders();
                        } else {
                            showToast('轉備貨失敗：' + result.message, 'error');
                        }
                    } catch (err) {
                        console.error('轉備貨失敗:', err);
                        showToast('轉備貨失敗：' + err.message, 'error');

                        // 記錄到除錯中心
                        fetch('/wp-json/buygo-plus-one/v1/debug/log', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': wpNonce },
                            credentials: 'include',
                            body: JSON.stringify({
                                module: 'Orders',
                                message: '子訂單轉備貨失敗',
                                level: 'error',
                                data: { error: err.message, order_id: childOrder.id }
                            })
                        });
                    } finally {
                        shipping.value = false;
                    }
                }
            );
        };

        // 載入訂單詳情
        const loadOrderDetail = async (orderId) => {
            try {
                const response = await fetch(`/wp-json/buygo-plus-one/v1/orders?id=${orderId}`, {
                    credentials: 'include',
                    headers: { 'X-WP-Nonce': wpNonce }
                });

                const result = await response.json();

                if (result.success && result.data && result.data.length > 0) {
                    currentOrder.value = result.data[0];
                }
            } catch (err) {
                console.error('載入訂單詳情失敗:', err);
            }
        };
        

        // 是否全選（用於 checkbox 狀態）
        const isAllSelected = computed(() => {
            const visibleOrders = filteredOrders.value;
            if (visibleOrders.length === 0) return false;
            return visibleOrders.every(order => selectedItems.value.includes(order.id));
        });

        // 全選/取消全選
        const toggleSelectAll = (event) => {
            const visibleOrders = filteredOrders.value;
            if (event.target.checked) {
                // 選取當前篩選後的所有訂單
                const visibleIds = visibleOrders.map(o => o.id);
                selectedItems.value = [...new Set([...selectedItems.value, ...visibleIds])];
            } else {
                // 取消選取當前篩選後的所有訂單
                const visibleIds = new Set(visibleOrders.map(o => o.id));
                selectedItems.value = selectedItems.value.filter(id => !visibleIds.has(id));
            }
        };
        
        // 分頁（使用篩選後的總數而非 API 返回的總數）
        const totalPages = computed(() => {
            if (perPage.value === -1) return 1;
            // 【修復 2026-01-31】使用 allFilteredOrders 的長度計算總頁數
            // 這樣分頁才會正確反映當前篩選條件下的結果數量
            return Math.ceil(allFilteredOrders.value.length / perPage.value);
        });
        
        // 可見的頁碼（最多顯示 5 頁）
        const visiblePages = computed(() => {
            const pages = [];
            const total = totalPages.value;
            const current = currentPage.value;
            
            if (total <= 5) {
                for (let i = 1; i <= total; i++) {
                    pages.push(i);
                }
            } else {
                if (current <= 3) {
                    pages.push(1, 2, 3, 4, 5);
                } else if (current >= total - 2) {
                    for (let i = total - 4; i <= total; i++) {
                        pages.push(i);
                    }
                } else {
                    for (let i = current - 2; i <= current + 2; i++) {
                        pages.push(i);
                    }
                }
            }
            
            return pages;
        });
        
        const previousPage = () => {
            if (currentPage.value > 1) {
                currentPage.value--;
                loadOrders();
            }
        };
        
        const nextPage = () => {
            if (currentPage.value < totalPages.value) {
                currentPage.value++;
                loadOrders();
            }
        };
        
        const goToPage = (page) => {
            currentPage.value = page;
            loadOrders();
        };
        
        const changePerPage = () => {
            currentPage.value = 1;
            loadOrders();
        };
        
        // 監聽篩選狀態變化
        // 【修復 2026-01-31】切換分頁時不需要重新載入資料
        // 因為前端已經有所有資料，只需要重置到第一頁即可
        // Vue computed 會自動重新計算 filteredOrders
        watch(filterStatus, () => {
            currentPage.value = 1; // 重置到第一頁
            // 不再呼叫 loadOrders()，前端直接篩選
        });

        // 使用預注入資料初始化（消除 Loading）
        const initFromPreloadedData = () => {
            const preloaded = window.buygoInitialData?.orders;
            if (!preloaded || !preloaded.success || !preloaded.data) return false;

            orders.value = preloaded.data.map(order => ({
                ...order,
                has_allocation: order.items && Array.isArray(order.items) && order.items.some(item => {
                    const allocatedQty = item.allocated_quantity != null
                        ? parseInt(item.allocated_quantity, 10)
                        : 0;
                    return !isNaN(allocatedQty) && isFinite(allocatedQty) && allocatedQty > 0;
                }),
                items: order.items || []
            }));
            totalOrders.value = preloaded.total || preloaded.data.length;
            updateStats(preloaded.stats);
            orders.value.forEach(order => {
                if (order.children && order.children.length > 0) {
                    collapsedChildren.value.add(order.id);
                }
            });
            loading.value = false;
            // 寫入快取，讓 preload 失敗時有 fallback
            if (window.BuyGoCache) { window.BuyGoCache.set('orders', preloaded); }
            // 清除預注入資料，避免重複使用
            delete window.buygoInitialData?.orders;
            return true;
        };

        // ============================================
        // 具名 Event Handler（供 onMounted/onUnmounted 配對使用）
        // ============================================
        const handleDocClickOrders = () => {
            if (openStatusDropdown.value !== null) {
                openStatusDropdown.value = null;
            }
        };
        const handleStorageChange = (e) => {
            if (e.key === 'buygo_allocation_updated' && e.newValue) {
                loadOrders();
                localStorage.removeItem('buygo_allocation_updated');
            }
        };
        const handlePageshowOrders = (e) => {
            if (e.persisted) {
                loadOrders();
            }
        };
        const handleVisibilityChangeOrders = () => {
            if (document.visibilityState === 'visible') {
                if (window.BuyGoCache && window.BuyGoCache.isFresh && window.BuyGoCache.isFresh('orders')) {
                    return;
                }
                loadOrders();
                localStorage.removeItem('buygo_allocation_updated');
            }
        };
        let removePopstateListenerOrders = null;

        // 初始化
        onMounted(() => {
            // 優先使用預注入資料，失敗則 fallback 到 API
            if (!initFromPreloadedData()) {
                // 快取 fallback：使用 sessionStorage 快取加速重複訪問
                const cached = window.BuyGoCache && window.BuyGoCache.get('orders');
                if (cached && cached.success && cached.data) {
                    orders.value = cached.data.map(order => ({
                        ...order,
                        has_allocation: order.items && Array.isArray(order.items) && order.items.some(item => {
                            const allocatedQty = item.allocated_quantity != null
                                ? parseInt(item.allocated_quantity, 10)
                                : 0;
                            return !isNaN(allocatedQty) && isFinite(allocatedQty) && allocatedQty > 0;
                        }),
                        items: order.items || []
                    }));
                    totalOrders.value = cached.total || cached.data.length;
                    updateStats(cached.stats);
                    orders.value.forEach(order => {
                        if (order.children && order.children.length > 0) {
                            collapsedChildren.value.add(order.id);
                        }
                    });
                    loading.value = false;
                    // 背景靜默刷新（silent 模式：不顯示 loading skeleton）
                    if (!window.BuyGoCache.isFresh('orders')) {
                        loadOrders({ silent: true });
                    }
                } else {
                    loadOrders();
                }
            }
            // 檢查 URL 參數（使用 BuyGoRouter 核心模組）
            checkUrlParams();
            // 監聽瀏覽器上一頁/下一頁（儲存 cleanup 函式）
            removePopstateListenerOrders = window.BuyGoRouter.setupPopstateListener(checkUrlParams);

            // 點擊外部關閉狀態下拉選單
            document.addEventListener('click', handleDocClickOrders);

            // 監聽商品分配更新事件（同步執行出貨按鈕狀態）
            window.addEventListener('storage', handleStorageChange);

            // 監聯頁面顯示事件（處理 bfcache 和頁面切換）
            window.addEventListener('pageshow', handlePageshowOrders);

            // 監聽頁面可見性變化（從其他標籤頁切換回來）
            // SWR 策略：快取新鮮時不重新載入，避免切分頁回來時 Loading 閃爍
            document.addEventListener('visibilitychange', handleVisibilityChangeOrders);
        });

        // SPA 清理：移除所有 event listener，防止記憶體洩漏
        onUnmounted(() => {
            if (removePopstateListenerOrders) removePopstateListenerOrders();
            document.removeEventListener('click', handleDocClickOrders);
            window.removeEventListener('storage', handleStorageChange);
            window.removeEventListener('pageshow', handlePageshowOrders);
            document.removeEventListener('visibilitychange', handleVisibilityChangeOrders);
        });

        // Smart Search Box 事件處理器
        const handleOrderSelect = (order) => {
            if (order && order.id) {
                openOrderDetail(order.id);
            }
        };

        // 本地搜尋處理函數(輸入時過濾列表)
        const handleOrderSearch = (query) => {
            searchQuery.value = query;
            currentPage.value = 1;  // 重置到第一頁
            loadOrders();
        };

        // 清除搜尋
        const handleOrderSearchClear = () => {
            searchQuery.value = '';
            searchFilter.value = null;
            searchFilterName.value = '';
            currentPage.value = 1;
            loadOrders();
        };

        // 幣別切換處理（Header 元件會呼叫此方法）
        const onCurrencyChange = (newCurrency) => {
            console.log('[OrdersPage] 幣別變更:', newCurrency);
            currentCurrency.value = newCurrency;
        };

        return {
            orders,
            loading,
            error,
            currentPage,
            perPage,
            totalOrders,
            totalPages,
            visiblePages,
            previousPage,
            nextPage,
            goToPage,
            changePerPage,
            formatPrice,
            formatDate,
            getStatusClass,
            getStatusText,
            viewOrderDetails,
            closeOrderModal,
            hasAllocatedItems,
            canShowShipButton,
            shipOrder,
            shipOrderItem,
            shipChildOrder,
            loadOrderDetail,
            shipping,
            handleSearchSelect,
            handleSearchInput,
            handleSearchClear,
            toggleSelectAll,
            isAllSelected,
            selectedItems,
            searchFilter,
            searchFilterName,
            searchQuery,
            systemCurrency,
            currentCurrency,
            showOrderModal,
            currentOrder,
            loadOrders,
            showModal,
            selectedOrderId,
            openOrderDetail,
            closeOrderDetail,
            formatItemsDisplay,
            toggleOrderExpand,
            isOrderExpanded,
            expandedOrders,
            toggleChildrenCollapse,
            isChildrenCollapsed,
            collapsedChildren,
            // 路由狀態（URL 驅動）
            currentView,
            currentOrderId,
            navigateTo,
            checkUrlParams,
            // 確認 Modal 和 Toast
            confirmModal,
            showConfirm,
            closeConfirmModal,
            handleConfirm,
            toastMessage,
            showToast,
            // UI 狀態
            showMobileSearch,
            // 新增方法
            batchPrepare,
            batchProcessing,
            batchDelete,
            toggleCurrency,
            // Smart Search Box
            handleOrderSelect,
            handleOrderSearch,
            handleOrderSearchClear,
            // 幣別切換
            onCurrencyChange,
            // 運送狀態相關
            shippingStatuses,
            toggleStatusDropdown,
            isStatusDropdownOpen,
            updateShippingStatus,
            openStatusDropdown,
            dropdownPosition,
            // 狀態篩選相關
            filterStatus,
            stats,
            filteredOrders,
            getFilteredChildren,
            getStatusCategory,
            tabCounts,
            // API 認證
            wpNonce
        };
}
;
/* === includes/views/composables/useProducts.js === */
/**
 * useProducts Composable
 * 商品管理頁面的資料邏輯層
 *
 * 從 ProductsPage.js 的 setup() 提取而來
 * Dependencies: Vue 3, BuyGoRouter, useCurrency, BuyGoSmartSearchBox, BuyGoCache
 */
function useProducts() {
        const { ref, computed, watch, onMounted, onUnmounted } = Vue;

        // WordPress REST API nonce（用於 API 認證）
        const wpNonce = window.buygoWpNonce || '';

        // 使用 useCurrency Composable 處理幣別邏輯
        const {
            formatPrice,
            convertCurrency,
            getCurrencySymbol,
            systemCurrency,
            currencySymbols,
            exchangeRates
        } = useCurrency();
        
        // --- Router & UI State ---
        const isSidebarCollapsed = ref(false);
        const showMobileMenu = ref(false);
        const showMobileSearch = ref(false);
        const currentTab = ref('products');
        const currentView = ref('list'); // 'list', 'edit', 'allocation', 'buyers'
        const currentId = ref(null);
        const viewMode = ref('table'); // 'table' or 'grid' - 商品列表顯示模式
        
        // --- Data Refs ---
        const products = ref([]);
        const selectedItems = ref([]);
        const loading = ref(true);
        const error = ref(null);
        const globalSearchQuery = ref('');

        // --- Seller Limit State (Phase 19) ---
        const sellerLimit = ref({ can_add: true, current: 0, limit: 0, message: '' });
        
        // --- Sub-page Data ---
        const editingProduct = ref({ id: '', name: '', price: 0, status: 'published', purchased: 0 }); // Initialize with defaults
        const selectedProduct = ref(null);
        
        // Buyers
        const buyers = ref([]);
        const buyersLoading = ref(false);
        const buyersProduct = ref(null);  // 商品資訊（名稱、圖片）
        const allocatingOrderItemId = ref(null);  // 改用 order_item_id
        const editingAllocationKey = ref(null);    // 正在 inline 編輯的唯一鍵（order_item_id）
        const editingAllocationQty = ref(0);       // inline 編輯中的輸入數量
        const buyersSearch = ref('');  // 搜尋客戶名稱
        const buyersCurrentPage = ref(1);
        const buyersVariants = ref([]);        // API 回傳的 variants 陣列（多樣式商品）
        const buyersSelectedVariant = ref(''); // 選中的 variant object_id（'' = 全部）
        const buyersPerPage = ref(3);
        const buyersPerPageOptions = [
            { value: 3, label: '3 / 頁' },
            { value: 5, label: '5 / 頁' },
            { value: 20, label: '20 / 頁' },
            { value: 50, label: '50 / 頁' }
        ];

        // 依 variant 篩選後的下單名單（第一層篩選）
        const filteredBuyersByVariant = computed(() => {
            if (!buyersSelectedVariant.value) return buyers.value;
            const varId = parseInt(buyersSelectedVariant.value);
            return buyers.value.filter(order => order.object_id === varId);
        });

        // 過濾後的下單名單（根據搜尋關鍵字，在 variant 篩選之後）
        const filteredBuyers = computed(() => {
            if (!buyersSearch.value.trim()) {
                return filteredBuyersByVariant.value;
            }
            const keyword = buyersSearch.value.toLowerCase().trim();
            return filteredBuyersByVariant.value.filter(order => {
                const customerName = String(order.customer_name || '').toLowerCase();
                const invoiceNo = String(order.invoice_no || '').toLowerCase();
                return customerName.includes(keyword) || invoiceNo.includes(keyword);
            });
        });

        // 分頁後的下單名單
        const paginatedBuyers = computed(() => {
            const start = (buyersCurrentPage.value - 1) * buyersPerPage.value;
            const end = start + buyersPerPage.value;
            return filteredBuyers.value.slice(start, end);
        });

        // 下單名單總頁數
        const buyersTotalPages = computed(() => {
            return Math.ceil(filteredBuyers.value.length / buyersPerPage.value) || 1;
        });

        // 下單名單分頁資訊
        const buyersStartIndex = computed(() => {
            if (filteredBuyers.value.length === 0) return 0;
            return (buyersCurrentPage.value - 1) * buyersPerPage.value + 1;
        });

        const buyersEndIndex = computed(() => {
            return Math.min(buyersCurrentPage.value * buyersPerPage.value, filteredBuyers.value.length);
        });

        // 下單名單可見頁碼
        const buyersVisiblePages = computed(() => {
            const pages = [];
            const maxPages = Math.min(5, buyersTotalPages.value);
            let startPage = Math.max(1, buyersCurrentPage.value - Math.floor(maxPages / 2));
            let endPage = startPage + maxPages - 1;

            if (endPage > buyersTotalPages.value) {
                endPage = buyersTotalPages.value;
                startPage = Math.max(1, endPage - maxPages + 1);
            }

            for (let i = startPage; i <= endPage; i++) {
                pages.push(i);
            }
            return pages;
        });

        // 下單名單分頁方法
        const buyersGoToPage = (page) => {
            if (page < 1 || page > buyersTotalPages.value) return;
            buyersCurrentPage.value = page;
        };

        const buyersHandlePerPageChange = () => {
            buyersCurrentPage.value = 1;
        };

        // 監聽搜尋變化，重置分頁
        watch(buyersSearch, () => {
            buyersCurrentPage.value = 1;
        });

        // 監聽 variant 篩選變化，重置分頁
        watch(buyersSelectedVariant, () => {
            buyersCurrentPage.value = 1;
        });

        // 跳轉到訂單詳情頁
        const goToOrderDetail = (orderId) => {
            window.location.href = `/buygo-portal/orders/?view=detail&id=${orderId}`;
        };

        // 統計摘要（依 variant 篩選後計算）
        const buyersSummary = computed(() => {
            const summary = {
                totalQuantity: 0,
                totalAllocated: 0,
                totalPending: 0,
                totalShipped: 0
            };
            filteredBuyersByVariant.value.forEach(order => {
                summary.totalQuantity += order.quantity || 0;
                summary.totalAllocated += order.allocated_quantity || 0;
                summary.totalPending += order.pending_quantity || 0;
                summary.totalShipped += order.shipped_quantity || 0;
            });
            return summary;
        });

        // Allocation
        const productOrders = ref([]);
        const allocationLoading = ref(false);
        const allocationSearch = ref('');
        const allocationSelectedVariant = ref(''); // 選中的 variant object_id（'' = 全部）

        // 從訂單列表提取 variant 選項（多樣式商品）
        const allocationVariants = computed(() => {
            const variantMap = {};
            productOrders.value.forEach(order => {
                const objId = String(order.object_id || '');
                const title = order.variation_title || '';
                if (objId && title) {
                    if (!variantMap[objId]) {
                        variantMap[objId] = { id: objId, title: title, order_count: 0 };
                    }
                    variantMap[objId].order_count++;
                }
            });
            const variants = Object.values(variantMap);
            return variants.length > 1 ? variants : [];
        });

        // 先按 variant 過濾，再按搜尋關鍵字過濾
        const filteredProductOrdersByVariant = computed(() => {
            if (!allocationSelectedVariant.value) return productOrders.value;
            const varId = parseInt(allocationSelectedVariant.value);
            return productOrders.value.filter(order => parseInt(order.object_id) === varId);
        });

        const filteredProductOrders = computed(() => {
            if (!allocationSearch.value.trim()) {
                return filteredProductOrdersByVariant.value;
            }
            const keyword = allocationSearch.value.toLowerCase().trim();
            return filteredProductOrdersByVariant.value.filter(order => {
                const orderId = String(order.order_id || '').toLowerCase();
                const customer = String(order.customer || '').toLowerCase();
                return orderId.includes(keyword) || customer.includes(keyword);
            });
        });

        // 總分配數量（用於顯示浮動按鈕）
        const totalAllocation = computed(() => {
            return productOrders.value.reduce((acc, o) => acc + (o.allocated || 0), 0);
        });

        // 分配頁面分頁
        const allocationCurrentPage = ref(1);
        const allocationPerPage = ref(3);
        const allocationPerPageOptions = [
            { value: 3, label: '3 / 頁' },
            { value: 5, label: '5 / 頁' },
            { value: 20, label: '20 / 頁' },
            { value: 50, label: '50 / 頁' }
        ];

        // 分頁後的分配訂單列表
        const paginatedProductOrders = computed(() => {
            const start = (allocationCurrentPage.value - 1) * allocationPerPage.value;
            const end = start + allocationPerPage.value;
            return filteredProductOrders.value.slice(start, end);
        });

        // 分配頁面總頁數
        const allocationTotalPages = computed(() => {
            return Math.ceil(filteredProductOrders.value.length / allocationPerPage.value) || 1;
        });

        // 分配頁面分頁資訊
        const allocationStartIndex = computed(() => {
            if (filteredProductOrders.value.length === 0) return 0;
            return (allocationCurrentPage.value - 1) * allocationPerPage.value + 1;
        });

        const allocationEndIndex = computed(() => {
            return Math.min(allocationCurrentPage.value * allocationPerPage.value, filteredProductOrders.value.length);
        });

        // 分配頁面可見頁碼
        const allocationVisiblePages = computed(() => {
            const pages = [];
            const maxPages = Math.min(5, allocationTotalPages.value);
            let startPage = Math.max(1, allocationCurrentPage.value - Math.floor(maxPages / 2));
            let endPage = startPage + maxPages - 1;

            if (endPage > allocationTotalPages.value) {
                endPage = allocationTotalPages.value;
                startPage = Math.max(1, endPage - maxPages + 1);
            }

            for (let i = startPage; i <= endPage; i++) {
                pages.push(i);
            }
            return pages;
        });

        // 分配頁面分頁方法
        const allocationGoToPage = (page) => {
            if (page < 1 || page > allocationTotalPages.value) return;
            allocationCurrentPage.value = page;
        };

        const allocationHandlePerPageChange = () => {
            allocationCurrentPage.value = 1;
        };

        // 監聽分配搜尋或 variant 切換，重置分頁
        watch([allocationSearch, allocationSelectedVariant], () => {
            allocationCurrentPage.value = 1;
        });

        // Image Modal
        const showImageModal = ref(false);
        const currentImage = ref(null);
        const imageError = ref(null);
        const imageUploading = ref(false); // 圖片上傳中狀態
        const notification = ref(null);
        const fileInput = ref(null);
        const currentProduct = ref(null); // Ensure this is defined once

        // Ensure editingProduct has default structure
        // const editingProduct = ref(...); // Already defined above

        // --- 成本與來源自訂欄位（Phase 49）---
        const editTab = ref('basic'); // 'basic' | 'cost'
        const customFields = ref({
            cost_price: '',
            original_price: '',
            purchase_location: '',
            supplier: '',
            barcode: '',
            manufacturing_notes: ''
        });
        const customFieldsLoading = ref(false);
        const customFieldsSaving = ref(false);

        // Toast
        const toastMessage = ref({ show: false, message: '', type: 'success' });
        
        // Pagination
        const currentPage = ref(1);
        const perPage = ref(5);
        const totalProducts = ref(0);

        // 當前顯示幣別（監聽全域幣別變化）
        const currentCurrency = ref(systemCurrency.value);

        // 監聽全域幣別變化
        watch(systemCurrency, (newCurrency) => {
            console.log('[ProductsPage] 偵測到幣別變化:', newCurrency);
            currentCurrency.value = newCurrency;
        });

        // --- Router Logic (使用 BuyGoRouter 核心模組) ---
        const checkUrlParams = async () => {
            const params = window.BuyGoRouter.checkUrlParams();
            const { view, id } = params;

            if (view && view !== 'list' && id) {
                // 先嘗試在已載入的列表中找
                let product = products.value.find(p => p.id == id);

                // 如果列表中沒有，透過 API 取得單一商品
                if (!product) {
                    try {
                        const res = await fetch(`/wp-json/buygo-plus-one/v1/products?id=${id}`, {
                            credentials: 'include',
                            headers: { 'X-WP-Nonce': wpNonce }
                        });
                        const data = await res.json();
                        if (data.success && data.data && data.data.length > 0) {
                            product = data.data[0];
                        }
                    } catch (e) {
                        console.error('Failed to fetch product:', e);
                    }
                }

                if (product) {
                    handleNavigation(view, product, false);
                } else if (!loading.value) {
                    handleNavigation('list', null, false);
                }
            } else {
                currentView.value = 'list';
            }
        };

        const navigateTo = async (view, product = null, updateUrl = true) => {
            await handleNavigation(view, product, updateUrl);
        };

        // 批量上架入口（Phase 57 ROUTE-01）
        const goToBatchCreate = () => {
            if (window.BuyGoRouter && window.BuyGoRouter.spaNavigate) {
                window.BuyGoRouter.spaNavigate('batch-create');
            }
        };

        const handleNavigation = async (view, product = null, updateUrl = true) => {
            // 離開編輯頁時重置 Tab 和自訂欄位（Phase 49）
            if (view !== 'edit') {
                editTab.value = 'basic';
                customFields.value = {
                    cost_price: '', original_price: '', purchase_location: '',
                    supplier: '', barcode: '', manufacturing_notes: ''
                };
            }

            currentView.value = view;

            if (product) {
                currentId.value = product.id;
                selectedProduct.value = product;

                if (updateUrl) {
                    window.BuyGoRouter.navigateTo(view, product.id);
                }

                // Load Data for Sub-pages
                if (view === 'edit') {
                    // 多樣式商品：初始化 variant 編輯資訊（一次性賦值確保響應式）
                    if (product.has_variations && product.variations?.length > 0) {
                        const selectedVar = product.selected_variation || product.variations[0];
                        editingProduct.value = {
                            ...product,
                            editing_variation_id: String(selectedVar.id),
                            editing_variation_title: selectedVar.variation_title || '',
                            editing_variation_price: selectedVar.price || 0,
                            editing_variation_purchased: 0,
                            editing_variation_stock: selectedVar.available > 0 ? selectedVar.available : ''
                        };
                        // 從 API 取得選中 variation 的真實採購數量
                        try {
                            const res = await fetch(`/wp-json/buygo-plus-one/v1/variations/${selectedVar.id}/stats?_t=${Date.now()}`, {
                                cache: 'no-store',
                                credentials: 'include',
                                headers: { 'Cache-Control': 'no-cache', 'Pragma': 'no-cache', 'X-WP-Nonce': wpNonce }
                            });
                            const data = await res.json();
                            if (data.success) {
                                editingProduct.value.editing_variation_purchased = data.data.purchased || 0;
                            }
                            // 庫存從 variation 本身的 available 欄位取得
                            editingProduct.value.editing_variation_stock = selectedVar.available > 0 ? selectedVar.available : '';
                        } catch (e) {
                            console.error('載入 Variation 採購數量失敗:', e);
                        }
                    } else {
                        editingProduct.value = { ...product, stock: product.stock > 0 ? product.stock : '' };
                    }
                    // 載入自訂欄位（Phase 49）— 多樣式用 variation ID
                    if (product.has_variations && product.variations?.length > 0) {
                        const varId = editingProduct.value.editing_variation_id;
                        loadCustomFields(varId);
                    } else {
                        loadCustomFields(product.id);
                    }
                } else if (view === 'allocation') {
                    await loadProductOrders(product.id);
                } else if (view === 'buyers') {
                    await loadBuyers(product.id);
                }
            } else {
                currentId.value = null;
                selectedProduct.value = null;
                if (updateUrl) {
                    window.BuyGoRouter.goToList();
                }
            }
        };

        const getSubPageTitle = computed(() => {
            if (currentView.value === 'edit') return '編輯商品';
            if (currentView.value === 'allocation') return '庫存分配';
            if (currentView.value === 'buyers') return '下單名單';
            return '';
        });
        
        const isAllSelected = computed(() => {
            return products.value.length > 0 && selectedItems.value.length === products.value.length;
        });

        // 訂單狀態樣式
        const getStatusClass = (status) => {
            const classes = {
                'pending': 'bg-amber-100 text-amber-700',
                'partial': 'bg-blue-100 text-blue-700',
                'allocated': 'bg-green-100 text-green-700',
                'shipped': 'bg-slate-100 text-slate-600'
            };
            return classes[status] || 'bg-slate-100 text-slate-600';
        };

        // 訂單狀態文字
        const getStatusText = (status) => {
            const texts = {
                'pending': '待分配',
                'partial': '部分處理',
                'allocated': '已分配',
                'shipped': '已出貨'
            };
            return texts[status] || '未知';
        };

        // --- Seller Limit Check (Phase 19) ---
        const checkSellerLimit = async () => {
            try {
                const res = await fetch('/wp-json/buygo-plus-one/v1/products/limit-check', {
                    credentials: 'include',
                    headers: {
                        'X-WP-Nonce': wpNonce
                    }
                });
                const data = await res.json();
                if (data.success) {
                    sellerLimit.value = data.data;
                }
            } catch (e) {
                console.error('檢查賣家限制失敗:', e);
            }
        };

        // --- API Methods ---
        const loadProducts = async (options = {}) => {
            // silent 模式：背景刷新時不顯示 loading skeleton，避免切頁閃爍
            if (!options.silent) loading.value = true;
            try {
                // 使用 BuyGoCache 快取層，不再強制繞過瀏覽器快取
                let url = `/wp-json/buygo-plus-one/v1/products?page=${currentPage.value}&per_page=${perPage.value}`;
                if (globalSearchQuery.value) {
                    url += `&search=${encodeURIComponent(globalSearchQuery.value)}`;
                }
                const res = await fetch(url, {
                    credentials: 'include',
                    headers: {
                        'X-WP-Nonce': wpNonce
                    }
                });
                const data = await res.json();
                if (data.success) {
                    // 初始化 Variation 顯示邏輯
                    products.value = data.data
                        .filter(product => product !== null && product !== undefined)
                        .map(product => {
                            // 如果是多樣式商品，設定預設選中的 variation
                            if (product.has_variations && product.default_variation) {
                                product.selected_variation_id = product.default_variation.id;
                                product.selected_variation = product.default_variation;
                            }
                            return product;
                        });
                    // 【修復】使用 API 回傳的總數，而非當前頁的商品數
                    totalProducts.value = data.total || products.value.length;
                    // 儲存到 BuyGoCache
                    if (window.BuyGoCache) { window.BuyGoCache.set('products', data); }

                    // 並行執行 URL 參數檢查和賣家限制檢查，減少載入時間
                    await Promise.all([
                        checkUrlParams(),
                        checkSellerLimit()
                    ]);
                } else {
                    products.value = [];
                    totalProducts.value = 0;
                    showToast(data.message || '載入失敗', 'error');
                }
            } catch (e) {
                error.value = e.message;
            } finally {
                loading.value = false;
            }
        };

        const loadBuyers = async (id) => {
            buyersLoading.value = true;
            buyersProduct.value = null;
            try {
                // 使用 BuyGoCache 快取層，不再強制繞過瀏覽器快取
                const res = await fetch(`/wp-json/buygo-plus-one/v1/products/${id}/buyers`, {
                    credentials: 'include',
                    headers: { 'X-WP-Nonce': wpNonce }
                });
                const data = await res.json();
                if (data.success) {
                    buyers.value = data.data;
                    // 儲存商品資訊
                    if (data.product) {
                        buyersProduct.value = data.product;
                    }
                    // 儲存 variants 資料（多樣式商品）
                    buyersVariants.value = Array.isArray(data.variants) ? data.variants : [];
                    // buyers 頁預設顯示全部 variant，不自動篩選
                    buyersSelectedVariant.value = '';
                    buyersCurrentPage.value = 1;
                }
            } catch(e) { console.error(e); }
            finally { buyersLoading.value = false; }
        };

        // 切換到下單名單檢視
        const viewBuyers = (product) => {
            handleNavigation('buyers', product, true);
        };

        // 一鍵分配：將單筆訂單分配
        const allocateOrder = async (order) => {
            if (!currentId.value || !order.order_item_id) return;

            allocatingOrderItemId.value = order.order_item_id;

            try {
                const res = await fetch(`/wp-json/buygo-plus-one/v1/products/${currentId.value}/allocate-all`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Cache-Control': 'no-cache',
                        'X-WP-Nonce': wpNonce
                    },
                    credentials: 'include',
                    body: JSON.stringify({
                        order_item_id: order.order_item_id
                    })
                });

                const data = await res.json();

                if (data.success) {
                    showToast(`已分配 ${data.total_allocated} 個商品給 ${order.customer_name}`, 'success');
                    // 標記該訂單為已分配
                    order.is_allocated = true;
                    order.allocated_quantity = order.quantity;
                    // 重新載入購買名單以更新狀態
                    await loadBuyers(currentId.value);
                    // 重新載入商品列表以更新已分配數量
                    await loadProducts();
                } else {
                    showToast(data.message || '分配失敗', 'error');
                }
            } catch (e) {
                console.error('一鍵分配錯誤:', e);
                showToast('分配時發生錯誤', 'error');
            } finally {
                allocatingOrderItemId.value = null;
            }
        };

        // 開啟 inline 分配編輯（點擊已分配數字時觸發）
        const startEditAllocation = (order) => {
            editingAllocationKey.value = order.order_item_id;
            editingAllocationQty.value = order.allocated_quantity || order.already_allocated || 0;
        };

        // 取消 inline 分配編輯
        const cancelEditAllocation = () => {
            editingAllocationKey.value = null;
            editingAllocationQty.value = 0;
        };

        // 確認調整分配：呼叫 POST /products/adjust-allocation API
        const confirmAdjustAllocation = async (order) => {
            if (!order) return;

            const newQty = parseInt(editingAllocationQty.value, 10);
            if (isNaN(newQty) || newQty < 0) {
                showToast('請輸入有效數量', 'error');
                return;
            }

            // 沒有變動則直接關閉
            const original = order.allocated_quantity || order.already_allocated || 0;
            if (newQty === original) {
                cancelEditAllocation();
                return;
            }

            // 輸入 0 時顯示二次確認
            if (newQty === 0) {
                if (!confirm('確定要撤銷此分配？')) return;
            }

            try {
                const res = await fetch('/wp-json/buygo-plus-one/v1/products/adjust-allocation', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': wpNonce
                    },
                    credentials: 'include',
                    body: JSON.stringify({
                        product_id: currentId.value,
                        order_id: order.order_id,
                        new_quantity: newQty
                    })
                });

                const data = await res.json();

                if (data.success) {
                    showToast('分配已調整', 'success');
                    cancelEditAllocation();
                    // 重新載入下單名單與商品列表以同步狀態
                    await loadBuyers(currentId.value);
                    await loadProducts();
                } else {
                    showToast(data.message || '調整失敗', 'error');
                }
            } catch (e) {
                console.error('調整分配錯誤:', e);
                showToast('調整時發生錯誤', 'error');
            }
        };

        // 日期格式化（後端已格式化，直接返回；若需解析則處理）
        const formatDate = (dateString) => {
            if (!dateString) return '';
            // 如果已經是 YYYY/MM/DD 格式，直接返回
            if (/^\d{4}\/\d{2}\/\d{2}$/.test(dateString)) {
                return dateString;
            }
            // 嘗試解析日期
            const date = new Date(dateString);
            if (isNaN(date.getTime())) return dateString; // 無法解析則原樣返回
            return `${date.getFullYear()}/${String(date.getMonth() + 1).padStart(2, '0')}/${String(date.getDate()).padStart(2, '0')}`;
        };

        // --- 自訂欄位 API 方法（Phase 49）---

        // 載入商品自訂欄位
        const loadCustomFields = async (productId) => {
            customFieldsLoading.value = true;
            try {
                const response = await fetch(`/wp-json/buygo-plus-one/v1/products/${productId}/custom-fields`, {
                    credentials: 'include',
                    headers: { 'X-WP-Nonce': wpNonce }
                });
                const result = await response.json();
                if (result.success && result.data) {
                    Object.assign(customFields.value, result.data);
                }
            } catch (e) {
                console.error('[Products] 載入自訂欄位失敗:', e);
            } finally {
                customFieldsLoading.value = false;
            }
        };

        // 儲存商品自訂欄位
        const saveCustomFields = async () => {
            const ep = editingProduct.value;
            // 多樣式商品用 variation ID，單一商品用主商品 ID
            const targetId = (ep.has_variations && ep.editing_variation_id) ? ep.editing_variation_id : ep.id;
            if (!targetId) return;
            customFieldsSaving.value = true;
            try {
                const response = await fetch(`/wp-json/buygo-plus-one/v1/products/${targetId}/custom-fields`, {
                    method: 'PUT',
                    credentials: 'include',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': wpNonce
                    },
                    body: JSON.stringify(customFields.value)
                });
                const result = await response.json();
                if (result.success) {
                    showToast('自訂欄位已儲存');
                } else {
                    showToast('儲存失敗', 'error');
                }
            } catch (e) {
                showToast('儲存失敗：' + e.message, 'error');
            } finally {
                customFieldsSaving.value = false;
            }
        };

        // 利潤計算（前端即時）
        const profit = computed(() => {
            const ep = editingProduct.value;
            // 多樣式商品用樣式價格，單一商品用主商品價格
            const price = ep.has_variations
                ? (parseFloat(ep.editing_variation_price) || 0)
                : (parseFloat(ep.price) || 0);
            const cost = parseFloat(customFields.value.cost_price) || 0;
            if (!cost) return null;
            return price - cost;
        });

        const profitMargin = computed(() => {
            const price = parseFloat(editingProduct.value.price) || 0;
            if (!price || profit.value === null) return null;
            return ((profit.value / price) * 100).toFixed(1);
        });

        const loadProductOrders = async (id) => {
            allocationLoading.value = true;
             try {
                // 使用 BuyGoCache 快取層，不再強制繞過瀏覽器快取
                const res = await fetch(`/wp-json/buygo-plus-one/v1/products/${id}/orders`, {
                    credentials: 'include',
                    headers: { 'X-WP-Nonce': wpNonce }
                });
                const data = await res.json();
                // Adapter for old API response structure if needed
                if (data.success) productOrders.value = data.data;
            } catch(e) { console.error(e); }
            finally { allocationLoading.value = false; }
        };

        const saveProduct = async () => {
            try {
                const ep = editingProduct.value;

                // 儲存主商品
                const saveBody = { ...ep };
                if (saveBody.stock === '' || saveBody.stock === null) delete saveBody.stock;
                const res = await fetch(`/wp-json/buygo-plus-one/v1/products/${ep.id}`, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': wpNonce },
                    credentials: 'include',
                    body: JSON.stringify(saveBody)
                });
                const data = await res.json();

                // 多樣式商品：同時儲存 variant 資料
                if (ep.has_variations && ep.editing_variation_id) {
                    const varRes = await fetch(`/wp-json/buygo-plus-one/v1/variations/${ep.editing_variation_id}`, {
                        method: 'PUT',
                        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': wpNonce },
                        credentials: 'include',
                        body: JSON.stringify({
                            purchased: ep.editing_variation_purchased,
                            variation_title: ep.editing_variation_title,
                            ...(ep.editing_variation_stock !== '' ? { stock: ep.editing_variation_stock } : {})
                        })
                    });
                    // 同步 variation 採購數量回列表的 product.purchased
                    if (varRes.ok) {
                        ep.purchased = ep.editing_variation_purchased;
                        // 同步 variation 陣列中的 title 和庫存
                        const v = ep.variations?.find(v => String(v.id) === String(ep.editing_variation_id));
                        if (v) {
                            v.variation_title = ep.editing_variation_title;
                            v.available = ep.editing_variation_stock;
                        }
                    }
                }

                if (data.success) {
                    // 同時儲存自訂欄位（成本價等）
                    await saveCustomFields();
                    const idx = products.value.findIndex(p => p.id === ep.id);
                    if (idx !== -1) products.value[idx] = { ...products.value[idx], ...ep };
                    showToast('儲存成功');
                    loadProducts(); // Refresh list
                    navigateTo('list');
                } else {
                    showToast(data.message || '儲存失敗', 'error');
                }
            } catch(e) { showToast('儲存失敗', 'error'); }
        };
        
        const savePurchased = async (product) => {
             // Reuse logic from saveProduct or dedicated endpoint
             try {
                // 如果是多樣式商品，更新選中的 variation 的採購數量
                if (product.has_variations && product.selected_variation_id) {
                    await fetch(`/wp-json/buygo-plus-one/v1/variations/${product.selected_variation_id}`, {
                        method: 'PUT',
                        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': wpNonce },
                        credentials: 'include',
                        body: JSON.stringify({ purchased: product.purchased })
                    });
                } else {
                    // 單一商品，更新商品本身的採購數量
                    await fetch(`/wp-json/buygo-plus-one/v1/products/${product.id}`, {
                        method: 'PUT',
                        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': wpNonce },
                        credentials: 'include',
                        body: JSON.stringify({ purchased: product.purchased })
                    });
                }
                showToast('已更新採購數量');
             } catch(e) { console.error(e); }
        };

        const toggleStatus = async (product) => {
            const newStatus = product.status === 'published' ? 'private' : 'published';
             try {
                await fetch(`/wp-json/buygo-plus-one/v1/products/${product.id}`, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': wpNonce },
                    credentials: 'include',
                    body: JSON.stringify({ status: newStatus })
                });
                product.status = newStatus;
             } catch(e) { console.error(e); }
        };

        const deleteProduct = async (id) => {
            if(!window.confirm('確定要刪除此商品嗎？此動作無法復原。')) return;
            try {
                const res = await fetch('/wp-json/buygo-plus-one/v1/products/batch-delete', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': wpNonce },
                    credentials: 'include',
                    body: JSON.stringify({ ids: [id] })
                });
                const data = await res.json();
                
                if (data.success) {
                     products.value = products.value.filter(p => p.id !== id);
                     showToast('已刪除');
                     loadProducts();
                } else {
                     showToast(data.message || '刪除失敗', 'error');
                }
            } catch(e) { console.error(e); showToast('刪除錯誤', 'error'); }
        };
        
        const batchDelete = async () => {
             if(!confirm(`確認刪除 ${selectedItems.value.length} 項？`)) return;
             // Implement batch delete API call
             products.value = products.value.filter(p => !selectedItems.value.includes(p.id));
             selectedItems.value = [];
             showToast('批次刪除成功');
        };

        // SubPage Save Handler
        const handleSubPageSave = async () => {
            if (currentView.value === 'edit') {
                saveProduct();
            } else if (currentView.value === 'allocation') {
                await handleAllocation();
            }
        };
        
        // 處理分配功能
        const handleAllocation = async () => {
            if (!selectedProduct.value) {
                showToast('請選擇商品', 'error');
                return;
            }

            // 準備分配資料
            // 【增量模式】只傳送「本次新分配」的數量，後端會建立新的子訂單
            const allocationData = productOrders.value
                .filter(order => order.allocated && order.allocated > 0)
                .map(order => ({
                    order_id: order.order_id,
                    order_item_id: order.order_item_id || order.id,
                    quantity: order.allocated  // 只傳本次新分配的數量
                }));
            
            if (allocationData.length === 0) {
                showToast('請至少分配一個訂單', 'error');
                return;
            }
            
            try {
                const res = await fetch('/wp-json/buygo-plus-one/v1/products/allocate', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': wpNonce
                    },
                    credentials: 'include',
                    body: JSON.stringify({
                        product_id: selectedProduct.value.id,
                        allocations: allocationData
                    })
                });
                
                const data = await res.json();
                
                if (data.success) {
                    showToast('分配成功', 'success');

                    // 計算總分配數量
                    const totalAllocated = allocationData.reduce((sum, alloc) => sum + alloc.quantity, 0);

                    // 立即更新本地商品資料的 allocated 欄位
                    const productIndex = products.value.findIndex(p => p.id === selectedProduct.value.id);
                    if (productIndex !== -1) {
                        products.value[productIndex].allocated = (products.value[productIndex].allocated || 0) + totalAllocated;
                    }

                    // 如果正在編輯的商品是同一個，也更新編輯中的商品
                    if (editingProduct.value && editingProduct.value.id === selectedProduct.value.id) {
                        editingProduct.value.allocated = (editingProduct.value.allocated || 0) + totalAllocated;
                    }

                    // 更新 selectedProduct
                    if (selectedProduct.value) {
                        selectedProduct.value.allocated = (selectedProduct.value.allocated || 0) + totalAllocated;
                    }

                    // 重新載入商品列表（確保資料同步）
                    await loadProducts();
                    // 重新載入訂單資料
                    await loadProductOrders(selectedProduct.value.id);

                    // 通知訂單頁面需要重新載入（用於同步執行出貨按鈕狀態）
                    localStorage.setItem('buygo_allocation_updated', Date.now().toString());

                    // 返回列表
                    navigateTo('list');
                } else {
                    showToast(data.message || '分配失敗', 'error');
                }
            } catch (e) {
                console.error('分配失敗:', e);
                showToast('分配失敗：' + e.message, 'error');
            }
        };
        
        // Image Handling
        const openImageModal = (p) => { currentProduct.value = p; currentImage.value = p.image; showImageModal.value = true; };
        const closeImageModal = () => { showImageModal.value = false; currentProduct.value = null; };
        const triggerFileInput = () => fileInput.value.click();
        const handleFileSelect = async (e) => {
            const file = e.target.files[0];
            if(file) {
                 imageUploading.value = true; // 開始上傳
                 imageError.value = null; // 清除錯誤
                 const formData = new FormData();
                 formData.append('image', file);
                 try {
                     const res = await fetch(`/wp-json/buygo-plus-one/v1/products/${currentProduct.value.id}/image`, {
                         method: 'POST',
                         headers: { 'X-WP-Nonce': wpNonce },
                         credentials: 'include',
                         body: formData
                     });
                     const data = await res.json();
                     if (data.success) {
                         currentImage.value = data.data.image_url;
                         currentProduct.value.image = data.data.image_url;
                         if (editingProduct.value && editingProduct.value.id === currentProduct.value.id) {
                             editingProduct.value.image = data.data.image_url;
                         }
                         showToast('圖片上傳成功');
                         // 上傳成功後自動關閉 Modal
                         setTimeout(() => closeImageModal(), 500);
                     } else {
                         imageError.value = data.message || '上傳失敗';
                     }
                 } catch(err) {
                    imageError.value = '上傳錯誤，請稍後再試';
                 } finally {
                    imageUploading.value = false; // 結束上傳
                    e.target.value = ''; // 清除 file input，允許重新選擇同一檔案
                 }
            }
        };

        // Helpers
        const toggleSelectAll = () => {
            if (isAllSelected.value) selectedItems.value = [];
            else selectedItems.value = products.value.map(p => p.id);
        };

        // 格式化價格（根據 currentCurrency 顯示）
        const formatPriceDisplay = (price, productCurrency = null) => {
            const safePrice = price ?? 0;
            const sourceCurrency = productCurrency || systemCurrency.value;

            // 如果當前顯示幣別與商品幣別相同,直接格式化
            if (currentCurrency.value === sourceCurrency) {
                return formatPrice(safePrice, sourceCurrency);
            }

            // 否則進行匯率轉換
            const convertedPrice = convertCurrency(safePrice, sourceCurrency, currentCurrency.value);
            return formatPrice(convertedPrice, currentCurrency.value);
        };

        // 計算台幣轉換價格（用於顯示參考價格）
        const getTWDPrice = (price, currency) => {
            const safePrice = price ?? 0;
            const rates = exchangeRates.value;
            const rate = rates[currency] || 1;
            return Math.round(safePrice * rate);
        };

        const calculateReserved = (p) => Math.max(0, (p.ordered || 0) - (p.purchased || 0));
        const showToast = (msg, type='success') => { toastMessage.value = { show: true, message: msg, type }; setTimeout(()=> toastMessage.value.show=false, 3000); };

        // 商品短連結
        const getProductLink = (productId) => window.location.origin + '/item/' + parseInt(productId, 10);
        const copyProductLink = async (productId) => {
            const url = getProductLink(productId);
            try {
                await navigator.clipboard.writeText(url);
                showToast('已複製商品連結');
            } catch {
                const ta = document.createElement('textarea');
                ta.value = url;
                ta.style.cssText = 'position:fixed;left:-9999px;opacity:0';
                document.body.appendChild(ta);
                ta.select();
                document.execCommand('copy');
                document.body.removeChild(ta);
                showToast('已複製商品連結');
            }
        };

        // Variation 相關方法
        const getDisplayTitle = (product) => {
            if (!product) return '';
            return product.name || '';
        };

        const getDisplayPrice = (product) => {
            if (!product) return 0;
            if (product.has_variations && product.selected_variation) {
                return product.selected_variation.price;
            }
            return product.price;
        };

        // 取得顯示用的圖片 URL（優先顯示已選變體的圖片）
        const getDisplayImage = (product) => {
            if (!product) return null;
            if (product.has_variations && product.selected_variation && product.selected_variation.image) {
                return product.selected_variation.image;
            }
            return product.image;
        };

        const onVariationChange = async (product) => {
            if (!product.has_variations || !product.selected_variation_id) return;

            // 找到選中的 variation
            const variation = product.variations.find(v => v.id === product.selected_variation_id);
            if (!variation) return;

            product.selected_variation = variation;

            // 取得該 variation 的統計資料
            try {
                const res = await fetch(`/wp-json/buygo-plus-one/v1/variations/${variation.id}/stats?_t=${Date.now()}`, {
                    cache: 'no-store',
                    credentials: 'include',
                    headers: {
                        'Cache-Control': 'no-cache',
                        'Pragma': 'no-cache',
                        'X-WP-Nonce': wpNonce
                    }
                });
                const data = await res.json();
                if (data.success) {
                    // 更新商品的統計資料
                    product.ordered = data.data.ordered || 0;
                    product.allocated = data.data.allocated || 0;
                    product.shipped = data.data.shipped || 0;
                    product.purchased = data.data.purchased || 0;
                    product.pending = data.data.pending || 0;
                    product.reserved = data.data.reserved || 0;
                }
            } catch (e) {
                console.error('載入 Variation 統計失敗:', e);
            }
        };

        // 編輯頁面切換 Variation
        const onEditVariationChange = async () => {
            const ep = editingProduct.value;
            if (!ep.has_variations || !ep.editing_variation_id) return;

            const variation = ep.variations.find(v => String(v.id) === String(ep.editing_variation_id));
            if (!variation) return;

            ep.editing_variation_title = variation.variation_title || '';
            ep.editing_variation_price = variation.price || 0;
            ep.editing_variation_stock = variation.available > 0 ? variation.available : '';

            // 取得該 variation 的統計資料（含採購數量）
            try {
                const res = await fetch(`/wp-json/buygo-plus-one/v1/variations/${variation.id}/stats?_t=${Date.now()}`, {
                    cache: 'no-store',
                    credentials: 'include',
                    headers: { 'Cache-Control': 'no-cache', 'Pragma': 'no-cache', 'X-WP-Nonce': wpNonce }
                });
                const data = await res.json();
                if (data.success) {
                    ep.editing_variation_purchased = data.data.purchased || 0;
                }
            } catch (e) {
                console.error('載入 Variation 統計失敗:', e);
            }

            // 切換樣式時重新載入該樣式的自訂欄位（成本價等）
            loadCustomFields(variation.id);
        };

        // Smart Search Box 處理函數
        const handleProductSelect = (product) => {
            if (product && product.id) {
                // 導航到商品編輯頁面
                navigateTo('edit', product);
            }
        };

        // 本地搜尋處理函數(輸入時過濾列表)
        const handleProductSearch = (query) => {
            globalSearchQuery.value = query;
            currentPage.value = 1;  // 重置到第一頁
            loadProducts();
        };

        // 清除搜尋
        const handleProductSearchClear = () => {
            globalSearchQuery.value = '';
            currentPage.value = 1;
            loadProducts();
        };

        // 預注入資料初始化（消除 Loading 畫面）
        const initFromPreloadedData = () => {
            const preloaded = window.buygoInitialData?.products;
            if (!preloaded || !preloaded.success || !preloaded.data) return false;

            products.value = preloaded.data
                .filter(product => product !== null && product !== undefined)
                .map(product => {
                    if (product.has_variations && product.default_variation) {
                        product.selected_variation_id = product.default_variation.id;
                        product.selected_variation = product.default_variation;
                    }
                    return product;
                });
            totalProducts.value = preloaded.total || products.value.length;
            loading.value = false;
            // 寫入快取，讓 preload 失敗時有 fallback
            if (window.BuyGoCache) { window.BuyGoCache.set('products', preloaded); }
            delete window.buygoInitialData?.products;
            return true;
        };

        // ============================================
        // 具名 Event Handler（供 onMounted/onUnmounted 配對使用）
        // ============================================
        // 平板響應式視圖自動切換
        let userPreferredMode = 'table';
        let isAutoSwitched = false;

        const handlePageshowProducts = async (e) => {
            if (e.persisted) {
                await loadProducts();
                await checkUrlParams();
            }
        };
        const handleVisibilityChangeProducts = async () => {
            if (document.visibilityState === 'visible') {
                if (window.BuyGoCache && window.BuyGoCache.isFresh && window.BuyGoCache.isFresh('products')) {
                    return;
                }
                await loadProducts();
                await checkUrlParams();
            }
        };
        const handleViewModeByWidth = () => {
            const width = window.innerWidth;

            // 只在列表視圖時自動切換
            if (currentView.value !== 'list') return;

            // 平板直向（768px-1024px）→ 強制網格模式
            if (width >= 768 && width < 1024) {
                if (viewMode.value !== 'grid') {
                    isAutoSwitched = true;
                    viewMode.value = 'grid';
                }
            }
            // 桌面/平板橫向（>= 1024px）→ 恢復用戶偏好或預設表格模式
            else if (width >= 1024) {
                if (viewMode.value !== userPreferredMode) {
                    isAutoSwitched = true;
                    viewMode.value = userPreferredMode;
                }
            }
        };
        let removePopstateListenerProducts = null;

        onMounted(async () => {
            if (!initFromPreloadedData()) {
                // 快取 fallback：使用 sessionStorage 快取加速重複訪問
                const cached = window.BuyGoCache && window.BuyGoCache.get('products');
                if (cached && cached.success && cached.data) {
                    products.value = cached.data
                        .filter(product => product !== null && product !== undefined)
                        .map(product => {
                            if (product.has_variations && product.default_variation) {
                                product.selected_variation_id = product.default_variation.id;
                                product.selected_variation = product.default_variation;
                            }
                            return product;
                        });
                    totalProducts.value = cached.total || products.value.length;
                    loading.value = false;
                    // 背景靜默刷新（silent 模式：不顯示 loading skeleton）
                    if (!window.BuyGoCache.isFresh('products')) {
                        await loadProducts({ silent: true });
                    }
                } else {
                    await loadProducts();
                }
            }
            // 頁面載入後立即檢查 URL 參數
            await checkUrlParams();

            // 使用 BuyGoRouter 核心模組的 popstate 監聽（儲存 cleanup 函式）
            removePopstateListenerProducts = window.BuyGoRouter.setupPopstateListener(checkUrlParams);

            // 監聽頁面顯示事件（處理 bfcache 和頁面切換）
            window.addEventListener('pageshow', handlePageshowProducts);

            // 監聽頁面可見性變化（從其他標籤頁切換回來）
            // SWR 策略：快取新鮮時不重新載入，避免切分頁回來時 Loading 閃爍
            document.addEventListener('visibilitychange', handleVisibilityChangeProducts);

            // 初始化用戶偏好的視圖模式
            userPreferredMode = viewMode.value;

            // 監聽用戶手動切換視圖模式
            watch(viewMode, (newMode) => {
                if (!isAutoSwitched && window.innerWidth >= 1024) {
                    userPreferredMode = newMode;
                }
                isAutoSwitched = false;
            });

            // 初次檢查
            handleViewModeByWidth();

            // 監聽視窗尺寸變化
            window.addEventListener('resize', handleViewModeByWidth);
        });

        // SPA 清理：移除所有 event listener，防止記憶體洩漏
        onUnmounted(() => {
            if (removePopstateListenerProducts) removePopstateListenerProducts();
            window.removeEventListener('pageshow', handlePageshowProducts);
            document.removeEventListener('visibilitychange', handleVisibilityChangeProducts);
            window.removeEventListener('resize', handleViewModeByWidth);
        });

        // 幣別切換處理（Header 元件會呼叫此方法）
        const onCurrencyChange = (newCurrency) => {
            console.log('[ProductsPage] 幣別變更:', newCurrency);
            currentCurrency.value = newCurrency;
        };

        return {
            // State
            isSidebarCollapsed, showMobileMenu, showMobileSearch, currentTab, currentView, currentId, viewMode,
            products, selectedItems, loading, error, globalSearchQuery, sellerLimit,
            editingProduct, selectedProduct, buyers, buyersLoading, buyersProduct, buyersSummary, allocatingOrderItemId, productOrders, allocationLoading, allocationSearch, allocationSelectedVariant, allocationVariants, filteredProductOrders, totalAllocation,
            buyersVariants, buyersSelectedVariant, filteredBuyersByVariant,
            buyersSearch, buyersCurrentPage, buyersPerPage, buyersPerPageOptions, filteredBuyers, paginatedBuyers, buyersTotalPages, buyersStartIndex, buyersEndIndex, buyersVisiblePages, buyersGoToPage, buyersHandlePerPageChange, goToOrderDetail,
            allocationCurrentPage, allocationPerPage, allocationPerPageOptions, paginatedProductOrders, allocationTotalPages, allocationStartIndex, allocationEndIndex, allocationVisiblePages, allocationGoToPage, allocationHandlePerPageChange,
            showImageModal, currentImage, imageUploading, imageError, toastMessage,
            // 自訂欄位（Phase 49）
            editTab, customFields, customFieldsLoading, customFieldsSaving, profit, profitMargin,
            currentPage, perPage, totalProducts, menuItems: [
                { id: 'products', label: '商品管理', icon: '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>' },
                { id: 'orders', label: '訂單管理', icon: '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>' },
                 { id: 'settings', label: '系統設定', icon: '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>' },
            ],

            // Methods
            navigateTo, goToBatchCreate, checkUrlParams, getSubPageTitle, isAllSelected,
            loadProducts, saveProduct, savePurchased, toggleStatus, deleteProduct, batchDelete, allocateOrder, viewBuyers, formatDate,
            getStatusClass, getStatusText,
            handleSubPageSave, openImageModal, closeImageModal, triggerFileInput, handleFileSelect,
            toggleSelectAll, formatPriceDisplay, getTWDPrice, calculateReserved, handleSearchInput: (e) => { globalSearchQuery.value = e.target.value; loadProducts(); },
            handleProductSelect,
            handleProductSearch,
            handleProductSearchClear,
            getProductLink, copyProductLink,
            // 調整分配方法
            editingAllocationKey, editingAllocationQty, startEditAllocation, cancelEditAllocation, confirmAdjustAllocation,
            // 自訂欄位方法（Phase 49）
            loadCustomFields, saveCustomFields,
            // Variation 方法
            getDisplayTitle,
            getDisplayPrice,
            getDisplayImage,
            onVariationChange,
            onEditVariationChange,
            fileInput,
             handleTabClick: (id) => {
                 currentTab.value = id;
                 if (id === 'products') navigateTo('list');
             },
             currentCurrency,
             systemCurrency,
             currencySymbols,
             toggleCurrency: () => {
                 // 在系統幣別和台幣之間切換
                 if (currentCurrency.value === 'TWD') {
                     currentCurrency.value = systemCurrency.value;
                     showToast(`已切換為 ${currencySymbols[systemCurrency.value]} ${systemCurrency.value}`);
                 } else {
                     currentCurrency.value = 'TWD';
                     showToast(`已切換為 ${currencySymbols['TWD']} TWD`);
                 }
             },
             // 幣別切換
             onCurrencyChange
        };
}
;
/* === includes/views/composables/useShipmentProducts.js === */
/**
 * useShipmentProducts Composable
 * 備貨管理頁面資料邏輯
 *
 * 功能:
 * - 出貨單列表載入與分頁
 * - 搜尋處理（本地過濾）
 * - 出貨單展開/收合
 * - 批次操作（標記已出貨、合併出貨單）
 * - 轉出貨（pending → ready_to_ship）
 * - 確認 Modal 與 Toast 通知
 * - 預載資料初始化（消除 Loading）
 *
 * 使用方式:
 * const { shipments, loading, loadShipments, ... } = useShipmentProducts();
 *
 * Dependencies:
 * - Vue 3
 * - useCurrency (composable)
 * - BuyGoCache (optional, global)
 *
 * @version 1.0.0
 */
function useShipmentProducts() {
    const { ref, computed, onMounted, onUnmounted, watch } = Vue;

    // WordPress REST API nonce（用於 API 認證）
    const wpNonce = window.buygoWpNonce || '';

    // 使用 useCurrency Composable 處理幣別邏輯
    const { formatPrice } = useCurrency();

    // ========================================
    // 狀態變數
    // ========================================
    const shipments = ref([]);
    const loading = ref(false);
    const error = ref(null);

    // 分頁狀態
    const currentPage = ref(1);
    const perPage = ref(5);
    const totalShipments = ref(0);

    // 搜尋狀態
    const searchQuery = ref(null);
    const searchFilter = ref(null);

    // 狀態篩選（與 activeTab 同步）
    const currentStatusFilter = ref('pending');
    const statusFilters = [
        { value: 'all', label: '全部' },
        { value: 'pending', label: '備貨中' },
        { value: 'ready_to_ship', label: '待出貨' },
        { value: 'shipped', label: '已出貨' }
    ];

    // 批次操作
    const selectedItems = ref([]);
    const selectedShipments = ref([]);

    // 展開狀態（用於商品列表展開）
    const expandedShipments = ref(new Set());

    // 確認 Modal 狀態
    const showConfirmModal = ref(false);
    const confirmModal = ref({
        title: '確認操作',
        message: '',
        onConfirm: null
    });

    // Toast 通知狀態
    const toastMessage = ref({
        show: false,
        message: '',
        type: 'success' // 'success' | 'error' | 'info'
    });

    // 全域搜尋狀態
    const showMobileSearch = ref(false);
    const globalSearchQuery = ref('');

    // ========================================
    // 全域搜尋處理
    // ========================================
    const handleGlobalSearch = () => {
        if (globalSearchQuery.value.trim()) {
            // 可以實作跨頁面搜尋邏輯
            // TODO: 實作跨頁面搜尋功能
        }
    };

    // ========================================
    // Toast 通知
    // ========================================
    const showToast = (message, type = 'success') => {
        toastMessage.value = { show: true, message, type };
        setTimeout(() => {
            toastMessage.value.show = false;
        }, 3000);
    };

    // ========================================
    // API 呼叫：載入出貨單列表
    // ========================================
    const loadShipments = async (options = {}) => {
        // silent 模式：背景刷新時不顯示 loading skeleton，避免切頁閃爍
        if (!options.silent) loading.value = true;
        error.value = null;

        try {
            // 使用 BuyGoCache 快取層，不再強制繞過瀏覽器快取
            let url = `/wp-json/buygo-plus-one/v1/shipments?page=${currentPage.value}&per_page=${perPage.value}&status=pending`;

            // 加入搜尋參數
            if (searchQuery.value) {
                url += `&search=${encodeURIComponent(searchQuery.value)}`;
            }

            const response = await fetch(url, {
                credentials: 'include',
                headers: {
                    'X-WP-Nonce': wpNonce
                }
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const result = await response.json();

            if (result.success && result.data) {
                shipments.value = result.data;
                totalShipments.value = result.total || result.data.length;

                // 儲存到 BuyGoCache
                if (window.BuyGoCache) { window.BuyGoCache.set('shipment-products', result); }
            } else {
                throw new Error(result.message || '載入出貨單失敗');
            }
        } catch (err) {
            console.error('載入出貨單錯誤:', err);
            error.value = err.message;
            shipments.value = [];
        } finally {
            loading.value = false;
        }
    };

    // ========================================
    // 搜尋處理（本地過濾）
    // ========================================
    const handleLocalSearchInput = (query) => {
        searchQuery.value = query;
        currentPage.value = 1;  // 重置到第一頁
        loadShipments();
    };

    const handleLocalSearchSelect = (item) => {
        // 選擇後過濾顯示該出貨單
        if (item && item.shipment_number) {
            searchQuery.value = item.shipment_number;
            currentPage.value = 1;
            loadShipments();
        }
    };

    const handleLocalSearchClear = () => {
        searchQuery.value = null;
        currentPage.value = 1;
        loadShipments();
    };

    // ========================================
    // 格式化函數
    // ========================================
    const formatDate = (dateString) => {
        if (!dateString) return '';
        const date = new Date(dateString);
        return date.toLocaleDateString('zh-TW');
    };

    const formatItemsDisplay = (shipment, maxLength = 50) => {
        if (!shipment.items || !Array.isArray(shipment.items) || shipment.items.length === 0) {
            return `0 個項目`;
        }

        // 顯示商品名稱列表
        const itemCount = shipment.items.length;
        const firstItem = shipment.items[0];
        const itemNames = shipment.items.map(item => item.product_name || '未知商品').join('、');

        // 如果只有一個商品,顯示完整名稱
        if (itemCount === 1) {
            return firstItem.product_name || '未知商品';
        }

        // 多個商品時,截斷顯示
        if (itemNames.length <= maxLength) {
            return itemNames;
        }

        return `${firstItem.product_name || '未知商品'} 等 ${itemCount} 項`;
    };

    const getStatusClass = (status) => {
        const statusClasses = {
            'pending': 'bg-yellow-100 text-yellow-800 border border-yellow-200',
            'ready_to_ship': 'bg-orange-100 text-orange-800 border border-orange-200',
            'shipped': 'bg-green-100 text-green-800 border border-green-200',
            'archived': 'bg-slate-100 text-slate-800 border border-slate-200',
            'delivered': 'bg-blue-100 text-blue-800 border border-blue-200'
        };
        return statusClasses[status] || 'bg-slate-100 text-slate-800';
    };

    const getStatusText = (status) => {
        const statusTexts = {
            'pending': '備貨中',
            '備貨中': '備貨中',
            'ready_to_ship': '待出貨',
            'shipped': '已出貨',
            '已出貨': '已出貨',
            'archived': '已存檔',
            'delivered': '已送達'
        };
        return statusTexts[status] || '備貨中';
    };

    // ========================================
    // 展開控制
    // ========================================
    const toggleShipmentExpand = (shipmentId) => {
        if (expandedShipments.value.has(shipmentId)) {
            expandedShipments.value.delete(shipmentId);
        } else {
            expandedShipments.value.add(shipmentId);
        }
    };

    const isShipmentExpanded = (shipmentId) => {
        return expandedShipments.value.has(shipmentId);
    };

    // ========================================
    // 確認 Modal
    // ========================================
    const showConfirm = (message, title = '確認操作', onConfirm = null) => {
        confirmModal.value = {
            title,
            message,
            onConfirm
        };
        showConfirmModal.value = true;
    };

    const executeConfirm = () => {
        if (confirmModal.value.onConfirm) {
            confirmModal.value.onConfirm();
        }
        showConfirmModal.value = false;
    };

    const cancelConfirm = () => {
        showConfirmModal.value = false;
        confirmModal.value.onConfirm = null;
    };

    // ========================================
    // 出貨操作
    // ========================================
    const markShipped = (shipmentId) => {
        showConfirm('確定要標記此出貨單為已出貨嗎？', '確認標記已出貨', async () => {
            try {
                const response = await fetch(`/wp-json/buygo-plus-one/v1/shipments/batch-mark-shipped`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': wpNonce
                    },
                    credentials: 'include',
                    body: JSON.stringify({
                        shipment_ids: [shipmentId]
                    })
                });

                const result = await response.json();

                if (result.success) {
                    showToast('標記成功！', 'success');
                    await loadShipments();
                } else {
                    showToast('標記失敗：' + result.message, 'error');
                }
            } catch (err) {
                console.error('標記失敗:', err);
                showToast('標記失敗：' + err.message, 'error');
            }
        });
    };

    const batchMarkShipped = () => {
        if (selectedShipments.value.length === 0) {
            return;
        }

        showConfirm(
            `確定要標記 ${selectedShipments.value.length} 個出貨單為已出貨嗎？`,
            '批次標記已出貨',
            async () => {
                try {
                    const response = await fetch(`/wp-json/buygo-plus-one/v1/shipments/batch-mark-shipped`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-WP-Nonce': wpNonce
                        },
                        credentials: 'include',
                        body: JSON.stringify({
                            shipment_ids: selectedShipments.value
                        })
                    });

                    const result = await response.json();

                    if (result.success) {
                        showToast(`成功標記 ${result.count} 個出貨單為已出貨！`, 'success');
                        selectedShipments.value = [];
                        await loadShipments();
                    } else {
                        showToast('標記失敗：' + result.message, 'error');
                    }
                } catch (err) {
                    console.error('批次標記失敗:', err);
                    showToast('標記失敗：' + err.message, 'error');
                }
            }
        );
    };

    const moveToShipment = async (shipmentId) => {
        showConfirm(
            '確認轉出貨',
            '確定要將此出貨單轉為待出貨嗎？轉出貨後將出現在「出貨」頁面。',
            async () => {
                try {
                    const response = await fetch(`/wp-json/buygo-plus-one/v1/shipments/${shipmentId}/transfer`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-WP-Nonce': wpNonce
                        },
                        credentials: 'include'
                    });

                    const result = await response.json();

                    if (result.success) {
                        showToast('已轉為待出貨', 'success');
                        await loadShipments();
                    } else {
                        showToast('轉出貨失敗：' + result.message, 'error');
                    }
                } catch (err) {
                    showToast('轉出貨失敗', 'error');
                }
            }
        );
    };

    const mergeShipments = async () => {
        if (!canMerge.value) {
            showToast('只能合併相同客戶的出貨單', 'error');
            return;
        }

        showConfirm(
            '確認合併出貨單',
            `確定要合併 ${selectedShipments.value.length} 個出貨單嗎？`,
            async () => {
                try {
                    const response = await fetch('/wp-json/buygo-plus-one/v1/shipments/merge', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-WP-Nonce': wpNonce
                        },
                        credentials: 'include',
                        body: JSON.stringify({
                            shipment_ids: selectedShipments.value
                        })
                    });

                    const result = await response.json();

                    if (result.success) {
                        showToast('合併成功！', 'success');
                        selectedShipments.value = [];
                        await loadShipments();
                    } else {
                        showToast('合併失敗：' + (result.message || '未知錯誤'), 'error');
                    }
                } catch (err) {
                    console.error('合併失敗:', err);
                    showToast('合併失敗：' + err.message, 'error');
                }
            }
        );
    };

    // ========================================
    // 全選邏輯
    // ========================================
    const toggleSelectAll = (event) => {
        if (event.target.checked) {
            selectedShipments.value = shipments.value.map(s => s.id);
        } else {
            selectedShipments.value = [];
        }
    };

    const isAllSelected = computed(() => {
        return shipments.value.length > 0 &&
               selectedShipments.value.length === shipments.value.length;
    });

    const canMerge = computed(() => {
        if (selectedShipments.value.length < 2) return false;

        // 取得所有選中出貨單的客戶 ID
        const customerIds = selectedShipments.value.map(id => {
            const shipment = shipments.value.find(s => s.id === id);
            return shipment?.customer_id;
        });

        // 檢查是否都是同一個客戶
        return customerIds.every(id => id === customerIds[0]);
    });

    // ========================================
    // 分頁邏輯
    // ========================================
    const totalPages = computed(() => {
        if (perPage.value === -1) return 1;
        return Math.ceil(totalShipments.value / perPage.value);
    });

    const visiblePages = computed(() => {
        const pages = [];
        const total = totalPages.value;
        const current = currentPage.value;

        if (total <= 5) {
            for (let i = 1; i <= total; i++) {
                pages.push(i);
            }
        } else {
            if (current <= 3) {
                pages.push(1, 2, 3, 4, 5);
            } else if (current >= total - 2) {
                for (let i = total - 4; i <= total; i++) {
                    pages.push(i);
                }
            } else {
                for (let i = current - 2; i <= current + 2; i++) {
                    pages.push(i);
                }
            }
        }

        return pages;
    });

    const previousPage = () => {
        if (currentPage.value > 1) {
            currentPage.value--;
            loadShipments();
        }
    };

    const nextPage = () => {
        if (currentPage.value < totalPages.value) {
            currentPage.value++;
            loadShipments();
        }
    };

    const goToPage = (page) => {
        currentPage.value = page;
        loadShipments();
    };

    const changePerPage = () => {
        currentPage.value = 1;
        loadShipments();
    };

    // ========================================
    // 預載初始化
    // ========================================
    const initFromPreloadedData = () => {
        const preloaded = window.buygoInitialData?.shipments;
        if (!preloaded || !preloaded.success || !preloaded.data) return false;

        // 備貨商品頁只顯示 pending 狀態，預注入的是全部資料，需要過濾
        const pendingShipments = preloaded.data.filter(s => s.status === 'pending');
        shipments.value = pendingShipments;
        totalShipments.value = pendingShipments.length;
        loading.value = false;
        // 寫入快取，讓 preload 失敗時有 fallback
        if (window.BuyGoCache) { window.BuyGoCache.set('shipment-products', preloaded); }
        delete window.buygoInitialData?.shipments;
        return true;
    };

    // ========================================
    // 具名 Event Handler（供 onMounted/onUnmounted 配對使用）
    // ========================================
    const handlePageshowShipProducts = (e) => {
        if (e.persisted) {
            loadShipments();
        }
    };
    const handleVisibilityChangeShipProducts = () => {
        if (document.visibilityState === 'visible') {
            if (window.BuyGoCache && window.BuyGoCache.isFresh && window.BuyGoCache.isFresh('shipment-products')) {
                return;
            }
            loadShipments();
        }
    };

    // ========================================
    // 生命週期
    // ========================================
    onMounted(() => {
        if (!initFromPreloadedData()) {
            // 備貨頁快取是狀態相依的，無法直接重用，一律打 API
            loadShipments();
        }

        // 監聽頁面顯示事件（處理 bfcache 和頁面切換）
        window.addEventListener('pageshow', handlePageshowShipProducts);

        // 監聽頁面可見性變化
        // SWR 策略：快取新鮮時不重新載入，避免切分頁回來時 Loading 閃爍
        document.addEventListener('visibilitychange', handleVisibilityChangeShipProducts);
    });

    // SPA 清理：移除所有 event listener，防止記憶體洩漏
    onUnmounted(() => {
        window.removeEventListener('pageshow', handlePageshowShipProducts);
        document.removeEventListener('visibilitychange', handleVisibilityChangeShipProducts);
    });

    // 幣別切換處理（Header 元件會呼叫此方法）
    const onCurrencyChange = (newCurrency) => {
        console.log('[ShipmentProductsPage] 幣別變更:', newCurrency);
        currentCurrency.value = newCurrency;
    };

    // ========================================
    // 回傳所有公開的狀態和方法
    // ========================================
    return {
        // 狀態
        shipments,
        loading,
        error,
        currentPage,
        perPage,
        totalShipments,
        totalPages,
        visiblePages,
        previousPage,
        nextPage,
        goToPage,
        changePerPage,
        formatDate,
        formatItemsDisplay,
        formatPrice,
        getStatusClass,
        getStatusText,
        toggleShipmentExpand,
        isShipmentExpanded,
        expandedShipments,
        currentStatusFilter,
        statusFilters,
        markShipped,
        batchMarkShipped,
        toggleSelectAll,
        selectedItems,
        selectedShipments,
        isAllSelected,
        canMerge,
        moveToShipment,
        mergeShipments,
        loadShipments,

        // 全域搜尋
        showMobileSearch,
        globalSearchQuery,
        handleGlobalSearch,

        // Smart Search Box 事件處理
        handleSearchInput: handleLocalSearchInput,
        handleSearchSelect: handleLocalSearchSelect,
        handleSearchClear: handleLocalSearchClear,

        // Modal 和 Toast
        showConfirmModal,
        confirmModal,
        showConfirm,
        executeConfirm,
        cancelConfirm,
        toastMessage,
        showToast,

        // 幣別切換
        onCurrencyChange
    };
}
;
/* === includes/views/composables/useShipmentDetails.js === */
/**
 * useShipmentDetails Composable
 * 出貨明細頁面資料邏輯
 *
 * Dependencies:
 * - Vue 3
 * - BuyGoRouter (global)
 * - useCurrency (composable)
 * - flatpickr (global, CDN)
 *
 * Required window variables:
 * - window.buygoWpNonce: WordPress REST API nonce
 */
function useShipmentDetails() {
    const { ref, computed, watch, onMounted, onUnmounted, nextTick } = Vue;

    // WordPress Nonce for API authentication
    const wpNonce = window.buygoWpNonce || '';

    // 使用 useCurrency Composable 處理幣別邏輯
    const { formatPrice, getCurrencySymbol, systemCurrency } = useCurrency();
    const activeTab = ref('ready_to_ship');
    const shipments = ref([]);
    const loading = ref(false);
    const stats = ref({ ready_to_ship: 0, shipped: 0, archived: 0 });

    // ============================================
    // 路由狀態（子分頁切換）
    // ============================================
    const currentView = ref('list');  // 'list' | 'detail'
    const currentShipmentId = ref(null);
    
    // 勾選狀態
    const selectedShipments = ref([]);
    
    // Modal 狀態
    const confirmModal = ref({ show: false, title: '', message: '', onConfirm: null });
    const toastMessage = ref({ show: false, message: '', type: 'success' });
    // 標記出貨子頁面資料
    const markShippedData = ref({
        shipment: null,
        items: [],
        total: 0,
        estimated_delivery_date: '',
        shipping_method: '',
        loading: false
    });

    // 物流下拉選單狀態
    const showShippingMethodDropdown = ref(false);
    const dropdownPosition = ref('bottom'); // 'bottom' 向下展開 | 'top' 向上展開

    // 物流方式選項（8 個物流公司 + 彩虹配色）
    const shippingMethods = [
        { value: '易利', label: '易利', color: 'bg-red-100 text-red-800 border border-red-300' },
        { value: '千森', label: '千森', color: 'bg-orange-100 text-orange-800 border border-orange-300' },
        { value: 'OMI', label: 'OMI', color: 'bg-yellow-100 text-yellow-800 border border-yellow-300' },
        { value: '多賀', label: '多賀', color: 'bg-green-100 text-green-800 border border-green-300' },
        { value: '賀來', label: '賀來', color: 'bg-blue-100 text-blue-800 border border-blue-300' },
        { value: '神奈川', label: '神奈川', color: 'bg-indigo-100 text-indigo-800 border border-indigo-300' },
        { value: '新日本', label: '新日本', color: 'bg-purple-100 text-purple-800 border border-purple-300' },
        { value: 'EMS', label: 'EMS', color: 'bg-pink-100 text-pink-800 border border-pink-300' }
    ];

    // 詳情 Modal 狀態
    const detailModal = ref({
        show: false,
        shipment: null,
        items: [],
        total: 0
    });

    // 分頁狀態
    const currentPage = ref(1);
    const perPage = ref(5);
    const totalShipments = ref(0);

    // 搜尋狀態
    const searchQuery = ref(null);
    const searchFilter = ref(null);

    // Flatpickr ref
    const estimatedDeliveryInput = ref(null);
    let flatpickrInstance = null;

    // 載入出貨單列表
    const loadShipments = async (options = {}) => {
        // silent 模式：背景刷新時不顯示 loading skeleton，避免切頁閃爍
        if (!options.silent) loading.value = true;
        try {
            // 使用 BuyGoCache 快取層，不再強制繞過瀏覽器快取
            let url = `/wp-json/buygo-plus-one/v1/shipments?status=${activeTab.value}&page=${currentPage.value}&per_page=${perPage.value}`;

            // 加入搜尋參數
            if (searchQuery.value) {
                url += `&search=${encodeURIComponent(searchQuery.value)}`;
            }

            const response = await fetch(url, {
                credentials: 'include',
                headers: {
                    'X-WP-Nonce': wpNonce
                }
            });
            const result = await response.json();

            if (result.success) {
                shipments.value = result.data || [];
                totalShipments.value = result.total || result.data.length;

                // 儲存到 BuyGoCache
                if (window.BuyGoCache) { window.BuyGoCache.set('shipment-details', result); }
            }
        } catch (err) {
            console.error('載入出貨單失敗:', err);
            showToast('載入失敗', 'error');
        } finally {
            loading.value = false;
        }
    };
    
    // 載入統計數據
    const loadStats = async () => {
        try {
            const statuses = ['ready_to_ship', 'shipped', 'archived'];
            for (const status of statuses) {
                const response = await fetch(`/wp-json/buygo-plus-one/v1/shipments?status=${status}&per_page=1`, {
                    credentials: 'include',
                    headers: {
                        'X-WP-Nonce': wpNonce
                    }
                });
                const result = await response.json();
                if (result.success && result.total !== undefined) {
                    stats.value[status] = result.total;
                }
            }
        } catch (err) {
            console.error('載入統計失敗:', err);
        }
    };
    
    // 顯示標記出貨子頁面
    const showMarkShippedConfirm = (shipment) => {
        navigateTo('shipment-mark', shipment.id);
    };

    // 載入標記出貨子頁面資料
    const loadMarkShippedData = async (shipmentId) => {
        markShippedData.value.loading = true;
        try {
            const url = `/wp-json/buygo-plus-one/v1/shipments/${shipmentId}/detail`;
            // 使用 BuyGoCache 快取層，不再強制繞過瀏覽器快取
            const response = await fetch(url, {
                credentials: 'include',
                headers: {
                    'X-WP-Nonce': wpNonce
                }
            });
            const result = await response.json();

            if (result.success) {
                markShippedData.value = {
                    shipment: result.data.shipment,
                    items: result.data.items,
                    total: result.data.items.reduce((sum, item) => sum + (item.quantity * item.price), 0),
                    estimated_delivery_date: '',
                    loading: false
                };
            } else {
                showToast('載入出貨單資料失敗：' + result.message, 'error');
                navigateTo('list');
            }
        } catch (err) {
            console.error('載入出貨單資料失敗:', err);
            showToast('載入出貨單資料失敗', 'error');
            navigateTo('list');
        }
    };

    // 確認標記已出貨（從子頁面執行）
    const confirmMarkShipped = async () => {
        const shipment = markShippedData.value.shipment;
        const estimatedDeliveryDate = markShippedData.value.estimated_delivery_date;
        const shippingMethod = markShippedData.value.shipping_method;

        if (!shipment) {
            navigateTo('list');
            return;
        }

        markShippedData.value.loading = true;

        try {
            // 準備 API 請求資料
            const requestData = {
                shipment_ids: [shipment.id]
            };

            // 如果有設定預計送達時間，加入請求資料（轉換為 MySQL DATETIME 格式）
            if (estimatedDeliveryDate) {
                requestData.estimated_delivery_at = estimatedDeliveryDate + ' 00:00:00';
            }

            // 如果有設定物流方式，加入請求資料
            if (shippingMethod) {
                requestData.shipping_method = shippingMethod;
            }

            const response = await fetch(`/wp-json/buygo-plus-one/v1/shipments/batch-mark-shipped`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': wpNonce
                },
                credentials: 'include',
                body: JSON.stringify(requestData)
            });
            const result = await response.json();

            if (result.success) {
                showToast('✓ 出貨單已標記為已出貨', 'success');
                selectedShipments.value = [];
                // 返回列表頁
                navigateTo('list');
                await loadShipments();
                await loadStats();
            } else {
                showToast('✗ 操作失敗：' + result.message, 'error');
            }
        } catch (err) {
            console.error('標記出貨失敗:', err);
            showToast('✗ 操作失敗，請稍後再試', 'error');
        } finally {
            markShippedData.value.loading = false;
        }
    };

    // 標記已出貨（保留原有函數供批次操作使用）
    const markShipped = (shipmentId) => {
        // 從列表中找到對應的出貨單
        const shipment = shipments.value.find(s => s.id === shipmentId);
        if (shipment) {
            showMarkShippedConfirm(shipment);
        }
    };
    
    // 移至存檔
    const archiveShipment = (shipmentId) => {
        showConfirm(
            '確認移至存檔',
            '確定要將此出貨單移至存檔區嗎？',
            async () => {
                try {
                    const response = await fetch(`/wp-json/buygo-plus-one/v1/shipments/${shipmentId}/archive`, {
                        method: 'POST',
                        credentials: 'include',
                        headers: {
                            'X-WP-Nonce': wpNonce
                        }
                    });
                    const result = await response.json();
                    
                    if (result.success) {
                        showToast('已移至存檔區', 'success');
                        selectedShipments.value = [];
                        await loadShipments();
                        await loadStats();
                    } else {
                        showToast('移至存檔失敗：' + result.message, 'error');
                    }
                } catch (err) {
                    showToast('移至存檔失敗', 'error');
                }
            }
        );
    };
    
    // 是否全選
    const isAllSelected = computed(() => {
        return shipments.value.length > 0 && 
               selectedShipments.value.length === shipments.value.length;
    });

    // 切換全選
    const toggleSelectAll = (event) => {
        if (event.target.checked) {
            selectedShipments.value = shipments.value.map(s => s.id);
        } else {
            selectedShipments.value = [];
        }
    };

    // 清除勾選
    const clearSelection = () => {
        selectedShipments.value = [];
    };

    // 分頁處理函數
    const changePerPage = () => {
        currentPage.value = 1; // 重置到第一頁
        loadShipments();
    };

    const previousPage = () => {
        if (currentPage.value > 1) {
            currentPage.value--;
            loadShipments();
        }
    };

    const nextPage = () => {
        if (currentPage.value < totalPages.value) {
            currentPage.value++;
            loadShipments();
        }
    };

    // 計算屬性：總頁數
    const totalPages = computed(() => {
        return Math.ceil(totalShipments.value / perPage.value);
    });

    // 計算可見頁碼
    const visiblePages = computed(() => {
        const pages = [];
        const maxPages = Math.min(5, totalPages.value);
        let startPage = Math.max(1, currentPage.value - Math.floor(maxPages / 2));
        let endPage = startPage + maxPages - 1;

        if (endPage > totalPages.value) {
            endPage = totalPages.value;
            startPage = Math.max(1, endPage - maxPages + 1);
        }

        for (let i = startPage; i <= endPage; i++) {
            pages.push(i);
        }
        return pages;
    });

    // 跳轉到指定頁
    const goToPage = (page) => {
        if (page < 1 || page > totalPages.value) return;
        currentPage.value = page;
        loadShipments();
    };

    // 批次標記已出貨
    const batchMarkShipped = () => {
        if (selectedShipments.value.length === 0) {
            showToast('請先選擇出貨單', 'error');
            return;
        }
        
        showConfirm(
            '確認批次標記已出貨',
            `確定要將 ${selectedShipments.value.length} 個出貨單標記為已出貨嗎？`,
            async () => {
                try {
                    const response = await fetch('/wp-json/buygo-plus-one/v1/shipments/batch-mark-shipped', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-WP-Nonce': wpNonce
                        },
                        credentials: 'include',
                        body: JSON.stringify({ shipment_ids: selectedShipments.value })
                    });
                    const result = await response.json();
                    
                    if (result.success) {
                        showToast('批次標記成功！', 'success');
                        selectedShipments.value = [];
                        await loadShipments();
                        await loadStats();
                    } else {
                        showToast('批次標記失敗：' + result.message, 'error');
                    }
                } catch (err) {
                    console.error('批次標記失敗:', err);
                    showToast('批次標記失敗', 'error');
                }
            }
        );
    };

    // 批次移至存檔
    const batchArchive = () => {
        if (selectedShipments.value.length === 0) {
            showToast('請先選擇出貨單', 'error');
            return;
        }
        
        showConfirm(
            '確認批次移至存檔',
            `確定要將 ${selectedShipments.value.length} 個出貨單移至存檔區嗎？`,
            async () => {
                try {
                    const response = await fetch('/wp-json/buygo-plus-one/v1/shipments/batch-archive', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-WP-Nonce': wpNonce
                        },
                        credentials: 'include',
                        body: JSON.stringify({ shipment_ids: selectedShipments.value })
                    });
                    const result = await response.json();
                    
                    if (result.success) {
                        showToast('批次移至存檔成功！', 'success');
                        selectedShipments.value = [];
                        await loadShipments();
                        await loadStats();
                    } else {
                        showToast('批次移至存檔失敗：' + result.message, 'error');
                    }
                } catch (err) {
                    console.error('批次移至存檔失敗:', err);
                    showToast('批次移至存檔失敗', 'error');
                }
            }
        );
    };

    // 合併顯示開關
    const mergeEnabled = ref(true);

    // 合併同商品顯示（按 product_id 歸組，數量和金額加總）
    const mergeItemsByProduct = (items) => {
        if (!items || items.length === 0) return [];
        const map = {};
        items.forEach(item => {
            const pid = item.product_id;
            if (map[pid]) {
                map[pid].quantity += Number(item.quantity);
            } else {
                map[pid] = { ...item, quantity: Number(item.quantity) };
            }
        });
        return Object.values(map);
    };

    const mergedDetailItems = computed(() =>
        mergeEnabled.value ? mergeItemsByProduct(detailModal.value.items) : detailModal.value.items
    );
    const mergedMarkShippedItems = computed(() =>
        mergeEnabled.value ? mergeItemsByProduct(markShippedData.value.items) : markShippedData.value.items
    );

    // 匯出單張出貨單
    const exportShipment = async (shipmentId) => {
        if (!shipmentId) {
            showToast('出貨單 ID 無效', 'error');
            return;
        }

        try {
            // 建立 URL（使用 GET 參數 + nonce 驗證）
            const url = `/wp-json/buygo-plus-one/v1/shipments/export?shipment_ids=${shipmentId}&_wpnonce=${wpNonce}`;

            // 直接開啟 URL（瀏覽器會自動下載檔案）
            window.location.href = url;

            showToast('正在匯出...', 'info');
        } catch (err) {
            console.error('匯出失敗:', err);
            showToast('匯出失敗：' + err.message, 'error');
        }
    };

    // 批次匯出（參考舊外掛，使用 GET 請求直接開啟 URL）
    const batchExport = () => {
        if (selectedShipments.value.length === 0) {
            showToast('請先選擇出貨單', 'error');
            return;
        }

        try {
            // 建立 URL（使用 GET 參數傳遞 shipment_ids + nonce 驗證）
            const ids = selectedShipments.value.join(',');
            const url = `/wp-json/buygo-plus-one/v1/shipments/export?shipment_ids=${ids}&_wpnonce=${wpNonce}`;

            // 直接開啟 URL（瀏覽器會自動下載檔案）
            window.location.href = url;

            showToast(`正在匯出 ${selectedShipments.value.length} 個出貨單...`, 'info');
        } catch (err) {
            console.error('批次匯出失敗:', err);
            showToast('批次匯出失敗：' + err.message, 'error');
        }
    };

    // 查看詳情（改為使用子分頁）
    const viewDetail = (shipmentId) => {
        openShipmentDetail(shipmentId);
    };

    // 關閉詳情（改為使用子分頁）
    const closeDetailModal = () => {
        closeShipmentDetail();
    };

    // ============================================
    // 路由邏輯（子分頁切換）
    // ============================================

    // 檢查 URL 參數
    const checkUrlParams = () => {
        const params = window.BuyGoRouter.checkUrlParams();
        const { view, id } = params;

        if (view === 'detail' && id) {
            currentView.value = 'detail';
            currentShipmentId.value = id;
            loadShipmentDetail(id);
        } else if (view === 'shipment-mark' && id) {
            currentView.value = 'shipment-mark';
            currentShipmentId.value = id;
            loadMarkShippedData(id);
        } else {
            currentView.value = 'list';
            currentShipmentId.value = null;
        }
    };

    // 導航函數
    const navigateTo = (view, shipmentId = null, updateUrl = true) => {
        currentView.value = view;

        if (view === 'shipment-mark' && shipmentId) {
            currentShipmentId.value = shipmentId;
            loadMarkShippedData(shipmentId);

            if (updateUrl) {
                window.BuyGoRouter.navigateTo(view, shipmentId);
            }
        } else if (view === 'detail' && shipmentId) {
            currentShipmentId.value = shipmentId;
            loadShipmentDetail(shipmentId);

            if (updateUrl) {
                window.BuyGoRouter.navigateTo(view, shipmentId);
            }
        } else {
            currentShipmentId.value = null;
            detailModal.value = { show: false, shipment: null, items: [], total: 0 };
            markShippedData.value = { shipment: null, items: [], total: 0, estimated_delivery_date: '', loading: false };

            if (updateUrl) {
                window.BuyGoRouter.goToList();
            }
        }
    };

    // 載入出貨單詳情（供子分頁使用）
    const loadShipmentDetail = async (shipmentId) => {
        try {
            const url = `/wp-json/buygo-plus-one/v1/shipments/${shipmentId}/detail`;
            // 使用 BuyGoCache 快取層，不再強制繞過瀏覽器快取
            const response = await fetch(url, {
                credentials: 'include',
                headers: {
                    'X-WP-Nonce': wpNonce
                }
            });
            const result = await response.json();

            if (result.success) {
                // 將 estimated_delivery_at 轉換為 date input 格式
                const shipmentData = result.data.shipment;
                // 無論有無值都初始化，避免 v-model 綁定 undefined（null 時設空字串）
                shipmentData.estimated_delivery_date = shipmentData.estimated_delivery_at
                    ? formatDateForInput(shipmentData.estimated_delivery_at)
                    : '';

                detailModal.value = {
                    show: true,
                    shipment: shipmentData,
                    items: result.data.items,
                    total: result.data.items.reduce((sum, item) => sum + (item.quantity * item.price), 0)
                };
            } else {
                showToast('載入詳情失敗：' + result.message, 'error');
            }
        } catch (err) {
            console.error('載入詳情失敗:', err);
            showToast('載入詳情失敗', 'error');
        }
    };

    // 開啟出貨單詳情
    const openShipmentDetail = (shipmentId) => {
        navigateTo('detail', shipmentId);
    };

    // 關閉出貨單詳情
    const closeShipmentDetail = () => {
        navigateTo('list');
    };

    // 列印收據
    const printDetail = () => {
        window.print();
    };
    
    // Modal 控制
    const showConfirm = (title, message, onConfirm) => {
        confirmModal.value = { show: true, title, message, onConfirm };
    };

    const closeConfirmModal = () => {
        confirmModal.value = { show: false, title: '', message: '', onConfirm: null };
    };
    
    const handleConfirm = () => {
        if (confirmModal.value.onConfirm) {
            confirmModal.value.onConfirm();
        }
        closeConfirmModal();
    };
    
    const showToast = (message, type = 'success') => {
        toastMessage.value = { show: true, message, type };
        setTimeout(() => {
            toastMessage.value.show = false;
        }, 3000);
    };
    
    // 格式化日期
    const formatDate = (dateString) => {
        if (!dateString) return '-';
        const date = new Date(dateString);
        return `${date.getFullYear()}/${date.getMonth() + 1}/${date.getDate()}`;
    };

    // 取得今天日期（YYYY-MM-DD 格式，用於 date input 的 min 屬性）
    // 用本地時間而非 toISOString()（UTC），避免台灣時區在午夜前後顯示昨天
    const getTodayDate = () => {
        const d = new Date();
        return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
    };

    // 取得當前日期時間（用於出貨時間顯示）
    const getCurrentDateTime = () => {
        const now = new Date();
        const year = now.getFullYear();
        const month = String(now.getMonth() + 1).padStart(2, '0');
        const day = String(now.getDate()).padStart(2, '0');
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        const seconds = String(now.getSeconds()).padStart(2, '0');
        return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
    };

    // 將 MySQL datetime 轉換為 date input 可用格式（YYYY-MM-DD）
    const formatDateForInput = (datetime) => {
        if (!datetime) return '';
        return datetime.split(' ')[0]; // 取 YYYY-MM-DD 部分
    };

    // 儲存出貨單的預計送達時間（供已出貨詳情頁面編輯使用）
    const saveShipment = async (shipmentId) => {
        if (!shipmentId || !detailModal.value.shipment) return;

        const estimatedDeliveryDate = detailModal.value.shipment.estimated_delivery_date || '';
        // 記錄儲存前的舊值，失敗時還原
        const previousDeliveryAt = detailModal.value.shipment.estimated_delivery_at;
        const previousDeliveryDate = estimatedDeliveryDate;

        try {
            const requestData = {};
            if (estimatedDeliveryDate) {
                requestData.estimated_delivery_at = estimatedDeliveryDate + ' 00:00:00';
            } else {
                requestData.estimated_delivery_at = null;
            }

            const response = await fetch(`/wp-json/buygo-plus-one/v1/shipments/${shipmentId}`, {
                method: 'PUT',
                credentials: 'include',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': wpNonce
                },
                body: JSON.stringify(requestData)
            });
            const result = await response.json();

            if (result.success) {
                showToast('預計送達時間已儲存');
                if (detailModal.value.shipment) {
                    detailModal.value.shipment.estimated_delivery_at = requestData.estimated_delivery_at;
                }
            } else {
                // 還原前端顯示，避免前後端不同步
                if (detailModal.value.shipment) {
                    detailModal.value.shipment.estimated_delivery_at = previousDeliveryAt;
                    detailModal.value.shipment.estimated_delivery_date = previousDeliveryDate;
                }
                showToast(result.message || '儲存失敗', 'error');
            }
        } catch (err) {
            // 網路錯誤也還原前端顯示
            if (detailModal.value.shipment) {
                detailModal.value.shipment.estimated_delivery_at = previousDeliveryAt;
                detailModal.value.shipment.estimated_delivery_date = previousDeliveryDate;
            }
            console.error('儲存出貨單失敗:', err);
            showToast('儲存失敗', 'error');
        }
    };

    // 物流下拉選單控制（智慧展開：判斷向上或向下）
    const toggleShippingMethodDropdown = (event) => {
        if (!showShippingMethodDropdown.value) {
            // 計算空間決定展開方向
            const button = event.currentTarget;
            const rect = button.getBoundingClientRect();
            const spaceBelow = window.innerHeight - rect.bottom;
            const spaceAbove = rect.top;
            const dropdownHeight = 8 * 48; // 8 個選項 × 每個約 48px

            // 決定展開方向：優先向下，空間不足才向上
            dropdownPosition.value = spaceBelow >= dropdownHeight ? 'bottom' : 'top';
        }
        showShippingMethodDropdown.value = !showShippingMethodDropdown.value;
    };

    const selectShippingMethod = (value) => {
        markShippedData.value.shipping_method = value;
        showShippingMethodDropdown.value = false;
    };

    const getShippingMethodColor = (method) => {
        const methodObj = shippingMethods.find(m => m.value === method);
        return methodObj ? methodObj.color : 'bg-slate-100 text-slate-800 border border-slate-300';
    };

    // 智慧搜尋處理
    const handleSearchInput = (query) => {
        // 本地搜尋處理函數（輸入時過濾列表）
        searchQuery.value = query;
        currentPage.value = 1;  // 重置到第一頁
        loadShipments();
    };

    const handleSearchSelect = (item) => {
        // 搜尋選中項目後的處理
        if (item && item.id) {
            viewDetail(item.id);
        }
    };

    const handleSearchClear = () => {
        // 清除搜尋後重新載入列表
        searchQuery.value = null;
        currentPage.value = 1;
        loadShipments();
    };

    // Header 幣別切換處理（避免 Vue 警告）
    const onCurrencyChange = (newCurrency) => {
        // 出貨頁面不需要幣別切換功能，此方法僅為滿足 header-component 需求
        console.log('Currency change event received:', newCurrency);
    };

    // 監聽分頁切換，清除勾選
    watch(() => activeTab.value, () => {
        selectedShipments.value = [];
        loadShipments();
    });

    // 監聽標記出貨頁面切換，初始化 Flatpickr
    watch(() => currentView.value, (newView) => {
        if (newView === 'shipment-mark') {
            // 延遲初始化，確保 DOM 已渲染
            nextTick(() => {
                // 銷毀舊的 Flatpickr 實例
                if (flatpickrInstance) {
                    flatpickrInstance.destroy();
                }

                // 初始化新的 Flatpickr 實例
                if (estimatedDeliveryInput.value && typeof flatpickr !== 'undefined') {
                    // 檢測是否為手機裝置
                    const isMobile = window.innerWidth < 768;

                    flatpickrInstance = flatpickr(estimatedDeliveryInput.value, {
                        dateFormat: "Y-m-d",
                        minDate: "today",
                        locale: typeof flatpickr.l10ns !== 'undefined' && flatpickr.l10ns.zh_tw ? flatpickr.l10ns.zh_tw : "default",
                        disableMobile: true,  // 關鍵：禁用原生日期選擇器，強制使用 Flatpickr
                        appendTo: document.body,  // 附加到 body，讓 CSS 生效
                        positionElement: isMobile ? undefined : estimatedDeliveryInput.value,  // 手機版不固定位置
                        onChange: (selectedDates, dateStr) => {
                            markShippedData.value.estimated_delivery_date = dateStr;
                        }
                    });
                }
            });
        } else {
            // 離開標記出貨頁面時銷毀 Flatpickr
            if (flatpickrInstance) {
                flatpickrInstance.destroy();
                flatpickrInstance = null;
            }
        }
    });
    
    // 預注入資料初始化（消除 Loading 畫面）
    const initFromPreloadedData = () => {
        const preloaded = window.buygoInitialData?.shipments;
        if (!preloaded || !preloaded.success || !preloaded.data) return false;

        // 出貨明細頁按 activeTab 過濾（預設 ready_to_ship）
        const filtered = preloaded.data.filter(s => s.status === activeTab.value);
        shipments.value = filtered;
        totalShipments.value = filtered.length;
        // 統計各狀態數量
        const allData = preloaded.data;
        stats.value = {
            ready_to_ship: allData.filter(s => s.status === 'ready_to_ship').length,
            shipped: allData.filter(s => s.status === 'shipped').length,
            archived: allData.filter(s => s.status === 'archived').length
        };
        loading.value = false;
        // 寫入快取，讓 preload 失敗時有 fallback
        if (window.BuyGoCache) { window.BuyGoCache.set('shipment-details', preloaded); }
        delete window.buygoInitialData?.shipments;
        return true;
    };

    // ========================================
    // 具名 Event Handler（供 onMounted/onUnmounted 配對使用）
    // ========================================
    let removePopstateListenerShipDetails = null;

    const handlePageshowShipDetails = (e) => {
        if (e.persisted) {
            loadShipments();
            loadStats();
        }
    };
    const handleVisibilityChangeShipDetails = () => {
        if (document.visibilityState === 'visible') {
            if (window.BuyGoCache && window.BuyGoCache.isFresh && window.BuyGoCache.isFresh('shipment-details')) {
                return;
            }
            loadShipments();
            loadStats();
        }
    };
    const handleDocClickShipDetails = (e) => {
        if (showShippingMethodDropdown.value && !e.target.closest('.relative')) {
            showShippingMethodDropdown.value = false;
        }
    };

    onMounted(() => {
        if (!initFromPreloadedData()) {
            // 出貨頁快取是 tab 相依的（待出貨/已出貨/存檔），無法直接重用
            // 一律打 API 載入正確 tab 的資料
            loadShipments();
            loadStats();
        }

        // 檢查 URL 參數（支援直接訪問詳情頁）
        checkUrlParams();

        // 監聽瀏覽器上一頁/下一頁（儲存 cleanup 函式）
        removePopstateListenerShipDetails = window.BuyGoRouter.setupPopstateListener(checkUrlParams);

        // 監聽頁面顯示事件（處理 bfcache 和頁面切換）
        window.addEventListener('pageshow', handlePageshowShipDetails);

        // 監聽頁面可見性變化
        // SWR 策略：快取新鮮時不重新載入，避免切分頁回來時 Loading 閃爍
        document.addEventListener('visibilitychange', handleVisibilityChangeShipDetails);

        // 點擊外部關閉物流下拉選單
        document.addEventListener('click', handleDocClickShipDetails);
    });

    // SPA 清理：移除所有 event listener + flatpickr，防止記憶體洩漏
    onUnmounted(() => {
        if (removePopstateListenerShipDetails) removePopstateListenerShipDetails();
        window.removeEventListener('pageshow', handlePageshowShipDetails);
        document.removeEventListener('visibilitychange', handleVisibilityChangeShipDetails);
        document.removeEventListener('click', handleDocClickShipDetails);
        // 清理 flatpickr instance
        if (flatpickrInstance) {
            flatpickrInstance.destroy();
            flatpickrInstance = null;
        }
    });

    return {
        activeTab,
        shipments,
        loading,
        stats,
        selectedShipments,
        isAllSelected,
        confirmModal,
        toastMessage,
        detailModal,
        markShippedData,
        showMarkShippedConfirm,
        loadMarkShippedData,
        confirmMarkShipped,
        markShipped,
        archiveShipment,
        viewDetail,
        closeConfirmModal,
        handleConfirm,
        formatDate,
        getTodayDate,
        getCurrentDateTime,
        formatDateForInput,
        toggleSelectAll,
        clearSelection,
        batchMarkShipped,
        batchArchive,
        closeDetailModal,
        formatPrice,
        printDetail,
        getCurrencySymbol,
        systemCurrency,
        // 搜尋相關
        searchQuery,
        globalSearchQuery: searchQuery,  // 別名給 template 使用
        handleSearchInput,
        handleGlobalSearch: handleSearchInput,  // 別名給 template 使用
        handleSearchSelect,
        handleSearchClear,
        showToast,
        // 匯出功能
        exportShipment,
        batchExport,
        // 分頁相關
        currentPage,
        perPage,
        totalShipments,
        totalPages,
        visiblePages,
        changePerPage,
        previousPage,
        nextPage,
        goToPage,
        // 路由相關（子分頁切換）
        currentView,
        currentShipmentId,
        navigateTo,
        checkUrlParams,
        openShipmentDetail,
        closeShipmentDetail,
        loadShipmentDetail,
        saveShipment,
        // 物流下拉選單相關
        showShippingMethodDropdown,
        dropdownPosition,
        shippingMethods,
        toggleShippingMethodDropdown,
        selectShippingMethod,
        getShippingMethodColor,
        // Flatpickr ref
        estimatedDeliveryInput,
        // 合併顯示
        mergeEnabled,
        mergedDetailItems,
        mergedMarkShippedItems,
        // Header 事件處理
        onCurrencyChange
    };
}
;
/* === includes/views/composables/useBatchCreate.js === */
/**
 * useBatchCreate Composable - 批量上架邏輯
 *
 * 功能:
 * - 數量選擇（快選按鈕 5/10/15/20 + 自訂輸入）
 * - 配額查詢（呼叫 /products/limit-check API）
 * - 超額檢查與 CTA 按鈕狀態控制
 * - SPA 導航（返回商品列表 / 進入下一步）
 * - 表單狀態管理（items CRUD + 配額進度）
 * - CSV 匯入（前端解析 + 模式切換）
 *
 * 步驟控制:
 * - 'select' = 數量選擇（Phase 57 實作）
 * - 'form'   = 表單填寫（Phase 58 實作 ✓）
 *
 * 使用方式:
 * const { quantity, selectPreset, canProceed, ... } = useBatchCreate();
 *
 * @version 1.1.0
 * @date 2026-03-02
 */

// 注意: 全域函式，不使用 ES6 import/export（WordPress 環境相容）
function useBatchCreate() {
    const { ref, computed, onMounted } = Vue;
    const { get, post } = useApi();

    // ========== 步驟控制 ==========
    // 'select' = 數量選擇（Phase 57）
    // 'form'   = 表單填寫（Phase 58 實作）
    const step = ref('select');

    // ========== 數量選擇狀態 ==========
    const selectedPreset = ref(null);  // 5, 10, 15, 20 或 null
    const customQuantity = ref('');    // 自訂輸入值（字串，避免 number input 的 0 預設值問題）
    const presetOptions = [5, 10, 15, 20];

    /**
     * 當前選擇的數量
     * 快選按鈕優先，次之為自訂輸入
     */
    const quantity = computed(() => {
        if (selectedPreset.value !== null) return selectedPreset.value;
        const custom = parseInt(customQuantity.value);
        if (!isNaN(custom) && custom >= 1 && custom <= 20) return custom;
        return 0;
    });

    /**
     * 選擇快選按鈕
     * 同時清空自訂輸入
     */
    const selectPreset = (num) => {
        selectedPreset.value = num;
        customQuantity.value = '';
    };

    /**
     * 自訂輸入時取消快選按鈕狀態
     */
    const onCustomInput = () => {
        selectedPreset.value = null;
    };

    // ========== 配額狀態 ==========
    const quota = ref({ can_add: true, current: 0, limit: 0, message: '' });
    const quotaLoading = ref(true);

    /**
     * 剩餘配額數量
     * limit === 0 表示無限制，回傳 Infinity
     */
    const remaining = computed(() => {
        if (quota.value.limit === 0) return Infinity;
        return Math.max(0, quota.value.limit - quota.value.current);
    });

    /**
     * 是否超過配額
     * limit === 0（無限制）時永遠回傳 false
     */
    const isOverQuota = computed(() => {
        if (quota.value.limit === 0) return false;
        return quantity.value > remaining.value;
    });

    /**
     * 「開始填寫」按鈕是否可用
     * 需同時滿足：已選擇數量 + 未超過配額
     */
    const canProceed = computed(() => {
        return quantity.value > 0 && !isOverQuota.value;
    });

    /**
     * 載入配額資訊
     * 呼叫 /wp-json/buygo-plus-one/v1/products/limit-check
     */
    const loadQuota = async () => {
        quotaLoading.value = true;
        try {
            const res = await get('/wp-json/buygo-plus-one/v1/products/limit-check', {
                showError: false
            });
            if (res && res.data) {
                quota.value = res.data;
            }
        } catch (e) {
            console.error('載入配額失敗:', e);
        } finally {
            quotaLoading.value = false;
        }
    };

    // ========== 表單狀態（Phase 58） ==========

    /**
     * 商品表單項目陣列
     * 每個 item: { id: number, name: '', price: '', quantity: '0', description: '' }
     * id 用於 Vue v-for :key 追蹤
     */
    const items = ref([]);
    let nextId = 1;

    /**
     * 建立單一空白商品物件
     */
    const createEmptyItem = () => ({
        id: nextId++,
        name: '',
        price: '',
        quantity: '0',
        description: '',
        imageId: null,
        imageUrl: null,
        imageUploading: false
    });

    /**
     * 初始化 N 個空白商品
     * 由 startFilling() 呼叫
     */
    const initItems = (count) => {
        items.value = [];
        nextId = 1;
        for (let i = 0; i < count; i++) {
            items.value.push(createEmptyItem());
        }
    };

    /**
     * 新增一個空白商品
     */
    const addItem = () => {
        items.value.push(createEmptyItem());
    };

    /**
     * 刪除指定商品（至少保留 1 個）
     */
    const removeItem = (id) => {
        if (items.value.length <= 1) return;
        items.value = items.value.filter(item => item.id !== id);
    };

    /**
     * 目前表單中的商品數量
     */
    const itemCount = computed(() => items.value.length);

    /**
     * 配額進度（已用 + 目前表單中的商品數量）
     * 用於頂部進度條顯示
     */
    const quotaUsed = computed(() => quota.value.current + itemCount.value);

    /**
     * 配額進度百分比（0-100）
     * limit === 0 表示無限制，回傳 0（不顯示進度條）
     */
    const quotaPercent = computed(() => {
        if (quota.value.limit === 0) return 0;
        return Math.min(100, Math.round((quotaUsed.value / quota.value.limit) * 100));
    });

    /**
     * 表單階段的「超額」判斷
     * 和數量選擇階段不同 — 這裡用 itemCount 而非 quantity
     */
    const isFormOverQuota = computed(() => {
        if (quota.value.limit === 0) return false;
        return quotaUsed.value > quota.value.limit;
    });

    // ========== 模式切換 + CSV 匯入（Phase 58 Plan 02） ==========

    /**
     * 表單模式：'manual' = 手動輸入, 'csv' = CSV 匯入
     * 切換時保留已填寫的手動資料
     */
    const formMode = ref('manual');

    /**
     * CSV 匯入相關狀態
     */
    const csvError = ref('');           // CSV 解析錯誤訊息
    const csvSuccessMsg = ref('');      // CSV 匯入成功提示（例如「成功匯入 8 筆」）
    const csvUploading = ref(false);    // 上傳中狀態

    /**
     * CSV Modal 顯示狀態（桌面版用）
     */
    const showCsvModal = ref(false);

    /**
     * 下載 CSV 範本
     * 前端生成 Blob + 觸發下載
     */
    const downloadCsvTemplate = () => {
        const bom = '\uFEFF';  // UTF-8 BOM，確保 Excel 正確讀取中文
        const content = bom + '名稱,售價,數量,描述\n範例商品,100,10,這是商品說明\n';
        const blob = new Blob([content], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'batch-create-template.csv';
        a.click();
        URL.revokeObjectURL(url);
    };

    /**
     * 切換表單模式
     * 切換到 csv 模式時保留 items（不清空）
     */
    const setFormMode = (mode) => {
        formMode.value = mode;
        csvError.value = '';
        csvSuccessMsg.value = '';
    };

    /**
     * 解析 CSV 文字內容
     * 支援中英文表頭，自動對應欄位
     * @param {string} text - CSV 原始文字
     * @returns {{ data: Array, error: string }}
     */
    const parseCSV = (text) => {
        const lines = text.trim().split(/\r?\n/);
        if (lines.length < 2) {
            return { data: [], error: 'CSV 至少需要表頭和一行資料' };
        }

        // 解析表頭 — 支援中英文欄位名
        const headerLine = lines[0];
        const headers = headerLine.split(',').map(h => h.trim().replace(/^["']|["']$/g, '').toLowerCase());

        // 欄位對照表
        const fieldMap = {};
        const nameAliases = ['名稱', 'name', '商品名稱', '品名'];
        const priceAliases = ['售價', 'price', '價格', '單價'];
        const qtyAliases = ['數量', 'quantity', 'qty', '庫存'];
        const descAliases = ['描述', 'description', 'desc', '說明'];

        headers.forEach((h, i) => {
            if (nameAliases.includes(h)) fieldMap.name = i;
            else if (priceAliases.includes(h)) fieldMap.price = i;
            else if (qtyAliases.includes(h)) fieldMap.quantity = i;
            else if (descAliases.includes(h)) fieldMap.description = i;
        });

        // 驗證必要欄位
        if (fieldMap.name === undefined) {
            return { data: [], error: 'CSV 缺少「名稱」欄位（支援：名稱、name、商品名稱、品名）' };
        }
        if (fieldMap.price === undefined) {
            return { data: [], error: 'CSV 缺少「售價」欄位（支援：售價、price、價格、單價）' };
        }

        // 解析資料行
        const data = [];
        const errors = [];

        for (let i = 1; i < lines.length; i++) {
            const line = lines[i].trim();
            if (!line) continue;  // 跳過空行

            const cols = line.split(',').map(c => c.trim().replace(/^["']|["']$/g, ''));

            const name = cols[fieldMap.name] || '';
            const price = cols[fieldMap.price] || '';
            const qty = fieldMap.quantity !== undefined ? cols[fieldMap.quantity] : '';
            const desc = fieldMap.description !== undefined ? cols[fieldMap.description] : '';

            // 驗證每行必填
            if (!name) {
                errors.push('第 ' + (i + 1) + ' 行缺少商品名稱');
                continue;
            }
            if (!price || isNaN(Number(price))) {
                errors.push('第 ' + (i + 1) + ' 行售價無效');
                continue;
            }

            // 數量缺失或非數字 → 預設 0（無限）
            const parsedQty = qty && !isNaN(Number(qty)) ? String(Math.max(0, Math.floor(Number(qty)))) : '0';

            data.push({
                name: name,
                price: String(price),
                quantity: parsedQty,
                description: desc
            });
        }

        if (data.length === 0) {
            const errMsg = errors.length > 0
                ? '無有效資料。' + errors.join('；')
                : '無有效資料行';
            return { data: [], error: errMsg };
        }

        return {
            data: data,
            error: errors.length > 0 ? '部分行有錯誤（已跳過）：' + errors.join('；') : ''
        };
    };

    /**
     * 處理 CSV 檔案上傳
     * 前端解析 CSV → 填入 items 陣列
     * @param {Event} event - file input change event
     */
    const handleCsvUpload = (event) => {
        const file = event.target.files && event.target.files[0];
        if (!file) return;

        csvError.value = '';
        csvSuccessMsg.value = '';
        csvUploading.value = true;

        const reader = new FileReader();
        reader.onload = (e) => {
            try {
                const text = e.target.result;
                const result = parseCSV(text);

                if (result.error && result.data.length === 0) {
                    // 完全失敗
                    csvError.value = result.error;
                    csvUploading.value = false;
                    return;
                }

                // 將解析結果填入 items
                // 策略：保留已填寫的手動資料 + 追加 CSV 資料
                const newItems = result.data.map(d => ({
                    id: nextId++,
                    name: d.name,
                    price: d.price,
                    quantity: d.quantity,
                    description: d.description,
                    imageId: null,
                    imageUrl: null,
                    imageUploading: false
                }));

                // 保留已有手動填寫資料的 items，只替換空白的
                const filledItems = items.value.filter(item =>
                    item.name.trim() !== '' || String(item.price).trim() !== ''
                );

                items.value = [...filledItems, ...newItems];

                // 如果合併後沒有項目，至少保留 CSV 的
                if (items.value.length === 0 && newItems.length > 0) {
                    items.value = newItems;
                }

                csvSuccessMsg.value = '成功匯入 ' + result.data.length + ' 筆商品';
                if (result.error) {
                    csvSuccessMsg.value += '（' + result.error + '）';
                }

                // 關閉 CSV modal（桌面版）+ 切回手動模式
                showCsvModal.value = false;
                formMode.value = 'manual';
            } catch (err) {
                csvError.value = '檔案讀取失敗：' + err.message;
            } finally {
                csvUploading.value = false;
                // 重置 file input，允許重複上傳同一檔案
                event.target.value = '';
            }
        };
        reader.onerror = () => {
            csvError.value = '檔案讀取失敗';
            csvUploading.value = false;
        };
        reader.readAsText(file);
    };

    /**
     * 拖放狀態（控制上傳區的 .dragging 視覺回饋）
     */
    const isDragging = ref(false);

    /**
     * 處理拖放上傳
     * 從 dataTransfer 取得檔案，走和 handleCsvUpload 相同的 FileReader 路徑
     * @param {DragEvent} event - drop event
     */
    const handleDrop = (event) => {
        isDragging.value = false;
        const file = event.dataTransfer && event.dataTransfer.files && event.dataTransfer.files[0];
        if (!file) return;
        if (!file.name.endsWith('.csv')) {
            csvError.value = '請上傳 .csv 格式的檔案';
            return;
        }

        csvError.value = '';
        csvSuccessMsg.value = '';
        csvUploading.value = true;

        const reader = new FileReader();
        reader.onload = (e) => {
            try {
                const text = e.target.result;
                const result = parseCSV(text);

                if (result.error && result.data.length === 0) {
                    csvError.value = result.error;
                    csvUploading.value = false;
                    return;
                }

                const newItems = result.data.map(d => ({
                    id: nextId++,
                    name: d.name,
                    price: d.price,
                    quantity: d.quantity,
                    description: d.description,
                    imageId: null,
                    imageUrl: null,
                    imageUploading: false
                }));

                const filledItems = items.value.filter(item =>
                    item.name.trim() !== '' || String(item.price).trim() !== ''
                );

                items.value = [...filledItems, ...newItems];

                if (items.value.length === 0 && newItems.length > 0) {
                    items.value = newItems;
                }

                csvSuccessMsg.value = '成功匯入 ' + result.data.length + ' 筆商品';
                if (result.error) {
                    csvSuccessMsg.value += '（' + result.error + '）';
                }

                // 關閉 CSV modal（桌面版）+ 切回手動模式
                showCsvModal.value = false;
                formMode.value = 'manual';
            } catch (err) {
                csvError.value = '檔案讀取失敗：' + err.message;
            } finally {
                csvUploading.value = false;
            }
        };
        reader.onerror = () => {
            csvError.value = '檔案讀取失敗';
            csvUploading.value = false;
        };
        reader.readAsText(file);
    };

    /**
     * 清除 CSV 提示訊息
     */
    const clearCsvMessages = () => {
        csvError.value = '';
        csvSuccessMsg.value = '';
    };

    // ========== 圖片上傳（Phase 60） ==========

    /**
     * 上傳單一商品圖片到暫存端點
     * @param {Object} item - items 中的某個 item 物件
     * @param {Event} event - file input change event
     */
    const uploadItemImage = async (item, event) => {
        const file = event.target && event.target.files && event.target.files[0];
        if (!file) return;

        // 前端驗證
        if (!file.type.match(/^image\/(jpeg|png|webp)$/)) {
            if (window.showToast) {
                window.showToast('僅支援 JPG、PNG、WebP 格式', 'error');
            }
            event.target.value = '';
            return;
        }
        if (file.size > 5 * 1024 * 1024) {
            if (window.showToast) {
                window.showToast('圖片大小不能超過 5MB', 'error');
            }
            event.target.value = '';
            return;
        }

        item.imageUploading = true;

        try {
            const formData = new FormData();
            formData.append('image', file);

            const response = await window.fetch('/wp-json/buygo-plus-one/v1/products/upload-temp-image', {
                method: 'POST',
                headers: {
                    'X-WP-Nonce': window.buygoWpNonce || ''
                },
                credentials: 'include',
                body: formData
            });

            const res = await response.json();

            if (res.success && res.data) {
                item.imageId = res.data.attachment_id;
                item.imageUrl = res.data.image_url;
            } else {
                if (window.showToast) {
                    window.showToast(res.message || '圖片上傳失敗', 'error');
                }
            }
        } catch (err) {
            if (window.showToast) {
                window.showToast('圖片上傳失敗：' + (err.message || '網路錯誤'), 'error');
            }
        } finally {
            item.imageUploading = false;
            event.target.value = '';
        }
    };

    /**
     * 清除商品圖片
     * @param {Object} item - items 中的某個 item 物件
     */
    const removeItemImage = (item) => {
        item.imageId = null;
        item.imageUrl = null;
    };

    // ========== 提交與結果（Phase 59） ==========

    /**
     * 提交中狀態（控制按鈕 disabled + spinner）
     */
    const submitting = ref(false);

    /**
     * 全局提交錯誤訊息（網路錯誤等）
     */
    const submitError = ref('');

    /**
     * 有效商品：name 和 price 都已填寫且 price > 0
     */
    const validItems = computed(() => {
        return items.value.filter(item =>
            item.name.trim() !== '' &&
            String(item.price).trim() !== '' &&
            Number(item.price) > 0
        );
    });

    /**
     * 有效商品數量
     */
    const validItemCount = computed(() => validItems.value.length);

    /**
     * 清除所有 items 的 _error 屬性
     * 在重新提交前呼叫
     */
    const clearItemErrors = () => {
        items.value.forEach(item => {
            delete item._error;
        });
    };

    /**
     * 提交批量上架
     * 呼叫 POST /products/batch-create API
     * 處理三種結果：全部成功 / 部分失敗 / 全部失敗
     */
    const submitBatch = async () => {
        // 防重複
        if (submitting.value) return;
        // 前置驗證
        if (validItemCount.value === 0) return;

        submitting.value = true;
        submitError.value = '';
        clearItemErrors();

        // 構建 payload（後端 API 欄位名稱為 title，非 name）
        const payload = {
            items: validItems.value.map(item => {
                const data = {
                    title: item.name.trim(),
                    price: String(item.price).trim(),
                    quantity: item.quantity || '0',
                    description: item.description.trim()
                };
                if (item.imageId) {
                    data.image_attachment_id = item.imageId;
                }
                return data;
            })
        };

        try {
            // 直接用 fetch 繞過 useApi 的 success 檢查
            // 因為 batch-create 回傳的 success 欄位需要在這裡自行處理三種結果
            const response = await window.fetch('/wp-json/buygo-plus-one/v1/products/batch-create', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': window.buygoWpNonce || '',
                    'Cache-Control': 'no-cache'
                },
                credentials: 'include',
                body: JSON.stringify(payload)
            });

            if (!response.ok) {
                const errBody = await response.json().catch(() => ({}));
                throw new Error(errBody.message || errBody.error || 'HTTP ' + response.status);
            }

            const res = await response.json();

            if (res.failed === 0 && res.created > 0) {
                // 全部成功 — toast + 跳回商品列表
                if (window.showToast) {
                    window.showToast('成功上架 ' + res.created + ' 個商品', 'success');
                }
                setTimeout(() => {
                    goBack();
                }, 800);
            } else if (res.created > 0 && res.failed > 0) {
                // 部分失敗 — 移除成功的，標記失敗的
                if (window.showToast) {
                    window.showToast('成功 ' + res.created + ' 個，失敗 ' + res.failed + ' 個', 'error');
                }

                // 建立 validItems 到原始 items 的對應
                const validItemIds = validItems.value.map(item => item.id);
                const failedIndices = new Set();
                const failedErrors = {};

                if (res.results) {
                    res.results.forEach(result => {
                        if (!result.success) {
                            // result.index 對應 payload.items 的 index，也就是 validItems 的 index
                            const itemId = validItemIds[result.index];
                            failedIndices.add(itemId);
                            failedErrors[itemId] = result.error || '上架失敗';
                        }
                    });
                }

                // 只保留失敗的 + 無效的（未提交的）
                items.value = items.value.filter(item => {
                    // 保留無效商品（不在 validItems 中的）
                    if (!validItemIds.includes(item.id)) return true;
                    // 保留失敗的
                    return failedIndices.has(item.id);
                });

                // 標記錯誤
                items.value.forEach(item => {
                    if (failedErrors[item.id]) {
                        item._error = failedErrors[item.id];
                    }
                });
            } else {
                // 全部失敗（created === 0）
                const errorMsg = (res.results && res.results[0] && res.results[0].error)
                    || res.error || '請檢查商品資料';
                if (window.showToast) {
                    window.showToast('上架失敗：' + errorMsg, 'error');
                }
                // 標記所有 items 的 _error
                if (res.results) {
                    const validItemIds = validItems.value.map(item => item.id);
                    res.results.forEach(result => {
                        if (!result.success && validItemIds[result.index]) {
                            const targetItem = items.value.find(item => item.id === validItemIds[result.index]);
                            if (targetItem) {
                                targetItem._error = result.error || '上架失敗';
                            }
                        }
                    });
                }
            }
        } catch (err) {
            // 網路錯誤 / API 完全無回應
            submitError.value = err.message || '網路錯誤，請稍後重試';
            if (window.showToast) {
                window.showToast('上架失敗：' + (err.message || '網路錯誤，請稍後重試'), 'error');
            }
        } finally {
            submitting.value = false;
        }
    };

    // ========== 導航 ==========

    /**
     * 返回商品列表頁（SPA 導航）
     */
    const goBack = () => {
        if (window.BuyGoRouter) {
            window.BuyGoRouter.spaNavigate('products');
        }
    };

    /**
     * 開始填寫（進入 Phase 58 表單步驟）
     */
    const startFilling = () => {
        if (!canProceed.value) return;
        step.value = 'form';
        initItems(quantity.value);
    };

    // ========== 生命週期 ==========
    onMounted(() => {
        loadQuota();
    });

    return {
        // 步驟
        step,
        // 數量選擇
        selectedPreset,
        customQuantity,
        presetOptions,
        quantity,
        selectPreset,
        onCustomInput,
        // 配額
        quota,
        quotaLoading,
        remaining,
        isOverQuota,
        canProceed,
        loadQuota,
        // 表單（Phase 58）
        items, createEmptyItem, initItems, addItem, removeItem,
        itemCount, quotaUsed, quotaPercent, isFormOverQuota,
        // CSV 匯入（Phase 58 Plan 02）
        formMode, csvError, csvSuccessMsg, csvUploading, isDragging,
        setFormMode, parseCSV, handleCsvUpload, handleDrop, clearCsvMessages,
        // CSV Modal + 範本（Phase 60）
        showCsvModal, downloadCsvTemplate,
        // 圖片上傳（Phase 60）
        uploadItemImage, removeItemImage,
        // 提交（Phase 59）
        submitting, submitError, validItems, validItemCount,
        submitBatch, clearItemErrors,
        // 導航
        goBack,
        startFilling
    };
}
;

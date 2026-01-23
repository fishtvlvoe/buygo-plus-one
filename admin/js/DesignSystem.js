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

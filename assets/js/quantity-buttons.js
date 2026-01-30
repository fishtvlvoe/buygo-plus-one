/**
 * BuyGo FluentCart Quantity Buttons
 *
 * 增強 FluentCart 數量輸入框為 [ - ] [ 數字 ] [ + ] 介面
 * 支援 accessibility (aria-label, keyboard navigation)
 * 支援 FluentCart Vue 元件的 change 事件
 *
 * @package BuyGoPlus
 * @version 0.0.4
 */
(function() {
    'use strict';

    // 設定（從 PHP 傳入）
    const config = window.buygoQuantityConfig || {
        style: 'plus-minus',
        buttonStyle: 'rounded'
    };

    // 如果設定為 default，不執行增強
    if (config.style === 'default') {
        return;
    }

    /**
     * 初始化數量按鈕
     */
    function initQuantityButtons() {
        // 找到 FluentCart 產品頁面的數量輸入框
        // 支援多種可能的 selector
        const selectors = [
            '.fct-single-product-page input[type="number"]',
            '.fct-product-quantity input[type="number"]',
            '[data-fluent-cart-single-product-page] input[type="number"]',
            '.fc-quantity input[type="number"]',
            'input[name="quantity"]'
        ];

        const inputs = document.querySelectorAll(selectors.join(', '));

        inputs.forEach(function(input) {
            // 避免重複處理
            if (input.closest('.buygo-quantity-wrapper')) {
                return;
            }

            // 檢查是否為數量輸入框（排除其他 number input）
            if (input.name !== 'quantity' && !input.classList.contains('qty')) {
                return;
            }

            enhanceInput(input);
        });
    }

    /**
     * 增強單個輸入框
     *
     * @param {HTMLInputElement} input 原始輸入框
     */
    function enhanceInput(input) {
        // 取得 min/max/step 屬性
        const min = parseInt(input.min) || 1;
        const max = parseInt(input.max) || 9999;
        const step = parseInt(input.step) || 1;

        // 建立包裝器
        const wrapper = document.createElement('div');
        wrapper.className = 'buygo-quantity-wrapper';
        if (config.buttonStyle === 'rounded') {
            wrapper.classList.add('rounded');
        }

        // 建立減少按鈕
        const minusBtn = document.createElement('button');
        minusBtn.type = 'button';
        minusBtn.className = 'buygo-quantity-btn minus';
        minusBtn.textContent = '−'; // 使用 minus sign (U+2212)
        minusBtn.setAttribute('aria-label', '減少數量');
        minusBtn.setAttribute('tabindex', '0');

        // 建立增加按鈕
        const plusBtn = document.createElement('button');
        plusBtn.type = 'button';
        plusBtn.className = 'buygo-quantity-btn plus';
        plusBtn.textContent = '+';
        plusBtn.setAttribute('aria-label', '增加數量');
        plusBtn.setAttribute('tabindex', '0');

        // 插入 DOM
        input.parentNode.insertBefore(wrapper, input);
        wrapper.appendChild(minusBtn);
        wrapper.appendChild(input);
        wrapper.appendChild(plusBtn);

        // 更新按鈕狀態
        function updateButtonStates() {
            const current = parseInt(input.value) || min;
            minusBtn.disabled = current <= min;
            plusBtn.disabled = current >= max;
        }

        // 觸發輸入事件（讓 FluentCart Vue 元件感知變化）
        function triggerChange() {
            // 觸發 input 事件（Vue 監聽）
            input.dispatchEvent(new Event('input', { bubbles: true }));
            // 觸發 change 事件（標準表單事件）
            input.dispatchEvent(new Event('change', { bubbles: true }));
            // 更新按鈕狀態
            updateButtonStates();
        }

        // 減少按鈕事件
        minusBtn.addEventListener('click', function(e) {
            e.preventDefault();
            const current = parseInt(input.value) || min;
            if (current > min) {
                input.value = current - step;
                triggerChange();
            }
        });

        // 增加按鈕事件
        plusBtn.addEventListener('click', function(e) {
            e.preventDefault();
            const current = parseInt(input.value) || min;
            if (current < max) {
                input.value = current + step;
                triggerChange();
            }
        });

        // 鍵盤導航支援
        minusBtn.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                minusBtn.click();
            }
        });

        plusBtn.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                plusBtn.click();
            }
        });

        // 輸入框值變化時更新按鈕狀態
        input.addEventListener('input', updateButtonStates);
        input.addEventListener('change', updateButtonStates);

        // 防止手動輸入超出範圍
        input.addEventListener('blur', function() {
            let value = parseInt(input.value) || min;
            value = Math.max(min, Math.min(max, value));
            input.value = value;
            updateButtonStates();
        });

        // 初始化按鈕狀態
        updateButtonStates();
    }

    /**
     * 使用 MutationObserver 監聽動態載入的內容
     *
     * FluentCart 可能使用 Vue 動態渲染，需要監聽 DOM 變化
     */
    function observeDynamicContent() {
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.addedNodes.length) {
                    // 延遲執行以確保 DOM 完全渲染
                    setTimeout(initQuantityButtons, 100);
                }
            });
        });

        // 監聽產品頁面容器
        const containers = [
            '.fct-single-product-page',
            '[data-fluent-cart-single-product-page]',
            '.fc-product-single',
            'main'
        ];

        containers.forEach(function(selector) {
            const container = document.querySelector(selector);
            if (container) {
                observer.observe(container, {
                    childList: true,
                    subtree: true
                });
            }
        });
    }

    /**
     * 初始化
     */
    function init() {
        // 初始化數量按鈕
        initQuantityButtons();

        // 監聽動態內容
        observeDynamicContent();
    }

    // DOM Ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // 暴露初始化方法供外部呼叫
    window.buygoQuantityButtons = {
        init: initQuantityButtons
    };
})();

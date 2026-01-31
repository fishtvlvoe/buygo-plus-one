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

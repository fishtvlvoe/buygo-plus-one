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

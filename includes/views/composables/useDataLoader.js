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

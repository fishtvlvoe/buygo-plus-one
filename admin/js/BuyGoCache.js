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
    TTL: 5 * 60 * 1000,  // 5 分鐘視為「新鮮」

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

        // 延遲 2 秒後開始，不搶當前頁面的載入資源
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
        }, 2000);
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

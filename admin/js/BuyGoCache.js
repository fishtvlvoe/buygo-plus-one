/**
 * BuyGo Frontend Cache
 * 使用 sessionStorage 快取 API 回應，加速重複訪問
 * TTL: 5 分鐘
 */
window.BuyGoCache = {
    TTL: 5 * 60 * 1000,

    get: function(key) {
        try {
            var raw = sessionStorage.getItem('buygo_cache_' + key);
            if (!raw) return null;
            var parsed = JSON.parse(raw);
            if (Date.now() - parsed.ts > this.TTL) {
                sessionStorage.removeItem('buygo_cache_' + key);
                return null;
            }
            return parsed.data;
        } catch (e) { return null; }
    },

    set: function(key, data) {
        try {
            sessionStorage.setItem('buygo_cache_' + key, JSON.stringify({ data: data, ts: Date.now() }));
        } catch (e) { /* sessionStorage 滿了就忽略 */ }
    },

    clear: function(key) {
        if (key) {
            sessionStorage.removeItem('buygo_cache_' + key);
        } else {
            var keys = Object.keys(sessionStorage).filter(function(k) { return k.startsWith('buygo_cache_'); });
            keys.forEach(function(k) { sessionStorage.removeItem(k); });
        }
    }
};

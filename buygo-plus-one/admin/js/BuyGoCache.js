/**
 * BuyGo 前端快取工具
 *
 * 使用 sessionStorage 快取 API 回應，減少重複訪問同頁面時的等待時間。
 * TTL 預設 5 分鐘，關閉瀏覽器分頁後自動清除（sessionStorage 特性）。
 */
window.BuyGoCache = {
    TTL: 5 * 60 * 1000,

    get(key) {
        try {
            var raw = sessionStorage.getItem('buygo_cache_' + key);
            if (!raw) return null;
            var parsed = JSON.parse(raw);
            if (Date.now() - parsed.ts > this.TTL) {
                sessionStorage.removeItem('buygo_cache_' + key);
                return null;
            }
            return parsed.data;
        } catch (e) {
            return null;
        }
    },

    set(key, data) {
        try {
            sessionStorage.setItem('buygo_cache_' + key, JSON.stringify({
                data: data,
                ts: Date.now()
            }));
        } catch (e) {
            // sessionStorage 滿了或不可用，靜默忽略
        }
    },

    clear(key) {
        if (key) {
            sessionStorage.removeItem('buygo_cache_' + key);
        } else {
            var keys = Object.keys(sessionStorage).filter(function(k) {
                return k.startsWith('buygo_cache_');
            });
            keys.forEach(function(k) { sessionStorage.removeItem(k); });
        }
    }
};

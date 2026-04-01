/**
 * BuyGo+1 Service Worker
 *
 * 策略：
 *  - 靜態資源（JS/CSS/字型/圖片）→ Cache First，快取 7 天
 *  - API 請求（/wp-json/buygo-plus-one/）→ Network First，不快取
 *  - HTML 頁面 → Network First，不快取
 */

var CACHE_NAME = 'buygo-sw-v1';

// 靜態資源副檔名正規表達式
var STATIC_EXTS = /\.(js|css|woff2|woff|ttf|png|jpg|jpeg|gif|svg|ico|webp)(\?.*)?$/i;

// 靜態資源快取有效期（7 天，毫秒）
var CACHE_MAX_AGE = 7 * 24 * 60 * 60 * 1000;

// =====================
// Install：預載核心資源（可選，目前留空讓快取自然建立）
// =====================
self.addEventListener('install', function(event) {
    // 跳過等待，立即接管
    self.skipWaiting();
});

// =====================
// Activate：清理舊版本快取
// =====================
self.addEventListener('activate', function(event) {
    event.waitUntil(
        caches.keys().then(function(keys) {
            return Promise.all(
                keys.filter(function(key) {
                    // 刪除非當前版本的快取
                    return key !== CACHE_NAME;
                }).map(function(key) {
                    return caches.delete(key);
                })
            );
        }).then(function() {
            // 立即控制所有分頁（不等待 reload）
            return self.clients.claim();
        })
    );
});

// =====================
// Fetch：根據請求類型選擇快取策略
// =====================
self.addEventListener('fetch', function(event) {
    var url = event.request.url;
    var method = event.request.method;

    // 只處理 GET 請求
    if (method !== 'GET') return;

    // API 請求 → Network First（不快取，由前端 BuyGoCache 處理）
    if (url.indexOf('/wp-json/buygo-plus-one/') !== -1) {
        event.respondWith(fetch(event.request));
        return;
    }

    // 靜態資源 → Cache First（快取 7 天）
    if (STATIC_EXTS.test(url)) {
        event.respondWith(cacheFirst(event.request));
        return;
    }

    // HTML 頁面 → Network First（確保內容最新）
    if (event.request.headers.get('accept') &&
        event.request.headers.get('accept').indexOf('text/html') !== -1) {
        event.respondWith(networkFirst(event.request));
        return;
    }

    // 其餘請求直接走網路
});

/**
 * Cache First 策略
 * 優先從快取回傳；快取不存在或已過期時才向網路請求並更新快取
 */
function cacheFirst(request) {
    return caches.open(CACHE_NAME).then(function(cache) {
        return cache.match(request).then(function(cached) {
            if (cached) {
                // 檢查快取是否過期（透過自訂 response header）
                var cachedAt = cached.headers.get('x-sw-cached-at');
                if (cachedAt && (Date.now() - parseInt(cachedAt, 10)) < CACHE_MAX_AGE) {
                    return cached;
                }
            }

            // 快取不存在或已過期，向網路請求
            return fetch(request).then(function(response) {
                if (!response || response.status !== 200 || response.type === 'opaque') {
                    return response;
                }

                // 複製 response（stream 只能讀一次），注入快取時間戳
                var headers = new Headers(response.headers);
                headers.set('x-sw-cached-at', String(Date.now()));

                var cachedResponse = new Response(response.body, {
                    status:     response.status,
                    statusText: response.statusText,
                    headers:    headers
                });

                cache.put(request, cachedResponse.clone());
                return cachedResponse;
            }).catch(function() {
                // 網路失敗時回傳舊快取（即使過期）
                return cached || new Response('', { status: 503 });
            });
        });
    });
}

/**
 * Network First 策略
 * 優先向網路請求；網路失敗時才回傳快取（HTML 頁面用）
 */
function networkFirst(request) {
    return fetch(request).catch(function() {
        return caches.match(request).then(function(cached) {
            return cached || new Response('', { status: 503 });
        });
    });
}

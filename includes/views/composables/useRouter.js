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

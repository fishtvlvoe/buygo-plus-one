<?php
/**
 * BuyGo Portal SPA Template
 *
 * 單頁應用（SPA）的唯一 HTML 殼。
 * 所有頁面元件統一載入，由 JS 端的 BuyGoRouter 動態切換。
 * 第一次進入時預注入當前頁面的資料，其他頁面由 BuyGoCache 預載。
 */

/**
 * 預注入初始資料（直接呼叫 Service，繞過 REST dispatch）
 */
function buygo_get_initial_data($page) {
    $data = [];

    try {
        switch ($page) {
            case 'products':
                $service = new \BuyGoPlus\Services\ProductService();
                $result = $service->getProductsWithOrderCount();
                if (!empty($result['success'])) {
                    $data['products'] = $result;
                }
                break;

            case 'orders':
                $service = new \BuyGoPlus\Services\OrderService();
                $data['orders'] = $service->getOrders(['page' => 1, 'per_page' => 30]);
                break;

            case 'dashboard':
                $service = new \BuyGoPlus\Services\DashboardService();
                $data['stats'] = $service->calculateStats();
                $data['revenue'] = $service->getRevenueTrend(30);
                $data['products'] = $service->getProductOverview();
                $data['activities'] = $service->getRecentActivities(10);
                $data['profit'] = $service->calculateProfitStats();
                break;

            case 'settings':
                $data['templates'] = ['success' => true, 'data' => \BuyGoPlus\Services\SettingsService::get_templates()];
                $data['helpers'] = ['success' => true, 'data' => \BuyGoPlus\Services\SettingsService::get_helpers_with_line_status()];
                break;
        }
    } catch (\Exception $e) {
        // 直出失敗不影響頁面，前端會自動 fallback 到 API 載入
    }

    return $data;
}

// 檢查權限
if (!is_user_logged_in()) {
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    // LINE 瀏覽器：直接跳轉 LINE 登入（不帶 redirect，登入後由角色分流決定去向）
    // 注意：不再傳 redirect 參數，避免 Cloudflare WAF 把斜線吃掉導致壞掉的跳轉 URL
    if (stripos($user_agent, 'Line/') !== false) {
        wp_safe_redirect(home_url('/line-hub/auth/'));
        exit;
    }

    // 其他瀏覽器：跳轉 WordPress 登入（不帶 redirect_to，由 login_redirect filter 處理角色分流）
    wp_safe_redirect(wp_login_url());
    exit;
}

// 已登入，檢查是否有賣場後台權限
$has_portal_access = current_user_can('manage_options')
    || current_user_can('buygo_admin')
    || current_user_can('buygo_helper');

// 買家：redirect 到 FluentCart 會員中心（含 buygo_embed CSS 注入隱藏 WordPress 外框）
if (!$has_portal_access) {
    $buyer_url = home_url('/my-account/?buygo_embed=1');
    wp_safe_redirect($buyer_url);
    exit;
}

// SPA：從 URL 取得初始頁面（用於預注入資料）
$current_page = get_query_var('buygo_page', 'dashboard');

// 取得用戶權限（傳給前端做 SPA 頁面級權限控制）
$user_permissions = [];
$permission_keys = ['products', 'orders', 'shipments', 'customers', 'settings'];
foreach ($permission_keys as $perm) {
    $user_permissions[$perm] = \BuyGoPlus\Services\SettingsService::helper_can($perm);
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>BuyGo+1 賣場後台</title>

    <!-- Tailwind CSS 本地打包（取代 CDN） -->
    <link rel="stylesheet" href="<?php echo plugins_url('dist/app.css', BUYGO_PLUS_ONE_PLUGIN_FILE); ?>">

    <!-- 字型（本地，避免 Google Fonts CDN 延遲） -->
    <link rel="stylesheet" href="<?php echo plugins_url('assets/fonts/fonts.css', BUYGO_PLUS_ONE_PLUGIN_FILE); ?>">

    <!-- Design System CSS（合併外部檔，瀏覽器可快取；用 filemtime 當版本號避免舊快取） -->
    <link rel="stylesheet" href="<?php $css_path = BUYGO_PLUS_ONE_PLUGIN_DIR . 'dist/design-system.css'; echo plugins_url('dist/design-system.css', BUYGO_PLUS_ONE_PLUGIN_FILE) . '?v=' . (file_exists($css_path) ? filemtime($css_path) : '1'); ?>">

    <!-- 字體 -->
    <style>
        body { font-family: 'Open Sans', sans-serif; }
        h1, h2, h3, h4, h5, h6 { font-family: 'Poppins', sans-serif; }
    </style>
</head>
<body class="bg-slate-50">
    <div id="buygo-app">
        <!-- Skeleton Loading：Vue mount 後自動替換 -->
        <div class="buygo-skeleton">
            <div class="buygo-skeleton-sidebar">
                <div class="buygo-skeleton-logo"></div>
                <div class="buygo-skeleton-menu-item active"></div>
                <div class="buygo-skeleton-menu-item"></div>
                <div class="buygo-skeleton-menu-item"></div>
                <div class="buygo-skeleton-menu-item"></div>
                <div class="buygo-skeleton-menu-item"></div>
                <div class="buygo-skeleton-menu-item"></div>
                <div class="buygo-skeleton-menu-item"></div>
            </div>
            <div class="buygo-skeleton-content">
                <div class="buygo-skeleton-header"></div>
                <div class="buygo-skeleton-table">
                    <div class="buygo-skeleton-row" style="width:100%"></div>
                    <div class="buygo-skeleton-row" style="width:95%"></div>
                    <div class="buygo-skeleton-row" style="width:90%"></div>
                    <div class="buygo-skeleton-row" style="width:97%"></div>
                    <div class="buygo-skeleton-row" style="width:88%"></div>
                    <div class="buygo-skeleton-row" style="width:93%"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- 共用組件 -->
    <?php require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'components/shared/new-sidebar.php'; ?>
    <?php require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'components/shared/smart-search-box.php'; ?>
    <?php require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'components/shared/page-header.php'; ?>
    <?php require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'components/shared/pagination.php'; ?>

    <!-- Vue 3 + SortableJS + VueDraggable（全部本地，無 CDN） -->
    <script src="<?php echo plugins_url('assets/js/vue.global.prod.js', BUYGO_PLUS_ONE_PLUGIN_FILE); ?>"></script>
    <script src="<?php echo plugins_url('assets/js/sortable.min.js', BUYGO_PLUS_ONE_PLUGIN_FILE); ?>"></script>
    <script src="<?php echo plugins_url('assets/js/vuedraggable.umd.min.js', BUYGO_PLUS_ONE_PLUGIN_FILE); ?>"></script>

    <!-- BuyGo App JS（14 個模組合併外部檔，瀏覽器可快取；Vue 之後載入） -->
    <script src="<?php $js_path = BUYGO_PLUS_ONE_PLUGIN_DIR . 'dist/app.js'; echo plugins_url('dist/app.js', BUYGO_PLUS_ONE_PLUGIN_FILE) . '?v=' . (file_exists($js_path) ? filemtime($js_path) : '1'); ?>"></script>

    <!-- 全域變數 -->
    <script>
        window.buygoWpNonce = '<?php echo wp_create_nonce("wp_rest"); ?>';
        window.buygoUserPermissions = <?php echo wp_json_encode($user_permissions); ?>;
    </script>

    <?php
    // 預注入首頁資料：PHP 直接查詢 DB 並注入 window.buygoInitialData
    // 前端讀取此資料可免去一次 API round-trip，大幅加速首次載入
    $initial_data = buygo_get_initial_data($current_page);
    if (!empty($initial_data)) {
        echo '<script>window.buygoInitialData = ' . wp_json_encode($initial_data) . ';</script>';
    }
    ?>

    <!-- SPA：載入所有頁面元件 -->
    <?php
    $page_partials = [
        'dashboard', 'products', 'orders',
        'shipment-products', 'shipment-details',
        'customers', 'settings', 'search',
        'batch-create'
    ];
    foreach ($page_partials as $partial) {
        $file = BUYGO_PLUS_ONE_PLUGIN_DIR . 'admin/partials/' . $partial . '.php';
        if (file_exists($file)) {
            require $file;
        }
    }
    ?>

    <script>
    const { createApp } = Vue;

    // SPA 元件對應表
    const pageComponents = {
        'dashboard':        typeof DashboardPageComponent !== 'undefined' ? DashboardPageComponent : null,
        'products':         typeof ProductsPageComponent !== 'undefined' ? ProductsPageComponent : null,
        'orders':           typeof OrdersPageComponent !== 'undefined' ? OrdersPageComponent : null,
        'shipment-products':typeof ShipmentProductsPageComponent !== 'undefined' ? ShipmentProductsPageComponent : null,
        'shipment-details': typeof ShipmentDetailsPageComponent !== 'undefined' ? ShipmentDetailsPageComponent : null,
        'customers':        typeof CustomersPageComponent !== 'undefined' ? CustomersPageComponent : null,
        'settings':         typeof SettingsPageComponent !== 'undefined' ? SettingsPageComponent : null,
        'search':           typeof SearchPageComponent !== 'undefined' ? SearchPageComponent : null,
        'batch-create':     typeof BatchCreatePageComponent !== 'undefined' ? BatchCreatePageComponent : null
    };

    // 建立 SPA 主 App
    const app = createApp({
        components: {
            NewSidebar: NewSidebarComponent,
            PageHeader: PageHeader,
            'page-header-component': PageHeaderComponent,
            SmartSearchBox: BuyGoSmartSearchBox
        },
        data() {
            return {
                currentPage: BuyGoRouter.parsePath(),
                isSidebarCollapsed: false
            }
        },
        computed: {
            currentComponent() {
                return pageComponents[this.currentPage] || null;
            }
        },
        methods: {
            handleGlobalSearchSelect(item) {
                if (item.url) {
                    // SPA 內部導航
                    var match = item.url.match(/\/buygo-portal\/([a-z-]+)/);
                    if (match && pageComponents[match[1]]) {
                        BuyGoRouter.spaNavigate(match[1]);
                    } else {
                        window.location.href = item.url;
                    }
                }
            },
            toggleSidebar() {
                this.isSidebarCollapsed = !this.isSidebarCollapsed;
            },
            onPageChange(page) {
                this.currentPage = page;
                // 更新頁面標題
                var titles = {
                    'dashboard': '儀表板',
                    'products': '商品',
                    'orders': '訂單',
                    'shipment-products': '備貨',
                    'shipment-details': '出貨',
                    'customers': '客戶',
                    'settings': '設定',
                    'search': '搜尋',
                    'batch-create': '批量上架'
                };
                document.title = 'BuyGo+1 ' + (titles[page] || '賣場後台');
            }
        },
        mounted() {
            // 初始化 SPA Router
            var self = this;
            BuyGoRouter.initSPA(function(page) {
                self.onPageChange(page);
            });
        },
        template: `
            <div>
                <NewSidebar
                    :currentPage="currentPage"
                    :collapsed="isSidebarCollapsed"
                    @toggle="toggleSidebar"
                />
                <div class="min-h-screen transition-all duration-300"
                    :class="isSidebarCollapsed ? 'md:ml-20' : 'md:ml-48 lg:ml-64'">
                    <div v-if="currentComponent" class="buygo-page-loaded">
                        <component :is="currentComponent" :key="currentPage" />
                    </div>
                    <main v-else class="p-6">
                        <div class="max-w-7xl mx-auto">
                            <div class="buygo-content-skeleton h-8 w-48 mb-6"></div>
                            <div class="bg-white rounded-xl p-6">
                                <div class="buygo-content-skeleton h-12 w-full mb-3"></div>
                                <div class="buygo-content-skeleton h-12 w-[95%] mb-3"></div>
                                <div class="buygo-content-skeleton h-12 w-[90%] mb-3"></div>
                                <div class="buygo-content-skeleton h-12 w-[97%] mb-3"></div>
                            </div>
                        </div>
                    </main>
                </div>
            </div>
        `
    });

    // 全域註冊 Header 元件
    app.component('page-header-component', PageHeaderComponent);

    // 自訂指令：v-focus — 元素掛載後自動 focus 並選取全部文字
    app.directive('focus', {
        mounted(el) {
            el.focus();
            el.select();
        }
    });

    app.mount('#buygo-app');

    // 預載其他頁面資料
    if (window.BuyGoCache && window.BuyGoCache.preload) {
        window.BuyGoCache.preload(window.buygoWpNonce);
    }
    </script>

    <!-- Service Worker 註冊：快取靜態資源（JS/CSS/字型/圖片），加速二次造訪 -->
    <script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function() {
            navigator.serviceWorker.register('<?php echo esc_url(plugins_url('assets/sw.js', dirname(__FILE__, 2))); ?>').then(function(registration) {
                // 註冊成功，Service Worker 範圍：外掛 assets/ 目錄
            }).catch(function(err) {
                // 註冊失敗不影響頁面功能
            });
        });
    }
    </script>
</body>
</html>

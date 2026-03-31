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
    $redirect_to = home_url($_SERVER['REQUEST_URI']);
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    // LINE 瀏覽器：直接跳轉 LINE 登入
    if (stripos($user_agent, 'Line/') !== false) {
        $line_login_url = home_url('/nextend_social_login/?loginSocial=line&redirect=' . urlencode($redirect_to));
        wp_redirect($line_login_url);
        exit;
    }

    // 其他瀏覽器：跳轉 WordPress 登入頁面
    wp_redirect(wp_login_url($redirect_to));
    exit;
}

// 已登入，檢查是否有賣場後台權限
$has_portal_access = current_user_can('manage_options')
    || current_user_can('buygo_admin')
    || current_user_can('buygo_helper');

// 買家標記
$is_buyer = !$has_portal_access;
// ?embed=1 讓 FluentCartCustomerPortal::maybeRenderEmbedPage() 攔截，
// 輸出不含 WordPress header/footer 的精簡頁面，避免 iframe 載入整個 WordPress 頁面
$buyer_account_url = home_url('/my-account/?embed=1');

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

    <!-- Google Fonts（加 preconnect + swap） -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">

    <!-- Design System CSS (inline 繞過 InstaWP WAF) -->
    <style>
    <?php
    include BUYGO_PLUS_ONE_PLUGIN_DIR . 'design-system/tokens/colors.css';
    include BUYGO_PLUS_ONE_PLUGIN_DIR . 'design-system/tokens/spacing.css';
    include BUYGO_PLUS_ONE_PLUGIN_DIR . 'design-system/tokens/typography.css';
    include BUYGO_PLUS_ONE_PLUGIN_DIR . 'design-system/tokens/effects.css';
    include BUYGO_PLUS_ONE_PLUGIN_DIR . 'design-system/components/header.css';
    include BUYGO_PLUS_ONE_PLUGIN_DIR . 'design-system/components/smart-search-box.css';
    include BUYGO_PLUS_ONE_PLUGIN_DIR . 'design-system/components/table.css';
    include BUYGO_PLUS_ONE_PLUGIN_DIR . 'design-system/components/card.css';
    include BUYGO_PLUS_ONE_PLUGIN_DIR . 'design-system/components/button.css';
    include BUYGO_PLUS_ONE_PLUGIN_DIR . 'design-system/components/form.css';
    include BUYGO_PLUS_ONE_PLUGIN_DIR . 'design-system/components/status-tag.css';
    include BUYGO_PLUS_ONE_PLUGIN_DIR . 'design-system/components/pagination.css';
    ?>
    * { box-sizing: border-box; }
    a { text-decoration: none; }
    button { font-family: inherit; }
    .truncate { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; border-width: 0; }
    .hidden-mobile { display: none; }
    @media (min-width: 768px) { .hidden-mobile { display: block; } }
    .hidden-desktop { display: block; }
    @media (min-width: 768px) { .hidden-desktop { display: none; } }
    /* Skeleton Loading */
    .buygo-skeleton { display: flex; min-height: 100vh; background: #f8fafc; }
    .buygo-skeleton-sidebar { width: 12rem; background: #fff; border-right: 1px solid #e2e8f0; padding: 1.5rem 1rem; }
    .buygo-skeleton-logo { height: 2rem; width: 6rem; background: #e2e8f0; border-radius: 0.5rem; margin-bottom: 2rem; }
    .buygo-skeleton-menu-item { height: 2.5rem; background: #f1f5f9; border-radius: 0.5rem; margin-bottom: 0.5rem; }
    .buygo-skeleton-menu-item.active { background: #dbeafe; }
    .buygo-skeleton-content { flex: 1; padding: 1.5rem; }
    .buygo-skeleton-header { height: 2rem; width: 12rem; background: #e2e8f0; border-radius: 0.5rem; margin-bottom: 1.5rem; }
    .buygo-skeleton-table { background: #fff; border-radius: 0.75rem; padding: 1.5rem; }
    .buygo-skeleton-row { height: 3rem; background: linear-gradient(90deg, #f1f5f9 25%, #e2e8f0 50%, #f1f5f9 75%); background-size: 200% 100%; border-radius: 0.5rem; margin-bottom: 0.75rem; animation: buygo-shimmer 1.5s infinite; }
    @keyframes buygo-shimmer { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }
    @media (max-width: 768px) { .buygo-skeleton-sidebar { display: none; } .buygo-skeleton-content { padding: 1rem; } }
    /* SPA Page Transition */
    .buygo-page-enter { opacity: 0; }
    .buygo-page-loaded { opacity: 1; transition: opacity 0.15s ease-in; }
    /* Page Content Skeleton（SPA 切換時各頁面的 loading 狀態） */
    .buygo-content-skeleton {
        animation: buygo-shimmer 1.5s infinite;
        background: linear-gradient(90deg, #f1f5f9 25%, #e2e8f0 50%, #f1f5f9 75%);
        background-size: 200% 100%;
        border-radius: 0.5rem;
    }
    </style>

    <!-- BuyGo Core JS Modules -->
    <script><?php include BUYGO_PLUS_ONE_PLUGIN_DIR . 'admin/js/RouterMixin.js'; ?></script>
    <script><?php include BUYGO_PLUS_ONE_PLUGIN_DIR . 'admin/js/DesignSystem.js'; ?></script>
    <script><?php include BUYGO_PLUS_ONE_PLUGIN_DIR . 'admin/js/BuyGoCache.js'; ?></script>
    <!-- SPA Router -->
    <script><?php include BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/views/composables/useRouter.js'; ?></script>

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
    <script><?php include BUYGO_PLUS_ONE_PLUGIN_DIR . 'components/shared/header-component.js'; ?></script>

    <!-- Vue 3 + SortableJS + VueDraggable（全部本地，無 CDN） -->
    <script src="<?php echo plugins_url('assets/js/vue.global.prod.js', BUYGO_PLUS_ONE_PLUGIN_FILE); ?>"></script>
    <script src="<?php echo plugins_url('assets/js/sortable.min.js', BUYGO_PLUS_ONE_PLUGIN_FILE); ?>"></script>
    <script src="<?php echo plugins_url('assets/js/vuedraggable.umd.min.js', BUYGO_PLUS_ONE_PLUGIN_FILE); ?>"></script>

    <!-- 全站 Composables -->
    <script><?php include BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/views/composables/useCurrency.js'; ?></script>
    <script><?php include BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/views/composables/useApi.js'; ?></script>
    <script><?php include BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/views/composables/usePermissions.js'; ?></script>
    <script><?php include BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/views/composables/useDataLoader.js'; ?></script>

    <!-- 頁面 Composables（SPA：全部載入） -->
    <script><?php include BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/views/composables/useOrders.js'; ?></script>
    <script><?php include BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/views/composables/useProducts.js'; ?></script>
    <script><?php include BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/views/composables/useShipmentProducts.js'; ?></script>
    <script><?php include BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/views/composables/useShipmentDetails.js'; ?></script>
    <script><?php include BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/views/composables/useBatchCreate.js'; ?></script>

    <!-- 全域變數 -->
    <script>
        window.buygoWpNonce = '<?php echo wp_create_nonce("wp_rest"); ?>';
        window.buygoUserPermissions = <?php echo wp_json_encode($user_permissions); ?>;
        window.buygoIsBuyer = <?php echo $is_buyer ? 'true' : 'false'; ?>;
        window.buygoAccountUrl = '<?php echo esc_js($buyer_account_url); ?>';
    </script>

    <?php
    // 預注入初始頁面資料
    $initial_data = buygo_get_initial_data($current_page);
    if (!empty($initial_data)) :
    ?>
    <script>
        window.buygoInitialData = <?php echo wp_json_encode($initial_data); ?>;
        window.buygoInitialPage = '<?php echo esc_js($current_page); ?>';
    </script>
    <?php endif; ?>

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
            // 買家側邊欄選單項目（儀表板、訂單進度、會員中心）
            var buyerMenuItems = [
                {
                    id: 'my-account',
                    label: '儀表板',
                    url: (window.buygoAccountUrl || '') + '#/',
                    icon: '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"></path></svg>'
                },
                {
                    id: 'order-tracking',
                    label: '訂單進度',
                    url: (window.buygoAccountUrl || '') + '#/order-tracking',
                    icon: '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>'
                },
                {
                    id: 'purchase-history',
                    label: '購買記錄',
                    url: (window.buygoAccountUrl || '') + '#/purchase-history',
                    icon: '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>'
                }
            ];
            return {
                currentPage: BuyGoRouter.parsePath(),
                isSidebarCollapsed: false,
                accountUrl: window.buygoAccountUrl || '',
                // 買家側邊欄狀態
                buyerMenuItems: buyerMenuItems,
                buyerActivePage: 'my-account',
                iframeUrl: window.buygoAccountUrl || ''
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
            // 買家側邊欄點擊：更新 active 狀態並切換 iframe URL
            onBuyerNavClick(item) {
                this.buyerActivePage = item.id;
                this.iframeUrl = item.url;
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
        template: window.buygoIsBuyer ? `
            <div style="display:flex;min-height:100vh;">
                <!-- 買家精簡側邊欄 -->
                <aside style="width:200px;background:#fff;border-right:1px solid #e2e8f0;display:flex;flex-direction:column;position:fixed;top:0;left:0;height:100vh;z-index:20;">
                    <!-- Logo -->
                    <div style="height:64px;display:flex;align-items:center;padding:0 24px;border-bottom:1px solid #f1f5f9;">
                        <svg style="width:32px;height:32px;color:#3b82f6;flex-shrink:0;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                        </svg>
                        <span style="margin-left:8px;font-weight:700;font-size:18px;color:#3b82f6;">BuyGo+1</span>
                    </div>
                    <!-- 買家選單項目 -->
                    <nav style="flex:1;padding:16px 0;">
                        <a v-for="item in buyerMenuItems" :key="item.id"
                           :href="item.url"
                           :style="{
                               display: 'flex',
                               alignItems: 'center',
                               padding: '12px 24px',
                               textDecoration: 'none',
                               color: buyerActivePage === item.id ? '#3b82f6' : '#475569',
                               background: buyerActivePage === item.id ? '#eff6ff' : 'transparent',
                               borderRight: buyerActivePage === item.id ? '2px solid #3b82f6' : '2px solid transparent',
                               fontWeight: buyerActivePage === item.id ? '500' : '400',
                               fontSize: '14px',
                               transition: 'all 0.15s'
                           }"
                           @click="onBuyerNavClick(item)">
                            <span v-html="item.icon" style="width:20px;height:20px;flex-shrink:0;"></span>
                            <span style="margin-left:12px;white-space:nowrap;">{{ item.label }}</span>
                        </a>
                    </nav>
                </aside>
                <!-- 買家主內容區：iframe 嵌入 FluentCart 精簡頁面 -->
                <div style="margin-left:200px;flex:1;display:flex;flex-direction:column;">
                    <iframe :src="iframeUrl" style="flex:1;width:100%;height:100vh;border:none;" allowfullscreen></iframe>
                </div>
            </div>
        ` : `
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

    app.mount('#buygo-app');

    // 預載其他頁面資料
    if (window.BuyGoCache && window.BuyGoCache.preload) {
        window.BuyGoCache.preload(window.buygoWpNonce);
    }
    </script>
</body>
</html>

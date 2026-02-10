<?php
/**
 * 預注入初始資料（消除 Loading 畫面）
 *
 * 在 PHP 端預先查詢各頁面的初始資料，透過 inline script 注入到前端。
 * Vue 元件啟動時直接使用預注入資料，不再發 API 請求，消除 Loading 狀態。
 *
 * 使用 WordPress rest_do_request() 做內部請求，完全複用現有 API 邏輯。
 */
function buygo_get_initial_data($page) {
    // 確保 REST API 已初始化
    if (!did_action('rest_api_init')) {
        do_action('rest_api_init');
    }

    $data = [];

    try {
        switch ($page) {
            case 'orders':
                $request = new \WP_REST_Request('GET', '/buygo-plus-one/v1/orders');
                $request->set_param('page', 1);
                $request->set_param('per_page', 100);
                $response = rest_do_request($request);
                if (!is_wp_error($response) && $response->get_status() === 200) {
                    $data['orders'] = $response->get_data();
                }
                break;

            case 'products':
                $request = new \WP_REST_Request('GET', '/buygo-plus-one/v1/products');
                $response = rest_do_request($request);
                if (!is_wp_error($response) && $response->get_status() === 200) {
                    $data['products'] = $response->get_data();
                }
                break;

            case 'shipment-products':
                $request = new \WP_REST_Request('GET', '/buygo-plus-one/v1/shipments');
                $request->set_param('per_page', -1);
                $response = rest_do_request($request);
                if (!is_wp_error($response) && $response->get_status() === 200) {
                    $data['shipments'] = $response->get_data();
                }
                break;

            case 'shipment-details':
                $request = new \WP_REST_Request('GET', '/buygo-plus-one/v1/shipments');
                $request->set_param('per_page', -1);
                $response = rest_do_request($request);
                if (!is_wp_error($response) && $response->get_status() === 200) {
                    $data['shipments'] = $response->get_data();
                }
                break;

            case 'customers':
                $request = new \WP_REST_Request('GET', '/buygo-plus-one/v1/customers');
                $request->set_param('page', 1);
                $request->set_param('per_page', 20);
                $response = rest_do_request($request);
                if (!is_wp_error($response) && $response->get_status() === 200) {
                    $data['customers'] = $response->get_data();
                }
                break;

            case 'dashboard':
                // 儀表板有 4 個 API，全部預查
                $endpoints = [
                    'stats' => '/buygo-plus-one/v1/dashboard/stats',
                    'revenue' => '/buygo-plus-one/v1/dashboard/revenue',
                    'products' => '/buygo-plus-one/v1/dashboard/products',
                    'activities' => '/buygo-plus-one/v1/dashboard/activities',
                ];
                foreach ($endpoints as $key => $route) {
                    $request = new \WP_REST_Request('GET', $route);
                    if ($key === 'revenue') {
                        $request->set_param('period', 30);
                    }
                    if ($key === 'activities') {
                        $request->set_param('limit', 10);
                    }
                    $response = rest_do_request($request);
                    if (!is_wp_error($response) && $response->get_status() === 200) {
                        $data[$key] = $response->get_data();
                    }
                }
                break;

            case 'settings':
                // 設定頁需要模板和助手列表
                $endpoints = [
                    'templates' => '/buygo-plus-one/v1/settings/templates',
                    'helpers' => '/buygo-plus-one/v1/settings/helpers',
                ];
                foreach ($endpoints as $key => $route) {
                    $request = new \WP_REST_Request('GET', $route);
                    $response = rest_do_request($request);
                    if (!is_wp_error($response) && $response->get_status() === 200) {
                        $data[$key] = $response->get_data();
                    }
                }
                break;
        }
    } catch (\Exception $e) {
        // 預注入失敗不應該阻擋頁面載入，靜默失敗，Vue 會 fallback 到 API
        error_log('BuyGo initial data injection failed: ' . $e->getMessage());
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

if (!$has_portal_access) {
    require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/views/no-access.php';
    exit;
}

$current_page = get_query_var('buygo_page', 'dashboard');
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>BuyGo+1 賣場後台</title>
    
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Tailwind 自訂配置 -->
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#2563EB',
                        accent: '#F97316',
                        bgMain: '#F8FAFC',
                        success: '#10B981',
                        warning: '#F59E0B',
                    }
                }
            }
        }
    </script>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
    
    <!-- Design System CSS (統一設計系統 - 2026-01-27) -->
    <link rel="stylesheet" href="<?php echo esc_url(BUYGO_PLUS_ONE_PLUGIN_URL . 'design-system/index.css'); ?>">

    <!-- BuyGo Core JS Modules (新路徑：admin/js/) -->
    <script src="<?php echo esc_url(BUYGO_PLUS_ONE_PLUGIN_URL . 'admin/js/RouterMixin.js'); ?>"></script>
    <script src="<?php echo esc_url(BUYGO_PLUS_ONE_PLUGIN_URL . 'admin/js/DesignSystem.js'); ?>"></script>
    <script><?php include BUYGO_PLUS_ONE_PLUGIN_DIR . 'admin/js/BuyGoCache.js'; ?></script>

    <style>
        body {
            font-family: 'Open Sans', sans-serif;
        }
        h1, h2, h3, h4, h5, h6 {
            font-family: 'Poppins', sans-serif;
        }
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

    <!-- 載入組件定義 -->
    <?php require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'components/shared/new-sidebar.php'; ?>
    <?php require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'components/shared/smart-search-box.php'; ?>
    <?php require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'components/shared/page-header.php'; ?>
    <?php require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'components/shared/pagination.php'; ?>

    <!-- 獨立 Header 元件 (Vue Component) -->
    <script src="<?php echo esc_url(BUYGO_PLUS_ONE_PLUGIN_URL . 'components/shared/header-component.js'); ?>"></script>
    
    <!-- Vue 3 CDN -->
    <script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>
    <!-- SortableJS (vuedraggable 依賴) -->
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    <!-- VueDraggable CDN -->
    <script src="https://cdn.jsdelivr.net/npm/vuedraggable@4.1.0/dist/vuedraggable.umd.min.js"></script>

    <!-- useCurrency Composable (全站幣別處理邏輯) -->
    <script src="<?php echo esc_url(BUYGO_PLUS_ONE_PLUGIN_URL . 'includes/views/composables/useCurrency.js'); ?>"></script>

    <!-- Global WP Nonce for REST API -->
    <script>
        window.buygoWpNonce = '<?php echo wp_create_nonce("wp_rest"); ?>';
    </script>

    <?php
    // 預注入初始資料（消除 Loading 畫面）
    $initial_data = buygo_get_initial_data($current_page);
    if (!empty($initial_data)) :
    ?>
    <script>
        window.buygoInitialData = <?php echo wp_json_encode($initial_data); ?>;
    </script>
    <?php endif; ?>

    <?php
    // 載入頁面元件（如果存在）- 新路徑：admin/partials/
    $page_file = BUYGO_PLUS_ONE_PLUGIN_DIR . 'admin/partials/' . $current_page . '.php';
    $has_page_component = false;
    if (file_exists($page_file)) {
        require $page_file;
        $has_page_component = true;
    }
    ?>
    
    <script>
    const { createApp } = Vue;

    // 載入頁面元件（如果存在）
    <?php
    $page_component_name = null;
    if ($has_page_component) {
        // 根據頁面名稱決定元件名稱
        $component_map = [
            'products' => 'ProductsPageComponent',
            'orders' => 'OrdersPageComponent',
            'shipment-products' => 'ShipmentProductsPageComponent',
            'shipment-details' => 'ShipmentDetailsPageComponent',
            'customers' => 'CustomersPageComponent',
            'settings' => 'SettingsPageComponent',
            'dashboard' => 'DashboardPageComponent',
            'search' => 'SearchPageComponent'
        ];
        $page_component_name = $component_map[$current_page] ?? null;
    }
    ?>
    <?php if ($page_component_name): ?>
    const pageComponent = <?php echo $page_component_name; ?>;
    <?php endif; ?>

    // 建立主 App
    const app = createApp({
        components: {
            NewSidebar: NewSidebarComponent,
            PageHeader: PageHeader,
            'page-header-component': PageHeaderComponent,
            SmartSearchBox: BuyGoSmartSearchBox<?php echo $page_component_name ? ', PageContent: pageComponent' : ''; ?>
        },
        data() {
            return {
                currentPage: '<?php echo esc_js($current_page); ?>',
                isSidebarCollapsed: false
            }
        },
        methods: {
            handleGlobalSearchSelect(item) {
                // 根據類型導向不同頁面
                if (item.url) {
                    window.location.href = item.url;
                }
            },
            toggleSidebar() {
                this.isSidebarCollapsed = !this.isSidebarCollapsed;
            }
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
                    <!-- 頁面內容 -->
                    <?php if ($page_component_name): ?>
                    <PageContent />
                    <?php else: ?>
                    <main class="p-6">
                        <div class="max-w-7xl mx-auto">
                            <h1 class="text-3xl font-bold text-gray-900 mb-4">BuyGo+1 載入中...</h1>
                            <p class="text-gray-600">當前頁面：{{ currentPage }}</p>
                        </div>
                    </main>
                    <?php endif; ?>
                </div>
            </div>
        `
    });

    // 全域註冊 Header 元件（讓所有頁面元件都能使用）
    app.component('page-header-component', PageHeaderComponent);

    app.mount('#buygo-app');
    </script>
</body>
</html>

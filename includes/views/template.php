<?php
// 檢查權限
if (!is_user_logged_in()) {
    wp_redirect(wp_login_url(home_url($_SERVER['REQUEST_URI'])));
    exit;
}

$current_page = get_query_var('buygo_page', 'dashboard');
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
    <div id="buygo-app"></div>

    <!-- 載入組件定義 -->
    <?php require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'components/shared/new-sidebar.php'; ?>
    <?php require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'components/shared/smart-search-box.php'; ?>
    <?php require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'components/shared/page-header.php'; ?>
    <?php require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'components/shared/pagination.php'; ?>
    
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
            SmartSearchBox: BuyGoSmartSearchBox<?php echo $page_component_name ? ', PageContent: pageComponent' : ''; ?>
        },
        data() {
            return {
                currentPage: '<?php echo esc_js($current_page); ?>'
            }
        },
        methods: {
            handleGlobalSearchSelect(item) {
                // 根據類型導向不同頁面
                if (item.url) {
                    window.location.href = item.url;
                }
            }
        },
        template: `
            <div>
                <NewSidebar :currentPage="currentPage" />
                <div class="md:ml-20 lg:ml-64 min-h-screen transition-all duration-300">
                    <!-- 全域 Header（包含搜尋框和通知） -->
                    <header class="bg-white border-b border-slate-200 sticky top-0 z-40">
                        <div class="flex items-center justify-between px-6 py-3">
                            <!-- Logo/品牌（手機版顯示） -->
                            <div class="lg:hidden">
                                <span class="text-lg font-bold text-primary">BuyGo+1</span>
                            </div>

                            <!-- 全域搜尋框 -->
                            <div class="flex-1 max-w-2xl mx-4">
                                <smart-search-box
                                    api-endpoint="/wp-json/buygo-plus-one/v1/global-search"
                                    :search-fields="['name', 'invoice_no', 'full_name']"
                                    placeholder="搜尋訂單、商品、客戶、出貨單..."
                                    :global-search="true"
                                    :enable-history="true"
                                    :max-history="5"
                                    :max-suggestions="5"
                                    :show-image="false"
                                    :show-status="false"
                                    display-field="display_field"
                                    display-sub-field="display_sub_field"
                                    @select="handleGlobalSearchSelect"
                                />
                            </div>

                            <!-- 通知鈴鐺 -->
                            <div class="flex items-center gap-2">
                                <button class="relative p-2 text-slate-400 hover:text-primary hover:bg-slate-50 rounded-lg transition">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                                    </svg>
                                    <!-- 未讀提示點 -->
                                    <span class="absolute top-1.5 right-1.5 w-2 h-2 bg-red-500 rounded-full"></span>
                                </button>
                            </div>
                        </div>
                    </header>

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

    app.mount('#buygo-app');
    </script>
</body>
</html>

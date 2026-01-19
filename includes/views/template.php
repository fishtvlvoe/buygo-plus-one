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
    
    <!-- Design System CSS (基於 UI/UX Pro Max 建議 + Demo 設計稿) -->
    <link rel="stylesheet" href="<?php echo esc_url(BUYGO_PLUS_ONE_PLUGIN_URL . 'includes/views/assets/design-system.css'); ?>">

    <!-- BuyGo Core JS Modules -->
    <script src="<?php echo esc_url(BUYGO_PLUS_ONE_PLUGIN_URL . 'assets/js/RouterMixin.js'); ?>"></script>
    <script src="<?php echo esc_url(BUYGO_PLUS_ONE_PLUGIN_URL . 'assets/js/DesignSystem.js'); ?>"></script>

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
        <!-- 載入側邊導航元件 -->
        <?php require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'components/shared/side-nav.php'; ?>
        
        <!-- 載入智慧搜尋框元件 -->
        <?php require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'components/shared/smart-search-box.php'; ?>
        
        <!-- 載入 PageHeader 元件 -->
        <?php require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'components/shared/page-header.php'; ?>
        
        <!-- 主內容區 -->
        <div class="md:ml-64 min-h-screen">
            <!-- 頁面內容容器（由 Vue 動態渲染） -->
            <div id="page-content"></div>
        </div>
    </div>
    
    <!-- Vue 3 CDN -->
    <script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>
    <!-- SortableJS (vuedraggable 依賴) -->
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    <!-- VueDraggable CDN -->
    <script src="https://cdn.jsdelivr.net/npm/vuedraggable@4.1.0/dist/vuedraggable.umd.min.js"></script>
    
    <?php
    // 載入頁面元件（如果存在）
    $page_file = BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/views/pages/' . $current_page . '.php';
    $has_page_component = false;
    if (file_exists($page_file)) {
        require $page_file;
        $has_page_component = true;
    }
    ?>
    
    <script>
    const { createApp } = Vue;
    
    // 載入側邊導航元件
    const sideNavComponent = <?php echo $component_name; ?>Component;
    
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
            'dashboard' => null
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
            SideNav: sideNavComponent,
            PageHeader: PageHeader,
            SmartSearchBox: BuyGoSmartSearchBox<?php echo $page_component_name ? ', PageContent: pageComponent' : ''; ?>
        },
        data() {
            return {
                currentPage: '<?php echo esc_js($current_page); ?>'
            }
        },
        template: `
            <div>
                <SideNav :currentPage="currentPage" />
                <div class="md:ml-64 min-h-screen">
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

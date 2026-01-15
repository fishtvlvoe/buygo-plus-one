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
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Open Sans', sans-serif;
        }
        h1, h2, h3, h4, h5, h6 {
            font-family: 'Poppins', sans-serif;
        }
    </style>
</head>
<body class="bg-gray-50">
    <div id="buygo-app">
        <!-- 載入側邊導航元件 -->
        <?php require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'components/shared/side-nav.php'; ?>
        
        <!-- 主內容區 -->
        <div class="md:ml-64 min-h-screen">
            <!-- 頁面內容 -->
            <?php
            $page_file = BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/views/pages/' . $current_page . '.php';
            if (file_exists($page_file)) {
                require_once $page_file;
            } else {
                // 預設內容
                echo '<main class="p-6"><div class="max-w-7xl mx-auto"><h1 class="text-3xl font-bold text-gray-900 mb-4">BuyGo+1 載入中...</h1><p class="text-gray-600">當前頁面：' . esc_html($current_page) . '</p></div></main>';
            }
            ?>
        </div>
    </div>
    
    <!-- Vue 3 CDN -->
    <script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>
    
    <script>
    const { createApp } = Vue;
    
    // 載入側邊導航元件
    const sideNavComponent = <?php echo $component_name; ?>Component;
    
    createApp({
        components: {
            SideNav: sideNavComponent
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
                    <main class="p-6">
                        <div class="max-w-7xl mx-auto">
                            <h1 class="text-3xl font-bold text-gray-900 mb-4">BuyGo+1 載入中...</h1>
                            <p class="text-gray-600">當前頁面：{{ currentPage }}</p>
                        </div>
                    </main>
                </div>
            </div>
        `
    }).mount('#buygo-app');
    </script>
</body>
</html>

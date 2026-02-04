<?php
/**
 * 新版側邊欄組件
 * 基於 products.php 的藍色主題設計
 */

// 預先定義社群 URL（Heredoc 內使用）
// 使用 FluentCommunity 的 Helper 類別取得動態設定的 Portal Slug
$community_url = '';
if (class_exists('\FluentCommunity\App\Services\Helper')) {
    $portal_slug = \FluentCommunity\App\Services\Helper::getPortalSlug();
    $community_url = esc_url(home_url('/' . $portal_slug . '/'));
}

$new_sidebar_template = <<<HTML
<div>
    <!-- 桌面版側邊欄 -->
    <aside class="bg-white border-r border-slate-200 hidden md:flex flex-col transition-all duration-300 z-20 shrink-0 fixed left-0 top-0 h-screen"
        :class="collapsed ? 'w-20' : 'w-48 lg:w-64'">

        <!-- Logo -->
        <div class="h-16 flex items-center justify-center border-b border-slate-100 p-2">
            <a href="/buygo-portal/dashboard" class="flex items-center gap-2 font-bold text-primary text-xl overflow-hidden whitespace-nowrap" v-if="!collapsed">
                <div class="hidden lg:flex items-center gap-2">
                    <svg class="w-8 h-8 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                    </svg>
                    <span>BuyGo+1</span>
                </div>
                <div class="lg:hidden">
                    <svg class="w-8 h-8 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                    </svg>
                </div>
            </a>
            <a v-else href="/buygo-portal/dashboard">
                <svg class="w-8 h-8 text-primary shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                </svg>
            </a>
        </div>

        <!-- 選單項目 -->
        <nav class="flex-1 overflow-y-auto py-4 space-y-1">
            <a v-for="item in menuItems" :key="item.id"
               :href="item.url"
               :class="[
                   'w-full flex items-center px-4 md:px-6 py-3 transition-colors duration-200 group relative',
                   currentPage === item.id ? 'bg-blue-50 text-primary border-r-2 border-primary' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'
               ]"
               :title="item.label">
                <span v-html="item.icon" class="w-6 h-6 shrink-0"></span>
                <span class="ml-3 font-medium whitespace-nowrap transition-opacity duration-200 hidden md:block"
                    :class="collapsed ? 'opacity-0 w-0 overflow-hidden' : 'opacity-100'">
                    {{ item.label }}
                </span>
            </a>
        </nav>

        <!-- 社群連結按鈕 -->
        <a href="{$community_url}"
           target="_blank"
           class="flex items-center justify-center p-4 border-t border-slate-100 text-slate-400 hover:text-primary hover:bg-blue-50 transition-colors group"
           title="前往 BuyGo 社群">
            <svg class="w-6 h-6 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
            </svg>
            <span class="ml-3 font-medium whitespace-nowrap transition-opacity duration-200 hidden md:block text-sm"
                  :class="collapsed ? 'opacity-0 w-0 overflow-hidden' : 'opacity-100'">
                前往社群
            </span>
        </a>

        <!-- 收合按鈕 -->
        <button @click="toggleSidebar"
            class="hidden md:flex p-4 border-t border-slate-100 text-slate-400 hover:text-slate-600 justify-center transition-colors">
            <svg class="w-6 h-6 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                :class="{'rotate-180': collapsed}">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"></path>
            </svg>
        </button>
    </aside>

    <!-- 手機版選單按鈕 -->
    <button
        @click="showMobileMenu = true"
        class="md:hidden fixed top-4 left-4 z-[60] p-2 bg-white rounded-lg shadow-md text-slate-600 hover:bg-slate-50 transition">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
        </svg>
    </button>

    <!-- 手機版選單覆蓋層 -->
    <div v-if="showMobileMenu" class="fixed inset-0 z-[70] flex md:hidden" @click.self="showMobileMenu = false">
        <div class="w-64 bg-white h-full shadow-2xl flex flex-col">
            <!-- 標題 -->
            <div class="h-16 flex items-center justify-between px-6 border-b border-slate-100">
                <a href="/buygo-portal/dashboard" class="font-bold text-primary text-xl flex items-center gap-2">
                    <svg class="w-6 h-6 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                    </svg>
                    <span>BuyGo+1</span>
                </a>
                <button @click="showMobileMenu = false" class="p-2 text-slate-400 hover:text-slate-600 rounded-lg">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <!-- 選單 -->
            <nav class="flex-1 overflow-y-auto py-4 space-y-1">
                <a v-for="item in menuItems" :key="item.id"
                   :href="item.url"
                   @click="showMobileMenu = false"
                   :class="[
                       'w-full flex items-center px-6 py-3 transition-colors duration-200',
                       currentPage === item.id ? 'bg-blue-50 text-primary border-r-4 border-primary' : 'text-slate-600 hover:bg-slate-50'
                   ]">
                    <span v-html="item.icon" class="w-6 h-6 shrink-0"></span>
                    <span class="ml-3 font-medium">{{ item.label }}</span>
                </a>
            </nav>

            <!-- 社群連結 -->
            <div class="border-t border-slate-100 p-4">
                <a href="{$community_url}"
                   target="_blank"
                   class="flex items-center px-6 py-3 text-slate-600 hover:bg-blue-50 hover:text-primary rounded-lg transition-colors">
                    <svg class="w-6 h-6 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                    <span class="ml-3 font-medium">前往社群</span>
                </a>
            </div>
        </div>
        <div class="flex-1 bg-black/20 backdrop-blur-sm" @click="showMobileMenu = false"></div>
    </div>
</div>
HTML;

// 選單項目資料（使用 SVG 圖標）
$new_menu_items = [
    [
        'id' => 'dashboard',
        'label' => '儀表板',
        'url' => '/buygo-portal/dashboard',
        'icon' => '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"></path></svg>'
    ],
    [
        'id' => 'products',
        'label' => '商品',
        'url' => '/buygo-portal/products',
        'icon' => '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>'
    ],
    [
        'id' => 'orders',
        'label' => '訂單',
        'url' => '/buygo-portal/orders',
        'icon' => '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>'
    ],
    [
        'id' => 'shipment-products',
        'label' => '備貨',
        'url' => '/buygo-portal/shipment-products',
        'icon' => '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path></svg>'
    ],
    [
        'id' => 'shipment-details',
        'label' => '出貨',
        'url' => '/buygo-portal/shipment-details',
        'icon' => '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path></svg>'
    ],
    [
        'id' => 'customers',
        'label' => '客戶',
        'url' => '/buygo-portal/customers',
        'icon' => '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>'
    ],
    [
        'id' => 'settings',
        'label' => '設定',
        'url' => '/buygo-portal/settings',
        'icon' => '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>'
    ]
];
?>

<script>
const NewSidebarComponent = {
    name: 'NewSidebar',
    template: `<?php echo $new_sidebar_template; ?>`,
    props: {
        currentPage: {
            type: String,
            default: 'dashboard'
        },
        collapsed: {
            type: Boolean,
            default: false
        }
    },
    emits: ['toggle'],
    setup(props, { emit }) {
        const { ref, onMounted, onUnmounted } = Vue;

        const showMobileMenu = ref(false);
        const menuItems = <?php echo json_encode($new_menu_items); ?>;

        // 切換側邊欄狀態（向父元件發送事件）
        const toggleSidebar = () => {
            emit('toggle');
        };

        // 監聽視窗大小變化
        const handleResize = () => {
            if (window.innerWidth >= 768) {
                showMobileMenu.value = false;
            }
        };

        // ESC 鍵關閉選單
        const handleKeydown = (e) => {
            if (e.key === 'Escape' && showMobileMenu.value) {
                showMobileMenu.value = false;
            }
        };

        onMounted(() => {
            window.addEventListener('resize', handleResize);
            document.addEventListener('keydown', handleKeydown);
        });

        onUnmounted(() => {
            window.removeEventListener('resize', handleResize);
            document.removeEventListener('keydown', handleKeydown);
        });

        return {
            showMobileMenu,
            menuItems,
            toggleSidebar
        };
    }
};
</script>

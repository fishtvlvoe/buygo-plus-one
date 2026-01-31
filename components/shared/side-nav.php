<?php
// 側邊導航元件
$component_name = 'SideNav';
$component_template = <<<'HTML'
<div>
    <!-- 手機版選單按鈕 -->
    <button 
        @click="toggleMenu"
        class="md:hidden fixed top-4 left-4 z-50 p-2 bg-white rounded-md shadow-md text-gray-600 hover:bg-gray-100"
    >
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
        </svg>
    </button>
    
    <!-- 手機版遮罩 -->
    <div 
        v-if="isOpen"
        @click="toggleMenu"
        class="fixed inset-0 bg-black bg-opacity-50 z-30 md:hidden"
    ></div>
    
    <!-- 側邊導航 -->
    <nav class="bg-white shadow-sm border-r border-gray-200 h-screen fixed left-0 top-0 z-40 w-64 transition-transform duration-300 ease-in-out md:translate-x-0" :class="{ '-translate-x-full': !isOpen, 'translate-x-0': isOpen }">
        <!-- 手機版關閉按鈕 -->
        <div class="flex items-center justify-between p-4 border-b border-gray-200 md:hidden">
            <h2 class="text-lg font-semibold text-gray-900">BuyGo+1</h2>
            <button @click="toggleMenu" class="p-2 rounded-md text-gray-600 hover:bg-gray-100">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        
        <!-- Logo / 標題 -->
        <div class="hidden md:flex items-center justify-center p-6 border-b border-gray-200">
            <h1 class="text-xl font-bold text-gray-900">BuyGo+1</h1>
        </div>
        
        <!-- 選單項目 -->
        <div class="py-4">
            <a
                v-for="item in menuItems"
                :key="item.id"
                :href="item.url"
                class="flex items-center px-6 py-3 text-gray-700 hover:bg-blue-50 hover:text-blue-600 transition-colors duration-150"
                :class="{ 'bg-blue-50 text-blue-600 border-r-2 border-blue-600': item.id === currentPage }"
            >
                <span class="mr-3">{{ item.icon }}</span>
                <span class="font-medium">{{ item.label }}</span>
            </a>
        </div>
    </nav>
</div>
HTML;

// 選單項目資料
$menu_items = [
    ['id' => 'dashboard', 'label' => '儀表板', 'icon' => '📊', 'url' => '/buygo-portal/dashboard'],
    ['id' => 'products', 'label' => '商品管理', 'icon' => '📦', 'url' => '/buygo-portal/products'],
    ['id' => 'orders', 'label' => '訂單管理', 'icon' => '🛒', 'url' => '/buygo-portal/orders'],
    ['id' => 'shipment-products', 'label' => '出貨商品', 'icon' => '📤', 'url' => '/buygo-portal/shipment-products'],
    ['id' => 'shipment-details', 'label' => '出貨明細', 'icon' => '📋', 'url' => '/buygo-portal/shipment-details'],
    ['id' => 'customers', 'label' => '客戶管理', 'icon' => '👥', 'url' => '/buygo-portal/customers'],
    ['id' => 'settings', 'label' => '系統設定', 'icon' => '⚙️', 'url' => '/buygo-portal/settings'],
];
?>

<script>
const <?php echo $component_name; ?>Component = {
    name: '<?php echo $component_name; ?>',
    template: `<?php echo $component_template; ?>`,
    props: {
        currentPage: {
            type: String,
            default: 'dashboard'
        }
    },
    setup(props) {
        const { ref, onMounted, onUnmounted } = Vue;
        
        const isOpen = ref(false);
        const menuItems = <?php echo json_encode($menu_items); ?>;
        
        const toggleMenu = () => {
            isOpen.value = !isOpen.value;
        };
        
        // 手機版：點擊外部關閉選單
        const handleClickOutside = (event) => {
            if (window.innerWidth < 768 && isOpen.value) {
                const nav = event.target.closest('nav');
                const button = event.target.closest('button');
                if (!nav && !button) {
                    isOpen.value = false;
                }
            }
        };
        
        // 桌面版：自動開啟
        const checkScreenSize = () => {
            if (window.innerWidth >= 768) {
                isOpen.value = true;
            } else {
                isOpen.value = false;
            }
        };
        
        onMounted(() => {
            checkScreenSize();
            window.addEventListener('resize', checkScreenSize);
            document.addEventListener('click', handleClickOutside);
        });
        
        onUnmounted(() => {
            window.removeEventListener('resize', checkScreenSize);
            document.removeEventListener('click', handleClickOutside);
        });
        
        return {
            isOpen,
            menuItems,
            currentPage: props.currentPage,
            toggleMenu
        };
    }
};
</script>

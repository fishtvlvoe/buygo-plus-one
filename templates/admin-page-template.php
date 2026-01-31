<?php
/**
 * BuyGo+1 管理員頁面範本
 *
 * 使用此範本建立新的管理員頁面。
 *
 * 使用方式：
 * 1. 複製此檔案到 admin/partials/
 * 2. 將 {PageName} 替換為頁面名稱（如 Reports）
 * 3. 將 {page-name} 替換為頁面 slug（如 reports）
 * 4. 將 {頁面標題} 替換為中文標題（如 報表）
 *
 * @package BuyGo_Plus_One
 * @since 1.0.0
 */

// 防止直接訪問
if (!defined('ABSPATH')) {
    exit;
}
?>

<!-- ============================================ -->
<!-- Tailwind 配置 -->
<!-- ============================================ -->
<script>
    tailwind.config = {
        theme: {
            extend: {
                fontFamily: {
                    sans: ['Inter', 'sans-serif'],
                    mono: ['Fira Code', 'monospace'],
                },
                colors: {
                    primary: '#2563EB',
                    secondary: '#3B82F6',
                    cta: '#F97316',
                    surface: '#FFFFFF',
                    background: '#F8FAFC',
                },
                screens: {
                    'xs': '375px',
                }
            }
        }
    }
</script>

<!-- ============================================ -->
<!-- 頁面專屬 CSS（使用 {page-name}- 前綴） -->
<!-- ============================================ -->
<style>
    /* 滾動條樣式 */
    ::-webkit-scrollbar { width: 8px; height: 8px; }
    ::-webkit-scrollbar-track { background: #f1f5f9; }
    ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
    ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }

    /* 過渡動畫 */
    .slide-enter-active, .slide-leave-active { transition: transform 0.3s ease-in-out; }
    .slide-enter-from, .slide-leave-to { transform: translateX(100%); }
    .search-slide-enter-active, .search-slide-leave-active { transition: all 0.2s ease; }
    .search-slide-enter-from, .search-slide-leave-to { opacity: 0; transform: translateY(-10px); }
    .fade-enter-active, .fade-leave-active { transition: opacity 0.2s ease; }
    .fade-enter-from, .fade-leave-to { opacity: 0; }

    /* 頁面專屬樣式 - 使用 {page-name}- 前綴 */
    .{page-name}-card {
        /* 範例：卡片樣式 */
    }
    .{page-name}-loading {
        /* 範例：載入狀態 */
    }

    [v-cloak] { display: none; }
</style>

<?php
// ============================================
// Vue 組件模板
// ============================================
${page_name}_component_template = <<<'HTML'

<div class="min-h-screen bg-slate-50 text-slate-900 font-sans antialiased">
    <main class="flex flex-col min-w-0 relative bg-slate-50 min-h-screen">

        <!-- ============================================ -->
        <!-- 頁首部分 -->
        <!-- ============================================ -->
        <header class="h-16 bg-white border-b border-slate-200 flex items-center justify-between px-4 md:px-6 shrink-0 z-10 sticky top-0 md:static relative">
            <!-- 左側：標題與麵包屑 -->
            <div class="flex items-center gap-3 md:gap-4 overflow-hidden flex-1">
                <div class="flex flex-col overflow-hidden min-w-0 pl-12 md:pl-0" v-show="!showMobileSearch">
                    <h1 class="text-xl font-bold text-slate-900 leading-tight truncate">{頁面標題}</h1>
                    <nav class="hidden md:flex text-[10px] md:text-xs text-slate-500 gap-1 items-center truncate">
                        首頁 <span class="text-slate-300">/</span> {頁面標題}
                        <span v-if="currentView !== 'list'" class="text-slate-300">/</span>
                        <span v-if="currentView === 'detail'" class="text-primary font-medium truncate">詳情 #{{ currentId }}</span>
                    </nav>
                </div>
            </div>

            <!-- 右側：操作按鈕 -->
            <div class="flex items-center gap-2 md:gap-3 shrink-0">
                <!-- 手機版搜尋按鈕 -->
                <button @click="showMobileSearch = !showMobileSearch"
                    class="md:hidden p-2 text-slate-500 hover:text-slate-700 hover:bg-slate-100 rounded-lg transition">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                </button>

                <!-- 桌面版搜尋框 -->
                <div class="relative hidden sm:block w-32 md:w-48 lg:w-64 transition-all duration-300">
                    <input type="text" placeholder="搜尋..." v-model="searchQuery" @input="handleSearchInput"
                        class="pl-9 pr-4 py-2 bg-slate-100 border-none rounded-lg text-sm focus:ring-2 focus:ring-primary w-full transition-all">
                    <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                </div>

                <!-- 新增按鈕（如果需要） -->
                <button @click="openCreate" class="px-3 py-2 bg-primary text-white rounded-lg text-sm font-medium hover:bg-primary/90 transition flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                    <span class="hidden sm:inline">新增</span>
                </button>
            </div>

            <!-- 手機版搜尋覆蓋層 -->
            <transition name="search-slide">
                <div v-if="showMobileSearch" class="absolute inset-0 z-20 bg-white flex items-center px-4 gap-2 md:hidden">
                    <div class="relative flex-1">
                        <input type="text" placeholder="搜尋..." v-model="searchQuery" @input="handleSearchInput"
                            class="w-full pl-9 pr-4 py-2 bg-slate-100 border-none rounded-lg text-sm focus:ring-2 focus:ring-primary">
                        <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                    </div>
                    <button @click="showMobileSearch = false" class="text-sm font-medium text-slate-500 p-2">取消</button>
                </div>
            </transition>
        </header>
        <!-- 結束：頁首部分 -->

        <!-- ============================================ -->
        <!-- 內容區域 -->
        <!-- ============================================ -->
        <div class="flex-1 overflow-auto bg-slate-50/50 relative">

            <!-- 列表檢視 -->
            <div v-show="currentView === 'list'" class="p-2 xs:p-4 md:p-6 w-full max-w-7xl mx-auto space-y-4 md:space-y-6">

                <!-- 工具列 -->
                <div class="flex items-center gap-2 md:gap-3">
                    <div class="flex-1">
                        <smart-search-box
                            api-endpoint="/wp-json/buygo-plus-one/v1/{page-name}"
                            :search-fields="['name']"
                            placeholder="搜尋{頁面標題}..."
                            @select="handleItemSelect"
                            @search="handleItemSearch"
                            @clear="handleItemSearchClear"
                        ></smart-search-box>
                    </div>
                </div>

                <!-- 載入中 -->
                <div v-if="{page_name}Loading" class="flex items-center justify-center py-12">
                    <div class="w-8 h-8 border-2 border-primary border-t-transparent rounded-full animate-spin"></div>
                </div>

                <!-- 資料列表 -->
                <div v-else class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                    <!-- 表頭 -->
                    <div class="hidden md:grid grid-cols-12 gap-4 px-6 py-3 bg-slate-50 border-b border-slate-200 text-xs font-semibold text-slate-500 uppercase tracking-wider">
                        <div class="col-span-1">#</div>
                        <div class="col-span-5">名稱</div>
                        <div class="col-span-3">狀態</div>
                        <div class="col-span-3 text-right">操作</div>
                    </div>

                    <!-- 資料列 -->
                    <div v-for="item in {page_name}Data" :key="item.id"
                         class="grid grid-cols-12 gap-4 px-4 md:px-6 py-4 border-b border-slate-100 hover:bg-slate-50/50 transition items-center">
                        <div class="col-span-1 text-sm text-slate-500">#{{ item.id }}</div>
                        <div class="col-span-5 font-medium text-slate-900">{{ item.name }}</div>
                        <div class="col-span-3">
                            <span class="px-2 py-1 text-xs rounded-full"
                                  :class="item.status === 'active' ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-600'">
                                {{ item.status === 'active' ? '啟用' : '停用' }}
                            </span>
                        </div>
                        <div class="col-span-3 flex justify-end gap-2">
                            <button @click="viewDetail(item.id)" class="p-2 text-slate-400 hover:text-primary hover:bg-primary/10 rounded-lg transition">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                </svg>
                            </button>
                            <button @click="editItem(item.id)" class="p-2 text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- 空狀態 -->
                    <div v-if="{page_name}Data.length === 0" class="px-6 py-12 text-center text-slate-500">
                        <svg class="w-12 h-12 mx-auto text-slate-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
                        </svg>
                        <p>目前沒有資料</p>
                    </div>
                </div>

                <!-- 分頁 -->
                <buygo-pagination
                    v-if="{page_name}TotalPages > 1"
                    :current-page="{page_name}CurrentPage"
                    :total-pages="{page_name}TotalPages"
                    :total-items="{page_name}TotalItems"
                    @page-change="handlePageChange"
                ></buygo-pagination>
            </div>
            <!-- 結束：列表檢視 -->

            <!-- 詳情檢視（與列表檢視平級） -->
            <div v-show="currentView === 'detail'" class="p-4 md:p-6">
                <div class="max-w-4xl mx-auto">
                    <!-- 返回按鈕 -->
                    <button @click="backToList" class="mb-4 flex items-center gap-2 text-slate-600 hover:text-primary transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                        </svg>
                        返回列表
                    </button>

                    <!-- 詳情內容 -->
                    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
                        <h2 class="text-lg font-semibold mb-4">詳情 #{{ currentId }}</h2>
                        <!-- 在此添加詳情內容 -->
                    </div>
                </div>
            </div>
            <!-- 結束：詳情檢視 -->

        </div>
        <!-- 結束：內容區域 -->

    </main>
</div>

HTML;
?>

<!-- ============================================ -->
<!-- Vue 組件腳本 -->
<!-- ============================================ -->
<script>
const {PageName}PageComponent = {
    name: '{PageName}PageComponent',
    template: `<?php echo ${page_name}_component_template; ?>`,
    components: {
        SmartSearchBox: BuyGoSmartSearchBox,
        BuyGoPagination: BuyGoPagination
    },
    setup() {
        const { ref, reactive, computed, onMounted, watch } = Vue;

        // ============================================
        // 【重要】wpNonce 必須定義並導出
        // ============================================
        const wpNonce = '<?php echo wp_create_nonce("wp_rest"); ?>';

        // ============================================
        // 狀態定義
        // ============================================
        const currentView = ref('list');  // 'list' | 'detail'
        const currentId = ref(null);
        const showMobileSearch = ref(false);
        const searchQuery = ref('');

        // 資料狀態
        const {page_name}Data = ref([]);
        const {page_name}Loading = ref(false);
        const {page_name}CurrentPage = ref(1);
        const {page_name}TotalPages = ref(1);
        const {page_name}TotalItems = ref(0);

        // ============================================
        // API 請求（帶有 X-WP-Nonce header）
        // ============================================
        const load{PageName} = async () => {
            {page_name}Loading.value = true;
            try {
                const params = new URLSearchParams({
                    page: {page_name}CurrentPage.value,
                    per_page: 20,
                    search: searchQuery.value
                });

                const response = await fetch(`/wp-json/buygo-plus-one/v1/{page-name}?${params}`, {
                    headers: {
                        'X-WP-Nonce': wpNonce,
                        'Content-Type': 'application/json'
                    }
                });

                if (!response.ok) throw new Error('API 請求失敗');

                const data = await response.json();
                {page_name}Data.value = data.items || [];
                {page_name}TotalPages.value = data.total_pages || 1;
                {page_name}TotalItems.value = data.total || 0;
            } catch (error) {
                console.error('載入失敗:', error);
            } finally {
                {page_name}Loading.value = false;
            }
        };

        // ============================================
        // 事件處理
        // ============================================
        const handleSearchInput = () => {
            {page_name}CurrentPage.value = 1;
            load{PageName}();
        };

        const handleItemSelect = (item) => {
            viewDetail(item.id);
        };

        const handleItemSearch = (query) => {
            searchQuery.value = query;
            {page_name}CurrentPage.value = 1;
            load{PageName}();
        };

        const handleItemSearchClear = () => {
            searchQuery.value = '';
            load{PageName}();
        };

        const handlePageChange = (page) => {
            {page_name}CurrentPage.value = page;
            load{PageName}();
        };

        // ============================================
        // 視圖切換
        // ============================================
        const viewDetail = (id) => {
            currentId.value = id;
            currentView.value = 'detail';
        };

        const editItem = (id) => {
            currentId.value = id;
            currentView.value = 'edit';
        };

        const backToList = () => {
            currentView.value = 'list';
            currentId.value = null;
        };

        const openCreate = () => {
            currentId.value = null;
            currentView.value = 'create';
        };

        // ============================================
        // 生命週期
        // ============================================
        onMounted(() => {
            load{PageName}();
        });

        // ============================================
        // 【重要】必須導出 wpNonce
        // ============================================
        return {
            wpNonce,  // ← 這行很重要！

            // 視圖狀態
            currentView,
            currentId,
            showMobileSearch,
            searchQuery,

            // 資料狀態
            {page_name}Data,
            {page_name}Loading,
            {page_name}CurrentPage,
            {page_name}TotalPages,
            {page_name}TotalItems,

            // 方法
            load{PageName},
            handleSearchInput,
            handleItemSelect,
            handleItemSearch,
            handleItemSearchClear,
            handlePageChange,
            viewDetail,
            editItem,
            backToList,
            openCreate
        };
    }
};
</script>

<?php
// 出貨管理頁面元件

$shipment_products_component_template = <<<'HTML'
<main class="min-h-screen bg-slate-50">
    <!-- Header（固定高度 64px）-->
    <header class="h-16 bg-white border-b border-slate-200 flex items-center justify-between px-4 md:px-6 shrink-0 z-10 sticky top-0 md:static relative">
        <!-- 左側：標題 -->
        <div class="flex items-center gap-3 md:gap-4 overflow-hidden flex-1">
            <div class="flex flex-col overflow-hidden min-w-0 pl-12 md:pl-0">
                <h1 class="text-xl font-bold text-slate-900 leading-tight truncate">備貨</h1>
            </div>
        </div>

        <!-- 右側：操作區 -->
        <div class="flex items-center gap-2 md:gap-3 shrink-0">
            <!-- 搜尋按鈕（手機版）-->
            <button @click="showMobileSearch = true" class="md:hidden p-2 text-slate-400 hover:text-slate-600 rounded-full hover:bg-slate-100">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                </svg>
            </button>

            <!-- 全域搜尋（桌面版顯示）-->
            <div class="relative hidden sm:block w-32 md:w-48 lg:w-64">
                <input type="text"
                       placeholder="全域搜尋..."
                       v-model="globalSearchQuery"
                       @input="handleGlobalSearch"
                       class="pl-9 pr-4 py-2 bg-slate-100 border-none rounded-lg text-sm focus:ring-2 focus:ring-primary w-full">
                <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                </svg>
            </div>

            <!-- 通知 -->
            <button class="p-2 text-slate-400 hover:text-slate-600 rounded-full hover:bg-slate-100 relative">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                </svg>
            </button>
        </div>

        <!-- 手機版搜尋覆蓋層 -->
        <transition name="search-slide">
            <div v-if="showMobileSearch" class="absolute inset-0 z-20 bg-white flex items-center px-4 gap-2 md:hidden">
                <div class="relative flex-1">
                    <input type="text"
                           placeholder="全域搜尋..."
                           v-model="globalSearchQuery"
                           @input="handleGlobalSearch"
                           class="pl-9 pr-4 py-2 bg-slate-100 border-none rounded-lg text-sm w-full focus:outline-none">
                    <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                </div>
                <button @click="showMobileSearch = false" class="p-2 text-slate-600 hover:text-slate-900">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
        </transition>
    </header>

    <!-- Smart Search Box（獨立區域）-->
    <div class="bg-white shadow-sm border-b border-slate-200 px-6 py-4">
        <smart-search-box
            api-endpoint="/wp-json/buygo-plus-one/v1/shipments"
            :search-fields="['shipment_number', 'customer_name', 'product_name']"
            placeholder="搜尋出貨單號、客戶或商品"
            display-field="shipment_number"
            display-sub-field="customer_name"
            :show-currency-toggle="false"
            @select="handleSearchSelect"
            @search="handleSearchInput"
            @clear="handleSearchClear"
        />
    </div>

     <!-- 出貨單列表容器 -->
    <div class="p-6">
        <!-- 載入狀態 -->
        <div v-if="loading" class="buygo-loading">
            <div class="buygo-loading-spinner"></div>
            <p>載入中...</p>
        </div>

        <!-- 錯誤訊息 -->
        <div v-else-if="error" class="text-center py-8">
            <p class="text-red-600">{{ error }}</p>
            <button @click="loadShipments" class="buygo-btn buygo-btn-primary mt-4">重新載入</button>
        </div>
        
        <!-- 出貨單列表 -->
        <div v-else>
            <!-- 批次操作工具列 -->
            <div v-if="selectedShipments.length > 0" class="mb-4 bg-blue-50 border border-blue-200 rounded-xl p-4 flex items-center justify-between">
                <div class="text-sm text-blue-700 font-medium">
                    已選擇 {{ selectedShipments.length }} 個出貨單
                </div>
                <button
                    @click="mergeShipments"
                    :disabled="!canMerge"
                    :class="canMerge ? 'buygo-btn buygo-btn-accent' : 'buygo-btn bg-slate-300 cursor-not-allowed text-white'">
                    合併出貨單
                </button>
            </div>
            
            <!-- 桌面版表格 -->
            <div class="hidden md:block bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50/50">
                            <tr>
                                <th class="px-4 py-4 w-12 text-center">
                                    <input type="checkbox" @change="toggleSelectAll" class="rounded border-slate-300 text-primary w-4 h-4 cursor-pointer">
                                </th>
                                <th class="px-4 py-4 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider whitespace-nowrap w-[12%]">出貨單號</th>
                                <th class="px-4 py-4 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider whitespace-nowrap w-[10%]">客戶</th>
                                <th class="px-4 py-4 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider w-[40%]">商品清單</th>
                                <th class="px-2 py-4 text-center text-xs font-semibold text-slate-500 uppercase tracking-wider whitespace-nowrap w-[8%]">總數量</th>
                                <th class="px-2 py-4 text-center text-xs font-semibold text-slate-500 uppercase tracking-wider whitespace-nowrap w-[10%]">狀態</th>
                                <th class="px-4 py-4 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider whitespace-nowrap w-[12%]">建立日期</th>
                                <th class="px-2 py-4 text-center text-xs font-semibold text-slate-500 uppercase tracking-wider whitespace-nowrap w-[8%]">操作</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-slate-100">
                            <tr v-for="shipment in shipments" :key="shipment.id" class="hover:bg-slate-50 transition">
                                <td class="px-4 py-4 text-center">
                                    <input type="checkbox" :value="shipment.id" v-model="selectedShipments" class="rounded border-slate-300 text-primary w-4 h-4 cursor-pointer">
                                </td>
                                <td class="px-4 py-4 text-sm font-medium text-slate-900 whitespace-nowrap">{{ shipment.shipment_number }}</td>
                                <td class="px-4 py-4 text-sm text-slate-600 whitespace-nowrap">{{ shipment.customer_name || '未知客戶' }}</td>
                                <td class="px-4 py-4">
                                    <!-- 商品清單（移除外部圖片） -->
                                    <div class="flex items-center gap-2">
                                        <span
                                            @click="toggleShipmentExpand(shipment.id)"
                                            class="cursor-pointer hover:text-primary transition text-sm text-slate-700 line-clamp-2"
                                            :title="formatItemsDisplay(shipment, 999)"
                                        >
                                            {{ formatItemsDisplay(shipment) }}
                                        </span>
                                        <button
                                            v-if="shipment.items && shipment.items.length > 0"
                                            @click="toggleShipmentExpand(shipment.id)"
                                            class="text-slate-400 hover:text-primary transition flex-shrink-0"
                                        >
                                            <svg
                                                class="w-4 h-4 transition-transform"
                                                :class="{ 'rotate-180': isShipmentExpanded(shipment.id) }"
                                                fill="none"
                                                stroke="currentColor"
                                                viewBox="0 0 24 24"
                                            >
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                            </svg>
                                        </button>
                                    </div>
                                    <!-- 展開的商品詳細列表 -->
                                    <div
                                        v-if="isShipmentExpanded(shipment.id) && shipment.items && shipment.items.length > 0"
                                        class="mt-3 pt-3 border-t border-slate-200"
                                    >
                                        <div class="space-y-2">
                                            <div
                                                v-for="item in shipment.items"
                                                :key="item.id"
                                                class="flex items-center gap-3 text-xs"
                                            >
                                                <!-- 商品圖片 -->
                                                <img
                                                    v-if="item.product_image"
                                                    :src="item.product_image"
                                                    :alt="item.product_name"
                                                    class="w-10 h-10 object-cover rounded border border-slate-200 shrink-0"
                                                />
                                                <div v-else class="w-10 h-10 bg-slate-100 rounded flex items-center justify-center border border-slate-200 shrink-0">
                                                    <svg class="w-6 h-6 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                                    </svg>
                                                </div>
                                                <div class="flex-1 min-w-0">
                                                    <div class="font-medium text-slate-900 truncate">{{ item.product_name }}</div>
                                                    <div class="text-slate-500">
                                                        {{ item.quantity }} × {{ formatPrice(item.unit_price || item.price || 0, item.currency || 'JPY') }} = {{ formatPrice((item.quantity * (item.unit_price || item.price || 0)), item.currency || 'JPY') }}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-2 py-4 text-center text-sm font-semibold text-slate-900 whitespace-nowrap">{{ shipment.total_quantity || 0 }}</td>
                                <td class="px-2 py-4 text-center">
                                    <span
                                        class="inline-block px-2 py-1 text-xs font-medium rounded-full whitespace-nowrap"
                                        :class="{
                                            'bg-yellow-100 text-yellow-800 border border-yellow-200': shipment.status === 'pending' || shipment.status === '備貨中',
                                            'bg-green-100 text-green-800 border border-green-200': shipment.status === 'shipped' || shipment.status === '已出貨'
                                        }"
                                    >
                                        {{ getStatusText(shipment.status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-4 text-sm text-slate-600 whitespace-nowrap">{{ formatDate(shipment.created_at) }}</td>
                                <td class="px-2 py-4 text-center">
                                    <!-- 操作按鈕（僅待出貨狀態顯示） -->
                                    <button
                                        v-if="shipment.status === 'pending' || shipment.status === '備貨中'"
                                        @click="moveToShipment(shipment.id)"
                                        class="inline-block px-3 py-1.5 text-xs font-medium text-white bg-blue-500 hover:bg-blue-600 rounded-lg transition shadow-sm whitespace-nowrap"
                                        style="min-width: 80px;">
                                        已出貨
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- 手機版卡片 -->
            <div class="md:hidden space-y-4">
                <div v-for="shipment in shipments" :key="shipment.id" class="bg-white border border-slate-200 rounded-xl p-4 mb-3">
                    <div class="flex items-start justify-between mb-3">
                        <div class="flex-1">
                            <div class="text-sm font-bold text-slate-900 mb-1">{{ shipment.shipment_number }}</div>
                            <div class="text-xs text-slate-500">{{ shipment.customer_name || '未知客戶' }}</div>
                        </div>
                        <input type="checkbox" :value="shipment.id" v-model="selectedShipments" class="rounded border-slate-300">
                    </div>
                    
                    <div class="mb-3">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="text-xs text-slate-500">商品：</span>
                            <span 
                                @click="toggleShipmentExpand(shipment.id)"
                                class="font-medium text-slate-900 cursor-pointer hover:text-primary transition flex-1 truncate"
                                :title="formatItemsDisplay(shipment, 999)"
                            >
                                {{ formatItemsDisplay(shipment, 40) }}
                            </span>
                            <button 
                                v-if="shipment.items && shipment.items.length > 0"
                                @click="toggleShipmentExpand(shipment.id)"
                                class="text-slate-400 hover:text-primary transition flex-shrink-0"
                            >
                                <svg 
                                    class="w-3 h-3 transition-transform"
                                    :class="{ 'rotate-180': isShipmentExpanded(shipment.id) }"
                                    fill="none" 
                                    stroke="currentColor" 
                                    viewBox="0 0 24 24"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </button>
                        </div>
                        <!-- 展開的商品詳細列表 -->
                        <div
                            v-if="isShipmentExpanded(shipment.id) && shipment.items && shipment.items.length > 0"
                            class="mt-2 pt-2 border-t border-slate-200"
                        >
                            <div class="space-y-2">
                                <div
                                    v-for="item in shipment.items"
                                    :key="item.id"
                                    class="flex items-center gap-2 text-xs bg-slate-50 p-2 rounded"
                                >
                                    <!-- 商品圖片 -->
                                    <img
                                        v-if="item.product_image"
                                        :src="item.product_image"
                                        :alt="item.product_name"
                                        class="w-8 h-8 object-cover rounded border border-slate-200 shrink-0"
                                    />
                                    <div v-else class="w-8 h-8 bg-slate-100 rounded flex items-center justify-center border border-slate-200 shrink-0">
                                        <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                        </svg>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="font-medium text-slate-900 truncate">{{ item.product_name }}</div>
                                        <div class="text-slate-500">
                                            {{ item.quantity }} × {{ formatPrice(item.unit_price || item.price || 0, item.currency || 'JPY') }} = {{ formatPrice((item.quantity * (item.unit_price || item.price || 0)), item.currency || 'JPY') }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex items-center justify-between mb-3 text-xs">
                        <div>
                            <span class="text-slate-500">總數量：</span>
                            <span class="font-bold text-slate-900">{{ shipment.total_quantity || 0 }}</span>
                        </div>
                        <span
                            class="inline-block px-2 py-1 text-xs font-medium rounded-full"
                            style="white-space: nowrap;"
                            :class="{
                                'bg-yellow-100 text-yellow-800 border border-yellow-200': shipment.status === 'pending' || shipment.status === '備貨中',
                                'bg-green-100 text-green-800 border border-green-200': shipment.status === 'shipped' || shipment.status === '已出貨'
                            }"
                        >
                            {{ getStatusText(shipment.status) }}
                        </span>
                    </div>

                    <div class="mb-3 text-xs">
                        <span class="text-slate-500">建立日期：</span>
                        <span class="text-slate-900">{{ formatDate(shipment.created_at) }}</span>
                    </div>

                    <!-- 操作按鈕（僅待出貨狀態顯示） -->
                    <button
                        v-if="shipment.status === 'pending' || shipment.status === '備貨中'"
                        @click="moveToShipment(shipment.id)"
                        class="w-full px-3 py-2 text-sm font-medium text-white bg-blue-500 hover:bg-blue-600 rounded-lg transition shadow-sm"
                        style="white-space: nowrap;">
                        已出貨
                    </button>
                </div>
            </div>
            
            <!-- 統一分頁樣式 -->
            <div v-if="totalShipments > 0" class="mt-6 flex flex-col sm:flex-row items-center justify-between bg-white px-4 py-3 border border-slate-200 rounded-xl shadow-sm gap-3">
                <div class="text-sm text-slate-700 text-center sm:text-left">
                    顯示 <span class="font-medium">{{ perPage === -1 ? 1 : (currentPage - 1) * perPage + 1 }}</span> 到 <span class="font-medium">{{ perPage === -1 ? totalShipments : Math.min(currentPage * perPage, totalShipments) }}</span> 筆，共 <span class="font-medium">{{ totalShipments }}</span> 筆
                </div>
                <div class="flex items-center gap-3">
                    <select v-model.number="perPage" @change="changePerPage" class="px-3 py-1.5 border border-slate-300 rounded-lg text-sm focus:border-primary focus:ring-1 focus:ring-primary outline-none">
                        <option :value="5">5 / 頁</option>
                        <option :value="10">10 / 頁</option>
                        <option :value="20">20 / 頁</option>
                        <option :value="50">50 / 頁</option>
                        <option :value="-1">全部</option>
                    </select>
                    <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                        <button @click="previousPage" :disabled="currentPage === 1" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-slate-300 bg-white text-sm font-medium text-slate-500 hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed">
                            <span class="sr-only">上一頁</span>
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                        </button>
                        <button v-for="p in visiblePages" :key="p" @click="goToPage(p)" :class="[p === currentPage ? 'z-10 bg-blue-50 border-primary text-primary' : 'bg-white border-slate-300 text-slate-500 hover:bg-slate-50', 'relative inline-flex items-center px-4 py-2 border text-sm font-medium']">
                            {{ p }}
                        </button>
                        <button @click="nextPage" :disabled="currentPage >= totalPages" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-slate-300 bg-white text-sm font-medium text-slate-500 hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed">
                            <span class="sr-only">下一頁</span>
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                        </button>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    <!-- 確認 Modal -->
    <div
        v-if="showConfirmModal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50"
        @click.self="cancelConfirm"
    >
        <div class="bg-white rounded-2xl shadow-xl max-w-md w-full mx-4 overflow-hidden">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-slate-900 mb-4">{{ confirmModal.title || '確認操作' }}</h3>
                <p class="text-slate-600 mb-6">{{ confirmModal.message }}</p>
                <div class="flex justify-end gap-3">
                    <button
                        @click="cancelConfirm"
                        class="buygo-btn buygo-btn-secondary">
                        取消
                    </button>
                    <button
                        @click="executeConfirm"
                        class="buygo-btn buygo-btn-accent">
                        確認
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Toast 通知 -->
    <div 
        v-if="toastMessage.show" 
        class="fixed top-4 right-4 z-50 animate-slide-in"
    >
        <div :class="[
            'px-6 py-4 rounded-lg shadow-lg flex items-center gap-3',
            toastMessage.type === 'success' ? 'bg-green-500 text-white' : 
            toastMessage.type === 'error' ? 'bg-red-500 text-white' : 
            'bg-blue-500 text-white'
        ]">
            <svg v-if="toastMessage.type === 'success'" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
            </svg>
            <svg v-else-if="toastMessage.type === 'error'" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
            <span class="font-medium">{{ toastMessage.message }}</span>
        </div>
    </div>
</main>
HTML;
?>

<script>
const ShipmentProductsPageComponent = {
    name: 'ShipmentProductsPage',
    components: {
        'smart-search-box': BuyGoSmartSearchBox
    },
    template: `<?php echo $shipment_products_component_template; ?>`,
    setup() {
        const { ref, computed, onMounted, watch } = Vue;

        // 使用 useCurrency Composable 處理幣別邏輯
        const { formatPrice } = useCurrency();
        
        // 狀態變數
        const shipments = ref([]);
        const loading = ref(false);
        const error = ref(null);
        
        // 分頁狀態
        const currentPage = ref(1);
        const perPage = ref(5);
        const totalShipments = ref(0);

        // 搜尋狀態
        const searchQuery = ref(null);
        const searchFilter = ref(null);

        // 狀態篩選（與 activeTab 同步）
        const currentStatusFilter = ref('pending');
        const statusFilters = [
            { value: 'all', label: '全部' },
            { value: 'pending', label: '待出貨' },
            { value: 'shipped', label: '已出貨' }
        ];
        
        // 批次操作
        const selectedItems = ref([]);
        const selectedShipments = ref([]);
        
        // 展開狀態（用於商品列表展開）
        const expandedShipments = ref(new Set());
        
        // 確認 Modal 狀態
        const showConfirmModal = ref(false);
        const confirmModal = ref({
            title: '確認操作',
            message: '',
            onConfirm: null
        });
        
        // Toast 通知狀態
        const toastMessage = ref({
            show: false,
            message: '',
            type: 'success' // 'success' | 'error' | 'info'
        });

        // 全域搜尋狀態
        const showMobileSearch = ref(false);
        const globalSearchQuery = ref('');

        // 全域搜尋處理
        const handleGlobalSearch = () => {
            if (globalSearchQuery.value.trim()) {
                // 可以實作跨頁面搜尋邏輯
                console.log('全域搜尋:', globalSearchQuery.value);
            }
        };

        // 顯示 Toast 訊息
        const showToast = (message, type = 'success') => {
            toastMessage.value = { show: true, message, type };
            setTimeout(() => {
                toastMessage.value.show = false;
            }, 3000);
        };
        
        // 載入出貨單列表
        const loadShipments = async () => {
            loading.value = true;
            error.value = null;
            
            try {
                // 載入待出貨的出貨單（用於建立出貨單時的參考）
                let url = `/wp-json/buygo-plus-one/v1/shipments?page=${currentPage.value}&per_page=${perPage.value}&status=pending`;

                // 加入搜尋參數
                if (searchQuery.value) {
                    url += `&search=${encodeURIComponent(searchQuery.value)}`;
                }
                
                const response = await fetch(url, {
                    credentials: 'include',
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const result = await response.json();

                if (result.success && result.data) {
                    shipments.value = result.data;
                    totalShipments.value = result.total || result.data.length;
                } else {
                    throw new Error(result.message || '載入出貨單失敗');
                }
            } catch (err) {
                console.error('載入出貨單錯誤:', err);
                error.value = err.message;
                shipments.value = [];
            } finally {
                loading.value = false;
            }
        };

        // 全局搜尋處理函數
        const handleGlobalSearchInput = (query) => {
            // 全局搜索不需要在本頁面載入資料，因為會跳轉到對應頁面
            console.log('全局搜索輸入:', query);
        };

        const handleGlobalSearchSelect = (item) => {
            // 根據選擇的項目類型跳轉到對應頁面
            if (item.url) {
                window.location.href = item.url;
            } else {
                console.log('搜索項目沒有URL:', item);
            }
        };

        const handleGlobalSearchClear = () => {
            // 清除全局搜索
            console.log('清除全局搜索');
        };

        // 格式化日期
        const formatDate = (dateString) => {
            if (!dateString) return '';
            const date = new Date(dateString);
            return date.toLocaleDateString('zh-TW');
        };
        
        // 格式化商品列表顯示
        const formatItemsDisplay = (shipment, maxLength = 50) => {
            if (!shipment.items || !Array.isArray(shipment.items) || shipment.items.length === 0) {
                return `0 個項目`;
            }

            // 顯示商品名稱列表
            const itemCount = shipment.items.length;
            const firstItem = shipment.items[0];
            const itemNames = shipment.items.map(item => item.product_name || '未知商品').join('、');

            // 如果只有一個商品,顯示完整名稱
            if (itemCount === 1) {
                return firstItem.product_name || '未知商品';
            }

            // 多個商品時,截斷顯示
            if (itemNames.length <= maxLength) {
                return itemNames;
            }

            return `${firstItem.product_name || '未知商品'} 等 ${itemCount} 項`;
        };
        
        // 切換出貨單展開狀態
        const toggleShipmentExpand = (shipmentId) => {
            if (expandedShipments.value.has(shipmentId)) {
                expandedShipments.value.delete(shipmentId);
            } else {
                expandedShipments.value.add(shipmentId);
            }
        };
        
        // 檢查出貨單是否展開
        const isShipmentExpanded = (shipmentId) => {
            return expandedShipments.value.has(shipmentId);
        };
        
        // 取得狀態樣式
        const getStatusClass = (status) => {
            const statusClasses = {
                'pending': 'bg-yellow-100 text-yellow-800 border border-yellow-200',
                'shipped': 'bg-green-100 text-green-800 border border-green-200',
                'archived': 'bg-slate-100 text-slate-800 border border-slate-200',
                'delivered': 'bg-blue-100 text-blue-800 border border-blue-200'
            };
            return statusClasses[status] || 'bg-slate-100 text-slate-800';
        };
        
        // 取得狀態文字（中文化）
        const getStatusText = (status) => {
            const statusTexts = {
                'pending': '待出貨',
                '備貨中': '待出貨',
                'shipped': '已出貨',
                '已出貨': '已出貨',
                'archived': '已存檔',
                'delivered': '已送達'
            };
            return statusTexts[status] || '待出貨';
        };

        // 顯示確認 Modal
        const showConfirm = (message, title = '確認操作', onConfirm = null) => {
            confirmModal.value = {
                title,
                message,
                onConfirm
            };
            showConfirmModal.value = true;
        };
        
        // 執行確認操作
        const executeConfirm = () => {
            if (confirmModal.value.onConfirm) {
                confirmModal.value.onConfirm();
            }
            showConfirmModal.value = false;
        };
        
        // 取消確認
        const cancelConfirm = () => {
            showConfirmModal.value = false;
            confirmModal.value.onConfirm = null;
        };
        
        // 標記為已出貨
        const markShipped = (shipmentId) => {
            showConfirm('確定要標記此出貨單為已出貨嗎？', '確認標記已出貨', async () => {
                try {
                    const response = await fetch(`/wp-json/buygo-plus-one/v1/shipments/batch-mark-shipped`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        credentials: 'include',
                        body: JSON.stringify({
                            shipment_ids: [shipmentId]
                        })
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        showToast('標記成功！', 'success');
                        await loadShipments();
                    } else {
                        showToast('標記失敗：' + result.message, 'error');
                    }
                } catch (err) {
                    console.error('標記失敗:', err);
                    showToast('標記失敗：' + err.message, 'error');
                }
            });
        };
        
        // 批次標記為已出貨（保留此功能，但改用 selectedShipments）
        const batchMarkShipped = () => {
            if (selectedShipments.value.length === 0) {
                return;
            }
            
            showConfirm(
                `確定要標記 ${selectedShipments.value.length} 個出貨單為已出貨嗎？`,
                '批次標記已出貨',
                async () => {
                    try {
                        const response = await fetch(`/wp-json/buygo-plus-one/v1/shipments/batch-mark-shipped`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            credentials: 'include',
                            body: JSON.stringify({
                                shipment_ids: selectedShipments.value
                            })
                        });
                        
                        const result = await response.json();
                        
                        if (result.success) {
                            showToast(`成功標記 ${result.count} 個出貨單為已出貨！`, 'success');
                            selectedShipments.value = [];
                            await loadShipments();
                        } else {
                            showToast('標記失敗：' + result.message, 'error');
                        }
                    } catch (err) {
                        console.error('批次標記失敗:', err);
                        showToast('標記失敗：' + err.message, 'error');
                    }
                }
            );
        };
        
        
        // 全選/取消全選
        const toggleSelectAll = (event) => {
            if (event.target.checked) {
                selectedShipments.value = shipments.value.map(s => s.id);
            } else {
                selectedShipments.value = [];
            }
        };
        
        // 檢查是否可以合併（必須是相同客戶）
        const canMerge = computed(() => {
            if (selectedShipments.value.length < 2) return false;
            
            // 取得所有選中出貨單的客戶 ID
            const customerIds = selectedShipments.value.map(id => {
                const shipment = shipments.value.find(s => s.id === id);
                return shipment?.customer_id;
            });
            
            // 檢查是否都是同一個客戶
            return customerIds.every(id => id === customerIds[0]);
        });
        
        // 移至待出貨（建立出貨單）
        const moveToShipment = async (shipmentId) => {
            showConfirm(
                '確認移至待出貨',
                '確定要將此商品移至出貨流程嗎？',
                async () => {
                    try {
                        // 呼叫 API 建立出貨單
                        const response = await fetch('/wp-json/buygo-plus-one/v1/shipments', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            credentials: 'include',
                            body: JSON.stringify({
                                shipment_ids: [shipmentId]
                            })
                        });
                        
                        const result = await response.json();
                        
                        if (result.success) {
                            showToast('已移至待出貨', 'success');
                            await loadShipments();
                        } else {
                            showToast('移至待出貨失敗：' + result.message, 'error');
                        }
                    } catch (err) {
                        showToast('移至待出貨失敗', 'error');
                    }
                }
            );
        };
        
        // 合併出貨單
        const mergeShipments = async () => {
            if (!canMerge.value) {
                showToast('只能合併相同客戶的出貨單', 'error');
                return;
            }
            
            // 確認這裡有正確的資料
            console.log('準備合併的出貨單 IDs:', selectedShipments.value);
            
            showConfirm(
                '確認合併出貨單',
                `確定要合併 ${selectedShipments.value.length} 個出貨單嗎？`,
                async () => {
                    try {
                        const response = await fetch('/wp-json/buygo-plus-one/v1/shipments/merge', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            credentials: 'include',
                            body: JSON.stringify({
                                shipment_ids: selectedShipments.value  // 確認這裡傳送的是 array
                            })
                        });
                        
                        const result = await response.json();
                        
                        // 加入詳細的錯誤訊息
                        console.log('合併 API 回應:', result);
                        
                        if (result.success) {
                            showToast('合併成功！', 'success');
                            selectedShipments.value = [];
                            await loadShipments();
                        } else {
                            showToast('合併失敗：' + (result.message || '未知錯誤'), 'error');
                        }
                    } catch (err) {
                        console.error('合併失敗錯誤:', err);
                        showToast('合併失敗：' + err.message, 'error');
                    }
                }
            );
        };

        // 分頁
        const totalPages = computed(() => {
            if (perPage.value === -1) return 1;
            return Math.ceil(totalShipments.value / perPage.value);
        });
        
        // 可見的頁碼（最多顯示 5 頁）
        const visiblePages = computed(() => {
            const pages = [];
            const total = totalPages.value;
            const current = currentPage.value;
            
            if (total <= 5) {
                for (let i = 1; i <= total; i++) {
                    pages.push(i);
                }
            } else {
                if (current <= 3) {
                    pages.push(1, 2, 3, 4, 5);
                } else if (current >= total - 2) {
                    for (let i = total - 4; i <= total; i++) {
                        pages.push(i);
                    }
                } else {
                    for (let i = current - 2; i <= current + 2; i++) {
                        pages.push(i);
                    }
                }
            }
            
            return pages;
        });
        
        const previousPage = () => {
            if (currentPage.value > 1) {
                currentPage.value--;
                loadShipments();
            }
        };
        
        const nextPage = () => {
            if (currentPage.value < totalPages.value) {
                currentPage.value++;
                loadShipments();
            }
        };
        
        const goToPage = (page) => {
            currentPage.value = page;
            loadShipments();
        };
        
        const changePerPage = () => {
            currentPage.value = 1;
            loadShipments();
        };
        
        // 初始化
        onMounted(() => {
            loadShipments();
        });
        
        return {
            // 狀態
            shipments,
            loading,
            error,
            currentPage,
            perPage,
            totalShipments,
            totalPages,
            visiblePages,
            previousPage,
            nextPage,
            goToPage,
            changePerPage,
            formatDate,
            formatItemsDisplay,
            formatPrice,
            getStatusClass,
            getStatusText,
            toggleShipmentExpand,
            isShipmentExpanded,
            expandedShipments,
            currentStatusFilter,
            statusFilters,
            markShipped,
            batchMarkShipped,
            toggleSelectAll,
            selectedItems,
            selectedShipments,
            canMerge,
            moveToShipment,
            mergeShipments,
            loadShipments,

            // 全域搜尋
            showMobileSearch,
            globalSearchQuery,
            handleGlobalSearch,

            // Smart Search Box 事件處理
            handleSearchInput: handleGlobalSearchInput,
            handleSearchSelect: handleGlobalSearchSelect,
            handleSearchClear: handleGlobalSearchClear,

            // Modal 和 Toast
            showConfirmModal,
            confirmModal,
            showConfirm,
            executeConfirm,
            cancelConfirm,
            toastMessage,
            showToast
        };
    }
};
</script>

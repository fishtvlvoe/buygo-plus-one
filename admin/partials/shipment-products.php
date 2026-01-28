<?php
// 出貨管理頁面元件

require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'components/shared/smart-search-box.php';

?>
<!-- Shipment Products Page Styles -->
<link rel="stylesheet" href="<?php echo esc_url(plugins_url('../css/shipment-products.css', __FILE__)); ?>" />
<?php

$shipment_products_component_template = <<<'HTML'
<main class="min-h-screen bg-slate-50">

    <!-- ============================================ -->
    <!-- 頁首部分 -->
    <!-- ============================================ -->
    <header class="page-header">
        <!-- 左側：標題 -->
        <div class="flex items-center gap-3 md:gap-4 overflow-hidden flex-1">
            <div class="flex flex-col overflow-hidden min-w-0 pl-12 md:pl-0">
                <h1 class="page-header-title">備貨</h1>
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
            <div class="global-search">
                <input type="text"
                       placeholder="全域搜尋..."
                       v-model="globalSearchQuery"
                       @input="handleGlobalSearch">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                </svg>
            </div>

            <!-- 通知 -->
            <button class="notification-bell">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
    <!-- 結束：頁首部分 -->

    <!-- ============================================ -->
    <!-- 內容區域（此頁面無子頁面切換） -->
    <!-- ============================================ -->
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
            <!-- 批次操作工具列（桌面版） -->
            <div v-if="selectedShipments.length > 0" class="hidden md:flex mb-4 bg-blue-50 border border-blue-200 rounded-xl p-4 items-center justify-between">
                <div class="flex items-center gap-2 text-sm text-blue-700 font-medium">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"></path>
                        <path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3z" clip-rule="evenodd"></path>
                    </svg>
                    已選擇 {{ selectedShipments.length }} 個出貨單
                </div>
                <button
                    @click="mergeShipments"
                    :disabled="!canMerge"
                    :class="canMerge ? 'px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white font-medium rounded-lg transition shadow-sm' : 'px-4 py-2 bg-slate-300 cursor-not-allowed text-white font-medium rounded-lg'">
                    合併
                </button>
            </div>
            
            <!-- 桌面版表格 -->
            <div class="hidden md:block bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50 border-b border-slate-200">
                            <tr>
                                <th class="px-4 py-3 w-12 text-center">
                                    <input type="checkbox" @change="toggleSelectAll" :checked="isAllSelected" class="rounded border-slate-300 text-primary w-4 h-4 cursor-pointer">
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider whitespace-nowrap">出貨單號</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider whitespace-nowrap">客戶</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">商品清單</th>
                                <th class="px-2 py-3 text-center text-xs font-semibold text-slate-600 uppercase tracking-wider whitespace-nowrap">總數量</th>
                                <th class="px-2 py-3 text-center text-xs font-semibold text-slate-600 uppercase tracking-wider whitespace-nowrap">狀態</th>
                                <th class="px-2 py-3 text-center text-xs font-semibold text-slate-600 uppercase tracking-wider whitespace-nowrap">操作</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider whitespace-nowrap">建立日期</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-slate-100">
                            <tr v-for="shipment in shipments" :key="shipment.id" class="hover:bg-slate-50 transition">
                                <td class="px-4 py-3 text-center">
                                    <input type="checkbox" :value="shipment.id" v-model="selectedShipments" class="rounded border-slate-300 text-primary w-4 h-4 cursor-pointer">
                                </td>
                                <td class="px-4 py-3 text-sm font-medium text-slate-900 whitespace-nowrap">{{ shipment.shipment_number }}</td>
                                <td class="px-4 py-3 text-sm text-slate-600 whitespace-nowrap">{{ shipment.customer_name || '未知客戶' }}</td>
                                <td class="px-4 py-3">
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
                                <td class="px-2 py-3 text-center text-sm font-semibold text-slate-900 whitespace-nowrap">{{ shipment.total_quantity || 0 }}</td>
                                <td class="px-2 py-3 text-center">
                                    <span
                                        class="inline-block px-3 py-1 text-xs font-medium rounded-full whitespace-nowrap"
                                        :class="{
                                            'bg-yellow-100 text-yellow-800 border border-yellow-300': shipment.status === 'pending' || shipment.status === '備貨中',
                                            'bg-green-100 text-green-800 border border-green-300': shipment.status === 'shipped' || shipment.status === '已出貨'
                                        }"
                                    >
                                        {{ getStatusText(shipment.status) }}
                                    </span>
                                </td>
                                <td class="px-2 py-3 text-center">
                                    <!-- 操作按鈕（僅待出貨狀態顯示） -->
                                    <button
                                        v-if="shipment.status === 'pending' || shipment.status === '備貨中'"
                                        @click="moveToShipment(shipment.id)"
                                        class="px-3 py-1.5 bg-orange-500 hover:bg-orange-600 text-white text-sm font-medium rounded-lg transition shadow-sm whitespace-nowrap">
                                        轉出貨
                                    </button>
                                </td>
                                <td class="px-4 py-3 text-sm text-slate-600 whitespace-nowrap">{{ formatDate(shipment.created_at) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- 手機版卡片 -->
            <div class="md:hidden space-y-4">
                <!-- 手機版全選區域 -->
                <div class="flex items-center gap-3 px-1 mb-4">
                    <input
                        type="checkbox"
                        @change="toggleSelectAll"
                        :checked="isAllSelected"
                        class="rounded border-slate-300 text-primary w-4 h-4 cursor-pointer"
                    >
                    <span class="text-sm font-medium text-slate-700">
                        全選
                        <span v-if="selectedShipments.length > 0" class="text-primary">
                            ({{ selectedShipments.length }})
                        </span>
                    </span>
                </div>

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
                        轉出貨
                    </button>
                </div>
            </div>

            <!-- 手機版浮動合併工具列 -->
            <div
                v-if="selectedShipments.length > 0"
                class="fixed bottom-20 left-0 right-0 z-40 mx-4 mb-4 bg-blue-50 border border-blue-200 text-blue-700 rounded-xl p-4 flex items-center justify-between shadow-2xl shipment-animate-in md:hidden"
            >
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"></path>
                        <path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3z" clip-rule="evenodd"></path>
                    </svg>
                    <span class="font-bold">已選擇 {{ selectedShipments.length }} 個出貨單</span>
                </div>
                <button
                    @click="mergeShipments"
                    :disabled="!canMerge"
                    :class="canMerge
                        ? 'bg-orange-500 hover:bg-orange-600 text-white'
                        : 'bg-slate-300 text-slate-500 cursor-not-allowed'"
                    class="px-4 py-2 rounded-lg font-bold text-sm transition shadow-lg"
                >
                    合併
                </button>
            </div>

            <!-- 統一分頁樣式 -->
            <div v-if="totalShipments > 0" class="mt-6 flex flex-col sm:flex-row items-center justify-between bg-white px-4 py-3 border border-slate-200 rounded-xl shadow-sm gap-3">
                <div class="text-sm text-slate-700 text-center sm:text-left">
                    顯示 <span class="font-medium">{{ perPage === -1 ? 1 : (currentPage - 1) * perPage + 1 }}</span> 到 <span class="font-medium">{{ perPage === -1 ? totalShipments : Math.min(currentPage * perPage, totalShipments) }}</span> 筆，共 <span class="font-medium">{{ totalShipments }}</span> 筆
                </div>
                <div class="flex items-center gap-3">
                    <select v-model.number="perPage" @change="changePerPage" class="px-3 py-1.5 border border-slate-300 rounded-lg text-sm focus:border-primary focus:ring-1 focus:ring-primary outline-none">
                        <option :value="5">5 筆</option>
                        <option :value="10">10 筆</option>
                        <option :value="20">20 筆</option>
                        <option :value="50">50 筆</option>
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


<!-- Shipment Products Page Template -->
<script type="text/x-template" id="shipment-products-page-template">
    <?php echo $shipment_products_component_template; ?>
</script>

<!-- Shipment Products Page Component -->
<script>
window.buygoWpNonce = '<?php echo wp_create_nonce("wp_rest"); ?>';
</script>
<script src="<?php echo esc_url(plugins_url('js/components/ShipmentProductsPage.js', dirname(__FILE__))); ?>"></script>


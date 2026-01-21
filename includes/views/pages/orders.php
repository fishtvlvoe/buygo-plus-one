<?php
// 訂單管理頁面元件

// 載入智慧搜尋框元件
require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'components/shared/smart-search-box.php';

// 載入 OrderDetailModal 元件
require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'components/order/order-detail-modal.php';
?>
<style>
    /* Custom Scrollbar */
    ::-webkit-scrollbar { width: 8px; height: 8px; }
    ::-webkit-scrollbar-track { background: #f1f5f9; }
    ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
    ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
    
    /* Transitions */
    .search-slide-enter-active, .search-slide-leave-active { transition: all 0.2s ease; }
    .search-slide-enter-from, .search-slide-leave-to { opacity: 0; transform: translateY(-10px); }
    
    [v-cloak] { display: none; }
</style>
<?php
$orders_component_template = <<<'HTML'
<!-- Root Template Content (由 template.php 統一掛載，側邊欄已由共用組件處理) -->
<div class="min-h-screen bg-slate-50 text-slate-900 font-sans antialiased">

    <!-- Main Content -->
    <main class="flex flex-col min-w-0 relative bg-slate-50 min-h-screen">

        <!-- Header -->
        <header class="h-16 bg-white border-b border-slate-200 flex items-center justify-between px-4 md:px-6 shrink-0 z-40 sticky top-0 md:static">
            <div class="flex items-center gap-3 md:gap-4 overflow-hidden flex-1">
                <div class="flex flex-col overflow-hidden min-w-0 pl-12 md:pl-0" v-show="!showMobileSearch">
                    <h1 class="text-xl font-bold text-slate-900 leading-tight truncate">訂單</h1>
                    <nav class="hidden md:flex text-[10px] md:text-xs text-slate-500 gap-1 items-center truncate">
                        首頁 <span class="text-slate-300">/</span> 訂單列表
                        <span v-if="currentView === 'detail'" class="text-slate-300">/</span>
                        <span v-if="currentView === 'detail'" class="text-primary font-medium truncate">詳情 #{{ currentOrderId }}</span>
                    </nav>
                    
                    <!-- 篩選提示 -->
                    <div v-if="searchFilter" class="hidden md:flex items-center gap-2 mt-1">
                        <span class="text-xs text-blue-600 bg-blue-50 px-2 py-0.5 rounded-full border border-blue-200">
                            篩選：{{ searchFilterName }}
                        </span>
                        <button 
                            @click="handleSearchClear"
                            class="text-xs text-slate-500 hover:text-slate-700 underline">
                            清除篩選
                        </button>
                    </div>
                </div>
            </div>

            <!-- Right Actions -->
            <div class="flex items-center gap-2 md:gap-3 shrink-0">
                <button @click="showMobileSearch = !showMobileSearch"
                    class="md:hidden p-2 text-slate-500 hover:text-slate-700 hover:bg-slate-100 rounded-lg transition">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </button>

                <!-- Batch Actions -->
                <div v-if="selectedItems.length > 0" class="flex items-center gap-2 animate-in fade-in slide-in-from-right-4 duration-300">
                    <span class="text-xs font-medium text-slate-500 hidden sm:inline">已選 {{ selectedItems.length }} 項</span>
                    <button @click="batchPrepare" :disabled="batchProcessing" class="px-3 py-1.5 bg-orange-50 text-orange-600 rounded-lg text-xs font-medium hover:bg-orange-100 border border-orange-200 transition disabled:opacity-50">
                        {{ batchProcessing ? '處理中...' : '批次轉備貨' }}
                    </button>
                    <button @click="batchDelete" class="px-3 py-1.5 bg-red-50 text-red-600 rounded-lg text-xs font-medium hover:bg-red-100 border border-red-200 transition">批次刪除</button>
                </div>

                <!-- Desktop Search -->
                <div class="relative hidden sm:block w-32 md:w-48 lg:w-64 transition-all duration-300">
                    <input type="text" placeholder="全域搜尋..." v-model="searchQuery" @input="handleSearchInput"
                        class="pl-9 pr-4 py-2 bg-slate-100 border-none rounded-lg text-sm focus:ring-2 focus:ring-primary w-full transition-all">
                    <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </div>

                <!-- Notification -->
                <button class="p-2 text-slate-400 hover:text-slate-600 rounded-full hover:bg-slate-100 relative">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                </button>
                
                <!-- Currency Toggle -->
                <button @click="toggleCurrency" class="ml-2 px-3 py-1.5 bg-white border border-slate-200 rounded-md text-xs font-bold text-slate-600 hover:border-primary hover:text-primary transition shadow-sm">
                    {{ systemCurrency }}
                </button>
            </div>

            <!-- Mobile Search Overlay -->
            <transition name="search-slide">
                <div v-if="showMobileSearch" class="absolute inset-0 z-20 bg-white flex items-center px-4 gap-2 md:hidden">
                    <div class="relative flex-1">
                        <input type="text" placeholder="全域搜尋..." v-model="searchQuery" @input="handleSearchInput"
                            class="w-full pl-9 pr-4 py-2 bg-slate-100 border-none rounded-lg text-sm focus:ring-2 focus:ring-primary auto-focus">
                        <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </div>
                    <button @click="showMobileSearch = false" class="text-sm font-medium text-slate-500 p-2">取消</button>
                </div>
            </transition>
        </header>

        <div class="flex-1 overflow-auto bg-slate-50/50 relative">
            <!-- 列表視圖（當 currentView === 'list' 時顯示） -->
            <div v-show="currentView === 'list'" class="p-2 xs:p-4 md:p-6 w-full max-w-7xl mx-auto space-y-4 md:space-y-6">

                <!-- Smart Search Box -->
                <smart-search-box
                    api-endpoint="/wp-json/buygo-plus-one/v1/orders"
                    :search-fields="['invoice_no', 'customer_name', 'customer_email']"
                    display-field="invoice_no"
                    display-sub-field="customer_name"
                    placeholder="搜尋訂單編號、客戶名稱或 Email..."
                    :show-image="false"
                    :show-status="true"
                    @select="handleOrderSelect"
                    @search="handleOrderSearch"
                    @clear="handleOrderSearchClear"
                ></smart-search-box>

                <!-- Loading -->
                <div v-if="loading" class="text-center py-12"><div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary mx-auto"></div><p class="mt-2 text-slate-500">載入中...</p></div>

                <!-- Error -->
                <div v-else-if="error" class="text-center py-12">
                    <p class="text-red-600 mb-4">{{ error }}</p>
                    <button @click="loadOrders" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-700 font-medium transition shadow-sm">重新載入</button>
                </div>

                <!-- Content -->
                <div v-else>
            <!-- 桌面版表格 -->
            <div class="hidden md:block bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <table class="w-full">
                    <thead class="bg-slate-50 border-b border-slate-200">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">
                                <input type="checkbox" @change="toggleSelectAll" class="rounded border-slate-300">
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">編號</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">客戶</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">項目</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">總金額</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">運送狀態</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">下單日期</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">操作</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-slate-200">
                        <!-- 父訂單行 -->
                        <template v-for="order in orders" :key="order.id">
                        <tr class="hover:bg-slate-50 transition">
                            <td class="px-4 py-3">
                                <input type="checkbox" :value="order.id" v-model="selectedItems" class="rounded border-slate-300">
                            </td>
                            <td class="px-4 py-3 text-sm font-medium text-slate-900">
                                <div class="flex items-center gap-2">
                                    <button
                                        v-if="order.children && order.children.length > 0"
                                        @click="toggleChildrenCollapse(order.id)"
                                        class="text-slate-400 hover:text-primary transition flex-shrink-0"
                                    >
                                        <svg
                                            class="w-4 h-4 transition-transform"
                                            :class="{ 'rotate-180': isChildrenCollapsed(order.id) }"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                        </svg>
                                    </button>
                                    <span>#{{ order.invoice_no || order.id }}</span>
                                    <span v-if="order.children && order.children.length > 0" class="text-xs text-blue-600 bg-blue-50 px-2 py-0.5 rounded-full">
                                        {{ order.children.length }} 批次
                                    </span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-sm text-slate-600">{{ order.customer_name }}</td>
                            <td class="px-4 py-3 text-sm text-slate-600">
                                <div class="flex items-center gap-2">
                                    <span 
                                        @click="toggleOrderExpand(order.id)"
                                        class="cursor-pointer hover:text-primary transition truncate max-w-xs"
                                        :title="formatItemsDisplay(order, 999)"
                                    >
                                        {{ formatItemsDisplay(order) }}
                                    </span>
                                    <button 
                                        @click="toggleOrderExpand(order.id)"
                                        class="text-slate-400 hover:text-primary transition flex-shrink-0"
                                    >
                                        <svg 
                                            class="w-4 h-4 transition-transform"
                                            :class="{ 'rotate-180': isOrderExpanded(order.id) }"
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
                                    v-if="isOrderExpanded(order.id) && order.items && order.items.length > 0"
                                    class="mt-2 pt-2 border-t border-slate-200 space-y-2"
                                >
                                    <div 
                                        v-for="item in order.items" 
                                        :key="item.id"
                                        class="flex items-center gap-3 text-xs"
                                    >
                                        <img 
                                            v-if="item.product_image"
                                            :src="item.product_image" 
                                            :alt="item.product_name"
                                            class="w-10 h-10 object-cover rounded border border-slate-200"
                                        />
                                        <div class="flex-1 min-w-0">
                                            <div class="font-medium text-slate-900 truncate">{{ item.product_name }}</div>
                                            <div class="text-slate-500">
                                                {{ item.quantity }} × {{ formatPrice(item.price || 0, order.currency) }} = {{ formatPrice(item.total || 0, order.currency) }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-sm font-semibold text-slate-900">{{ formatPrice(order.total_amount, order.currency) }}</td>
                            <td class="px-4 py-3">
                                <div class="relative inline-block">
                                    <button
                                        @click.stop="toggleStatusDropdown(order.id)"
                                        :class="getStatusClass(order.shipping_status || 'unshipped')"
                                        class="px-3 py-1 text-xs font-medium rounded-full cursor-pointer hover:opacity-80 transition whitespace-nowrap flex-shrink-0 overflow-hidden flex items-center gap-1"
                                    >
                                        <span>{{ getStatusText(order.shipping_status || 'unshipped') }}</span>
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                        </svg>
                                    </button>
                                    <!-- 下拉選單 -->
                                    <div
                                        v-if="isStatusDropdownOpen(order.id)"
                                        @click.stop
                                        class="absolute z-50 mt-1 bg-white border border-slate-200 rounded-lg shadow-lg py-1 min-w-[120px]"
                                    >
                                        <button
                                            v-for="status in shippingStatuses"
                                            :key="status.value"
                                            @click="updateShippingStatus(order.id, status.value)"
                                            :class="[
                                                'w-full px-3 py-2 text-left text-xs hover:bg-slate-50 transition whitespace-nowrap',
                                                order.shipping_status === status.value ? 'font-bold' : ''
                                            ]"
                                        >
                                            <span :class="status.color" class="px-2 py-0.5 rounded-full">
                                                {{ status.label }}
                                            </span>
                                        </button>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-sm text-slate-600">{{ formatDate(order.created_at) }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <button @click="openOrderDetail(order.id)" class="p-2 text-slate-500 hover:bg-slate-50 rounded-lg transition" title="查看詳情">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                    </button>
                                    <!-- 根據狀態顯示不同按鈕（父訂單） -->
                                    <button
                                        v-if="hasAllocatedItems(order) && canShowShipButton(order)"
                                        @click="shipOrder(order)"
                                        class="px-3 py-1.5 bg-orange-500 hover:bg-orange-600 text-white text-sm font-medium rounded-lg transition shadow-sm">
                                        轉備貨
                                    </button>
                                    <span
                                        v-else-if="order.shipping_status === 'preparing'"
                                        class="px-3 py-1.5 bg-yellow-100 text-yellow-700 text-sm font-medium rounded-lg border border-yellow-200 inline-flex items-center gap-1">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                        備貨中
                                    </span>
                                    <span
                                        v-else-if="order.shipping_status === 'processing' || order.shipping_status === 'ready_to_ship'"
                                        class="px-3 py-1.5 bg-blue-100 text-blue-700 text-sm font-medium rounded-lg border border-blue-200 inline-flex items-center gap-1">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                        待出貨
                                    </span>
                                    <span
                                        v-else-if="order.shipping_status === 'shipped'"
                                        class="px-3 py-1.5 bg-green-100 text-green-700 text-sm font-medium rounded-lg border border-green-200 inline-flex items-center gap-1">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                        已出貨
                                    </span>
                                </div>
                            </td>
                        </tr>

                        <!-- 子訂單行（拆單） -->
                        <tr
                            v-if="!isChildrenCollapsed(order.id)"
                            v-for="childOrder in order.children"
                            :key="'child-' + childOrder.id"
                            class="bg-blue-50/30 hover:bg-blue-50/50 transition border-l-4 border-blue-400"
                        >
                            <td class="px-4 py-3">
                                <!-- 子訂單不可勾選 -->
                            </td>
                            <td class="px-4 py-3 text-sm font-medium text-blue-700">
                                <div class="flex items-center gap-2">
                                    <svg class="w-4 h-4 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                    </svg>
                                    #{{ childOrder.invoice_no }}
                                    <span class="text-xs text-blue-600 bg-blue-100 px-2 py-0.5 rounded-full">拆單</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-sm text-slate-600">{{ order.customer_name }}</td>
                            <td class="px-4 py-3 text-sm text-slate-600">
                                <!-- 子訂單商品資訊 -->
                                <div class="text-xs text-slate-600">
                                    <template v-if="childOrder.items && childOrder.items.length > 0">
                                        <div v-for="item in childOrder.items" :key="item.id" class="truncate">
                                            {{ item.product_name || item.product_title }} × {{ item.quantity }}
                                        </div>
                                    </template>
                                    <span v-else class="text-blue-700">拆單商品</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-sm font-semibold text-blue-700">{{ formatPrice(childOrder.total_amount, childOrder.currency) }}</td>
                            <td class="px-4 py-3">
                                <span
                                    :class="getStatusClass(childOrder.shipping_status || 'unshipped')"
                                    class="px-3 py-1 text-xs font-medium rounded-full whitespace-nowrap"
                                >
                                    {{ getStatusText(childOrder.shipping_status || 'unshipped') }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-slate-600">{{ formatDate(childOrder.created_at) }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <button @click="openOrderDetail(childOrder.id)" class="p-2 text-blue-500 hover:bg-blue-50 rounded-lg transition" title="查看詳情">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                    </button>
                                    <!-- 根據狀態顯示不同按鈕 -->
                                    <button
                                        v-if="!childOrder.shipping_status || childOrder.shipping_status === 'unshipped'"
                                        @click="shipChildOrder(childOrder, order)"
                                        class="px-3 py-1.5 bg-blue-500 hover:bg-blue-600 text-white text-sm font-medium rounded-lg transition shadow-sm">
                                        轉備貨
                                    </button>
                                    <span
                                        v-else-if="childOrder.shipping_status === 'preparing'"
                                        class="px-3 py-1.5 bg-yellow-100 text-yellow-700 text-sm font-medium rounded-lg border border-yellow-200 inline-flex items-center gap-1">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                        備貨中
                                    </span>
                                    <span
                                        v-else-if="childOrder.shipping_status === 'processing' || childOrder.shipping_status === 'ready_to_ship'"
                                        class="px-3 py-1.5 bg-blue-100 text-blue-700 text-sm font-medium rounded-lg border border-blue-200 inline-flex items-center gap-1">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                        待出貨
                                    </span>
                                    <span
                                        v-else-if="childOrder.shipping_status === 'shipped'"
                                        class="px-3 py-1.5 bg-green-100 text-green-700 text-sm font-medium rounded-lg border border-green-200 inline-flex items-center gap-1">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                        已出貨
                                    </span>
                                </div>
                            </td>
                        </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <!-- 手機版卡片 -->
            <div class="md:hidden space-y-4">
                <div v-for="order in orders" :key="order.id" class="bg-white border border-slate-200 rounded-xl p-4 mb-3">
                    <div class="flex items-start justify-between mb-3">
                        <div class="flex-1">
                            <div class="text-sm font-bold text-slate-900 mb-1">#{{ order.invoice_no || order.id }}</div>
                            <div class="text-xs text-slate-500">{{ order.customer_name }}</div>
                        </div>
                        <input type="checkbox" :value="order.id" v-model="selectedItems" class="rounded border-slate-300">
                    </div>
                    
                    <div class="grid grid-cols-2 gap-2 mb-3 text-xs">
                        <div class="col-span-2">
                            <div class="flex items-center gap-2">
                                <span class="text-slate-500">商品：</span>
                                <span 
                                    @click="toggleOrderExpand(order.id)"
                                    class="font-medium text-slate-900 cursor-pointer hover:text-primary transition flex-1 truncate"
                                    :title="formatItemsDisplay(order, 999)"
                                >
                                    {{ formatItemsDisplay(order, 40) }}
                                </span>
                                <button 
                                    @click="toggleOrderExpand(order.id)"
                                    class="text-slate-400 hover:text-primary transition flex-shrink-0"
                                >
                                    <svg 
                                        class="w-3 h-3 transition-transform"
                                        :class="{ 'rotate-180': isOrderExpanded(order.id) }"
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
                                v-if="isOrderExpanded(order.id) && order.items && order.items.length > 0"
                                class="mt-2 pt-2 border-t border-slate-200 space-y-2"
                            >
                                <div 
                                    v-for="item in order.items" 
                                    :key="item.id"
                                    class="flex items-center gap-2 text-xs bg-slate-50 p-2 rounded"
                                >
                                    <img 
                                        v-if="item.product_image"
                                        :src="item.product_image" 
                                        :alt="item.product_name"
                                        class="w-8 h-8 object-cover rounded border border-slate-200"
                                    />
                                    <div class="flex-1 min-w-0">
                                        <div class="font-medium text-slate-900 truncate">{{ item.product_name }}</div>
                                        <div class="text-slate-500">
                                            {{ item.quantity }} × {{ formatPrice(item.price || 0, order.currency) }} = {{ formatPrice(item.total || 0, order.currency) }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div>
                            <span class="text-slate-500">總金額：</span>
                            <span class="font-bold text-slate-900">{{ formatPrice(order.total_amount, order.currency) }}</span>
                        </div>
                        <div class="col-span-2">
                            <span class="text-slate-500">運送狀態：</span>
                            <div class="relative inline-block">
                                <button
                                    @click.stop="toggleStatusDropdown(order.id)"
                                    :class="getStatusClass(order.shipping_status || 'unshipped')"
                                    class="px-2 py-0.5 text-xs font-medium rounded-full cursor-pointer hover:opacity-80 transition whitespace-nowrap flex-shrink-0 overflow-hidden inline-flex items-center gap-1"
                                >
                                    <span>{{ getStatusText(order.shipping_status || 'unshipped') }}</span>
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                    </svg>
                                </button>
                                <!-- 下拉選單 -->
                                <div
                                    v-if="isStatusDropdownOpen(order.id)"
                                    @click.stop
                                    class="absolute z-50 mt-1 bg-white border border-slate-200 rounded-lg shadow-lg py-1 min-w-[120px]"
                                >
                                    <button
                                        v-for="status in shippingStatuses"
                                        :key="status.value"
                                        @click="updateShippingStatus(order.id, status.value)"
                                        :class="[
                                            'w-full px-3 py-2 text-left text-xs hover:bg-slate-50 transition whitespace-nowrap',
                                            order.shipping_status === status.value ? 'font-bold' : ''
                                        ]"
                                    >
                                        <span :class="status.color" class="px-2 py-0.5 rounded-full">
                                            {{ status.label }}
                                        </span>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div>
                            <span class="text-slate-500">下單日期：</span>
                            <span class="text-slate-900">{{ formatDate(order.created_at) }}</span>
                        </div>
                    </div>
                    
                    <div class="flex gap-2">
                        <button @click="openOrderDetail(order.id)" class="flex-1 py-2 bg-primary text-white rounded-lg text-sm font-medium flex items-center justify-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                            查看詳情
                        </button>
                        <!-- 根據狀態顯示不同按鈕（手機版） -->
                        <button
                            v-if="hasAllocatedItems(order) && canShowShipButton(order)"
                            @click="shipOrder(order)"
                            class="flex-1 px-3 py-2 bg-orange-500 hover:bg-orange-600 text-white text-sm font-medium rounded-lg transition shadow-sm">
                            轉備貨
                        </button>
                        <span
                            v-else-if="order.shipping_status === 'preparing'"
                            class="flex-1 px-3 py-2 bg-yellow-100 text-yellow-700 text-sm font-medium rounded-lg border border-yellow-200 flex items-center justify-center gap-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            備貨中
                        </span>
                        <span
                            v-else-if="order.shipping_status === 'processing' || order.shipping_status === 'ready_to_ship'"
                            class="flex-1 px-3 py-2 bg-blue-100 text-blue-700 text-sm font-medium rounded-lg border border-blue-200 flex items-center justify-center gap-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            待出貨
                        </span>
                        <span
                            v-else-if="order.shipping_status === 'shipped'"
                            class="flex-1 px-3 py-2 bg-green-100 text-green-700 text-sm font-medium rounded-lg border border-green-200 flex items-center justify-center gap-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            已出貨
                        </span>
                    </div>
                </div>
            </div> <!-- End md:hidden (mobile cards) -->

            <!-- 統一分頁樣式 -->
            <div v-if="totalOrders > 0" class="mt-6 flex flex-col sm:flex-row items-center justify-between bg-white px-4 py-3 border border-slate-200 rounded-xl shadow-sm gap-3">
                <div class="text-sm text-slate-700 text-center sm:text-left">
                    顯示 <span class="font-medium">{{ perPage === -1 ? 1 : (currentPage - 1) * perPage + 1 }}</span> 到 <span class="font-medium">{{ perPage === -1 ? totalOrders : Math.min(currentPage * perPage, totalOrders) }}</span> 筆，共 <span class="font-medium">{{ totalOrders }}</span> 筆
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
            </div> <!-- End v-if="totalOrders > 0" (pagination) -->
            </div> <!-- End v-else (order content) -->
            </div> <!-- End list view (v-show="currentView === 'list'") -->

            <!-- Subpages -->
            <div v-show="currentView !== 'list'" class="absolute inset-0 bg-slate-50 z-30 overflow-y-auto w-full" style="min-height: 100vh;">
                <div class="sticky top-0 z-40 bg-white/95 backdrop-blur border-b border-slate-200 px-4 md:px-6 py-3 md:py-4 flex items-center justify-between shadow-sm">
                    <div class="flex items-center gap-2 md:gap-4 overflow-hidden">
                        <button @click="navigateTo('list')" class="p-2 -ml-2 text-slate-500 hover:text-slate-800 hover:bg-slate-100 rounded-full transition-colors flex items-center gap-1 group shrink-0">
                            <svg class="w-5 h-5 group-hover:-translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                            <span class="text-sm font-medium">返回</span>
                        </button>
                        <div class="h-5 w-px bg-slate-200 hidden md:block"></div>
                        <div class="truncate"><h2 class="text-base md:text-xl font-bold text-slate-900 truncate">訂單詳情 #{{ currentOrderId }}</h2></div>
                    </div>
                    <div class="flex gap-2 shrink-0">
                        <button @click="navigateTo('list')" class="px-3 py-1.5 md:px-4 md:py-2 bg-slate-900 text-white rounded-lg hover:bg-slate-800 transition text-xs md:text-sm font-medium">關閉</button>
                    </div>
                </div>

                <div class="max-w-4xl mx-auto p-4 md:p-6 space-y-6 md:space-y-8">
                    <order-detail-modal
                        v-if="currentOrderId"
                        :order-id="currentOrderId"
                        :is-subpage="true"
                        @close="navigateTo('list')"
                    />
                </div>
            </div> <!-- End Subpages -->
        </div> <!-- End flex-1 main content container -->

    <!-- 訂單詳情 Modal（保留向下相容） -->
    <div v-if="showOrderModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50" @click.self="closeOrderModal">
        <div class="bg-white rounded-2xl shadow-xl max-w-3xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <!-- 標題列 -->
            <div class="p-6 border-b border-slate-200">
                <div class="flex items-center justify-between">
                    <h2 class="text-xl font-bold text-slate-900 font-title">訂單詳情</h2>
                    <button @click="closeOrderModal" class="text-slate-400 hover:text-slate-600 transition">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            </div>
            
            <!-- 內容區域 -->
            <div v-if="currentOrder" class="p-6">
                <!-- 訂單基本資訊 -->
                <div class="mb-6">
                    <h3 class="text-lg font-semibold text-slate-900 mb-4">訂單資訊</h3>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <span class="text-sm text-slate-500">訂單編號：</span>
                            <span class="text-sm font-medium text-slate-900">#{{ currentOrder.invoice_no || currentOrder.id }}</span>
                        </div>
                        <div>
                            <span class="text-sm text-slate-500">狀態：</span>
                            <span :class="getStatusClass(currentOrder.status)" class="px-2 py-1 text-xs font-medium rounded-full">
                                {{ getStatusText(currentOrder.status) }}
                            </span>
                        </div>
                        <div>
                            <span class="text-sm text-slate-500">客戶名稱：</span>
                            <span class="text-sm font-medium text-slate-900">{{ currentOrder.customer_name }}</span>
                        </div>
                        <div>
                            <span class="text-sm text-slate-500">客戶 Email：</span>
                            <span class="text-sm font-medium text-slate-900">{{ currentOrder.customer_email }}</span>
                        </div>
                        <div>
                            <span class="text-sm text-slate-500">客戶電話：</span>
                            <span class="text-sm font-medium text-slate-900">{{ currentOrder.customer_phone || '-' }}</span>
                        </div>
                        <div>
                            <span class="text-sm text-slate-500">客戶地址：</span>
                            <span class="text-sm font-medium text-slate-900">{{ currentOrder.customer_address || '-' }}</span>
                        </div>
                        <div>
                            <span class="text-sm text-slate-500">總金額：</span>
                            <span class="text-sm font-bold text-slate-900">{{ formatPrice(currentOrder.total_amount, currentOrder.currency) }}</span>
                        </div>
                        <div>
                            <span class="text-sm text-slate-500">下單日期：</span>
                            <span class="text-sm font-medium text-slate-900">{{ formatDate(currentOrder.created_at) }}</span>
                        </div>
                    </div>
                </div>
                
                <!-- 商品列表 -->
                <div>
                    <h3 class="text-lg font-semibold text-slate-900 mb-4">商品明細</h3>
                    <div class="space-y-4">
                        <div v-for="item in currentOrder.items" :key="item.id" class="border-b border-slate-200 pb-4 last:border-b-0">
                            <!-- 商品基本資訊 -->
                            <div class="flex items-center gap-4 mb-3">
                                <div class="flex-1">
                                    <h4 class="font-semibold text-slate-900">{{ item.product_name }}</h4>
                                    <div class="text-sm text-slate-600 mt-1">
                                        數量: {{ item.quantity }} × {{ formatPrice(item.price, currentOrder.currency) }} = {{ formatPrice(item.total, currentOrder.currency) }}
                                    </div>
                                </div>
                            </div>
                            
                            <!-- 出貨統計 -->
                            <div class="grid grid-cols-3 gap-2 mb-3">
                                <div class="bg-gray-100 p-2 rounded text-center">
                                    <div class="text-xs text-gray-600 mb-1">已出貨</div>
                                    <div class="font-bold text-slate-900">{{ item.shipped_quantity || 0 }}</div>
                                </div>
                                <div class="bg-blue-100 p-2 rounded text-center" v-if="item.allocated_quantity > 0">
                                    <div class="text-xs text-blue-600 mb-1">本次可出貨</div>
                                    <div class="font-bold text-blue-700">{{ item.allocated_quantity }}</div>
                                </div>
                                <div class="bg-yellow-100 p-2 rounded text-center">
                                    <div class="text-xs text-yellow-600 mb-1">未出貨</div>
                                    <div class="font-bold text-yellow-700">{{ item.pending_quantity || 0 }}</div>
                                </div>
                            </div>
                            
                            <!-- 出貨按鈕 -->
                            <button 
                                v-if="item.allocated_quantity > 0"
                                @click="shipOrderItem(item)" 
                                :disabled="shipping"
                                class="w-full px-4 py-2 bg-accent text-white rounded-lg text-xs font-black shadow-[0_2px_10px_-3px_rgba(249,115,22,0.5)] hover:bg-orange-600 hover:scale-105 transition active:scale-95 uppercase tracking-tighter disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:scale-100"
                            >
                                {{ shipping ? '備貨中...' : ('轉備貨 (' + item.allocated_quantity + ' 個)') }}
                            </button>
                            <div v-else class="text-sm text-slate-500 text-center py-2">
                                本商品尚未分配現貨配額，請先至商品管理分配。
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- 確認 Modal -->
    <div 
        v-if="confirmModal.show"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50"
        @click.self="closeConfirmModal"
    >
        <div class="bg-white rounded-2xl shadow-xl max-w-md w-full mx-4 overflow-hidden">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-slate-900 mb-4">{{ confirmModal.title || '確認操作' }}</h3>
                <p class="text-slate-600 mb-6">{{ confirmModal.message }}</p>
                <div class="flex justify-end gap-3">
                    <button 
                        @click="closeConfirmModal"
                        class="px-4 py-2 bg-slate-200 text-slate-700 rounded-lg hover:bg-slate-300 font-medium transition">
                        {{ confirmModal.cancelText }}
                    </button>
                    <button 
                        @click="handleConfirm"
                        class="px-4 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600 font-medium transition">
                        {{ confirmModal.confirmText }}
                    </button>
                </div>
            </div>
        </div>
    </div> <!-- End Confirm Modal -->

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
    </div> <!-- End Toast container -->

    </main>
</div> <!-- End root div -->
HTML;
?>


<script>
const OrdersPageComponent = {
    name: 'OrdersPage',
    components: {
        'order-detail-modal': OrderDetailModal,
        'smart-search-box': BuyGoSmartSearchBox
    },
    template: `<?php echo $orders_component_template; ?>`,
    setup() {
        const { ref, computed, onMounted, watch } = Vue;

        // 使用 useCurrency Composable 處理幣別邏輯
        const {
            formatPrice: formatCurrency,
            formatPriceWithConversion,
            systemCurrency: systemCurrencyFromComposable,
            currencySymbols,
            exchangeRates
        } = useCurrency();

        // ============================================
        // 路由狀態（使用 BuyGoRouter 核心模組）
        // ============================================
        const currentView = ref('list');  // 'list' | 'detail'
        const currentOrderId = ref(null);

        // UI 狀態
        const showMobileSearch = ref(false);

        // 狀態變數
        const orders = ref([]);
        const loading = ref(false);
        const error = ref(null);

        // 分頁狀態
        const currentPage = ref(1);
        const perPage = ref(5);
        const totalOrders = ref(0);

        // 搜尋篩選狀態
        const searchFilter = ref(null);
        const searchFilterName = ref('');
        const searchQuery = ref('');

        // 幣別設定 - 使用 composable 的系統幣別
        const systemCurrency = ref(systemCurrencyFromComposable.value);
        const currentCurrency = ref(systemCurrencyFromComposable.value);

        // 批次轉備貨
        const batchPrepare = async () => {
            if (selectedItems.value.length === 0) return;

            // 過濾出可以轉備貨的訂單（只有 unshipped 狀態的訂單可以轉備貨）
            const eligibleOrders = orders.value.filter(o =>
                selectedItems.value.includes(o.id) &&
                (!o.shipping_status || o.shipping_status === 'unshipped')
            );

            if (eligibleOrders.length === 0) {
                showToast('所選訂單都不是「未出貨」狀態，無法轉備貨', 'error');
                return;
            }

            const skippedCount = selectedItems.value.length - eligibleOrders.length;
            let confirmMessage = `確定要將 ${eligibleOrders.length} 筆訂單轉為備貨狀態嗎？`;
            if (skippedCount > 0) {
                confirmMessage += `\n（${skippedCount} 筆非「未出貨」狀態的訂單將被略過）`;
            }

            showConfirm(
                '批次轉備貨',
                confirmMessage,
                async () => {
                    batchProcessing.value = true;
                    let successCount = 0;
                    let failCount = 0;

                    try {
                        // 逐一呼叫 prepare API
                        for (const order of eligibleOrders) {
                            try {
                                const response = await fetch(`/wp-json/buygo-plus-one/v1/orders/${order.id}/prepare`, {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                    },
                                    credentials: 'include'
                                });

                                const result = await response.json();

                                if (result.success) {
                                    successCount++;
                                } else {
                                    failCount++;
                                    console.error(`訂單 #${order.id} 轉備貨失敗:`, result.message);
                                }
                            } catch (err) {
                                failCount++;
                                console.error(`訂單 #${order.id} 轉備貨錯誤:`, err);
                            }
                        }

                        // 顯示結果
                        if (failCount === 0) {
                            showToast(`成功將 ${successCount} 筆訂單轉為備貨狀態`, 'success');
                        } else {
                            showToast(`${successCount} 筆成功，${failCount} 筆失敗`, failCount > 0 ? 'error' : 'success');
                        }

                        // 清空選取並重新載入
                        selectedItems.value = [];
                        await loadOrders();

                    } catch (err) {
                        console.error('批次轉備貨錯誤:', err);
                        showToast('批次轉備貨失敗：' + err.message, 'error');
                    } finally {
                        batchProcessing.value = false;
                    }
                },
                { confirmText: '確認轉備貨', cancelText: '取消' }
            );
        };

        // 批次刪除
        const batchDelete = async () => {
            if(!confirm(`確認刪除 ${selectedItems.value.length} 項？`)) return;
            try {
                const res = await fetch('/wp-json/buygo-plus-one/v1/orders/batch-delete', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': '<?php echo wp_create_nonce("wp_rest"); ?>' },
                    body: JSON.stringify({ ids: selectedItems.value })
                });
                const data = await res.json();
                if (data.success) {
                    orders.value = orders.value.filter(o => !selectedItems.value.includes(o.id));
                    selectedItems.value = [];
                    showToast('批次刪除成功');
                    loadOrders();
                } else {
                    showToast(data.message || '刪除失敗', 'error');
                }
            } catch(e) { 
                console.error(e); 
                showToast('刪除錯誤', 'error'); 
            }
        };
        
        // 切換幣別
        const toggleCurrency = () => {
            // 在系統幣別和台幣之間切換
            if (currentCurrency.value === 'TWD') {
                currentCurrency.value = systemCurrencyFromComposable.value;
                systemCurrency.value = systemCurrencyFromComposable.value;
                showToast(`已切換為 ${currencySymbols[systemCurrencyFromComposable.value]} ${systemCurrencyFromComposable.value}`);
            } else {
                currentCurrency.value = 'TWD';
                systemCurrency.value = 'TWD';
                showToast(`已切換為 ${currencySymbols['TWD']} TWD`);
            }
        };

        // Modal 狀態（保留向下相容）
        const showOrderModal = ref(false);
        const currentOrder = ref(null);
        const shipping = ref(false);

        // OrderDetailModal 狀態（已改為 URL 驅動）
        const showModal = ref(false);
        const selectedOrderId = ref(null);

        // 批次操作
        const selectedItems = ref([]);
        const batchProcessing = ref(false);

        // 展開狀態（用於商品列表展開）
        const expandedOrders = ref(new Set());

        // 子訂單折疊狀態（預設展開）
        const collapsedChildren = ref(new Set());

        // 狀態下拉選單狀態
        const openStatusDropdown = ref(null);
        
        // 確認 Modal 狀態
        const confirmModal = ref({
            show: false,
            title: '',
            message: '',
            confirmText: '確認',
            cancelText: '取消',
            onConfirm: null
        });
        
        // Toast 通知狀態
        const toastMessage = ref({
            show: false,
            message: '',
            type: 'success' // 'success' | 'error' | 'info'
        });
        
        // 顯示確認對話框
        const showConfirm = (title, message, onConfirm, options = {}) => {
            confirmModal.value = {
                show: true,
                title,
                message,
                confirmText: options.confirmText || '確認',
                cancelText: options.cancelText || '取消',
                onConfirm
            };
        };
        
        // 關閉確認對話框
        const closeConfirmModal = () => {
            confirmModal.value.show = false;
        };
        
        // 確認按鈕處理
        const handleConfirm = () => {
            if (confirmModal.value.onConfirm) {
                confirmModal.value.onConfirm();
            }
            closeConfirmModal();
        };
        
        // 顯示 Toast 訊息
        const showToast = (message, type = 'success') => {
            toastMessage.value = { show: true, message, type };
            setTimeout(() => {
                toastMessage.value.show = false;
            }, 3000);
        };

        // 格式化價格（使用當前顯示幣別，並做匯率轉換）
        const formatPrice = (amount, originalCurrency = null) => {
            if (amount == null) return '-';

            // 如果有原始幣別且與當前顯示幣別不同，需要做匯率轉換
            if (originalCurrency && originalCurrency !== currentCurrency.value) {
                return formatPriceWithConversion(amount, originalCurrency, currentCurrency.value);
            }

            // 否則直接格式化（不轉換）
            return formatCurrency(amount, currentCurrency.value);
        };

        // 搜尋處理函數
        const handleSearchInput = (e) => {
            const query = e.target ? e.target.value : e;
            searchQuery.value = query;
            // 如果搜尋框有內容，嘗試找到對應的訂單
            if (query && query.trim()) {
                // 可以選擇是否要自動篩選，這裡先簡單處理為全域搜尋
                currentPage.value = 1;
                loadOrders();
            } else {
                // 清除搜尋時重置
                handleSearchClear();
            }
        };

        const handleSearchSelect = (item) => {
            searchFilter.value = item.id;
            searchFilterName.value = item.invoice_no || item.customer_name || '';
            searchQuery.value = item.invoice_no || item.customer_name || '';
            currentPage.value = 1;
            loadOrders();
        };

        const handleSearchClear = () => {
            searchFilter.value = null;
            searchFilterName.value = '';
            searchQuery.value = '';
            currentPage.value = 1;
            loadOrders();
        };

        // 載入訂單
        const loadOrders = async () => {
            loading.value = true;
            error.value = null;

            try {
                // 加入時間戳記強制繞過所有快取
                let url = `/wp-json/buygo-plus-one/v1/orders?page=${currentPage.value}&per_page=${perPage.value}&_t=${Date.now()}`;

                if (searchFilter.value) {
                    url += `&id=${searchFilter.value}`;
                } else if (searchQuery.value && searchQuery.value.trim()) {
                    // 如果沒有特定篩選，但有搜尋關鍵字，使用 search 參數
                    url += `&search=${encodeURIComponent(searchQuery.value.trim())}`;
                }

                const response = await fetch(url, {
                    credentials: 'include',
                    cache: 'no-store',  // 防止瀏覽器快取，確保每次都取得最新資料
                    headers: {
                        'Cache-Control': 'no-cache',
                        'Pragma': 'no-cache'
                    }
                });

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const result = await response.json();

                if (result.success && result.data) {
                    // 為每個訂單加上 has_allocation 標記和確保 items 存在
                    orders.value = result.data.map(order => ({
                        ...order,
                        // 檢查是否有分配的商品
                        has_allocation: order.items && Array.isArray(order.items) && order.items.some(item => {
                            const allocatedQty = item.allocated_quantity != null
                                ? parseInt(item.allocated_quantity, 10)
                                : 0;
                            return !isNaN(allocatedQty) && isFinite(allocatedQty) && allocatedQty > 0;
                        }),
                        // 確保 items 陣列存在
                        items: order.items || []
                    }));
                    totalOrders.value = result.total || result.data.length;

                    // 預設折疊所有有子訂單的訂單
                    orders.value.forEach(order => {
                        if (order.children && order.children.length > 0) {
                            collapsedChildren.value.add(order.id);
                        }
                    });
                } else {
                    throw new Error(result.message || '載入訂單失敗');
                }
            } catch (err) {
                console.error('載入訂單錯誤:', err);
                error.value = err.message;
                
                // 記錄到除錯中心（透過 API）
                fetch('/wp-json/buygo-plus-one/v1/debug/log', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'include',
                    body: JSON.stringify({
                        module: 'Orders',
                        message: '載入訂單失敗',
                        level: 'error',
                        data: { error: err.message, url: url }
                    })
                }).catch(console.error);
                
                orders.value = [];
            } finally {
                loading.value = false;
            }
        };

        // 格式化日期
        const formatDate = (dateString) => {
            if (!dateString) return '';
            const date = new Date(dateString);
            return date.toLocaleDateString('zh-TW');
        };
        
        // 格式化商品列表顯示
        const formatItemsDisplay = (order, maxLength = 50) => {
            if (!order.items || !Array.isArray(order.items) || order.items.length === 0) {
                return `${order.total_items || 0} 件`;
            }
            
            const itemsText = order.items
                .map(item => `${item.product_name || '未知商品'} x${item.quantity || 0}`)
                .join(', ');
            
            // 如果文字太長，截斷並加上省略號
            if (itemsText.length > maxLength) {
                return itemsText.substring(0, maxLength) + '...';
            }
            
            return itemsText;
        };
        
        // 切換訂單展開狀態
        const toggleOrderExpand = async (orderId) => {
            if (expandedOrders.value.has(orderId)) {
                expandedOrders.value.delete(orderId);
            } else {
                expandedOrders.value.add(orderId);
                
                // 如果訂單沒有 items，載入詳細資料
                const order = orders.value.find(o => o.id === orderId);
                if (order && (!order.items || !Array.isArray(order.items) || order.items.length === 0)) {
                    try {
                        const response = await fetch(`/wp-json/buygo-plus-one/v1/orders?id=${orderId}`, {
                            credentials: 'include'
                        });
                        
                        const result = await response.json();
                        
                        if (result.success && result.data && result.data.length > 0) {
                            const orderDetail = result.data[0];
                            // 更新 orders 陣列中的訂單資料
                            const index = orders.value.findIndex(o => o.id === orderId);
                            if (index !== -1) {
                                orders.value[index] = { ...orders.value[index], items: orderDetail.items };
                            }
                        }
                    } catch (err) {
                        console.error('載入訂單商品失敗:', err);
                    }
                }
            }
        };
        
        // 檢查訂單是否展開
        const isOrderExpanded = (orderId) => {
            return expandedOrders.value.has(orderId);
        };

        // 切換子訂單顯示/隱藏
        const toggleChildrenCollapse = (orderId) => {
            if (collapsedChildren.value.has(orderId)) {
                collapsedChildren.value.delete(orderId);
            } else {
                collapsedChildren.value.add(orderId);
            }
        };

        // 檢查子訂單是否已折疊
        const isChildrenCollapsed = (orderId) => {
            return collapsedChildren.value.has(orderId);
        };

        // 運送狀態選項（6個）
        const shippingStatuses = [
            { value: 'unshipped', label: '未出貨', color: 'bg-gray-100 text-gray-800 border border-gray-300' },
            { value: 'preparing', label: '備貨中', color: 'bg-yellow-100 text-yellow-800 border border-yellow-300' },
            { value: 'processing', label: '待出貨', color: 'bg-blue-100 text-blue-800 border border-blue-300' },
            { value: 'shipped', label: '已出貨', color: 'bg-purple-100 text-purple-800 border border-purple-300' },
            { value: 'completed', label: '交易完成', color: 'bg-green-100 text-green-800 border border-green-300' },
            { value: 'out_of_stock', label: '斷貨', color: 'bg-red-100 text-red-800 border border-red-300' }
        ];

        // 取得運送狀態樣式
        const getStatusClass = (status) => {
            const statusObj = shippingStatuses.find(s => s.value === status);
            return statusObj ? statusObj.color : 'bg-slate-100 text-slate-800 border border-slate-300';
        };

        // 取得運送狀態文字
        const getStatusText = (status) => {
            const statusObj = shippingStatuses.find(s => s.value === status);
            return statusObj ? statusObj.label : status;
        };

        // 切換狀態下拉選單
        const toggleStatusDropdown = (orderId) => {
            if (openStatusDropdown.value === orderId) {
                openStatusDropdown.value = null;
            } else {
                openStatusDropdown.value = orderId;
            }
        };

        // 檢查狀態下拉選單是否開啟
        const isStatusDropdownOpen = (orderId) => {
            return openStatusDropdown.value === orderId;
        };

        // 更新運送狀態
        const updateShippingStatus = async (orderId, newStatus) => {
            try {
                const response = await fetch(`/wp-json/buygo-plus-one/v1/orders/${orderId}/shipping-status`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': '<?php echo wp_create_nonce("wp_rest"); ?>'
                    },
                    credentials: 'include',
                    body: JSON.stringify({ status: newStatus })
                });

                const result = await response.json();

                if (result.success) {
                    // 更新本地訂單資料
                    const order = orders.value.find(o => o.id === orderId);
                    if (order) {
                        order.shipping_status = newStatus;
                    }
                    showToast('運送狀態已更新', 'success');
                } else {
                    showToast('更新失敗：' + (result.message || '未知錯誤'), 'error');
                }
            } catch (err) {
                console.error('更新運送狀態失敗:', err);
                showToast('更新失敗：' + err.message, 'error');
            } finally {
                // 關閉下拉選單
                openStatusDropdown.value = null;
            }
        };

        // 查看訂單詳情
        const viewOrderDetails = async (order) => {
            showOrderModal.value = true;
            // 重新載入訂單詳情以取得最新的 allocated_quantity
            await loadOrderDetail(order.id);
        };
        
        // 關閉訂單詳情 Modal
        const closeOrderModal = () => {
            showOrderModal.value = false;
            currentOrder.value = null;
        };
        
        // ============================================
        // 路由邏輯（使用 BuyGoRouter 核心模組）
        // ============================================
        const checkUrlParams = () => {
            const params = window.BuyGoRouter.checkUrlParams();
            const { view, id } = params;

            if (view === 'detail' && id) {
                currentView.value = 'detail';
                currentOrderId.value = id;
                selectedOrderId.value = id;
                loadOrderDetail(id);
            } else {
                currentView.value = 'list';
                currentOrderId.value = null;
            }
        };

        const navigateTo = (view, orderId = null, updateUrl = true) => {
            currentView.value = view;

            if (orderId) {
                currentOrderId.value = orderId;
                selectedOrderId.value = orderId;
                loadOrderDetail(orderId);

                if (updateUrl) {
                    window.BuyGoRouter.navigateTo(view, orderId);
                }
            } else {
                currentOrderId.value = null;
                selectedOrderId.value = null;
                currentOrder.value = null;

                if (updateUrl) {
                    window.BuyGoRouter.goToList();
                }
            }
        };

        // 開啟訂單詳情（URL 驅動）
        const openOrderDetail = (orderId) => {
            navigateTo('detail', orderId);
        };

        // 關閉訂單詳情（返回列表）
        const closeOrderDetail = () => {
            navigateTo('list');
        };
        
        // 檢查訂單是否有可出貨的商品（用於父訂單）
        // 重要：如果父訂單已有子訂單（拆單），父訂單本身不應顯示「轉備貨」按鈕
        // 因為此時應該在子訂單上操作，而非父訂單
        const hasAllocatedItems = (order) => {
            if (!order) {
                return false;
            }

            // 【關鍵邏輯】如果父訂單已有子訂單，父訂單不應顯示「轉備貨」按鈕
            // 使用者應該在子訂單上執行轉備貨操作
            if (order.children && order.children.length > 0) {
                return false;
            }

            // 優先檢查 has_allocation 欄位（如果 API 有提供）
            if (order.has_allocation === true) {
                return true;
            }

            // 如果沒有 has_allocation 欄位，檢查 items 中的 allocated_quantity
            if (!order.items || !Array.isArray(order.items) || order.items.length === 0) {
                return false;
            }

            // 檢查每個 item 的 allocated_quantity
            return order.items.some(item => {
                // 處理各種可能的資料類型：數字、字串、null、undefined
                const allocatedQty = item.allocated_quantity != null
                    ? parseInt(item.allocated_quantity, 10)
                    : 0;

                // 確保是有效數字
                const isValidNumber = !isNaN(allocatedQty) && isFinite(allocatedQty);
                return isValidNumber && allocatedQty > 0;
            });
        };

        // 檢查是否可以顯示「轉備貨」按鈕
        // 只有在未出貨狀態才顯示按鈕，已備貨或已出貨則顯示狀態標籤
        const canShowShipButton = (order) => {
            if (!order) return false;
            const status = order.shipping_status || 'unshipped';
            // 只有 unshipped 狀態才顯示轉備貨按鈕
            return status === 'unshipped' || status === '';
        };

        // 執行訂單出貨
        const shipOrder = async (order) => {
            // 轉備貨：將訂單狀態更新為 'preparing'
            showConfirm(
                '確認轉備貨',
                `確定要將訂單 #${order.invoice_no || order.id} 轉為備貨狀態嗎？`,
                async () => {
                    shipping.value = true;

                    try {
                        const response = await fetch(`/wp-json/buygo-plus-one/v1/orders/${order.id}/prepare`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            credentials: 'include'
                        });

                        const result = await response.json();

                        if (result.success) {
                            showToast('已轉為備貨狀態', 'success');
                            // 刷新列表
                            await loadOrders();
                        } else {
                            showToast('轉備貨失敗：' + result.message, 'error');
                        }
                    } catch (err) {
                        console.error('轉備貨失敗:', err);
                        showToast('轉備貨失敗：' + err.message, 'error');

                        // 記錄到除錯中心
                        fetch('/wp-json/buygo-plus-one/v1/debug/log', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            credentials: 'include',
                            body: JSON.stringify({
                                module: 'Orders',
                                message: '轉備貨失敗',
                                level: 'error',
                                data: { error: err.message, order_id: order.id }
                            })
                        }).catch(console.error);
                    } finally {
                        shipping.value = false;
                    }
                }
            );
        };
        
        // 執行出貨
        const shipOrderItem = (item) => {
            showConfirm(
                '確認出貨',
                `確定要出貨 ${item.allocated_quantity} 個「${item.product_name}」嗎？`,
                async () => {
                    shipping.value = true;
                    
                    try {
                        const response = await fetch(`/wp-json/buygo-plus-one/v1/orders/${item.order_id}/ship`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            credentials: 'include',
                            body: JSON.stringify({
                                items: [{
                                    order_item_id: item.id,
                                    quantity: item.allocated_quantity,
                                    product_id: item.product_id
                                }]
                            })
                        });
                        
                        const result = await response.json();
                        
                        if (result.success) {
                            showToast(`出貨成功！出貨單號：SH-${result.shipment_id}`, 'success');
                            // 重新載入訂單詳情
                            await loadOrderDetail(item.order_id);
                        } else {
                            showToast('出貨失敗：' + result.message, 'error');
                        }
                    } catch (err) {
                        console.error('出貨失敗:', err);
                        showToast('出貨失敗：' + err.message, 'error');
                
                        // 記錄到除錯中心
                        fetch('/wp-json/buygo-plus-one/v1/debug/log', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            credentials: 'include',
                            body: JSON.stringify({
                                module: 'Orders',
                                message: '訂單商品出貨失敗',
                                level: 'error',
                                data: { error: err.message, order_id: item.order_id, item_id: item.id }
                            })
                        }).catch(console.error);
                    } finally {
                        shipping.value = false;
                    }
                }
            );
        };

        // 執行子訂單轉備貨（不是直接出貨）
        const shipChildOrder = async (childOrder, parentOrder) => {
            // 確認轉備貨
            showConfirm(
                '確認轉備貨',
                `確定要將指定單 #${childOrder.invoice_no} 轉為備貨狀態嗎？`,
                async () => {
                    shipping.value = true;

                    try {
                        // 呼叫 /prepare 端點，將狀態改為 'preparing'
                        const response = await fetch(`/wp-json/buygo-plus-one/v1/orders/${childOrder.id}/prepare`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            credentials: 'include'
                        });

                        const result = await response.json();

                        if (result.success) {
                            showToast('已轉為備貨狀態', 'success');
                            // 刷新列表
                            await loadOrders();
                        } else {
                            showToast('轉備貨失敗：' + result.message, 'error');
                        }
                    } catch (err) {
                        console.error('轉備貨失敗:', err);
                        showToast('轉備貨失敗：' + err.message, 'error');

                        // 記錄到除錯中心
                        fetch('/wp-json/buygo-plus-one/v1/debug/log', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            credentials: 'include',
                            body: JSON.stringify({
                                module: 'Orders',
                                message: '子訂單轉備貨失敗',
                                level: 'error',
                                data: { error: err.message, order_id: childOrder.id }
                            })
                        });
                    } finally {
                        shipping.value = false;
                    }
                }
            );
        };

        // 載入訂單詳情
        const loadOrderDetail = async (orderId) => {
            try {
                const response = await fetch(`/wp-json/buygo-plus-one/v1/orders?id=${orderId}`, {
                    credentials: 'include'
                });
                
                const result = await response.json();
                
                if (result.success && result.data && result.data.length > 0) {
                    currentOrder.value = result.data[0];
                }
            } catch (err) {
                console.error('載入訂單詳情失敗:', err);
            }
        };
        

        // 全選/取消全選
        const toggleSelectAll = (event) => {
            if (event.target.checked) {
                selectedItems.value = orders.value.map(o => o.id);
            } else {
                selectedItems.value = [];
            }
        };
        
        // 分頁
        const totalPages = computed(() => {
            if (perPage.value === -1) return 1;
            return Math.ceil(totalOrders.value / perPage.value);
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
                loadOrders();
            }
        };
        
        const nextPage = () => {
            if (currentPage.value < totalPages.value) {
                currentPage.value++;
                loadOrders();
            }
        };
        
        const goToPage = (page) => {
            currentPage.value = page;
            loadOrders();
        };
        
        const changePerPage = () => {
            currentPage.value = 1;
            loadOrders();
        };
        
        // 初始化
        onMounted(() => {
            loadOrders();
            // 檢查 URL 參數（使用 BuyGoRouter 核心模組）
            checkUrlParams();
            // 監聽瀏覽器上一頁/下一頁
            window.BuyGoRouter.setupPopstateListener(checkUrlParams);

            // 點擊外部關閉狀態下拉選單
            document.addEventListener('click', () => {
                if (openStatusDropdown.value !== null) {
                    openStatusDropdown.value = null;
                }
            });

            // 監聽商品分配更新事件（同步執行出貨按鈕狀態）
            window.addEventListener('storage', (e) => {
                if (e.key === 'buygo_allocation_updated' && e.newValue) {
                    // 重新載入訂單列表以更新 allocated_quantity
                    loadOrders();
                    // 清除標記
                    localStorage.removeItem('buygo_allocation_updated');
                }
            });

            // 監聽頁面顯示事件（處理 bfcache 和頁面切換）
            // 當使用者從其他頁面切換回來時，重新載入資料
            window.addEventListener('pageshow', (e) => {
                // persisted 表示頁面是從 bfcache 恢復的
                if (e.persisted) {
                    loadOrders();
                }
            });

            // 監聽頁面可見性變化（從其他標籤頁切換回來）
            // 只要頁面變為可見就重新載入，確保資料永遠是最新的
            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'visible') {
                    loadOrders();
                    // 清除可能的分配更新標記
                    localStorage.removeItem('buygo_allocation_updated');
                }
            });
        });

        // Smart Search Box 事件處理器
        const handleOrderSelect = (order) => {
            if (order && order.id) {
                openOrderDetail(order.id);
            }
        };

        // 本地搜尋處理函數(輸入時過濾列表)
        const handleOrderSearch = (query) => {
            searchQuery.value = query;
            currentPage.value = 1;  // 重置到第一頁
            loadOrders();
        };

        // 清除搜尋
        const handleOrderSearchClear = () => {
            searchQuery.value = '';
            searchFilter.value = null;
            searchFilterName.value = '';
            currentPage.value = 1;
            loadOrders();
        };

        return {
            orders,
            loading,
            error,
            currentPage,
            perPage,
            totalOrders,
            totalPages,
            visiblePages,
            previousPage,
            nextPage,
            goToPage,
            changePerPage,
            formatPrice,
            formatDate,
            getStatusClass,
            getStatusText,
            viewOrderDetails,
            closeOrderModal,
            hasAllocatedItems,
            canShowShipButton,
            shipOrder,
            shipOrderItem,
            shipChildOrder,
            loadOrderDetail,
            shipping,
            handleSearchSelect,
            handleSearchInput,
            handleSearchClear,
            toggleSelectAll,
            selectedItems,
            searchFilter,
            searchFilterName,
            searchQuery,
            systemCurrency,
            currentCurrency,
            showOrderModal,
            currentOrder,
            loadOrders,
            showModal,
            selectedOrderId,
            openOrderDetail,
            closeOrderDetail,
            formatItemsDisplay,
            toggleOrderExpand,
            isOrderExpanded,
            expandedOrders,
            toggleChildrenCollapse,
            isChildrenCollapsed,
            collapsedChildren,
            // 路由狀態（URL 驅動）
            currentView,
            currentOrderId,
            navigateTo,
            checkUrlParams,
            // 確認 Modal 和 Toast
            confirmModal,
            showConfirm,
            closeConfirmModal,
            handleConfirm,
            toastMessage,
            showToast,
            // UI 狀態
            showMobileSearch,
            // 新增方法
            batchPrepare,
            batchProcessing,
            batchDelete,
            toggleCurrency,
            // Smart Search Box
            handleOrderSelect,
            handleOrderSearch,
            handleOrderSearchClear,
            // 運送狀態相關
            shippingStatuses,
            toggleStatusDropdown,
            isStatusDropdownOpen,
            updateShippingStatus,
            openStatusDropdown
        };
    }
};
</script>

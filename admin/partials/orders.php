<?php
// 訂單管理頁面元件

// 載入智慧搜尋框元件
require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'components/shared/smart-search-box.php';

// 載入 OrderDetailModal 元件
require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'components/order/order-detail-modal.php';
?>
<!-- Orders Page Styles -->
<link rel="stylesheet" href="<?php echo esc_url(plugins_url('../css/orders.css', __FILE__)); ?>" />
<?php
$orders_component_template = <<<'HTML'
<!-- Root Template Content (由 template.php 統一掛載，側邊欄已由共用組件處理) -->
<div class="min-h-screen bg-slate-50 text-slate-900 font-sans antialiased">

    <!-- Main Content -->
    <main class="flex flex-col min-w-0 relative bg-slate-50 min-h-screen">

        <!-- ============================================ -->
        <!-- 頁首部分 -->
        <!-- ============================================ -->
        <header class="page-header">
            <div class="flex items-center gap-3 md:gap-4 overflow-hidden flex-1">
                <div class="flex flex-col overflow-hidden min-w-0 pl-12 md:pl-0" v-show="!showMobileSearch">
                    <h1 class="page-header-title">訂單</h1>
                    <nav class="page-header-breadcrumb">
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
                    <button @click="batchPrepare" :disabled="batchProcessing" class="btn btn-primary btn-sm">
                        {{ batchProcessing ? '處理中...' : '批次轉備貨' }}
                    </button>
                    <button @click="batchDelete" class="btn btn-danger btn-sm">批次刪除</button>
                </div>

                <!-- Desktop Search -->
                <div class="global-search">
                    <input type="text" placeholder="全域搜尋..." v-model="searchQuery" @input="handleSearchInput">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </div>

                <!-- Notification -->
                <button class="notification-bell">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
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
        <!-- 結束：頁首部分 -->

        <!-- ============================================ -->
        <!-- 內容區域 -->
        <!-- ============================================ -->
        <div class="flex-1 overflow-auto bg-slate-50/50 relative">

            <!-- 列表檢視 -->
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
            <div class="data-table">
                <table>
                    <thead>
                        <tr>
                            <th>
                                <input type="checkbox" @change="toggleSelectAll" class="rounded border-slate-300">
                            </th>
                            <th>編號</th>
                            <th>客戶</th>
                            <th>項目</th>
                            <th>總金額</th>
                            <th>運送狀態</th>
                            <th>下單日期</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- 父訂單行 -->
                        <template v-for="order in orders" :key="order.id">
                        <tr>
                            <td>
                                <input type="checkbox" :value="order.id" v-model="selectedItems" class="rounded border-slate-300">
                            </td>
                            <td>
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
                            <td>{{ order.customer_name }}</td>
                            <td>
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
                            <td>{{ formatPrice(order.total_amount, order.currency) }}</td>
                            <td>
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
                            <td>{{ formatDate(order.created_at) }}</td>
                            <td>
                                <div class="flex items-center gap-2">
                                    <button @click="openOrderDetail(order.id)" class="p-2 text-slate-500 hover:bg-slate-50 rounded-lg transition" title="查看詳情">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                    </button>
                                    <!-- 根據狀態顯示不同按鈕（父訂單） -->
                                    <button
                                        v-if="hasAllocatedItems(order) && canShowShipButton(order)"
                                        @click="shipOrder(order)"
                                        class="btn btn-primary btn-sm">
                                        轉備貨
                                    </button>
                                    <span
                                        v-else-if="order.shipping_status === 'preparing'"
                                        class="status-tag status-tag-warning inline-flex items-center gap-1">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                        備貨中
                                    </span>
                                    <span
                                        v-else-if="order.shipping_status === 'processing' || order.shipping_status === 'ready_to_ship'"
                                        class="status-tag status-tag-info inline-flex items-center gap-1">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                        待出貨
                                    </span>
                                    <span
                                        v-else-if="order.shipping_status === 'shipped'"
                                        class="status-tag status-tag-success inline-flex items-center gap-1">
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
                            <td>
                                <!-- 子訂單不可勾選 -->
                            </td>
                            <td>
                                <div class="flex items-center gap-2">
                                    <svg class="w-4 h-4 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                    </svg>
                                    #{{ childOrder.invoice_no }}
                                    <span class="text-xs text-blue-600 bg-blue-100 px-2 py-0.5 rounded-full">拆單</span>
                                </div>
                            </td>
                            <td>{{ order.customer_name }}</td>
                            <td>
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
                            <td>{{ formatPrice(childOrder.total_amount, childOrder.currency) }}</td>
                            <td>
                                <span
                                    :class="getStatusClass(childOrder.shipping_status || 'unshipped')"
                                    class="px-3 py-1 text-xs font-medium rounded-full whitespace-nowrap"
                                >
                                    {{ getStatusText(childOrder.shipping_status || 'unshipped') }}
                                </span>
                            </td>
                            <td>{{ formatDate(childOrder.created_at) }}</td>
                            <td>
                                <div class="flex items-center gap-2">
                                    <button @click="openOrderDetail(childOrder.id)" class="p-2 text-blue-500 hover:bg-blue-50 rounded-lg transition" title="查看詳情">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                    </button>
                                    <!-- 根據狀態顯示不同按鈕 -->
                                    <button
                                        v-if="!childOrder.shipping_status || childOrder.shipping_status === 'unshipped'"
                                        @click="shipChildOrder(childOrder, order)"
                                        class="btn btn-primary btn-sm">
                                        轉備貨
                                    </button>
                                    <span
                                        v-else-if="childOrder.shipping_status === 'preparing'"
                                        class="status-tag status-tag-warning inline-flex items-center gap-1">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                        備貨中
                                    </span>
                                    <span
                                        v-else-if="childOrder.shipping_status === 'processing' || childOrder.shipping_status === 'ready_to_ship'"
                                        class="status-tag status-tag-info inline-flex items-center gap-1">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                        待出貨
                                    </span>
                                    <span
                                        v-else-if="childOrder.shipping_status === 'shipped'"
                                        class="status-tag status-tag-success inline-flex items-center gap-1">
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
            <div class="card-list">
                <div v-for="order in orders" :key="order.id" class="card">
                    <div class="flex items-start justify-between mb-3">
                        <div class="flex-1">
                            <h3 class="card-title">#{{ order.invoice_no || order.id }}</h3>
                            <p class="card-subtitle">{{ order.customer_name }}</p>
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
                        <button @click="openOrderDetail(order.id)" class="btn btn-primary flex-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                            查看詳情
                        </button>
                        <!-- 根據狀態顯示不同按鈕（手機版） -->
                        <button
                            v-if="hasAllocatedItems(order) && canShowShipButton(order)"
                            @click="shipOrder(order)"
                            class="btn btn-primary flex-1">
                            轉備貨
                        </button>
                        <span
                            v-else-if="order.shipping_status === 'preparing'"
                            class="status-tag status-tag-warning flex-1 flex items-center justify-center gap-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            備貨中
                        </span>
                        <span
                            v-else-if="order.shipping_status === 'processing' || order.shipping_status === 'ready_to_ship'"
                            class="status-tag status-tag-info flex-1 flex items-center justify-center gap-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            待出貨
                        </span>
                        <span
                            v-else-if="order.shipping_status === 'shipped'"
                            class="status-tag status-tag-success flex-1 flex items-center justify-center gap-1">
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
            </div>
            <!-- 結束：列表檢視 -->

            <!-- 子頁面（詳情等） -->
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
                        :wp-nonce="wpNonce"
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


<script type="text/x-template" id="orders-page-template">
    <?php echo $orders_component_template; ?>
</script>


<!-- Orders Page Component -->
<script>
window.buygoWpNonce = '<?php echo wp_create_nonce("wp_rest"); ?>';
</script>
<script src="<?php echo esc_url(plugins_url('js/components/OrdersPage.js', dirname(__FILE__))); ?>"></script>


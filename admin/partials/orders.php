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
// 設定 Header 參數
$header_title = '訂單';
$header_breadcrumb = '首頁 / 訂單列表';
$show_currency_toggle = true;

// 載入共用 Header
ob_start();
include __DIR__ . '/header-component.php';
$header_html = ob_get_clean();

$orders_component_template = <<<'HTML'
<!-- Root Template Content (由 template.php 統一掛載，側邊欄已由共用組件處理) -->
<div class="min-h-screen bg-slate-50 text-slate-900 font-sans antialiased">

    <!-- Main Content -->
    <main class="flex flex-col min-w-0 relative bg-slate-50 min-h-screen">
HTML;

// 將 Header 加入模板
$orders_component_template .= $header_html;

$orders_component_template .= <<<'HTML'

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

                <!-- 狀態分類按鈕 -->
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                    <div class="flex gap-2 md:gap-8 px-3 md:px-6 border-b border-slate-200">
                        <button
                            @click="filterStatus = null"
                            :class="filterStatus === null ? 'border-orange-500 text-orange-600' : 'border-transparent text-slate-600 hover:text-slate-900'"
                            class="flex-1 py-3 md:py-4 px-1 border-b-2 font-medium text-sm transition flex flex-col md:flex-row items-center justify-center gap-1"
                        >
                            <span>全部</span>
                            <span v-if="tabCounts.total > 0" class="px-2 py-0.5 bg-slate-100 text-slate-600 rounded-full text-xs">
                                {{ tabCounts.total }}
                            </span>
                        </button>
                        <button
                            @click="filterStatus = 'unshipped'"
                            :class="filterStatus === 'unshipped' ? 'border-orange-500 text-orange-600' : 'border-transparent text-slate-600 hover:text-slate-900'"
                            class="flex-1 py-3 md:py-4 px-1 border-b-2 font-medium text-sm transition flex flex-col md:flex-row items-center justify-center gap-1"
                        >
                            <span>轉備貨</span>
                            <span v-if="tabCounts.unshipped > 0" class="px-2 py-0.5 bg-gray-100 text-gray-600 rounded-full text-xs">
                                {{ tabCounts.unshipped }}
                            </span>
                        </button>
                        <button
                            @click="filterStatus = 'preparing'"
                            :class="filterStatus === 'preparing' ? 'border-orange-500 text-orange-600' : 'border-transparent text-slate-600 hover:text-slate-900'"
                            class="flex-1 py-3 md:py-4 px-1 border-b-2 font-medium text-sm transition flex flex-col md:flex-row items-center justify-center gap-1"
                        >
                            <span>備貨中</span>
                            <span v-if="tabCounts.preparing > 0" class="px-2 py-0.5 bg-yellow-100 text-yellow-600 rounded-full text-xs">
                                {{ tabCounts.preparing }}
                            </span>
                        </button>
                        <button
                            @click="filterStatus = 'shipped'"
                            :class="filterStatus === 'shipped' ? 'border-orange-500 text-orange-600' : 'border-transparent text-slate-600 hover:text-slate-900'"
                            class="flex-1 py-3 md:py-4 px-1 border-b-2 font-medium text-sm transition flex flex-col md:flex-row items-center justify-center gap-1"
                        >
                            <span>已出貨</span>
                            <span v-if="tabCounts.shipped > 0" class="px-2 py-0.5 bg-green-100 text-green-600 rounded-full text-xs">
                                {{ tabCounts.shipped }}
                            </span>
                        </button>
                    </div>
                </div>

                <!-- 批次操作區塊 -->
                <div v-if="selectedItems.length > 0" class="bg-blue-50 border border-blue-200 rounded-xl p-4 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                    <div class="flex items-center gap-2 text-sm text-blue-700">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <span>已選擇 <strong>{{ selectedItems.length }}</strong> 筆訂單</span>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button
                            @click="batchPrepare"
                            :disabled="batchProcessing"
                            class="btn btn-primary btn-sm"
                        >
                            <svg v-if="batchProcessing" class="animate-spin -ml-1 mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <svg v-else class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path>
                            </svg>
                            {{ batchProcessing ? '處理中...' : '批次轉備貨' }}
                        </button>
                        <button
                            @click="selectedItems = []"
                            class="btn btn-secondary btn-sm"
                        >
                            取消選擇
                        </button>
                    </div>
                </div>

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
                        <!-- 訂單行（父訂單或提取的子訂單） -->
                        <template v-for="order in filteredOrders" :key="order.id">
                        <tr :class="{ 'bg-blue-50/30 border-l-4 border-blue-400': order._isExtractedChild }">
                            <td>
                                <input type="checkbox" :value="order.id" v-model="selectedItems" class="rounded border-slate-300">
                            </td>
                            <td>
                                <div class="flex items-center gap-2">
                                    <!-- 展開/收合按鈕（僅限有子訂單的父訂單） -->
                                    <button
                                        v-if="getFilteredChildren(order).length > 0"
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
                                    <!-- 提取的子訂單加上箭頭圖示 -->
                                    <svg v-if="order._isExtractedChild" class="w-4 h-4 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                    </svg>
                                    <span>#{{ order.invoice_no || order.id }}</span>
                                    <!-- 提取的子訂單顯示「拆單」標籤 -->
                                    <span v-if="order._isExtractedChild" class="text-xs text-blue-600 bg-blue-100 px-2 py-0.5 rounded-full">拆單</span>
                                    <!-- 批次數（僅限有子訂單的父訂單） -->
                                    <span v-if="getFilteredChildren(order).length > 0" class="text-xs text-blue-600 bg-blue-50 px-2 py-0.5 rounded-full">
                                        {{ getFilteredChildren(order).length }} 批次
                                    </span>
                                </div>
                            </td>
                            <!-- 客戶名稱：提取的子訂單從父訂單取得 -->
                            <td>{{ order._isExtractedChild ? order._parentOrder.customer_name : order.customer_name }}</td>
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
                                        @click.stop="toggleStatusDropdown(order.id, $event)"
                                        :class="getStatusClass(order.shipping_status || 'unshipped')"
                                        class="px-3 py-1 text-xs font-medium rounded-full cursor-pointer hover:opacity-80 transition whitespace-nowrap flex-shrink-0 overflow-hidden flex items-center gap-1"
                                    >
                                        <span>{{ getStatusText(order.shipping_status || 'unshipped') }}</span>
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                        </svg>
                                    </button>
                                    <!-- 下拉選單（fixed 定位，不受容器限制） -->
                                    <div
                                        v-if="isStatusDropdownOpen(order.id)"
                                        @click.stop
                                        class="fixed z-[9999] bg-white border border-slate-200 rounded-lg shadow-xl py-1 min-w-[120px]"
                                        :style="{ top: dropdownPosition.top + 'px', left: dropdownPosition.left + 'px', transform: 'translateY(-100%)' }"
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
                                <div class="flex items-center gap-2 flex-nowrap min-w-max">
                                    <button @click="openOrderDetail(order.id)" class="p-2 text-slate-500 hover:bg-slate-50 rounded-lg transition flex-shrink-0" title="查看詳情">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                    </button>
                                    <!-- 根據狀態顯示不同按鈕（父訂單） -->
                                    <button
                                        v-if="hasAllocatedItems(order) && canShowShipButton(order)"
                                        @click="shipOrder(order)"
                                        class="btn btn-primary btn-sm flex-shrink-0">
                                        轉備貨
                                    </button>
                                    <span
                                        v-else-if="order.shipping_status === 'preparing'"
                                        class="status-tag status-tag-warning inline-flex items-center gap-1 flex-shrink-0">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                        備貨中
                                    </span>
                                    <span
                                        v-else-if="order.shipping_status === 'processing' || order.shipping_status === 'ready_to_ship'"
                                        class="status-tag status-tag-info inline-flex items-center gap-1 flex-shrink-0">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                        待出貨
                                    </span>
                                    <span
                                        v-else-if="order.shipping_status === 'shipped'"
                                        class="status-tag status-tag-success inline-flex items-center gap-1 flex-shrink-0">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                        已出貨
                                    </span>
                                </div>
                            </td>
                        </tr>

                        <!-- 子訂單行（拆單） -->
                        <tr
                            v-if="!isChildrenCollapsed(order.id)"
                            v-for="childOrder in getFilteredChildren(order)"
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
                                <div class="flex items-center gap-2 flex-nowrap min-w-max">
                                    <button @click="openOrderDetail(childOrder.id)" class="p-2 text-blue-500 hover:bg-blue-50 rounded-lg transition flex-shrink-0" title="查看詳情">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                    </button>
                                    <!-- 根據狀態顯示不同按鈕 -->
                                    <button
                                        v-if="!childOrder.shipping_status || childOrder.shipping_status === 'unshipped'"
                                        @click="shipChildOrder(childOrder, order)"
                                        class="btn btn-primary btn-sm flex-shrink-0">
                                        轉備貨
                                    </button>
                                    <span
                                        v-else-if="childOrder.shipping_status === 'preparing'"
                                        class="status-tag status-tag-warning inline-flex items-center gap-1 flex-shrink-0">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                        備貨中
                                    </span>
                                    <span
                                        v-else-if="childOrder.shipping_status === 'processing' || childOrder.shipping_status === 'ready_to_ship'"
                                        class="status-tag status-tag-info inline-flex items-center gap-1 flex-shrink-0">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                        待出貨
                                    </span>
                                    <span
                                        v-else-if="childOrder.shipping_status === 'shipped'"
                                        class="status-tag status-tag-success inline-flex items-center gap-1 flex-shrink-0">
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
                <!-- 手機版全選列 -->
                <div v-if="filteredOrders.length > 0" class="flex items-center justify-between px-4 py-3 bg-slate-50 rounded-xl mb-3">
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input
                            type="checkbox"
                            :checked="isAllSelected"
                            @change="toggleSelectAll"
                            class="w-5 h-5 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                        >
                        <span class="text-sm font-medium text-slate-700">全選</span>
                    </label>
                    <span v-if="selectedItems.length > 0" class="text-xs text-blue-600 font-medium">
                        已選 {{ selectedItems.length }} 筆
                    </span>
                </div>
                <template v-for="order in filteredOrders" :key="order.id">
                <div class="card" :class="{ 'ml-4 border-l-4 border-blue-400 bg-blue-50/30': order._isExtractedChild }">
                    <div class="flex items-start justify-between mb-3">
                        <div class="flex items-center gap-2">
                            <input type="checkbox" :value="order.id" v-model="selectedItems" class="w-5 h-5 rounded border-slate-300">
                        </div>
                        <div class="flex-1 ml-3">
                            <div class="flex items-center gap-2">
                                <!-- 提取的子訂單加上箭頭圖示 -->
                                <svg v-if="order._isExtractedChild" class="w-4 h-4 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                </svg>
                                <h3 class="card-title">#{{ order.invoice_no || order.id }}</h3>
                                <!-- 提取的子訂單顯示「拆單」標籤 -->
                                <span v-if="order._isExtractedChild" class="text-xs text-blue-600 bg-blue-100 px-2 py-0.5 rounded-full">拆單</span>
                                <!-- 子訂單展開按鈕（僅限有子訂單的父訂單） -->
                                <button
                                    v-if="getFilteredChildren(order).length > 0"
                                    @click.stop="toggleChildrenCollapse(order.id)"
                                    class="inline-flex items-center gap-1 px-2 py-1 text-xs text-blue-600 bg-blue-50 hover:bg-blue-100 rounded-full transition"
                                >
                                    <span>{{ getFilteredChildren(order).length }} 批次</span>
                                    <svg
                                        class="w-3 h-3 transition-transform"
                                        :class="{ 'rotate-180': !isChildrenCollapsed(order.id) }"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                    </svg>
                                </button>
                            </div>
                            <!-- 客戶名稱：提取的子訂單從父訂單取得 -->
                            <p class="card-subtitle">{{ order._isExtractedChild ? order._parentOrder.customer_name : order.customer_name }}</p>
                        </div>
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

                <!-- 手機版子訂單卡片 -->
                <template v-if="getFilteredChildren(order).length > 0 && !isChildrenCollapsed(order.id)">
                    <div
                        v-for="childOrder in getFilteredChildren(order)"
                        :key="'mobile-child-' + childOrder.id"
                        class="card ml-4 border-l-4 border-blue-400 bg-blue-50/30"
                    >
                        <div class="flex items-start justify-between mb-3">
                            <div class="flex-1">
                                <div class="flex items-center gap-2">
                                    <svg class="w-4 h-4 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                    </svg>
                                    <h3 class="card-title">#{{ childOrder.invoice_no }}</h3>
                                    <span class="text-xs text-blue-600 bg-blue-100 px-2 py-0.5 rounded-full">拆單</span>
                                </div>
                                <p class="card-subtitle">{{ order.customer_name }}</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-2 mb-3 text-xs">
                            <div class="col-span-2">
                                <span class="text-slate-500">商品：</span>
                                <template v-if="childOrder.items && childOrder.items.length > 0">
                                    <span v-for="(item, idx) in childOrder.items" :key="item.id" class="text-slate-900">
                                        {{ item.product_name || item.product_title }} × {{ item.quantity }}<span v-if="idx < childOrder.items.length - 1">、</span>
                                    </span>
                                </template>
                                <span v-else class="text-blue-700">拆單商品</span>
                            </div>
                            <div>
                                <span class="text-slate-500">總金額：</span>
                                <span class="font-bold text-slate-900">{{ formatPrice(childOrder.total_amount, childOrder.currency) }}</span>
                            </div>
                            <div>
                                <span class="text-slate-500">運送狀態：</span>
                                <span
                                    :class="getStatusClass(childOrder.shipping_status || 'unshipped')"
                                    class="px-2 py-0.5 text-xs font-medium rounded-full whitespace-nowrap"
                                >
                                    {{ getStatusText(childOrder.shipping_status || 'unshipped') }}
                                </span>
                            </div>
                            <div>
                                <span class="text-slate-500">日期：</span>
                                <span class="text-slate-900">{{ formatDate(childOrder.created_at) }}</span>
                            </div>
                        </div>

                        <div class="flex gap-2">
                            <button @click="openOrderDetail(childOrder.id)" class="flex-1 px-4 py-2 bg-slate-800 text-white rounded-lg hover:bg-slate-700 font-medium transition text-sm inline-flex items-center justify-center gap-1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                查看詳情
                            </button>
                            <!-- 根據狀態顯示不同按鈕 -->
                            <button
                                v-if="!childOrder.shipping_status || childOrder.shipping_status === 'unshipped'"
                                @click="shipChildOrder(childOrder, order)"
                                class="btn btn-primary flex-1">
                                轉備貨
                            </button>
                            <span
                                v-else-if="childOrder.shipping_status === 'preparing'"
                                class="status-tag status-tag-warning flex-1 flex items-center justify-center gap-1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                備貨中
                            </span>
                            <span
                                v-else-if="childOrder.shipping_status === 'processing' || childOrder.shipping_status === 'ready_to_ship'"
                                class="status-tag status-tag-info flex-1 flex items-center justify-center gap-1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                待出貨
                            </span>
                            <span
                                v-else-if="childOrder.shipping_status === 'shipped'"
                                class="status-tag status-tag-success flex-1 flex items-center justify-center gap-1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                已出貨
                            </span>
                        </div>
                    </div>
                </template>
                </template>
            </div> <!-- End md:hidden (mobile cards) -->

            <!-- 統一分頁樣式 -->
            <div class="pagination-container" v-if="filteredOrders.length > 0 || totalOrders > 0">
                <div class="pagination-info">
                    <!-- 有篩選時顯示篩選後的數量，無篩選時顯示分頁資訊 -->
                    <template v-if="filterStatus">
                        顯示 <span class="font-medium">1</span> 到 <span class="font-medium">{{ filteredOrders.length }}</span> 筆，共 <span class="font-medium">{{ filteredOrders.length }}</span> 筆
                    </template>
                    <template v-else>
                        顯示 <span class="font-medium">{{ perPage === -1 ? 1 : (currentPage - 1) * perPage + 1 }}</span> 到 <span class="font-medium">{{ perPage === -1 ? totalOrders : Math.min(currentPage * perPage, totalOrders) }}</span> 筆，共 <span class="font-medium">{{ totalOrders }}</span> 筆
                    </template>
                </div>
                <div class="pagination-controls">
                    <select v-model.number="perPage" @change="changePerPage" class="pagination-select">
                        <option :value="5">5 筆</option>
                        <option :value="10">10 筆</option>
                        <option :value="20">20 筆</option>
                        <option :value="50">50 筆</option>
                    </select>
                    <nav class="pagination-nav" aria-label="Pagination">
                        <button @click="previousPage" :disabled="currentPage === 1" class="pagination-button first">
                            <span class="sr-only">上一頁</span>
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                        </button>
                        <button v-for="p in visiblePages" :key="p" @click="goToPage(p)" :class="['pagination-button page', { 'active': p === currentPage }]">
                            {{ p }}
                        </button>
                        <button @click="nextPage" :disabled="currentPage >= totalPages" class="pagination-button last">
                            <span class="sr-only">下一頁</span>
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                        </button>
                    </nav>
                </div>
            </div> <!-- End pagination -->
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


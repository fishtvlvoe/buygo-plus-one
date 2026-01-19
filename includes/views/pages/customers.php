<?php
// 客戶管理頁面元件

// 載入智慧搜尋框元件
require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'components/shared/smart-search-box.php';
?>
<style>
/* Transitions */
.search-slide-enter-active, .search-slide-leave-active {
    transition: all 0.2s ease;
}
.search-slide-enter-from, .search-slide-leave-to {
    opacity: 0;
    transform: translateY(-10px);
}
</style>
<?php
$customers_component_template = <<<'HTML'
<main class="min-h-screen bg-slate-50">
    <!-- Header（與 products.php 一致） -->
    <header class="h-16 bg-white border-b border-slate-200 flex items-center justify-between px-4 md:px-6 shrink-0 z-10 sticky top-0 md:static relative">
        <div class="flex items-center gap-3 md:gap-4 overflow-hidden flex-1">
            <div class="flex flex-col overflow-hidden min-w-0" v-show="!showMobileSearch">
                <h1 class="text-base md:text-xl font-bold text-slate-900 leading-tight truncate">客戶管理</h1>
                <nav class="hidden md:flex text-[10px] md:text-xs text-slate-500 gap-1 items-center truncate">
                    <a href="/buygo-portal/dashboard" class="text-slate-500 hover:text-primary">首頁</a>
                    <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                    <span class="text-slate-900 font-medium">客戶</span>
                    <span v-if="currentView === 'detail'" class="text-slate-300">/</span>
                    <span v-if="currentView === 'detail'" class="text-primary font-medium truncate">詳情 #{{ currentCustomerId }}</span>
                </nav>
            </div>
        </div>

        <!-- 右側操作區 -->
        <div class="flex items-center gap-2 md:gap-3 shrink-0">
            <!-- 手機版搜尋按鈕 -->
            <button @click="showMobileSearch = !showMobileSearch"
                class="md:hidden p-2 text-slate-500 hover:text-slate-700 hover:bg-slate-100 rounded-lg transition">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
            </button>

            <!-- 桌面版全域搜尋框 -->
            <div class="relative hidden sm:block w-32 md:w-48 lg:w-64 transition-all duration-300">
                <input type="text" placeholder="全域搜尋..." v-model="globalSearchQuery" @input="handleGlobalSearch"
                    class="pl-9 pr-4 py-2 bg-slate-100 border-none rounded-lg text-sm focus:ring-2 focus:ring-primary w-full transition-all">
                <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
            </div>

            <!-- 通知鈴鐺 -->
            <button class="p-2 text-slate-400 hover:text-slate-600 rounded-full hover:bg-slate-100 relative">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
            </button>

            <!-- 幣別切換 -->
            <button @click="toggleCurrency" class="ml-2 px-3 py-1.5 bg-white border border-slate-200 rounded-md text-xs font-bold text-slate-600 hover:border-primary hover:text-primary transition shadow-sm">
                {{ displayCurrency }}
            </button>
        </div>

        <!-- 手機版搜尋覆蓋層 -->
        <transition name="search-slide">
            <div v-if="showMobileSearch" class="absolute inset-0 z-20 bg-white flex items-center px-4 gap-2 md:hidden">
                <div class="relative flex-1">
                    <input type="text" placeholder="全域搜尋..." v-model="globalSearchQuery" @input="handleGlobalSearch"
                        class="w-full pl-9 pr-4 py-2 bg-slate-100 border-none rounded-lg text-sm focus:ring-2 focus:ring-primary">
                    <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </div>
                <button @click="showMobileSearch = false" class="text-sm font-medium text-slate-500 p-2">取消</button>
            </div>
        </transition>
    </header>

    <!-- 客戶列表容器 -->
    <div v-show="currentView === 'list'" class="p-2 xs:p-4 md:p-6">
        <!-- 載入狀態 -->
        <div v-if="loading" class="buygo-loading">
            <div class="buygo-loading-spinner"></div>
            <p>載入中...</p>
        </div>

        <!-- 錯誤訊息 -->
        <div v-else-if="error" class="buygo-empty-state">
            <p class="text-red-600 mb-4">{{ error }}</p>
            <button @click="loadCustomers" class="buygo-btn buygo-btn-primary">重新載入</button>
        </div>
        
        <!-- 客戶列表 -->
        <div v-else>
            <!-- 桌面版表格 -->
            <div class="hidden md:block bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <table class="w-full">
                    <thead class="bg-slate-50/50 border-b border-slate-200">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">客戶名稱</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">電話</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Email</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">訂單數</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">總消費</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">操作</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-slate-100">
                        <tr v-for="customer in customers" :key="customer.id" class="hover:bg-slate-50 transition">
                            <td class="px-4 py-4 text-sm font-medium text-slate-900">{{ customer.full_name || '-' }}</td>
                            <td class="px-4 py-4 text-sm text-slate-600">{{ customer.phone || '-' }}</td>
                            <td class="px-4 py-4 text-sm text-slate-600">{{ customer.email || '-' }}</td>
                            <td class="px-4 py-4 text-sm text-slate-600">{{ customer.order_count || 0 }}</td>
                            <td class="px-4 py-4 text-sm font-semibold text-slate-900">{{ formatPrice(customer.total_spent || 0, systemCurrency) }}</td>
                            <td class="px-4 py-4">
                                <button @click="navigateTo('detail', customer.id)" class="px-3 py-1.5 bg-primary text-white hover:bg-primary-dark text-xs font-medium rounded-lg transition">
                                    查看詳情
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div> <!-- End desktop table -->

            <!-- 手機版卡片 -->
            <div class="md:hidden space-y-4 md:space-y-6">
                <div v-for="customer in customers" :key="customer.id" class="bg-white border border-slate-200 rounded-xl p-4 mb-3">
                    <div class="mb-3">
                        <div class="text-base font-bold text-slate-900 mb-1">{{ customer.full_name || '-' }}</div>
                        <div class="text-sm text-slate-600 mb-1">{{ customer.phone || '-' }}</div>
                        <div class="flex items-center justify-between mt-2">
                            <div>
                                <div class="text-xs text-slate-500">訂單數</div>
                                <div class="text-sm font-semibold text-slate-900">{{ customer.order_count || 0 }}</div>
                            </div>
                            <div>
                                <div class="text-xs text-slate-500">總消費</div>
                                <div class="text-sm font-semibold text-slate-900">{{ formatPrice(customer.total_spent || 0, systemCurrency) }}</div>
                            </div>
                        </div>
                    </div>
                    <button @click="navigateTo('detail', customer.id)" class="w-full buygo-btn buygo-btn-primary">
                        查看詳情
                    </button>
                </div>
            </div> <!-- End mobile cards -->

            <!-- 統一分頁樣式 -->
            <div v-if="totalCustomers > 0" class="mt-6 flex flex-col sm:flex-row items-center justify-between bg-white px-4 py-3 border border-slate-200 rounded-xl shadow-sm gap-3">
                <div class="text-sm text-slate-700 text-center sm:text-left">
                    顯示 <span class="font-medium">{{ perPage === -1 ? 1 : (currentPage - 1) * perPage + 1 }}</span> 到 <span class="font-medium">{{ perPage === -1 ? totalCustomers : Math.min(currentPage * perPage, totalCustomers) }}</span> 筆，共 <span class="font-medium">{{ totalCustomers }}</span> 筆
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
            </div> <!-- End pagination -->
        </div> <!-- End customer list content -->
    </div> <!-- End list view -->

    <!-- 客戶詳情子頁面（URL 驅動） -->
    <div v-show="currentView === 'detail'" class="absolute inset-0 bg-slate-50 z-30 overflow-y-auto w-full" style="min-height: 100vh;">
        <!-- 子頁面 Header -->
        <div class="sticky top-0 z-40 bg-white/95 backdrop-blur border-b border-slate-200 px-4 md:px-6 py-3 md:py-4 flex items-center justify-between shadow-sm">
            <div class="flex items-center gap-2 md:gap-4 overflow-hidden">
                <button @click="navigateTo('list')" class="p-2 -ml-2 text-slate-500 hover:text-slate-800 hover:bg-slate-100 rounded-full transition-colors flex items-center gap-1 group shrink-0">
                    <svg class="w-5 h-5 group-hover:-translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                    <span class="text-sm font-medium">返回</span>
                </button>
                <div class="h-5 w-px bg-slate-200 hidden md:block"></div>
                <div class="truncate">
                    <h2 class="text-base md:text-xl font-bold text-slate-900 truncate">
                        客戶詳情 <span v-if="selectedCustomer">#{{ currentCustomerId }}</span>
                    </h2>
                </div>
            </div>
            <div class="flex gap-2 shrink-0">
                <button @click="navigateTo('list')" class="px-3 py-1.5 md:px-4 md:py-2 bg-white border border-slate-300 rounded-lg text-slate-700 hover:bg-slate-50 transition text-xs md:text-sm font-medium">關閉</button>
            </div>
        </div>

        <!-- 子頁面內容 -->
        <div class="max-w-4xl mx-auto p-4 md:p-6 space-y-6 md:space-y-8">
            <!-- Loading 狀態 -->
            <div v-if="detailLoading" class="text-center py-12">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary mx-auto"></div>
                <p class="mt-2 text-slate-500">載入中...</p>
            </div>

            <!-- 客戶詳情內容 -->
            <div v-else-if="selectedCustomer" class="space-y-6">
                <!-- Tab 按鈕 -->
                <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
                    <div class="flex border-b border-slate-200">
                        <button
                            @click="activeTab = 'orders'"
                            :class="activeTab === 'orders' ? 'border-b-2 border-blue-600 text-blue-600 bg-blue-50/50' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50'"
                            class="flex-1 px-4 py-3 font-medium transition-colors text-sm">
                            訂單記錄 <span v-if="selectedCustomer.order_count > 0" class="text-xs ml-1">({{ selectedCustomer.order_count }})</span>
                        </button>
                        <button
                            @click="activeTab = 'info'"
                            :class="activeTab === 'info' ? 'border-b-2 border-blue-600 text-blue-600 bg-blue-50/50' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50'"
                            class="flex-1 px-4 py-3 font-medium transition-colors text-sm">
                            客戶資訊
                        </button>
                    </div>

                    <!-- Tab 內容區 -->
                    <div class="p-4 md:p-6">
                        <!-- Tab 1: 訂單記錄 -->
                        <div v-show="activeTab === 'orders'">
                            <!-- 搜尋框（訂單 > 5 筆時顯示） -->
                            <div v-if="selectedCustomer.orders && selectedCustomer.orders.length > 5" class="mb-4">
                                <div class="relative">
                                    <input
                                        v-model="orderSearchQuery"
                                        type="text"
                                        placeholder="搜尋訂單編號或狀態..."
                                        class="w-full px-4 py-2 pl-10 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none text-sm">
                                    <svg class="absolute left-3 top-1/2 transform -translate-y-1/2 w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                    </svg>
                                </div>
                            </div>

                            <!-- 訂單列表 -->
                            <div v-if="filteredOrders && filteredOrders.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                                <div
                                    v-for="order in filteredOrders"
                                    :key="order.id"
                                    class="border border-slate-200 rounded-lg overflow-hidden hover:border-slate-300 transition">
                                    <!-- 訂單卡片頭部 -->
                                    <div
                                        @click="toggleOrderExpand(order.id)"
                                        class="p-3 hover:bg-slate-50 transition cursor-pointer">
                                        <div class="flex items-center justify-between mb-2">
                                            <div class="font-semibold text-slate-900 text-sm">
                                                #{{ order.order_number || order.id }}
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <span :class="getOrderStatusClass(order.order_status)"
                                                      class="px-2 py-0.5 text-xs font-medium rounded-full">
                                                    {{ getOrderStatusText(order.order_status) }}
                                                </span>
                                                <svg
                                                    class="w-4 h-4 text-slate-400 transition-transform"
                                                    :class="expandedOrderId === order.id ? 'rotate-180' : ''"
                                                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                                </svg>
                                            </div>
                                        </div>
                                        <div class="text-sm font-medium text-slate-700 mb-1">
                                            {{ formatPrice(order.total_amount || 0, order.currency) }}
                                        </div>
                                        <div class="text-xs text-slate-500">
                                            {{ formatShortDate(order.created_at) }}
                                        </div>
                                    </div>

                                    <!-- 展開區：商品明細 -->
                                    <div
                                        v-if="expandedOrderId === order.id"
                                        class="px-3 pb-3 pt-0 bg-slate-50 border-t border-slate-200">
                                        <div class="text-xs text-slate-500 mb-3 pt-3">
                                            下單日期：{{ formatDate(order.created_at) }}
                                        </div>
                                        <div class="text-xs font-medium text-slate-700 mb-2">購買商品：</div>
                                        <div v-if="loadingOrderItems && expandedOrderId === order.id" class="text-center py-4 text-xs text-slate-500">
                                            載入商品資料中...
                                        </div>
                                        <div v-else-if="(orderItems[order.id] || []).length > 0" class="space-y-2">
                                            <div
                                                v-for="item in (orderItems[order.id] || [])"
                                                :key="item.id"
                                                class="bg-white rounded-lg p-2 border border-slate-200">
                                                <div class="flex gap-2">
                                                    <div class="w-10 h-10 bg-slate-100 rounded flex items-center justify-center flex-shrink-0 border border-slate-200 overflow-hidden">
                                                        <img v-if="item.product_image" :src="item.product_image" class="w-full h-full object-cover" :alt="item.product_name">
                                                        <svg v-else class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                                        </svg>
                                                    </div>
                                                    <div class="flex-1 min-w-0">
                                                        <div class="font-medium text-sm text-slate-900 truncate">
                                                            {{ item.product_name || '未命名商品' }}
                                                        </div>
                                                        <div class="text-xs text-slate-600 mt-0.5">
                                                            數量：{{ item.quantity }}
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div v-else class="text-center py-4 text-xs text-slate-500">
                                            此訂單無商品資料
                                        </div>
                                        <button
                                            @click.stop="navigateToOrder(order.id)"
                                            class="mt-3 w-full py-2 text-xs text-blue-600 hover:text-blue-800 font-medium bg-white border border-slate-200 rounded-lg hover:bg-blue-50 transition">
                                            前往訂單管理
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="text-sm text-slate-500 text-center py-8">
                                <svg class="w-12 h-12 mx-auto mb-3 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                                </svg>
                                <p v-if="orderSearchQuery">找不到符合「{{ orderSearchQuery }}」的訂單</p>
                                <p v-else>此客戶尚無訂單記錄</p>
                            </div>
                        </div>

                        <!-- Tab 2: 客戶資訊 -->
                        <div v-show="activeTab === 'info'">
                            <div class="space-y-4">
                                <!-- 基本資訊 -->
                                <div class="grid grid-cols-2 gap-4">
                                    <div class="bg-slate-50 rounded-lg p-3">
                                        <span class="text-xs text-slate-500 block mb-1">姓名</span>
                                        <div class="text-sm font-medium text-slate-900">{{ selectedCustomer.full_name || '-' }}</div>
                                    </div>
                                    <div class="bg-slate-50 rounded-lg p-3">
                                        <span class="text-xs text-slate-500 block mb-1">電話</span>
                                        <div class="text-sm font-medium text-slate-900">{{ selectedCustomer.phone || '-' }}</div>
                                    </div>
                                </div>

                                <div class="bg-slate-50 rounded-lg p-3">
                                    <span class="text-xs text-slate-500 block mb-1">Email</span>
                                    <div class="text-sm font-medium text-slate-900 break-all">{{ selectedCustomer.email || '-' }}</div>
                                </div>

                                <div v-if="selectedCustomer.address" class="bg-slate-50 rounded-lg p-3">
                                    <span class="text-xs text-slate-500 block mb-1">地址</span>
                                    <div class="text-sm font-medium text-slate-900">{{ selectedCustomer.address }}</div>
                                </div>

                                <!-- 統計資訊 -->
                                <div class="grid grid-cols-2 gap-4 pt-4 border-t border-slate-200">
                                    <div class="bg-blue-50 rounded-lg p-4 text-center">
                                        <span class="text-xs text-blue-600 block mb-1">訂單數</span>
                                        <div class="text-2xl font-bold text-blue-700">{{ selectedCustomer.order_count || 0 }}</div>
                                    </div>
                                    <div class="bg-green-50 rounded-lg p-4 text-center">
                                        <span class="text-xs text-green-600 block mb-1">總消費</span>
                                        <div class="text-2xl font-bold text-green-700">{{ formatPrice(selectedCustomer.total_spent || 0, displayCurrency) }}</div>
                                    </div>
                                </div>
                            </div>

                            <!-- 備註欄位 -->
                            <div class="mt-6 pt-6 border-t border-slate-200">
                                <label class="block text-sm font-medium text-slate-700 mb-2">備註</label>
                                <textarea
                                    v-model="customerNote"
                                    @blur="saveNote"
                                    rows="4"
                                    class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary outline-none text-sm resize-none"
                                    placeholder="輸入客戶備註..."></textarea>
                                <div class="flex items-center justify-end gap-2 mt-2">
                                    <span v-if="noteSaving" class="text-xs text-slate-500">儲存中...</span>
                                    <span v-if="noteSaved" class="text-xs text-green-600">已儲存</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 載入失敗 -->
            <div v-else class="text-center py-12">
                <svg class="w-12 h-12 mx-auto mb-3 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <p class="text-slate-500">載入客戶資料失敗</p>
                <button @click="navigateTo('list')" class="mt-4 px-4 py-2 bg-primary text-white rounded-lg text-sm">返回列表</button>
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
const CustomersPageComponent = {
    name: 'CustomersPage',
    components: {
        'smart-search-box': BuyGoSmartSearchBox
    },
    template: `<?php echo $customers_component_template; ?>`,
    setup() {
        const { ref, computed, onMounted } = Vue;
        
        // 狀態變數
        const customers = ref([]);
        const loading = ref(false);
        const error = ref(null);
        
        // 分頁狀態
        const currentPage = ref(1);
        const perPage = ref(20);
        const totalCustomers = ref(0);
        
        // 搜尋篩選狀態
        const searchFilter = ref(null);
        const searchFilterName = ref('');
        
        // ========== 路由狀態（新增）==========
        const currentView = ref('list');  // 'list' | 'detail'
        const currentCustomerId = ref(null);
        const selectedCustomer = ref(null);
        const detailLoading = ref(false);

        // UI 狀態（新增）
        const showMobileSearch = ref(false);
        const globalSearchQuery = ref('');

        // 幣別（從系統設定讀取）
        const displayCurrency = ref(window.buygoSettings?.currency || 'JPY');

        // 訂單搜尋（子頁面內用）
        const orderSearchQuery = ref('');
        
        // Tab 分頁狀態
        const activeTab = ref('orders');
        
        // 備註狀態
        const customerNote = ref('');
        const noteSaving = ref(false);
        const noteSaved = ref(false);

        // 幣別：從客戶第一筆訂單或預設
        const systemCurrency = ref('JPY');
        const currentCurrency = ref('JPY');

        // 訂單展開狀態
        const expandedOrderId = ref(null);
        const orderItems = ref({});
        const loadingOrderItems = ref(false);
        
        // Toast 通知狀態
        const toastMessage = ref({
            show: false,
            message: '',
            type: 'success'
        });
        
        // 顯示 Toast 訊息
        const showToast = (message, type = 'success') => {
            toastMessage.value = { show: true, message, type };
            setTimeout(() => {
                toastMessage.value.show = false;
            }, 3000);
        };
        
        // 載入客戶列表
        const loadCustomers = async () => {
            loading.value = true;
            error.value = null;
            
            try {
                let url = `/wp-json/buygo-plus-one/v1/customers?page=${currentPage.value}&per_page=${perPage.value}`;
                
                if (searchFilter.value) {
                    url += `&search=${encodeURIComponent(searchFilter.value)}`;
                }
                
                const response = await fetch(url, {
                    credentials: 'include',
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const result = await response.json();
                
                if (result.success && result.data) {
                    customers.value = result.data;
                    totalCustomers.value = result.total || result.data.length;
                } else {
                    throw new Error(result.message || '載入客戶列表失敗');
                }
            } catch (err) {
                console.error('載入客戶錯誤:', err);
                error.value = err.message;
                customers.value = [];
            } finally {
                loading.value = false;
            }
        };
        
        // ========== 路由邏輯（新增）==========

        // 檢查 URL 參數
        const checkUrlParams = () => {
            const params = window.BuyGoRouter.checkUrlParams();
            const { view, id } = params;

            if (view === 'detail' && id) {
                currentView.value = 'detail';
                currentCustomerId.value = id;
                loadCustomerDetail(id);
            } else {
                currentView.value = 'list';
                currentCustomerId.value = null;
                selectedCustomer.value = null;
            }
        };

        // 導航函數
        const navigateTo = (view, customerId = null, updateUrl = true) => {
            currentView.value = view;

            if (view === 'detail' && customerId) {
                currentCustomerId.value = customerId;
                loadCustomerDetail(customerId);

                if (updateUrl) {
                    window.BuyGoRouter.navigateTo('detail', customerId);
                }
            } else {
                // 返回列表
                currentCustomerId.value = null;
                selectedCustomer.value = null;
                activeTab.value = 'orders';
                orderSearchQuery.value = '';
                expandedOrderId.value = null;
                orderItems.value = {};

                if (updateUrl) {
                    window.BuyGoRouter.goToList();
                }
            }
        };

        // 載入客戶詳情
        const loadCustomerDetail = async (customerId) => {
            detailLoading.value = true;
            try {
                const response = await fetch(`/wp-json/buygo-plus-one/v1/customers/${customerId}`, {
                    credentials: 'include'
                });

                const result = await response.json();

                if (result.success && result.data) {
                    selectedCustomer.value = result.data;
                    customerNote.value = result.data.note || '';
                    noteSaved.value = false;

                    // 設定幣別（從客戶第一筆訂單讀取）
                    if (result.data.orders && result.data.orders.length > 0) {
                        displayCurrency.value = result.data.orders[0].currency || 'JPY';
                    }
                } else {
                    showToast('載入客戶詳情失敗', 'error');
                    navigateTo('list');
                }
            } catch (err) {
                console.error('載入客戶詳情錯誤:', err);
                showToast('載入客戶詳情失敗', 'error');
                navigateTo('list');
            } finally {
                detailLoading.value = false;
            }
        };

        // 全域搜尋處理
        const handleGlobalSearch = (event) => {
            // 可以在這裡實作全域搜尋邏輯
            console.log('Global search:', event.target.value);
        };

        // 幣別切換
        const toggleCurrency = () => {
            const currencies = ['JPY', 'TWD', 'USD'];
            const currentIndex = currencies.indexOf(displayCurrency.value);
            const nextIndex = (currentIndex + 1) % currencies.length;
            displayCurrency.value = currencies[nextIndex];

            // 儲存使用者偏好
            localStorage.setItem('buygo_display_currency', displayCurrency.value);
            showToast(`已切換為 ${displayCurrency.value}`);
        };

        // 跳轉到訂單管理頁面（更新：使用 Deep Link）
        const navigateToOrder = (orderId) => {
            window.location.href = `/buygo-portal/orders/?view=detail&id=${orderId}`;
        };
        
        // 格式化金額（amount 單位為分，除以 100 顯示；currency 可選，缺則用 currentCurrency 或 JPY）
        const formatPrice = (amount, currency = null) => {
            if (amount !== 0 && !amount) return '-';
            const currencyCode = currency || currentCurrency.value || 'JPY';
            const value = amount / 100;
            return `${currencyCode} ${value.toLocaleString('zh-TW', { minimumFractionDigits: 0, maximumFractionDigits: 0 })}`;
        };
        
        // 格式化日期
        const formatDate = (dateString) => {
            if (!dateString) return '-';
            const date = new Date(dateString);
            return date.toLocaleDateString('zh-TW');
        };
        
        // 格式化短日期（月/日）
        const formatShortDate = (dateString) => {
            if (!dateString) return '-';
            const date = new Date(dateString);
            const month = date.getMonth() + 1;
            const day = date.getDate();
            return `${month}/${day}`;
        };

        // 儲存備註
        const saveNote = async () => {
            if (!selectedCustomer.value) return;
            
            noteSaving.value = true;
            noteSaved.value = false;
            
            try {
                const response = await fetch(`/wp-json/buygo-plus-one/v1/customers/${selectedCustomer.value.id}/note`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    credentials: 'include',
                    body: JSON.stringify({ note: customerNote.value })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    noteSaved.value = true;
                    showToast('備註已儲存', 'success');
                    setTimeout(() => {
                        noteSaved.value = false;
                    }, 2000);
                } else {
                    showToast('儲存備註失敗：' + result.message, 'error');
                }
            } catch (err) {
                console.error('儲存備註錯誤:', err);
                showToast('儲存備註失敗', 'error');
            } finally {
                noteSaving.value = false;
            }
        };
        
        // 取得訂單狀態樣式
        const getOrderStatusClass = (status) => {
            const statusClasses = {
                'pending': 'bg-yellow-100 text-yellow-800 border border-yellow-200',
                'processing': 'bg-blue-100 text-blue-800 border border-blue-200',
                'shipped': 'bg-purple-100 text-purple-800 border border-purple-200',
                'completed': 'bg-green-100 text-green-800 border border-green-200',
                'cancelled': 'bg-red-100 text-red-800 border border-red-200'
            };
            return statusClasses[status] || 'bg-slate-100 text-slate-800';
        };
        
        // 取得訂單狀態文字
        const getOrderStatusText = (status) => {
            const statusTexts = {
                'pending': '待處理',
                'processing': '處理中',
                'shipped': '已出貨',
                'completed': '已完成',
                'cancelled': '已取消'
            };
            return statusTexts[status] || status;
        };
        
        // 過濾訂單列表
        const filteredOrders = computed(() => {
            if (!selectedCustomer.value || !selectedCustomer.value.orders) {
                return [];
            }

            const query = orderSearchQuery.value.toLowerCase().trim();
            if (!query) {
                return selectedCustomer.value.orders;
            }

            return selectedCustomer.value.orders.filter(order => {
                const orderNumber = (order.order_number || order.id || '').toString().toLowerCase();
                const orderStatus = (order.order_status || '').toLowerCase();
                const statusText = getOrderStatusText(order.order_status).toLowerCase();

                return orderNumber.includes(query) ||
                       orderStatus.includes(query) ||
                       statusText.includes(query);
            });
        });
        
        // 切換訂單展開
        const toggleOrderExpand = async (orderId) => {
            if (expandedOrderId.value === orderId) {
                expandedOrderId.value = null;
                return;
            }
            expandedOrderId.value = orderId;
            if (!orderItems.value[orderId]) {
                await loadOrderItems(orderId);
            }
        };

        // 載入訂單商品（GET /orders/{id} 的 data.items，price/total 已為元）
        const loadOrderItems = async (orderId) => {
            loadingOrderItems.value = true;
            try {
                const response = await fetch(`/wp-json/buygo-plus-one/v1/orders/${orderId}`, { credentials: 'include' });
                if (!response.ok) throw new Error('Failed to load order items');
                const result = await response.json();
                if (result.success && result.data) {
                    orderItems.value[orderId] = result.data.items || [];
                } else {
                    orderItems.value[orderId] = [];
                }
            } catch (e) {
                console.error('Failed to load order items:', e);
                orderItems.value[orderId] = [];
            } finally {
                loadingOrderItems.value = false;
            }
        };

        
        // 搜尋處理
        const handleSearchSelect = async (item) => {
            searchFilter.value = item.full_name || item.phone || item.email;
            searchFilterName.value = item.full_name || item.phone || item.email;
            currentPage.value = 1;
            await loadCustomers();
        };
        
        const handleSearchInput = (query) => {
            // 搜尋輸入處理（目前無額外邏輯）
        };
        
        const handleSearchClear = () => {
            searchFilter.value = null;
            searchFilterName.value = '';
            currentPage.value = 1;
            loadCustomers();
        };
        
        // 分頁
        const totalPages = computed(() => {
            if (perPage.value === -1) return 1;
            return Math.ceil(totalCustomers.value / perPage.value);
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
                loadCustomers();
            }
        };
        
        const nextPage = () => {
            if (currentPage.value < totalPages.value) {
                currentPage.value++;
                loadCustomers();
            }
        };
        
        const goToPage = (page) => {
            currentPage.value = page;
            loadCustomers();
        };
        
        const changePerPage = () => {
            currentPage.value = 1;
            loadCustomers();
        };
        
        // 初始化
        onMounted(() => {
            loadCustomers();

            // 檢查 URL 參數並設置監聽
            checkUrlParams();
            window.BuyGoRouter.setupPopstateListener(checkUrlParams);

            // 從 localStorage 讀取使用者幣別偏好
            const savedCurrency = localStorage.getItem('buygo_display_currency');
            if (savedCurrency) {
                displayCurrency.value = savedCurrency;
            }
        });
        
        return {
            customers,
            loading,
            error,
            currentPage,
            perPage,
            totalCustomers,
            totalPages,
            visiblePages,
            previousPage,
            nextPage,
            goToPage,
            changePerPage,
            formatPrice,
            formatDate,
            formatShortDate,
            activeTab,
            filteredOrders,
            customerNote,
            noteSaving,
            noteSaved,
            saveNote,
            getOrderStatusClass,
            getOrderStatusText,
            expandedOrderId,
            orderItems,
            loadingOrderItems,
            toggleOrderExpand,
            systemCurrency,
            currentCurrency,
            handleSearchSelect,
            handleSearchInput,
            handleSearchClear,
            searchFilter,
            searchFilterName,
            loadCustomers,
            toastMessage,
            showToast,
            // 新增路由相關
            currentView,
            currentCustomerId,
            selectedCustomer,
            detailLoading,
            showMobileSearch,
            globalSearchQuery,
            displayCurrency,
            orderSearchQuery,
            // 新增方法
            navigateTo,
            checkUrlParams,
            loadCustomerDetail,
            handleGlobalSearch,
            toggleCurrency,
            navigateToOrder
        };
    }
};
</script>

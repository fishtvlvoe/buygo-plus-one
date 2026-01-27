<?php
// 客戶管理頁面元件

// 載入智慧搜尋框元件
require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'components/shared/smart-search-box.php';
?>
<!-- Customers Page Styles -->
<link rel="stylesheet" href="<?php echo esc_url(plugins_url('../css/customers.css', __FILE__)); ?>" />
<?php
$customers_component_template = <<<'HTML'

    <!-- ============================================ -->
    <!-- 頁首部分 -->
    <!-- ============================================ -->
    <header class="page-header">
        <div class="page-header-left" v-show="!showMobileSearch">
            <h1 class="page-header-title">客戶</h1>
            <nav class="page-header-breadcrumb">
                <a href="/buygo-portal/dashboard">首頁</a>
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                <span class="active">客戶</span>
                <span v-if="currentView === 'detail'" class="text-slate-300">/</span>
                <span v-if="currentView === 'detail'" class="text-primary font-medium truncate">詳情 #{{ currentCustomerId }}</span>
            </nav>
        </div>

        <!-- 右側操作區 -->
        <div class="page-header-actions">
            <!-- 手機版搜尋按鈕 -->
            <button @click="showMobileSearch = !showMobileSearch" class="search-toggle-mobile">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
            </button>

            <!-- 桌面版全域搜尋框 -->
            <div class="global-search">
                <input type="text" placeholder="全域搜尋..." v-model="globalSearchQuery" @input="handleGlobalSearch">
                <svg class="search-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
            </div>

            <!-- 通知鈴鐺 -->
            <button class="notification-bell">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
            </button>

            <!-- 幣別切換 -->
            <button @click="toggleCurrency" class="btn btn-secondary btn-sm">
                {{ displayCurrency }}
            </button>
        </div>

        <!-- 手機版搜尋覆蓋層 -->
        <transition name="search-slide">
            <div v-if="showMobileSearch" class="mobile-search-overlay">
                <div class="global-search">
                    <input type="text" placeholder="全域搜尋..." v-model="globalSearchQuery" @input="handleGlobalSearch">
                    <svg class="search-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </div>
                <button @click="showMobileSearch = false" class="btn btn-secondary btn-sm">取消</button>
            </div>
        </transition>
    </header>
    <!-- 結束：頁首部分 -->

    <!-- ============================================ -->
    <!-- 內容區域 -->
    <!-- ============================================ -->

    <!-- 列表檢視 -->
    <div v-show="currentView === 'list'" class="p-2 xs:p-4 md:p-6 w-full max-w-7xl mx-auto space-y-4 md:space-y-6">

        <!-- Smart Search Box (使用設計系統 smart-search-box classes) -->
        <smart-search-box
            api-endpoint="/wp-json/buygo-plus-one/v1/customers"
            :search-fields="['full_name', 'phone', 'email']"
            display-field="full_name"
            display-sub-field="email"
            placeholder="搜尋客戶名稱、電話或 Email..."
            :show-image="false"
            :show-status="false"
            @select="handleCustomerSelect"
        ></smart-search-box>

        <!-- 載入狀態 -->
        <div v-if="loading" class="text-center py-12">
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary mx-auto"></div>
            <p class="mt-2 text-slate-500">載入中...</p>
        </div>

        <!-- 客戶列表 -->
        <div v-else-if="error" class="text-center py-12">
            <p class="text-red-600 mb-4">{{ error }}</p>
            <button @click="loadCustomers" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary-dark transition">重新載入</button>
        </div>

        <!-- Content -->
        <div v-else>
            <!-- 桌面版表格 -->
            <div class="data-table">
                <table>
                    <thead>
                        <tr>
                            <th class="text-center" style="width: 3rem;"><input type="checkbox" @change="toggleSelectAll" :checked="isAllSelected" class="form-checkbox"></th>
                            <th class="text-left" style="width: 25%;">客戶</th>
                            <th class="text-center">訂單數</th>
                            <th class="text-right">總消費</th>
                            <th class="text-center">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="customer in customers" :key="customer.id">
                            <td class="text-center"><input type="checkbox" :value="customer.id" v-model="selectedItems" class="form-checkbox"></td>
                            <td>
                                <div class="customer-info">
                                    <img :src="customer.avatar || 'https://www.gravatar.com/avatar/?d=mp&s=100'" :alt="customer.full_name" class="customer-avatar">
                                    <div class="customer-details">
                                        <div class="customer-name" @click="navigateTo('detail', customer.id)">
                                            {{ customer.full_name || '-' }}
                                        </div>
                                        <div class="customer-phone">{{ customer.phone || '-' }}</div>
                                        <div class="customer-email">{{ customer.email || '-' }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="text-center">{{ customer.order_count || 0 }}</td>
                            <td class="text-right">{{ formatPrice(customer.total_spent || 0, systemCurrency) }}</td>
                            <td class="text-center">
                                <button @click="navigateTo('detail', customer.id)" class="btn btn-primary btn-sm">
                                    查看詳情
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div> <!-- End desktop table -->

            <!-- 手機版卡片 -->
            <div class="card-list">
                <div v-for="customer in customers" :key="customer.id" class="card">
                    <div class="card-title">{{ customer.full_name || '-' }}</div>
                    <div class="card-subtitle">{{ customer.phone || '-' }}</div>
                    <div class="card-meta">
                        <div class="card-meta-item">
                            <span class="card-meta-label">訂單數</span>
                            <span class="card-meta-value">{{ customer.order_count || 0 }}</span>
                        </div>
                        <div class="card-meta-item">
                            <span class="card-meta-label">總消費</span>
                            <span class="card-meta-value">{{ formatPrice(customer.total_spent || 0, systemCurrency) }}</span>
                        </div>
                    </div>
                    <button @click="navigateTo('detail', customer.id)" class="btn btn-primary btn-block">
                        查看詳情
                    </button>
                </div>
            </div> <!-- End mobile cards -->

            <!-- 統一分頁樣式 -->
            <div v-if="totalCustomers > 0" class="pagination-container">
                <div class="pagination-info">
                    顯示 <span class="font-medium">{{ perPage === -1 ? 1 : (currentPage - 1) * perPage + 1 }}</span> 到 <span class="font-medium">{{ perPage === -1 ? totalCustomers : Math.min(currentPage * perPage, totalCustomers) }}</span> 筆，共 <span class="font-medium">{{ totalCustomers }}</span> 筆
                </div>
                <div class="pagination-controls">
                    <select v-model.number="perPage" @change="changePerPage" class="pagination-select">
                        <option :value="5">5 筆</option>
                        <option :value="10">10 筆</option>
                        <option :value="20">20 筆</option>
                        <option :value="50">50 筆</option>
                    </select>
                    <nav class="pagination-nav">
                        <button @click="previousPage" :disabled="currentPage === 1" class="pagination-button first">
                            <span class="sr-only">上一頁</span>
                            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                        </button>
                        <button v-for="p in visiblePages" :key="p" @click="goToPage(p)" :class="['pagination-button page', p === currentPage ? 'active' : '']">
                            {{ p }}
                        </button>
                        <button @click="nextPage" :disabled="currentPage >= totalPages" class="pagination-button last">
                            <span class="sr-only">下一頁</span>
                            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                        </button>
                    </nav>
                </div>
            </div> <!-- End pagination -->
        </div> <!-- End customer list content -->
    </div>
    <!-- 結束：列表檢視 -->

    <!-- 詳情檢視 -->
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

HTML;
?>


<!-- Customers Page Template -->
<script type="text/x-template" id="customers-page-template">
    <?php echo $customers_component_template; ?>
</script>

<!-- Customers Page Component -->
<script>
window.buygoWpNonce = '<?php echo wp_create_nonce("wp_rest"); ?>';
</script>
<script src="<?php echo esc_url(plugins_url('js/components/CustomersPage.js', dirname(__FILE__))); ?>"></script>


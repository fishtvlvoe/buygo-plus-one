<?php
// 客戶管理頁面元件

// 載入智慧搜尋框元件
require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'components/shared/smart-search-box.php';

$customers_component_template = <<<'HTML'
<main class="min-h-screen bg-slate-50">
    <!-- 頁面標題 -->
    <div class="bg-white shadow-sm border-b border-slate-200 px-6 py-4">
        <div class="mb-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900 mb-1 font-title">客戶管理</h1>
                    <p class="text-sm text-slate-500">管理您的客戶資料與訂單記錄</p>
                    
                    <!-- 篩選提示 -->
                    <div v-if="searchFilter" class="mt-2 flex items-center gap-2">
                        <span class="text-xs text-blue-600 bg-blue-50 px-2 py-1 rounded-full border border-blue-200">
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
            
            <!-- 智慧搜尋框 -->
            <smart-search-box
                api-endpoint="/wp-json/buygo-plus-one/v1/customers"
                :search-fields="['full_name', 'phone', 'email']"
                placeholder="搜尋客戶姓名、電話或 Email"
                display-field="full_name"
                display-sub-field="phone"
                :show-currency-toggle="false"
                @select="handleSearchSelect"
                @search="handleSearchInput"
                @clear="handleSearchClear"
            />
        </div>
    </div>

    <!-- 客戶列表容器 -->
    <div class="p-6">
        <!-- 載入狀態 -->
        <div v-if="loading" class="text-center py-8">
            <p class="text-slate-600">載入中...</p>
        </div>
        
        <!-- 錯誤訊息 -->
        <div v-else-if="error" class="text-center py-8">
            <p class="text-red-600">{{ error }}</p>
            <button @click="loadCustomers" class="mt-4 px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-700 font-medium transition shadow-sm">重新載入</button>
        </div>
        
        <!-- 客戶列表 -->
        <div v-else>
            <!-- 桌面版表格 -->
            <div class="hidden md:block bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                <table class="w-full">
                    <thead class="bg-slate-50 border-b border-slate-200">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">客戶名稱</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">電話</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Email</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">訂單數</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">總消費</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">最後下單日期</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">操作</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-slate-200">
                        <tr v-for="customer in customers" :key="customer.id" class="hover:bg-slate-50 transition">
                            <td class="px-4 py-3 text-sm font-medium text-slate-900">{{ customer.full_name || '-' }}</td>
                            <td class="px-4 py-3 text-sm text-slate-600">{{ customer.phone || '-' }}</td>
                            <td class="px-4 py-3 text-sm text-slate-600">{{ customer.email || '-' }}</td>
                            <td class="px-4 py-3 text-sm text-slate-600">{{ customer.order_count || 0 }}</td>
                            <td class="px-4 py-3 text-sm font-semibold text-slate-900">{{ formatPrice(customer.total_spent || 0, systemCurrency) }}</td>
                            <td class="px-4 py-3 text-sm text-slate-600">{{ formatDate(customer.last_order_date) }}</td>
                            <td class="px-4 py-3">
                                <button @click="openCustomerDetail(customer.id)" class="text-primary hover:text-primary-dark text-sm font-medium">
                                    查看詳情
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- 手機版卡片 -->
            <div class="md:hidden space-y-4">
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
                    <button @click="openCustomerDetail(customer.id)" class="w-full py-2 bg-primary text-white rounded-lg text-sm font-medium">
                        查看詳情
                    </button>
                </div>
            </div>
            
            <!-- 桌面版分頁 -->
            <footer class="hidden md:flex items-center justify-between px-6 py-4 bg-white border border-slate-200 rounded-2xl shadow-sm mt-6">
                <div class="flex items-center gap-4">
                    <span class="text-xs text-slate-500 font-medium">
                        <template v-if="perPage === -1">顯示全部 {{ totalCustomers }} 筆</template>
                        <template v-else>顯示 {{ totalCustomers }} 筆中的第 {{ (currentPage - 1) * perPage + 1 }} 到 {{ Math.min(currentPage * perPage, totalCustomers) }} 筆</template>
                    </span>
                    <select 
                        v-model="perPage" 
                        @change="changePerPage"
                        class="px-3 py-1.5 text-xs font-medium border border-slate-200 rounded-lg bg-white focus:ring-1 focus:ring-primary outline-none">
                        <option :value="5">5 / 頁</option>
                        <option :value="10">10 / 頁</option>
                        <option :value="20">20 / 頁</option>
                        <option :value="30">30 / 頁</option>
                        <option :value="50">50 / 頁</option>
                        <option :value="100">100 / 頁</option>
                        <option :value="-1">全部</option>
                    </select>
                </div>
                <div class="flex gap-2">
                    <button 
                        @click="previousPage"
                        :disabled="currentPage === 1"
                        :class="currentPage === 1 ? 'cursor-not-allowed text-slate-400' : 'text-slate-600 hover:bg-slate-50'"
                        class="px-3 py-1.5 bg-white border border-slate-200 rounded-lg text-xs transition">
                        上一頁
                    </button>
                    <button 
                        v-for="page in visiblePages" 
                        :key="page"
                        @click="goToPage(page)"
                        :class="page === currentPage ? 'bg-primary text-white border-primary font-bold shadow-sm' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50'"
                        class="px-3 py-1.5 border rounded-lg text-xs transition">
                        {{ page }}
                    </button>
                    <button 
                        @click="nextPage"
                        :disabled="currentPage === totalPages"
                        :class="currentPage === totalPages ? 'cursor-not-allowed text-slate-400' : 'text-slate-600 hover:bg-slate-50'"
                        class="px-3 py-1.5 bg-white border border-slate-200 rounded-lg text-xs transition">
                        下一頁
                    </button>
                </div>
            </footer>
            
            <!-- 手機版分頁 -->
            <footer class="flex md:hidden items-center justify-between px-4 py-3 bg-white border border-slate-200 rounded-2xl shadow-sm mt-6">
                <div class="flex items-center gap-2">
                    <span class="text-xs text-slate-500 font-medium">
                        <template v-if="perPage === -1">全部 {{ totalCustomers }} 筆</template>
                        <template v-else>第 {{ (currentPage - 1) * perPage + 1 }}-{{ Math.min(currentPage * perPage, totalCustomers) }} 筆</template>
                    </span>
                    <select 
                        v-model="perPage" 
                        @change="changePerPage"
                        class="text-xs px-2 py-1.5 border border-slate-200 rounded-lg bg-white outline-none">
                        <option :value="5">5/頁</option>
                        <option :value="10">10/頁</option>
                        <option :value="20">20/頁</option>
                        <option :value="30">30/頁</option>
                        <option :value="50">50/頁</option>
                        <option :value="100">100/頁</option>
                        <option :value="-1">全部</option>
                    </select>
                </div>
                <div class="flex gap-1.5">
                    <button 
                        @click="previousPage"
                        :disabled="currentPage === 1"
                        class="w-8 h-8 flex items-center justify-center border border-slate-200 rounded-lg bg-white transition"
                        :class="currentPage === 1 ? 'text-slate-400 cursor-not-allowed' : 'text-slate-600 hover:bg-slate-50'">
                        ◀
                    </button>
                    <button 
                        class="w-8 h-8 flex items-center justify-center bg-primary text-white rounded-lg text-xs font-bold shadow-sm">
                        {{ currentPage }}
                    </button>
                    <button 
                        @click="nextPage"
                        :disabled="currentPage === totalPages"
                        class="w-8 h-8 flex items-center justify-center border border-slate-200 rounded-lg bg-white transition"
                        :class="currentPage === totalPages ? 'text-slate-400 cursor-not-allowed' : 'text-slate-600 hover:bg-slate-50'">
                        ▶
                    </button>
                </div>
            </footer>
        </div>
    </div>
    
    <!-- 客戶詳情 Modal -->
    <div v-if="showCustomerModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50" @click.self="closeCustomerModal">
        <div class="bg-white rounded-2xl shadow-xl max-w-3xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <!-- 標題列 -->
            <div class="p-6 border-b border-slate-200">
                <div class="flex items-center justify-between">
                    <h2 class="text-xl font-bold text-slate-900 font-title">客戶詳情</h2>
                    <button @click="closeCustomerModal" class="text-slate-400 hover:text-slate-600 transition">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            </div>
            
            <!-- Tab 按鈕 -->
            <div v-if="currentCustomer" class="px-6 pt-4 border-b border-slate-200">
                <div class="flex gap-1">
                    <button 
                        @click="activeTab = 'orders'"
                        :class="activeTab === 'orders' ? 'border-b-2 border-blue-600 text-blue-600' : 'text-slate-600 hover:text-slate-900'"
                        class="px-4 py-3 font-medium transition-colors min-h-[44px]">
                        訂單記錄 <span v-if="currentCustomer.order_count > 0" class="text-xs ml-1">({{ currentCustomer.order_count }})</span>
                    </button>
                    <button 
                        @click="activeTab = 'info'"
                        :class="activeTab === 'info' ? 'border-b-2 border-blue-600 text-blue-600' : 'text-slate-600 hover:text-slate-900'"
                        class="px-4 py-3 font-medium transition-colors min-h-[44px]">
                        客戶資訊
                    </button>
                </div>
            </div>
            
            <!-- 內容區域 -->
            <div v-if="currentCustomer" class="p-6">
                <!-- Tab 1: 訂單記錄 -->
                <div v-show="activeTab === 'orders'">
                    <!-- 搜尋框（訂單 > 5 筆時顯示） -->
                    <div v-if="currentCustomer.orders && currentCustomer.orders.length > 5" class="mb-4">
                        <div class="relative">
                            <input 
                                v-model="searchQuery"
                                type="text"
                                placeholder="搜尋訂單編號或狀態..."
                                class="w-full px-4 py-2 pl-10 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none text-sm">
                            <svg class="absolute left-3 top-1/2 transform -translate-y-1/2 w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                        </div>
                        <div v-if="searchQuery && filteredOrders.length > 0" class="text-xs text-slate-500 mt-2">
                            找到 {{ filteredOrders.length }} 筆訂單
                        </div>
                    </div>
                    
                    <!-- 訂單列表 -->
                    <div v-if="filteredOrders && filteredOrders.length > 0" class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <div 
                            v-for="order in filteredOrders" 
                            :key="order.id"
                            class="border border-slate-200 rounded-lg overflow-hidden transition-all">
                            <!-- 頭部：點擊展開/收合 -->
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
                                            class="w-4 h-4 text-slate-400 transition-transform flex-shrink-0"
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
                                            <div class="w-12 h-12 bg-slate-100 rounded flex items-center justify-center flex-shrink-0">
                                                <svg class="w-6 h-6 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                                </svg>
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <div class="font-medium text-sm text-slate-900 truncate">
                                                    {{ item.product_name || '未命名商品' }}
                                                </div>
                                                <div class="text-xs text-slate-600 mt-1">
                                                    數量：{{ item.quantity }} × {{ formatPrice(Math.round((item.price || 0) * 100), order.currency) }}
                                                </div>
                                                <div class="text-xs text-slate-500 mt-1">
                                                    已出貨：<span class="text-green-600 font-medium">{{ item.shipped_quantity || 0 }}</span> | 待出貨：<span class="text-orange-600 font-medium">{{ item.pending_quantity || 0 }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div v-else class="text-center py-4 text-xs text-slate-500">
                                    此訂單無商品資料
                                </div>
                                <div class="mt-3 pt-3 border-t border-slate-200 space-y-1">
                                    <div class="flex justify-between text-xs">
                                        <span class="text-slate-600">商品總數：</span>
                                        <span class="font-medium text-slate-900">{{ (orderItems[order.id] || []).reduce((s,i)=>s+(i.quantity||0),0) }}</span>
                                    </div>
                                    <div class="flex justify-between text-sm">
                                        <span class="text-slate-600">訂單總金額：</span>
                                        <span class="font-bold text-slate-900">{{ formatPrice(order.total_amount || 0, order.currency) }}</span>
                                    </div>
                                    <button 
                                        @click.stop="navigateToOrder(order.id)" 
                                        class="mt-2 w-full py-1.5 text-xs text-blue-600 hover:text-blue-800 font-medium">
                                        前往訂單管理
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-sm text-slate-500 text-center py-8">
                        <p v-if="searchQuery">找不到符合「{{ searchQuery }}」的訂單</p>
                        <p v-else>此客戶尚無訂單記錄</p>
                    </div>
                </div>
                
                <!-- Tab 2: 客戶資訊 -->
                <div v-show="activeTab === 'info'">
                    <div class="space-y-3 mb-6">
                        <!-- 基本資訊（2 欄） -->
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <span class="text-xs text-slate-500">姓名</span>
                                <div class="text-sm font-medium text-slate-900">{{ currentCustomer.full_name || '-' }}</div>
                            </div>
                            <div>
                                <span class="text-xs text-slate-500">電話</span>
                                <div class="text-sm font-medium text-slate-900">{{ currentCustomer.phone || '-' }}</div>
                            </div>
                        </div>
                        
                        <!-- Email（單欄） -->
                        <div>
                            <span class="text-xs text-slate-500">Email</span>
                            <div class="text-sm font-medium text-slate-900 break-all">{{ currentCustomer.email || '-' }}</div>
                        </div>
                        
                        <!-- 地址（單欄） -->
                        <div v-if="currentCustomer.address">
                            <span class="text-xs text-slate-500">地址</span>
                            <div class="text-sm font-medium text-slate-900">{{ currentCustomer.address }}</div>
                        </div>
                        
                        <!-- 統計資訊（2 欄） -->
                        <div class="grid grid-cols-2 gap-4 pt-3 border-t border-slate-200">
                            <div>
                                <span class="text-xs text-slate-500">訂單數</span>
                                <div class="text-lg font-semibold text-slate-900">{{ currentCustomer.order_count || 0 }}</div>
                            </div>
                            <div>
                                <span class="text-xs text-slate-500">總消費</span>
                                <div class="text-lg font-bold text-blue-600">{{ formatPrice(currentCustomer.total_spent || 0, systemCurrency) }}</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- 備註欄位 -->
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">備註</label>
                        <textarea 
                            v-model="customerNote"
                            @blur="saveNote"
                            rows="4"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-1 focus:ring-primary focus:border-primary outline-none text-sm"
                            placeholder="輸入客戶備註..."></textarea>
                        <div v-if="noteSaving" class="text-xs text-slate-500 mt-1">儲存中...</div>
                        <div v-if="noteSaved" class="text-xs text-green-600 mt-1">已儲存</div>
                    </div>
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
        
        // Modal 狀態
        const showCustomerModal = ref(false);
        const currentCustomer = ref(null);
        const activeTab = ref('orders'); // Tab 分頁狀態
        const searchQuery = ref(''); // 搜尋關鍵字
        
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

        // 從客戶第一筆訂單讀取幣別，供 formatPrice 與總消費使用
        const loadSystemCurrency = () => {
            if (currentCustomer.value?.orders?.length) {
                const c = currentCustomer.value.orders[0].currency;
                systemCurrency.value = c || 'JPY';
                currentCurrency.value = systemCurrency.value;
            }
        };
        
        // 開啟客戶詳情
        const openCustomerDetail = async (customerId) => {
            try {
                const response = await fetch(`/wp-json/buygo-plus-one/v1/customers/${customerId}`, {
                    credentials: 'include'
                });
                
                const result = await response.json();
                
                if (result.success && result.data) {
                    currentCustomer.value = result.data;
                    customerNote.value = result.data.note || '';
                    noteSaved.value = false;
                    loadSystemCurrency();
                    showCustomerModal.value = true;
                } else {
                    showToast('載入客戶詳情失敗', 'error');
                }
            } catch (err) {
                console.error('載入客戶詳情錯誤:', err);
                showToast('載入客戶詳情失敗', 'error');
            }
        };
        
        // 關閉客戶詳情 Modal
        const closeCustomerModal = () => {
            showCustomerModal.value = false;
            currentCustomer.value = null;
            customerNote.value = '';
            noteSaved.value = false;
            activeTab.value = 'orders'; // 重置 Tab
            searchQuery.value = ''; // 清除搜尋
            expandedOrderId.value = null;
            orderItems.value = {};
        };
        
        // 儲存備註
        const saveNote = async () => {
            if (!currentCustomer.value) return;
            
            noteSaving.value = true;
            noteSaved.value = false;
            
            try {
                const response = await fetch(`/wp-json/buygo-plus-one/v1/customers/${currentCustomer.value.id}/note`, {
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
            if (!currentCustomer.value || !currentCustomer.value.orders) {
                return [];
            }
            
            const query = searchQuery.value.toLowerCase().trim();
            if (!query) {
                return currentCustomer.value.orders;
            }
            
            return currentCustomer.value.orders.filter(order => {
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

        // 跳轉到訂單管理頁面
        const navigateToOrder = (orderId) => {
            closeCustomerModal();
            window.location.href = `/buygo-portal/orders/?openDetail=${orderId}`;
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
            showCustomerModal,
            currentCustomer,
            activeTab,
            searchQuery,
            filteredOrders,
            openCustomerDetail,
            closeCustomerModal,
            navigateToOrder,
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
            showToast
        };
    }
};
</script>

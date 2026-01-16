<?php
// 訂單管理頁面元件
$orders_component_template = <<<'HTML'
<main class="min-h-screen bg-slate-50">
    <!-- 頁面標題 -->
    <div class="bg-white shadow-sm border-b border-slate-200 px-6 py-4">
        <div class="mb-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900 mb-1 font-title">訂單管理</h1>
                    <p class="text-sm text-slate-500">管理您的訂單、狀態與出貨</p>
                    
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
                api-endpoint="/wp-json/buygo-plus-one/v1/orders"
                :search-fields="['invoice_no', 'customer_name']"
                placeholder="搜尋訂單編號或客戶名稱"
                display-field="invoice_no"
                display-sub-field="customer_name"
                :show-currency-toggle="false"
                @select="handleSearchSelect"
                @search="handleSearchInput"
                @clear="handleSearchClear"
            />
        </div>
    </div>

    <!-- 訂單列表容器 -->
    <div class="p-6">
        <!-- 載入狀態 -->
        <div v-if="loading" class="text-center py-8">
            <p class="text-slate-600">載入中...</p>
        </div>
        
        <!-- 錯誤訊息 -->
        <div v-else-if="error" class="text-center py-8">
            <p class="text-red-600">{{ error }}</p>
            <button @click="loadOrders" class="mt-4 px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-700 font-medium transition shadow-sm">重新載入</button>
        </div>
        
        <!-- 訂單列表 -->
        <div v-else>
            <!-- 桌面版表格 -->
            <div class="hidden md:block bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                <table class="w-full">
                    <thead class="bg-slate-50 border-b border-slate-200">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">
                                <input type="checkbox" @change="toggleSelectAll" class="rounded border-slate-300">
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">訂單編號</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">客戶名稱</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">商品數量</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">總金額</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">狀態</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">下單日期</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">操作</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-slate-200">
                        <tr v-for="order in orders" :key="order.id" class="hover:bg-slate-50 transition">
                            <td class="px-4 py-3">
                                <input type="checkbox" :value="order.id" v-model="selectedItems" class="rounded border-slate-300">
                            </td>
                            <td class="px-4 py-3 text-sm font-medium text-slate-900">{{ order.invoice_no }}</td>
                            <td class="px-4 py-3 text-sm text-slate-600">{{ order.customer_name }}</td>
                            <td class="px-4 py-3 text-sm text-slate-600">{{ order.total_items }} 件</td>
                            <td class="px-4 py-3 text-sm font-semibold text-slate-900">{{ formatPrice(order.total_amount, order.currency) }}</td>
                            <td class="px-4 py-3">
                                <span :class="getStatusClass(order.status)" class="px-2 py-1 text-xs font-medium rounded-full">
                                    {{ getStatusText(order.status) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-slate-600">{{ formatDate(order.created_at) }}</td>
                            <td class="px-4 py-3">
                                <button @click="viewOrderDetails(order)" class="text-primary hover:text-primary-dark text-sm font-medium">
                                    查看詳情
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- 手機版卡片 -->
            <div class="md:hidden space-y-4">
                <div v-for="order in orders" :key="order.id" class="bg-white border border-slate-200 rounded-xl p-4 mb-3">
                    <div class="flex items-start justify-between mb-3">
                        <div class="flex-1">
                            <div class="text-sm font-bold text-slate-900 mb-1">{{ order.invoice_no }}</div>
                            <div class="text-xs text-slate-500">{{ order.customer_name }}</div>
                        </div>
                        <input type="checkbox" :value="order.id" v-model="selectedItems" class="rounded border-slate-300">
                    </div>
                    
                    <div class="grid grid-cols-2 gap-2 mb-3 text-xs">
                        <div>
                            <span class="text-slate-500">商品數量：</span>
                            <span class="font-medium text-slate-900">{{ order.total_items }} 件</span>
                        </div>
                        <div>
                            <span class="text-slate-500">總金額：</span>
                            <span class="font-bold text-slate-900">{{ formatPrice(order.total_amount, order.currency) }}</span>
                        </div>
                        <div>
                            <span class="text-slate-500">狀態：</span>
                            <span :class="getStatusClass(order.status)" class="px-2 py-0.5 text-xs font-medium rounded-full">
                                {{ getStatusText(order.status) }}
                            </span>
                        </div>
                        <div>
                            <span class="text-slate-500">下單日期：</span>
                            <span class="text-slate-900">{{ formatDate(order.created_at) }}</span>
                        </div>
                    </div>
                    
                    <button @click="viewOrderDetails(order)" class="w-full py-2 bg-primary text-white rounded-lg text-sm font-medium">
                        查看詳情
                    </button>
                </div>
            </div>
            
            <!-- 桌面版分頁 -->
            <footer class="hidden md:flex items-center justify-between px-6 py-4 bg-white border border-slate-200 rounded-2xl shadow-sm mt-6">
                <div class="flex items-center gap-4">
                    <span class="text-xs text-slate-500 font-medium">
                        <template v-if="perPage === -1">顯示全部 {{ totalOrders }} 筆</template>
                        <template v-else>顯示 {{ totalOrders }} 筆中的第 {{ (currentPage - 1) * perPage + 1 }} 到 {{ Math.min(currentPage * perPage, totalOrders) }} 筆</template>
                    </span>
                    <select 
                        v-model="perPage" 
                        @change="changePerPage"
                        class="px-3 py-1.5 text-xs font-medium border border-slate-200 rounded-lg bg-white focus:ring-1 focus:ring-primary outline-none">
                        <option :value="5">5 / 頁</option>
                        <option :value="10">10 / 頁</option>
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
                        <template v-if="perPage === -1">全部 {{ totalOrders }} 筆</template>
                        <template v-else>第 {{ (currentPage - 1) * perPage + 1 }}-{{ Math.min(currentPage * perPage, totalOrders) }} 筆</template>
                    </span>
                    <select 
                        v-model="perPage" 
                        @change="changePerPage"
                        class="text-xs px-2 py-1.5 border border-slate-200 rounded-lg bg-white outline-none">
                        <option :value="5">5/頁</option>
                        <option :value="10">10/頁</option>
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
    
    <!-- 訂單詳情 Modal -->
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
                            <span class="text-sm font-medium text-slate-900">{{ currentOrder.invoice_no }}</span>
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
                                {{ shipping ? '出貨中...' : ('執行出貨 (' + item.allocated_quantity + ' 個)') }}
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
</main>
HTML;
?>

<script>
const OrdersPageComponent = {
    name: 'OrdersPage',
    components: {
        'smart-search-box': BuyGoSmartSearchBox
    },
    template: `<?php echo $orders_component_template; ?>`,
    setup() {
        const { ref, computed, onMounted } = Vue;
        
        // 狀態變數
        const orders = ref([]);
        const loading = ref(false);
        const error = ref(null);
        
        // 分頁狀態
        const currentPage = ref(1);
        const perPage = ref(10);
        const totalOrders = ref(0);
        
        // 搜尋篩選狀態
        const searchFilter = ref(null);
        const searchFilterName = ref('');
        
        // Modal 狀態
        const showOrderModal = ref(false);
        const currentOrder = ref(null);
        const shipping = ref(false);
        
        // 批次操作
        const selectedItems = ref([]);
        
        // 載入訂單
        const loadOrders = async () => {
            loading.value = true;
            error.value = null;
            
            try {
                let url = `/wp-json/buygo-plus-one/v1/orders?page=${currentPage.value}&per_page=${perPage.value}`;
                
                if (searchFilter.value) {
                    url += `&id=${searchFilter.value}`;
                }
                
                const response = await fetch(url, {
                    credentials: 'include',
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const result = await response.json();
                
                if (result.success && result.data) {
                    orders.value = result.data;
                    totalOrders.value = result.total || result.data.length;
                } else {
                    throw new Error(result.message || '載入訂單失敗');
                }
            } catch (err) {
                console.error('載入訂單錯誤:', err);
                error.value = err.message;
                orders.value = [];
            } finally {
                loading.value = false;
            }
        };
        
        // 格式化金額
        const formatPrice = (price, currency = 'TWD') => {
            return `${price.toLocaleString()} ${currency}`;
        };
        
        // 格式化日期
        const formatDate = (dateString) => {
            if (!dateString) return '';
            const date = new Date(dateString);
            return date.toLocaleDateString('zh-TW');
        };
        
        // 取得狀態樣式
        const getStatusClass = (status) => {
            const statusClasses = {
                'pending': 'bg-yellow-100 text-yellow-800 border border-yellow-200',
                'processing': 'bg-blue-100 text-blue-800 border border-blue-200',
                'shipped': 'bg-purple-100 text-purple-800 border border-purple-200',
                'completed': 'bg-green-100 text-green-800 border border-green-200',
                'cancelled': 'bg-red-100 text-red-800 border border-red-200'
            };
            return statusClasses[status] || 'bg-slate-100 text-slate-800';
        };
        
        // 取得狀態文字
        const getStatusText = (status) => {
            const statusTexts = {
                'pending': '待處理',
                'processing': '處理中',
                'shipped': '已出貨',
                'completed': '已完成',
                'cancelled': '已取消'
            };
            return statusTexts[status] || status;
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
        
        // 執行出貨
        const shipOrderItem = async (item) => {
            if (!confirm(`確定要出貨 ${item.allocated_quantity} 個「${item.product_name}」嗎？`)) {
                return;
            }
            
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
                    alert(`出貨成功！出貨單號：SH-${result.shipment_id}`);
                    // 重新載入訂單詳情
                    await loadOrderDetail(item.order_id);
                } else {
                    alert('出貨失敗：' + result.message);
                }
            } catch (err) {
                console.error('出貨失敗:', err);
                alert('出貨失敗：' + err.message);
            } finally {
                shipping.value = false;
            }
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
        
        // 搜尋處理
        const handleSearchSelect = async (item) => {
            searchFilter.value = item.id;
            searchFilterName.value = item.invoice_no;
            await loadOrders();
        };
        
        const handleSearchInput = (query) => {
            console.log('搜尋:', query);
        };
        
        const handleSearchClear = () => {
            searchFilter.value = null;
            searchFilterName.value = '';
            loadOrders();
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
        });
        
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
            shipOrderItem,
            loadOrderDetail,
            shipping,
            handleSearchSelect,
            handleSearchInput,
            handleSearchClear,
            toggleSelectAll,
            selectedItems,
            searchFilter,
            searchFilterName,
            showOrderModal,
            currentOrder,
            loadOrders
        };
    }
};
</script>

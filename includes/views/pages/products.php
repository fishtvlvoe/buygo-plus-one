<?php
// 商品管理頁面元件
$products_component_template = <<<'HTML'
<main class="min-h-screen bg-gray-50">
    <!-- 頁面標題 -->
    <div class="bg-white shadow-sm border-b border-gray-200 px-6 py-4">
        <div class="mb-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900 mb-1 font-title">商品管理</h1>
                    <p class="text-sm text-slate-500">管理您的庫存、價格與訂單分配</p>
                </div>
                
                <div class="flex items-center gap-3">
                    <!-- 匯出 CSV 按鈕 -->
                    <button 
                        @click="exportCSV"
                        class="px-4 py-2 bg-white border border-slate-200 rounded-lg text-sm font-medium hover:bg-slate-50 transition shadow-sm flex items-center gap-2"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        匯出 CSV
                    </button>
                </div>
            </div>
            
            <!-- 智慧搜尋框 -->
            <smart-search-box
                api-endpoint="/wp-json/buygo-plus-one/v1/products"
                :search-fields="['name', 'id']"
                placeholder="搜尋商品、客戶名字或訂單編號"
                display-field="name"
                display-sub-field="id"
                :show-currency-toggle="true"
                default-currency="JPY"
                @select="handleSearchSelect"
                @search="handleSearchInput"
                @clear="handleSearchClear"
                @currency-change="handleCurrencyChange"
            />
        </div>
    </div>

    <!-- 商品列表容器 -->
    <div class="p-6">
        <!-- 載入狀態 -->
        <div v-if="loading" class="text-center py-8">
            <p class="text-gray-600">載入中...</p>
        </div>
        
        <!-- 錯誤訊息 -->
        <div v-else-if="error" class="text-center py-8">
            <p class="text-red-600">{{ error }}</p>
            <button @click="loadProducts" class="mt-4 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium">重新載入</button>
        </div>
        
        <!-- 商品列表 -->
        <div v-else>
            <!-- 批次操作工具列 -->
            <div v-if="selectedItems.length > 0" class="mb-4 bg-blue-50 border border-blue-200 rounded-lg p-4 flex items-center justify-between">
                <div class="text-sm text-blue-700">
                    已選擇 {{ selectedItems.length }} 個商品
                </div>
                <button 
                    @click="batchDelete"
                    class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 font-medium transition-colors"
                >
                    批次刪除
                </button>
            </div>
        <!-- 桌面版表格 -->
        <div class="hidden md:block overflow-x-auto">
            <table class="min-w-full bg-white shadow-sm rounded-lg overflow-hidden">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <input 
                                type="checkbox" 
                                @change="toggleSelectAll"
                                :checked="selectedItems.length === products.length && products.length > 0"
                                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                            />
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">商品</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">價格</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">狀態</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">已下單</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">已採購</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">預訂</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">操作</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <tr v-for="product in products" :key="product.id" class="hover:bg-gray-50">
                        <td class="px-4 py-3 whitespace-nowrap">
                            <input 
                                type="checkbox" 
                                :value="product.id"
                                v-model="selectedItems"
                                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                            />
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 h-12 w-12 flex items-center justify-center bg-gray-100 rounded-lg mr-3">
                                    <span v-if="!product.image" class="text-2xl">📦</span>
                                    <img v-else :src="product.image" :alt="product.name" class="h-12 w-12 object-cover rounded-lg">
                                </div>
                                <div>
                                    <div class="text-sm font-medium text-gray-900">{{ product.name }}</div>
                                    <div class="text-sm text-gray-500">ID: {{ product.id }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                            {{ formatPrice(product.price, product.currency) }}
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <button 
                                @click="toggleStatus(product)"
                                :class="product.status === 'published' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'"
                                class="px-3 py-1 rounded-full text-xs font-medium hover:opacity-80 transition-opacity"
                            >
                                {{ product.status === 'published' ? '已上架' : '已下架' }}
                            </button>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                            {{ product.ordered }}
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <input 
                                type="number" 
                                v-model.number="product.purchased"
                                @blur="savePurchased(product)"
                                class="w-20 px-2 py-1 rounded border-0 bg-green-50 text-green-700 focus:ring-2 focus:ring-green-500 focus:outline-none"
                                min="0"
                            />
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm font-semibold text-orange-600">
                            {{ calculateReserved(product) }}
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm">
                            <button class="text-blue-600 hover:text-blue-800 font-medium">編輯</button>
                            <button @click="deleteProduct(product.id)" class="ml-3 text-red-600 hover:text-red-800 font-medium">刪除</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- 手機版卡片 -->
        <div class="md:hidden space-y-4">
            <div 
                v-for="product in products" 
                :key="product.id"
                class="bg-white rounded-lg shadow-sm p-4 border border-gray-200"
            >
                <div class="flex items-start justify-between mb-3">
                    <div class="flex items-center flex-1">
                        <div class="flex-shrink-0 h-16 w-16 flex items-center justify-center bg-gray-100 rounded-lg mr-3">
                            <span v-if="!product.image" class="text-3xl">📦</span>
                            <img v-else :src="product.image" :alt="product.name" class="h-16 w-16 object-cover rounded-lg">
                        </div>
                        <div class="flex-1">
                            <div class="flex items-center justify-between mb-1">
                                <h3 class="text-base font-semibold text-gray-900">{{ product.name }}</h3>
                                <button 
                                    @click="toggleStatus(product)"
                                    :class="product.status === 'published' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'"
                                    class="px-2 py-1 rounded-full text-xs font-medium"
                                >
                                    {{ product.status === 'published' ? '已上架' : '已下架' }}
                                </button>
                            </div>
                            <div class="text-sm text-gray-500 mb-2">ID: {{ product.id }}</div>
                            <div class="text-lg font-bold text-gray-900">{{ formatPrice(product.price, product.currency) }}</div>
                        </div>
                    </div>
                </div>
                
                <div class="grid grid-cols-3 gap-3 mb-3">
                    <div class="text-center">
                        <div class="text-xs text-gray-500 mb-1">已下單</div>
                        <div class="text-base font-semibold text-gray-900">{{ product.ordered }}</div>
                    </div>
                    <div class="text-center">
                        <div class="text-xs text-gray-500 mb-1">已採購</div>
                        <input 
                            type="number" 
                            v-model.number="product.purchased"
                            @blur="savePurchased(product)"
                            class="w-full px-2 py-1 rounded border-0 bg-green-50 text-green-700 text-center text-base font-semibold focus:ring-2 focus:ring-green-500 focus:outline-none"
                            min="0"
                        />
                    </div>
                    <div class="text-center">
                        <div class="text-xs text-gray-500 mb-1">預訂</div>
                        <div class="text-base font-semibold text-orange-600">{{ calculateReserved(product) }}</div>
                    </div>
                </div>
                
                <div class="flex gap-2">
                    <button class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium text-sm">編輯</button>
                    <button @click="deleteProduct(product.id)" class="flex-1 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 font-medium text-sm">刪除</button>
                </div>
            </div>
        </div>
        </div>
    </div>
</main>
HTML;
?>

<script>
const ProductsPageComponent = {
    name: 'ProductsPage',
    components: {
        'smart-search-box': BuyGoSmartSearchBox
    },
    template: `<?php echo $products_component_template; ?>`,
    setup() {
        const { ref, onMounted } = Vue;
        
        const products = ref([]);
        const selectedItems = ref([]);
        const loading = ref(true);
        const error = ref(null);
        
        // 載入商品
        const loadProducts = async () => {
            loading.value = true;
            error.value = null;
            try {
                const response = await fetch('/wp-json/buygo-plus-one/v1/products');
                const result = await response.json();
                if (result.success) {
                    products.value = result.data;
                } else {
                    error.value = '載入商品失敗';
                }
            } catch (err) {
                error.value = '網路錯誤：' + err.message;
            } finally {
                loading.value = false;
            }
        };
        
        const formatPrice = (price, currency) => {
            return `${price.toLocaleString()} ${currency}`;
        };

        const calculateReserved = (product) => {
            return Math.max(0, product.ordered - product.purchased);
        };

        const toggleSelectAll = (event) => {
            if (event.target.checked) {
                selectedItems.value = products.value.map(p => p.id);
            } else {
                selectedItems.value = [];
            }
        };

        // 切換狀態
        const toggleStatus = async (product) => {
            const newStatus = product.status === 'published' ? 'private' : 'published';
            try {
                const response = await fetch(`/wp-json/buygo-plus-one/v1/products/${product.id}`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        status: newStatus
                    })
                });
                const result = await response.json();
                if (result.success) {
                    product.status = newStatus;
                } else {
                    console.error('更新狀態失敗:', result);
                }
            } catch (err) {
                console.error('更新狀態失敗:', err);
            }
        };

        // 儲存已採購數量
        const savePurchased = async (product) => {
            try {
                const response = await fetch(`/wp-json/buygo-plus-one/v1/products/${product.id}`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        purchased: product.purchased
                    })
                });
                const result = await response.json();
                if (result.success) {
                    console.log('已採購數量更新成功');
                } else {
                    console.error('更新失敗:', result);
                }
            } catch (err) {
                console.error('更新失敗:', err);
            }
        };

        const deleteProduct = (id) => {
            if (confirm('確定要刪除此商品嗎？')) {
                products.value = products.value.filter(p => p.id !== id);
                // TODO: API 整合時呼叫刪除 API
                console.log('刪除商品:', id);
            }
        };

        // 批次刪除
        const batchDelete = async () => {
            if (selectedItems.value.length === 0) {
                return;
            }
            
            if (!confirm(`確定要刪除 ${selectedItems.value.length} 個商品嗎？此操作無法復原。`)) {
                return;
            }
            
            try {
                const response = await fetch('/wp-json/buygo-plus-one/v1/products/batch-delete', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        ids: selectedItems.value
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // 從列表中移除已刪除的商品
                    products.value = products.value.filter(p => !selectedItems.value.includes(p.id));
                    selectedItems.value = [];
                    console.log('批次刪除成功');
                } else {
                    alert('批次刪除失敗：' + result.message);
                }
            } catch (err) {
                console.error('批次刪除錯誤:', err);
                alert('批次刪除失敗');
            }
        };

        // 匯出 CSV
        const exportCSV = async (event) => {
            try {
                // 顯示載入中
                const button = event.target.closest('button');
                const originalText = button.innerHTML;
                button.innerHTML = '<svg class="w-5 h-5 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg> 匯出中...';
                button.disabled = true;
                
                const response = await fetch('/wp-json/buygo-plus-one/v1/products/export', {
                    method: 'GET',
                });
                
                if (!response.ok) {
                    throw new Error('匯出失敗');
                }
                
                // 取得 blob
                const blob = await response.blob();
                
                // 建立下載連結
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                
                // 檔案名稱：buygo_products_2026-01-16.csv
                const today = new Date().toISOString().split('T')[0];
                a.download = `buygo_products_${today}.csv`;
                
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                window.URL.revokeObjectURL(url);
                
                console.log('CSV 匯出成功');
                
                // 恢復按鈕狀態
                button.innerHTML = originalText;
                button.disabled = false;
                
            } catch (err) {
                console.error('匯出 CSV 錯誤:', err);
                alert('匯出失敗');
                
                // 恢復按鈕狀態
                const button = event.target.closest('button');
                button.innerHTML = '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg> 匯出 CSV';
                button.disabled = false;
            }
        };

        // 處理搜尋選擇
        const handleSearchSelect = (item) => {
            console.log('選擇商品:', item);
            // 可以選擇：
            // 選項 1：直接打開編輯 Modal
            // 選項 2：設定搜尋條件並重新載入列表
            // 目前使用選項 2
            loadProducts();
        };

        // 處理搜尋輸入
        const handleSearchInput = (query) => {
            console.log('搜尋:', query);
            // 這個事件會在使用者輸入時觸發
            // 智慧搜尋框會自動處理建議列表
        };

        // 處理清除搜尋
        const handleSearchClear = () => {
            console.log('清除搜尋');
            loadProducts();
        };

        // 處理幣別切換
        const handleCurrencyChange = (currency) => {
            console.log('切換幣別:', currency);
            // TODO: 實作幣別轉換邏輯
        };
        
        onMounted(() => {
            loadProducts();
        });

        return {
            products,
            selectedItems,
            loading,
            error,
            formatPrice,
            calculateReserved,
            toggleSelectAll,
            toggleStatus,
            savePurchased,
            deleteProduct,
            loadProducts,
            batchDelete,
            exportCSV,
            handleSearchSelect,
            handleSearchInput,
            handleSearchClear,
            handleCurrencyChange
        };
    }
};
</script>

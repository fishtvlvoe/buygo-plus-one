<?php
// 商品管理頁面元件
$products_component_template = <<<'HTML'
<main class="min-h-screen bg-gray-50">
    <!-- 頁面標題 -->
    <div class="bg-white shadow-sm border-b border-gray-200 px-6 py-4">
        <h1 class="text-2xl font-bold text-gray-900">商品管理</h1>
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
            loadProducts
        };
    }
};
</script>

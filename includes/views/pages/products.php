<?php
// 商品管理頁面元件
$products_component_template = <<<'HTML'
<main class="min-h-screen bg-slate-50">
    <!-- 頁面標題 -->
    <div class="bg-white shadow-sm border-b border-slate-200 px-6 py-4">
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
            <p class="text-slate-600">載入中...</p>
        </div>
        
        <!-- 錯誤訊息 -->
        <div v-else-if="error" class="text-center py-8">
            <p class="text-red-600">{{ error }}</p>
            <button @click="loadProducts" class="mt-4 px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-700 font-medium transition shadow-sm">重新載入</button>
        </div>
        
        <!-- 商品列表 -->
        <div v-else>
            <!-- 批次操作工具列 -->
            <div v-if="selectedItems.length > 0" class="mb-4 bg-blue-50 border border-blue-200 rounded-xl p-4 flex items-center justify-between">
                <div class="text-sm text-blue-700 font-medium">
                    已選擇 {{ selectedItems.length }} 個商品
                </div>
                <button 
                    @click="batchDelete"
                    class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 font-medium transition shadow-sm"
                >
                    批次刪除
                </button>
            </div>
        <!-- 桌面版表格 -->
        <div class="hidden md:block bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
            <table class="min-w-full">
                <thead class="bg-slate-50/50 border-b border-slate-100">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider text-slate-500">
                            <input 
                                type="checkbox" 
                                @change="toggleSelectAll"
                                :checked="selectedItems.length === products.length && products.length > 0"
                                class="rounded border-slate-300 text-primary focus:ring-primary"
                            />
                        </th>
                        <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider text-slate-500">商品</th>
                        <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider text-slate-500">價格</th>
                        <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider text-slate-500">狀態</th>
                        <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider text-slate-500">已下單</th>
                        <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider text-slate-500">已採購</th>
                        <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider text-slate-500">預訂</th>
                        <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider text-slate-500">操作</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-slate-100">
                    <tr v-for="product in products" :key="product.id" class="border-b border-slate-100 hover:bg-slate-50/30 transition">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <input 
                                type="checkbox" 
                                :value="product.id"
                                v-model="selectedItems"
                                class="rounded border-slate-300 text-primary focus:ring-primary"
                            />
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 h-12 w-12 flex items-center justify-center bg-slate-100 rounded-lg mr-3 cursor-pointer hover:opacity-80 transition" @click="openImageModal(product)">
                                    <span v-if="!product.image" class="text-2xl">📦</span>
                                    <img v-else :src="product.image" :alt="product.name" class="h-12 w-12 object-cover rounded-lg">
                                </div>
                                <div>
                                    <div class="text-sm font-medium text-slate-900">{{ product.name }}</div>
                                    <div class="text-sm text-slate-500">ID: {{ product.id }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-900">
                            {{ formatPrice(product.price, product.currency) }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <button 
                                @click="toggleStatus(product)"
                                :class="product.status === 'published' ? 'bg-green-100 text-green-800' : 'bg-slate-100 text-slate-800'"
                                class="px-3 py-1 rounded-full text-xs font-medium hover:opacity-80 transition-opacity"
                            >
                                {{ product.status === 'published' ? '已上架' : '已下架' }}
                            </button>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-900">
                            {{ product.ordered }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <input 
                                type="number" 
                                v-model.number="product.purchased"
                                @blur="savePurchased(product)"
                                class="w-20 px-2 py-1 rounded border-0 bg-green-50 text-green-700 focus:ring-2 focus:ring-green-500 focus:outline-none"
                                min="0"
                            />
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-orange-600">
                            {{ calculateReserved(product) }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            <button 
                                @click="openEditModal(product)"
                                class="px-3 py-1.5 bg-primary text-white rounded-lg hover:bg-blue-700 font-medium text-sm transition shadow-sm">
                                編輯
                            </button>
                            <button @click="deleteProduct(product.id)" class="ml-3 px-3 py-1.5 bg-red-600 text-white rounded-lg hover:bg-red-700 font-medium text-sm transition shadow-sm">刪除</button>
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
                class="bg-white rounded-2xl shadow-sm border border-slate-100 p-4 mb-4 transition hover:shadow-md"
            >
                    <div class="flex items-start justify-between mb-3">
                    <div class="flex items-center flex-1">
                        <div class="flex-shrink-0 h-16 w-16 flex items-center justify-center bg-slate-100 rounded-lg mr-3 cursor-pointer hover:opacity-80 transition" @click="openImageModal(product)">
                            <span v-if="!product.image" class="text-3xl">📦</span>
                            <img v-else :src="product.image" :alt="product.name" class="h-16 w-16 object-cover rounded-lg">
                        </div>
                        <div class="flex-1">
                            <div class="flex items-center justify-between mb-1">
                                <h3 class="text-base font-semibold text-slate-900">{{ product.name }}</h3>
                                <button 
                                    @click="toggleStatus(product)"
                                    :class="product.status === 'published' ? 'bg-green-100 text-green-800' : 'bg-slate-100 text-slate-800'"
                                    class="px-2 py-1 rounded-full text-xs font-medium"
                                >
                                    {{ product.status === 'published' ? '已上架' : '已下架' }}
                                </button>
                            </div>
                            <div class="text-sm text-slate-500 mb-2">ID: {{ product.id }}</div>
                            <div class="text-lg font-bold text-slate-900">{{ formatPrice(product.price, product.currency) }}</div>
                        </div>
                    </div>
                </div>
                
                <div class="grid grid-cols-3 gap-3 mb-3">
                    <div class="text-center">
                        <div class="text-xs text-slate-500 mb-1">已下單</div>
                        <div class="text-base font-semibold text-slate-900">{{ product.ordered }}</div>
                    </div>
                    <div class="text-center">
                        <div class="text-xs text-slate-500 mb-1">已採購</div>
                        <input 
                            type="number" 
                            v-model.number="product.purchased"
                            @blur="savePurchased(product)"
                            class="w-full px-2 py-1 rounded border-0 bg-green-50 text-green-700 text-center text-base font-semibold focus:ring-2 focus:ring-green-500 focus:outline-none"
                            min="0"
                        />
                    </div>
                    <div class="text-center">
                        <div class="text-xs text-slate-500 mb-1">預訂</div>
                        <div class="text-base font-semibold text-orange-600">{{ calculateReserved(product) }}</div>
                    </div>
                </div>
                
                <div class="flex gap-2">
                    <button 
                        @click="openEditModal(product)"
                        class="flex-1 px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-700 font-medium text-sm transition shadow-sm">
                        編輯
                    </button>
                    <button @click="deleteProduct(product.id)" class="flex-1 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 font-medium text-sm transition shadow-sm">刪除</button>
                </div>
            </div>
        </div>
        
        <!-- 桌面版分頁 -->
        <footer class="hidden md:flex items-center justify-between px-6 py-4 bg-white border border-slate-200 rounded-2xl shadow-sm mt-6">
            <div class="flex items-center gap-4">
                <span class="text-xs text-slate-500 font-medium">
                    <template v-if="perPage === -1">顯示全部 {{ totalProducts }} 筆</template>
                    <template v-else>顯示 {{ totalProducts }} 筆中的第 {{ (currentPage - 1) * perPage + 1 }} 到 {{ Math.min(currentPage * perPage, totalProducts) }} 筆</template>
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
                    <template v-if="perPage === -1">全部 {{ totalProducts }} 筆</template>
                    <template v-else>第 {{ (currentPage - 1) * perPage + 1 }}-{{ Math.min(currentPage * perPage, totalProducts) }} 筆</template>
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
    
    <!-- 圖片編輯 Modal -->
    <div v-if="showImageModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50" @click.self="closeImageModal">
        <div class="bg-white rounded-2xl shadow-xl max-w-lg w-full mx-4">
            <!-- 標題列 -->
            <div class="p-6 border-b border-slate-200">
                <div class="flex items-center justify-between">
                    <h2 class="text-xl font-bold text-slate-900 font-title">編輯商品圖片</h2>
                    <button @click="closeImageModal" class="text-slate-400 hover:text-slate-600 transition">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            </div>
            
            <!-- 內容區域 -->
            <div class="p-6">
                <!-- 當前圖片預覽 -->
                <div v-if="currentImage" class="mb-4">
                    <img :src="currentImage" class="w-full h-48 object-cover rounded-lg border border-slate-200">
                    <button 
                        @click="removeImage"
                        :disabled="uploading"
                        class="mt-2 w-full px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition"
                        :class="uploading ? 'opacity-50 cursor-not-allowed' : ''"
                    >
                        移除圖片
                    </button>
                </div>
                
                <!-- 上傳區域 -->
                <div 
                    @click="triggerFileInput"
                    @dragover.prevent="isDragging = true"
                    @dragleave.prevent="isDragging = false"
                    @drop.prevent="handleDrop"
                    class="border-2 border-dashed rounded-lg p-8 text-center cursor-pointer transition"
                    :class="isDragging ? 'border-primary bg-blue-50' : 'border-slate-300 hover:border-primary'"
                >
                    <svg class="mx-auto h-12 w-12 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                    </svg>
                    <p class="mt-2 text-sm text-slate-600">
                        <span class="font-medium text-primary">點擊上傳</span> 或拖放圖片到這裡
                    </p>
                    <p class="mt-1 text-xs text-slate-500">支援 JPG、PNG、WebP，最大 5MB</p>
                </div>
                
                <input 
                    ref="fileInput"
                    type="file" 
                    accept="image/jpeg,image/png,image/webp"
                    @change="handleFileSelect"
                    class="hidden"
                >
                
                <!-- 上傳進度 -->
                <div v-if="uploading" class="mt-4">
                    <div class="flex items-center justify-center">
                        <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-primary"></div>
                        <span class="ml-3 text-slate-600">上傳中...</span>
                    </div>
                </div>
                
                <!-- 錯誤訊息 -->
                <div v-if="imageError" class="mt-4 bg-red-50 border border-red-200 rounded-lg p-4">
                    <p class="text-red-800 text-sm">{{ imageError }}</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- 編輯商品 Modal -->
    <div v-if="showEditModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50" @click.self="closeEditModal">
        <div class="bg-white rounded-2xl shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <!-- 標題列 -->
            <div class="p-6 border-b border-slate-200">
                <div class="flex items-center justify-between">
                    <h2 class="text-xl font-bold text-slate-900 font-title">編輯商品</h2>
                    <button @click="closeEditModal" class="text-slate-400 hover:text-slate-600 transition">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            </div>
            
            <!-- Loading 狀態 -->
            <div v-if="editLoading" class="flex items-center justify-center py-12">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
                <span class="ml-3 text-slate-600">載入中...</span>
            </div>
            
            <!-- Error 狀態 -->
            <div v-else-if="editError" class="p-6">
                <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                    <p class="text-red-800">{{ editError }}</p>
                </div>
            </div>
            
            <!-- 編輯表單 -->
            <div v-else-if="editingProduct" class="p-6">
                <form @submit.prevent="saveProduct" class="space-y-4">
                    <!-- 商品名稱 -->
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">商品名稱</label>
                        <input
                            v-model="editingProduct.name"
                            type="text"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary transition"
                            required
                        />
                    </div>
                    
                    <!-- 價格 -->
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">價格（台幣）</label>
                        <input
                            v-model.number="editingProduct.price"
                            type="number"
                            step="0.01"
                            min="0"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary transition"
                            required
                        />
                    </div>
                    
                    <!-- 已採購 -->
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">已採購</label>
                        <input
                            v-model.number="editingProduct.purchased"
                            type="number"
                            min="0"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary transition"
                        />
                    </div>
                    
                    <!-- 狀態 -->
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">狀態</label>
                        <select
                            v-model="editingProduct.status"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary transition"
                        >
                            <option value="published">已上架</option>
                            <option value="private">已下架</option>
                        </select>
                    </div>
                    
                    <!-- 按鈕列 -->
                    <div class="flex justify-end space-x-3 pt-4 border-t border-slate-200">
                        <button
                            type="button"
                            @click="closeEditModal"
                            class="px-4 py-2 bg-white border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50 transition"
                        >
                            取消
                        </button>
                        <button
                            type="submit"
                            :disabled="saving"
                            class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-700 transition shadow-sm"
                            :class="saving ? 'opacity-50 cursor-not-allowed' : ''"
                        >
                            {{ saving ? '儲存中...' : '儲存' }}
                        </button>
                    </div>
                </form>
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
        
        // 分頁狀態
        const currentPage = ref(1);
        const perPage = ref(10);
        const totalProducts = ref(0);
        
        // Modal 狀態
        const showEditModal = ref(false);
        const editingProduct = ref(null);
        const editLoading = ref(false);
        const editError = ref(null);
        const saving = ref(false);
        
        // 圖片 Modal 狀態
        const showImageModal = ref(false);
        const currentProduct = ref(null);
        const currentImage = ref(null);
        const uploading = ref(false);
        const isDragging = ref(false);
        const imageError = ref(null);
        const fileInput = ref(null);
        
        // 總頁數
        const totalPages = Vue.computed(() => {
            if (perPage.value === -1) return 1;
            return Math.ceil(totalProducts.value / perPage.value);
        });
        
        // 可見的頁碼（最多顯示 5 頁）
        const visiblePages = Vue.computed(() => {
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
        
        // 載入商品
        const loadProducts = async () => {
            loading.value = true;
            error.value = null;
            
            try {
                const response = await fetch(
                    `/wp-json/buygo-plus-one/v1/products?page=${currentPage.value}&per_page=${perPage.value}`,
                    {
                        credentials: 'include',
                    }
                );
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const result = await response.json();
                
                if (result.success && result.data) {
                    products.value = result.data;
                    // 假設 API 回傳 total
                    totalProducts.value = result.total || result.data.length;
                } else {
                    throw new Error(result.message || '載入商品失敗');
                }
            } catch (err) {
                console.error('載入商品錯誤:', err);
                error.value = err.message;
                products.value = [];
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
        
        // 上一頁
        const previousPage = () => {
            if (currentPage.value > 1) {
                currentPage.value--;
                loadProducts();
            }
        };
        
        // 下一頁
        const nextPage = () => {
            if (currentPage.value < totalPages.value) {
                currentPage.value++;
                loadProducts();
            }
        };
        
        // 跳到指定頁
        const goToPage = (page) => {
            currentPage.value = page;
            loadProducts();
        };
        
        // 改變每頁數量
        const changePerPage = () => {
            currentPage.value = 1;
            loadProducts();
        };
        
        // 打開編輯 Modal
        const openEditModal = (product) => {
            showEditModal.value = true;
            editingProduct.value = { ...product }; // 複製商品資料
            editError.value = null;
        };
        
        // 關閉編輯 Modal
        const closeEditModal = () => {
            showEditModal.value = false;
            editingProduct.value = null;
            editError.value = null;
        };
        
        // 儲存商品
        const saveProduct = async () => {
            saving.value = true;
            editError.value = null;
            
            try {
                const response = await fetch(
                    `/wp-json/buygo-plus-one/v1/products/${editingProduct.value.id}`,
                    {
                        method: 'PUT',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        credentials: 'include',
                        body: JSON.stringify({
                            name: editingProduct.value.name,
                            price: editingProduct.value.price,
                            purchased: editingProduct.value.purchased,
                            status: editingProduct.value.status
                        }),
                    }
                );
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const result = await response.json();
                
                if (result.success) {
                    // 更新本地資料
                    const index = products.value.findIndex(p => p.id === editingProduct.value.id);
                    if (index !== -1) {
                        products.value[index] = { 
                            ...products.value[index], 
                            name: editingProduct.value.name,
                            price: editingProduct.value.price,
                            purchased: editingProduct.value.purchased,
                            status: editingProduct.value.status
                        };
                    }
                    
                    closeEditModal();
                    console.log('商品更新成功');
                } else {
                    throw new Error(result.message || '儲存失敗');
                }
            } catch (err) {
                console.error('儲存商品錯誤:', err);
                editError.value = err.message || '儲存時發生錯誤';
            } finally {
                saving.value = false;
            }
        };
        
        // 打開圖片 Modal
        const openImageModal = (product) => {
            showImageModal.value = true;
            currentProduct.value = product;
            currentImage.value = product.image;
            imageError.value = null;
        };
        
        // 關閉圖片 Modal
        const closeImageModal = () => {
            showImageModal.value = false;
            currentProduct.value = null;
            currentImage.value = null;
            imageError.value = null;
        };
        
        // 觸發檔案選擇
        const triggerFileInput = () => {
            fileInput.value.click();
        };
        
        // 處理檔案選擇
        const handleFileSelect = (event) => {
            const file = event.target.files[0];
            if (file) {
                uploadImage(file);
            }
        };
        
        // 處理拖放
        const handleDrop = (event) => {
            isDragging.value = false;
            const file = event.dataTransfer.files[0];
            if (file) {
                uploadImage(file);
            }
        };
        
        // 上傳圖片
        const uploadImage = async (file) => {
            // 檢查檔案大小
            if (file.size > 5 * 1024 * 1024) {
                imageError.value = '檔案大小超過 5MB';
                return;
            }
            
            // 檢查檔案類型
            if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
                imageError.value = '不支援的檔案格式';
                return;
            }
            
            uploading.value = true;
            imageError.value = null;
            
            try {
                const formData = new FormData();
                formData.append('image', file);
                
                const response = await fetch(
                    `/wp-json/buygo-plus-one/v1/products/${currentProduct.value.id}/image`,
                    {
                        method: 'POST',
                        credentials: 'include',
                        body: formData
                    }
                );
                
                const result = await response.json();
                
                if (result.success) {
                    // 更新當前圖片
                    currentImage.value = result.data.image_url;
                    
                    // 更新商品列表中的圖片
                    const index = products.value.findIndex(p => p.id === currentProduct.value.id);
                    if (index !== -1) {
                        products.value[index].image = result.data.image_url;
                    }
                    
                    console.log('圖片上傳成功');
                } else {
                    imageError.value = result.message || '上傳失敗';
                }
            } catch (err) {
                console.error('上傳圖片錯誤:', err);
                imageError.value = '上傳時發生錯誤';
            } finally {
                uploading.value = false;
            }
        };
        
        // 移除圖片
        const removeImage = async () => {
            if (!confirm('確定要移除圖片嗎？')) {
                return;
            }
            
            uploading.value = true;
            imageError.value = null;
            
            try {
                const response = await fetch(
                    `/wp-json/buygo-plus-one/v1/products/${currentProduct.value.id}/image`,
                    {
                        method: 'DELETE',
                        credentials: 'include'
                    }
                );
                
                const result = await response.json();
                
                if (result.success) {
                    // 清除當前圖片
                    currentImage.value = null;
                    
                    // 更新商品列表中的圖片
                    const index = products.value.findIndex(p => p.id === currentProduct.value.id);
                    if (index !== -1) {
                        products.value[index].image = null;
                    }
                    
                    console.log('圖片移除成功');
                } else {
                    imageError.value = result.message || '移除失敗';
                }
            } catch (err) {
                console.error('移除圖片錯誤:', err);
                imageError.value = '移除時發生錯誤';
            } finally {
                uploading.value = false;
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
            // 分頁
            currentPage,
            perPage,
            totalProducts,
            totalPages,
            visiblePages,
            previousPage,
            nextPage,
            goToPage,
            changePerPage,
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
            handleCurrencyChange,
            // Modal
            showEditModal,
            editingProduct,
            editLoading,
            editError,
            saving,
            openEditModal,
            closeEditModal,
            saveProduct,
            // 圖片 Modal
            showImageModal,
            currentProduct,
            currentImage,
            uploading,
            isDragging,
            imageError,
            fileInput,
            openImageModal,
            closeImageModal,
            triggerFileInput,
            handleFileSelect,
            handleDrop,
            uploadImage,
            removeImage
        };
    }
};
</script>

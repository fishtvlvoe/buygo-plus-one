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
        <div class="hidden md:block glass-card rounded-2xl overflow-hidden">
            <table class="min-w-full">
                <thead class="bg-slate-50/50 border-b border-slate-100">
                    <tr>
                        <th class="px-6 py-4 text-left text-[10px] font-bold uppercase tracking-wider text-slate-400">
                            <input 
                                type="checkbox" 
                                @change="toggleSelectAll"
                                :checked="selectedItems.length === products.length && products.length > 0"
                                class="rounded border-slate-300 text-primary focus:ring-primary"
                            />
                        </th>
                        <th class="px-6 py-4 text-left text-[10px] font-bold uppercase tracking-wider text-slate-400">商品</th>
                        <th class="px-6 py-4 text-left text-[10px] font-bold uppercase tracking-wider text-slate-400">價格</th>
                        <th class="px-6 py-4 text-left text-[10px] font-bold uppercase tracking-wider text-slate-400">狀態</th>
                        <th class="px-6 py-4 text-left text-[10px] font-bold uppercase tracking-wider text-slate-400">已下單</th>
                        <th class="px-6 py-4 text-left text-[10px] font-bold uppercase tracking-wider text-slate-400">已採購</th>
                        <th class="px-6 py-4 text-left text-[10px] font-bold uppercase tracking-wider text-slate-400">已分配</th>
                        <th class="px-6 py-4 text-left text-[10px] font-bold uppercase tracking-wider text-slate-400">預訂</th>
                        <th class="px-6 py-4 text-left text-[10px] font-bold uppercase tracking-wider text-slate-400">操作</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="product in products" :key="product.id" class="hover:bg-slate-50/30 transition">
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
                            <span 
                                @click="openBuyersModal(product)"
                                class="cursor-pointer hover:text-primary hover:underline transition"
                            >
                                {{ product.ordered }}
                            </span>
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
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-900">
                            {{ product.allocated || 0 }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-orange-600">
                            {{ calculateReserved(product) }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            <button 
                                @click="openAllocationModal(product)"
                                class="px-3 py-1.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium text-sm transition shadow-sm">
                                分配
                            </button>
                            <button 
                                @click="openEditModal(product)"
                                class="ml-2 px-3 py-1.5 bg-primary text-white rounded-lg hover:bg-blue-700 font-medium text-sm transition shadow-sm">
                                編輯
                            </button>
                            <button @click="deleteProduct(product.id)" class="ml-2 px-3 py-1.5 bg-red-600 text-white rounded-lg hover:bg-red-700 font-medium text-sm transition shadow-sm">刪除</button>
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
                class="bg-white rounded-2xl p-4 shadow-sm border border-slate-100 hover:shadow-md transition"
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
                
                <!-- Stats Grid -->
                <div class="grid grid-cols-3 gap-2 mb-4 bg-slate-50/50 p-3 rounded-xl">
                    <div class="text-center">
                        <p class="text-[10px] text-slate-400 uppercase font-bold mb-1">已下單</p>
                        <p class="font-bold text-slate-700">{{ product.ordered }}</p>
                    </div>
                    <div class="text-center">
                        <p class="text-[10px] text-slate-400 uppercase font-bold mb-1">已採購</p>
                        <input 
                            type="number" 
                            v-model.number="product.purchased"
                            @blur="savePurchased(product)"
                            class="w-full px-2 py-1 text-center text-sm font-bold text-green-600 bg-green-50 border border-green-100 rounded-lg outline-none transition"
                            min="0"
                        />
                    </div>
                    <div class="text-center">
                        <p class="text-[10px] text-slate-400 uppercase font-bold mb-1">預訂</p>
                        <p class="font-bold text-orange-600">{{ calculateReserved(product) }}</p>
                    </div>
                </div>
                
                <div class="flex gap-2">
                    <button 
                        @click="openAllocationModal(product)"
                        class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-xl hover:bg-blue-700 font-medium text-sm transition shadow-sm">
                        分配
                    </button>
                    <button 
                        @click="openEditModal(product)"
                        class="flex-1 px-4 py-2 bg-primary text-white rounded-xl hover:bg-blue-700 font-medium text-sm transition shadow-sm">
                        編輯
                    </button>
                    <button @click="deleteProduct(product.id)" class="flex-1 px-4 py-2 bg-red-600 text-white rounded-xl hover:bg-red-700 font-medium text-sm transition shadow-sm">刪除</button>
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
    
    <!-- 下單客戶 Modal -->
    <div v-if="showBuyersModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50" @click.self="closeBuyersModal">
        <div class="bg-white rounded-2xl shadow-xl max-w-3xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <!-- 標題列 -->
            <div class="p-6 border-b border-slate-200">
                <div class="flex items-center justify-between">
                    <h2 class="text-xl font-bold text-slate-900 font-title">下單客戶列表</h2>
                    <button @click="closeBuyersModal" class="text-slate-400 hover:text-slate-600 transition">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            </div>
            
            <!-- Loading 狀態 -->
            <div v-if="buyersLoading" class="flex items-center justify-center py-12">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
                <span class="ml-3 text-slate-600">載入中...</span>
            </div>
            
            <!-- Error 狀態 -->
            <div v-else-if="buyersError" class="p-6">
                <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                    <p class="text-red-800">{{ buyersError }}</p>
                </div>
            </div>
            
            <!-- 客戶列表 -->
            <div v-else-if="buyers.length > 0" class="p-6">
                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead class="bg-slate-50 border-b border-slate-200">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-bold uppercase text-slate-500">客戶名稱</th>
                                <th class="px-4 py-3 text-left text-xs font-bold uppercase text-slate-500">Email</th>
                                <th class="px-4 py-3 text-right text-xs font-bold uppercase text-slate-500">訂單數</th>
                                <th class="px-4 py-3 text-right text-xs font-bold uppercase text-slate-500">總數量</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="buyer in buyers" :key="buyer.customer_id" class="hover:bg-slate-50 transition">
                                <td class="px-4 py-3 text-sm text-slate-900">{{ buyer.customer_name }}</td>
                                <td class="px-4 py-3 text-sm text-slate-600">{{ buyer.customer_email }}</td>
                                <td class="px-4 py-3 text-sm text-slate-900 text-right">{{ buyer.order_count }}</td>
                                <td class="px-4 py-3 text-sm font-medium text-primary text-right">{{ buyer.quantity }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- 無資料 -->
            <div v-else class="p-12 text-center">
                <svg class="mx-auto h-12 w-12 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
                </svg>
                <p class="mt-4 text-slate-600">目前沒有客戶下單</p>
            </div>
        </div>
    </div>
    
    <!-- 分配庫存 Modal -->
    <div v-if="showAllocationModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50" @click.self="closeAllocationModal">
        <div class="bg-white rounded-2xl shadow-xl max-w-4xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <!-- 標題列 -->
            <div class="p-6 border-b border-slate-200">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-xl font-bold text-slate-900 font-title">庫存分配 - {{ selectedProduct?.name }}</h2>
                        <p class="text-sm text-slate-600 mt-1">
                            剩餘可分配：<strong class="text-blue-600">{{ (selectedProduct?.purchased || 0) - (selectedProduct?.allocated || 0) }}</strong> 個
                        </p>
                    </div>
                    <button @click="closeAllocationModal" class="text-slate-400 hover:text-slate-600 transition">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            </div>
            
            <!-- Loading 狀態 -->
            <div v-if="allocationLoading" class="flex items-center justify-center py-12">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
                <span class="ml-3 text-slate-600">載入中...</span>
            </div>
            
            <!-- Error 狀態 -->
            <div v-else-if="allocationError" class="p-6">
                <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                    <p class="text-red-800">{{ allocationError }}</p>
                </div>
            </div>
            
            <!-- 訂單列表 -->
            <div v-else-if="productOrders.length > 0" class="p-6">
                <!-- 商品資訊區塊 -->
                <div class="mb-6 p-4 bg-slate-50 rounded-lg border border-slate-200">
                    <div class="flex items-center gap-4">
                        <div class="flex-shrink-0">
                            <img 
                                v-if="selectedProduct?.image" 
                                :src="selectedProduct.image" 
                                :alt="selectedProduct.name"
                                class="w-20 h-20 object-cover rounded-lg"
                            />
                            <div v-else class="w-20 h-20 bg-slate-200 rounded-lg flex items-center justify-center">
                                <span class="text-2xl">📦</span>
                            </div>
                        </div>
                        <div class="flex-1">
                            <h3 class="text-lg font-semibold text-slate-900">{{ selectedProduct?.name }}</h3>
                            <div class="mt-1 text-sm text-slate-500">
                                總數量：<span class="font-medium text-slate-700">{{ selectedProduct?.purchased || 0 }}</span>
                            </div>
                        </div>
                        <div class="grid grid-cols-3 gap-3">
                            <div class="bg-white border border-pink-200 rounded-lg p-3 text-center">
                                <div class="text-xs text-pink-600 mb-1">已出貨數量(所有出貨)</div>
                                <div class="text-lg font-bold text-green-600">{{ totalShipped }}</div>
                            </div>
                            <div class="bg-white border border-pink-200 rounded-lg p-3 text-center">
                                <div class="text-xs text-pink-600 mb-1">本次可出貨數量</div>
                                <div class="text-lg font-bold text-green-600">{{ totalAllocated }}</div>
                            </div>
                            <div class="bg-white border border-pink-200 rounded-lg p-3 text-center">
                                <div class="text-xs text-pink-600 mb-1">未出貨數量</div>
                                <div class="text-lg font-bold text-green-600">{{ totalPending }}</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 border-b border-slate-200">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-bold uppercase text-slate-500">訂單編號</th>
                                <th class="px-4 py-3 text-left text-xs font-bold uppercase text-slate-500">客戶</th>
                                <th class="px-4 py-3 text-right text-xs font-bold uppercase text-slate-500">需求數量</th>
                                <th class="px-4 py-3 text-right text-xs font-bold uppercase text-slate-500">已分配</th>
                                <th class="px-4 py-3 text-right text-xs font-bold uppercase text-slate-500">已出貨</th>
                                <th class="px-4 py-3 text-right text-xs font-bold uppercase text-slate-500">未出貨</th>
                                <th class="px-4 py-3 text-left text-xs font-bold uppercase text-slate-500">狀態</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="order in productOrders" :key="order.order_id" class="hover:bg-slate-50 transition">
                                <td class="px-4 py-3 text-slate-900 font-medium">#{{ order.order_id }}</td>
                                <td class="px-4 py-3 text-slate-900">{{ order.customer }}</td>
                                <td class="px-4 py-3 text-slate-900 text-right">{{ order.required }}</td>
                                <td class="px-4 py-3 text-right">
                                    <input 
                                        type="number" 
                                        v-model.number="order.allocated"
                                        @input="updateOrderStatus(order)"
                                        :min="0"
                                        :max="order.required"
                                        class="w-20 px-2 py-1 text-right border border-blue-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-blue-600 font-medium"
                                    />
                                </td>
                                <td class="px-4 py-3 text-slate-600 text-right">{{ order.shipped || 0 }}</td>
                                <td class="px-4 py-3 text-slate-600 text-right">{{ order.pending || 0 }}</td>
                                <td class="px-4 py-3">
                                    <span 
                                        :class="order.status === '已分配' ? 'bg-green-100 text-green-800' : order.status === '部分分配' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800'"
                                        class="px-2 py-1 text-xs font-medium rounded-full"
                                    >
                                        {{ order.status }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <!-- 操作按鈕 -->
                <div class="mt-6 flex justify-end gap-3 pt-4 border-t border-slate-200">
                    <button 
                        @click="closeAllocationModal"
                        class="px-4 py-2 bg-white border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50 transition"
                    >
                        取消
                    </button>
                    <button 
                        @click="confirmAllocation"
                        :disabled="updatingAllocation || hasUnsavedChanges === false"
                        class="px-6 py-2 bg-orange-500 text-white rounded-lg text-sm font-bold shadow-[0_2px_10px_-3px_rgba(249,115,22,0.5)] hover:bg-orange-600 hover:scale-105 transition active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:scale-100"
                    >
                        {{ updatingAllocation ? '保存中...' : '確認分配' }}
                    </button>
                </div>
            </div>
            
            <!-- 無資料 -->
            <div v-else class="p-12 text-center">
                <svg class="mx-auto h-12 w-12 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <p class="mt-4 text-slate-600">目前沒有訂單</p>
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
        
        // 搜尋篩選狀態
        const searchFilter = ref(null);
        const searchFilterName = ref('');
        
        // 幣別狀態
        const currentCurrency = ref('JPY'); // 預設日幣
        const baseCurrency = 'JPY'; // 基準幣別（商品原始價格的幣別）
        const exchangeRates = ref({}); // 儲存所有匯率 { TWD: 0.22, USD: 0.0067, KRW: 9.2, THB: 0.24 }
        const rateLoading = ref(false);
        const rateLastUpdated = ref(null); // 最後更新時間
        
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
        
        // 下單客戶 Modal 狀態
        const showBuyersModal = ref(false);
        const buyers = ref([]);
        const buyersLoading = ref(false);
        const buyersError = ref(null);
        
        // 分配庫存 Modal 狀態
        const showAllocationModal = ref(false);
        const selectedProduct = ref(null);
        const productOrders = ref([]);
        const originalAllocations = ref({}); // 儲存原始分配數量，用於檢測變更
        const allocationLoading = ref(false);
        const allocationError = ref(null);
        const updatingAllocation = ref(false);
        
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
                // 建立 API 參數
                let url = `/wp-json/buygo-plus-one/v1/products?page=${currentPage.value}&per_page=${perPage.value}`;
                
                // 如果有搜尋篩選，加入 ID 參數
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
        
        // 取得匯率
        const fetchExchangeRate = async () => {
            rateLoading.value = true;
            try {
                // 檢查 LocalStorage 快取（24 小時內有效）
                const cachedRates = localStorage.getItem('buygo_exchange_rates');
                const cachedTime = localStorage.getItem('buygo_rates_updated');
                
                if (cachedRates && cachedTime) {
                    const cacheAge = Date.now() - new Date(cachedTime).getTime();
                    const hours24 = 24 * 60 * 60 * 1000;
                    
                    // 如果快取在 24 小時內，直接使用
                    if (cacheAge < hours24) {
                        exchangeRates.value = JSON.parse(cachedRates);
                        rateLastUpdated.value = cachedTime;
                        console.log('使用快取匯率:', exchangeRates.value);
                        rateLoading.value = false;
                        return;
                    }
                }
                
                // 使用免費 API 取得 JPY 對所有幣別的匯率
                const response = await fetch('https://api.exchangerate-api.com/v4/latest/JPY');
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const data = await response.json();
                
                if (data && data.rates) {
                    // 儲存所有支援的幣別匯率（相對於 JPY）
                    exchangeRates.value = {
                        JPY: 1, // 基準幣別
                        TWD: data.rates.TWD || 0.22,
                        USD: data.rates.USD || 0.0067,
                        KRW: data.rates.KRW || 9.2,
                        THB: data.rates.THB || 0.24
                    };
                    
                    rateLastUpdated.value = new Date().toISOString();
                    
                    console.log('匯率已更新:', exchangeRates.value);
                    console.log('更新時間:', rateLastUpdated.value);
                    
                    // 儲存到 LocalStorage（避免每次都呼叫 API）
                    localStorage.setItem('buygo_exchange_rates', JSON.stringify(exchangeRates.value));
                    localStorage.setItem('buygo_rates_updated', rateLastUpdated.value);
                }
            } catch (err) {
                console.error('匯率讀取失敗，嘗試從 LocalStorage 讀取:', err);
                
                // 失敗時從 LocalStorage 讀取
                const cachedRates = localStorage.getItem('buygo_exchange_rates');
                const cachedTime = localStorage.getItem('buygo_rates_updated');
                
                if (cachedRates) {
                    exchangeRates.value = JSON.parse(cachedRates);
                    rateLastUpdated.value = cachedTime;
                    console.log('使用快取匯率:', exchangeRates.value);
                } else {
                    // 使用預設值
                    exchangeRates.value = {
                        JPY: 1,
                        TWD: 0.22,
                        USD: 0.0067,
                        KRW: 9.2,
                        THB: 0.24
                    };
                    console.log('使用預設匯率');
                }
            } finally {
                rateLoading.value = false;
            }
        };
        
        // 金額轉換函數（支援任意幣別轉換）
        const convertPrice = (price, fromCurrency, toCurrency) => {
            if (fromCurrency === toCurrency) return price;
            
            // 如果匯率還沒載入，回傳原價
            if (Object.keys(exchangeRates.value).length === 0) {
                return price;
            }
            
            // 先轉換為基準幣別 (JPY)
            let priceInBase = price;
            if (fromCurrency !== baseCurrency) {
                const fromRate = exchangeRates.value[fromCurrency];
                if (!fromRate) return price; // 找不到匯率，回傳原價
                priceInBase = price / fromRate;
            }
            
            // 再從基準幣別轉換為目標幣別
            let convertedPrice = priceInBase;
            if (toCurrency !== baseCurrency) {
                const toRate = exchangeRates.value[toCurrency];
                if (!toRate) return price; // 找不到匯率，回傳原價
                convertedPrice = priceInBase * toRate;
            }
            
            return Math.round(convertedPrice);
        };
        
        const formatPrice = (price, originalCurrency) => {
            // 如果當前幣別與原始幣別不同，進行轉換
            let displayPrice = price;
            if (currentCurrency.value !== originalCurrency) {
                displayPrice = convertPrice(price, originalCurrency, currentCurrency.value);
            }
            return `${displayPrice.toLocaleString()} ${currentCurrency.value}`;
        };

        const calculateReserved = (product) => {
            const ordered = product.ordered || 0;
            const purchased = product.purchased || 0;
            const allocated = product.allocated || 0;
            return Math.max(0, ordered - purchased - allocated);
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
        const handleSearchSelect = async (item) => {
            console.log('選擇商品:', item);
            
            // 設定搜尋篩選狀態
            searchFilter.value = item.id;
            searchFilterName.value = item.name;
            
            // 重置到第一頁
            currentPage.value = 1;
            
            // 重新載入商品（只載入選中的商品）
            await loadProducts();
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
            searchFilter.value = null;
            searchFilterName.value = '';
            currentPage.value = 1;
            loadProducts();
        };

        // 處理幣別切換
        const handleCurrencyChange = async (currency) => {
            console.log('切換幣別:', currency);
            
            // 如果匯率還沒載入，先嘗試獲取
            if (Object.keys(exchangeRates.value).length === 0) {
                await fetchExchangeRate();
            }
            
            // 更新當前幣別
            currentCurrency.value = currency;
            
            // 不需要修改 products 陣列，formatPrice 會自動轉換顯示
            console.log('當前匯率表:', exchangeRates.value);
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
        
        // 打開下單客戶 Modal
        const openBuyersModal = async (product) => {
            showBuyersModal.value = true;
            buyersLoading.value = true;
            buyersError.value = null;
            buyers.value = [];
            
            try {
                const response = await fetch(
                    `/wp-json/buygo-plus-one/v1/products/${product.id}/buyers`,
                    {
                        credentials: 'include'
                    }
                );
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const result = await response.json();
                
                if (result.success) {
                    buyers.value = result.data;
                } else {
                    throw new Error(result.message || '載入失敗');
                }
            } catch (err) {
                console.error('載入下單客戶錯誤:', err);
                buyersError.value = err.message || '載入時發生錯誤';
            } finally {
                buyersLoading.value = false;
            }
        };
        
        // 關閉下單客戶 Modal
        const closeBuyersModal = () => {
            showBuyersModal.value = false;
            buyers.value = [];
            buyersError.value = null;
        };
        
        // 計算總數量（computed）
        const totalShipped = Vue.computed(() => {
            return productOrders.value.reduce((sum, order) => sum + (order.shipped || 0), 0);
        });
        
        const totalAllocated = Vue.computed(() => {
            return productOrders.value.reduce((sum, order) => sum + (order.allocated || 0), 0);
        });
        
        const totalPending = Vue.computed(() => {
            return productOrders.value.reduce((sum, order) => sum + (order.pending || 0), 0);
        });
        
        // 打開分配庫存 Modal
        const openAllocationModal = async (product) => {
            selectedProduct.value = product;
            showAllocationModal.value = true;
            allocationLoading.value = true;
            allocationError.value = null;
            productOrders.value = [];
            
            try {
                const response = await fetch(
                    `/wp-json/buygo-plus-one/v1/products/${product.id}/orders`,
                    {
                        credentials: 'include'
                    }
                );
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const result = await response.json();
                
                if (result.success) {
                    productOrders.value = result.data;
                    // 儲存原始分配數量，用於檢測變更
                    originalAllocations.value = {};
                    productOrders.value.forEach(order => {
                        originalAllocations.value[order.order_id] = order.allocated || 0;
                    });
                } else {
                    throw new Error(result.message || '載入失敗');
                }
            } catch (err) {
                console.error('載入訂單列表錯誤:', err);
                allocationError.value = err.message || '載入時發生錯誤';
            } finally {
                allocationLoading.value = false;
            }
        };
        
        // 關閉分配庫存 Modal
        const closeAllocationModal = () => {
            showAllocationModal.value = false;
            selectedProduct.value = null;
            productOrders.value = [];
            originalAllocations.value = {};
            allocationError.value = null;
        };
        
        // 檢測是否有未保存的變更
        const hasUnsavedChanges = Vue.computed(() => {
            if (productOrders.value.length === 0) return false;
            return productOrders.value.some(order => {
                const current = order.allocated || 0;
                const original = originalAllocations.value[order.order_id] || 0;
                return current !== original;
            });
        });
        
        // 更新訂單狀態（僅本地顯示，不保存）
        const updateOrderStatus = (order) => {
            // 驗證輸入值
            const newAllocated = Math.max(0, Math.min(Math.floor(order.allocated || 0), order.required));
            order.allocated = newAllocated;
            order.pending = order.required - newAllocated;
            order.status = newAllocated >= order.required ? '已分配' : (newAllocated > 0 ? '部分分配' : '未分配');
        };
        
        // 確認並保存所有分配
        const confirmAllocation = async () => {
            if (updatingAllocation.value || !hasUnsavedChanges.value) return;
            
            updatingAllocation.value = true;
            allocationError.value = null;
            
            try {
                // 準備所有變更的分配數量
                const allocations = {};
                productOrders.value.forEach(order => {
                    allocations[order.order_id] = order.allocated || 0;
                });
                
                const response = await fetch('/wp-json/buygo-plus-one/v1/products/allocate', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    credentials: 'include',
                    body: JSON.stringify({
                        product_id: selectedProduct.value.id,
                        order_ids: Object.keys(allocations).map(id => parseInt(id)),
                        allocations: allocations
                    })
                });
                
                if (!response.ok) {
                    const errorText = await response.text();
                    let errorData;
                    try {
                        errorData = JSON.parse(errorText);
                    } catch (e) {
                        errorData = { message: errorText || `HTTP ${response.status} 錯誤` };
                    }
                    throw new Error(errorData.message || `HTTP ${response.status} 錯誤`);
                }
                
                const result = await response.json();
                
                if (result.success) {
                    // 更新原始分配數量
                    productOrders.value.forEach(order => {
                        originalAllocations.value[order.order_id] = order.allocated || 0;
                    });
                    
                    // 重新載入商品列表以更新總分配數量
                    await loadProducts();
                    
                    // 關閉 Modal
                    closeAllocationModal();
                    
                    // 顯示成功訊息
                    alert('分配成功！配額已更新至各訂單。');
                } else {
                    allocationError.value = result.message || '分配失敗';
                    console.error('分配失敗:', result);
                }
            } catch (err) {
                console.error('分配失敗:', err);
                allocationError.value = err.message || '分配時發生錯誤';
            } finally {
                updatingAllocation.value = false;
            }
        };
        
        onMounted(async () => {
            await fetchExchangeRate();
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
            searchFilter,
            searchFilterName,
            currentCurrency,
            exchangeRates,
            rateLoading,
            rateLastUpdated,
            fetchExchangeRate,
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
            removeImage,
            // 下單客戶 Modal
            showBuyersModal,
            buyers,
            buyersLoading,
            buyersError,
            openBuyersModal,
            closeBuyersModal,
            // 分配庫存 Modal
            showAllocationModal,
            selectedProduct,
            productOrders,
            allocationLoading,
            allocationError,
            updatingAllocation,
            hasUnsavedChanges,
            totalShipped,
            totalAllocated,
            totalPending,
            openAllocationModal,
            closeAllocationModal,
            updateOrderStatus,
            confirmAllocation
        };
    }
};
</script>

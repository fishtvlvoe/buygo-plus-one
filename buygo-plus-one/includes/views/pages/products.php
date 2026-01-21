<?php
// 商品管理頁面元件
?>
<!-- Tailwind Config -->
<script>
    tailwind.config = {
        theme: {
            extend: {
                fontFamily: {
                    sans: ['Inter', 'sans-serif'],
                    mono: ['Fira Code', 'monospace'],
                },
                colors: {
                    primary: '#2563EB', // Blue-600
                    secondary: '#3B82F6', // Blue-500
                    cta: '#F97316', // Orange-500
                    surface: '#FFFFFF',
                    background: '#F8FAFC', // Slate-50
                },
                screens: {
                    'xs': '375px', // Mobile Small
                }
            }
        }
    }
</script>
<style>
    /* Custom Scrollbar */
    ::-webkit-scrollbar { width: 8px; height: 8px; }
    ::-webkit-scrollbar-track { background: #f1f5f9; }
    ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
    ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
    
    /* Inline Edit Input */
    .inline-edit-input {
        width: 80px; text-align: center; border: 1px solid #e2e8f0; border-radius: 0.375rem;
        padding: 0.25rem 0.5rem; font-family: 'Fira Code', monospace; outline: none; transition: all 0.2s;
    }
    .inline-edit-input:focus { border-color: #2563EB; box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.1); }
    
    /* Transitions */
    .slide-enter-active, .slide-leave-active { transition: transform 0.3s ease-in-out; }
    .slide-enter-from, .slide-leave-to { transform: translateX(100%); }
    .search-slide-enter-active, .search-slide-leave-active { transition: all 0.2s ease; }
    .search-slide-enter-from, .search-slide-leave-to { opacity: 0; transform: translateY(-10px); }
    
    [v-cloak] { display: none; }
</style>

<?php
$products_component_template = <<<'HTML'

<!-- Root Template Content (由 template.php 統一掛載，側邊欄已由共用組件處理) -->
<div class="min-h-screen bg-slate-50 text-slate-900 font-sans antialiased">

    <!-- Main Content -->
    <main class="flex flex-col min-w-0 relative bg-slate-50 min-h-screen">

        <!-- Header -->
        <header class="h-16 bg-white border-b border-slate-200 flex items-center justify-between px-4 md:px-6 shrink-0 z-10 sticky top-0 md:static relative">
            <div class="flex items-center gap-3 md:gap-4 overflow-hidden flex-1">
                <div class="flex flex-col overflow-hidden min-w-0 pl-12 md:pl-0" v-show="!showMobileSearch">
                    <h1 class="text-xl font-bold text-slate-900 leading-tight truncate">商品</h1>
                    <nav class="hidden md:flex text-[10px] md:text-xs text-slate-500 gap-1 items-center truncate">
                        首頁 <span class="text-slate-300">/</span> 商品列表
                        <span v-if="currentView !== 'list'" class="text-slate-300">/</span>
                        <span v-if="currentView === 'edit'" class="text-primary font-medium truncate">編輯 #{{ currentId }}</span>
                        <span v-if="currentView === 'allocation'" class="text-primary font-medium truncate">分配 #{{ currentId }}</span>
                        <span v-if="currentView === 'buyers'" class="text-primary font-medium truncate">下單名單 #{{ currentId }}</span>
                    </nav>
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
                    <button @click="batchDelete" class="px-3 py-1.5 bg-red-50 text-red-600 rounded-lg text-xs font-medium hover:bg-red-100 border border-red-200 transition">批次刪除</button>
                </div>

                <!-- Desktop Search -->
                <div class="relative hidden sm:block w-32 md:w-48 lg:w-64 transition-all duration-300">
                    <input type="text" placeholder="全域搜尋..." v-model="globalSearchQuery" @input="handleSearchInput"
                        class="pl-9 pr-4 py-2 bg-slate-100 border-none rounded-lg text-sm focus:ring-2 focus:ring-primary w-full transition-all">
                    <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </div>

                <!-- Notification -->
                <button class="p-2 text-slate-400 hover:text-slate-600 rounded-full hover:bg-slate-100 relative">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                </button>
                
                <!-- Currency Toggle -->
                <button @click="toggleCurrency" class="ml-2 px-3 py-1.5 bg-white border border-slate-200 rounded-md text-xs font-bold hover:border-primary hover:text-primary transition shadow-sm" :class="currentCurrency === 'TWD' ? 'text-green-600 border-green-200' : 'text-slate-600'">
                    <span v-if="currentCurrency === 'TWD'">NT$</span>
                    <span v-else>{{ currentCurrency }}</span>
                </button>
            </div>

            <!-- Mobile Search Overlay -->
            <transition name="search-slide">
                <div v-if="showMobileSearch" class="absolute inset-0 z-20 bg-white flex items-center px-4 gap-2 md:hidden">
                    <div class="relative flex-1">
                        <input type="text" placeholder="全域搜尋..." v-model="globalSearchQuery" @input="handleSearchInput"
                            class="w-full pl-9 pr-4 py-2 bg-slate-100 border-none rounded-lg text-sm focus:ring-2 focus:ring-primary auto-focus">
                        <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </div>
                    <button @click="showMobileSearch = false" class="text-sm font-medium text-slate-500 p-2">取消</button>
                </div>
            </transition>
        </header>

        <div class="flex-1 overflow-auto bg-slate-50/50 relative">
            <div v-show="currentView === 'list'" class="p-2 xs:p-4 md:p-6 w-full max-w-7xl mx-auto space-y-4 md:space-y-6">
                
                <!-- Smart Search Box -->
                <smart-search-box
                    api-endpoint="/wp-json/buygo-plus-one/v1/products"
                    :search-fields="['name', 'sku']"
                    @select="handleProductSelect"
                    @search="handleProductSearch"
                    @clear="handleProductSearchClear"
                ></smart-search-box>

                <!-- Loading -->
                <div v-if="loading" class="text-center py-12"><div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary mx-auto"></div><p class="mt-2 text-slate-500">載入中...</p></div>
                
                <!-- Content (Desktop & Mobile) -->
                <div v-else>
                    <!-- Desktop Table -->
                    <div class="hidden md:block bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-slate-200">
                                <thead class="bg-slate-50/50">
                                    <tr>
                                        <th class="px-4 py-4 w-12 text-center"><input type="checkbox" @change="toggleSelectAll" :checked="isAllSelected" class="rounded border-slate-300 text-primary w-4 h-4 cursor-pointer"></th>
                                        <th class="px-4 py-4 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider w-[35%]">商品</th>
                                        <th class="px-2 py-4 text-right text-xs font-semibold text-slate-500 uppercase whitespace-nowrap hidden lg:table-cell">價格</th>
                                        <th class="px-2 py-4 text-center text-xs font-semibold text-slate-500 uppercase whitespace-nowrap">狀態</th>
                                        <th class="px-2 py-4 text-center text-xs font-semibold text-slate-500 uppercase whitespace-nowrap">下單</th>
                                        <th class="px-2 py-4 text-center text-xs font-semibold text-slate-500 uppercase whitespace-nowrap">採購</th>
                                        <th class="px-2 py-4 text-center text-xs font-semibold text-slate-500 uppercase whitespace-nowrap hidden xl:table-cell">已出貨</th>
                                        <th class="px-2 py-4 text-center text-xs font-semibold text-slate-500 uppercase whitespace-nowrap text-blue-600">待出貨</th>
                                        <th class="px-2 py-4 text-center text-xs font-semibold text-slate-500 uppercase whitespace-nowrap text-slate-400">預訂</th>
                                        <th class="px-2 py-4 text-center text-xs font-semibold text-slate-500 uppercase whitespace-nowrap">操作</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-slate-100">
                                    <tr v-for="product in products" :key="product.id" class="hover:bg-slate-50 transition">
                                        <td class="px-4 py-4 text-center"><input type="checkbox" :value="product.id" v-model="selectedItems" class="rounded border-slate-300 text-primary w-4 h-4 cursor-pointer"></td>
                                        <td class="px-4 py-4">
                                            <div class="flex items-center gap-4">
                                                <div class="h-16 w-16 bg-slate-100 rounded-lg flex items-center justify-center text-slate-400 shrink-0 border border-slate-200 cursor-pointer group hover:border-primary transition" @click="openImageModal(product)">
                                                    <img v-if="product.image" :src="product.image" class="w-full h-full object-cover rounded-lg">
                                                    <svg v-else class="w-8 h-8 group-hover:text-primary transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                                </div>
                                                <div class="min-w-0 cursor-pointer" @click="navigateTo('edit', product)">
                                                    <div class="text-sm font-bold text-slate-900 hover:text-primary hover:underline transition-colors line-clamp-2 leading-snug">{{ product.name }}</div>
                                                    <div class="text-[10px] text-slate-400 font-mono mt-1">ID: {{ product.id }} <span class="lg:hidden ml-2 font-bold text-slate-600">{{ formatPriceDisplay(product.price, product.currency) }}</span></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-2 py-4 text-right font-mono text-sm font-medium hidden lg:table-cell">
                                            <div class="flex flex-col items-end">
                                                <span>{{ formatPriceDisplay(product.price, product.currency) }}</span>
                                                <span v-if="systemCurrency !== 'TWD' && currentCurrency !== 'TWD'" class="text-xs text-slate-400 font-normal">
                                                    ≈ NT${{ getTWDPrice(product.price, product.currency || systemCurrency).toLocaleString() }}
                                                </span>
                                            </div>
                                        </td>
                                        <td class="px-2 py-4 text-center">
                                             <button @click="toggleStatus(product)" :class="product.status === 'published' ? 'bg-green-100 text-green-800 border-green-200' : 'bg-slate-100 text-slate-800 border-slate-200'" class="px-2.5 py-1 text-xs font-semibold rounded-full border hover:opacity-80 transition cursor-pointer">{{ product.status === 'published' ? '已上架' : '已下架' }}</button>
                                        </td>
                                        <td class="px-2 py-4 text-center">
                                            <button @click="navigateTo('buyers', product)" class="text-base font-bold text-green-600 hover:text-green-700 hover:underline decoration-green-300 underline-offset-2 transition">{{ product.ordered || 0 }}</button>
                                        </td>
                                        <td class="px-2 py-4 text-center">
                                            <input type="number" v-model.number="product.purchased" @blur="savePurchased(product)" class="inline-edit-input text-gray-700 bg-slate-50 focus:bg-white" @click.stop>
                                        </td>
                                        <td class="px-2 py-4 text-center font-bold text-blue-600 font-mono text-sm hidden xl:table-cell">{{ product.shipped || 0 }}</td>
                                        <td class="px-2 py-4 text-center font-bold text-orange-600 font-mono text-sm">{{ (product.allocated || 0) - (product.shipped || 0) }}</td>
                                        <td class="px-2 py-4 text-center font-bold text-slate-400 font-mono text-sm">{{ calculateReserved(product) }}</td>
                                        <td class="px-2 py-4 text-center">
                                            <div class="flex items-center justify-center gap-1">
                                                <button @click="navigateTo('allocation', product)" class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition" title="分配"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg></button>
                                                <button @click="navigateTo('edit', product)" class="p-2 text-slate-500 hover:bg-slate-50 rounded-lg transition" title="編輯"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg></button>
                                                <button @click="deleteProduct(product.id)" class="p-2 text-red-500 hover:bg-red-50 rounded-lg transition" title="刪除"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg></button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Mobile Card View (Updated) -->
                    <div class="md:hidden space-y-3">
                     <div class="flex items-center gap-3 px-1 mb-2">
                        <label class="flex items-center gap-2 text-sm text-slate-600 font-medium">
                            <input type="checkbox" @change="toggleSelectAll" :checked="isAllSelected" class="rounded border-slate-300 text-primary w-4 h-4 cursor-pointer">
                            全選
                        </label>
                    </div>
                    <div v-for="product in products" :key="product.id" class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                        <div class="p-3 flex gap-3 relative">
                            <div class="absolute top-3 left-3 z-10 w-6 h-6 flex items-center justify-center">
                                <input type="checkbox" :value="product.id" v-model="selectedItems" class="rounded border-slate-300 text-primary w-4 h-4 bg-white shadow-sm">
                            </div>
                            <div class="w-24 h-24 bg-slate-100 rounded-lg flex items-center justify-center text-slate-400 shrink-0 border border-slate-200 ml-6 cursor-pointer hover:border-primary transition relative group" @click="openImageModal(product)">
                                <img v-if="product.image" :src="product.image" class="w-full h-full object-cover rounded-lg">
                                <svg v-else class="w-8 h-8 group-hover:text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                            </div>
                            <div class="flex-1 min-w-0 flex flex-col justify-between py-1">
                                <div>
                                    <div class="flex justify-between items-start gap-2">
                                        <h3 class="text-sm font-bold text-slate-900 leading-tight cursor-pointer hover:text-primary transition-colors" @click="navigateTo('edit', product)">{{ product.name }}</h3>
                                        <button @click="toggleStatus(product)" :class="product.status === 'published' ? 'bg-green-100 text-green-800 border-green-200' : 'bg-slate-100 text-slate-800 border-slate-200'" class="px-2 py-0.5 text-[10px] font-medium rounded-full border shrink-0 whitespace-nowrap">{{ product.status === 'published' ? '上架' : '下架' }}</button>
                                    </div>
                                    <div class="mt-1">
                                        <span class="text-xs font-bold text-slate-500">{{ formatPriceDisplay(product.price, product.currency) }}</span>
                                        <span v-if="systemCurrency !== 'TWD' && currentCurrency !== 'TWD'" class="text-[10px] text-slate-400 ml-1">
                                            ≈ NT${{ getTWDPrice(product.price, product.currency || systemCurrency).toLocaleString() }}
                                        </span>
                                    </div>
                                </div>
                                <div class="flex items-center justify-end gap-2 mt-2">
                                    <span class="text-[10px] text-slate-400">採購</span>
                                    <input type="number" v-model.number="product.purchased" @blur="savePurchased(product)" class="w-20 px-2 py-1 text-right text-sm font-bold border rounded bg-slate-50 focus:bg-white focus:border-primary focus:outline-none">
                                </div>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 border-t border-slate-100 bg-slate-50/50 divide-x divide-slate-100">
                            <div class="px-2 py-3 text-center clickable active:bg-green-100" @click="navigateTo('buyers', product)">
                                <div class="text-[10px] text-slate-400 mb-0.5">下單</div>
                                <div class="font-bold text-green-600 text-base underline decoration-green-200">{{ product.ordered || 0 }}</div>
                            </div>
                            <div class="px-2 py-3 text-center">
                                <div class="text-[10px] text-slate-400 mb-0.5">預訂</div>
                                <div class="font-bold text-slate-400 text-base">{{ calculateReserved(product) }}</div>
                            </div>
                        </div>
                        <div class="grid grid-cols-3 border-t border-slate-100 bg-slate-50/50 divide-x divide-slate-100">
                            <div class="px-2 py-3 text-center">
                                <div class="text-[10px] text-slate-400 mb-0.5">已分配</div>
                                <div class="font-bold text-blue-600 text-base">{{ product.allocated || 0 }}</div>
                            </div>
                            <div class="px-2 py-3 text-center">
                                <div class="text-[10px] text-slate-400 mb-0.5">已出貨</div>
                                <div class="font-bold text-slate-500 text-base">{{ product.shipped || 0 }}</div>
                            </div>
                            <div class="px-2 py-3 text-center">
                                <div class="text-[10px] text-orange-500 mb-0.5">待出貨</div>
                                <div class="font-bold text-orange-600 text-base">{{ (product.allocated || 0) - (product.shipped || 0) }}</div>
                            </div>
                        </div>
                        <div class="grid grid-cols-3 border-t border-slate-200 divide-x divide-slate-200">
                            <button @click="navigateTo('allocation', product)" class="py-3 flex items-center justify-center gap-1.5 text-blue-600 hover:bg-blue-50 bg-white transition active:bg-blue-100"><span class="text-xs font-bold">分配</span></button>
                            <button @click="navigateTo('edit', product)" class="py-3 flex items-center justify-center gap-1.5 text-slate-600 hover:bg-slate-50 bg-white transition active:bg-slate-100"><span class="text-xs font-bold">編輯</span></button>
                            <button @click="deleteProduct(product.id)" class="py-3 flex items-center justify-center gap-1.5 text-red-500 hover:bg-red-50 bg-white transition active:bg-red-100"><span class="text-xs font-bold">刪除</span></button>
                        </div>
                    </div>
                </div>
                </div>
            </div> <!-- End List View Container -->

            <!-- Pagination -->
            <div v-if="currentView === 'list' && totalProducts > 0" class="mt-6 flex flex-col sm:flex-row items-center justify-between bg-white px-4 py-3 border border-slate-200 rounded-xl shadow-sm gap-3">
                <div class="text-sm text-slate-700 text-center sm:text-left">
                    顯示 <span class="font-medium">{{ (currentPage - 1) * perPage + 1 }}</span> 到 <span class="font-medium">{{ Math.min(currentPage * perPage, totalProducts) }}</span> 筆，共 <span class="font-medium">{{ totalProducts }}</span> 筆
                </div>
                <div class="flex items-center gap-3">
                    <!-- Per Page Selector -->
                    <select v-model.number="perPage" @change="currentPage = 1; loadProducts()" class="px-3 py-1.5 border border-slate-300 rounded-lg text-sm focus:border-primary focus:ring-1 focus:ring-primary outline-none">
                        <option :value="5">5 筆</option>
                        <option :value="10">10 筆</option>
                        <option :value="20">20 筆</option>
                        <option :value="50">50 筆</option>
                    </select>
                    <!-- Page Navigation -->
                    <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                        <button @click="currentPage > 1 && (currentPage--, loadProducts())" :disabled="currentPage === 1" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-slate-300 bg-white text-sm font-medium text-slate-500 hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed">
                            <span class="sr-only">上一頁</span>
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                        </button>
                        <button v-for="p in Math.min(5, Math.ceil(totalProducts / perPage))" :key="p" @click="currentPage = p; loadProducts()" :class="[p === currentPage ? 'z-10 bg-blue-50 border-primary text-primary' : 'bg-white border-slate-300 text-slate-500 hover:bg-slate-50', 'relative inline-flex items-center px-4 py-2 border text-sm font-medium']">
                            {{ p }}
                        </button>
                        <button @click="currentPage < Math.ceil(totalProducts / perPage) && (currentPage++, loadProducts())" :disabled="currentPage >= Math.ceil(totalProducts / perPage)" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-slate-300 bg-white text-sm font-medium text-slate-500 hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed">
                            <span class="sr-only">下一頁</span>
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                        </button>
                    </nav>
                </div>
            </div>

            <!-- Subpages -->
            <!-- Subpages (No Transition for Debugging) -->
            <div v-show="currentView !== 'list'" class="absolute inset-0 bg-slate-50 z-30 overflow-y-auto w-full" style="min-height: 100vh;">
                    <div class="sticky top-0 z-40 bg-white/95 backdrop-blur border-b border-slate-200 px-4 md:px-6 py-3 md:py-4 flex items-center justify-between shadow-sm">
                        <div class="flex items-center gap-2 md:gap-4 overflow-hidden">
                            <button @click="navigateTo('list')" class="p-2 -ml-2 text-slate-500 hover:text-slate-800 hover:bg-slate-100 rounded-full transition-colors flex items-center gap-1 group shrink-0">
                                <svg class="w-5 h-5 group-hover:-translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                                <span class="text-sm font-medium">返回</span>
                            </button>
                            <div class="h-5 w-px bg-slate-200 hidden md:block"></div>
                            <div class="truncate"><h2 class="text-base md:text-xl font-bold text-slate-900 truncate">{{ getSubPageTitle }}</h2></div>
                        </div>
                        <div class="flex gap-2 shrink-0">
                            <button @click="navigateTo('list')" class="px-3 py-1.5 md:px-4 md:py-2 bg-white border border-slate-300 rounded-lg text-slate-700 hover:bg-slate-50 transition text-xs md:text-sm font-medium">{{ currentView === 'buyers' ? '關閉' : '取消' }}</button>
                            <button v-if="currentView !== 'buyers'" @click="handleSubPageSave" class="px-3 py-1.5 md:px-6 md:py-2 bg-primary text-white rounded-lg hover:bg-blue-700 transition text-xs md:text-sm font-medium shadow-lg shadow-blue-200">{{ currentView === 'allocation' ? '確認' : '儲存' }}</button>
                        </div>
                    </div>

                    <div class="max-w-4xl mx-auto p-4 md:p-6 space-y-6 md:space-y-8">
                        <!-- Buyers List -->
                        <div v-if="currentView === 'buyers'">
                             <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                                <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50">
                                    <h3 class="font-bold text-slate-800">購買名單明細</h3>
                                </div>
                                <div v-if="buyersLoading" class="p-8 text-center"><div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary mx-auto"></div></div>
                                <table v-else class="min-w-full divide-y divide-slate-100">
                                    <thead class="bg-white"><tr><th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">客戶</th><th class="px-6 py-3 text-right text-xs font-medium text-slate-500 uppercase">數量</th><th class="px-6 py-3 text-right text-xs font-medium text-slate-500 uppercase">狀態</th></tr></thead>
                                    <tbody class="bg-white divide-y divide-slate-100">
                                        <tr v-for="buyer in buyers" :key="buyer.customer_id" class="hover:bg-slate-50">
                                            <td class="px-6 py-4 text-sm font-medium text-slate-900">{{ buyer.customer_name }}</td>
                                            <td class="px-6 py-4 text-sm text-right font-mono">{{ buyer.quantity }}</td>
                                            <td class="px-6 py-4 text-right"><span class="px-2 py-1 text-xs rounded-full bg-slate-100">已下單</span></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Edit Form -->
                        <div v-show="currentView === 'edit'" class="grid grid-cols-1 md:grid-cols-3 gap-4 md:gap-6">
                             <div class="md:col-span-2 space-y-4 md:space-y-6">
                                <div class="bg-white p-4 md:p-6 rounded-xl border border-slate-200 shadow-sm space-y-4">
                                    <h3 class="text-base md:text-lg font-bold text-slate-900 mb-2 md:mb-4">基本資訊 #{{ editingProduct.id }}</h3>
                                    <div><label class="block text-xs md:text-sm font-medium text-slate-700 mb-1">商品名稱</label><input type="text" v-model="editingProduct.name" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm bg-slate-50"></div>
                                    <div class="grid grid-cols-2 gap-3 md:gap-4">
                                        <div><label class="block text-xs md:text-sm font-medium text-slate-700 mb-1">價格</label><input type="number" v-model="editingProduct.price" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm"></div>
                                        <div><label class="block text-xs md:text-sm font-medium text-slate-700 mb-1">狀態</label><select v-model="editingProduct.status" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm"><option value="published">已上架</option><option value="private">已下架</option></select></div>
                                    </div>
                                    <div><label class="block text-xs md:text-sm font-medium text-slate-700 mb-1">已採購</label><input type="number" v-model="editingProduct.purchased" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm"></div>
                                </div>
                            </div>

                            <!-- Image Management Column -->
                             <div class="space-y-4">
                                <div class="bg-white p-4 md:p-6 rounded-xl border border-slate-200 shadow-sm">
                                    <h3 class="text-base md:text-lg font-bold text-slate-900 mb-2 md:mb-4">商品圖片</h3>
                                    <div class="relative group aspect-square bg-slate-50 rounded-lg border-2 border-dashed border-slate-300 hover:border-primary hover:bg-slate-100 transition-colors cursor-pointer overflow-hidden flex flex-col items-center justify-center" @click="openImageModal(editingProduct)">
                                        <img v-if="editingProduct.image" :src="editingProduct.image" class="absolute inset-0 w-full h-full object-cover">
                                        <div v-else class="text-slate-400 flex flex-col items-center">
                                            <svg class="w-8 h-8 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                            <span class="text-xs font-medium">點擊上傳圖片</span>
                                        </div>
                                        <!-- Hover Overlay -->
                                        <div v-if="editingProduct.image" class="absolute inset-0 bg-black/50 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                                            <span class="text-white text-xs md:text-sm font-bold bg-white/20 backdrop-blur px-3 py-1.5 rounded-full border border-white/50">更換圖片</span>
                                        </div>
                                    </div>
                                    <p class="text-xs text-slate-500 mt-2 text-center">支援 JPG, PNG, GIF</p>
                                </div>
                            </div>

                        </div>

                        <!-- Allocation View -->
                        <div v-show="currentView === 'allocation'" class="space-y-4 md:space-y-6">
                            <!-- 商品資訊卡片 -->
                            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4 flex gap-4 items-start">
                                <div class="w-20 h-20 bg-slate-100 rounded-lg flex items-center justify-center shrink-0 overflow-hidden border border-slate-200">
                                    <img v-if="selectedProduct?.image" :src="selectedProduct.image" class="w-full h-full object-cover">
                                    <svg v-else class="w-8 h-8 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                    </svg>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <h3 class="font-bold text-slate-900 truncate text-base md:text-lg">{{ selectedProduct?.name }}</h3>
                                    <p class="text-sm text-slate-500 mt-0.5">{{ formatPriceDisplay(selectedProduct?.price) }} <span class="text-xs text-slate-400 ml-1">ID: {{ selectedProduct?.id }}</span></p>
                                    <div class="mt-3 grid grid-cols-4 gap-2 text-center">
                                        <div class="bg-green-50 rounded-lg py-2 px-1">
                                            <div class="text-[10px] text-green-600">已下單</div>
                                            <div class="font-bold text-green-700">{{ selectedProduct?.ordered || 0 }}</div>
                                        </div>
                                        <div class="bg-slate-100 rounded-lg py-2 px-1">
                                            <div class="text-[10px] text-slate-600">已採購</div>
                                            <div class="font-bold text-slate-700">{{ selectedProduct?.purchased || 0 }}</div>
                                        </div>
                                        <div class="bg-blue-50 rounded-lg py-2 px-1">
                                            <div class="text-[10px] text-blue-600">已分配</div>
                                            <div class="font-bold text-blue-700">{{ selectedProduct?.allocated || 0 }}</div>
                                        </div>
                                        <div class="bg-primary/10 rounded-lg py-2 px-1">
                                            <div class="text-[10px] text-primary">可分配</div>
                                            <div class="font-bold text-primary">{{ (selectedProduct?.ordered || 0) - (selectedProduct?.allocated || 0) }}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- Orders Table implementation for Allocation... (Simplified for this step, using existing logic) -->
                            <!-- Orders Table implementation for Allocation -->
                            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden flex flex-col h-[calc(100vh-300px)]">
                                <div class="p-4 border-b border-slate-200 bg-slate-50 flex justify-between items-center">
                                    <h3 class="font-bold text-slate-800">待分配訂單</h3>
                                    <button @click="handleSubPageSave" class="px-4 py-2 bg-primary text-white text-sm font-bold rounded-lg shadow hover:bg-primary-dark transition">儲存分配</button>
                                </div>
                                <div class="flex-1 overflow-auto p-0">
                                    <div v-if="allocationLoading" class="flex flex-col items-center justify-center h-full py-12">
                                        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary mb-2"></div>
                                        <p class="text-slate-500">載入訂單中...</p>
                                    </div>
                                    <div v-else-if="productOrders.length === 0" class="flex flex-col items-center justify-center h-full py-12 text-slate-500">
                                        <svg class="w-12 h-12 mb-2 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>
                                        <p>目前沒有此商品的待處理訂單</p>
                                    </div>
                                    <table v-else class="min-w-full divide-y divide-slate-200">
                                        <thead class="bg-white sticky top-0 z-10 shadow-sm">
                                            <tr>
                                                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">訂單編號 / 客戶</th>
                                                <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase">下單時間</th>
                                                <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase">下單量</th>
                                                <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase">已分配</th>
                                                <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase w-32">本次分配</th>
                                                <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase">待分配</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100 bg-white">
                                            <tr v-for="order in productOrders" :key="order.order_id" class="hover:bg-slate-50 transition">
                                                <td class="px-4 py-3">
                                                    <div class="font-medium text-slate-900">#{{ order.order_id }}</div>
                                                    <div class="text-xs text-slate-500">{{ order.customer || '訪客' }}</div>
                                                </td>
                                                <td class="px-4 py-3 text-center text-sm text-slate-500">{{ order.date || '-' }}</td>
                                                <td class="px-4 py-3 text-right font-medium text-slate-900">{{ order.required || order.quantity }}</td>
                                                <td class="px-4 py-3 text-right font-medium text-blue-600">{{ order.already_allocated || 0 }}</td>
                                                <td class="px-4 py-3 text-right">
                                                    <input type="number" v-model.number="order.allocated" min="0" :max="order.pending || (order.required - (order.already_allocated || 0))" class="w-20 px-2 py-1 border border-slate-300 rounded text-right focus:border-primary focus:ring-1 focus:ring-primary outline-none">
                                                </td>
                                                <td class="px-4 py-3 text-right font-medium text-red-600">{{ order.pending || ((order.required || order.quantity) - (order.already_allocated || 0)) }}</td>
                                            </tr>
                                        </tbody>
                                        <tfoot class="bg-slate-50 font-bold text-slate-700">
                                            <tr>
                                                <td colspan="3" class="px-4 py-3 text-right">總計分配：</td>
                                                <td class="px-4 py-3 text-right text-primary">{{ productOrders.reduce((acc, o) => acc + (o.allocated||0), 0) }}</td>
                                                <td></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <!-- </transition> -->
        </div>
    </main>
    
    <!-- Image Modal -->
    <div v-if="showImageModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm" @click.self="closeImageModal">
        <div class="bg-white rounded-2xl p-6 shadow-xl max-w-lg w-full">
            <h2 class="text-xl font-bold mb-4">編輯圖片</h2>
            <img v-if="currentImage" :src="currentImage" class="w-full h-48 object-cover rounded mb-4">
            <div @click="triggerFileInput" class="border-2 border-dashed p-8 text-center cursor-pointer hover:border-primary">點擊上傳圖片</div>
            <input ref="fileInput" type="file" @change="handleFileSelect" class="hidden" accept="image/*">
        </div>
    </div>
    
    <!-- Toast -->
    <div v-if="toastMessage.show" class="fixed top-4 right-4 z-50 px-6 py-4 rounded-lg shadow-lg bg-slate-800 text-white animate-in fade-in slide-in-from-top-4">{{ toastMessage.message }}</div>

</div>
HTML;
?>

<!-- App Mount Point -->
<div id="buygo-app" v-cloak></div>

<!-- Component Template Definition -->
<script type="text/x-template" id="products-page-template">
    <?php echo $products_component_template; ?>
</script>

<script>
const ProductsPageComponent = {
    name: 'ProductsPage',
    components: {
        'smart-search-box': BuyGoSmartSearchBox
    },
    template: '#products-page-template',
    setup() {
        const { ref, computed, watch, onMounted } = Vue;

        // 使用 useCurrency Composable 處理幣別邏輯
        const {
            formatPrice,
            convertCurrency,
            getCurrencySymbol,
            systemCurrency,
            currencySymbols,
            exchangeRates
        } = useCurrency();
        
        // --- Router & UI State ---
        const isSidebarCollapsed = ref(false);
        const showMobileMenu = ref(false);
        const showMobileSearch = ref(false);
        const currentTab = ref('products');
        const currentView = ref('list'); // 'list', 'edit', 'allocation', 'buyers'
        const currentId = ref(null);
        
        // --- Data Refs ---
        const products = ref([]);
        const selectedItems = ref([]);
        const loading = ref(true);
        const error = ref(null);
        const globalSearchQuery = ref('');
        
        // --- Sub-page Data ---
        const editingProduct = ref({ id: '', name: '', price: 0, status: 'published', purchased: 0 }); // Initialize with defaults
        const selectedProduct = ref(null);
        
        // Buyers
        const buyers = ref([]);
        const buyersLoading = ref(false);
        
        // Allocation
        const productOrders = ref([]);
        const allocationLoading = ref(false);
        
        // Image Modal
        const showImageModal = ref(false);
        const currentImage = ref(null);
        const imageError = ref(null);
        const notification = ref(null);
        const fileInput = ref(null);
        const currentProduct = ref(null); // Ensure this is defined once
        
        // Ensure editingProduct has default structure
        // const editingProduct = ref(...); // Already defined above
        
        // Toast
        const toastMessage = ref({ show: false, message: '', type: 'success' });
        
        // Pagination
        const currentPage = ref(1);
        const perPage = ref(5);
        const totalProducts = ref(0);

        // 當前顯示幣別
        const currentCurrency = ref(systemCurrency.value);

        // --- Router Logic (使用 BuyGoRouter 核心模組) ---
        const checkUrlParams = async () => {
            const params = window.BuyGoRouter.checkUrlParams();
            const { view, id } = params;

            if (view && view !== 'list' && id) {
                // 先嘗試在已載入的列表中找
                let product = products.value.find(p => p.id == id);

                // 如果列表中沒有，透過 API 取得單一商品
                if (!product) {
                    try {
                        const res = await fetch(`/wp-json/buygo-plus-one/v1/products?id=${id}`);
                        const data = await res.json();
                        if (data.success && data.data && data.data.length > 0) {
                            product = data.data[0];
                        }
                    } catch (e) {
                        console.error('Failed to fetch product:', e);
                    }
                }

                if (product) {
                    handleNavigation(view, product, false);
                } else if (!loading.value) {
                    handleNavigation('list', null, false);
                }
            } else {
                currentView.value = 'list';
            }
        };

        const navigateTo = async (view, product = null, updateUrl = true) => {
            await handleNavigation(view, product, updateUrl);
        };

        const handleNavigation = async (view, product = null, updateUrl = true) => {
            currentView.value = view;

            if (product) {
                currentId.value = product.id;
                selectedProduct.value = product;

                if (updateUrl) {
                    window.BuyGoRouter.navigateTo(view, product.id);
                }

                // Load Data for Sub-pages
                if (view === 'edit') {
                    editingProduct.value = { ...product };
                } else if (view === 'allocation') {
                    await loadProductOrders(product.id);
                } else if (view === 'buyers') {
                    await loadBuyers(product.id);
                }
            } else {
                currentId.value = null;
                selectedProduct.value = null;
                if (updateUrl) {
                    window.BuyGoRouter.goToList();
                }
            }
        };

        const getSubPageTitle = computed(() => {
            if (currentView.value === 'edit') return '編輯商品';
            if (currentView.value === 'allocation') return '庫存分配';
            if (currentView.value === 'buyers') return '下單名單';
            return '';
        });
        
        const isAllSelected = computed(() => {
            return products.value.length > 0 && selectedItems.value.length === products.value.length;
        });
        
        // --- API Methods ---
        const loadProducts = async () => {
            loading.value = true;
            try {
                let url = `/wp-json/buygo-plus-one/v1/products?page=${currentPage.value}&per_page=${perPage.value}`;
                if (globalSearchQuery.value) {
                    url += `&search=${encodeURIComponent(globalSearchQuery.value)}`;
                }
                const res = await fetch(url);
                const data = await res.json();
                if (data.success) {
                    products.value = data.data;
                    totalProducts.value = data.total || data.data.length;
                    await checkUrlParams(); 
                } else {
                    products.value = [];
                    totalProducts.value = 0;
                    showToast(data.message || '載入失敗', 'error');
                }
            } catch (e) {
                error.value = e.message;
            } finally {
                loading.value = false;
            }
        };

        const loadBuyers = async (id) => {
            buyersLoading.value = true;
            try {
                const res = await fetch(`/wp-json/buygo-plus-one/v1/products/${id}/buyers`);
                const data = await res.json();
                if (data.success) buyers.value = data.data;
            } catch(e) { console.error(e); }
            finally { buyersLoading.value = false; }
        };

        const loadProductOrders = async (id) => {
            allocationLoading.value = true;
             try {
                const res = await fetch(`/wp-json/buygo-plus-one/v1/products/${id}/orders`);
                const data = await res.json();
                // Adapter for old API response structure if needed
                if (data.success) productOrders.value = data.data;
            } catch(e) { console.error(e); }
            finally { allocationLoading.value = false; }
        };

        const saveProduct = async () => {
            try {
                const res = await fetch(`/wp-json/buygo-plus-one/v1/products/${editingProduct.value.id}`, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': '<?php echo wp_create_nonce("wp_rest"); ?>' },
                    body: JSON.stringify(editingProduct.value)
                });
                const data = await res.json();
                if (data.success) {
                    const idx = products.value.findIndex(p => p.id === editingProduct.value.id);
                    if (idx !== -1) products.value[idx] = { ...products.value[idx], ...editingProduct.value };
                    showToast('儲存成功');
                    loadProducts(); // Refresh list
                    navigateTo('list');
                } else {
                    showToast(data.message || '儲存失敗', 'error');
                }
            } catch(e) { showToast('儲存失敗', 'error'); }
        };
        
        const savePurchased = async (product) => {
             // Reuse logic from saveProduct or dedicated endpoint
             try {
                await fetch(`/wp-json/buygo-plus-one/v1/products/${product.id}`, {
                    method: 'PUT',
                     headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ purchased: product.purchased })
                });
                showToast('已更新採購數量');
             } catch(e) { console.error(e); }
        };

        const toggleStatus = async (product) => {
            const newStatus = product.status === 'published' ? 'private' : 'published';
             try {
                await fetch(`/wp-json/buygo-plus-one/v1/products/${product.id}`, {
                    method: 'PUT',
                     headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ status: newStatus })
                });
                product.status = newStatus;
             } catch(e) { console.error(e); }
        };

        const deleteProduct = async (id) => {
            if(!window.confirm('確定要刪除此商品嗎？此動作無法復原。')) return;
            try {
                const res = await fetch('/wp-json/buygo-plus-one/v1/products/batch-delete', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': '<?php echo wp_create_nonce("wp_rest"); ?>' },
                    body: JSON.stringify({ ids: [id] })
                });
                const data = await res.json();
                
                if (data.success) {
                     products.value = products.value.filter(p => p.id !== id);
                     showToast('已刪除');
                     loadProducts();
                } else {
                     showToast(data.message || '刪除失敗', 'error');
                }
            } catch(e) { console.error(e); showToast('刪除錯誤', 'error'); }
        };
        
        const batchDelete = async () => {
             if(!confirm(`確認刪除 ${selectedItems.value.length} 項？`)) return;
             // Implement batch delete API call
             products.value = products.value.filter(p => !selectedItems.value.includes(p.id));
             selectedItems.value = [];
             showToast('批次刪除成功');
        };

        // SubPage Save Handler
        const handleSubPageSave = async () => {
            if (currentView.value === 'edit') {
                saveProduct();
            } else if (currentView.value === 'allocation') {
                await handleAllocation();
            }
        };
        
        // 處理分配功能
        const handleAllocation = async () => {
            if (!selectedProduct.value) {
                showToast('請選擇商品', 'error');
                return;
            }
            
            // 準備分配資料
            const allocationData = productOrders.value
                .filter(order => order.allocated && order.allocated > 0)
                .map(order => ({
                    order_id: order.order_id,
                    order_item_id: order.order_item_id || order.id,
                    quantity: order.allocated
                }));
            
            if (allocationData.length === 0) {
                showToast('請至少分配一個訂單', 'error');
                return;
            }
            
            try {
                const res = await fetch('/wp-json/buygo-plus-one/v1/products/allocate', {
                    method: 'POST',
                    headers: { 
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': '<?php echo wp_create_nonce("wp_rest"); ?>'
                    },
                    body: JSON.stringify({
                        product_id: selectedProduct.value.id,
                        allocations: allocationData
                    })
                });
                
                const data = await res.json();
                
                if (data.success) {
                    showToast('分配成功', 'success');

                    // 計算總分配數量
                    const totalAllocated = allocationData.reduce((sum, alloc) => sum + alloc.quantity, 0);

                    // 立即更新本地商品資料的 allocated 欄位
                    const productIndex = products.value.findIndex(p => p.id === selectedProduct.value.id);
                    if (productIndex !== -1) {
                        products.value[productIndex].allocated = (products.value[productIndex].allocated || 0) + totalAllocated;
                    }

                    // 如果正在編輯的商品是同一個，也更新編輯中的商品
                    if (editingProduct.value && editingProduct.value.id === selectedProduct.value.id) {
                        editingProduct.value.allocated = (editingProduct.value.allocated || 0) + totalAllocated;
                    }

                    // 更新 selectedProduct
                    if (selectedProduct.value) {
                        selectedProduct.value.allocated = (selectedProduct.value.allocated || 0) + totalAllocated;
                    }

                    // 重新載入商品列表（確保資料同步）
                    await loadProducts();
                    // 重新載入訂單資料
                    await loadProductOrders(selectedProduct.value.id);

                    // 通知訂單頁面需要重新載入（用於同步執行出貨按鈕狀態）
                    localStorage.setItem('buygo_allocation_updated', Date.now().toString());

                    // 返回列表
                    navigateTo('list');
                } else {
                    showToast(data.message || '分配失敗', 'error');
                }
            } catch (e) {
                console.error('分配失敗:', e);
                showToast('分配失敗：' + e.message, 'error');
            }
        };
        
        // Image Handling
        const openImageModal = (p) => { currentProduct.value = p; currentImage.value = p.image; showImageModal.value = true; };
        const closeImageModal = () => { showImageModal.value = false; currentProduct.value = null; };
        const triggerFileInput = () => fileInput.value.click();
        const handleFileSelect = async (e) => {
            const file = e.target.files[0];
            if(file) {
                 const formData = new FormData();
                 formData.append('image', file);
                 try {
                     const res = await fetch(`/wp-json/buygo-plus-one/v1/products/${currentProduct.value.id}/image`, {
                         method: 'POST',
                         headers: { 'X-WP-Nonce': '<?php echo wp_create_nonce("wp_rest"); ?>' },
                         body: formData
                     });
                     const data = await res.json();
                     if (data.success) {
                         currentImage.value = data.data.image_url;
                         currentProduct.value.image = data.data.image_url;
                         if (editingProduct.value && editingProduct.value.id === currentProduct.value.id) {
                             editingProduct.value.image = data.data.image_url;
                         }
                         showToast('圖片上傳成功');
                     } else {
                         imageError.value = data.message;
                     }
                 } catch(err) {
                    imageError.value = '上傳錯誤';
                 }
            }
        };

        // Helpers
        const toggleSelectAll = () => {
            if (isAllSelected.value) selectedItems.value = [];
            else selectedItems.value = products.value.map(p => p.id);
        };

        // 格式化價格（根據 currentCurrency 顯示）
        const formatPriceDisplay = (price, productCurrency = null) => {
            const safePrice = price ?? 0;
            const sourceCurrency = productCurrency || systemCurrency.value;

            // 如果當前顯示幣別與商品幣別相同,直接格式化
            if (currentCurrency.value === sourceCurrency) {
                return formatPrice(safePrice, sourceCurrency);
            }

            // 否則進行匯率轉換
            const convertedPrice = convertCurrency(safePrice, sourceCurrency, currentCurrency.value);
            return formatPrice(convertedPrice, currentCurrency.value);
        };

        // 計算台幣轉換價格（用於顯示參考價格）
        const getTWDPrice = (price, currency) => {
            const safePrice = price ?? 0;
            const rates = exchangeRates.value;
            const rate = rates[currency] || 1;
            return Math.round(safePrice * rate);
        };

        const calculateReserved = (p) => Math.max(0, (p.ordered || 0) - (p.purchased || 0));
        const showToast = (msg, type='success') => { toastMessage.value = { show: true, message: msg, type }; setTimeout(()=> toastMessage.value.show=false, 3000); };

        // Smart Search Box 處理函數
        const handleProductSelect = (product) => {
            if (product && product.id) {
                // 導航到商品編輯頁面
                navigateTo('edit', product);
            }
        };

        // 本地搜尋處理函數(輸入時過濾列表)
        const handleProductSearch = (query) => {
            globalSearchQuery.value = query;
            currentPage.value = 1;  // 重置到第一頁
            loadProducts();
        };

        // 清除搜尋
        const handleProductSearchClear = () => {
            globalSearchQuery.value = '';
            currentPage.value = 1;
            loadProducts();
        };

        onMounted(() => {
            loadProducts();
            // 使用 BuyGoRouter 核心模組的 popstate 監聽
            window.BuyGoRouter.setupPopstateListener(checkUrlParams);
        });

        return {
            // State
            isSidebarCollapsed, showMobileMenu, showMobileSearch, currentTab, currentView, currentId,
            products, selectedItems, loading, error, globalSearchQuery,
            editingProduct, selectedProduct, buyers, buyersLoading, productOrders, allocationLoading,
            showImageModal, currentImage, toastMessage,
            currentPage, perPage, totalProducts, menuItems: [
                { id: 'products', label: '商品管理', icon: '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>' },
                { id: 'orders', label: '訂單管理', icon: '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>' },
                 { id: 'settings', label: '系統設定', icon: '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>' },
            ],
            
            // Methods
            navigateTo, checkUrlParams, getSubPageTitle, isAllSelected,
            loadProducts, saveProduct, savePurchased, toggleStatus, deleteProduct, batchDelete,
            handleSubPageSave, openImageModal, closeImageModal, triggerFileInput, handleFileSelect,
            toggleSelectAll, formatPriceDisplay, getTWDPrice, calculateReserved, handleSearchInput: (e) => { globalSearchQuery.value = e.target.value; loadProducts(); },
            handleProductSelect,
            handleProductSearch,
            handleProductSearchClear,
             fileInput,
             handleTabClick: (id) => {
                 currentTab.value = id;
                 if (id === 'products') navigateTo('list');
             },
             currentCurrency,
             systemCurrency,
             currencySymbols,
             toggleCurrency: () => {
                 // 在系統幣別和台幣之間切換
                 if (currentCurrency.value === 'TWD') {
                     currentCurrency.value = systemCurrency.value;
                     showToast(`已切換為 ${currencySymbols[systemCurrency.value]} ${systemCurrency.value}`);
                 } else {
                     currentCurrency.value = 'TWD';
                     showToast(`已切換為 NT$ TWD`);
                 }
             }
        };
    }
};
// 注意：不再自行掛載，由 template.php 統一管理 Vue app
</script>

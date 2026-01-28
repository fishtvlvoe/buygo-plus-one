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
<!-- Products Page Styles -->
<link rel="stylesheet" href="<?php echo esc_url(plugins_url('../css/products.css', __FILE__)); ?>" />

<?php
$products_component_template = <<<'HTML'

<!-- Root Template Content (由 template.php 統一掛載，側邊欄已由共用組件處理) -->
<div class="min-h-screen bg-slate-50 text-slate-900 font-sans antialiased">

    <!-- Main Content -->
    <main class="flex flex-col min-w-0 relative bg-slate-50 min-h-screen">

        <!-- ============================================ -->
        <!-- 頁首部分 -->
        <!-- ============================================ -->
        <header class="page-header">
            <div class="flex items-center gap-3 md:gap-4 overflow-hidden flex-1">
                <div class="flex flex-col overflow-hidden min-w-0 pl-12 md:pl-0" v-show="!showMobileSearch">
                    <h1 class="page-header-title">商品</h1>
                    <nav class="page-header-breadcrumb">
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
                <div class="global-search">
                    <input type="text" placeholder="全域搜尋..." v-model="globalSearchQuery" @input="handleSearchInput">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </div>

                <!-- Notification -->
                <button class="notification-bell">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                </button>
                
                <!-- Currency Toggle -->
                <button @click="toggleCurrency" class="ml-2 px-3 py-1.5 bg-white border border-slate-200 rounded-md text-xs font-bold hover:border-primary hover:text-primary transition shadow-sm" :class="currentCurrency === 'TWD' ? 'text-green-600 border-green-200' : 'text-slate-600'">
                    <span>{{ currencySymbols[currentCurrency] || currentCurrency }}</span>
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
        <!-- 結束：頁首部分 -->

        <!-- ============================================ -->
        <!-- 內容區域 -->
        <!-- ============================================ -->
        <div class="flex-1 overflow-auto bg-slate-50/50 relative">

            <!-- 列表檢視 -->
            <div v-show="currentView === 'list'" class="p-2 xs:p-4 md:p-6 w-full max-w-7xl mx-auto space-y-4 md:space-y-6">
                
                <!-- Toolbar: Search + View Toggle -->
                <div class="flex items-center gap-2 md:gap-3">
                    <div class="flex-1">
                        <smart-search-box
                            api-endpoint="/wp-json/buygo-plus-one/v1/products"
                            :search-fields="['name', 'sku']"
                            @select="handleProductSelect"
                            @search="handleProductSearch"
                            @clear="handleProductSearchClear"
                        ></smart-search-box>
                    </div>
                    <!-- View Mode Toggle (支援手機版和桌面版) -->
                    <div class="flex items-center bg-white border border-slate-200 rounded-lg p-0.5 md:p-1 shadow-sm shrink-0">
                        <button @click="viewMode = 'table'" :class="viewMode === 'table' ? 'bg-primary text-white' : 'text-slate-500 hover:bg-slate-100'" class="p-1.5 md:p-2 rounded-md transition" title="列表檢視">
                            <svg class="w-4 h-4 md:w-5 md:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"></path></svg>
                        </button>
                        <button @click="viewMode = 'grid'" :class="viewMode === 'grid' ? 'bg-primary text-white' : 'text-slate-500 hover:bg-slate-100'" class="p-1.5 md:p-2 rounded-md transition" title="網格檢視">
                            <svg class="w-4 h-4 md:w-5 md:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
                        </button>
                    </div>
                </div>

                <!-- Loading -->
                <div v-if="loading" class="text-center py-12"><div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary mx-auto"></div><p class="mt-2 text-slate-500">載入中...</p></div>
                
                <!-- Content (Desktop & Mobile) -->
                <div v-else>
                    <!-- Desktop Table View -->
                    <div v-show="viewMode === 'table'" class="data-table">
                        <table>
                            <thead>
                                <tr>
                                    <th class="w-12 text-center"><input type="checkbox" @change="toggleSelectAll" :checked="isAllSelected" class="rounded border-slate-300 text-primary w-4 h-4 cursor-pointer"></th>
                                    <th class="w-[35%]">商品</th>
                                    <th class="text-right whitespace-nowrap hidden lg:table-cell">價格</th>
                                    <th class="text-center whitespace-nowrap">狀態</th>
                                    <th class="text-center whitespace-nowrap">下單</th>
                                    <th class="text-center whitespace-nowrap">採購</th>
                                    <th class="text-center whitespace-nowrap hidden xl:table-cell">已出貨</th>
                                    <th class="text-center whitespace-nowrap text-blue-600">待出貨</th>
                                    <th class="text-center whitespace-nowrap text-slate-400">預訂</th>
                                    <th class="text-center whitespace-nowrap">操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="product in products" :key="product.id">
                                    <td class="text-center"><input type="checkbox" :value="product.id" v-model="selectedItems" class="rounded border-slate-300 text-primary w-4 h-4 cursor-pointer"></td>
                                    <td>
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
                                    <td class="text-right font-mono text-sm font-medium hidden lg:table-cell">
                                        <span>{{ formatPriceDisplay(product.price, product.currency) }}</span>
                                    </td>
                                    <td class="text-center">
                                         <button @click="toggleStatus(product)" :class="product.status === 'published' ? 'bg-green-100 text-green-800 border-green-200' : 'bg-slate-100 text-slate-800 border-slate-200'" class="px-2.5 py-1 text-xs font-semibold rounded-full border hover:opacity-80 transition cursor-pointer">{{ product.status === 'published' ? '已上架' : '已下架' }}</button>
                                    </td>
                                    <td class="text-center">
                                        <button @click="navigateTo('buyers', product)" class="text-base font-bold text-green-600 hover:text-green-700 hover:underline decoration-green-300 underline-offset-2 transition">{{ product.ordered || 0 }}</button>
                                    </td>
                                    <td class="text-center">
                                        <input type="number" v-model.number="product.purchased" @blur="savePurchased(product)" class="inline-edit-input text-gray-700 bg-slate-50 focus:bg-white" @click.stop>
                                    </td>
                                    <td class="text-center font-bold text-blue-600 font-mono text-sm hidden xl:table-cell">{{ product.shipped || 0 }}</td>
                                    <td class="text-center font-bold text-orange-600 font-mono text-sm">{{ Math.max(0, (product.allocated || 0) - (product.shipped || 0)) }}</td>
                                    <td class="text-center font-bold text-slate-400 font-mono text-sm">{{ calculateReserved(product) }}</td>
                                    <td class="text-center">
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

                    <!-- Desktop Grid View -->
                    <div v-show="viewMode === 'grid'" class="hidden md:block">
                        <!-- Grid Header with Select All -->
                        <div class="flex items-center justify-between mb-4 px-1">
                            <label class="flex items-center gap-2 text-sm text-slate-600 font-medium">
                                <input type="checkbox" @change="toggleSelectAll" :checked="isAllSelected" class="rounded border-slate-300 text-primary w-4 h-4 cursor-pointer">
                                全選 ({{ selectedItems.length }}/{{ products.length }})
                            </label>
                            <span class="text-xs text-slate-500">{{ products.length }} 件商品</span>
                        </div>
                        <!-- Grid Container -->
                        <div class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5 gap-4">
                            <div v-for="product in products" :key="product.id" class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden hover:shadow-md hover:border-primary/30 transition group">
                                <!-- Image with checkbox overlay -->
                                <div class="relative aspect-square bg-slate-100 cursor-pointer" @click="openImageModal(product)">
                                    <img v-if="product.image" :src="product.image" class="w-full h-full object-cover">
                                    <div v-else class="w-full h-full flex items-center justify-center text-slate-300">
                                        <svg class="w-16 h-16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                    </div>
                                    <!-- Checkbox -->
                                    <div class="absolute top-2 left-2">
                                        <input type="checkbox" :value="product.id" v-model="selectedItems" @click.stop class="rounded border-slate-300 text-primary w-5 h-5 bg-white/90 shadow-sm cursor-pointer">
                                    </div>
                                    <!-- Status Badge -->
                                    <div class="absolute top-2 right-2">
                                        <span :class="product.status === 'published' ? 'bg-green-500' : 'bg-slate-400'" class="px-2 py-0.5 text-[10px] font-bold text-white rounded-full shadow">
                                            {{ product.status === 'published' ? '上架' : '下架' }}
                                        </span>
                                    </div>
                                    <!-- Quick Stats Overlay (on hover) -->
                                    <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/70 to-transparent p-3 opacity-0 group-hover:opacity-100 transition-opacity">
                                        <div class="flex justify-around text-white text-xs">
                                            <div class="text-center">
                                                <div class="font-bold text-green-400">{{ product.ordered || 0 }}</div>
                                                <div class="text-[10px] text-white/70">下單</div>
                                            </div>
                                            <div class="text-center">
                                                <div class="font-bold text-blue-400">{{ product.allocated || 0 }}</div>
                                                <div class="text-[10px] text-white/70">分配</div>
                                            </div>
                                            <div class="text-center">
                                                <div class="font-bold text-orange-400">{{ Math.max(0, (product.allocated || 0) - (product.shipped || 0)) }}</div>
                                                <div class="text-[10px] text-white/70">待出</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <!-- Product Info -->
                                <div class="p-3">
                                    <h3 class="text-sm font-bold text-slate-900 line-clamp-2 leading-tight mb-1 cursor-pointer hover:text-primary transition" @click="navigateTo('edit', product)">{{ product.name }}</h3>
                                    <div class="flex items-center justify-between mt-2">
                                        <span class="text-sm font-bold text-primary">{{ formatPriceDisplay(product.price, product.currency) }}</span>
                                        <span class="text-[10px] text-slate-400 font-mono">ID: {{ product.id }}</span>
                                    </div>
                                    <!-- Purchased Input -->
                                    <div class="flex items-center justify-between mt-2 pt-2 border-t border-slate-100">
                                        <span class="text-[10px] text-slate-500">採購數量</span>
                                        <input type="number" v-model.number="product.purchased" @blur="savePurchased(product)" @click.stop class="w-16 px-2 py-1 text-right text-xs font-bold border rounded bg-slate-50 focus:bg-white focus:border-primary focus:outline-none">
                                    </div>
                                </div>
                                <!-- Action Buttons -->
                                <div class="grid grid-cols-3 border-t border-slate-200 divide-x divide-slate-200">
                                    <button @click="navigateTo('buyers', product)" class="py-2.5 flex items-center justify-center text-green-600 hover:bg-green-50 transition" title="下單名單">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                                    </button>
                                    <button @click="navigateTo('edit', product)" class="py-2.5 flex items-center justify-center text-slate-500 hover:bg-slate-50 transition" title="編輯">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                    </button>
                                    <button @click="deleteProduct(product.id)" class="py-2.5 flex items-center justify-center text-red-500 hover:bg-red-50 transition" title="刪除">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Mobile Views -->
                    <div class="md:hidden">
                        <!-- Mobile List View (原始 Card View) -->
                        <div v-show="viewMode === 'table'" class="card-list">
                            <div class="flex items-center gap-3 px-1 mb-2">
                                <label class="flex items-center gap-2 text-sm text-slate-600 font-medium">
                                    <input type="checkbox" @change="toggleSelectAll" :checked="isAllSelected" class="rounded border-slate-300 text-primary w-4 h-4 cursor-pointer">
                                    全選
                                </label>
                            </div>
                            <div v-for="product in products" :key="product.id" class="card">
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
                                        <div class="font-bold text-orange-600 text-base">{{ Math.max(0, (product.allocated || 0) - (product.shipped || 0)) }}</div>
                                    </div>
                                </div>
                                <div class="grid grid-cols-3 border-t border-slate-200 divide-x divide-slate-200">
                                    <button @click="navigateTo('allocation', product)" class="py-3 flex items-center justify-center gap-1.5 text-blue-600 hover:bg-blue-50 bg-white transition active:bg-blue-100"><span class="text-xs font-bold">分配</span></button>
                                    <button @click="navigateTo('edit', product)" class="py-3 flex items-center justify-center gap-1.5 text-slate-600 hover:bg-slate-50 bg-white transition active:bg-slate-100"><span class="text-xs font-bold">編輯</span></button>
                                    <button @click="deleteProduct(product.id)" class="py-3 flex items-center justify-center gap-1.5 text-red-500 hover:bg-red-50 bg-white transition active:bg-red-100"><span class="text-xs font-bold">刪除</span></button>
                                </div>
                            </div>
                        </div>

                        <!-- Mobile Grid View (單欄滿版大圖) -->
                        <div v-show="viewMode === 'grid'">
                            <!-- Grid Header with Select All -->
                            <div class="flex items-center justify-between mb-3 px-1">
                                <label class="flex items-center gap-2 text-sm text-slate-600 font-medium">
                                    <input type="checkbox" @change="toggleSelectAll" :checked="isAllSelected" class="rounded border-slate-300 text-primary w-4 h-4 cursor-pointer">
                                    全選 ({{ selectedItems.length }}/{{ products.length }})
                                </label>
                                <span class="text-xs text-slate-500">{{ products.length }} 件商品</span>
                            </div>
                            <!-- Grid Container (手機版單欄滿版) -->
                            <div class="space-y-3">
                                <div v-for="product in products" :key="product.id" class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                                    <!-- 大圖區域 -->
                                    <div class="relative aspect-[4/3] bg-slate-100 cursor-pointer" @click="openImageModal(product)">
                                        <img v-if="product.image" :src="product.image" class="w-full h-full object-cover">
                                        <div v-else class="w-full h-full flex items-center justify-center text-slate-300">
                                            <svg class="w-20 h-20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                        </div>
                                        <!-- Checkbox -->
                                        <div class="absolute top-3 left-3">
                                            <input type="checkbox" :value="product.id" v-model="selectedItems" @click.stop class="rounded border-slate-300 text-primary w-6 h-6 bg-white/90 shadow-sm cursor-pointer">
                                        </div>
                                        <!-- Status Badge -->
                                        <div class="absolute top-3 right-3">
                                            <span :class="product.status === 'published' ? 'bg-green-500' : 'bg-slate-400'" class="px-2 py-1 text-xs font-bold text-white rounded-full shadow">
                                                {{ product.status === 'published' ? '上架' : '下架' }}
                                            </span>
                                        </div>
                                    </div>
                                    <!-- Stats Row (獨立一排，不遮擋圖片) -->
                                    <div class="grid grid-cols-3 divide-x divide-slate-200 border-b border-slate-200 bg-slate-50">
                                        <div class="py-3 text-center">
                                            <div class="text-xl font-bold text-green-600">{{ product.ordered || 0 }}</div>
                                            <div class="text-[10px] text-slate-500">下單</div>
                                        </div>
                                        <div class="py-3 text-center">
                                            <div class="text-xl font-bold text-blue-600">{{ product.allocated || 0 }}</div>
                                            <div class="text-[10px] text-slate-500">分配</div>
                                        </div>
                                        <div class="py-3 text-center">
                                            <div class="text-xl font-bold text-orange-600">{{ Math.max(0, (product.allocated || 0) - (product.shipped || 0)) }}</div>
                                            <div class="text-[10px] text-slate-500">待出</div>
                                        </div>
                                    </div>
                                    <!-- Product Info -->
                                    <div class="p-3">
                                        <h3 class="text-base font-bold text-slate-900 leading-tight mb-2 cursor-pointer hover:text-primary transition" @click="navigateTo('edit', product)">{{ product.name }}</h3>
                                        <div class="flex items-center justify-between">
                                            <span class="text-lg font-bold text-primary">{{ formatPriceDisplay(product.price, product.currency) }}</span>
                                            <span class="text-xs text-slate-400 font-mono">ID: {{ product.id }}</span>
                                        </div>
                                    </div>
                                    <!-- Action Buttons -->
                                    <div class="grid grid-cols-3 border-t border-slate-200 divide-x divide-slate-200">
                                        <button @click="navigateTo('buyers', product)" class="py-3 flex items-center justify-center gap-1.5 text-green-600 hover:bg-green-50 active:bg-green-100 transition">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                                            <span class="text-xs font-bold">名單</span>
                                        </button>
                                        <button @click="navigateTo('allocation', product)" class="py-3 flex items-center justify-center gap-1.5 text-blue-600 hover:bg-blue-50 active:bg-blue-100 transition">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                                            <span class="text-xs font-bold">分配</span>
                                        </button>
                                        <button @click="navigateTo('edit', product)" class="py-3 flex items-center justify-center gap-1.5 text-slate-600 hover:bg-slate-50 active:bg-slate-100 transition">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                            <span class="text-xs font-bold">編輯</span>
                                        </button>
                                    </div>
                                </div>
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
            <!-- 結束：列表檢視 -->

            <!-- 子頁面（編輯、分配、下單名單等） -->
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
                            <!-- 商品資訊卡片 -->
                            <div v-if="buyersProduct" class="bg-white rounded-xl border border-slate-200 shadow-sm p-4 mb-4 flex items-center gap-4">
                                <img v-if="buyersProduct.image" :src="buyersProduct.image" class="w-16 h-16 rounded-lg object-cover border border-slate-200" />
                                <div v-else class="w-16 h-16 rounded-lg bg-slate-100 flex items-center justify-center border border-slate-200">
                                    <svg class="w-8 h-8 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <h4 class="font-bold text-slate-900 truncate">{{ buyersProduct.name }}</h4>
                                    <div class="text-xs text-slate-500 mt-1">商品 ID: {{ buyersProduct.id }}</div>
                                </div>
                                <div class="text-right shrink-0">
                                    <div class="text-2xl font-bold text-primary">{{ buyers.length }}</div>
                                    <div class="text-xs text-slate-500">筆訂單</div>
                                </div>
                            </div>

                            <!-- 統計摘要區塊 -->
                            <div class="grid grid-cols-4 gap-2 mb-4">
                                <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-3 text-center">
                                    <div class="text-2xl font-bold text-slate-900">{{ buyersSummary.totalQuantity }}</div>
                                    <div class="text-xs text-slate-500 mt-1">總數量</div>
                                </div>
                                <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-3 text-center">
                                    <div class="text-2xl font-bold text-green-600">{{ buyersSummary.totalAllocated }}</div>
                                    <div class="text-xs text-green-600 mt-1">已分配</div>
                                </div>
                                <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-3 text-center">
                                    <div class="text-2xl font-bold text-amber-600">{{ buyersSummary.totalPending }}</div>
                                    <div class="text-xs text-amber-600 mt-1">待分配</div>
                                </div>
                                <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-3 text-center">
                                    <div class="text-2xl font-bold text-blue-600">{{ buyersSummary.totalShipped }}</div>
                                    <div class="text-xs text-blue-600 mt-1">已出貨</div>
                                </div>
                            </div>

                            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                                <!-- 標題與搜尋 -->
                                <div class="p-3 border-b border-slate-200 bg-slate-50">
                                    <div class="flex items-center justify-between mb-2">
                                        <h3 class="font-bold text-slate-800">訂單明細</h3>
                                        <span class="text-xs text-slate-500">共 {{ filteredBuyers.length }} 筆訂單</span>
                                    </div>
                                    <!-- 搜尋框 -->
                                    <div class="relative">
                                        <input type="text" v-model="buyersSearch" placeholder="搜尋客戶名稱..." class="w-full pl-9 pr-4 py-2 text-sm border border-slate-300 rounded-lg focus:border-primary focus:ring-1 focus:ring-primary outline-none">
                                        <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                                        <button v-if="buyersSearch" @click="buyersSearch = ''" class="absolute right-3 top-2.5 text-slate-400 hover:text-slate-600">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                        </button>
                                    </div>
                                </div>
                                <div v-if="buyersLoading" class="p-8 text-center"><div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary mx-auto"></div></div>
                                <div v-else-if="buyers.length === 0" class="p-8 text-center text-slate-500">
                                    <svg class="w-12 h-12 mx-auto text-slate-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                                    <p>目前沒有訂單</p>
                                </div>
                                <div v-else-if="filteredBuyers.length === 0" class="p-8 text-center text-slate-500">
                                    <svg class="w-12 h-12 mx-auto text-slate-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                                    <p>找不到符合「{{ buyersSearch }}」的訂單</p>
                                </div>
                                <!-- 桌面版表格 -->
                                <table v-else class="hidden md:table min-w-full divide-y divide-slate-100">
                                    <thead class="bg-slate-50 border-b border-slate-200">
                                        <tr>
                                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">訂單編號</th>
                                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">客戶</th>
                                            <th class="px-4 py-3 text-center text-xs font-semibold text-slate-600 uppercase tracking-wider">數量</th>
                                            <th class="px-4 py-3 text-center text-xs font-semibold text-green-600 uppercase tracking-wider bg-green-50/50">已分配</th>
                                            <th class="px-4 py-3 text-center text-xs font-semibold text-slate-600 uppercase tracking-wider">狀態</th>
                                            <th class="px-4 py-3 text-center text-xs font-semibold text-slate-600 uppercase tracking-wider">操作</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-slate-100">
                                        <tr v-for="order in paginatedBuyers" :key="order.order_item_id" class="hover:bg-slate-50">
                                            <td class="px-4 py-4">
                                                <button @click="goToOrderDetail(order.order_id)" class="text-sm font-bold text-primary hover:text-blue-700 hover:underline transition">#{{ order.order_id }}</button>
                                            </td>
                                            <td class="px-4 py-4">
                                                <div class="text-sm font-medium text-slate-900">{{ order.customer_name }}</div>
                                            </td>
                                            <td class="px-4 py-4 text-center">
                                                <div class="text-xl font-bold text-slate-900">{{ order.quantity }}</div>
                                            </td>
                                            <td class="px-4 py-4 text-center bg-green-50/30">
                                                <div class="text-xl font-bold text-green-600">{{ order.allocated_quantity }}</div>
                                                <div v-if="order.shipped_quantity > 0" class="text-xs text-blue-500 mt-0.5">(已出貨 {{ order.shipped_quantity }})</div>
                                            </td>
                                            <td class="px-4 py-4 text-center">
                                                <span :class="getStatusClass(order.status)" class="px-3 py-1.5 text-sm rounded-full font-medium">
                                                    {{ getStatusText(order.status) }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-4 text-center">
                                                <button
                                                    v-if="order.pending_quantity > 0 && !['shipped', 'completed'].includes(order.shipping_status)"
                                                    @click="allocateOrder(order)"
                                                    :disabled="allocatingOrderItemId === order.order_item_id"
                                                    class="px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white text-sm font-medium rounded-lg transition shadow-sm disabled:opacity-50 disabled:cursor-not-allowed">
                                                    {{ allocatingOrderItemId === order.order_item_id ? '分配中...' : '一鍵分配' }}
                                                </button>
                                                <span v-else-if="['shipped', 'completed'].includes(order.shipping_status)" class="text-sm text-blue-600 font-medium inline-flex items-center gap-1">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                                    已出貨
                                                </span>
                                                <span v-else class="text-sm text-green-600 font-medium inline-flex items-center gap-1">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                                    完成
                                                </span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                                <!-- 手機版卡片（採用分配頁風格） -->
                                <div v-if="!buyersLoading && filteredBuyers.length > 0" class="md:hidden divide-y divide-slate-100">
                                    <div v-for="order in paginatedBuyers" :key="order.order_item_id" class="px-3 py-2.5 hover:bg-slate-50 transition">
                                        <div class="flex items-center justify-between gap-2">
                                            <!-- 左側：編號+客戶+統計 -->
                                            <div class="flex-1 min-w-0" @click="goToOrderDetail(order.order_id)">
                                                <div class="flex items-center gap-2 mb-1">
                                                    <button @click="goToOrderDetail(order.order_id)" class="font-bold text-primary hover:text-blue-700 hover:underline transition">#{{ order.order_id }}</button>
                                                    <span class="text-sm text-slate-500 truncate">{{ order.customer_name }}</span>
                                                </div>
                                                <div class="flex items-center gap-3 text-xs">
                                                    <span><span class="text-slate-400">數量</span> <span class="font-bold text-slate-700">{{ order.quantity }}</span></span>
                                                    <span><span class="text-slate-400">已配</span> <span class="font-bold text-green-600">{{ order.allocated_quantity }}</span></span>
                                                    <span><span class="text-slate-400">待配</span> <span class="font-bold text-amber-600">{{ order.pending_quantity }}</span></span>
                                                </div>
                                            </div>
                                            <!-- 右側：狀態與操作 -->
                                            <div class="flex flex-col items-end gap-1.5 shrink-0">
                                                <span :class="getStatusClass(order.status)" class="px-2 py-0.5 text-[10px] rounded-full font-medium">
                                                    {{ getStatusText(order.status) }}
                                                </span>
                                                <button
                                                    v-if="order.pending_quantity > 0 && !['shipped', 'completed'].includes(order.shipping_status)"
                                                    @click.stop="allocateOrder(order)"
                                                    :disabled="allocatingOrderItemId === order.order_item_id"
                                                    class="px-2.5 py-1 bg-orange-500 hover:bg-orange-600 text-white text-xs font-medium rounded-lg transition shadow-sm disabled:opacity-50 disabled:cursor-not-allowed">
                                                    {{ allocatingOrderItemId === order.order_item_id ? '分配中...' : '一鍵分配' }}
                                                </button>
                                                <span v-else-if="['shipped', 'completed'].includes(order.shipping_status)" class="text-xs text-blue-600 font-medium inline-flex items-center gap-0.5">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                                    已出貨
                                                </span>
                                                <span v-else class="text-xs text-green-600 font-medium inline-flex items-center gap-0.5">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                                    完成
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <!-- 分頁控制 -->
                                <div v-if="filteredBuyers.length > 0" class="px-4 py-3 border-t border-slate-200 bg-slate-50 flex flex-col sm:flex-row items-center justify-between gap-3">
                                    <div class="text-sm text-slate-700 text-center sm:text-left">
                                        顯示 <span class="font-medium">{{ buyersStartIndex }}</span> 到 <span class="font-medium">{{ buyersEndIndex }}</span> 筆，共 <span class="font-medium">{{ filteredBuyers.length }}</span> 筆
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <!-- 每頁筆數選擇 -->
                                        <select v-model.number="buyersPerPage" @change="buyersHandlePerPageChange" class="px-3 py-1.5 border border-slate-300 rounded-lg text-sm focus:border-primary focus:ring-1 focus:ring-primary outline-none">
                                            <option v-for="option in buyersPerPageOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                                        </select>
                                        <!-- 頁碼導航 -->
                                        <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                                            <button @click="buyersGoToPage(buyersCurrentPage - 1)" :disabled="buyersCurrentPage === 1"
                                                class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-slate-300 bg-white text-sm font-medium text-slate-500 hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed">
                                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                                            </button>
                                            <button v-for="p in buyersVisiblePages" :key="p" @click="buyersGoToPage(p)"
                                                :class="[p === buyersCurrentPage ? 'z-10 bg-blue-50 border-primary text-primary' : 'bg-white border-slate-300 text-slate-500 hover:bg-slate-50', 'relative inline-flex items-center px-4 py-2 border text-sm font-medium']">
                                                {{ p }}
                                            </button>
                                            <button @click="buyersGoToPage(buyersCurrentPage + 1)" :disabled="buyersCurrentPage >= buyersTotalPages"
                                                class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-slate-300 bg-white text-sm font-medium text-slate-500 hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed">
                                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                                            </button>
                                        </nav>
                                    </div>
                                </div>
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
                                        <div class="bg-primary/10 rounded-lg py-2 px-1">
                                            <div class="text-[10px] text-primary">可分配</div>
                                            <div class="font-bold text-primary">{{ Math.max(0, (selectedProduct?.purchased || 0) - (selectedProduct?.allocated || 0)) }}</div>
                                        </div>
                                        <div class="bg-blue-50 rounded-lg py-2 px-1">
                                            <div class="text-[10px] text-blue-600">已分配</div>
                                            <div class="font-bold text-blue-700">{{ selectedProduct?.allocated || 0 }}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- Orders List for Allocation -->
                            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden relative">
                                <!-- 標題列與上方確認分配按鈕 -->
                                <div class="p-3 border-b border-slate-200 bg-slate-50">
                                    <div class="flex items-center justify-between mb-2">
                                        <h3 class="font-bold text-slate-800">待分配訂單 <span class="text-sm font-normal text-slate-500">({{ filteredProductOrders.length }}/{{ productOrders.length }} 筆)</span></h3>
                                        <!-- 上方分配按鈕（精簡版） -->
                                        <button v-if="totalAllocation > 0" @click="handleSubPageSave" class="px-3 py-1.5 bg-primary text-white text-sm font-medium rounded-lg shadow-sm hover:bg-blue-700 transition flex items-center gap-1.5">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                                            分配 ({{ totalAllocation }})
                                        </button>
                                        <span v-else class="px-3 py-1.5 bg-slate-200 text-slate-500 text-sm font-medium rounded-lg flex items-center gap-1.5">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                                            分配 (0)
                                        </span>
                                    </div>
                                    <!-- 搜尋框 -->
                                    <div class="relative">
                                        <input type="text" v-model="allocationSearch" placeholder="搜尋訂單編號或客戶名稱..." class="w-full pl-9 pr-4 py-2 text-sm border border-slate-300 rounded-lg focus:border-primary focus:ring-1 focus:ring-primary outline-none">
                                        <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                                        <button v-if="allocationSearch" @click="allocationSearch = ''" class="absolute right-3 top-2.5 text-slate-400 hover:text-slate-600">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                        </button>
                                    </div>
                                </div>
                                <div class="overflow-auto">
                                    <div v-if="allocationLoading" class="flex flex-col items-center justify-center py-12">
                                        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary mb-2"></div>
                                        <p class="text-slate-500">載入訂單中...</p>
                                    </div>
                                    <div v-else-if="filteredProductOrders.length === 0" class="flex flex-col items-center justify-center py-12 text-slate-500">
                                        <svg class="w-12 h-12 mb-2 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>
                                        <p v-if="allocationSearch">找不到符合「{{ allocationSearch }}」的訂單</p>
                                        <p v-else>目前沒有此商品的待處理訂單</p>
                                    </div>
                                    <!-- 訂單列表（使用分頁後的資料） -->
                                    <div v-else class="divide-y divide-slate-100">
                                        <div v-for="order in paginatedProductOrders" :key="order.order_id" class="px-3 py-2.5 hover:bg-slate-50 transition">
                                            <div class="flex items-center justify-between gap-2">
                                                <!-- 左側：編號+客戶+統計 -->
                                                <div class="flex-1 min-w-0">
                                                    <div class="flex items-center gap-2 mb-1">
                                                        <button @click="goToOrderDetail(order.order_id)" class="font-bold text-primary hover:text-blue-700 hover:underline transition">#{{ order.order_id }}</button>
                                                        <span class="text-sm text-slate-500 truncate">{{ order.customer || '訪客' }}</span>
                                                    </div>
                                                    <div class="flex items-center gap-3 text-xs">
                                                        <span><span class="text-slate-400">下單</span> <span class="font-bold text-slate-700">{{ order.required || order.quantity }}</span></span>
                                                        <span><span class="text-slate-400">已配</span> <span class="font-bold text-blue-600">{{ order.already_allocated || 0 }}</span></span>
                                                        <span><span class="text-slate-400">待配</span> <span class="font-bold text-red-600">{{ order.pending || ((order.required || order.quantity) - (order.already_allocated || 0)) }}</span></span>
                                                    </div>
                                                </div>
                                                <!-- 右側：輸入框 -->
                                                <input type="number" v-model.number="order.allocated" min="0" :max="order.pending || (order.required - (order.already_allocated || 0))" class="w-16 px-2 py-1.5 border border-slate-300 rounded-lg text-center text-sm font-bold focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none shrink-0">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <!-- 分頁控制 -->
                                <div v-if="filteredProductOrders.length > 0" class="px-4 py-3 border-t border-slate-200 bg-slate-50 flex flex-col sm:flex-row items-center justify-between gap-3">
                                    <div class="text-sm text-slate-700 text-center sm:text-left">
                                        顯示 <span class="font-medium">{{ allocationStartIndex }}</span> 到 <span class="font-medium">{{ allocationEndIndex }}</span> 筆，共 <span class="font-medium">{{ filteredProductOrders.length }}</span> 筆
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <!-- 每頁筆數選擇 -->
                                        <select v-model.number="allocationPerPage" @change="allocationHandlePerPageChange" class="px-3 py-1.5 border border-slate-300 rounded-lg text-sm focus:border-primary focus:ring-1 focus:ring-primary outline-none">
                                            <option v-for="option in allocationPerPageOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                                        </select>
                                        <!-- 頁碼導航 -->
                                        <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                                            <button @click="allocationGoToPage(allocationCurrentPage - 1)" :disabled="allocationCurrentPage === 1"
                                                class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-slate-300 bg-white text-sm font-medium text-slate-500 hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed">
                                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                                            </button>
                                            <button v-for="p in allocationVisiblePages" :key="p" @click="allocationGoToPage(p)"
                                                :class="[p === allocationCurrentPage ? 'z-10 bg-blue-50 border-primary text-primary' : 'bg-white border-slate-300 text-slate-500 hover:bg-slate-50', 'relative inline-flex items-center px-4 py-2 border text-sm font-medium']">
                                                {{ p }}
                                            </button>
                                            <button @click="allocationGoToPage(allocationCurrentPage + 1)" :disabled="allocationCurrentPage >= allocationTotalPages"
                                                class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-slate-300 bg-white text-sm font-medium text-slate-500 hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed">
                                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                                            </button>
                                        </nav>
                                    </div>
                                </div>
                                <!-- 總計與下方確認分配按鈕 -->
                                <div v-if="productOrders.length > 0" class="px-3 py-3 bg-white border-t border-slate-200">
                                    <div class="flex items-center justify-between mb-3">
                                        <span class="text-slate-600">總計分配：</span>
                                        <span class="text-lg font-bold text-primary">{{ totalAllocation }}</span>
                                    </div>
                                    <button @click="handleSubPageSave" :disabled="totalAllocation === 0" class="w-full py-3 bg-primary text-white text-base font-bold rounded-lg shadow-lg hover:bg-blue-700 transition flex items-center justify-center gap-2 disabled:bg-slate-300 disabled:cursor-not-allowed disabled:shadow-none">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                        確認分配 {{ totalAllocation }} 件
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <!-- </transition> -->
        </div>
    </main>
    
    <!-- Image Modal -->
    <div v-if="showImageModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm" @click.self="!imageUploading && closeImageModal()">
        <div class="bg-white rounded-2xl p-6 shadow-xl max-w-lg w-full mx-4">
            <!-- Header with close button -->
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-bold">編輯圖片</h2>
                <button v-if="!imageUploading" @click="closeImageModal" class="p-2 hover:bg-slate-100 rounded-lg transition">
                    <svg class="w-5 h-5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <!-- Current Image Preview -->
            <img v-if="currentImage" :src="currentImage" class="w-full h-48 object-cover rounded-lg mb-4">

            <!-- Upload Area -->
            <div v-if="!imageUploading" @click="triggerFileInput" class="products-upload-area border-2 border-dashed border-slate-300 p-8 text-center cursor-pointer hover:border-primary hover:bg-primary/5 rounded-lg transition">
                <svg class="w-10 h-10 mx-auto mb-2 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                <p class="text-slate-600 font-medium">點擊上傳圖片</p>
                <p class="text-xs text-slate-400 mt-1">支援 JPG、PNG、GIF</p>
            </div>

            <!-- Uploading State -->
            <div v-else class="products-upload-loading border-2 border-primary/30 bg-primary/5 p-8 text-center rounded-lg">
                <div class="products-upload-spinner w-10 h-10 mx-auto mb-3 border-4 border-primary/30 border-t-primary rounded-full animate-spin"></div>
                <p class="text-primary font-medium">圖片上傳中...</p>
                <p class="text-xs text-slate-500 mt-1">請勿關閉此視窗</p>
            </div>

            <!-- Error Message -->
            <div v-if="imageError" class="mt-4 p-3 bg-red-50 border border-red-200 rounded-lg text-red-600 text-sm">
                {{ imageError }}
            </div>

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


<!-- Products Page Component -->
<script>
// Set wpNonce for component
window.buygoWpNonce = '<?php echo wp_create_nonce("wp_rest"); ?>';
</script>
<script src="<?php echo esc_url(plugins_url('js/components/ProductsPage.js', dirname(__FILE__))); ?>"></script>


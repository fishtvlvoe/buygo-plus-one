<?php
/**
 * Smart Search Box Component - 可重用的智慧搜尋框模組
 * 
 * 用法：
 * components: {
 *   'smart-search-box': BuyGoSmartSearchBox
 * },
 * template: `
 *   <smart-search-box
 *     api-endpoint="/wp-json/buygo-plus-one/v1/products"
 *     :search-fields="['name', 'id']"
 *     placeholder="搜尋商品..."
 *     @select="handleSelect"
 *     @search="handleSearch"
 *   />
 * `
 */
?>
<script>
const BuyGoSmartSearchBox = {
    props: {
        // API 端點（必填）
        apiEndpoint: {
            type: String,
            required: true
        },
        // 要搜尋的欄位（必填）
        searchFields: {
            type: Array,
            required: true,
            default: () => ['name']
        },
        // Placeholder 文字
        placeholder: {
            type: String,
            default: '搜尋...'
        },
        // 顯示項目的標題欄位名稱
        displayField: {
            type: String,
            default: 'name'
        },
        // 顯示項目的副標題欄位名稱（可選）
        displaySubField: {
            type: String,
            default: null
        },
        // 是否顯示圖片
        showImage: {
            type: Boolean,
            default: true
        },
        // 圖片欄位名稱
        imageField: {
            type: String,
            default: 'image'
        },
        // 是否顯示狀態標籤
        showStatus: {
            type: Boolean,
            default: true
        },
        // 狀態欄位名稱
        statusField: {
            type: String,
            default: 'status'
        },
        // 最多顯示幾筆建議
        maxSuggestions: {
            type: Number,
            default: 5
        },
        // 是否顯示幣別切換
        showCurrencyToggle: {
            type: Boolean,
            default: false
        },
        // 幣別切換的初始狀態（JPY 或 TWD）
        defaultCurrency: {
            type: String,
            default: 'JPY'
        }
    },

    template: `
        <div class="mb-6 bg-slate-50 rounded-xl p-4 border border-slate-200">
            <div class="buygo-smart-search relative">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                    <svg class="h-4 w-4 text-slate-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
                <input 
                    type="text" 
                    v-model="searchQuery"
                    @input="handleInput"
                    @focus="handleFocus"
                    @blur="handleBlur"
                    :class="showCurrencyToggle ? 'block w-full rounded-xl border-slate-200 pl-10 pr-32 focus:border-primary focus:ring-primary focus:ring-2 text-sm py-3 placeholder-slate-400 shadow-sm bg-white transition' : 'block w-full rounded-xl border-slate-200 pl-10 pr-10 focus:border-primary focus:ring-primary focus:ring-2 text-sm py-3 placeholder-slate-400 shadow-sm bg-white transition'" 
                    :placeholder="placeholder">
                
                <!-- Clear Button -->
                <button 
                    v-if="searchQuery"
                    @click="clearSearch"
                    :class="showCurrencyToggle ? 'absolute inset-y-0 right-24 flex items-center pr-3 text-slate-400 hover:text-slate-600 transition' : 'absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400 hover:text-slate-600 transition'">
                    <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                    </svg>
                </button>
                
                <!-- 幣別切換（右側） -->
                <div v-if="showCurrencyToggle" class="absolute inset-y-0 right-2 flex items-center gap-2">
                    <span class="text-xs font-bold text-slate-700">{{ currentCurrency === 'JPY' ? '日幣' : '台幣' }}</span>
                    <label class="toggle-switch transform scale-90">
                        <input type="checkbox" v-model="isJPY" @change="handleCurrencyChange">
                        <span class="toggle-slider"></span>
                    </label>
                </div>

            <!-- Suggestions Dropdown -->
            <div 
                v-show="showSuggestions && suggestions.length > 0"
                class="absolute z-10 mt-1 w-full bg-white shadow-xl max-h-60 rounded-xl py-1 text-base ring-1 ring-slate-200 overflow-auto focus:outline-none sm:text-sm">
                
                <!-- Title -->
                <div class="px-3 py-2 text-xs font-semibold text-slate-500 uppercase tracking-wider bg-slate-50">
                    {{ searchQuery ? '搜尋結果' : '最近項目' }}
                </div>
                
                <!-- Suggestions List -->
                <ul class="divide-y divide-gray-100">
                    <li 
                        v-for="item in suggestions"
                        :key="'search-' + item.id"
                        @mousedown="selectItem(item)"
                        class="cursor-pointer hover:bg-blue-50 px-4 py-2 flex justify-between items-center group transition">
                        <div class="flex items-center gap-2">
                            <!-- Image -->
                            <img 
                                v-if="showImage && item[imageField]" 
                                :src="item[imageField]" 
                                class="h-8 w-8 rounded object-cover">
                            
                            <!-- Text -->
                            <div>
                                <div class="font-medium text-slate-900 group-hover:text-primary">
                                    {{ item[displayField] || '未命名' }}
                                </div>
                                <div v-if="displaySubField && item[displaySubField]" class="text-xs text-slate-500">
                                    {{ item[displaySubField] }}
                                </div>
                            </div>
                        </div>
                        
                        <!-- Status -->
                        <span 
                            v-if="showStatus && item[statusField]"
                            :class="getStatusClass(item[statusField])" 
                            class="text-xs px-2 py-0.5 rounded-full border">
                            {{ formatStatus(item[statusField]) }}
                        </span>
                    </li>
                </ul>
            </div>
        </div>
    `,

    data() {
        return {
            searchQuery: '',
            searchTimeout: null,
            showSuggestions: false,
            searchLoading: false,
            suggestions: [],
            currentCurrency: this.defaultCurrency,
            isJPY: this.defaultCurrency === 'JPY'
        };
    },

    methods: {
        handleInput() {
            // 通知父組件搜尋內容變化
            this.$emit('search', this.searchQuery);

            // 載入建議
            if (this.searchQuery.length > 0) {
                this.loadSuggestions();
            } else {
                this.showSuggestions = false;
                this.suggestions = [];
            }

            // 防抖處理
            clearTimeout(this.searchTimeout);
            this.searchTimeout = setTimeout(() => {
                this.$emit('change', this.searchQuery);
            }, 500);
        },

        handleFocus() {
            this.showSuggestions = true;
            if (this.searchQuery.length > 0) {
                this.loadSuggestions();
            } else {
                this.loadRecentItems();
            }
        },

        handleBlur() {
            setTimeout(() => {
                this.showSuggestions = false;
            }, 200);
        },

        async loadRecentItems() {
            this.searchLoading = true;
            try {
                const params = new URLSearchParams({
                    page: 1,
                    per_page: this.maxSuggestions,
                    status: 'all',
                });

                const response = await fetch(`${this.apiEndpoint}?${params}`, {
                    credentials: 'include',
                });

                if (!response.ok) throw new Error(`HTTP ${response.status}`);

                const result = await response.json();
                if (result.success && result.data) {
                    this.suggestions = result.data.slice(0, this.maxSuggestions);
                } else {
                    this.suggestions = [];
                }
            } catch (error) {
                console.error('Failed to load recent items:', error);
                this.suggestions = [];
            } finally {
                this.searchLoading = false;
            }
        },

        async loadSuggestions() {
            if (this.searchQuery.length === 0) {
                this.loadRecentItems();
                return;
            }

            this.searchLoading = true;
            this.showSuggestions = true;

            try {
                const params = new URLSearchParams({
                    page: 1,
                    per_page: this.maxSuggestions,
                    search: this.searchQuery,
                    status: 'all',
                });

                const response = await fetch(`${this.apiEndpoint}?${params}`, {
                    credentials: 'include',
                });

                if (!response.ok) throw new Error(`HTTP ${response.status}`);

                const result = await response.json();
                if (result.success && result.data) {
                    this.suggestions = result.data.slice(0, this.maxSuggestions);
                } else {
                    this.suggestions = [];
                }
            } catch (error) {
                console.error('Failed to load suggestions:', error);
                this.suggestions = [];
            } finally {
                this.searchLoading = false;
            }
        },

        selectItem(item) {
            this.searchQuery = item[this.displayField];
            this.showSuggestions = false;
            this.$emit('select', item);
        },

        clearSearch() {
            this.searchQuery = '';
            this.showSuggestions = false;
            this.suggestions = [];
            this.$emit('clear');
            this.$emit('search', '');
        },

        handleCurrencyChange() {
            this.currentCurrency = this.isJPY ? 'JPY' : 'TWD';
            this.$emit('currency-change', this.currentCurrency);
        },

        formatStatus(status) {
            const statusMap = {
                'published': '已上架',
                'private': '已下架',
                'publish': '已上架',
                'draft': '草稿',
                'pending': '待審核'
            };
            return statusMap[status] || status;
        },

        getStatusClass(status) {
            const classMap = {
                'published': 'bg-green-50 text-green-700 border-green-200',
                'private': 'bg-gray-50 text-gray-700 border-gray-200',
                'publish': 'bg-green-50 text-green-700 border-green-200',
                'draft': 'bg-orange-50 text-orange-700 border-orange-200',
                'pending': 'bg-yellow-50 text-yellow-700 border-yellow-200'
            };
            return classMap[status] || 'bg-gray-50 text-gray-700 border-gray-200';
        }
    }
};
</script>
<style>
/* iOS Toggle Switch */
.buygo-smart-search .toggle-switch {
    position: relative;
    display: inline-block;
    width: 50px;
    height: 26px;
}
.buygo-smart-search .toggle-switch input {
    opacity: 0;
    width: 0;
    height: 0;
    position: absolute;
}
.buygo-smart-search .toggle-slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #CBD5E1;
    transition: .4s;
    border-radius: 34px;
}
.buygo-smart-search .toggle-slider:before {
    position: absolute;
    content: "";
    height: 20px;
    width: 20px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: .4s;
    border-radius: 50%;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}
.buygo-smart-search input:checked + .toggle-slider {
    background-color: #10B981;
}
.buygo-smart-search input:checked + .toggle-slider:before {
    transform: translateX(24px);
}
</style>

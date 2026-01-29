/**
 * BuyGo Plus One - 獨立 Header Vue 元件
 *
 * 用途：提供完整的 Header 功能，包含搜尋、通知、幣別切換
 * 特色：自給自足，不需要頁面提供 data 或 methods
 *
 * 使用方式：
 * 1. 在 template.php 註冊：app.component('page-header-component', PageHeaderComponent);
 * 2. 在頁面使用：<page-header-component :title="..." :breadcrumb="..." :show-currency-toggle="..." />
 *
 * @version 2.0.0
 * @author BuyGo Team
 */

(function() {
    'use strict';

    window.PageHeaderComponent = {
        props: {
            title: {
                type: String,
                default: '頁面'
            },
            breadcrumb: {
                type: String,
                default: '<a href="/buygo-portal/dashboard" class="active">首頁</a>'
            },
            showCurrencyToggle: {
                type: Boolean,
                default: true  // 預設顯示幣別切換（所有頁面都可能需要）
            }
        },

        setup() {
            const { ref, computed, watch, onMounted } = Vue;

            // 使用 useCurrency Composable
            const {
                systemCurrency,
                setSystemCurrency,
                getCurrencySymbol
            } = useCurrency();

            // 全域搜尋
            const globalSearchQuery = ref('');
            const showMobileSearch = ref(false);
            const searchSuggestions = ref([]);
            const showSuggestions = ref(false);
            const isLoadingSuggestions = ref(false);
            const searchHistory = ref([]);
            const selectedSuggestionIndex = ref(-1);

            // 通知
            const unreadCount = ref(0);

            // 幣別選項（顯示完整名稱避免混淆）
            const currencyOptions = ref([
                { code: 'TWD', label: '台幣 (NT$)', symbol: 'NT$' },
                { code: 'JPY', label: '日幣 (¥)', symbol: '¥' },
                { code: 'USD', label: '美金 ($)', symbol: '$' },
                { code: 'CNY', label: '人民幣 (¥)', symbol: '¥' }
            ]);

            // 類型 icon 對照表
            const typeIcons = {
                order: '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>',
                product: '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>',
                customer: '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>',
                shipment: '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path></svg>'
            };

            // 從 localStorage 載入搜尋歷史並註冊事件監聽
            onMounted(() => {
                try {
                    const saved = localStorage.getItem('buygo_search_history');
                    if (saved) {
                        searchHistory.value = JSON.parse(saved);
                    }
                } catch (e) {
                    console.error('[PageHeader] 載入搜尋歷史失敗:', e);
                }

                // 註冊點擊外部關閉建議框的事件
                document.addEventListener('click', handleClickOutside);
            });

            // 清理事件監聽器
            const { onUnmounted } = Vue;
            onUnmounted(() => {
                document.removeEventListener('click', handleClickOutside);
            });

            // 點擊外部關閉建議框
            const handleClickOutside = (event) => {
                const searchContainer = document.querySelector('.global-search');
                if (searchContainer && !searchContainer.contains(event.target)) {
                    showSuggestions.value = false;
                    selectedSuggestionIndex.value = -1;
                }
            };

            // 儲存搜尋歷史到 localStorage
            const saveSearchHistory = () => {
                try {
                    localStorage.setItem('buygo_search_history', JSON.stringify(searchHistory.value));
                } catch (e) {
                    console.error('[PageHeader] 儲存搜尋歷史失敗:', e);
                }
            };

            return {
                // 狀態
                globalSearchQuery,
                showMobileSearch,
                searchSuggestions,
                showSuggestions,
                isLoadingSuggestions,
                searchHistory,
                selectedSuggestionIndex,
                unreadCount,
                systemCurrency,
                currencyOptions,
                typeIcons,

                // 方法
                setSystemCurrency,
                getCurrencySymbol,
                saveSearchHistory
            };
        },

        methods: {
            /**
             * 處理全域搜尋輸入（即時搜尋/建議）
             * 使用 debounce 避免過度請求
             */
            handleGlobalSearch() {
                const query = this.globalSearchQuery.trim();

                // 清空時隱藏建議框
                if (!query) {
                    this.showSuggestions = false;
                    this.searchSuggestions = [];
                    return;
                }

                // Debounce: 取消之前的計時器
                if (this.searchDebounceTimer) {
                    clearTimeout(this.searchDebounceTimer);
                }

                // 300ms 後才執行搜尋
                this.searchDebounceTimer = setTimeout(() => {
                    this.fetchSearchSuggestions(query);
                }, 300);
            },

            /**
             * 取得搜尋建議
             */
            async fetchSearchSuggestions(query) {
                this.isLoadingSuggestions = true;

                try {
                    const response = await fetch(
                        `/wp-json/buygo-plus-one/v1/global-search?query=${encodeURIComponent(query)}&per_page=10`,
                        {
                            headers: {
                                'X-WP-Nonce': window.buygoWpNonce
                            }
                        }
                    );

                    if (!response.ok) {
                        throw new Error('搜尋建議請求失敗');
                    }

                    const result = await response.json();

                    if (result.success && result.data) {
                        this.searchSuggestions = result.data;
                        this.showSuggestions = true;
                        this.selectedSuggestionIndex = -1;
                    }

                } catch (error) {
                    console.error('[PageHeader] 取得搜尋建議失敗:', error);
                } finally {
                    this.isLoadingSuggestions = false;
                }
            },

            /**
             * Enter 鍵跳轉搜尋結果頁面
             */
            goToSearchPage() {
                const query = this.globalSearchQuery.trim();
                if (query) {
                    // 加入搜尋歷史
                    this.addToSearchHistory(query);

                    const encodedQuery = encodeURIComponent(query);
                    window.location.href = `/buygo-portal/search?q=${encodedQuery}`;
                }
            },

            /**
             * 選擇建議項目
             */
            selectSuggestion(suggestion) {
                // 加入搜尋歷史
                this.addToSearchHistory(this.globalSearchQuery.trim());

                // 根據類型導航到對應頁面
                this.navigateToDetail(suggestion);
            },

            /**
             * 根據項目類型導航到詳情頁
             */
            navigateToDetail(item) {
                let url = '';

                switch (item.type) {
                    case 'order':
                        url = `/buygo-portal/orders?id=${item.id}`;
                        break;
                    case 'product':
                        url = `/buygo-portal/products?id=${item.id}`;
                        break;
                    case 'customer':
                        url = `/buygo-portal/customers?id=${item.id}`;
                        break;
                    case 'shipment':
                        url = `/buygo-portal/shipment-details?id=${item.id}`;
                        break;
                    default:
                        // 預設跳轉搜尋頁
                        url = `/buygo-portal/search?q=${encodeURIComponent(this.globalSearchQuery)}`;
                }

                window.location.href = url;
            },

            /**
             * 加入搜尋歷史
             */
            addToSearchHistory(query) {
                if (!query) return;

                // 移除重複項目
                const index = this.searchHistory.indexOf(query);
                if (index > -1) {
                    this.searchHistory.splice(index, 1);
                }

                // 加到最前面
                this.searchHistory.unshift(query);

                // 只保留最近 10 筆
                if (this.searchHistory.length > 10) {
                    this.searchHistory = this.searchHistory.slice(0, 10);
                }

                // 儲存到 localStorage
                this.saveSearchHistory();
            },

            /**
             * 點擊搜尋歷史項目
             */
            selectHistoryItem(query) {
                this.globalSearchQuery = query;
                this.goToSearchPage();
            },

            /**
             * 處理搜尋框 focus 事件
             */
            handleGlobalSearchFocus() {
                // 如果有搜尋文字或有搜尋歷史,顯示建議框
                if (this.globalSearchQuery || this.searchHistory.length > 0) {
                    this.showSuggestions = true;
                }
            },

            /**
             * 鍵盤導航（上下鍵選擇建議）
             */
            handleKeydown(event) {
                // Enter 鍵和 Escape 鍵在任何情況下都要處理
                if (event.key === 'Enter') {
                    event.preventDefault();
                    if (this.showSuggestions && this.selectedSuggestionIndex >= 0 && this.searchSuggestions.length > 0) {
                        this.selectSuggestion(this.searchSuggestions[this.selectedSuggestionIndex]);
                    } else {
                        this.goToSearchPage();
                    }
                    return;
                }

                if (event.key === 'Escape') {
                    this.showSuggestions = false;
                    this.selectedSuggestionIndex = -1;
                    return;
                }

                // 上下鍵導航只在有建議時才處理
                if (!this.showSuggestions || this.searchSuggestions.length === 0) {
                    return;
                }

                switch (event.key) {
                    case 'ArrowDown':
                        event.preventDefault();
                        this.selectedSuggestionIndex =
                            (this.selectedSuggestionIndex + 1) % this.searchSuggestions.length;
                        break;
                    case 'ArrowUp':
                        event.preventDefault();
                        this.selectedSuggestionIndex =
                            this.selectedSuggestionIndex <= 0
                                ? this.searchSuggestions.length - 1
                                : this.selectedSuggestionIndex - 1;
                        break;
                }
            },

            /**
             * 取得類型 icon HTML
             */
            getTypeIcon(type) {
                return this.typeIcons[type] || this.typeIcons.order;
            },

            /**
             * 切換手機版搜尋面板
             */
            toggleMobileSearch() {
                this.showMobileSearch = !this.showMobileSearch;
                console.log('[PageHeader] 手機版搜尋:', this.showMobileSearch);
                // TODO: 顯示全螢幕搜尋面板
            },

            /**
             * 切換通知面板
             */
            toggleNotifications() {
                console.log('[PageHeader] 切換通知面板');
                // TODO: 顯示通知下拉面板
                // TODO: 標記通知為已讀
            },

            /**
             * 處理幣別切換
             */
            handleCurrencyChange(event) {
                const newCurrency = event.target.value;

                // 更新全域幣別設定
                this.setSystemCurrency(newCurrency);

                console.log('[PageHeader] 切換幣別:', newCurrency);

                // 觸發事件讓父元件知道幣別改變了
                this.$emit('currency-changed', newCurrency);
            }
        },

        template: `
<!-- ============================================ -->
<!-- 頁首部分 (獨立 Header 元件) -->
<!-- ============================================ -->
<header class="page-header">
    <div class="flex items-center gap-3 md:gap-4 overflow-hidden flex-1">
        <div class="flex flex-col overflow-hidden min-w-0 pl-12 md:pl-0">
            <h1 class="page-header-title">{{ title }}</h1>
            <nav class="page-header-breadcrumb" v-html="breadcrumb"></nav>
        </div>
    </div>

    <!-- 右側操作區 -->
    <div class="flex items-center gap-2 md:gap-3 shrink-0">
        <!-- 手機版搜尋 icon (640px以下顯示) -->
        <button class="notification-bell sm:hidden" @click="toggleMobileSearch" title="搜尋">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
            </svg>
        </button>

        <!-- 桌面版全域搜尋框 (640px以上顯示) -->
        <div class="global-search" ref="searchContainer">
            <input type="text"
                   placeholder="搜尋訂單、商品、客戶、出貨單..."
                   v-model="globalSearchQuery"
                   @input="handleGlobalSearch"
                   @keydown="handleKeydown"
                   @focus="handleGlobalSearchFocus">
            <svg class="search-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
            </svg>

            <!-- 搜尋建議下拉框 -->
            <div v-if="showSuggestions" class="search-suggestions">
                <!-- 載入中 -->
                <div v-if="isLoadingSuggestions" class="suggestion-item loading">
                    <span class="loading-spinner"></span>
                    <span>搜尋中...</span>
                </div>

                <!-- 搜尋結果建議 -->
                <template v-else-if="searchSuggestions.length > 0">
                    <div class="suggestions-header">搜尋結果</div>
                    <div v-for="(suggestion, index) in searchSuggestions"
                         :key="suggestion.id + '-' + suggestion.type"
                         :class="['suggestion-item', { 'selected': index === selectedSuggestionIndex }]"
                         @click="selectSuggestion(suggestion)"
                         @mouseenter="selectedSuggestionIndex = index">
                        <span class="suggestion-icon" v-html="getTypeIcon(suggestion.type)"></span>
                        <div class="suggestion-content">
                            <div class="suggestion-title" v-html="suggestion.title"></div>
                            <div class="suggestion-meta">{{ suggestion.meta }}</div>
                        </div>
                        <span class="suggestion-type">{{ suggestion.type_label }}</span>
                    </div>
                </template>

                <!-- 搜尋歷史 -->
                <template v-else-if="!globalSearchQuery && searchHistory.length > 0">
                    <div class="suggestions-header">最近搜尋</div>
                    <div v-for="(historyItem, index) in searchHistory"
                         :key="'history-' + index"
                         class="suggestion-item history-item"
                         @click="selectHistoryItem(historyItem)">
                        <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <span class="history-text">{{ historyItem }}</span>
                    </div>
                </template>

                <!-- 無結果 -->
                <div v-else-if="globalSearchQuery && searchSuggestions.length === 0" class="suggestion-item no-results">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span>找不到相關結果</span>
                </div>
            </div>
        </div>

        <!-- 幣別切換下拉選單 -->
        <select v-if="showCurrencyToggle"
                class="currency-select"
                v-model="systemCurrency"
                @change="handleCurrencyChange"
                title="選擇幣別">
            <option v-for="option in currencyOptions"
                    :key="option.code"
                    :value="option.code">
                {{ option.label }}
            </option>
        </select>

        <!-- 通知鈴鐺 -->
        <button class="notification-bell" @click="toggleNotifications" title="通知">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
            </svg>
            <span v-if="unreadCount > 0" class="notification-badge">{{ unreadCount }}</span>
        </button>
    </div>
</header>
<!-- 結束:頁首部分 -->
        `
    };

    console.log('[PageHeaderComponent] 已載入獨立 Header Vue 元件');
})();

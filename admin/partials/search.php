<?php
// 全域搜尋結果頁面
?>
<!-- Search Page Styles -->
<link rel="stylesheet" href="<?php echo esc_url(plugins_url('../css/search.css', __FILE__)); ?>?v=<?php echo time(); ?>" />

<?php
// 設定 Header 參數
$header_title = '搜尋結果';
$header_breadcrumb = '<a href="/buygo-portal/dashboard" class="">首頁</a>
<svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
<span class="active">搜尋</span>';
$show_currency_toggle = false;

// 載入共用 Header
ob_start();
include __DIR__ . '/header-component.php';
$header_html = ob_get_clean();

$search_component_template = <<<'HTML'
<main class="min-h-screen bg-slate-50">
HTML;

// 將 Header 加入模板
$search_component_template .= $header_html;

$search_component_template .= <<<'HTML'

    <!-- ============================================ -->
    <!-- 內容區域 -->
    <!-- ============================================ -->
    <div class="search-page-container">

        <!-- 搜尋 Header -->
        <div class="search-header">
            <div class="search-input-wrapper">
                <svg class="search-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                </svg>
                <input
                    type="text"
                    v-model="searchQuery"
                    @keyup.enter="performSearch"
                    placeholder="搜尋訂單、商品、客戶或出貨單..."
                    class="search-input"
                />
                <button v-if="searchQuery" @click="searchQuery = ''; performSearch()" class="clear-search-btn">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="w-5 h-5">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            <button @click="performSearch" class="search-btn">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                </svg>
                <span class="hidden sm:inline">搜尋</span>
            </button>
        </div>

        <!-- 主要內容區（左側過濾器 + 右側結果） -->
        <div class="search-content-grid">

            <!-- 左側：過濾器 -->
            <aside class="search-filters">
                <h3 class="filter-title">篩選條件</h3>

                <!-- 類型過濾 -->
                <div class="filter-group">
                    <label class="filter-label">類型</label>
                    <div class="filter-options">
                        <button
                            @click="applyFilter('type', 'all')"
                            :class="['filter-option', { active: filters.type === 'all' }]"
                        >
                            全部
                        </button>
                        <button
                            @click="applyFilter('type', 'order')"
                            :class="['filter-option', { active: filters.type === 'order' }]"
                        >
                            🛒 訂單
                        </button>
                        <button
                            @click="applyFilter('type', 'product')"
                            :class="['filter-option', { active: filters.type === 'product' }]"
                        >
                            📦 商品
                        </button>
                        <button
                            @click="applyFilter('type', 'customer')"
                            :class="['filter-option', { active: filters.type === 'customer' }]"
                        >
                            👤 客戶
                        </button>
                        <button
                            @click="applyFilter('type', 'shipment')"
                            :class="['filter-option', { active: filters.type === 'shipment' }]"
                        >
                            🚚 出貨單
                        </button>
                    </div>
                </div>

                <!-- 狀態過濾 -->
                <div class="filter-group">
                    <label class="filter-label">狀態</label>
                    <div class="filter-options">
                        <button
                            @click="applyFilter('status', 'all')"
                            :class="['filter-option', { active: filters.status === 'all' }]"
                        >
                            全部
                        </button>
                        <button
                            @click="applyFilter('status', 'pending')"
                            :class="['filter-option', { active: filters.status === 'pending' }]"
                        >
                            待處理
                        </button>
                        <button
                            @click="applyFilter('status', 'completed')"
                            :class="['filter-option', { active: filters.status === 'completed' }]"
                        >
                            已完成
                        </button>
                        <button
                            @click="applyFilter('status', 'cancelled')"
                            :class="['filter-option', { active: filters.status === 'cancelled' }]"
                        >
                            已取消
                        </button>
                    </div>
                </div>

                <!-- 日期範圍 -->
                <div class="filter-group">
                    <label class="filter-label">日期範圍</label>
                    <div class="date-inputs">
                        <input
                            type="date"
                            v-model="filters.dateFrom"
                            @change="performSearch"
                            class="date-input"
                            placeholder="開始日期"
                        />
                        <input
                            type="date"
                            v-model="filters.dateTo"
                            @change="performSearch"
                            class="date-input"
                            placeholder="結束日期"
                        />
                    </div>
                </div>

                <!-- 清除篩選 -->
                <button @click="clearFilters" class="clear-filters-btn">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                    </svg>
                    清除篩選
                </button>
            </aside>

            <!-- 右側：搜尋結果 -->
            <div class="search-results">

                <!-- 結果數量 -->
                <div v-if="!loading && searchQuery" class="results-header">
                    <p class="results-count">
                        找到 <strong>{{ pagination.total }}</strong> 個結果
                        <span v-if="searchQuery"> - 「{{ searchQuery }}」</span>
                    </p>
                </div>

                <!-- Loading 狀態 -->
                <div v-if="loading" class="results-loading">
                    <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary mx-auto"></div>
                    <p class="mt-4 text-slate-500">搜尋中...</p>
                </div>

                <!-- 錯誤狀態 -->
                <div v-else-if="error" class="results-error">
                    <svg class="error-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <p class="error-message">{{ error }}</p>
                    <button @click="performSearch" class="retry-btn">重試</button>
                </div>

                <!-- 空狀態（尚未搜尋） -->
                <div v-else-if="!searchQuery" class="results-empty">
                    <svg class="empty-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                    <h3 class="empty-title">開始搜尋</h3>
                    <p class="empty-description">輸入關鍵字搜尋訂單、商品、客戶或出貨單</p>
                    <ul class="empty-tips">
                        <li>• 支援訂單編號、商品名稱、客戶姓名、電話等</li>
                        <li>• 可使用篩選器縮小搜尋範圍</li>
                        <li>• 點擊結果可快速跳轉到詳情頁</li>
                    </ul>
                </div>

                <!-- 無結果 -->
                <div v-else-if="results.length === 0" class="results-empty">
                    <svg class="empty-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <h3 class="empty-title">找不到結果</h3>
                    <p class="empty-description">「{{ searchQuery }}」沒有符合的項目</p>
                    <ul class="empty-tips">
                        <li>• 檢查拼字是否正確</li>
                        <li>• 嘗試不同的關鍵字</li>
                        <li>• 使用更通用的詞彙</li>
                        <li>• 調整篩選條件</li>
                    </ul>
                </div>

                <!-- 結果列表 -->
                <div v-else class="results-list">
                    <div
                        v-for="result in results"
                        :key="result.id"
                        @click="handleResultClick(result)"
                        class="result-card"
                        role="button"
                        tabindex="0"
                        @keydown.enter="handleResultClick(result)"
                    >
                        <!-- 類型標籤 -->
                        <div class="result-type-badge" :class="result.type">
                            {{ getResultIcon(result.type) }} {{ getTypeLabel(result.type) }}
                        </div>

                        <!-- 結果內容 -->
                        <div class="result-content">
                            <h4 class="result-title">{{ result.title }}</h4>
                            <p v-if="result.subtitle" class="result-subtitle">{{ result.subtitle }}</p>
                            <div class="result-meta">
                                <span v-if="result.date" class="result-meta-item">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                    </svg>
                                    {{ formatDate(result.date) }}
                                </span>
                                <span v-if="result.amount" class="result-meta-item">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    NT$ {{ result.amount.toLocaleString() }}
                                </span>
                                <span v-if="result.status" class="result-status-badge" :class="result.status">
                                    {{ getStatusLabel(result.status) }}
                                </span>
                            </div>
                        </div>

                        <!-- 查看詳情箭頭 -->
                        <div class="result-arrow">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                            </svg>
                        </div>
                    </div>
                </div>

                <!-- 分頁器 -->
                <div v-if="pagination.totalPages > 1" class="search-pagination">
                    <button
                        @click="changePage(pagination.page - 1)"
                        :disabled="pagination.page === 1"
                        class="pagination-btn"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                        </svg>
                        上一頁
                    </button>

                    <div class="pagination-numbers">
                        <button
                            v-for="page in visiblePages"
                            :key="page"
                            @click="changePage(page)"
                            :class="['pagination-number', { active: page === pagination.page }]"
                        >
                            {{ page }}
                        </button>
                    </div>

                    <button
                        @click="changePage(pagination.page + 1)"
                        :disabled="pagination.page === pagination.totalPages"
                        class="pagination-btn"
                    >
                        下一頁
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </button>
                </div>

            </div>
        </div>

    </div>

</main>
HTML;

// 清理 HEREDOC 縮排
$search_component_template = preg_replace('/\n\s+/', "\n", $search_component_template);
?>

<script>
const SearchPageComponent = {
    template: `<?php echo $search_component_template; ?>`,

    data() {
        return {
            searchQuery: '',
            results: [],
            filters: {
                type: 'all',
                status: 'all',
                dateFrom: null,
                dateTo: null,
            },
            pagination: {
                page: 1,
                perPage: 20,
                total: 0,
                totalPages: 0,
            },
            loading: false,
            error: null,
        };
    },

    computed: {
        visiblePages() {
            // 顯示當前頁前後 2 頁
            const pages = [];
            const start = Math.max(1, this.pagination.page - 2);
            const end = Math.min(this.pagination.totalPages, this.pagination.page + 2);

            for (let i = start; i <= end; i++) {
                pages.push(i);
            }

            return pages;
        }
    },

    mounted() {
        // 從 URL 讀取搜尋參數
        const urlParams = new URLSearchParams(window.location.search);
        const queryParam = urlParams.get('q');

        if (queryParam) {
            this.searchQuery = queryParam;
            this.performSearch();
        }
    },

    methods: {
        async performSearch() {
            // 如果沒有搜尋詞，清空結果
            if (!this.searchQuery.trim()) {
                this.results = [];
                this.pagination.total = 0;
                this.pagination.totalPages = 0;
                return;
            }

            this.loading = true;
            this.error = null;

            try {
                // 構建查詢參數
                const params = new URLSearchParams({
                    q: this.searchQuery,
                    page: this.pagination.page,
                    per_page: this.pagination.perPage,
                });

                // 添加過濾器（如果不是 'all'）
                if (this.filters.type !== 'all') {
                    params.append('type', this.filters.type);
                }
                if (this.filters.status !== 'all') {
                    params.append('status', this.filters.status);
                }
                if (this.filters.dateFrom) {
                    params.append('date_from', this.filters.dateFrom);
                }
                if (this.filters.dateTo) {
                    params.append('date_to', this.filters.dateTo);
                }

                // 調用全域搜尋 API
                const response = await fetch(`/wp-json/buygo-plus-one/v1/global-search?${params}`, {
                    headers: {
                        'X-WP-Nonce': window.buygoWpNonce
                    }
                });

                if (!response.ok) {
                    throw new Error(`API 錯誤: ${response.status}`);
                }

                const result = await response.json();

                // 更新結果
                this.results = result.data || [];
                this.pagination.total = result.total || 0;
                this.pagination.totalPages = result.total_pages || 0;

                // 更新 URL（不重新載入頁面）
                this.updateURL();

            } catch (err) {
                console.error('搜尋失敗:', err);
                this.error = '搜尋失敗，請稍後再試';
            } finally {
                this.loading = false;
            }
        },

        applyFilter(filterName, value) {
            this.filters[filterName] = value;
            this.pagination.page = 1; // 重置頁碼
            this.performSearch();
        },

        clearFilters() {
            this.filters = {
                type: 'all',
                status: 'all',
                dateFrom: null,
                dateTo: null,
            };
            this.pagination.page = 1;
            this.performSearch();
        },

        changePage(page) {
            if (page < 1 || page > this.pagination.totalPages) {
                return;
            }
            this.pagination.page = page;
            this.performSearch();
        },

        updateURL() {
            // 更新瀏覽器 URL（不重新載入）
            const params = new URLSearchParams({ q: this.searchQuery });
            const newUrl = `${window.location.pathname}?${params}`;
            window.history.pushState({}, '', newUrl);
        },

        getResultIcon(type) {
            const icons = {
                order: '🛒',
                product: '📦',
                customer: '👤',
                shipment: '🚚'
            };
            return icons[type] || '📄';
        },

        getTypeLabel(type) {
            const labels = {
                order: '訂單',
                product: '商品',
                customer: '客戶',
                shipment: '出貨單'
            };
            return labels[type] || type;
        },

        getStatusLabel(status) {
            const labels = {
                pending: '待處理',
                processing: '處理中',
                completed: '已完成',
                cancelled: '已取消',
                shipped: '已出貨',
                delivered: '已送達'
            };
            return labels[status] || status;
        },

        formatDate(dateString) {
            if (!dateString) return '';
            const date = new Date(dateString);
            return date.toLocaleDateString('zh-TW', {
                year: 'numeric',
                month: '2-digit',
                day: '2-digit'
            });
        },

        handleResultClick(result) {
            // 根據類型跳轉到對應的詳情頁
            const routes = {
                order: '/buygo-portal/orders',
                product: '/buygo-portal/products',
                customer: '/buygo-portal/customers',
                shipment: '/buygo-portal/shipment-details'
            };

            const basePath = routes[result.type];
            if (basePath && result.id) {
                // 使用 URL 參數傳遞 ID
                window.location.href = `${basePath}?id=${result.id}`;
            }
        },

        // 幣別切換處理（Header 元件會呼叫此方法）
        onCurrencyChange(newCurrency) {
            console.log('[SearchPage] 幣別變更:', newCurrency);
        }
    }
};
</script>

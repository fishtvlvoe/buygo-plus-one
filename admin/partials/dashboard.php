<?php
// Dashboard 頁面元件
?>
<!-- Dashboard Page Styles -->
<link rel="stylesheet" href="<?php echo esc_url(plugins_url('../css/dashboard.css', __FILE__)); ?>" />
<!-- Chart.js CDN (4.x) -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>

<?php
$dashboard_component_template = <<<'HTML'
<main class="min-h-screen bg-slate-50">

    <!-- ============================================ -->
    <!-- 頁首部分 -->
    <!-- ============================================ -->
    <header class="page-header">
        <div class="flex items-center gap-3 md:gap-4 overflow-hidden flex-1">
            <div class="flex flex-col overflow-hidden min-w-0 pl-12 md:pl-0">
                <h1 class="page-header-title">儀表板</h1>
                <nav class="page-header-breadcrumb">
                    <a href="/buygo-portal/dashboard" class="active">首頁</a>
                </nav>
            </div>
        </div>

        <!-- 右側操作區 -->
        <div class="flex items-center gap-2 md:gap-3 shrink-0">
            <!-- 全域搜尋框 -->
            <div class="global-search">
                <input type="text" placeholder="全域搜尋..." v-model="globalSearchQuery" @input="handleGlobalSearch">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
            </div>

            <!-- 通知鈴鐺 -->
            <button class="notification-bell">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
            </button>

            <!-- 幣別切換 -->
            <button @click="toggleCurrency" class="ml-2 px-3 py-1.5 bg-white border border-slate-200 rounded-md text-xs font-bold text-slate-600 hover:border-primary hover:text-primary transition shadow-sm">
                {{ displayCurrency }}
            </button>
        </div>
    </header>
    <!-- 結束：頁首部分 -->

    <!-- ============================================ -->
    <!-- 內容區域 -->
    <!-- ============================================ -->
    <div class="p-2 xs:p-4 md:p-6 w-full max-w-7xl mx-auto space-y-4 md:space-y-6">

        <!-- 載入中狀態 -->
        <div v-if="loading" class="space-y-4">
            <div class="stats-grid">
                <div v-for="i in 4" :key="i" class="stat-card loading-skeleton">
                    <div class="h-4 bg-slate-200 rounded w-24 mb-2 animate-pulse"></div>
                    <div class="h-8 bg-slate-200 rounded w-32 mb-2 animate-pulse"></div>
                    <div class="h-4 bg-slate-200 rounded w-20 animate-pulse"></div>
                </div>
            </div>
        </div>

        <!-- 錯誤狀態 -->
        <div v-else-if="error" class="text-center py-12 bg-white rounded-lg shadow">
            <svg class="w-16 h-16 text-red-500 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <p class="text-red-600 mb-4 text-lg font-medium">{{ error }}</p>
            <button @click="loadAllData" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-700 transition">重新載入</button>
        </div>

        <!-- 主要內容 -->
        <div v-else>
            <!-- 統計卡片區 -->
            <section class="stats-grid">
                <!-- 總營收 -->
                <div class="stat-card">
                    <div class="stat-card-label">本月總營收</div>
                    <div class="stat-card-value">{{ formatCurrency(stats.total_revenue.value) }}</div>
                    <div class="stat-card-change" :class="getChangeClass(stats.total_revenue.change_percent)">
                        <span>{{ stats.total_revenue.change_percent >= 0 ? '↑' : '↓' }}</span>
                        <span>{{ Math.abs(stats.total_revenue.change_percent).toFixed(1) }}% 較上月</span>
                    </div>
                </div>

                <!-- 訂單數 -->
                <div class="stat-card">
                    <div class="stat-card-label">本月訂單數</div>
                    <div class="stat-card-value">{{ stats.total_orders.value.toLocaleString() }}</div>
                    <div class="stat-card-change" :class="getChangeClass(stats.total_orders.change_percent)">
                        <span>{{ stats.total_orders.change_percent >= 0 ? '↑' : '↓' }}</span>
                        <span>{{ Math.abs(stats.total_orders.change_percent).toFixed(1) }}% 較上月</span>
                    </div>
                </div>

                <!-- 客戶數 -->
                <div class="stat-card">
                    <div class="stat-card-label">新增客戶數</div>
                    <div class="stat-card-value">{{ stats.total_customers.value.toLocaleString() }}</div>
                    <div class="stat-card-change" :class="getChangeClass(stats.total_customers.change_percent)">
                        <span>{{ stats.total_customers.change_percent >= 0 ? '↑' : '↓' }}</span>
                        <span>{{ Math.abs(stats.total_customers.change_percent).toFixed(1) }}% 較上月</span>
                    </div>
                </div>

                <!-- 平均訂單金額 -->
                <div class="stat-card">
                    <div class="stat-card-label">平均訂單金額</div>
                    <div class="stat-card-value">{{ formatCurrency(stats.avg_order_value.value) }}</div>
                    <div class="stat-card-change" :class="getChangeClass(stats.avg_order_value.change_percent)">
                        <span>{{ stats.avg_order_value.change_percent >= 0 ? '↑' : '↓' }}</span>
                        <span>{{ Math.abs(stats.avg_order_value.change_percent).toFixed(1) }}% 較上月</span>
                    </div>
                </div>
            </section>

            <!-- 圖表區 -->
            <section class="charts-grid">
                <div class="chart-card">
                    <h3 class="chart-title">營收趨勢（過去 30 天）</h3>
                    <div class="chart-container">
                        <canvas ref="revenueChart"></canvas>
                    </div>
                </div>
            </section>

            <!-- 底部區域：商品概覽 + 最近活動 -->
            <div class="dashboard-bottom-grid">
                <!-- 商品概覽 -->
                <section class="products-overview">
                    <h3 class="section-title">商品概覽</h3>
                    <div class="stats-row">
                        <div class="stat-item">
                            <span class="label">總商品數</span>
                            <span class="value">{{ productsData.total_products }}</span>
                        </div>
                        <div class="stat-item">
                            <span class="label">已上架</span>
                            <span class="value text-green-600">{{ productsData.published }}</span>
                        </div>
                        <div class="stat-item">
                            <span class="label">待上架</span>
                            <span class="value text-amber-600">{{ productsData.draft }}</span>
                        </div>
                    </div>
                </section>

                <!-- 最近活動 -->
                <section class="activities-list">
                    <h3 class="section-title">最近活動</h3>
                    <div v-if="activities.length === 0" class="text-center py-8 text-slate-500">
                        <svg class="w-12 h-12 mx-auto mb-2 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        <p>尚無活動記錄</p>
                    </div>
                    <div v-else class="activity-item" v-for="activity in activities" :key="activity.id">
                        <div class="activity-icon" :class="activity.type">
                            <svg v-if="activity.type === 'order'" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                            </svg>
                            <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                            </svg>
                        </div>
                        <div class="activity-content">
                            <div class="activity-title">訂單 {{ activity.order_id }}</div>
                            <div class="activity-description">
                                {{ activity.customer_name }} · NT$ {{ Math.round(activity.amount).toLocaleString() }}
                            </div>
                        </div>
                        <div class="activity-time">{{ formatTimeAgo(activity.timestamp) }}</div>
                    </div>
                </section>
            </div>

        </div>
    </div>

</main>
HTML;

// 清理 HEREDOC 縮排（移除每行開頭的空白）
$dashboard_component_template = preg_replace('/\n\s+/', "\n", $dashboard_component_template);
?>

<script>
const DashboardPageComponent = {
    template: `<?php echo $dashboard_component_template; ?>`,

    data() {
        return {
            loading: true,
            error: null,
            globalSearchQuery: '',

            // 幣別設定
            currentCurrency: 'TWD',
            displayCurrency: 'TWD',

            // 統計數據
            stats: {
                total_revenue: { value: 0, change_percent: 0 },
                total_orders: { value: 0, change_percent: 0 },
                total_customers: { value: 0, change_percent: 0 },
                avg_order_value: { value: 0, change_percent: 0 }
            },

            // 營收趨勢資料
            revenueData: null,
            revenueChart: null, // Chart.js 實例

            // 商品數據
            productsData: {
                total_products: 0,
                published: 0,
                draft: 0
            },

            // 活動列表
            activities: []
        };
    },

    mounted() {
        this.loadAllData();
    },

    methods: {
        async loadAllData() {
            this.loading = true;
            this.error = null;

            try {
                // 平行載入所有資料（效能優化）
                await Promise.all([
                    this.loadStats(),
                    this.loadRevenue(),
                    this.loadProducts(),
                    this.loadActivities()
                ]);
            } catch (err) {
                console.error('載入 Dashboard 資料失敗:', err);
                this.error = '載入資料失敗，請稍後再試';
            } finally {
                this.loading = false;
            }
        },

        async loadStats() {
            // 使用 FluentCart 官方 Dashboard API
            const response = await fetch('/wp-json/fluent-cart/v2/dashboard/stats', {
                headers: { 'X-WP-Nonce': window.buygoWpNonce }
            });

            if (!response.ok) {
                throw new Error(`API 錯誤: ${response.status}`);
            }

            const result = await response.json();
            console.log('FluentCart API 回傳:', result);

            // FluentCart API 回傳格式：{ stats: [ {...}, {...} ] }
            // 需要轉換為我們的資料格式
            if (result.stats && Array.isArray(result.stats)) {
                console.log('解析 stats 陣列:', result.stats);
                this.parseFluentCartStats(result.stats);
                console.log('解析後的 stats:', this.stats);
            } else {
                console.error('FluentCart API 回傳格式不正確:', result);
            }
        },

        parseFluentCartStats(statsArray) {
            // 解析 FluentCart stats 陣列，映射到我們的 stats 物件
            statsArray.forEach(stat => {
                // FluentCart 金額以元為單位，帶小數點（例如：180.0000）
                // 我們需要轉換為分（乘以 100）
                const value = parseFloat(stat.current_count) || 0;

                // 根據 title 判斷是哪個統計項目（FluentCart 使用繁體中文）
                if (stat.title === '收入') {
                    this.stats.total_revenue = {
                        value: Math.round(value * 100), // 元 → 分
                        change_percent: 0
                    };
                } else if (stat.title === '訂單') {
                    this.stats.total_orders = {
                        value: value,
                        change_percent: 0
                    };
                }
            });

            // FluentCart API 沒有提供客戶數，使用我們自己的 API
            this.loadCustomersCount();

            // 計算平均訂單金額
            if (this.stats.total_orders.value > 0) {
                this.stats.avg_order_value = {
                    value: Math.round(this.stats.total_revenue.value / this.stats.total_orders.value),
                    change_percent: 0
                };
            }
        },

        async loadCustomersCount() {
            // FluentCart Dashboard API 沒有客戶數統計，使用我們自己的 API
            try {
                const response = await fetch('/wp-json/buygo-plus-one/v1/dashboard/stats', {
                    headers: { 'X-WP-Nonce': window.buygoWpNonce }
                });

                if (response.ok) {
                    const result = await response.json();
                    if (result.data && result.data.total_customers) {
                        this.stats.total_customers = result.data.total_customers;
                    }
                }
            } catch (err) {
                console.warn('無法載入客戶數統計:', err);
                // 客戶數載入失敗不影響其他統計
            }
        },

        async loadRevenue() {
            const response = await fetch(`/wp-json/buygo-plus-one/v1/dashboard/revenue?period=30&currency=${this.currentCurrency}`, {
                headers: { 'X-WP-Nonce': window.buygoWpNonce }
            });

            if (!response.ok) {
                throw new Error(`API 錯誤: ${response.status}`);
            }

            const result = await response.json();
            this.revenueData = result.data;

            // 使用 $nextTick 確保 DOM 渲染完成
            this.$nextTick(() => {
                this.renderRevenueChart();
            });
        },

        async loadProducts() {
            const response = await fetch('/wp-json/buygo-plus-one/v1/dashboard/products', {
                headers: { 'X-WP-Nonce': window.buygoWpNonce }
            });

            if (!response.ok) {
                throw new Error(`API 錯誤: ${response.status}`);
            }

            const result = await response.json();
            this.productsData = result.data;
        },

        async loadActivities() {
            const response = await fetch('/wp-json/buygo-plus-one/v1/dashboard/activities?limit=10', {
                headers: { 'X-WP-Nonce': window.buygoWpNonce }
            });

            if (!response.ok) {
                throw new Error(`API 錯誤: ${response.status}`);
            }

            const result = await response.json();
            this.activities = result.data;
        },

        renderRevenueChart() {
            if (!this.revenueData || !this.$refs.revenueChart) {
                return;
            }

            const ctx = this.$refs.revenueChart.getContext('2d');

            // 銷毀舊圖表（避免重複渲染）
            if (this.revenueChart) {
                this.revenueChart.destroy();
            }

            this.revenueChart = new Chart(ctx, {
                type: 'line',
                data: this.revenueData,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: (context) => {
                                    return `營收: ${this.formatCurrency(context.parsed.y)}`;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: (value) => this.formatCurrency(value)
                            }
                        }
                    }
                }
            });
        },

        formatCurrency(cents) {
            const amount = Math.round(cents / 100); // 分 → 元，移除小數點
            return `NT$ ${amount.toLocaleString()}`;
        },

        getChangeClass(percent) {
            return percent >= 0 ? 'positive' : 'negative';
        },

        getIconClass(type) {
            return type === 'order' ? 'shopping-cart' : 'user-plus';
        },

        formatTimeAgo(timestamp) {
            const date = new Date(timestamp);
            const now = new Date();
            const diffMs = now - date;
            const diffMins = Math.floor(diffMs / 60000);

            if (diffMins < 1) return '剛剛';
            if (diffMins < 60) return `${diffMins} 分鐘前`;

            const diffHours = Math.floor(diffMins / 60);
            if (diffHours < 24) return `${diffHours} 小時前`;

            const diffDays = Math.floor(diffHours / 24);
            if (diffDays < 7) return `${diffDays} 天前`;

            return date.toLocaleDateString('zh-TW');
        },

        handleGlobalSearch() {
            // 全域搜尋功能（未來實作）
            console.log('全域搜尋:', this.globalSearchQuery);
        },

        toggleCurrency() {
            // 幣別切換功能（未來可擴展支援多幣別）
            const currencies = ['TWD', 'USD', 'CNY'];
            const currentIndex = currencies.indexOf(this.currentCurrency);
            const nextIndex = (currentIndex + 1) % currencies.length;
            this.currentCurrency = currencies[nextIndex];
            this.displayCurrency = currencies[nextIndex];

            // 重新載入營收資料（使用新幣別）
            this.loadRevenue();
        }
    }
};
</script>

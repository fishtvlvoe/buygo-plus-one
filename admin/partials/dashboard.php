<?php
// Dashboard 頁面元件
?>
<!-- Dashboard Page Styles (Redesigned) -->
<link rel="stylesheet" href="<?php echo esc_url(plugins_url('../css/dashboard-redesign.css', __FILE__)); ?>?v=<?php echo time(); ?>" />
<!-- Chart.js CDN (4.x) -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>

<?php
$dashboard_component_template = <<<'HTML'
<main class="min-h-screen bg-slate-50">
    <!-- Header 元件 -->
    <page-header-component
        title="儀表板"
        breadcrumb='<a href="/buygo-portal/dashboard" class="active">首頁</a>'
        :show-currency-toggle="true"
        @currency-changed="onCurrencyChange"
    ></page-header-component>

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
            <!-- 統計卡片區（精簡版） -->
            <section class="stats-grid-compact">
                <div class="stat-card-compact">
                    <div class="stat-card-label">營收</div>
                    <div class="stat-card-value">{{ formatCurrency(stats.total_revenue.value) }}</div>
                    <div class="stat-card-change" :class="getChangeClass(stats.total_revenue.change_percent)">
                        <span>{{ stats.total_revenue.change_percent >= 0 ? '↑' : '↓' }}</span>
                        <span>{{ Math.abs(stats.total_revenue.change_percent).toFixed(1) }}%</span>
                    </div>
                </div>

                <div class="stat-card-compact">
                    <div class="stat-card-label">訂單</div>
                    <div class="stat-card-value">{{ stats.total_orders.value.toLocaleString() }}</div>
                    <div class="stat-card-change" :class="getChangeClass(stats.total_orders.change_percent)">
                        <span>{{ stats.total_orders.change_percent >= 0 ? '↑' : '↓' }}</span>
                        <span>{{ Math.abs(stats.total_orders.change_percent).toFixed(1) }}%</span>
                    </div>
                </div>

                <div class="stat-card-compact">
                    <div class="stat-card-label">客戶</div>
                    <div class="stat-card-value">{{ stats.total_customers.value.toLocaleString() }}</div>
                    <div class="stat-card-change" :class="getChangeClass(stats.total_customers.change_percent)">
                        <span>{{ stats.total_customers.change_percent >= 0 ? '↑' : '↓' }}</span>
                        <span>{{ Math.abs(stats.total_customers.change_percent).toFixed(1) }}%</span>
                    </div>
                </div>

                <div class="stat-card-compact">
                    <div class="stat-card-label">客單價</div>
                    <div class="stat-card-value">{{ formatCurrency(stats.avg_order_value.value) }}</div>
                    <div class="stat-card-change" :class="getChangeClass(stats.avg_order_value.change_percent)">
                        <span>{{ stats.avg_order_value.change_percent >= 0 ? '↑' : '↓' }}</span>
                        <span>{{ Math.abs(stats.avg_order_value.change_percent).toFixed(1) }}%</span>
                    </div>
                </div>
            </section>

            <!-- 兩欄式主要內容區 -->
            <div class="dashboard-main-grid">
                <!-- 左欄：營收趨勢 + 產品管理 -->
                <section class="dashboard-left-column">
                    <!-- 營收趨勢圖 -->
                    <div class="chart-card-merged">
                        <h3 class="section-title-merged">營收趨勢（過去 30 天）</h3>
                        <div class="chart-container-merged">
                            <canvas ref="revenueChart"></canvas>
                        </div>
                    </div>

                    <!-- 產品管理 -->
                    <div class="products-section">
                        <h3 class="section-title-merged">產品管理</h3>
                        <div class="products-stats">
                            <div class="product-stat-item">
                                <span class="product-label">總數</span>
                                <span class="product-value">{{ productsData.total_products }}</span>
                            </div>
                            <div class="product-stat-item">
                                <span class="product-label">已上架</span>
                                <span class="product-value product-value-success">{{ productsData.published }}</span>
                            </div>
                            <div class="product-stat-item">
                                <span class="product-label">待上架</span>
                                <span class="product-value product-value-warning">{{ productsData.draft }}</span>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- 右欄：最近活動 -->
                <section class="dashboard-right-column">
                    <h3 class="section-title-merged">最近活動</h3>
                    <div v-if="activities.length === 0" class="empty-activities">
                        <svg class="empty-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        <p>尚無活動記錄</p>
                    </div>
                    <div v-else class="activities-container">
                        <div
                            class="activity-item"
                            v-for="activity in activities"
                            :key="activity.id"
                            @click="handleActivityClick(activity)"
                            role="button"
                            tabindex="0"
                            @keydown.enter="handleActivityClick(activity)"
                        >
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
                                    {{ activity.customer_name }} · {{ formatCurrency(activity.convertedAmount) }}
                                </div>
                            </div>
                            <div class="activity-time">{{ formatTimeAgo(activity.timestamp) }}</div>
                        </div>
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

            // 當前顯示幣別（從 useCurrency composable 同步）
            currentCurrency: 'JPY',

            // 幣別符號對照表（從 useCurrency 取得）
            currencySymbols: {},

            // useCurrency composable 方法
            currencyHelper: null,

            // 原始統計數據（各幣別分組）
            rawStats: null,

            // 計算後的統計數據（已換算成目標幣別）
            stats: {
                total_revenue: { value: 0, change_percent: 0 },
                total_orders: { value: 0, change_percent: 0 },
                total_customers: { value: 0, change_percent: 0 },
                avg_order_value: { value: 0, change_percent: 0 }
            },

            // 原始營收趨勢資料（各幣別分組）
            rawRevenueData: null,

            // 營收趨勢資料（已換算）
            revenueData: null,
            revenueChart: null, // Chart.js 實例

            // 商品數據
            productsData: {
                total_products: 0,
                published: 0,
                draft: 0
            },

            // 原始活動列表（含原始幣別）
            rawActivities: [],

            // 活動列表（已換算）
            activities: []
        };
    },

    async mounted() {
        // 從 useCurrency composable 取得方法和狀態
        this.currencyHelper = useCurrency();
        this.currentCurrency = this.currencyHelper.systemCurrency.value;
        this.currencySymbols = this.currencyHelper.currencySymbols;
        console.log('[Dashboard] 初始幣別:', this.currentCurrency);

        await this.loadAllData();

        // 等待所有資料載入完成後，再渲染圖表
        this.$nextTick(() => {
            if (this.revenueData && this.$refs.revenueChart) {
                this.renderRevenueChart();
            }
        });
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
            // API 現在回傳全幣別的原始數據
            const response = await fetch('/wp-json/buygo-plus-one/v1/dashboard/stats', {
                headers: { 'X-WP-Nonce': window.buygoWpNonce }
            });

            if (!response.ok) {
                throw new Error(`API 錯誤: ${response.status}`);
            }

            const result = await response.json();

            if (result.success && result.data) {
                this.rawStats = result.data;
                // 計算換算後的統計數據
                this.calculateConvertedStats();
            } else {
                console.error('BuyGo Dashboard API 回傳格式不正確:', result);
            }
        },

        // 計算換算後的統計數據
        calculateConvertedStats() {
            if (!this.rawStats || !this.currencyHelper) return;

            const { convertCurrency } = this.currencyHelper;
            const targetCurrency = this.currentCurrency;

            // 計算當期營收（換算後加總）
            let currentRevenue = 0;
            const currentByCurrency = this.rawStats.by_currency?.current || {};
            for (const [currency, data] of Object.entries(currentByCurrency)) {
                const converted = convertCurrency(data.total_revenue, currency, targetCurrency);
                currentRevenue += converted;
            }

            // 計算前期營收（換算後加總）
            let lastRevenue = 0;
            const lastByCurrency = this.rawStats.by_currency?.last || {};
            for (const [currency, data] of Object.entries(lastByCurrency)) {
                const converted = convertCurrency(data.total_revenue, currency, targetCurrency);
                lastRevenue += converted;
            }

            // 計算營收變化百分比
            const revenueChange = lastRevenue === 0
                ? (currentRevenue > 0 ? 100 : 0)
                : ((currentRevenue - lastRevenue) / lastRevenue) * 100;

            // 訂單數和客戶數直接從 API 取得（不需要換算）
            const totalOrders = this.rawStats.total_orders?.value || 0;
            const totalCustomers = this.rawStats.total_customers?.value || 0;
            const orderChange = this.rawStats.total_orders?.change_percent || 0;
            const customerChange = this.rawStats.total_customers?.change_percent || 0;

            // 計算平均訂單價值
            const avgOrderValue = totalOrders > 0 ? Math.round(currentRevenue / totalOrders) : 0;
            const lastAvgOrderValue = this.rawStats.total_orders?.value > 0 && lastRevenue > 0
                ? Math.round(lastRevenue / (this.rawStats.by_currency?.last ? Object.values(this.rawStats.by_currency.last).reduce((sum, d) => sum + d.order_count, 0) : 1))
                : 0;
            const avgChange = lastAvgOrderValue === 0
                ? (avgOrderValue > 0 ? 100 : 0)
                : ((avgOrderValue - lastAvgOrderValue) / lastAvgOrderValue) * 100;

            this.stats = {
                total_revenue: {
                    value: Math.round(currentRevenue),
                    change_percent: Math.round(revenueChange * 10) / 10
                },
                total_orders: {
                    value: totalOrders,
                    change_percent: orderChange
                },
                total_customers: {
                    value: totalCustomers,
                    change_percent: customerChange
                },
                avg_order_value: {
                    value: avgOrderValue,
                    change_percent: Math.round(avgChange * 10) / 10
                }
            };
        },

        async loadRevenue() {
            // API 現在回傳全幣別的原始數據
            const response = await fetch('/wp-json/buygo-plus-one/v1/dashboard/revenue?period=30', {
                headers: { 'X-WP-Nonce': window.buygoWpNonce }
            });

            if (!response.ok) {
                throw new Error(`API 錯誤: ${response.status}`);
            }

            const result = await response.json();
            this.rawRevenueData = result.data;
            // 計算換算後的營收趨勢
            this.calculateConvertedRevenue();
        },

        // 計算換算後的營收趨勢
        calculateConvertedRevenue() {
            if (!this.rawRevenueData || !this.currencyHelper) return;

            const { convertCurrency } = this.currencyHelper;
            const targetCurrency = this.currentCurrency;
            const labels = this.rawRevenueData.labels || [];
            const byCurrency = this.rawRevenueData.by_currency || {};

            // 計算每天的換算後營收
            const convertedData = labels.map((_, index) => {
                let dailyTotal = 0;
                for (const [currency, dailyAmounts] of Object.entries(byCurrency)) {
                    const amount = dailyAmounts[index] || 0;
                    dailyTotal += convertCurrency(amount, currency, targetCurrency);
                }
                return Math.round(dailyTotal);
            });

            // 轉換成 Chart.js 格式
            this.revenueData = {
                labels: labels,
                datasets: [{
                    label: '營收',
                    data: convertedData,
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.4
                }]
            };
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
            this.rawActivities = result.data || [];
            // 計算換算後的活動列表
            this.calculateConvertedActivities();
        },

        // 計算換算後的活動列表金額
        calculateConvertedActivities() {
            if (!this.currencyHelper) return;

            const { convertCurrency } = this.currencyHelper;
            const targetCurrency = this.currentCurrency;

            this.activities = this.rawActivities.map(activity => ({
                ...activity,
                // 換算金額（保持「分」單位，因為 formatCurrency 會除以 100）
                convertedAmount: Math.round(convertCurrency(activity.amount, activity.currency || 'JPY', targetCurrency))
            }));
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
                                    // 資料中的金額單位是「分」，需要除以 100
                                    const amount = Math.round(context.parsed.y / 100);
                                    const symbol = this.currencySymbols[this.currentCurrency] || '¥';
                                    return `營收: ${symbol}${amount.toLocaleString()}`;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: (value) => {
                                    // 資料中的金額單位是「分」，需要除以 100
                                    const amount = Math.round(value / 100);
                                    const symbol = this.currencySymbols[this.currentCurrency] || '¥';
                                    return `${symbol}${amount.toLocaleString()}`;
                                }
                            }
                        }
                    }
                }
            });
        },

        formatCurrency(cents) {
            const amount = Math.round(cents / 100); // 分 → 元，移除小數點
            const symbol = this.currencySymbols[this.currentCurrency] || '¥';
            return `${symbol}${amount.toLocaleString()}`;
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

        // 幣別切換時，只需要重新計算換算值，不需要重新載入資料
        async onCurrencyChange(newCurrency) {
            console.log('[Dashboard] 幣別切換:', newCurrency);
            // 更新當前幣別
            this.currentCurrency = newCurrency;

            // 重新計算換算後的數據（不需要重新載入 API）
            this.calculateConvertedStats();
            this.calculateConvertedRevenue();
            this.calculateConvertedActivities();

            // 重新渲染圖表
            this.$nextTick(() => {
                if (this.revenueData && this.$refs.revenueChart) {
                    this.renderRevenueChart();
                }
            });
        },

        handleActivityClick(activity) {
            // 活動項目點擊處理（未來實作：跳轉到訂單詳情或客戶頁面）
            console.log('Activity clicked:', activity);

            if (activity.type === 'order' && activity.order_id) {
                // 未來：跳轉到訂單詳情頁面
                // window.location.href = `/buygo-portal/orders?id=${activity.order_id}`;
                alert(`訂單 ${activity.order_id} 詳情（功能開發中）`);
            } else if (activity.type === 'customer' && activity.customer_name) {
                // 未來：跳轉到客戶頁面
                alert(`客戶 ${activity.customer_name} 詳情（功能開發中）`);
            }
        }
    }
};
</script>

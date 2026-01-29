# Dashboard 技術方案研究

**研究日期:** 2026-01-29
**研究者:** Claude Sonnet 4.5
**專案:** BuyGo Plus One - Dashboard 實作

---

## 執行摘要

本研究針對 BuyGo Plus One 外掛的 Dashboard 頁面實作,分析技術選型、架構整合、API 設計和前端組件設計。研究範圍涵蓋圖表庫選擇、FluentCart 資料庫整合、現有設計系統應用,以及響應式佈局策略。

**核心推薦:**
- **圖表庫:** Chart.js (CDN) - 輕量、易整合、與 Vue 3 相容性佳
- **API 架構:** 基於現有 Service Layer + REST API 模式,新增 `DashboardService` 和 `Dashboard_API`
- **前端架構:** 沿用現有 Vue 3 SPA + 設計系統,新增 dashboard.php 頁面
- **資料來源:** 直接查詢 FluentCart 資料庫 (`fct_orders`, `fct_customers`, `fct_order_items`)

---

## 1. 現有架構整合分析

### 1.1 架構概覽

BuyGo Plus One 採用 MVC 分層架構:

```
WordPress Plugin
├── Service Layer (商業邏輯)
│   └── 17 個服務類別 (OrderService, ProductService...)
├── REST API Layer (前後端通訊)
│   └── 10 個 API endpoints (Orders_API, Customers_API...)
├── Frontend (Vue 3 SPA)
│   ├── 7 個頁面 (customers.php, orders.php...)
│   └── Design System (tokens + components)
└── Database (FluentCart + BuyGo 自訂資料表)
```

### 1.2 Dashboard 在架構中的位置

**新增元件:**

| 層級 | 新增檔案 | 用途 |
|------|---------|------|
| **Service** | `includes/services/class-dashboard-service.php` | 封裝儀表板統計邏輯、查詢 FluentCart 資料 |
| **API** | `includes/api/class-dashboard-api.php` | 提供 REST endpoints 供前端調用 |
| **Frontend** | `admin/partials/dashboard.php` | Vue 3 頁面元件,顯示統計圖表和卡片 |
| **Routes** | `includes/class-routes.php` | 已存在路由 `/buygo-portal/dashboard/` |

### 1.3 與現有系統整合點

**資料流:**
```
Vue Dashboard Component (dashboard.php)
    ↓ fetch /buygo-plus-one/v1/dashboard/stats
Dashboard_API::get_stats()
    ↓ 調用 DashboardService
DashboardService::getStats()
    ↓ 查詢 FluentCart 資料表
fct_orders, fct_customers, fct_order_items
    ↓ 回傳 JSON
Vue 更新圖表和卡片
```

**權限檢查:** 沿用現有 `API::check_permission()` (nonce 或 API key)

**設計系統:** 使用現有 Design System (card, header, button...)

---

## 2. 圖表庫選擇

### 2.1 候選方案比較

| 項目 | Chart.js | ApexCharts |
|------|----------|------------|
| **檔案大小** | ~200KB (CDN) | ~450KB (CDN) |
| **Vue 3 整合** | 簡單 (直接 `<canvas>`) | 簡單 (官方 wrapper) |
| **響應式** | 內建支援 | 內建支援 |
| **圖表類型** | 8 種基本類型 | 15+ 種進階類型 |
| **互動性** | 基本 hover/tooltip | 進階 zoom/pan/export |
| **文件品質** | ★★★★★ (非常完整) | ★★★★☆ (完整) |
| **CDN 可用性** | ✅ jsDelivr, cdnjs | ✅ jsDelivr, cdnjs |
| **學習曲線** | 低 (1-2 小時) | 中 (3-4 小時) |
| **社群活躍度** | 非常高 (60k+ stars) | 高 (13k+ stars) |

### 2.2 推薦方案: Chart.js

**選擇理由:**

1. **輕量級:** 200KB vs 450KB,載入速度快 2 倍
2. **簡單整合:** 直接使用 `<canvas>` 元素,無需 wrapper
3. **符合需求:** Dashboard 只需基本統計圖表 (折線圖、長條圖、圓餅圖)
4. **文件完整:** 官方文件詳盡,中文資源豐富
5. **專案一致性:** 專案已使用輕量 CDN 策略 (Vue 3, Tailwind)

**版本選擇:**
- **Chart.js 4.x** (最新穩定版,2023 年發布)
- CDN: `https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js`

### 2.3 Chart.js 基本使用模式

**HTML 結構:**
```html
<div class="chart-container">
    <canvas ref="revenueChart"></canvas>
</div>
```

**Vue 3 整合:**
```javascript
import { ref, onMounted } from 'vue';

export default {
    setup() {
        const revenueChart = ref(null);

        onMounted(() => {
            const ctx = revenueChart.value.getContext('2d');
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: ['一月', '二月', '三月'],
                    datasets: [{
                        label: '營收',
                        data: [12000, 19000, 15000]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });
        });

        return { revenueChart };
    }
};
```

---

## 3. API 設計規格

### 3.1 端點架構

**命名空間:** `buygo-plus-one/v1`

| 端點 | 方法 | 用途 | 回應時間目標 |
|------|------|------|------------|
| `/dashboard/stats` | GET | 取得總覽統計 | < 500ms |
| `/dashboard/revenue` | GET | 取得營收趨勢 (30天) | < 800ms |
| `/dashboard/products` | GET | 取得商品概覽 (Top 5) | < 600ms |
| `/dashboard/activities` | GET | 取得最近活動 (10 筆) | < 400ms |

### 3.2 `/dashboard/stats` - 總覽統計

**請求:**
```http
GET /wp-json/buygo-plus-one/v1/dashboard/stats
Headers:
    X-WP-Nonce: {nonce}
```

**回應:**
```json
{
    "success": true,
    "data": {
        "total_revenue": {
            "value": 158000,
            "currency": "TWD",
            "change_percent": 12.5,
            "period": "本月"
        },
        "total_orders": {
            "value": 234,
            "change_percent": 8.3,
            "period": "本月"
        },
        "total_customers": {
            "value": 156,
            "change_percent": 5.1,
            "period": "本月"
        },
        "avg_order_value": {
            "value": 675,
            "currency": "TWD",
            "change_percent": 3.2,
            "period": "本月"
        }
    },
    "cached_at": "2026-01-29 10:30:00"
}
```

**欄位說明:**
- `value`: 數值 (營收以分為單位,顯示時除以 100)
- `change_percent`: 與上期相比的變化百分比 (正數=成長,負數=下降)
- `period`: 統計期間描述
- `cached_at`: 快取時間戳記 (用於顯示「最後更新時間」)

### 3.3 `/dashboard/revenue` - 營收趨勢

**請求:**
```http
GET /wp-json/buygo-plus-one/v1/dashboard/revenue?period=30&currency=TWD
```

**參數:**
- `period`: 天數 (預設 30,可選 7, 30, 90)
- `currency`: 幣別 (預設 TWD,可選 USD, CNY)

**回應:**
```json
{
    "success": true,
    "data": {
        "labels": ["01/01", "01/02", "01/03", "..."],
        "datasets": [
            {
                "label": "營收",
                "data": [12000, 15000, 13500, "..."],
                "borderColor": "#3b82f6",
                "backgroundColor": "rgba(59, 130, 246, 0.1)"
            }
        ],
        "currency": "TWD"
    }
}
```

**SQL 查詢範例:**
```sql
SELECT
    DATE(created_at) as date,
    SUM(total_amount) as daily_revenue
FROM wp_fct_orders
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    AND payment_status = 'paid'
    AND currency = 'TWD'
GROUP BY DATE(created_at)
ORDER BY date ASC
```

### 3.4 `/dashboard/products` - 商品概覽

**請求:**
```http
GET /wp-json/buygo-plus-one/v1/dashboard/products?limit=5
```

**回應:**
```json
{
    "success": true,
    "data": [
        {
            "product_id": 123,
            "product_name": "商品 A",
            "total_sales": 45,
            "total_revenue": 22500,
            "percentage": 18.5
        }
    ]
}
```

**SQL 查詢範例:**
```sql
SELECT
    oi.post_id as product_id,
    oi.post_title as product_name,
    SUM(oi.quantity) as total_sales,
    SUM(oi.line_total) as total_revenue
FROM wp_fct_order_items oi
INNER JOIN wp_fct_orders o ON oi.order_id = o.id
WHERE o.payment_status = 'paid'
    AND o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY oi.post_id
ORDER BY total_revenue DESC
LIMIT 5
```

### 3.5 `/dashboard/activities` - 最近活動

**請求:**
```http
GET /wp-json/buygo-plus-one/v1/dashboard/activities?limit=10
```

**回應:**
```json
{
    "success": true,
    "data": [
        {
            "type": "order",
            "title": "新訂單 #12345",
            "description": "客戶 張三 下單了 3 件商品",
            "timestamp": "2026-01-29 10:25:00",
            "icon": "shopping-cart",
            "url": "/buygo-portal/orders/?id=12345"
        },
        {
            "type": "customer",
            "title": "新客戶註冊",
            "description": "李四 註冊為新客戶",
            "timestamp": "2026-01-29 09:50:00",
            "icon": "user-plus",
            "url": "/buygo-portal/customers/?id=67"
        }
    ]
}
```

**SQL 查詢範例:**
```sql
(SELECT
    'order' as type,
    CONCAT('新訂單 #', id) as title,
    CONCAT('客戶 ', (SELECT CONCAT(first_name, ' ', last_name) FROM wp_fct_customers WHERE id = o.customer_id), ' 下單') as description,
    created_at as timestamp,
    id
FROM wp_fct_orders o
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
ORDER BY created_at DESC
LIMIT 5)

UNION ALL

(SELECT
    'customer' as type,
    '新客戶註冊' as title,
    CONCAT(first_name, ' ', last_name, ' 註冊為新客戶') as description,
    created_at as timestamp,
    id
FROM wp_fct_customers
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
ORDER BY created_at DESC
LIMIT 5)

ORDER BY timestamp DESC
LIMIT 10
```

### 3.6 快取策略

**快取位置:** WordPress Transients API
**快取鍵:** `buygo_dashboard_{endpoint}_{params_hash}`
**快取時間:**
- `/stats`: 5 分鐘 (統計數據變化頻繁)
- `/revenue`: 15 分鐘 (趨勢數據變化緩慢)
- `/products`: 15 分鐘
- `/activities`: 1 分鐘 (需要即時性)

**實作範例:**
```php
public function get_stats() {
    $cache_key = 'buygo_dashboard_stats';
    $cached = get_transient($cache_key);

    if ($cached !== false) {
        return new \WP_REST_Response([
            'success' => true,
            'data' => $cached,
            'cached_at' => get_transient($cache_key . '_time')
        ], 200);
    }

    $stats = $this->dashboardService->calculateStats();
    set_transient($cache_key, $stats, 5 * MINUTE_IN_SECONDS);
    set_transient($cache_key . '_time', current_time('mysql'), 5 * MINUTE_IN_SECONDS);

    return new \WP_REST_Response([
        'success' => true,
        'data' => $stats,
        'cached_at' => current_time('mysql')
    ], 200);
}
```

---

## 4. 前端設計

### 4.1 頁面佈局結構

**響應式策略:** 桌面版 4 欄 → 平板 2 欄 → 手機 1 欄

```html
<div class="dashboard-container">
    <!-- 頁首 (使用現有 page-header) -->
    <header class="page-header">...</header>

    <!-- 統計卡片區 (4 個) -->
    <section class="stats-grid">
        <div class="stat-card">總營收</div>
        <div class="stat-card">訂單數</div>
        <div class="stat-card">客戶數</div>
        <div class="stat-card">平均訂單</div>
    </section>

    <!-- 圖表區 (2 個) -->
    <section class="charts-grid">
        <div class="chart-card">營收趨勢圖</div>
        <div class="chart-card">商品銷售圓餅圖</div>
    </section>

    <!-- 活動列表 -->
    <section class="activities-list">
        <div class="activity-item">...</div>
    </section>
</div>
```

### 4.2 設計系統應用

**使用現有組件:**

| 組件 | 檔案 | 用途 |
|------|------|------|
| `.page-header` | `components/header.css` | 頁首標題和麵包屑 |
| `.card` | `components/card.css` | 統計卡片容器 |
| `.btn-primary` | `components/button.css` | 操作按鈕 |
| Design Tokens | `tokens/*.css` | 顏色、間距、字體 |

**新增樣式 (dashboard.css):**

```css
/* 統計卡片網格 */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: var(--spacing-4);
    margin-bottom: var(--spacing-6);
}

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: 1fr;
        gap: var(--spacing-3);
    }
}

/* 統計卡片 */
.stat-card {
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-xl);
    padding: var(--spacing-4);
    transition: all var(--transition-base);
}

.stat-card:hover {
    border-color: var(--color-hover-border);
    box-shadow: var(--shadow-md);
}

.stat-card-value {
    font-size: var(--font-size-3xl);
    font-weight: var(--font-weight-bold);
    color: var(--color-primary);
}

.stat-card-label {
    font-size: var(--font-size-sm);
    color: var(--color-text-secondary);
    margin-bottom: var(--spacing-2);
}

.stat-card-change {
    font-size: var(--font-size-xs);
    display: inline-flex;
    align-items: center;
    gap: var(--spacing-1);
}

.stat-card-change.positive {
    color: var(--color-success);
}

.stat-card-change.negative {
    color: var(--color-danger);
}

/* 圖表網格 */
.charts-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: var(--spacing-4);
    margin-bottom: var(--spacing-6);
}

@media (max-width: 768px) {
    .charts-grid {
        grid-template-columns: 1fr;
    }
}

/* 圖表卡片 */
.chart-card {
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-xl);
    padding: var(--spacing-4);
}

.chart-container {
    position: relative;
    height: 300px;
}

@media (max-width: 768px) {
    .chart-container {
        height: 250px;
    }
}
```

### 4.3 Vue 3 組件設計

**檔案:** `admin/partials/dashboard.php`

**核心結構:**
```javascript
const DashboardComponent = {
    data() {
        return {
            loading: true,
            stats: null,
            revenueData: null,
            productsData: null,
            activities: [],
            error: null,
            currency: 'TWD',

            // Chart.js 實例
            revenueChart: null,
            productsChart: null
        };
    },

    mounted() {
        this.loadAllData();
    },

    methods: {
        async loadAllData() {
            this.loading = true;
            try {
                await Promise.all([
                    this.loadStats(),
                    this.loadRevenue(),
                    this.loadProducts(),
                    this.loadActivities()
                ]);
            } catch (error) {
                this.error = error.message;
            } finally {
                this.loading = false;
            }
        },

        async loadStats() {
            const response = await fetch('/wp-json/buygo-plus-one/v1/dashboard/stats', {
                headers: { 'X-WP-Nonce': window.buygoWpNonce }
            });
            const data = await response.json();
            this.stats = data.data;
        },

        async loadRevenue() {
            const response = await fetch(`/wp-json/buygo-plus-one/v1/dashboard/revenue?currency=${this.currency}`, {
                headers: { 'X-WP-Nonce': window.buygoWpNonce }
            });
            const data = await response.json();
            this.revenueData = data.data;
            this.renderRevenueChart();
        },

        renderRevenueChart() {
            const ctx = this.$refs.revenueChart.getContext('2d');

            // 銷毀舊圖表
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

        formatCurrency(value) {
            const amount = value / 100; // 分 → 元
            return this.currency === 'TWD'
                ? `NT$ ${amount.toLocaleString()}`
                : `$ ${amount.toLocaleString()}`;
        },

        getChangeClass(percent) {
            return percent >= 0 ? 'positive' : 'negative';
        }
    }
};
```

### 4.4 載入狀態處理

**骨架屏 (Skeleton Screen):**
```html
<div v-if="loading" class="stats-grid">
    <div class="stat-card animate-pulse">
        <div class="h-4 bg-slate-200 rounded w-1/2 mb-3"></div>
        <div class="h-8 bg-slate-300 rounded w-3/4 mb-2"></div>
        <div class="h-3 bg-slate-200 rounded w-1/3"></div>
    </div>
    <!-- 重複 3 次 -->
</div>
```

**錯誤狀態:**
```html
<div v-if="error" class="text-center py-12">
    <svg class="w-16 h-16 text-red-500 mx-auto mb-4"><!-- 錯誤圖示 --></svg>
    <p class="text-red-600 mb-4">{{ error }}</p>
    <button @click="loadAllData" class="btn-primary">重新載入</button>
</div>
```

---

## 5. 資料庫查詢優化

### 5.1 FluentCart 資料表結構

**核心資料表:**

| 資料表 | 用途 | 索引建議 |
|--------|------|---------|
| `fct_orders` | 訂單主表 | `customer_id`, `created_at`, `payment_status` |
| `fct_order_items` | 訂單項目 | `order_id`, `post_id` |
| `fct_customers` | 客戶資料 | `created_at`, `status` |
| `fct_order_transactions` | 交易記錄 | `order_id`, `created_at` |

### 5.2 效能優化策略

**1. 複合索引:**
```sql
-- 加速訂單統計查詢
CREATE INDEX idx_orders_stats
ON wp_fct_orders (payment_status, created_at, total_amount);

-- 加速商品銷售查詢
CREATE INDEX idx_items_sales
ON wp_fct_order_items (post_id, order_id);
```

**2. 查詢優化原則:**
- 避免 `SELECT *`,只查詢需要的欄位
- 使用 `LIMIT` 限制結果數量
- 日期範圍查詢使用索引友善的條件 (例如 `created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)`)
- 聚合查詢 (SUM, COUNT) 配合 `GROUP BY` 使用索引

**3. 慢查詢監控:**
```php
// 在 DashboardService 中加入查詢時間記錄
$start_time = microtime(true);
$result = $wpdb->get_results($query);
$query_time = microtime(true) - $start_time;

if ($query_time > 1.0) {
    error_log("Slow query detected: {$query_time}s - {$query}");
}
```

---

## 6. 實作優先順序

### 階段一: 基礎架構 (2-3 天)

**目標:** 建立 API 和服務層基礎

- [ ] 建立 `DashboardService` 類別
- [ ] 實作 `/dashboard/stats` API
- [ ] 建立 `dashboard.php` 頁面骨架
- [ ] 測試 API 回應和權限檢查

**驗收標準:**
- API 回傳正確格式的 JSON
- 權限檢查正常運作
- 頁面可正常載入 (即使無內容)

### 階段二: 統計卡片 (2-3 天)

**目標:** 顯示 4 個統計卡片

- [ ] 實作統計卡片 Vue 組件
- [ ] 整合 `DashboardService::calculateStats()`
- [ ] 實作變化百分比計算邏輯
- [ ] 響應式樣式調整

**驗收標準:**
- 卡片顯示正確數據
- 變化百分比正確計算 (與上月比較)
- 手機版佈局正常

### 階段三: 營收趨勢圖 (2-3 天)

**目標:** 整合 Chart.js 顯示趨勢圖

- [ ] 載入 Chart.js CDN
- [ ] 實作 `/dashboard/revenue` API
- [ ] 實作 Vue 圖表渲染邏輯
- [ ] 響應式圖表調整

**驗收標準:**
- 折線圖正確顯示 30 天數據
- 支援幣別切換 (TWD/USD/CNY)
- 手機版圖表可讀性佳

### 階段四: 商品銷售圖表 (1-2 天)

**目標:** 圓餅圖顯示 Top 5 商品

- [ ] 實作 `/dashboard/products` API
- [ ] 實作圓餅圖組件
- [ ] 資料百分比計算

**驗收標準:**
- 圓餅圖顯示 Top 5 商品
- 顏色區分清晰
- Hover 顯示詳細資訊

### 階段五: 活動列表 (1-2 天)

**目標:** 顯示最近 10 筆活動

- [ ] 實作 `/dashboard/activities` API
- [ ] 實作活動列表組件
- [ ] 時間格式化 (相對時間)

**驗收標準:**
- 顯示最近 10 筆活動
- 時間顯示為「5 分鐘前」、「2 小時前」
- 點擊可跳轉到詳情頁

### 階段六: 優化和測試 (1-2 天)

**目標:** 效能優化和測試

- [ ] 實作快取機制
- [ ] 查詢效能測試 (< 500ms)
- [ ] 跨瀏覽器測試
- [ ] 錯誤處理完善

**驗收標準:**
- 所有 API < 500ms (首次載入)
- 快取機制正常運作
- 無 console 錯誤

**總預估時間:** 9-15 天 (1.5-3 週)

---

## 7. 風險和挑戰

### 7.1 技術風險

| 風險 | 嚴重性 | 緩解策略 |
|------|--------|---------|
| **FluentCart 資料表變更** | 中 | 透過 Model 層查詢,避免直接 SQL;定期檢查 FluentCart 更新日誌 |
| **大量訂單查詢慢** | 高 | 實作快取;使用索引;限制查詢範圍 (30 天) |
| **Chart.js CDN 失效** | 低 | 備用 CDN (cdnjs);考慮本地備份 |
| **響應式圖表顯示問題** | 中 | 測試多種螢幕尺寸;使用 `maintainAspectRatio: false` |

### 7.2 資料準確性挑戰

**問題:** FluentCart 的 `ltv`, `purchase_count` 等統計欄位可能不準確

**解決方案:**
- **不使用** FluentCart 預先計算的統計欄位
- **直接查詢** `fct_orders` 和 `fct_order_items` 表聚合計算
- 參考現有 `Customers_API` 的查詢模式:
  ```sql
  -- 直接從訂單表聚合計算,不使用 c.ltv 或 c.purchase_count
  SELECT
      COUNT(o.id) as order_count,
      COALESCE(SUM(o.total_amount), 0) as total_spent
  FROM wp_fct_orders o
  WHERE o.customer_id = ?
      AND o.payment_status = 'paid'
  ```

### 7.3 使用者體驗挑戰

**問題:** 載入時間可能影響體驗

**解決方案:**
1. **骨架屏:** 立即顯示載入佔位符
2. **漸進式載入:** 統計卡片先載入,圖表後載入
3. **快取策略:** 5-15 分鐘快取,減少資料庫壓力
4. **錯誤優雅降級:** 部分資料失敗不影響其他區塊

---

## 8. 程式碼範例

### 8.1 DashboardService 核心方法

**檔案:** `includes/services/class-dashboard-service.php`

```php
<?php
namespace BuyGoPlus\Services;

class DashboardService {

    private $debugService;

    public function __construct() {
        $this->debugService = new DebugService();
    }

    /**
     * 計算儀表板統計數據
     *
     * @return array 統計數據陣列
     */
    public function calculateStats() {
        global $wpdb;

        $table_orders = $wpdb->prefix . 'fct_orders';
        $current_month_start = date('Y-m-01 00:00:00');
        $last_month_start = date('Y-m-01 00:00:00', strtotime('-1 month'));
        $last_month_end = date('Y-m-t 23:59:59', strtotime('-1 month'));

        // 本月統計
        $current_stats = $wpdb->get_row($wpdb->prepare(
            "SELECT
                COUNT(*) as order_count,
                COALESCE(SUM(total_amount), 0) as total_revenue,
                COUNT(DISTINCT customer_id) as customer_count
             FROM {$table_orders}
             WHERE created_at >= %s
                 AND payment_status = 'paid'",
            $current_month_start
        ), ARRAY_A);

        // 上月統計 (用於計算變化百分比)
        $last_stats = $wpdb->get_row($wpdb->prepare(
            "SELECT
                COUNT(*) as order_count,
                COALESCE(SUM(total_amount), 0) as total_revenue,
                COUNT(DISTINCT customer_id) as customer_count
             FROM {$table_orders}
             WHERE created_at BETWEEN %s AND %s
                 AND payment_status = 'paid'",
            $last_month_start,
            $last_month_end
        ), ARRAY_A);

        // 計算變化百分比
        $revenue_change = $this->calculateChangePercent(
            $current_stats['total_revenue'],
            $last_stats['total_revenue']
        );

        $order_change = $this->calculateChangePercent(
            $current_stats['order_count'],
            $last_stats['order_count']
        );

        $customer_change = $this->calculateChangePercent(
            $current_stats['customer_count'],
            $last_stats['customer_count']
        );

        // 平均訂單價值
        $avg_order_value = $current_stats['order_count'] > 0
            ? round($current_stats['total_revenue'] / $current_stats['order_count'])
            : 0;

        $last_avg_order_value = $last_stats['order_count'] > 0
            ? round($last_stats['total_revenue'] / $last_stats['order_count'])
            : 0;

        $avg_change = $this->calculateChangePercent($avg_order_value, $last_avg_order_value);

        return [
            'total_revenue' => [
                'value' => (int)$current_stats['total_revenue'],
                'currency' => 'TWD',
                'change_percent' => $revenue_change,
                'period' => '本月'
            ],
            'total_orders' => [
                'value' => (int)$current_stats['order_count'],
                'change_percent' => $order_change,
                'period' => '本月'
            ],
            'total_customers' => [
                'value' => (int)$current_stats['customer_count'],
                'change_percent' => $customer_change,
                'period' => '本月'
            ],
            'avg_order_value' => [
                'value' => $avg_order_value,
                'currency' => 'TWD',
                'change_percent' => $avg_change,
                'period' => '本月'
            ]
        ];
    }

    /**
     * 計算變化百分比
     *
     * @param int $current 當前值
     * @param int $previous 前期值
     * @return float 變化百分比 (正數=成長,負數=下降)
     */
    private function calculateChangePercent($current, $previous) {
        if ($previous == 0) {
            return $current > 0 ? 100.0 : 0.0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    /**
     * 取得營收趨勢資料
     *
     * @param int $days 天數 (預設 30)
     * @param string $currency 幣別 (預設 TWD)
     * @return array Chart.js 格式的資料
     */
    public function getRevenueTrend($days = 30, $currency = 'TWD') {
        global $wpdb;

        $table_orders = $wpdb->prefix . 'fct_orders';
        $start_date = date('Y-m-d 00:00:00', strtotime("-{$days} days"));

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT
                DATE(created_at) as date,
                COALESCE(SUM(total_amount), 0) as daily_revenue
             FROM {$table_orders}
             WHERE created_at >= %s
                 AND payment_status = 'paid'
                 AND currency = %s
             GROUP BY DATE(created_at)
             ORDER BY date ASC",
            $start_date,
            $currency
        ), ARRAY_A);

        // 填補缺失日期 (沒有訂單的日期顯示 0)
        $labels = [];
        $data = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $labels[] = date('m/d', strtotime($date));

            // 查找該日期的營收
            $found = false;
            foreach ($results as $row) {
                if ($row['date'] === $date) {
                    $data[] = (int)$row['daily_revenue'];
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                $data[] = 0;
            }
        }

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => '營收',
                    'data' => $data,
                    'borderColor' => '#3b82f6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'tension' => 0.4
                ]
            ],
            'currency' => $currency
        ];
    }
}
```

### 8.2 Dashboard API 註冊

**檔案:** `includes/api/class-dashboard-api.php`

```php
<?php
namespace BuyGoPlus\Api;

use BuyGoPlus\Services\DashboardService;

class Dashboard_API {

    private $namespace = 'buygo-plus-one/v1';
    private $dashboardService;

    public function __construct() {
        $this->dashboardService = new DashboardService();
    }

    /**
     * 註冊 REST API 路由
     */
    public function register_routes() {
        // GET /dashboard/stats
        register_rest_route($this->namespace, '/dashboard/stats', [
            'methods' => 'GET',
            'callback' => [$this, 'get_stats'],
            'permission_callback' => [API::class, 'check_permission']
        ]);

        // GET /dashboard/revenue
        register_rest_route($this->namespace, '/dashboard/revenue', [
            'methods' => 'GET',
            'callback' => [$this, 'get_revenue'],
            'permission_callback' => [API::class, 'check_permission'],
            'args' => [
                'period' => [
                    'default' => 30,
                    'sanitize_callback' => 'absint'
                ],
                'currency' => [
                    'default' => 'TWD',
                    'sanitize_callback' => 'sanitize_text_field'
                ]
            ]
        ]);
    }

    /**
     * 取得統計數據
     */
    public function get_stats($request) {
        try {
            $cache_key = 'buygo_dashboard_stats';
            $cached = get_transient($cache_key);

            if ($cached !== false) {
                return new \WP_REST_Response([
                    'success' => true,
                    'data' => $cached,
                    'cached_at' => get_transient($cache_key . '_time')
                ], 200);
            }

            $stats = $this->dashboardService->calculateStats();
            set_transient($cache_key, $stats, 5 * MINUTE_IN_SECONDS);
            set_transient($cache_key . '_time', current_time('mysql'), 5 * MINUTE_IN_SECONDS);

            return new \WP_REST_Response([
                'success' => true,
                'data' => $stats,
                'cached_at' => current_time('mysql')
            ], 200);

        } catch (\Exception $e) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 取得營收趨勢
     */
    public function get_revenue($request) {
        try {
            $params = $request->get_params();
            $period = $params['period'] ?? 30;
            $currency = $params['currency'] ?? 'TWD';

            $cache_key = "buygo_dashboard_revenue_{$period}_{$currency}";
            $cached = get_transient($cache_key);

            if ($cached !== false) {
                return new \WP_REST_Response([
                    'success' => true,
                    'data' => $cached
                ], 200);
            }

            $revenue_data = $this->dashboardService->getRevenueTrend($period, $currency);
            set_transient($cache_key, $revenue_data, 15 * MINUTE_IN_SECONDS);

            return new \WP_REST_Response([
                'success' => true,
                'data' => $revenue_data
            ], 200);

        } catch (\Exception $e) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
```

---

## 9. 測試計畫

### 9.1 單元測試

**測試範圍:**
- `DashboardService::calculateStats()` - 統計計算正確性
- `DashboardService::calculateChangePercent()` - 百分比計算邏輯
- `DashboardService::getRevenueTrend()` - 趨勢資料格式

**測試框架:** PHPUnit (現有專案使用)

### 9.2 整合測試

**測試場景:**
1. API 端點回應格式驗證
2. 權限檢查 (nonce 驗證)
3. 快取機制運作
4. 錯誤處理 (資料庫連線失敗)

### 9.3 前端測試

**手動測試項目:**
- [ ] 統計卡片數據正確
- [ ] 圖表渲染正常
- [ ] 響應式佈局 (桌面 / 平板 / 手機)
- [ ] 載入狀態顯示
- [ ] 錯誤狀態處理
- [ ] 幣別切換功能

---

## 10. 參考資料

### 10.1 技術文件

- Chart.js 官方文件: https://www.chartjs.org/docs/latest/
- FluentCart 資料庫模型: `fluentcart-payuni/docs/fluentcart-reference/fluentcart.com_doc/database_models_*.md`
- BuyGo Plus One 架構: `.planning/codebase/ARCHITECTURE.md`

### 10.2 類似實作參考

- FluentCart Dashboard API: `restapi_operations_dashboard_get-dashboard-stats.md`
- 現有 Customers API: `includes/api/class-customers-api.php`
- 現有 Orders API: `includes/api/class-orders-api.php`

---

## 附錄 A: Chart.js 圖表範例

### A.1 折線圖 (營收趨勢)

```javascript
new Chart(ctx, {
    type: 'line',
    data: {
        labels: ['01/01', '01/02', '01/03'],
        datasets: [{
            label: '營收',
            data: [12000, 15000, 13500],
            borderColor: '#3b82f6',
            backgroundColor: 'rgba(59, 130, 246, 0.1)',
            tension: 0.4,
            fill: true
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            },
            tooltip: {
                mode: 'index',
                intersect: false,
                callbacks: {
                    label: function(context) {
                        return 'NT$ ' + (context.parsed.y / 100).toLocaleString();
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return 'NT$ ' + (value / 100).toLocaleString();
                    }
                }
            }
        }
    }
});
```

### A.2 圓餅圖 (商品銷售)

```javascript
new Chart(ctx, {
    type: 'doughnut',
    data: {
        labels: ['商品 A', '商品 B', '商品 C', '商品 D', '商品 E'],
        datasets: [{
            data: [30, 25, 20, 15, 10],
            backgroundColor: [
                '#3b82f6',
                '#8b5cf6',
                '#ec4899',
                '#f59e0b',
                '#10b981'
            ]
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'right'
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        const label = context.label || '';
                        const value = context.parsed;
                        return label + ': ' + value + '%';
                    }
                }
            }
        }
    }
});
```

---

**文件版本:** 1.0
**最後更新:** 2026-01-29
**下一步:** 進入實作階段,建立 DashboardService 和 Dashboard_API

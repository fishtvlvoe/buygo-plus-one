# BuyGo Portal 一秒載入計劃

## 目標
用戶登入後，BuyGo 所有頁面在 1 秒內顯示有意義的內容。
不論資料量（千筆訂單、萬張圖片）、不論主機速度。

## 現狀

### 已有的基礎（不用重建）
- `template.php` 已有 PHP 端初始資料注入（`buygoInitialData`）
- `BuyGoCache.js` 已有 sessionStorage 快取（5 分鐘 TTL）
- 骨架屏已在部分頁面實作
- Inline 載入策略已解決 WAF 問題

### 需要解決的問題

| 問題 | 影響 | 根因 |
|------|------|------|
| 大檔案難維護 | 改一行要讀 1400 行 | JS 元件未拆分 |
| 頁面切換重複 Loading | 每次切頁都重新打 API | BuyGoCache 只用 sessionStorage，沒有記憶體快取 |
| 初始資料注入只對第一個頁面有效 | 切到其他頁面仍要等 | 沒有預載其他頁面的資料 |
| 大量資料載入慢 | 3000 筆訂單全部渲染 | 沒有虛擬滾動 |

### 大檔案清單

| 檔案 | 行數 | 說明 |
|------|------|------|
| settings.php | 2581 | WP 後台設定頁面（非 Portal） |
| OrdersPage.js | 1418 | 訂單管理頁面 |
| ProductsPage.js | 1130 | 商品管理頁面 |
| ShipmentDetailsPage.js | 899 | 出貨詳情頁面 |
| DesignSystem.js | 827 | 設計系統元件庫 |
| admin-settings.js | 679 | WP 後台設定 JS |
| CustomersPage.js | 607 | 客戶管理頁面 |
| ShipmentProductsPage.js | 606 | 出貨商品頁面 |

---

## 執行計劃

### Step 1：熵減 — 拆分大檔案
**目標**：每個檔案 < 300 行，一個檔案一件事

#### 1a. OrdersPage.js（1418 行 → 5 個檔案）
```
OrdersPage.js (1418行)
├→ OrdersPage.js          (~150行) 主頁面：組裝 + 路由
├→ OrdersTable.js         (~200行) 表格元件：列表 + 排序 + 分頁
├→ OrderDetail.js         (~250行) 訂單詳情 Modal
├→ OrderFilters.js        (~150行) 篩選器：狀態 + 日期 + 搜尋
└→ useOrders.js           (~200行) 資料邏輯：API 呼叫 + 狀態管理
```

#### 1b. ProductsPage.js（1130 行 → 5 個檔案）
```
ProductsPage.js (1130行)
├→ ProductsPage.js        (~150行) 主頁面
├→ ProductsTable.js       (~200行) 表格元件
├→ ProductDetail.js       (~250行) 商品詳情 Modal
├→ ProductFilters.js      (~100行) 篩選器
└→ useProducts.js         (~200行) 資料邏輯
```

#### 1c. ShipmentDetailsPage.js（899 行 → 4 個檔案）
```
ShipmentDetailsPage.js (899行)
├→ ShipmentDetailsPage.js (~150行) 主頁面
├→ ShipmentItemsTable.js  (~200行) 明細表格
├→ ShipmentActions.js     (~150行) 操作按鈕群
└→ useShipments.js        (~200行) 資料邏輯
```

#### 1d. ShipmentProductsPage.js（606 行 → 3 個檔案）
```
ShipmentProductsPage.js (606行)
├→ ShipmentProductsPage.js (~150行) 主頁面
├→ ShipmentProductsList.js (~200行) 商品列表
└→ useShipmentProducts.js  (~150行) 資料邏輯
```

#### 1e. settings.php（2581 行 → 拆分 Tab）
```
settings.php (2581行)
├→ settings.php           (~100行) 主殼：Tab 切換
├→ tabs/general-tab.php   (~200行)
├→ tabs/roles-tab.php     (~200行)
├→ tabs/templates-tab.php (~300行) ← 已獨立
├→ tabs/developer-tab.php (~200行) ← 已獨立
└→ ... 其他 Tab
```

### Step 2：升級快取層
**目標**：BuyGoCache 從 sessionStorage 升級為記憶體 + 背景更新

```
目前 BuyGoCache（36 行）
  └→ sessionStorage 讀寫，5 分鐘 TTL

升級為 BuyGoCache v2（~120 行）
  ├→ 記憶體快取（最快，頁面切換用）
  ├→ sessionStorage 備份（刷新頁面用）
  ├→ SWR 策略：先回傳快取 → 背景更新 → 有變化才通知
  └→ 預載機制：登入後背景抓取 3 大頁面資料
```

快取流程：
```
用戶點「訂單」
  → 記憶體有資料？ → 立刻顯示（0.05s）→ 背景打 API 檢查更新
  → 記憶體沒有、sessionStorage 有？ → 顯示（0.1s）→ 背景更新
  → 都沒有？ → 顯示骨架屏 → 打 API → 顯示資料 → 存入快取
```

### Step 3：預載策略
**目標**：登入後背景自動抓取常用頁面資料

```
用戶登入進入 BuyGo
  │
  ├→ 立刻渲染當前頁面（靠 PHP 注入的 buygoInitialData）
  │
  └→ 背景靜默預載（不阻塞畫面）：
      ├→ GET /buygo-plus-one/v1/orders?per_page=20
      ├→ GET /buygo-plus-one/v1/products?per_page=20
      └→ GET /buygo-plus-one/v1/shipments/products

      全部存入 BuyGoCache
      用戶切頁時立刻可用
```

### Step 4：大量資料優化
**目標**：不論 100 筆還是 10000 筆，渲染速度一樣

| 技術 | 做法 | 效果 |
|------|------|------|
| API 分頁 | 已有（per_page=20） | 每次只傳 20 筆 |
| 虛擬滾動 | 只渲染畫面上可見的行（~15 行） | 不論總量，DOM 永遠只有 15 行 |
| 圖片懶載入 | `loading="lazy"` | 滾到才載入圖片 |
| 骨架屏 | 資料載入中顯示灰色方塊 | 用戶感知 0 等待 |

---

## 執行順序與依賴關係

```
Step 1a: 拆 OrdersPage.js ─────────────────┐
Step 1b: 拆 ProductsPage.js ────────────────┤
Step 1c: 拆 ShipmentDetailsPage.js ─────────┤ 可平行
Step 1d: 拆 ShipmentProductsPage.js ────────┤
Step 1e: 拆 settings.php ──────────────────┘
                  │
                  ▼ 全部拆完後
Step 2: 升級 BuyGoCache ──────────┐
                                   │ 依序
Step 3: 加入預載策略 ─────────────┘
                  │
                  ▼
Step 4: 虛擬滾動 + 圖片懶載入
                  │
                  ▼
        驗證：所有頁面 < 1 秒
```

## 每步的驗收標準

| Step | 驗收標準 |
|------|---------|
| 1 | 所有 JS 檔案 < 300 行，功能不變，測試通過 |
| 2 | 切換已訪問頁面 < 0.1 秒顯示資料 |
| 3 | 登入後切到其他頁面 < 0.3 秒顯示資料 |
| 4 | 3000 筆訂單列表滾動流暢，無卡頓 |

## Git 策略

- 分支：`feature/performance-optimization`
- 每個 Step 的每個子步驟一次 commit
- Step 1 完成後先部署測試，確認功能不變
- Step 2-4 逐步部署驗證

## 預估工作量

| Step | 檔案數 | 說明 |
|------|--------|------|
| Step 1 | ~20 個檔案 | 純拆分重構，不改功能邏輯 |
| Step 2 | 1 個檔案 | BuyGoCache.js 升級 |
| Step 3 | 2 個檔案 | template.php + 新增 preloader.js |
| Step 4 | 3-5 個檔案 | 表格元件加虛擬滾動 |

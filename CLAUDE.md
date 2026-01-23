# BuyGo+1 - Claude Code 專案指南

> ⚠️ **重要**：這是 Claude Code 每次對話開始時自動讀取的專案說明檔。
>
> **請在修改任何代碼前，先閱讀「已修復問題清單」和「修改前檢查清單」！**

---

## 🚨 已修復問題清單（絕對不能再壞掉！）

### 問題 1：LINE 上架沒有反應

**症狀**：用戶從 LINE 發送圖片和文字，官方帳號沒有反應

**根本原因**：
1. Channel Secret 讀取位置錯誤
2. HTTP Header 大小寫錯誤（`X-Line-Signature` vs `x-line-signature`）
3. REST API 權限設定錯誤（`permission_callback` 不能用 `verify_signature`）
4. 權限檢查使用舊的 option 而非 `wp_buygo_helpers` 資料表

**修復位置**：
- `/includes/api/class-line-webhook-api.php` - 簽名驗證
- `/includes/services/class-line-webhook-handler.php` - 權限檢查

**關鍵代碼（不要修改！）**：
```php
// class-line-webhook-api.php
// ✅ 必須使用小寫
$signature = $request->get_header('x-line-signature');

// ✅ permission_callback 必須是 __return_true
'permission_callback' => '__return_true'

// ✅ 從正確位置讀取 Channel Secret
$channel_secret = \BuyGo_Core::settings()->get('line_channel_secret', '');
```

**驗證方法**：LINE Developers Console 點擊「驗證」應返回 200 OK

---

### 問題 2：找不到客戶 UID

**症狀**：客戶頁面顯示空白或找不到用戶

**根本原因**：
- 用戶 LINE ID 沒有正確綁定到 WordPress 用戶
- 查詢時使用錯誤的 meta_key

**修復位置**：
- `/includes/services/class-line-service.php` - 用戶綁定邏輯
- `/includes/api/class-customers-api.php` - 客戶查詢

**關鍵代碼（不要修改！）**：
```php
// 正確的 meta_key
$meta_key = 'line_user_id';  // ✅ 不是 'buygo_line_id' 或其他

// 正確的查詢方式
$users = get_users([
    'meta_key' => 'line_user_id',
    'meta_value' => $line_uid
]);
```

**驗證方法**：客戶頁面應顯示有 LINE 綁定的用戶

---

### 問題 3：產品跟單問題

**症狀**：產品的訂單數量計算錯誤，或無法正確關聯訂單

**根本原因**：
- 父子訂單邏輯混淆
- 統計時重複計算父訂單和子訂單

**修復位置**：
- `/includes/services/class-product-service.php` - 訂單統計
- `/includes/services/class-order-service.php` - 父子訂單邏輯

**關鍵代碼（不要修改！）**：
```php
// 統計時只計算「沒有子訂單的訂單」或「子訂單」
// 避免父訂單被重複計算
$orders = $this->get_orders_for_product($product_id);
foreach ($orders as $order) {
    // ✅ 如果是父訂單且有子訂單，跳過（避免重複計算）
    if ($order->has_child_orders()) {
        continue;
    }
    // 計算邏輯...
}
```

**驗證方法**：產品的「已下單」數量應等於所有獨立訂單項目的總和

---

### 問題 4：搜尋框沒有功能

**症狀**：輸入關鍵字後，列表沒有過濾

**根本原因**：
1. `smart-search-box` 組件的事件沒有正確觸發
2. 頁面的 `handleProductSearch` 方法沒有正確綁定
3. API 的 `search` 參數沒有傳遞

**修復位置**：
- `/components/shared/smart-search-box.php` - 搜尋組件
- `/admin/partials/products.php` - 事件處理
- `/includes/api/class-products-api.php` - 搜尋參數處理

**關鍵代碼（不要修改！）**：
```javascript
// smart-search-box.php 必須 emit 這些事件
this.$emit('search', searchQuery);
this.$emit('select', item);
this.$emit('clear');

// products.php 必須監聽這些事件
<smart-search-box
    @search="handleProductSearch"
    @select="handleProductSelect"
    @clear="handleProductSearchClear"
></smart-search-box>

// handleProductSearch 必須調用 API
const handleProductSearch = (query) => {
    globalSearchQuery.value = query;
    loadProducts();  // ← 這行很重要！
};
```

**驗證方法**：在搜尋框輸入文字，列表應即時過濾

---

### 問題 5：API 401 權限錯誤

**症狀**：頁面載入時顯示 401 Unauthorized

**根本原因**：
- fetch 請求沒有帶 `X-WP-Nonce` header
- `wpNonce` 變數沒有定義

**修復位置**：
- 所有 `/admin/partials/*.php` 頁面

**關鍵代碼（每個頁面都必須有！）**：
```php
// 在 <script> 開頭定義
const wpNonce = '<?php echo wp_create_nonce("wp_rest"); ?>';

// 每個 fetch 都必須帶這個 header
fetch(url, {
    headers: {
        'X-WP-Nonce': wpNonce,
        'Content-Type': 'application/json'
    }
});
```

**驗證方法**：開啟 DevTools Network，確認所有 API 請求都返回 200

---

## ✅ 修改前檢查清單

**在修改任何頁面代碼之前，必須確認以下事項：**

### 修改 products.php 前

- [ ] 確認 `wpNonce` 變數存在且在 setup() 外部定義
- [ ] 確認 `smart-search-box` 的三個事件（@search, @select, @clear）都有綁定
- [ ] 確認 `handleProductSearch` 會調用 `loadProducts()`
- [ ] 確認所有 fetch 都帶有 `X-WP-Nonce` header
- [ ] 確認新增的 CSS 類名都有 `products-` 前綴
- [ ] 確認新增的 JavaScript 變數不與現有變數衝突

### 修改 orders.php 前

- [ ] 確認 `wpNonce` 變數存在
- [ ] 確認父子訂單邏輯沒有被破壞
- [ ] 確認 `shipping_status` 同步邏輯正確
- [ ] 確認新增的 CSS 類名都有 `orders-` 前綴

### 修改 LINE 相關代碼前

- [ ] 確認 Channel Secret 讀取使用 `\BuyGo_Core::settings()->get('line_channel_secret')`
- [ ] 確認 HTTP Header 使用小寫 `x-line-signature`
- [ ] 確認 `permission_callback` 是 `__return_true`
- [ ] 確認權限檢查使用 `wp_buygo_helpers` 資料表

### 修改 API 代碼前

- [ ] 確認 `check_permission()` 方法邏輯正確
- [ ] 確認 endpoint 的 `permission_callback` 設定正確
- [ ] 確認錯誤回傳格式一致

---

## ✅ 修改後驗證清單

**每次修改代碼後，必須驗證以下功能沒有壞掉：**

### 基本功能驗證（每次都要做）

- [ ] 所有頁面可以正常載入（無 JS 錯誤）
- [ ] 所有 API 請求返回 200（無 401/500）
- [ ] 搜尋框可以正常搜尋
- [ ] 分頁可以正常切換

### 商品頁驗證

- [ ] 商品列表正常顯示
- [ ] 搜尋框輸入後列表會過濾
- [ ] 點擊編輯可以進入編輯頁
- [ ] 點擊下單名單可以看到訂單
- [ ] 採購數量可以編輯並保存

### 訂單頁驗證

- [ ] 訂單列表正常顯示
- [ ] 父訂單和子訂單正確顯示
- [ ] 點擊訂單可以看到詳情
- [ ] 狀態切換功能正常

### LINE 功能驗證

- [ ] LINE Developers Console 驗證返回 200
- [ ] 從 LINE 發送圖片，官方帳號有回應
- [ ] 從 LINE 發送商品文字，商品能建立

---

## 🔧 Debug 快速診斷

當用戶報告 Bug 時，**不要等截圖**，直接執行：

### 快速診斷命令

```bash
# 1. 查詢最新 Webhook 日誌
cd "/Users/fishtv/Local Sites/buygo" && ./db-query.sh "SELECT id, event_type, LEFT(event_data, 150), created_at FROM wp_buygo_webhook_logs ORDER BY id DESC LIMIT 15"

# 2. 查詢錯誤日誌
cd "/Users/fishtv/Local Sites/buygo" && ./db-query.sh "SELECT event_type, event_data FROM wp_buygo_webhook_logs WHERE event_type = 'error' ORDER BY id DESC LIMIT 10"

# 3. 查詢 LINE 設定
cd "/Users/fishtv/Local Sites/buygo" && ./db-query.sh "SELECT option_name, LEFT(option_value, 30), LENGTH(option_value) FROM wp_options WHERE option_name LIKE 'buygo_line%'"
```

### 常見錯誤對照表

| 日誌事件 | 錯誤原因 | 解決方案 |
|----------|----------|----------|
| `signature_verification_failed` | Channel Secret 讀取錯誤 | 檢查 `SettingsService` 解密邏輯 |
| `permission_denied` | 權限不足 | 檢查 `wp_buygo_helpers` 資料表 |
| `401 Unauthorized` | wpNonce 缺失 | 檢查 fetch 的 headers |
| 搜尋無反應 | 事件未綁定 | 檢查 @search 事件和 handleProductSearch |

---

## 📁 關鍵檔案位置

```
/includes/
  /services/
    class-settings-service.php      # 設定讀取/解密（LINE 設定）
    class-line-webhook-handler.php  # LINE 訊息處理（權限檢查）
    class-product-service.php       # 商品邏輯（訂單統計）
    class-order-service.php         # 訂單邏輯（父子訂單）
  /api/
    class-line-webhook-api.php      # 簽名驗證（Header 大小寫）
    class-products-api.php          # 商品 API（搜尋參數）

/admin/partials/
    products.php    # 商品頁（wpNonce、搜尋事件）
    orders.php      # 訂單頁（wpNonce、父子訂單）
    customers.php   # 客戶頁（wpNonce、UID 查詢）

/components/shared/
    smart-search-box.php  # 搜尋組件（事件 emit）
```

---

## 🛡️ CSS/JavaScript 命名規範

### CSS 類名前綴

每個頁面的自訂 CSS 必須使用前綴，避免衝突：

| 頁面 | 前綴 | 範例 |
|------|------|------|
| products.php | `products-` | `.products-header`, `.products-search` |
| orders.php | `orders-` | `.orders-header`, `.orders-modal` |
| customers.php | `customers-` | `.customers-list`, `.customers-card` |
| shipment-*.php | `shipment-` | `.shipment-table`, `.shipment-row` |
| settings.php | `settings-` | `.settings-tab`, `.settings-form` |

### JavaScript 變數命名

- 頁面特定變數應有明確的命名空間
- 避免使用通用名稱如 `data`, `items`, `loading`
- 使用更具體的名稱如 `productsData`, `orderItems`, `customersLoading`

---

## 📖 相關文件

- `/ARCHITECTURE.md` - 技術架構（資料庫、API、LINE 整合）
- `/FRONTEND-ARCHITECTURE.md` - 前端架構重構計劃
- `/LAUNCH-PLAN.md` - 發布計劃和時間表
- `/TODO-BUYGO.md` - 待完成任務清單

---

## 💡 開發原則

1. **修改前先讀檢查清單** - 避免破壞已修復的功能
2. **修改後做驗證** - 確保沒有副作用
3. **使用命名空間** - CSS 類名和 JavaScript 變數都要有前綴
4. **小步迭代** - 每次只修改一個功能，驗證後再繼續
5. **有疑問就問** - 不確定的地方，先與用戶確認

---

## ✅ 2026-01-23 修復記錄（已完成）

### 環境說明

| 環境 | 說明 |
|------|------|
| **網域** | buygo.me（DNS A Record 指向 InstaWP） |
| **主機** | InstaWP 雲端開發環境 |
| **掛載路徑** | `/Volumes/insta-mount/`（直接連接雲端，修改立即生效） |
| **舊外掛** | `buygo`（客戶目前使用中） |
| **新外掛** | `buygo-plus-one-dev`（開發中，將取代舊外掛） |
| **兩個外掛狀態** | 同時啟用 |
| **資料庫版本** | `1.1.0`（新增出貨單資料表） |

### 已修復的 Bug

#### ✅ Bug 1：訂單詳情 401/403 錯誤
- **根本原因**：`wpNonce` 在 setup() 中定義但未在 return 中導出
- **修復**：
  1. `order-detail-modal.php` - 加入 wpNonce prop + X-WP-Nonce headers
  2. `orders.php` - 傳遞 :wp-nonce="wpNonce" + 在 return 中加入 wpNonce

#### ✅ Bug 2：庫存分配頁面顯示 0 筆訂單
- **根本原因**：`wp_buygo_shipment_items` 資料表不存在
- **修復**：
  1. `class-database.php` - 加入 shipments 和 shipment_items 資料表建立
  2. `class-plugin.php` - 加入 maybe_upgrade_database() 自動升級機制

#### ✅ Bug 3：產品名稱顯示「預設」
- **根本原因**：只讀取 $item['title']，未讀取 variation_title
- **修復**：`class-order-service.php` - 從 fct_product_variations 表讀取 variation_title

#### ✅ Bug 4：SQL NULL 問題
- **根本原因**：`NOT IN` 對 NULL 值返回 NULL
- **修復**：`class-allocation-service.php` - 改為 `(IS NULL OR NOT IN (...))`

### 已修改的檔案清單

```
/Volumes/insta-mount/wp-content/plugins/buygo-plus-one-dev/
├── components/order/order-detail-modal.php
│   ├── 加入 wpNonce prop（required: true）
│   └── 5 個 fetch 加入 X-WP-Nonce header
├── admin/partials/orders.php
│   ├── 傳遞 :wp-nonce="wpNonce" 給 order-detail-modal
│   └── return 中加入 wpNonce
├── includes/services/class-order-service.php
│   └── formatOrder() 讀取 variation_title
├── includes/services/class-allocation-service.php
│   ├── 修復 SQL NULL 問題
│   └── 加入除錯日誌
├── includes/api/class-api.php
│   └── check_permission() 加入除錯日誌
├── includes/class-database.php
│   ├── 加入 create_shipments_table()
│   └── 加入 create_shipment_items_table()
└── includes/class-plugin.php
    └── 加入 maybe_upgrade_database() 版本升級機制
```

### 待處理事項

#### 1. 版本更新機制
- [ ] 建立本地開發 → 雲端部署的流程
- [ ] 考慮使用 Git 進行版本控制

#### 2. UI 調整
- [ ] 等 UI 設計完成後再進行調整
- [ ] 目前功能可用，UI 可後續優化

#### 3. 多樣式商品
- [ ] 目前會拆分成多筆同名訂單
- [ ] 需要改進：顯示變體名稱（如顏色、尺寸）
- [ ] 這是較大的功能調整，建議在重構期處理

#### 4. 完整流程測試
- [ ] 測試：上架商品 → 訂單 → 備貨 → 分配 → 出貨
- [ ] 與客戶一起進行實際測試

### Debug 命令

```bash
# 檢查權限日誌
tail -50 /Volumes/insta-mount/wp-content/buygo-plus-one.log | grep PERMISSION

# 檢查分配日誌
tail -50 /Volumes/insta-mount/wp-content/buygo-plus-one.log | grep ALLOCATION

# 檢查資料庫升級
tail -50 /Volumes/insta-mount/wp-content/buygo-plus-one.log | grep UPGRADE
```

---

**最後更新**：2026-01-23
**維護者**：Development Team

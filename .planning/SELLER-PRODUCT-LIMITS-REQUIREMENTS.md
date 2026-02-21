# 賣家商品數量限制與 ID 對應系統

## 專案概述

將 BuyGo+1 外掛的賣家管理功能進行重構，移除「賣家類型」概念，改用統一的「商品數量限制」機制，並整合 FluentCart 自動賦予賣家權限的功能。

**分支**：`feature/seller-product-limits`

**狀態**：進行中

---

## 核心需求

### 1. 移除舊的賣家管理頁面

✅ **已完成**（Commit: 23d970a）

- 註解掉 `SellerManagementPage` 的註冊（`Plugin.php:158`）
- 在 `SellerManagementPage` 類別加入 `@deprecated` 標記
- 功能統一整合到「角色權限設定」頁面（`admin.php?page=buygo-settings&tab=roles`）

### 2. 隱藏「賣家類型」欄位

- **保留資料但隱藏 UI**
- `buygo_seller_type` user meta 保留在資料庫中（不刪除）
- 前端表格不再顯示「賣家類型」欄位和下拉選單
- 移除「真實賣家時 disabled」的邏輯

### 3. 修改「商品限制」欄位

- 所有賣家都可以編輯商品限制（移除 disabled 邏輯）
- **預設值改為 3**（目前是 2）
- 0 = 無限制
- 移除「真實賣家」與「測試賣家」的區別

### 4. 擴展「角色權限設定」頁面顯示

#### 表格結構變更

| 欄位名稱 | 現在 | 改造後 | 說明 |
|---------|------|--------|------|
| 使用者 | `張三` | `張三`<br>`WP-5` | 加入 WordPress User ID |
| Email | 保持不變 | 保持不變 | - |
| LINE ID | 保持不變 | 保持不變 | - |
| **角色** | `BuyGo 管理員` | `BuyGo 管理員`<br>`（無 BuyGo ID）` | 賣家無內部 ID |
| **角色**（小幫手） | `BuyGo 小幫手` | `BuyGo 小幫手`<br>`BuyGo-15` | 顯示 `wp_buygo_helpers.id` |
| 綁定關係 | 保持不變 | 保持不變 | - |
| **賣家類型** | ✅ 顯示 | ❌ **移除整個欄位** | 完全隱藏 |
| **商品限制** | 條件 disabled | **全部可編輯** | 0=無限，預設=3 |
| **操作** | 發送綁定 + 移除 | **只保留移除** | 移除「發送綁定」按鈕 |

#### 完整表格示意圖

| 使用者 | Email | LINE ID | 角色 | 綁定關係 | 商品限制 | 操作 |
|--------|-------|---------|------|----------|---------|------|
| 張三<br>WP-5 | zhang@example.com | ✅ U123... | **BuyGo 管理員**<br>（無 BuyGo ID） | 小幫手數量：2 個 | `[3]` 個商品 | 🗑️ 移除 |
| 李四<br>WP-8 | li@example.com | ✅ U456... | **BuyGo 小幫手**<br>BuyGo-15 | 綁定賣家：張三 | `[3]` 個商品 | 🗑️ 移除 |
| 王五<br>WP-12 | wang@example.com | ❌ 未綁定 | **BuyGo 管理員**<br>（無 BuyGo ID） | 無小幫手 | `[0]` 無限制 | 🗑️ 移除 |

### 5. FluentCart 整合

**目標**：用戶購買指定商品後，自動成為 BuyGo 賣家

#### 實作需求

1. **後台設定介面**
   - 在「角色權限設定」頁面（`buygo-settings&tab=roles`）新增設定區塊
   - 可以輸入「賣家商品 ID」（FluentCart Product ID）
   - 儲存到 WordPress options：`buygo_seller_product_id`

2. **監聽 FluentCart 訂單事件**
   - Hook: `fluent_cart/order_paid`（訂單付款完成）
   - 檢查訂單中是否包含指定的商品 ID
   - 如果包含，執行賦予權限流程

3. **自動賦予賣家權限**
   - 賦予 WordPress 角色：`buygo_admin`
   - 設定 user meta：
     - `buygo_seller_type` = `'test'`（保留但不顯示）
     - `buygo_product_limit` = `3`（預設商品限制）
   - 記錄 debug log

4. **LINE 綁定邏輯**
   - 如果用戶尚未綁定 LINE，無法從 LINE 上架商品
   - 不再提供「發送綁定連結」按鈕
   - 綁定 LINE 由用戶自行處理

### 6. 小幫手共享配額驗證

**目標**：小幫手上架商品時，計入所屬賣家的配額

#### 驗證邏輯

```
賣家配額檢查 = 賣家已上架數量 + 小幫手已上架數量 <= 賣家商品限制
```

#### 實作位置

- `ProductService` 或商品上架相關服務
- 查詢 `wp_buygo_helpers` 表取得賣家關係
- 統計賣家和所有小幫手的商品總數

---

## 資料庫結構

### 現有表格

#### `wp_buygo_helpers`（小幫手表）

```sql
CREATE TABLE wp_buygo_helpers (
    id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,    -- BuyGo 內部 ID
    helper_id bigint(20) UNSIGNED NOT NULL,            -- 小幫手的 WordPress User ID
    seller_id bigint(20) UNSIGNED NOT NULL,            -- 賣家的 WordPress User ID
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY unique_helper (helper_id, seller_id),
    KEY idx_seller (seller_id),
    KEY idx_helper (helper_id)
)
```

### User Meta（賣家相關）

- `buygo_seller_type`：賣家類型（`'test'` 或 `'real'`）**保留但不顯示**
- `buygo_product_limit`：商品數量限制（整數，0 = 無限制）

### Options（新增）

- `buygo_seller_product_id`：FluentCart 賣家商品 ID（整數）

---

## 技術細節

### 修改檔案清單

#### 必須修改

1. **`includes/admin/class-settings-page.php`**
   - `render_roles_tab()` 方法（約 654-890 行）
   - 修改表格 HTML 輸出
   - 加入 FluentCart 商品 ID 設定區塊

2. **`admin/js/admin-settings.js`**
   - 移除「賣家類型」下拉選單的 AJAX 邏輯
   - 修改「商品限制」輸入框的邏輯（移除 disabled）

3. **`includes/integrations/class-fluentcart-integration.php`**（新建或修改）
   - 監聽 `fluent_cart/order_paid` hook
   - 檢查商品 ID
   - 自動賦予 `buygo_admin` 角色

4. **`includes/services/class-product-service.php`**
   - 加入小幫手共享配額驗證邏輯

#### 選擇性修改

5. **`includes/services/class-settings-service.php`**
   - 可能需要新增 helper 方法來取得/設定 `buygo_seller_product_id`

---

## 測試需求

### 自動化測試（Playwright）

**測試腳本位置**：`/Users/fishtv/Local Sites/buygo/app/public/test-scripts/`

#### 測試案例

1. **角色權限頁面顯示**
   - 訪問 `https://test.buygo.me/wp-admin/admin.php?page=buygo-settings&tab=roles`
   - 驗證「使用者」欄位顯示 WP ID
   - 驗證「角色」欄位顯示 BuyGo ID（小幫手）
   - 驗證「賣家類型」欄位已隱藏
   - 驗證「商品限制」可編輯（無 disabled）
   - 驗證「發送綁定」按鈕已移除
   - 截圖保存

2. **FluentCart 自動賦予權限**
   - 模擬購買指定商品
   - 驗證用戶獲得 `buygo_admin` 角色
   - 驗證 user meta 正確設定

3. **小幫手配額驗證**
   - 模擬小幫手上架商品
   - 驗證配額計算正確（賣家 + 小幫手總數）

---

## 驗收標準

### UI 層面

- [ ] 角色權限頁面表格結構符合設計
- [ ] 「使用者」欄位顯示 WP ID
- [ ] 「角色」欄位顯示 BuyGo ID（小幫手）或「無 BuyGo ID」（賣家）
- [ ] 「賣家類型」欄位完全隱藏
- [ ] 「商品限制」所有人可編輯
- [ ] 「發送綁定」按鈕已移除
- [ ] FluentCart 商品 ID 設定區塊正常運作

### 功能層面

- [ ] 購買指定商品後自動賦予 `buygo_admin` 角色
- [ ] 預設商品限制設為 3
- [ ] 小幫手共享配額邏輯正確
- [ ] 配額驗證阻止超限上架

### 測試層面

- [ ] 所有 Playwright 測試通過
- [ ] 截圖驗證 UI 正確
- [ ] 無 Console 錯誤

---

## 已完成工作

### ✅ Commit: 23d970a

**標題**：refactor: 移除賣家管理頁面選單

**內容**：
- 註解掉 `SellerManagementPage` 的註冊（`Plugin.php:158`）
- 在 `SellerManagementPage` 類別加入 `@deprecated` 標記
- 功能統一整合到「角色權限設定」頁面（`buygo-settings&tab=roles`）
- 新流程：用戶從 FluentCart 購買「0 元賣家商品」後自動賦予 buygo_admin 角色

**修改檔案**：
- `includes/admin/class-seller-management-page.php`
- `includes/class-plugin.php`

---

## 待辦事項（供 GSD 使用）

### Phase 1：角色權限頁面 UI 改造

1. 修改「使用者」欄位（加入 WP ID）
2. 修改「角色」欄位（顯示 BuyGo ID）
3. 隱藏「賣家類型」欄位
4. 移除「發送綁定」按鈕
5. 修改「商品限制」欄位（移除 disabled 邏輯）
6. 測試並截圖驗證

### Phase 2：FluentCart 整合

1. 建立後台設定介面（商品 ID 輸入）
2. 監聽 `fluent_cart/order_paid` hook
3. 檢查商品 ID 並自動賦予角色
4. 設定預設商品限制為 3
5. 測試自動賦予流程

### Phase 3：小幫手共享配額

1. 修改商品上架驗證邏輯
2. 統計賣家和小幫手的商品總數
3. 驗證配額限制
4. 測試配額驗證邏輯

---

## 參考資料

### 相關檔案路徑

- 角色權限頁面：`includes/admin/class-settings-page.php`（654-890 行）
- 小幫手表結構：`includes/class-database.php`（415-437 行）
- 設定服務：`includes/services/class-settings-service.php`
- 商品服務：`includes/services/class-product-service.php`

### 資料庫查詢範例

```php
// 查詢小幫手的 BuyGo ID
global $wpdb;
$helper_id = $user->ID;
$buygo_id = $wpdb->get_var($wpdb->prepare(
    "SELECT id FROM {$wpdb->prefix}buygo_helpers WHERE helper_id = %d LIMIT 1",
    $helper_id
));
```

### FluentCart Hook 範例

```php
add_action('fluent_cart/order_paid', function($order) {
    $product_id = get_option('buygo_seller_product_id');

    foreach ($order->items as $item) {
        if ($item->product_id == $product_id) {
            $customer_id = $order->customer_id;
            // 賦予 buygo_admin 角色
            // 設定 user meta
            break;
        }
    }
}, 10, 1);
```

---

## 注意事項

1. **保留資料不刪除**：`buygo_seller_type` user meta 保留在資料庫，只是不在 UI 顯示
2. **預設值變更**：商品限制預設從 2 改為 3
3. **移除按鈕**：「發送綁定」按鈕完全移除，不是隱藏
4. **配額邏輯**：小幫手與賣家共享配額，不是獨立計算
5. **LINE 綁定**：沒綁定 LINE 的用戶即使購買商品也無法從 LINE 上架

---

## 聯絡資訊

- **專案**：BuyGo+1 WordPress Plugin
- **分支**：`feature/seller-product-limits`
- **測試環境**：https://test.buygo.me
- **後台路徑**：`/wp-admin/admin.php?page=buygo-settings&tab=roles`

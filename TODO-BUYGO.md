# BuyGo+1 待完成任務清單

> 更新日期：2026-01-22

---

## 開發工作流程

每完成一個功能，請依照以下流程執行：

1. **程式碼修改** - 完成功能開發
2. **Git 提交** - 提交變更並撰寫 commit message
3. **Chrome MCP 測試** - 使用 Chrome DevTools MCP 開啟頁面確認 UI
4. **Bug 修正** - 發現問題就修正，重複步驟 2-3 直到完成

---

## 待完成任務

### 1. 設定頁面 - 會員管理改版
**優先級：高**
**狀態：進行中**

**目前問題：**
1. 標題顯示「會員**權限**管理」→ 應改為「會員管理」
2. LINE 通知模板管理使用 📢 Emoji → 應改用 SVG Icon
3. 「新增小幫手」使用彈跳視窗（Modal）→ 應改為頁面內嵌分頁列表
4. 搜尋功能無法找到 WordPress 現有會員
5. 手機版按鈕太大、標題字體過大

**待修復項目：**
- [ ] 「會員權限管理」改成「會員管理」
- [ ] LINE 通知模板管理：移除 📢 Emoji，改用 SVG Icon
- [ ] 移除「新增小幫手」彈跳視窗，改為頁面內嵌分頁列表
- [ ] 修復搜尋功能，確保能搜尋到 WordPress 現有會員
- [ ] 點擊搜尋框時顯示最新 3 筆會員紀錄
- [ ] 手機版：新增小幫手按鈕縮小為 [+]
- [ ] 手機版：大標題字體縮小，與其他頁面一致

**期望設計：**
```
┌──────────────────────────────────────┐
│  會員管理                    [+]     │  ← 標題改名 + 小按鈕
├──────────────────────────────────────┤
│  ┌────────────────────────┐ [搜尋]  │
│  │ 搜尋使用者名稱或 Email │          │
│  └────────────────────────┘          │
│  ← 點擊輸入框時顯示最新 3 筆會員 →   │
│                                      │
│  使用者      │ EMAIL        │ 操作  │
│  ────────────┼──────────────┼────── │
│  王小明      │ a@test.com   │ [設定]│
│  李小華      │ b@test.com   │ [設定]│
│                                      │
│  ◀ 1  2  3  ... ▶  （分頁）         │
└──────────────────────────────────────┘
```

---

### 2. Phase C：多樣式產品 UI + 狀態追蹤邏輯
**優先級：中**

**功能描述：**
支援多樣式（多規格）產品的 UI 顯示與狀態追蹤。例如：同一商品有不同顏色、尺寸等變體。

**需要實作：**
1. 商品列表顯示多樣式產品的方式
2. 每個樣式的獨立庫存追蹤
3. 分配時可以選擇特定樣式
4. 訂單中顯示購買的具體樣式

**相關檔案：**
- `/includes/views/pages/products.php`
- `/includes/services/class-product-service.php`
- `/includes/services/class-allocation-service.php`

---

## 已完成任務歸檔

<details>
<summary>API 401 權限錯誤修復（2026-01-22）</summary>

**問題描述：**
WordPress 管理員身分登入後，除「設定頁」外，其他頁面 API 皆返回 401 Unauthorized。

**修復內容：**
- [x] customers.php - 加入 wpNonce 定義，所有 fetch 加入 X-WP-Nonce header
- [x] shipment-products.php - 加入 wpNonce 定義，所有 fetch 加入 X-WP-Nonce header
- [x] orders.php - 加入 wpNonce 定義，所有 fetch 加入 X-WP-Nonce header
- [x] products.php - 加入 wpNonce 定義，所有 fetch 加入 X-WP-Nonce header

**測試結果：**
- [x] 商品頁 API 200 OK
- [x] 訂單頁 API 200 OK
- [x] 客戶頁 API 200 OK
- [x] 備貨頁 API 200 OK

**Commit:** `fc06d7a`

</details>

<details>
<summary>Settings 頁面 401 Bug 修復（2026-01-22）</summary>

**已完成的修復：**
- [x] 前端 API 呼叫添加 `X-WP-Nonce` header（11 個 API 呼叫）
- [x] 添加 `wpNonce` 變數（`wp_create_nonce("wp_rest")`）

**測試結果：**
- [x] 開啟 Settings 頁面，確認無 401 錯誤
- [x] 測試模板載入功能 - API 返回 200
- [x] 測試會員權限管理功能 - API 返回 200
- [x] 測試關鍵字管理功能 - API 返回 200

</details>

<details>
<summary>Code Review 安全性修復（2026-01-22）</summary>

**高風險問題修復：**
- [x] **HR-1**: Settings API `check_permission_for_admin()` 從 `return true` 改為正確的權限檢查
- [x] **HR-2**: LINE Webhook API 簽名驗證實作（使用 HMAC-SHA256）
- [x] **HR-3**: 用戶搜尋 API 限制結果數量（10筆）並遮罩電子郵件

**中等風險問題修復：**
- [x] **MR-2**: DEBUG API 權限從 `is_user_logged_in()` 改為 `manage_options` 或 `buygo_admin`
- [x] **MR-5**: Settings 頁面所有 API 呼叫添加 `X-WP-Nonce` header

**修改的檔案：**
- `/includes/api/class-settings-api.php`
- `/includes/api/class-line-webhook-api.php`
- `/includes/api/class-debug-api.php`
- `/includes/views/pages/settings.php`

</details>

<details>
<summary>會員權限管理功能（2026-01-23）</summary>

- [x] 建立 `wp_buygo_helpers` 資料表（含 seller_id 欄位）
- [x] 修改 `SettingsService::get_helpers()` 依 seller_id 過濾
- [x] 修改 `SettingsService::add_helper()` 記錄 seller_id
- [x] 啟用 API 權限檢查（Products, Orders, Shipments, Customers API）
- [x] 將「小幫手管理」重新命名為「會員權限管理」
- [x] 移除 Emoji，改用 SVG Icon
- [x] 小幫手角色隱藏「會員權限管理」選單（v-if="isAdmin"）
- [x] FluentCommunity 側邊欄連結 Hook（只有 BuyGo 成員可見）
- [x] 修復資料庫統計查詢導致的 502 錯誤

</details>

<details>
<summary>訂單與出貨功能（歷史完成項目）</summary>

- [x] 修復一鍵分配 Bug - allocated_quantity 應存在 line_meta 中
- [x] 重新設計下單名單 UI - 顯示每筆獨立訂單而非整合
- [x] Phase B：Grid View 模式（商品列表大圖展示）
- [x] 為手機版加入 Grid View 切換功能（與電腦版一致）
- [x] 優化庫存分配頁面 - 移除下單時間、改橫向排列、修復輸入框問題
- [x] 修復已出貨訂單仍可分配的邏輯 Bug
- [x] 功能：訂單頁「批次轉備貨」- 勾選多筆訂單後一次性轉為備貨中狀態
- [x] 修復：父訂單有子訂單時隱藏轉備貨按鈕
- [x] 修復：批次轉備貨應處理子訂單而非父訂單
- [x] 修復：子訂單狀態變更時同步更新父訂單 shipping_status
- [x] 修復 Bug：出貨頁面應使用子分頁，不應出現彈跳視窗
- [x] 修復：批次轉備貨應檢查訂單是否已分配庫存（無分配不可轉備貨）
- [x] 問題 A：「下單名單」頁面改進 - 訂單編號可點擊、搜尋、分頁功能
- [x] 問題 B：修復「轉備貨」按鈕消失 Bug - 直接查詢 DB 取得 line_meta
- [x] 問題 C：「分配」頁面改進 - 訂單編號可點擊、搜尋、分頁、上方確認按鈕
- [x] 修復：下單名單訂單編號顯示錯誤（改用 order_id）
- [x] 修復：訂單編號跳轉連結錯誤（改為 /buygo-portal/orders/）
- [x] 修復：分配數量計算錯誤 - 改用子訂單數量計算，避免重複計算
- [x] 調整：分頁預設筆數改為 3，選項改為 3、5、20、50
- [x] 調整：分配頁面統計欄位順序改為「已下單、已採購、可分配、已分配」
- [x] 優化：上方分配按鈕精簡為「分配 (X)」+ 分配 icon

</details>

---

## 技術筆記

### 訂單狀態流程
```
未出貨 (unshipped) → 備貨中 (preparing) → 待出貨 (processing) → 已出貨 (shipped) → 交易完成 (completed)
                                                                      ↓
                                                                   斷貨 (out_of_stock)
```

### 父子訂單邏輯
1. 父訂單有子訂單時，「轉備貨」按鈕不顯示在父訂單上
2. 批次轉備貨會自動處理子訂單而非父訂單
3. 子訂單狀態變更會自動同步更新父訂單的 shipping_status
4. 批次轉備貨需檢查 `hasAllocatedItems()`，無分配庫存的訂單不可轉備貨

### 出貨頁面子分頁架構
- 使用 `currentView` ref 控制顯示（'list' | 'detail'）
- 使用 `BuyGoRouter` 處理 URL 參數（?view=detail&id=123）
- 支援瀏覽器返回按鈕（popstate 監聯）

### 關鍵服務類別
- `AllocationService` - 庫存分配邏輯
- `OrderService` - 訂單管理
- `ShipmentService` - 出貨單管理
- `ShippingStatusService` - 運送狀態管理
- `ProductService` - 商品管理

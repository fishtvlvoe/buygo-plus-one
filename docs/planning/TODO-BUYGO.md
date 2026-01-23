# BuyGo+1 待完成任務清單

> 更新日期：2026-01-23

---

## 開發工作流程

每完成一個功能，請依照以下流程執行：

1. **程式碼修改** - 完成功能開發
2. **Git 提交** - 提交變更並撰寫 commit message
3. **Chrome MCP 測試** - 使用 Chrome DevTools MCP 開啟頁面確認 UI
4. **Bug 修正** - 發現問題就修正，重複步驟 2-3 直到完成

---

## 待完成任務

### 0. 外掛部署與維護優化
**優先級：P0（緊急）**
**狀態：待執行**

#### A. GitHub 自動更新機制
**目標：**
讓客戶可透過 WordPress 後台自動檢查並更新外掛，無需手動下載 ZIP 檔。

**實作項目：**
- [ ] 研究 WordPress Plugin Update Checker 機制
- [ ] 整合 GitHub Releases API
- [ ] 實作版本檢查邏輯（對比本地版本與 GitHub 最新 Release）
- [ ] 實作自動下載與更新流程
- [ ] 測試自動更新流程（開發環境 → 測試站點）
- [ ] 撰寫客戶操作文件

**相關檔案：**
- `/buygo-plus-one.php` (主外掛檔案，定義版本號)
- 新增檔案：`/includes/class-auto-updater.php`

**參考資源：**
- [Plugin Update Checker Library](https://github.com/YahnisElsts/plugin-update-checker)
- [WordPress Plugin API - Updates](https://developer.wordpress.org/plugins/plugin-basics/updating-your-plugin/)

---

#### B. 永久連結自動刷新
**目標：**
外掛啟用時自動 flush rewrite rules，確保 BuyGo Portal 路由正常運作。

**問題描述：**
目前客戶安裝外掛後需手動進入「設定 → 永久連結」點擊儲存，否則前台路由 404。

**實作項目：**
- [ ] register_activation_hook 中自動執行 flush_rewrite_rules()
- [ ] 測試：停用外掛 → 重新啟用 → 確認路由正常
- [ ] 測試：全新安裝 → 確認路由正常

**相關檔案：**
- `/buygo-plus-one.php` (register_activation_hook)
- `/includes/class-short-link-routes.php` (ShortLinkRoutes::flush_rewrite_rules)

**Commit：**
待執行

---

#### C. 後台新增前台連結入口
**目標：**
在 WordPress 後台設定頁面新增「前往 BuyGo Portal」按鈕，方便管理員快速進入前台。

**實作項目：**
- [ ] 在設定頁面頂部新增按鈕組（或右上角）
- [ ] 按鈕文字：「🚀 前往 BuyGo Portal」
- [ ] 連結目標：`/buygo-portal/`（或 `home_url('/buygo-portal/')`）
- [ ] 樣式：與現有按鈕風格一致（Tailwind CSS）
- [ ] 手機版優化：按鈕文字精簡為「前台」或使用圖示

**相關檔案：**
- `/includes/views/pages/settings.php` (設定頁面)
- 或 `/includes/admin/class-settings-page.php` (後台選單)

**Commit：**
待執行

---

### 1. LINE 上架功能修復
**優先級：緊急**
**狀態：✅ 已完成並測試通過**
**修復日期：2026-01-23**

**問題描述：**
LINE 上傳圖片和文字時，官方帳號沒有反應，無法正常上架商品。

**根本原因：**
1. 權限檢查系統不同步：`can_upload_product()` 使用舊的 `buygo_helpers` option，但系統已改用 `wp_buygo_helpers` 資料表
2. 簽名驗證失敗：Channel Secret 存儲位置錯誤
   - ❌ 錯誤：從 `get_option('buygo_line_channel_secret')` 讀取（不存在）
   - ✅ 正確：從 `BuyGo_Core::settings()->get('line_channel_secret')` 讀取（加密存儲）
3. HTTP Header 大小寫錯誤：使用 `X-Line-Signature` 而非 `x-line-signature`
4. REST API 權限設定錯誤：將 `verify_signature()` 作為 `permission_callback` 導致 401

**已完成修復：**
- [x] 更新 `can_upload_product()` 方法檢查 `wp_buygo_helpers` 資料表
- [x] 權限被拒絕時發送明確訊息給用戶（不再 silent）
- [x] 增強簽名驗證日誌記錄（記錄所有請求和失敗原因）
- [x] 添加詳細的 permission_denied 日誌
- [x] **修復 Channel Secret 讀取邏輯**（從舊外掛的加密系統讀取）
- [x] **修正 HTTP Header 大小寫**（`X-Line-Signature` → `x-line-signature`）
- [x] **修正 REST API 權限設定**（`permission_callback` 改為 `__return_true`）
- [x] **新增 ARCHITECTURE.md 技術文件**（防止未來重複踩坑）

**測試結果：**
✅ Webhook 驗證成功（LINE Developers Console 顯示 200 OK）

**相關檔案：**
- `/includes/api/class-line-webhook-api.php` (簽名驗證修復)
- `/includes/services/class-line-webhook-handler.php` (權限檢查修復)
- `/ARCHITECTURE.md` (技術文件)

**Commits:**
- `fce684e` - 修復 LINE 上架權限檢查 Bug
- `cff61df` - 增強 LINE Webhook 簽名驗證日誌記錄
- `87b399f` - 修復 LINE Webhook Timeout 問題
- `7a6577d` - 修正 REST API 權限設定（401 問題）
- `3ef405e` - 修復 Channel Secret 讀取邏輯與 Header 大小寫
- `135e3bc` - 新增 ARCHITECTURE.md 技術文件

**重要提醒：**
⚠️ 未來修改 LINE 相關功能前，請先閱讀 [ARCHITECTURE.md](ARCHITECTURE.md) 文件！

---

## 📖 重要文件

### ARCHITECTURE.md - 技術架構文件
**路徑**：`/ARCHITECTURE.md`
**用途**：記錄系統架構、資料庫存取規範、常見錯誤解決方案

**何時閱讀：**
- 修改 LINE API 相關功能前
- 進行資料庫查詢前
- 遇到 Signature Mismatch、401 錯誤時
- 新增任何與舊外掛資料互動的功能

**主要內容：**
1. 雙外掛系統架構
2. 資料庫存取規範（Channel Secret、Helpers 權限）
3. LINE API 整合規範（HTTP Header 大小寫、簽名驗證）
4. 命名規範與大小寫對照表
5. 常見錯誤與解決方案
6. Debug 工具使用說明

---

### 2. Phase C：多樣式產品
**優先級：高**
**狀態：規劃完成，待執行**
**詳細計畫：** 請參閱 `/多樣式商品工作計畫.md`

**功能描述：**
支援多樣式（多規格）產品的上架與管理。例如：同一商品有不同顏色、尺寸等變體。

**實作範圍：**

#### A. 上架流程（LINE → FluentCart）
- [ ] A-1: ProductDataParser - 新增 `isMultiVariation()` 判斷方法
- [ ] A-2: ProductDataParser - 新增 `parseMultiVariation()` 解析方法
- [ ] A-3: FluentCartService - 新增 `createMultipleVariations()` 方法
- [ ] A-4: FluentCartService - 修改 `createProduct()` 支援多樣式
- [ ] A-5: FluentCommunity 貼文 - 確認多樣式模版正常運作
- [ ] A-6: LINE 通知 - 支援多樣式商品通知格式

#### B. 管理介面（BuyGo+1 前台）
- [ ] B-1: products.php - 新增「樣式」欄位（單一/多樣下拉選單）
- [ ] B-2: products.php - 下拉選單切換時整行數據更新
- [ ] B-3: products.php - 多樣式商品操作欄新增「📊 統計」按鈕
- [ ] B-4: products.php - 統計彈窗：顯示所有樣式明細 + 合計
- [ ] B-5: ProductService - 取得 variations 資料
- [ ] B-6: ProductsAPI - API 支援 variation 資料回傳
- [ ] B-7: products.php - 樣式層級：已採購數量、採購狀態編輯
- [ ] B-8: products.php - 分配子分頁：多樣式商品用 Tab 切換樣式
- [ ] B-9: AllocationService - 分配時記錄 `variation_id`
- [ ] B-10: orders.php - 訂單明細顯示具體樣式名稱
- [ ] B-11: shipment-products.php - 備貨清單按樣式分組顯示

#### C. 延伸功能（待研究）
- [ ] C-1: 圖片輪播 - 商品卡片支援多張圖片左右切換
- [ ] C-2: 樣式對應圖片 - 每個 variation 可設定獨立圖片
- [ ] C-3: 上傳多圖自動對應 - 上傳 3 張圖 → 自動對應 3 個樣式

**相關檔案：**
- `/includes/views/pages/products.php`
- `/includes/services/class-product-service.php`
- `/includes/services/class-allocation-service.php`
- `/includes/services/class-fluentcart-service.php`
- `/includes/services/class-product-data-parser.php`

---

## 已完成任務歸檔

<details>
<summary>⭐ 訂單與商品頁面 Bug 修復（2026-01-23）</summary>

**完成項目：**
- [x] **Bug A**: 訂單明細 403 錯誤 - wpNonce 未在 orders.php return statement 中導出
- [x] **Bug B**: 商品分配頁面顯示 0 筆訂單 - 缺少 `wp_buygo_shipments` 和 `wp_buygo_shipment_items` 資料表
- [x] **Bug C**: 商品名稱顯示「預設」- OrderService 未讀取 `fct_product_variations.variation_title`
- [x] **Bug D**: SQL NULL 處理錯誤 - `NOT IN` 查詢未處理 NULL 值

**修復的檔案：**
- `/admin/partials/orders.php` (新增 wpNonce 導出)
- `/components/order/order-detail-modal.php` (新增 wpNonce prop 與 X-WP-Nonce headers)
- `/includes/class-database.php` (新增 create_shipments_table 與 create_shipment_items_table)
- `/includes/class-plugin.php` (新增 maybe_upgrade_database 自動升級機制)
- `/includes/services/class-order-service.php` (修正產品名稱讀取邏輯)
- `/includes/services/class-allocation-service.php` (修正 SQL NULL 處理)

**資料庫升級：**
- 新增版本控制：`buygo_plus_one_db_version` option
- 當前版本：1.1.0
- 自動升級機制：外掛啟用時自動檢查並建立缺失的資料表

**Commit：** `fc439d9`

</details>

<details>
<summary>小幫手管理 UI 優化（2026-01-23）</summary>

**完成項目：**
- [x] 「會員管理」更名為「小幫手管理」
- [x] 刪除 LINE 模板通知副標題，簡化介面
- [x] 搜尋結果、最近會員、小幫手列表顯示用戶頭像
- [x] 手機版小幫手卡片改為垂直佈局（標題 + 頭像資訊 + 刪除按鈕）
- [x] 手機版新增按鈕加上「新增」文字

**修改的檔案：**
- `/includes/views/pages/settings.php`
- `/includes/api/class-settings-api.php`
- `/includes/services/class-settings-service.php`

**Commits:** `f0eaa28`, `840918c`, `311e127`

</details>

<details>
<summary>設定頁面 - 會員管理改版（2026-01-22）</summary>

**完成項目：**
- [x] 「會員權限管理」更名為「會員管理」
- [x] LINE 通知模板管理標題移除 📝 Emoji，改用 SVG Icon
- [x] 移除「新增小幫手」彈跳視窗，改為頁面內嵌子分頁
- [x] 新增搜尋框，支援即時搜尋 WordPress 使用者
- [x] 新增 `/settings/users/recent` API，點擊搜尋框時顯示最新 3 筆會員
- [x] 手機版優化：新增按鈕改為僅顯示 + 圖示
- [x] 手機版優化：移除按鈕隱藏文字，只顯示圖示

**修改的檔案：**
- `/includes/views/pages/settings.php`
- `/includes/api/class-settings-api.php`

**Commit:** `bd06cff`

</details>

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

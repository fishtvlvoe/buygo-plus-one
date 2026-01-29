# Architecture

**Analysis Date:** 2026-01-29

## Pattern Overview

**Overall:** WordPress 外掛架構，採用 MVC 分層模式（Service Layer + REST API + Vue 3 前端）

**Key Characteristics:**
- 單例模式外掛載入器（`Plugin::instance()`）
- 服務層封裝商業邏輯（17 個 Service classes）
- REST API 層處理前後端通訊（10 個 API endpoints）
- Vue 3 SPA 前端，透過 REST API 與後端交互
- 設計系統分離（Design System）實現 UI 樣式與代碼隔離

## Layers

**外掛核心層 (Plugin Core):**
- Purpose: 外掛初始化、依賴載入、Hooks 註冊
- Location: `includes/class-plugin.php`
- Contains: 單例模式載入器、依賴管理、Hooks 註冊
- Depends on: WordPress Core
- Used by: `buygo-plus-one.php` 主檔案透過 `plugins_loaded` hook 載入

**資料庫層 (Database):**
- Purpose: 資料表建立與升級
- Location: `includes/class-database.php`
- Contains: 9 個資料表建立與升級邏輯
  - `buygo_debug_logs` - 除錯日誌
  - `buygo_notification_logs` - 通知記錄
  - `buygo_workflow_logs` - 流程監控
  - `buygo_line_bindings` - LINE 綁定
  - `buygo_helpers` - 小幫手
  - `buygo_shipments` - 出貨單
  - `buygo_shipment_items` - 出貨單項目
  - `buygo_webhook_logs` - Webhook 日誌
  - `buygo_order_status_history` - 訂單狀態歷史
- Depends on: WordPress DB (`$wpdb`)
- Used by: 外掛啟用時（`register_activation_hook`）

**服務層 (Service Layer):**
- Purpose: 封裝商業邏輯，提供可重用的功能模組
- Location: `includes/services/`
- Contains: 17 個服務類別
  - `AllocationService` - 庫存分配邏輯
  - `DebugService` - 除錯日誌服務
  - `ExportService` - CSV 匯出功能
  - `FluentCartService` - FluentCart 整合
  - `LineBindingReceipt` - LINE 綁定收據
  - `LineService` - LINE Messaging API
  - `LineOrderNotifier` - LINE 訂單通知
  - `LineWebhookHandler` - LINE Webhook 處理
  - `NotificationTemplates` - 通知訊息模板
  - `OrderService` - 訂單管理邏輯
  - `ProductDataParser` - 商品資料解析
  - `ProductService` - 商品 CRUD
  - `SettingsService` - 外掛設定管理
  - `ShipmentService` - 出貨單管理
  - `ShippingStatusService` - 物流狀態對應
  - `WebhookLogger` - Webhook 日誌
- Depends on: FluentCart Models (Order, Customer, OrderItem), WordPress DB
- Used by: API 層、Admin 頁面

**API 層 (REST API):**
- Purpose: 提供 RESTful API endpoints 供前端調用
- Location: `includes/api/`
- Contains: 10 個 API 類別
  - `Customers_API` - 客戶列表、詳情、備註更新
  - `Products_API` - 商品 CRUD、批次刪除、CSV 匯出、圖片上傳
  - `Orders_API` - 訂單列表、詳情、狀態更新、父子訂單
  - `Shipments_API` - 出貨單 CRUD、出貨商品管理
  - `Settings_API` - 外掛設定讀寫、LINE Channel 設定
  - `Global_Search_API` - 全域搜尋（客戶、訂單、商品）
  - `Keywords_API` - 關鍵字管理（小幫手）
  - `Debug_API` - 除錯資訊查詢
  - `Line_Webhook_API` - LINE Webhook 接收
  - `LIFF_Login_API` - LINE LIFF 登入整合
- Depends on: 服務層、WordPress REST API
- Used by: Vue 3 前端

**路由層 (Routes):**
- Purpose: 自訂前端路由，將 URL 映射到頁面元件
- Location: `includes/class-routes.php`
- Contains: 7 個路由規則
  - `/buygo-portal/` → 重定向到 dashboard
  - `/buygo-portal/dashboard/` → dashboard.php（待建立）
  - `/buygo-portal/products/` → products.php
  - `/buygo-portal/orders/` → orders.php
  - `/buygo-portal/shipment-products/` → shipment-products.php
  - `/buygo-portal/shipment-details/` → shipment-details.php
  - `/buygo-portal/customers/` → customers.php
  - `/buygo-portal/settings/` → settings.php
- Depends on: WordPress Rewrite API
- Used by: WordPress `template_redirect` hook

**前端層 (Frontend):**
- Purpose: 使用者介面，透過 REST API 與後端交互
- Location: `admin/partials/`、`components/`、`design-system/`
- Contains: 7 個頁面 + 設計系統 + Vue 組件
  - **頁面**: customers.php, orders.php, products.php, settings.php, shipment-details.php, shipment-products.php, dashboard.php（待建立）
  - **設計系統**: `design-system/` - tokens (colors, spacing, typography, effects) + components (header, table, card, button, form, status-tag, pagination)
  - **Vue 組件**: `components/shared/` (sidebar, search-box, header, pagination) + `components/order/` (order-detail-modal)
- Depends on: Vue 3 CDN、Tailwind CSS CDN、Design System CSS、WordPress REST API
- Used by: 使用者透過瀏覽器訪問

## Data Flow

**前端請求流程:**

1. 使用者訪問 `/buygo-portal/products/` → WordPress Rewrite 規則觸發
2. `Routes::handle_buygo_pages()` 載入 `template.php`
3. `template.php` 載入 `admin/partials/products.php` 定義 Vue 元件
4. Vue 元件 mounted 時發送 API 請求（帶 `X-WP-Nonce` header）
5. `Products_API::get_products()` 處理請求
6. `ProductService` 查詢 FluentCart 資料表並格式化資料
7. API 回傳 JSON 給前端
8. Vue 更新畫面

**狀態管理:**
- 無集中式狀態管理（Vuex/Pinia）
- 每個頁面元件獨立管理 data（`loading`, `items`, `pagination` 等）
- 全域狀態透過 `useCurrency` composable 管理（幣別切換）

## Key Abstractions

**Service Classes:**
- Purpose: 封裝可重用商業邏輯
- Examples: `includes/services/class-order-service.php`, `includes/services/class-product-service.php`
- Pattern: 類別方法模式，通常在建構函數注入依賴（如 `DebugService`）

**API Classes:**
- Purpose: 統一 REST API endpoint 註冊與權限檢查
- Examples: `includes/api/class-customers-api.php`, `includes/api/class-products-api.php`
- Pattern: 每個 API 類別定義 `$namespace = 'buygo-plus-one/v1'` 並透過 `register_routes()` 註冊端點

**Vue Page Components:**
- Purpose: SPA 頁面組件，包含模板、資料、方法
- Examples: `admin/partials/customers.php` 定義 `CustomersPageComponent`
- Pattern: 在 PHP 檔案中使用 heredoc 定義 HTML 模板，然後在 `<script>` 區塊定義 Vue 組件選項

**Design System:**
- Purpose: 統一 UI 樣式，實現樣式與代碼分離
- Examples: `.page-header`, `.data-table`, `.btn-primary`
- Pattern: 使用 CSS 變數（design tokens）+ 語意化 class 名稱 + 響應式隔離（桌面版/手機版獨立間距）

## Entry Points

**WordPress 外掛入口:**
- Location: `buygo-plus-one.php`
- Triggers: WordPress `plugins_loaded` action (優先級 20)
- Responsibilities: 定義常數（`BUYGO_PLUS_ONE_VERSION`, `BUYGO_PLUS_ONE_PLUGIN_DIR`, `BUYGO_PLUS_ONE_PLUGIN_URL`）、註冊啟用/停用 hooks、初始化 `Plugin::instance()->init()`

**前端 SPA 入口:**
- Location: `includes/views/template.php`
- Triggers: 路由層 `Routes::handle_buygo_pages()` 載入
- Responsibilities:
  - 檢查使用者登入狀態
  - 載入 Tailwind CSS、Vue 3、設計系統
  - 載入共用組件（sidebar, search-box, pagination）
  - 掛載頁面元件到 `#buygo-app`
  - 注入 `window.buygoWpNonce` 供 API 請求使用

**REST API 入口:**
- Location: `includes/api/class-api.php`
- Triggers: WordPress `rest_api_init` action
- Responsibilities: 統一權限檢查 `API::check_permission()`（驗證 nonce 或 API key）

## Error Handling

**Strategy:** 分層錯誤處理，每層各自負責不同類型的錯誤

**Patterns:**
- **Service 層**: 使用 `try-catch` 捕獲資料庫錯誤，透過 `DebugService::log()` 記錄錯誤，回傳錯誤訊息陣列
- **API 層**: 捕獲 Service 層錯誤，回傳 `WP_Error` 或 JSON 錯誤回應（含 HTTP 狀態碼）
- **前端**: `fetch()` 捕獲網路錯誤，檢查 `response.ok` 並顯示錯誤提示（通常是 alert）

**範例（ProductService）:**
```php
try {
    $result = $wpdb->update(...);
    if ($result === false) {
        throw new \Exception($wpdb->last_error);
    }
} catch (\Exception $e) {
    $this->debugService->log('ProductService', '更新失敗', ['error' => $e->getMessage()]);
    return ['success' => false, 'message' => $e->getMessage()];
}
```

## Cross-Cutting Concerns

**Logging:**
- 使用 `DebugService::log()` 寫入 `buygo_debug_logs` 資料表
- 日誌等級：INFO（一般）、ERROR（錯誤）
- 日誌內容：服務名稱、操作描述、上下文資料（JSON）

**Validation:**
- API 層：使用 `register_rest_route()` 的 `args` 參數定義 `sanitize_callback` 和 `validate_callback`
- Service 層：方法內進行資料驗證（如檢查 `$product_id` 是否存在）
- 前端：基本的表單驗證（required, number range）

**Authentication:**
- 前端登入：WordPress 標準登入流程，透過 `is_user_logged_in()` 檢查
- API 認證：WordPress Nonce (`wp_create_nonce("wp_rest")`) 或 API Key（儲存在 `buygo_helpers` 資料表）
- 權限檢查：`API::check_permission()` 驗證 nonce 或 API key

---

*Architecture analysis: 2026-01-29*

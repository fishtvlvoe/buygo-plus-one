---
status: verifying
trigger: "頁面空白-header元件整合後vue無法渲染"
created: 2026-01-29T12:00:00Z
updated: 2026-01-29T13:00:00Z
---

## Current Focus

hypothesis: 已修復 dashboard.php，需要驗證並修復其他頁面
test: dashboard.php 已使用 HeaderMixin，等待使用者測試
expecting: dashboard 頁面能正常顯示；其他頁面仍然白屏
next_action: 使用者測試 dashboard；若成功則繼續修復其他 8 個頁面

## Symptoms

expected: Dashboard 和其他頁面應該正常顯示內容，包含 Header、側邊欄和主要內容
actual: 所有頁面都是空白的（白屏），只顯示白色背景
errors: Browser console 有錯誤訊息（從使用者截圖可見有多個 error 和 warning）
reproduction:
1. 訪問 https://test.buygo.me/buygo-portal/dashboard/
2. 或訪問 https://test.buygo.me/buygo-portal/shipment-products/
3. 頁面顯示為空白
started: 在 commit aebafdb "fix(ui): 統一所有頁面使用共用 Header 元件並移除 sticky 定位" 之後開始出現

## Eliminated

## Evidence

- timestamp: 2026-01-29T12:05:00Z
  checked: header-component.php, dashboard.php, products.php
  found: dashboard.php 使用普通 heredoc (<<<HTML)，但 products.php 使用 nowdoc (<<<'HTML')
  implication: 這導致兩種不同的變數解析行為

- timestamp: 2026-01-29T12:06:00Z
  checked: dashboard.php line 20-22 heredoc 處理
  found:
    ```php
    $dashboard_component_template = <<<HTML
    <main class="min-h-screen bg-slate-50">
        {$header_html}  // ← heredoc 會解析 {$變數}
    ```
  implication: 當 $header_html 包含 Vue 指令如 {{ displayCurrency }} 時，這些會被插入到 heredoc 中

- timestamp: 2026-01-29T12:07:00Z
  checked: header-component.php line 38-62（Vue 指令）
  found: @click, v-model, v-if, {{ displayCurrency }}, {{ unreadCount }}
  implication: 這些 Vue 指令會直接進入 heredoc 字串

- timestamp: 2026-01-29T12:08:00Z
  checked: dashboard.php line 173（template 定義）
  found: `template: \`<?php echo $dashboard_component_template; ?>\``
  implication: Vue template 使用 JavaScript backticks，當 $dashboard_component_template 包含未轉義的內容時會破壞 JavaScript 語法

- timestamp: 2026-01-29T12:10:00Z
  checked: products.php line 31-58（對照組）
  found: 使用 nowdoc (<<<'HTML')，然後用字串連接（.= $header_html）
  implication: nowdoc 不會解析變數，所以 products.php 的做法理論上更安全

- timestamp: 2026-01-29T12:40:00Z
  checked: header-component.php 完整內容
  found: 包含 Vue 指令：
    - Line 38: @click="toggleMobileSearch"
    - Line 46: v-model="globalSearchQuery" @input="handleGlobalSearch"
    - Line 52: @click="cycleCurrency" {{ displayCurrency }}
    - Line 58: @click="toggleNotifications"
    - Line 62: v-if="unreadCount > 0" {{ unreadCount }}
  implication: 這些 Vue 指令需要對應的 data 和 methods

- timestamp: 2026-01-29T12:42:00Z
  checked: dashboard.php line 180-213（DashboardPageComponent data）
  found: 定義了以下屬性：
    - globalSearchQuery, showMobileSearch (Line 184-185)
    - currentCurrency, displayCurrency (Line 188-189)
    - 但缺少 unreadCount
  implication: unreadCount 未定義，會導致 Vue 渲染錯誤

- timestamp: 2026-01-29T12:43:00Z
  checked: dashboard.php line 226-467（DashboardPageComponent methods）
  found: 缺少方法：
    - toggleMobileSearch（Line 38 使用）
    - handleGlobalSearch（Line 46 使用）
    - goToSearchPage（Line 46 使用）
    - cycleCurrency（已存在，Line 443）
    - toggleNotifications（Line 58 使用，未定義）
  implication: 缺少多個 methods，Vue 會報錯導致頁面白屏

- timestamp: 2026-01-29T12:48:00Z
  checked: ProductsPage.js（products.php 的 Vue 元件）
  found: 也沒有定義 header 所需的方法（toggleMobileSearch, handleGlobalSearch, toggleNotifications, cycleCurrency, unreadCount）
  implication: **所有使用 header-component.php 的頁面都有相同問題**

- timestamp: 2026-01-29T12:49:00Z
  checked: 使用 header-component.php 的頁面列表
  found: 9 個頁面全部使用：
    - dashboard.php
    - products.php
    - orders.php
    - customers.php
    - settings.php
    - search.php
    - shipment-products.php
    - shipment-details.php
    - header-component.php（自己 include 自己的註釋）
  implication: 需要為每個頁面的 Vue 元件新增缺失的定義

## Resolution

root_cause: **Vue 指令缺少對應的 data 和 methods 定義**
- header-component.php 包含多個 Vue 指令（@click, v-model, {{ }}）
- 這些指令被 include 到各頁面的 template 中
- 但所有頁面的 Vue 元件都缺少對應的 data 屬性和 methods：
  - 缺少 data: `unreadCount`, `globalSearchQuery`, `showMobileSearch`, `currentCurrency`, `displayCurrency`
  - 缺少 methods: `toggleMobileSearch`, `handleGlobalSearch`, `goToSearchPage`, `toggleNotifications`, `cycleCurrency`
- 當 Vue 嘗試編譯 template 時，遇到未定義的屬性/方法，導致錯誤並停止渲染
- 結果：整個頁面白屏（Vue 應用無法 mount）

fix: 建立共用 HeaderMixin.js 提供 header 相關功能
1. 建立 `/admin/js/HeaderMixin.js` - 包含所有 header 需要的 data 和 methods
2. 在 `template.php` 中載入 HeaderMixin.js
3. 修改 `dashboard.php` 使用 `mixins: [BuyGoHeaderMixin]`
4. 待修復：其他 8 個頁面也需要使用 HeaderMixin
   - products.php (使用外部 ProductsPage.js)
   - orders.php (使用外部 OrdersPage.js)
   - customers.php (使用外部 CustomersPage.js)
   - settings.php (內嵌 Vue 元件)
   - search.php (內嵌 Vue 元件)
   - shipment-products.php (使用外部 ShipmentProductsPage.js)
   - shipment-details.php (使用外部 ShipmentDetailsPage.js)

verification: 修復後在瀏覽器測試頁面是否正常顯示
files_changed:
  - admin/js/HeaderMixin.js (新建)
  - includes/views/template.php (載入 HeaderMixin.js)
  - admin/partials/dashboard.php (使用 HeaderMixin)

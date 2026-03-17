# Phase 41 Research: 基礎架構

**Date:** 2026-02-20
**Phase Goal:** BGO 後台從多子選單重構為 6-Tab 單頁設計，CSS 與 LineHub 設計語言對齊

---

## 現有架構分析

### 1. 選單結構（class-settings-page.php:36-68）

目前 BGO 有 **2 個 WordPress 後台頁面**：

| 頁面 | Slug | Render 函式 | 內容 |
|------|------|-------------|------|
| 主選單「BuyGo+1」 | `buygo-plus-one` | `render_templates_page()` | LINE 模板 |
| 子選單「設定」 | `buygo-settings` | `render_settings_page()` | 6 Tab 切換 |

選單註冊在 `add_admin_menu()` 中：
- `add_menu_page('BuyGo+1', 'BuyGo+1', 'manage_options', 'buygo-plus-one', ...)`
- `add_submenu_page('buygo-plus-one', 'LINE 模板', ..., 'buygo-plus-one')`
- `add_submenu_page('buygo-plus-one', '設定', ..., 'buygo-settings')`

### 2. 設定頁 Tab 結構（class-settings-page.php:147-199）

使用 WordPress 原生 `nav-tab-wrapper` / `nav-tab` / `nav-tab-active` CSS：

```php
$tabs = [
    'notifications' => '通知記錄',
    'workflow'      => '流程監控',
    'checkout'      => '結帳設定',
    'roles'         => '角色權限設定',
    'test-tools'    => '測試工具',
    'debug-center'  => '除錯中心'
];
```

Tab 切換方式：**頁面全刷新**（`<a href="?page=buygo-settings&tab=xxx">`），非 JS 切換。

### 3. Tab 檔案對應

| Tab Key | 檔案路徑 | v2.0 對應 Tab |
|---------|---------|---------------|
| `notifications` | `includes/admin/tabs/notifications-tab.php` | ❌ 刪除（功能已壞，移至 LineHub） |
| `workflow` | `includes/admin/tabs/workflow-tab.php` | → 合併到「開發者」(Phase 45) |
| `checkout` | `includes/admin/tabs/checkout-tab.php` | → 「結帳設定」Tab |
| `roles` | `includes/admin/tabs/roles-tab.php` | → 「角色權限」Tab |
| `test-tools` | `includes/admin/tabs/test-tools-tab.php` | → 合併到「開發者」(Phase 45) |
| `debug-center` | `includes/admin/tabs/debug-center-tab.php` | → 合併到「開發者」(Phase 45) |
| (獨立頁面) | `includes/admin/tabs/templates-tab.php` | → 「LINE 模板」Tab |
| (廢棄) | `includes/admin/tabs/line-tab.php` | ❌ 已廢棄 |

### 4. CSS 樣式（admin/css/admin-settings.css，192 行）

- 無 `.bgo-` 前綴類別
- 使用通用類名：`.tab-content`、`.status-badge`、Modal 樣式
- 使用者搜尋元件樣式（`.user-search-wrap`）
- 角色移除按鈕樣式

### 5. JS 載入（class-settings-page.php:83-125）

- `admin/js/admin-settings.js`（依賴 jQuery）
- `wp_localize_script` 提供 `buygoSettings` 全域物件
- Hook 判斷：`strpos($hook, 'buygo-plus-one') || strpos($hook, 'buygo-settings')`

### 6. LineHub Tab CSS 參考（line-hub/assets/css/admin-tabs.css，129 行）

```css
.line-hub-tabs          → 容器（border-bottom + 白色背景）
.line-hub-tabs-wrapper  → flex 佈局
.line-hub-tab a         → padding: 12px 20px, border-bottom: 2px solid transparent
.line-hub-tab.active a  → 品牌色底線（LINE 綠 #06C755）
.line-hub-card          → 白色卡片（border + box-shadow）
```

BGO 品牌色應為 `#3b82f6`（品牌藍），對齊 LineHub 的結構但使用自己的品牌色。

### 7. test-tools-tab.php 的 $this 依賴

`test-tools-tab.php` 使用 `$this->get_test_data_stats()` 和 `$this->execute_reset_test_data()`，這兩個方法定義在 `class-settings-page.php:372-519`。Phase 41 只是掛載現有 Tab，不動這些方法，到 Phase 45 合併時才處理。

---

## v2.0 新 Tab 結構

| Tab 名稱 | Key | Phase | 內容來源 |
|----------|-----|-------|---------|
| 角色權限 | `roles` | 41（掛載） + 42（重構） | roles-tab.php |
| LINE 模板 | `templates` | 41（掛載） | templates-tab.php |
| 結帳設定 | `checkout` | 41（掛載） | checkout-tab.php |
| 資料管理 | `data` | 43（新建） | data-tab.php（新） |
| 功能管理 | `features` | 44（新建） | features-tab.php（新） |
| 開發者 | `developer` | 45（合併） | developer-tab.php（新） |

Phase 41 只需掛載前 3 個已有的 Tab，後 3 個 Tab 在後續 Phase 中新增。

---

## 技術決策

### Tab 切換方式：保持 Server-Side 路由

理由：
1. 現有所有 Tab 都是 PHP 渲染，不需要 JS 切換
2. 與 WordPress 原生模式一致
3. 減少複雜度，避免引入前端狀態管理
4. 每個 Tab 的資料獨立載入，不需要預載其他 Tab 的資料

改變：從 `?page=buygo-settings&tab=xxx` 改為 `?page=buygo-plus-one&tab=xxx`（統一到一個頁面）

### CSS 命名：`.bgo-` 前綴

與 LineHub 的 `.line-hub-` 前綴對齊，新增 `.bgo-tabs`、`.bgo-tab`、`.bgo-card` 等。

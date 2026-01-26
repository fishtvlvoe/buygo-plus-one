# BuyGo+1 重構指南

> **用途**：使用範本建立新功能或重構現有代碼時的參考指南。

---

## 範本檔案位置

```
templates/
├── admin-page-template.php   # 管理員頁面範本
├── service-template.php      # 服務層範本
└── api-template.php          # REST API 範本
```

---

## 建立新功能的標準流程

### 1. 規劃階段

確定要建立的功能：
- 功能名稱（如：Reports / 報表）
- 需要的頁面、API、服務
- 資料表結構（如需要）

### 2. 建立服務層

**步驟：**

```bash
# 1. 複製範本
cp templates/service-template.php includes/services/class-report-service.php

# 2. 替換佔位符
# {Entity} → Report
# {entity} → report
# {Entities} → Reports
# {entities} → reports
```

**替換清單：**

| 佔位符 | 說明 | 範例 |
|--------|------|------|
| `{Entity}` | 實體名稱（大寫開頭） | `Report` |
| `{entity}` | 實體名稱（小寫） | `report` |
| `{Entities}` | 實體複數（大寫開頭） | `Reports` |
| `{entities}` | 實體複數（小寫） | `reports` |
| `{實體描述}` | 中文描述 | `報表` |

### 3. 建立 API 端點

**步驟：**

```bash
# 1. 複製範本
cp templates/api-template.php includes/api/class-reports-api.php

# 2. 替換佔位符（同上）

# 3. 在 class-api.php 中註冊
```

**註冊 API（在 `class-api.php` 的 `register_routes()` 方法中）：**

```php
// 載入 API 控制器
require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/api/class-reports-api.php';

// 註冊 API
$reports_api = new Reports_API();
$reports_api->register_routes();
```

### 4. 建立管理員頁面

**步驟：**

```bash
# 1. 複製範本
cp templates/admin-page-template.php admin/partials/reports.php

# 2. 替換佔位符
# {PageName} → Reports
# {page-name} → reports
# {page_name} → reports
# {頁面標題} → 報表
```

### 5. 測試驗證

參考 [CODING-STANDARDS.md](CODING-STANDARDS.md) 的修改後驗證清單。

---

## 重構現有代碼的指南

### CSS 隔離

**目標**：將內嵌 CSS 提取到獨立檔案

**步驟：**

1. 建立 `admin/css/{page-name}.css`
2. 將 `<style>` 內容移動到 CSS 檔案
3. 確保所有類名使用頁面前綴
4. 在 PHP 中引入 CSS：

```php
wp_enqueue_style(
    'buygo-{page-name}',
    BUYGO_PLUS_ONE_PLUGIN_URL . 'admin/css/{page-name}.css',
    [],
    BUYGO_PLUS_ONE_VERSION
);
```

### Vue 組件提取

**目標**：將內嵌 Vue 組件提取到獨立 JS 檔案

**步驟：**

1. 建立 `admin/js/components/{PageName}Page.js`
2. 將組件定義移動到 JS 檔案
3. 在 PHP 中引入並傳遞 wpNonce：

```php
wp_enqueue_script(
    'buygo-{page-name}',
    BUYGO_PLUS_ONE_PLUGIN_URL . 'admin/js/components/{PageName}Page.js',
    ['vue'],
    BUYGO_PLUS_ONE_VERSION,
    true
);

wp_localize_script('buygo-{page-name}', 'buygo{PageName}Config', [
    'nonce' => wp_create_nonce('wp_rest'),
    'apiUrl' => rest_url('buygo-plus-one/v1/')
]);
```

---

## 常見問題

### Q: 頁面空白怎麼辦？

**檢查：**
1. HTML 結構是否正確（列表/詳情是平級）
2. `wpNonce` 是否定義並導出
3. 瀏覽器 Console 是否有錯誤

### Q: API 返回 401/403？

**檢查：**
1. `wpNonce` 是否正確傳遞
2. fetch 是否帶有 `X-WP-Nonce` header
3. `permission_callback` 是否設定正確

### Q: CSS 樣式衝突？

**檢查：**
1. 類名是否使用頁面前綴
2. 是否有通用名稱（如 `.header`, `.card`）

---

## 檢查清單

### 建立新功能前

- [ ] 確認功能需求明確
- [ ] 確認資料表結構（如需要）
- [ ] 複製並修改範本
- [ ] 替換所有佔位符

### 建立新功能後

- [ ] API 返回正確資料
- [ ] 頁面正常載入
- [ ] 搜尋功能正常
- [ ] 分頁功能正常
- [ ] CSS 無衝突

---

## 範本使用範例

### 建立「報表」功能

```bash
# 1. 服務層
cp templates/service-template.php includes/services/class-report-service.php
# 替換：{Entity}→Report, {entities}→reports

# 2. API
cp templates/api-template.php includes/api/class-reports-api.php
# 替換：{Entities}→Reports, {entities}→reports

# 3. 頁面
cp templates/admin-page-template.php admin/partials/reports.php
# 替換：{PageName}→Reports, {page-name}→reports, {頁面標題}→報表
```

---

**最後更新**：2026-01-24

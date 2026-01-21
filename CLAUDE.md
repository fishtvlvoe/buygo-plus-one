# CLAUDE.md

本檔案為 Claude Code (claude.ai/code) 在此專案中工作時的指引文件。

## 使用者偏好

- **回應語言**：統一使用繁體中文回應
- **對話紀錄**：重要的對話內容和決策請記錄在此檔案中

## 專案概述

這是 BuyGo 外掛的 WordPress 開發中心。主要外掛是 **buygo-plus-one**，為 BuyGo 賣場提供獨立的後台管理系統，採用 Vue 3 + Tailwind CSS 前端和 PHP + WordPress REST API 後端。

## 目錄結構

```
/Users/fishtv/Development/
├── .plugin-testing/          # 共用測試框架（配置、腳本、模板）
├── buygo-plus-one/           # 主要外掛（開發原始碼）
└── buygo-plus-one-example/   # 範例/參考外掛
```

`buygo-plus-one/` 中的外掛透過符號連結指向 WordPress 安裝目錄 `/Users/fishtv/Local Sites/buygo/app/public/wp-content/plugins/`，在此編輯的內容會立即反映到 WordPress。

## 常用指令

```bash
# 執行所有單元測試
cd buygo-plus-one && composer test

# 執行測試（詳細輸出）
composer test:unit

# 執行特定測試
composer test -- --filter "testName"

# 產生覆蓋率報告（輸出至 coverage/）
composer test:coverage

# 初始化新外掛
bash .plugin-testing/scripts/init-new-plugin.sh <plugin-name>
```

## 架構說明

### 外掛結構 (buygo-plus-one/)

```
buygo-plus-one/
├── buygo-plus-one.php        # 主外掛檔案，啟用/停用 hooks
├── includes/
│   ├── class-plugin.php      # 單例模式外掛載入器，註冊所有 hooks
│   ├── class-database.php    # 資料表建立
│   ├── class-routes.php      # 前端路由
│   ├── services/             # 商業邏輯層（18 個服務類別）
│   ├── api/                  # REST API 端點（11 個 API 類別）
│   ├── admin/                # WordPress 後台頁面
│   └── views/                # Vue 元件和頁面模板
├── tests/
│   ├── Unit/                 # PHPUnit 單元測試（不依賴 WordPress）
│   └── bootstrap-unit.php    # 測試啟動檔
└── components/               # Vue 3 元件
```

### 服務層模式

`includes/services/` 中的服務包含商業邏輯：
- `ProductService` - 商品 CRUD 和庫存管理
- `OrderService` - 訂單處理和狀態
- `ShipmentService` - 出貨管理
- `AllocationService` - 庫存配置
- `SettingsService` - 外掛設定和角色權限

### API 層模式

`includes/api/` 中的 REST 端點遵循 WordPress REST API 慣例：
- 基礎命名空間：`buygo-plus-one/v1`
- 驗證方式：WordPress nonce/cookie 或 API 金鑰

### 初始化流程

1. `buygo-plus-one.php` → 定義常數，註冊啟用 hooks
2. `plugins_loaded` action（優先級 20）→ `Plugin::instance()->init()`
3. `Plugin::load_dependencies()` → 載入所有 service/api/admin 類別
4. `Plugin::register_hooks()` → 初始化路由、API、後台頁面

## 測試

單元測試位於 `tests/Unit/`，使用 PHPUnit 9 搭配 Yoast PHPUnit Polyfills。測試不需要 WordPress，只測試純 PHP 商業邏輯。

測試結構對應原始碼：
```
tests/Unit/Services/ProductServiceBasicTest.php
    → 測試 includes/services/class-product-service.php
```

## 開發流程

1. 在 `/Users/fishtv/Development/buygo-plus-one/` 編輯程式碼
2. 變更透過符號連結立即反映到 WordPress
3. 執行 `composer test` 驗證
4. 在瀏覽器 `http://buygo.local/wp-admin` 測試
5. 使用 git 提交變更

## WordPress 環境

- Local by Flywheel 安裝位置：`/Users/fishtv/Local Sites/buygo/`
- MySQL socket：`/Users/fishtv/Library/Application Support/Local/run/oFa4PFqBu/mysql/mysqld.sock`
- 測試資料庫：`wordpress_test`（使用者：root，密碼：root）

## 主要相依套件

此外掛整合了：
- FluentCart（電商平台）
- LINE Messaging API（通知、webhooks）

## 語言慣例

程式碼註解和文件使用繁體中文。變數名稱和程式碼遵循英文慣例。

---

## 對話紀錄

### 2026-01-22
- 建立 CLAUDE.md 檔案
- 使用者要求：將文件改為繁體中文、後續對話使用繁體中文回應、記錄重要對話內容

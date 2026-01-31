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
├── .plugin-testing/              # 共用測試框架（配置、腳本、模板）
├── buygo-plus-one/               # 主要外掛（正式版，main 分支）
├── buygo-plus-one-dev/           # 開發版（feature 分支）
├── buygo-line-notify/            # LINE Notify 外掛（獨立倉庫）
├── fluentcart-payuni/            # FluentCart PayUNi 金流外掛
└── buygo-multi-variation/        # 計畫文件（非外掛）
```

## 外掛倉庫狀態

| 外掛 | GitHub 倉庫 | 分支 | WordPress 連結 |
|------|------------|------|----------------|
| buygo-plus-one | fishtvlvoe/buygo-plus-one | main | 直接目錄 |
| buygo-plus-one-dev | fishtvlvoe/buygo-plus-one | feature/checkout-id-number | 符號連結 → |
| buygo-line-notify | fishtvlvoe/buygo-line-notify | main | 符號連結 → |
| fluentcart-payuni | fishtvlvoe/fluentcart-payuni | main | 符號連結 → |

所有外掛透過符號連結指向 WordPress 安裝目錄 `/Users/fishtv/Local Sites/buygo/app/public/wp-content/plugins/`，編輯內容會立即反映到 WordPress。

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
- **本地網域**：`http://buygo.local`（僅供本機開發）
- **測試網站**：`https://test.buygo.me`（外部可存取，用於瀏覽器測試）

**重要**：當需要透過瀏覽器存取網站時，務必使用 `https://test.buygo.me`，而非本地網域。本地的 WordPress 有外連版本，所有網頁測試都應該使用外部網域。

## Test Script Manager（測試腳本管理）

使用 Test Script Manager 外掛進行快速開發和測試：

### 何時使用

當需要進行以下任務時，自動使用 `/tsm` Skills：
- WordPress 資料庫查詢和分析
- PHP 函式和 WordPress API 測試
- Hook 和 Filter 除錯
- WooCommerce 訂單/產品測試
- 效能分析和優化
- 快速原型開發（包含 JavaScript/CSS）

### 測試環境

- 後台頁面：https://test.buygo.me/wp-admin/admin.php?page=test-script-manager
- 外掛位置：`wp-content/plugins/test-script-manager/`

### 開發工作流程

1. **快速開發和測試**
   - 在 Test Script Manager 後台編寫測試腳本
   - 可以在 PHP 中嵌入 JavaScript 和 CSS 進行整合測試
   - 反覆修改、執行、除錯，快速迭代

2. **確認功能正常**
   - 在後台執行測試，確認輸出結果
   - 測試腳本儲存在資料庫中，可重複使用
   - 版本控制：自動儲存每次修改

3. **正式化程式碼**
   - 測試完成後，將程式碼拆分成獨立檔案
   - JavaScript → `assets/js/`
   - CSS → `assets/css/`
   - PHP → `includes/`

### 優點

- ✅ 不需要每次建立實體檔案
- ✅ 集中管理所有測試腳本
- ✅ 即時執行，快速驗證
- ✅ 保留測試紀錄，未來可參考

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

### 2026-01-31

- 整合 Test Script Manager 外掛和 Skills
- 更新 tsm.md Skill，加入動態環境偵測功能
- 在 CLAUDE.md 中加入 Test Script Manager 使用規則和開發工作流程
- 測試網站：https://test.buygo.me

#### buygo-line-notify 外掛遺失問題修復

**問題**：`buygo-line-notify` 外掛在切換分支時被意外刪除

**原因分析**：
- 外掛在 `test/gsd-customers-ui` 分支上的某次提交（2883eed）中被整個刪除
- 但外掛從未被提交到 `release/v0.2.0` 分支
- 從 `test/gsd-customers-ui` 切換到 `release/v0.2.0` 時，git 自動清理了工作目錄中的外掛檔案

**解決方案**：
1. ✅ 使用 `git checkout 2883eed -- buygo-line-notify/` 從歷史恢復外掛
2. ✅ 從主倉庫移除，改為獨立 Git 倉庫
3. ✅ 建立 GitHub 倉庫：https://github.com/fishtvlvoe/buygo-line-notify
4. ✅ 推送到 GitHub，完成獨立化

**最終架構**：
- 所有外掛都是獨立的 Git 倉庫
- 每個外掛有自己的 GitHub 遠端倉庫
- 不再依賴主倉庫，避免分支切換問題

版本紀錄規範
存檔 (當我說「存檔」或「提交」時)

1. 執行 git diff 檢查本次改動
2. 執行 git add . 和 git commit (根據改動自動生成備註)
3. 在 CHANGELOG.md 頂部添加紀錄，格式：

YYYY-MM-DD HH:mm
• 改動內容

回退 (當我說「回退」或「撤銷」時)

1. 先告訴我會回退到哪個版本 (顯示上一次的提交資訊)
2. 等我確認後再執行回退
3. 回退後在 CHANGELOG.md 頂部紀錄：

YYYY-MM-DD HH:mm
• 回退：撤銷了 xxx 改動

查看歷史 (當我說「歷史」或「紀錄」時)
顯示最近 5 次提交的簡要資訊

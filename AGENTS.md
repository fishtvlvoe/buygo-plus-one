# AGENTS.md — BuyGo Plus One AI 導航指南

> 本檔案是 GenAI Agent 理解 BuyGo Plus One 專案的主要入口。執行任何程式碼任務前，請完整閱讀本檔案。

## 專案摘要

BuyGo Plus One 是 WordPress 外掛，提供團購+1 獨立賣場後台管理系統。核心功能包括：庫存分配與釋放、訂單建立與狀態更新、出貨追蹤、LINE 官方帳號整合、FluentCart 商品同步。

技術棧：WordPress 6.x + PHP 8.x + WooCommerce。PSR-4 命名空間：`BuyGoPlus\`。

版本來源：composer.json `require` 段定義 PHP >= 7.4、PHPUnit 9.0。

## 快速約束（5 條硬規則）

- **商業邏輯只放在 `includes/services/`**：API 層（`includes/api/`）只做輸入驗證和路由，禁止寫邏輯
- **資料表修改一律用 WordPress 遷移 hook**：禁止直接修改表結構
- **主入口 < 50 行、載入器 < 150 行、Service < 300 行**：熵減原則，防止單一檔案過大
- **先寫測試再寫實作**：TDD 優先，測試不依賴 WordPress 全域函數
- **第三方整合透過 hooks**：禁止直接 `new` 或 `require` 其他外掛的 class（如 LineHub）

## Domain-to-Code 對應表

| 領域 | 主服務 | 位置 | 相關 SPEC |
|------|--------|------|----------|
| 庫存分配 | AllocationService | includes/services/ | SPEC-001-allocation-variation-filter |
| 訂單管理 | OrderService | includes/services/ | SPEC-007-cancel-order-item-from-detail |
| 訂單商品 | OrderItemService | includes/services/ | SPEC-007-cancel-order-item-from-detail |
| 訂單格式化 | OrderFormatter | includes/services/ | SPEC-007 |
| 出貨管理 | ShipmentService | includes/services/ | Line Order Query (docs/features/) |
| 商品與變數 | ProductService | includes/services/ | SPEC-008-order-quantity-fluentcart-sync |
| 身份認證 | IdentityService | includes/services/ | Line Order Query |
| 設定管理 | SettingsService | includes/services/ | 後台設定 |

## 代碼生成規則

### 架構分層

```
buygo-plus-one.php (< 50 行)
  ↓ plugins_loaded
includes/class-plugin.php (< 150 行)
  ├─ load_dependencies()
  │  ├─ includes/services/*.php (商業邏輯，各 < 300 行)
  │  ├─ includes/api/*.php (REST 端點，驗證+路由)
  │  └─ includes/integrations/*.php (第三方)
  └─ register_hooks()
     ├─ wp_rest_init (API 註冊)
     ├─ admin_menu (後台頁面)
     └─ wp-cli (CLI 指令)
```

### 測試策略

- **單元測試**：`tests/Unit/` 目錄，測純 PHP 邏輯，不呼叫 WP 全域函數
- **Bootstrap**：`tests/bootstrap-unit.php` 設定 PSR-4 自動載入
- **覆蓋率目標**：> 80%（用 `composer test:coverage` 產出 HTML report）

### Git 規範

- 功能分支：`feature/xxx`，Bug 分支：`fix/xxx`
- 禁止直接 push main，所有變更必須建 PR
- Commit 訊息遵守 Conventional Commits（中文說明）

## GenAI 文檔導航

| 意圖 | 檔案 | 用途 |
|------|------|------|
| 了解文檔治理 | docs/README.md | SDD 分類、命名規則、維護觸發 |
| 查詢訂單取消 | docs/specs/SPEC-007-cancel-order-item-from-detail.md | 子訂單取消 API + UI 規格 |
| 查詢商品刪除同步 | docs/specs/SPEC-008-order-quantity-fluentcart-sync.md | FluentCart 商品移至回收桶邏輯 |
| 快取架構決策 | docs/adr/ADR-001-shell-cache-vs-buygo-cache.md | Shell 快取 vs Server-Rendered |
| SPA 效能升級 | docs/adr/ADR-002-spa-performance-upgrade.md | Vite + Tailwind 遷移計畫 |
| LINE 訂單查詢 | docs/adr/ADR-003-line-order-query-sync-boundary.md | LINE 整合邊界與狀態同步 |
| 快取方案深入 | docs/features/shell-cache-architecture.md | 買賣家流程細節 |
| 效能優化細節 | docs/features/spa-performance-upgrade.md | Phase 1-4 實作步驟 |
| LINE 整合需求 | docs/features/line-order-query.md | 訂單狀態定義與 LIFF 設計 |

## 常用指令

```bash
cd /Users/fishtv/Development/8-外掛/buygo-plus-one

# 執行所有測試
composer test

# 執行特定測試
composer test -- --filter "Service"
composer test -- --filter "OrderItemService"

# 詳細輸出
composer test:unit

# 覆蓋率報告（產出 coverage/ 目錄）
composer test:coverage

# 本機錯誤日誌（InstaWP 本地開發環境）
tail -f /Users/fishtv/Local\ Sites/buygo/app/logs/php-error.log
```

## 部署指令

```bash
# 禁止手寫 rsync，一律用 /deploy skill
/deploy
```

---

Retrofit 產生於 2026-04-27，來源：CLAUDE.md + composer.json + openspec/specs/ + docs/features/

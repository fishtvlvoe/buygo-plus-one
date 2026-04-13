# Copilot Instructions — BuyGo Plus One

BuyGo Plus One 是電商訂閱與加購外掛，整合 FluentCart，提供一鍵加購、訂閱管理等功能。

## 語言與風格
- 程式碼用英文（變數、函數、class）
- 註解用繁體中文
- Commit message 用英文，遵守 conventional commits 格式

## 開發規範
- 先寫測試再寫代碼（TDD）
- 不加不必要的 docblock、type hint 或過度抽象
- 不加 feature flag 或向後相容 shim，直接改
- 安全優先：避免 SQL injection、XSS、command injection

## WordPress 外掛規範
- PHP 8.x 語法
- 遵守 WordPress Coding Standards
- 資料庫查詢用 `$wpdb->prepare()`
- Hook 命名用外掛 prefix：`buygo_`
- 翻譯用 `__()` 和 `_e()`，text domain：`buygo-plus-one`

## 架構規範
- 商業邏輯只放在 `includes/services/`
- `includes/api/` 只做輸入驗證和路由，禁止在此寫商業邏輯
- `includes/core/` 放通用基礎設施
- `includes/integrations/` 放第三方整合（FluentCart 等）
- 主入口 `buygo-plus-one.php` 上限 50 行
- 載入器 `class-plugin.php` 上限 150 行
- 單一 Service class 上限 300 行
- 與其他外掛（LineHub 等）通訊一律透過 WordPress hooks，禁止直接 `new` 或 `require` 其他外掛的 class
- 資料庫結構變更一律用 WordPress 遷移 hook

## 禁止
- 不要自動加 `@since`、`@author` 等 PHPDoc 標籤
- 不要建議安裝額外的 linter 或工具
- 不要重構沒有被修改的相鄰程式碼
- 不在 `includes/api/` 寫商業邏輯
- 不直接查詢資料庫（必須透過 service）

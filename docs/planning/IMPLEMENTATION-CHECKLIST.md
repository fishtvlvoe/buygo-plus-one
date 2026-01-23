# BuyGo+1 實施檢查清單

> **用途**：每次 Claude 對話開始時讀取此檔案，了解目前進度。
>
> **完整計畫**：參見 `~/.claude/plans/golden-hopping-mockingbird.md`
>
> **最後更新**：2026-01-24
>
> **目前階段**：第 3 階段完成！所有服務層優化已完成，準備進入第 4 階段。

---

## 整體進度

| 階段 | 狀態 | 預計日期 |
|------|------|----------|
| 第 1 階段：立即修復 | ✅ 完成 | 2026-01-24 |
| 第 2 階段：參考系統 | ✅ 完成 | 2026-01-24 |
| 第 3 階段：組件分離 | ✅ 完成 | 2026-01-24 |
| 第 4 階段：自動化工具 | ⏳ 未開始 | 3月1日 - 7日 |
| 第 5 階段：Vite 遷移（選擇性） | ⏳ 未開始 | 3月7日後 |

---

## 第 1 階段：立即修復（今天）

### 文檔建立
- [x] 建立 IMPLEMENTATION-CHECKLIST.md（此檔案）
- [x] 建立 CODING-STANDARDS.md

### 結構註解
- [x] 添加結構註解到 `admin/partials/products.php`
- [x] 添加結構註解到 `admin/partials/orders.php`
- [x] 添加結構註解到 `admin/partials/customers.php`
- [x] 添加結構註解到 `admin/partials/shipment-details.php`
- [x] 添加結構註解到 `admin/partials/shipment-products.php`

### 參考更新
- [x] 更新 CLAUDE.md 添加 CODING-STANDARDS.md 參考

### 提交
- [x] Git 提交：`docs: 新增編碼規範和結構註解`

---

## 第 2 階段：參考系統（2026-01-24 完成）

### 範本庫
- [x] 建立 `templates/` 目錄
- [x] 建立 `templates/admin-page-template.php`
- [x] 建立 `templates/service-template.php`
- [x] 建立 `templates/api-template.php`

### 文檔
- [x] 建立 REFACTORING-GUIDE.md

### 自動化指令碼
- [x] 建立 `scripts/create-feature.sh`（合併建立頁面/服務功能）
- [x] 建立 `scripts/validate-structure.sh`

### 提交
- [x] Git 提交：`feat: 新增範本庫和參考系統`

---

## 第 3 階段：組件分離（2月15日 - 28日）

### Week 2：CSS 隔離（✅ 完成 - 2026-01-24）
- [x] 建立 `admin/css/` 目錄
- [x] 提取 `admin/css/products.css`
- [x] 提取 `admin/css/orders.css`
- [x] 提取 `admin/css/customers.css`
- [x] 提取 `admin/css/shipment-details.css`（無需提取，該檔案無樣式）
- [x] 提取 `admin/css/shipment-products.css`
- [x] 更新各 PHP 檔案連結到 CSS
- [x] Git 提交：`refactor: 提取 CSS 到獨立檔案`

### Week 3：Vue 組件提取（✅ 完成 - 2026-01-24）
- [x] 建立 `admin/js/components/` 目錄
- [x] 提取 `admin/js/components/ProductsPage.js`
- [x] 提取 `admin/js/components/OrdersPage.js`
- [x] 提取 `admin/js/components/CustomersPage.js`
- [x] 提取 `admin/js/components/ShipmentDetailsPage.js`
- [x] 提取 `admin/js/components/ShipmentProductsPage.js`
- [x] Git 提交：`refactor: 提取 Vue 組件到獨立檔案`

### Week 3：Composables 擴展（✅ 完成 - 2026-01-24）
- [x] 建立 `includes/views/composables/useApi.js`
- [x] 建立 `includes/views/composables/usePermissions.js`
- [x] 建立 `includes/views/composables/README.md`
- [x] Git 提交：`feat: 新增 Vue composables`

### 服務層優化（✅ 完成 - 2026-01-24）
- [x] 審查所有 15 個 services
- [x] 產出服務層錯誤處理與日誌集成審查報告
- [x] 建立 `docs/development/SERVICES-REVIEW-REPORT.md`
- [x] ✅ 執行高優先級修復（ProductDataParser 1.5→4.0, ExportService 2.0→4.0, NotificationTemplates 2.0→3.5）
- [x] ✅ 執行中優先級修復（LineService 2.5→4.5, SettingsService 2.5→4.5）
- [x] ✅ 升級舊的 WebhookLogger 到 DebugService（FluentCartService, ImageUploader, LineWebhookHandler）
- [x] Git 提交：`refactor: 修復高優先級服務的錯誤處理和日誌集成`
- [x] Git 提交：`refactor: 升級 WebhookLogger 到 DebugService`
- [x] Git 提交：`refactor: 改進中優先級服務的錯誤處理和日誌記錄`

---

## 第 4 階段：自動化工具（3月1日 - 7日）

### 驗證工具
- [ ] 完善 `scripts/validate-structure.sh`
- [ ] 測試驗證指令碼

### 預提交鉤子
- [ ] 建立 `.git/hooks/pre-commit`
- [ ] 測試 wpNonce 導出檢查
- [ ] 測試 permission_callback 檢查

### 技能更新
- [ ] 更新 `~/.claude/skills/debug-buygo/SKILL.md`
- [ ] 添加自動結構驗證流程

### 提交
- [ ] Git 提交：`feat: 新增自動化驗證和預提交鉤子`

---

## 第 5 階段：Vite 遷移（選擇性 - 3月7日後）

### 前置條件檢查
- [ ] 確認所有 Vue 組件已提取
- [ ] 確認所有 CSS 已提取
- [ ] 確認開發工作流穩定

### Vite 設定
- [ ] 建立 `resources/admin/` 結構
- [ ] 建立 `vite.config.js`
- [ ] 建立 `package.json`
- [ ] 設定構建輸出到 `assets/`

### 遷移
- [ ] 遷移 Vue 組件到 .vue 格式
- [ ] 設定 HMR
- [ ] 測試生產構建

---

## 參考檔案

| 檔案 | 用途 |
|------|------|
| `~/.claude/plans/golden-hopping-mockingbird.md` | 完整計畫（繁體中文） |
| [CODING-STANDARDS.md](../development/CODING-STANDARDS.md) | 編碼規範 |
| [BUGFIX-CHECKLIST.md](../bugfix/BUGFIX-CHECKLIST.md) | 已修復問題清單 |
| [/CLAUDE.md](/CLAUDE.md) | Claude 專案指南（根目錄） |
| [ARCHITECTURE.md](../development/ARCHITECTURE.md) | 技術架構 |
| [SERVICES-REVIEW-REPORT.md](../development/SERVICES-REVIEW-REPORT.md) | 服務層審查報告 |
| [Composables README](../../includes/views/composables/README.md) | Vue Composables 使用文檔 |

---

**最後更新**：2026-01-24
**目前階段**：第 3 階段 - 組件分離（✅ CSS 隔離、Vue 組件提取、Composables 擴展已完成；⏳ 服務層優化審查完成，待執行修復）

# BuyGo+1 實施檢查清單

> **用途**：每次 Claude 對話開始時讀取此檔案，了解目前進度。
>
> **完整計畫**：參見 `~/.claude/plans/golden-hopping-mockingbird.md`

---

## 整體進度

| 階段 | 狀態 | 預計日期 |
|------|------|----------|
| 第 1 階段：立即修復 | 🔄 進行中 | 今天（2小時） |
| 第 2 階段：參考系統 | ⏳ 未開始 | 2月8日 - 14日 |
| 第 3 階段：組件分離 | ⏳ 未開始 | 2月15日 - 28日 |
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
- [ ] Git 提交：`docs: 新增編碼規範和結構註解`

---

## 第 2 階段：參考系統（2月8日 - 14日）

### 範本庫
- [ ] 建立 `templates/` 目錄
- [ ] 建立 `templates/admin-page-template.php`
- [ ] 建立 `templates/service-template.php`
- [ ] 建立 `templates/api-template.php`

### 文檔
- [ ] 建立 REFACTORING-GUIDE.md

### 開發資源
- [ ] 建立 `/Users/fishtv/Development/buygo-dev-resources/` 目錄結構
- [ ] 複製範本到 buygo-dev-resources

### 自動化指令碼
- [ ] 建立 `scripts/create-page.sh`
- [ ] 建立 `scripts/create-service.sh`
- [ ] 建立 `scripts/validate-structure.sh`

### 提交
- [ ] Git 提交：`feat: 新增範本庫和參考系統`

---

## 第 3 階段：組件分離（2月15日 - 28日）

### Week 2：CSS 隔離
- [ ] 建立 `admin/css/` 目錄
- [ ] 提取 `admin/css/products.css`
- [ ] 提取 `admin/css/orders.css`
- [ ] 提取 `admin/css/customers.css`
- [ ] 提取 `admin/css/shipment-details.css`
- [ ] 提取 `admin/css/shipment-products.css`
- [ ] 更新各 PHP 檔案連結到 CSS
- [ ] Git 提交：`refactor: 提取 CSS 到獨立檔案`

### Week 3：Vue 組件提取
- [ ] 建立 `admin/js/components/` 目錄
- [ ] 提取 `admin/js/components/ProductsPage.js`
- [ ] 提取 `admin/js/components/OrdersPage.js`
- [ ] 提取 `admin/js/components/CustomersPage.js`
- [ ] 提取 `admin/js/components/ShipmentDetailsPage.js`
- [ ] 提取 `admin/js/components/ShipmentProductsPage.js`
- [ ] Git 提交：`refactor: 提取 Vue 組件到獨立檔案`

### Week 3：Composables 擴展
- [ ] 建立 `admin/js/composables/useApi.js`
- [ ] 建立 `admin/js/composables/usePermissions.js`
- [ ] Git 提交：`feat: 新增 Vue composables`

### 服務層優化
- [ ] 審查所有 19 個 services
- [ ] 確保錯誤處理一致性
- [ ] 確保日誌集成
- [ ] Git 提交：`refactor: 優化服務層錯誤處理`

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
| `CODING-STANDARDS.md` | 編碼規範（待建立） |
| `BUGFIX-CHECKLIST.md` | 已修復問題清單 |
| `CLAUDE.md` | Claude 專案指南 |
| `ARCHITECTURE.md` | 技術架構 |

---

**最後更新**：2026-01-24
**目前階段**：第 1 階段 - 立即修復

# 🔄 BuyGo+1 專案交接說明

> **給新 Claude 對話的交接文檔**
> **最後更新**：2026-01-24 上午 4:44
> **當前狀態**：第 3 階段進行中（90% 完成）

---

## 📋 快速開始檢查清單

新的 Claude 對話應該按照以下順序閱讀這些檔案：

### 1️⃣ **必讀核心文檔**（按順序）
```bash
# 在專案根目錄 /Users/fishtv/Development/buygo-plus-one-dev
1. /CLAUDE.md                                          # 專案總覽和指南
2. docs/planning/IMPLEMENTATION-CHECKLIST.md           # 當前進度（⭐ 最重要）
3. docs/development/CODING-STANDARDS.md                # 編碼規範
4. docs/bugfix/BUGFIX-CHECKLIST.md                     # 已修復問題清單（避免重複 bug）
```

### 2️⃣ **了解整體計畫**
```bash
~/.claude/plans/golden-hopping-mockingbird.md          # 完整 5 階段計畫
```

### 3️⃣ **技術參考**
```bash
docs/development/ARCHITECTURE.md                       # 技術架構
docs/development/SERVICES-REVIEW-REPORT.md             # 服務層審查報告
includes/views/composables/README.md                   # Vue Composables 文檔
```

---

## 🎯 當前任務狀態

### ✅ 已完成（不要重做）

#### 第 1 階段：立即修復
- ✅ CODING-STANDARDS.md 已建立
- ✅ 所有管理員頁面添加結構註解
- ✅ CLAUDE.md 已更新

#### 第 2 階段：參考系統
- ✅ 3 個範本已建立（`templates/`）
- ✅ 自動化指令碼已建立（`scripts/`）
- ✅ REFACTORING-GUIDE.md 已建立

#### 第 3 階段：組件分離（90% 完成）
- ✅ **CSS 隔離**：5 個 CSS 檔案已提取到 `admin/css/`
- ✅ **Vue 組件提取**：5 個組件已提取到 `admin/js/components/`
- ✅ **Composables**：3 個 composables 在 `includes/views/composables/`
- ✅ **服務層審查**：已完成並產出報告
- ✅ **高優先級服務修復**：
  - ProductDataParser (1.5→4.0)
  - ExportService (2.0→4.0)
  - NotificationTemplates (2.0→3.5)
- ✅ **DebugService 單例模式**：剛剛完成（重要修復！）

### 🔄 待完成任務（下一步工作）

#### 第 3 階段：服務層優化（剩餘 10%）

**任務 1：中優先級服務修復**
- [ ] LineService (評分 3.5/5)
  - 位置：`includes/services/class-line-service.php`
  - 問題：錯誤處理不完整，缺少輸入驗證
  - 目標：提升到 4.5/5

- [ ] SettingsService (評分 3.5/5)
  - 位置：`includes/services/class-settings-service.php`
  - 問題：缺少錯誤處理，返回類型不一致
  - 目標：提升到 4.5/5

**任務 2：升級 WebhookLogger 到 DebugService**
以下 3 個服務仍使用舊的 `WebhookLogger::get_instance()`，需要改為使用 `DebugService::get_instance()`：

- [ ] FluentCartService
  - 位置：`includes/services/class-fluentcart-service.php:33`
  - 改動：`$this->logger = WebhookLogger::get_instance();` → `$this->debugService = DebugService::get_instance();`
  - 更新所有 `$this->logger->log()` 為 `$this->debugService->log()`

- [ ] ImageUploader
  - 位置：`includes/services/class-image-uploader.php:40`
  - 同上改動

- [ ] LineWebhookHandler
  - 位置：`includes/services/class-line-webhook-handler.php:40`
  - 同上改動

#### 第 4 階段：自動化工具（尚未開始）
- [ ] 完善 `scripts/validate-structure.sh`
- [ ] 建立 `.git/hooks/pre-commit`
- [ ] 更新 `~/.claude/skills/debug-buygo/SKILL.md`

---

## 🚨 重要注意事項

### ⚠️ 絕對不要做的事情

1. **不要重新提取 CSS 或 Vue 組件**
   - 這些工作已完成，重做會破壞現有功能
   - 位置：`admin/css/` 和 `admin/js/components/`

2. **不要修改 DebugService 的單例模式**
   - 剛剛修復完成，已解決 WordPress 500 錯誤
   - 位置：`includes/services/class-debug-service.php`

3. **不要引入 BUGFIX-CHECKLIST.md 中已修復的 bug**
   - 修改前必須檢查該檔案
   - 位置：`docs/bugfix/BUGFIX-CHECKLIST.md`

4. **不要使用 `new DebugService()`**
   - 必須使用 `DebugService::get_instance()`
   - 建構函數是 private

### ✅ 應該做的事情

1. **遵循 CODING-STANDARDS.md**
   - 位置：`docs/development/CODING-STANDARDS.md`
   - 包含 HTML 結構、CSS 命名、Vue 組件模式

2. **使用現有範本**
   - 頁面範本：`templates/admin-page-template.php`
   - 服務範本：`templates/service-template.php`
   - API 範本：`templates/api-template.php`

3. **參考工作中的實現**
   - ProductsPage：`admin/js/components/ProductsPage.js`
   - OrdersPage：`admin/js/components/OrdersPage.js`
   - ProductDataParser：`includes/services/class-product-data-parser.php`（最佳實踐範例）

4. **每個修改都要提交**
   - 使用清晰的 commit message
   - 格式：`fix:`, `feat:`, `refactor:`, `docs:`
   - 範例：`fix: 升級 FluentCartService 使用 DebugService`

---

## 🛠️ 開發環境資訊

### 專案結構
```
/Users/fishtv/Development/buygo-plus-one-dev/
├── admin/
│   ├── css/                        # ✅ CSS 已提取到這裡
│   ├── js/
│   │   └── components/             # ✅ Vue 組件已提取到這裡
│   └── partials/                   # PHP 管理員頁面
├── includes/
│   ├── services/                   # ⚠️ 這裡有待修復的服務
│   └── views/
│       └── composables/            # ✅ Vue composables
├── templates/                      # ✅ 可重用範本
├── scripts/                        # ✅ 自動化指令碼
└── docs/
    ├── planning/                   # 計畫文檔
    ├── development/                # 開發文檔
    ├── bugfix/                     # Bug 修復記錄
    └── testing/                    # 測試文檔
```

### Git 狀態
- **分支**：`main`
- **最新 commit**：`c796afc - fix: 實作 DebugService 單例模式，修復類別名稱不一致`
- **領先遠端**：20 commits（需要時可 push）

### WordPress 環境
- **Local by Flywheel**：`/Users/fishtv/Local Sites/buygo/`
- **插件路徑**：通過 symlink 連結到開發目錄
- **測試站點**：test.buygo.me（通過 Local 訪問）

---

## 📝 具體執行步驟

### 步驟 1：完成中優先級服務修復

#### 修復 LineService

```bash
# 1. 讀取服務審查報告了解問題
cat docs/development/SERVICES-REVIEW-REPORT.md | grep -A 20 "LineService"

# 2. 讀取當前實現
cat includes/services/class-line-service.php

# 3. 參考最佳實踐
cat includes/services/class-product-data-parser.php

# 4. 進行修復（添加錯誤處理、輸入驗證、日誌）
# 5. 測試
# 6. 提交
git add includes/services/class-line-service.php
git commit -m "refactor: 改進 LineService 錯誤處理和輸入驗證

- 添加 try-catch 錯誤處理
- 添加輸入驗證
- 改進日誌記錄
- 統一返回類型
- 評分提升：3.5/5 → 4.5/5

參考：SERVICES-REVIEW-REPORT.md

Co-Authored-By: Claude Sonnet 4.5 <noreply@anthropic.com>"
```

#### 修復 SettingsService（同樣步驟）

### 步驟 2：升級 WebhookLogger 到 DebugService

#### FluentCartService 範例

```bash
# 1. 讀取檔案
cat includes/services/class-fluentcart-service.php

# 2. 修改（使用 Edit tool）
# 將：$this->logger = WebhookLogger::get_instance();
# 改為：$this->debugService = DebugService::get_instance();
#
# 將所有：$this->logger->log(...)
# 改為：$this->debugService->log(...)

# 3. 檢查語法
php -l includes/services/class-fluentcart-service.php

# 4. 提交
git add includes/services/class-fluentcart-service.php
git commit -m "refactor: 升級 FluentCartService 使用 DebugService

- 從 WebhookLogger 遷移到 DebugService
- 使用單例模式 get_instance()
- 保持功能不變

Co-Authored-By: Claude Sonnet 4.5 <noreply@anthropic.com>"
```

對 ImageUploader 和 LineWebhookHandler 重複相同步驟。

### 步驟 3：更新實施檢查清單

```bash
# 修改 docs/planning/IMPLEMENTATION-CHECKLIST.md
# 將完成的項目標記為 [x]
# 更新「最後更新」日期
# 更新「目前階段」描述

git add docs/planning/IMPLEMENTATION-CHECKLIST.md
git commit -m "docs: 更新實施檢查清單 - 第 3 階段完成

- 標記所有服務層優化為完成
- 更新進度狀態
- 準備進入第 4 階段

Co-Authored-By: Claude Sonnet 4.5 <noreply@anthropic.com>"
```

---

## 🧪 測試檢查清單

完成每個修改後，執行以下測試：

### 語法檢查
```bash
php -l includes/services/class-[service-name].php
```

### 功能測試
1. 在瀏覽器中打開 WordPress 管理後台
2. 訪問 BuyGo+1 各個管理頁面
3. 確認無 JavaScript 錯誤（開啟 DevTools Console）
4. 確認無 PHP 錯誤（檢查 debug.log）

### 檢查日誌
```bash
tail -50 "/Users/fishtv/Local Sites/buygo/app/public/wp-content/debug.log"
```

---

## 🎓 關鍵學習點

### 為什麼需要單例模式？
DebugService 需要單例模式因為：
1. 避免多次創建資料庫連接
2. 確保日誌一致性
3. 節省記憶體資源
4. WordPress 最佳實踐

### 服務修復的優先級
1. **高優先級**（已完成）：核心功能，影響用戶體驗
2. **中優先級**（待完成）：重要但非關鍵功能
3. **低優先級**：輔助功能，可延後處理

### Commit Message 格式
```
<type>: <subject>

[optional body]

[optional footer]
```

類型：
- `fix:` 修復 bug
- `feat:` 新功能
- `refactor:` 重構（不改變功能）
- `docs:` 文檔更新
- `test:` 測試相關
- `chore:` 維護任務

---

## 📞 遇到問題怎麼辦？

### 如果不確定某個決定

1. **檢查 BUGFIX-CHECKLIST.md**
   - 這個問題是否已經修復過？
   - 有沒有相關的解決方案？

2. **檢查 SERVICES-REVIEW-REPORT.md**
   - 服務的當前評分是多少？
   - 已知問題有哪些？

3. **參考最佳實踐**
   - ProductDataParser（完美範例）
   - ProductsPage.js（Vue 組件範例）

4. **詢問用戶**
   - 使用 AskUserQuestion tool
   - 提供選項和建議
   - 說明每個選項的影響

### 如果破壞了功能

1. **立即回滾**
   ```bash
   git checkout -- <file>
   ```

2. **檢查 debug.log**
   ```bash
   tail -100 "/Users/fishtv/Local Sites/buygo/app/public/wp-content/debug.log"
   ```

3. **參考工作版本**
   - 查看 git history
   - 參考已完成的服務

### 如果遇到 500 錯誤

最近修復的問題：
- DebugService 必須使用 `get_instance()`，不能用 `new DebugService()`
- 建構函數是 `private`
- 檢查所有使用 DebugService 的地方

---

## 📊 進度追蹤

### 如何更新進度

1. **修改 IMPLEMENTATION-CHECKLIST.md**
   ```markdown
   - [x] 完成的任務
   - [ ] 未完成的任務
   ```

2. **更新「最後更新」日期**

3. **更新「目前階段」描述**

4. **提交更新**
   ```bash
   git commit -m "docs: 更新實施檢查清單"
   ```

### 進度里程碑

- **90% → 95%**：完成中優先級服務修復（2 個服務）
- **95% → 100%**：完成 WebhookLogger 升級（3 個服務）
- **100%**：第 3 階段完成，準備進入第 4 階段

---

## 🎯 成功標準

### 第 3 階段完成標準

✅ **必須滿足以下所有條件**：

1. **所有服務評分 ≥ 4.0**
   - 查看 SERVICES-REVIEW-REPORT.md
   - 確認每個服務都有適當的錯誤處理

2. **統一使用 DebugService**
   - 沒有任何服務使用 `new DebugService()`
   - 沒有任何服務使用 `WebhookLogger`

3. **所有測試通過**
   - 無 PHP 語法錯誤
   - 無 JavaScript 控制台錯誤
   - 所有管理頁面正常工作

4. **文檔已更新**
   - IMPLEMENTATION-CHECKLIST.md 標記完成
   - Git 提交訊息清晰

---

## 🚀 完成第 3 階段後

### 慶祝一下！ 🎉

第 3 階段是整個計畫中最困難的部分，完成後意味著：
- ✅ 代碼結構更清晰
- ✅ 更容易維護
- ✅ Bug 更少
- ✅ 開發速度更快

### 然後準備第 4 階段

閱讀以下內容了解下一步：
- `~/.claude/plans/golden-hopping-mockingbird.md` 第 4 階段部分
- 重點：自動化工具和預提交鉤子

---

## 📚 有用的命令參考

```bash
# 查看當前分支和狀態
git status
git branch

# 查看最近的提交
git log --oneline -10

# 檢查 PHP 語法
php -l <file>

# 搜尋特定內容
grep -r "WebhookLogger" includes/services/
grep -r "new DebugService" includes/

# 查看檔案差異
git diff <file>

# 測試 WordPress 是否正常
curl -I http://test.buygo.me/wp-admin/

# 查看錯誤日誌
tail -f "/Users/fishtv/Local Sites/buygo/app/public/wp-content/debug.log"
```

---

## ✅ 交接確認

新的 Claude 對話應該能夠回答：

- [ ] 當前處於哪個階段？（第 3 階段，90% 完成）
- [ ] 下一步要做什麼？（中優先級服務修復 + WebhookLogger 升級）
- [ ] 哪些檔案不能改動？（已完成的 CSS、Vue 組件、DebugService）
- [ ] 如何檢查已修復的 bug？（查看 BUGFIX-CHECKLIST.md）
- [ ] 如何提交代碼？（遵循 commit message 格式）
- [ ] 遇到問題如何查找答案？（檢查相關文檔和最佳實踐）

---

## 🆘 緊急聯絡資訊

如果遇到嚴重問題，用戶可以：

1. **查看這份交接文檔**：`HANDOFF-NOTES.md`
2. **查看實施檢查清單**：`docs/planning/IMPLEMENTATION-CHECKLIST.md`
3. **查看 Bug 修復記錄**：`docs/bugfix/BUGFIX-CHECKLIST.md`
4. **回滾到最後正常的 commit**：`git log` 查看歷史

---

**祝新的 Claude 對話工作順利！** 🚀

*這份文檔由 Claude Sonnet 4.5 於 2026-01-24 準備，用於項目交接。*

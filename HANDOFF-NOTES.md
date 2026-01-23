# 🔄 BuyGo+1 專案交接說明

> **給新 Claude 對話的交接文檔**
> **最後更新**：2026-01-24 下午
> **當前狀態**：✅ 第 3 階段已完成（100%）→ 準備開始第 4 階段

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

#### 第 3 階段：組件分離（✅ 100% 完成）
- ✅ **CSS 隔離**：5 個 CSS 檔案已提取到 `admin/css/`
- ✅ **Vue 組件提取**：5 個組件已提取到 `admin/js/components/`
- ✅ **Composables**：3 個 composables 在 `includes/views/composables/`
- ✅ **服務層審查**：已完成並產出報告
- ✅ **所有服務層優化完成**：
  - 高優先級服務修復：ProductDataParser (1.5→4.0), ExportService (2.0→4.0), NotificationTemplates (2.0→3.5)
  - 中優先級服務修復：LineService (2.5→4.5), SettingsService (2.5→4.5)
  - WebhookLogger 升級：FluentCartService, ImageUploader, LineWebhookHandler
  - **所有服務評分 ≥ 4.0/5 ✅**

### 🚀 下一步工作：第 4 階段 - 自動化與除錯協助

#### 任務 1：完善結構驗證指令碼
- [ ] 位置：`scripts/validate-structure.sh`
- [ ] 功能：
  - 檢查頁首/內容結構正確性
  - 檢查 wpNonce 定義和導出
  - 檢查 CSS 前綴使用
  - 檢查檢視切換邏輯
- [ ] 測試驗證指令碼

#### 任務 2：更新頁面生成器指令碼
- [ ] 位置：`scripts/create-feature.sh`（已存在）
- [ ] 需要：
  - 更新以使用最新的範本
  - 添加更多範例和使用說明
  - 測試生成器

#### 任務 3：建立預提交鉤子
- [ ] 位置：`.git/hooks/pre-commit`
- [ ] 功能：
  - 檢查 wpNonce 必須在 return 中導出
  - 檢查 REST API permission_callback 不能使用 verify_signature
  - 檢查 HTML 結構必須正確
- [ ] 測試預提交鉤子

#### 任務 4：更新除錯技能
- [ ] 位置：`~/.claude/skills/debug-buygo/SKILL.md`
- [ ] 需要：
  - 添加自動結構檢查部分
  - 更新技能以使用 validate-structure.sh
  - 添加服務層最佳實踐參考

---

## 🚨 重要注意事項

### ⚠️ 絕對不要做的事情

1. **不要重新提取 CSS 或 Vue 組件**
   - 這些工作已完成，重做會破壞現有功能
   - 位置：`admin/css/` 和 `admin/js/components/`

2. **不要修改已優化的服務**
   - 所有服務已經過審查和優化
   - 評分都 ≥ 4.0/5，不需要再改動

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
   - 範例：`feat: 新增預提交鉤子防止 wpNonce 錯誤`

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
│   ├── services/                   # ✅ 所有服務已優化（評分 ≥ 4.0）
│   └── views/
│       └── composables/            # ✅ Vue composables
├── templates/                      # ✅ 可重用範本
├── scripts/                        # ⚠️ 需要完善的自動化指令碼
└── docs/
    ├── planning/                   # 計畫文檔
    ├── development/                # 開發文檔
    ├── bugfix/                     # Bug 修復記錄
    └── testing/                    # 測試文檔
```

### Git 狀態
- **分支**：`main`
- **最新 commit**：`docs: 更新實施檢查清單 - 第 3 階段完成`
- **領先遠端**：27 commits（需要時可 push）

### WordPress 環境
- **Local by Flywheel**：`/Users/fishtv/Local Sites/buygo/`
- **插件路徑**：通過 symlink 連結到開發目錄
- **測試站點**：test.buygo.me（通過 Local 訪問）

---

## 📝 第 4 階段執行指南

### 步驟 1：完善 validate-structure.sh

#### 1.1 讀取現有指令碼
```bash
cat scripts/validate-structure.sh
```

#### 1.2 添加檢查項目
需要檢查的內容：
- [ ] 頁首結構（`<header v-show="currentView === 'list'">`）
- [ ] 內容區域結構（`<div class="flex-1 overflow-auto">`）
- [ ] wpNonce 定義（`const wpNonce = '<?php echo wp_create_nonce...`）
- [ ] wpNonce 導出（在 `return { wpNonce, ...}` 中）
- [ ] CSS 前綴正確使用（例如 `products-*` 在 products.php 中）

#### 1.3 測試指令碼
```bash
./scripts/validate-structure.sh admin/partials/products.php
```

#### 1.4 提交
```bash
git add scripts/validate-structure.sh
git commit -m "feat: 完善結構驗證指令碼

- 添加 wpNonce 檢查
- 添加 CSS 前綴檢查
- 添加 HTML 結構檢查
- 測試通過

Co-Authored-By: Claude Sonnet 4.5 <noreply@anthropic.com>"
```

### 步驟 2：建立預提交鉤子

#### 2.1 建立鉤子檔案
```bash
cat > .git/hooks/pre-commit << 'EOF'
#!/bin/bash

# 預提交鉤子：防止常見錯誤被提交

echo "🔍 運行預提交檢查..."

# 檢查所有被修改的 PHP 檔案
for file in $(git diff --cached --name-only --diff-filter=ACM | grep '\.php$'); do
    echo "檢查: $file"

    # 運行結構驗證
    if [ -x "./scripts/validate-structure.sh" ]; then
        ./scripts/validate-structure.sh "$file"
        if [ $? -ne 0 ]; then
            echo "❌ 結構驗證失敗: $file"
            exit 1
        fi
    fi
done

echo "✅ 所有檢查通過！"
exit 0
EOF

chmod +x .git/hooks/pre-commit
```

#### 2.2 測試鉤子
```bash
# 製造一個錯誤（移除 wpNonce）
# 嘗試提交，應該被阻止
git add test.php
git commit -m "test"  # 應該失敗

# 修復錯誤
# 再次提交，應該成功
```

#### 2.3 提交鉤子說明
```bash
# 建立 hooks/README.md 說明如何安裝鉤子
git add .git/hooks/pre-commit
git add hooks/README.md
git commit -m "feat: 新增預提交鉤子防止常見錯誤

- 檢查 wpNonce 定義和導出
- 檢查 HTML 結構
- 自動運行結構驗證
- 添加安裝說明

Co-Authored-By: Claude Sonnet 4.5 <noreply@anthropic.com>"
```

### 步驟 3：更新除錯技能

#### 3.1 讀取現有技能
```bash
cat ~/.claude/skills/debug-buygo/SKILL.md
```

#### 3.2 添加新章節
添加：
- 自動結構驗證使用說明
- 服務層最佳實踐參考
- 常見錯誤和解決方案

#### 3.3 測試技能
在新對話中測試技能是否正確載入

### 步驟 4：更新文檔

#### 4.1 更新 IMPLEMENTATION-CHECKLIST.md
```bash
# 標記第 4 階段任務為完成
# 更新進度百分比
# 更新「最後更新」日期
```

#### 4.2 提交最終更新
```bash
git add docs/planning/IMPLEMENTATION-CHECKLIST.md
git commit -m "docs: 更新實施檢查清單 - 第 4 階段完成

- 標記所有自動化工具為完成
- 更新整體進度
- 準備進入第 5 階段（選擇性）

Co-Authored-By: Claude Sonnet 4.5 <noreply@anthropic.com>"
```

---

## 🧪 測試檢查清單

完成每個任務後，執行以下測試：

### 驗證指令碼測試
```bash
# 測試正確的檔案（應該通過）
./scripts/validate-structure.sh admin/partials/products.php

# 測試有錯誤的檔案（應該失敗並指出問題）
./scripts/validate-structure.sh admin/partials/broken-example.php
```

### 預提交鉤子測試
```bash
# 測試 1：正常提交（應該成功）
git add admin/partials/products.php
git commit -m "test: normal commit"

# 測試 2：錯誤提交（應該被阻止）
# 製造錯誤後嘗試提交
git add admin/partials/test-broken.php
git commit -m "test: should fail"  # 應該失敗
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

### 為什麼需要自動化工具？
1. **防止回歸**：預提交鉤子阻止已知錯誤被提交
2. **提高效率**：自動驗證比手動檢查快 10 倍
3. **統一標準**：所有開發者遵循相同規範
4. **減少 bug**：早期發現問題，修復成本低

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

2. **檢查 IMPLEMENTATION-CHECKLIST.md**
   - 這個任務是否已經完成？
   - 當前應該做什麼？

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
   - 參考已完成的實現

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

- **第 4 階段開始**：0%
- **完成驗證指令碼**：25%
- **完成預提交鉤子**：50%
- **完成技能更新**：75%
- **所有測試通過**：100%

---

## 🎯 成功標準

### 第 4 階段完成標準

✅ **必須滿足以下所有條件**：

1. **驗證指令碼正常工作**
   - 能檢查所有必要項目
   - 正確識別錯誤
   - 通過所有測試

2. **預提交鉤子正常工作**
   - 自動運行檢查
   - 阻止錯誤提交
   - 允許正確提交

3. **技能已更新**
   - 包含自動化工具說明
   - 包含最佳實踐參考
   - 在新對話中可用

4. **文檔已更新**
   - IMPLEMENTATION-CHECKLIST.md 標記完成
   - Git 提交訊息清晰
   - 所有更改已提交

---

## 🚀 完成第 4 階段後

### 慶祝一下！ 🎉

第 4 階段完成後意味著：
- ✅ 自動化工具已建立
- ✅ 預提交鉤子防止錯誤
- ✅ 開發效率大幅提升
- ✅ Bug 數量顯著減少

### 然後決定是否進行第 5 階段

閱讀以下內容了解下一步：
- `~/.claude/plans/golden-hopping-mockingbird.md` 第 5 階段部分
- 重點：Vite 構建系統（選擇性）
- 決定：是否需要更現代的構建系統

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

# 運行驗證指令碼
./scripts/validate-structure.sh <file>

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

- [ ] 當前處於哪個階段？（第 3 階段完成，準備開始第 4 階段）
- [ ] 第 3 階段完成了什麼？（CSS 隔離、Vue 組件提取、服務層優化）
- [ ] 第 4 階段要做什麼？（自動化工具、預提交鉤子、技能更新）
- [ ] 哪些檔案不能改動？（已完成的 CSS、Vue 組件、優化的服務）
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
*第 3 階段已完成，所有服務評分 ≥ 4.0/5，準備開始第 4 階段。*

# 🚀 快速開始指南

**歡迎！** 你已經有了一個完整的 WordPress 外掛開發框架。

---

## ⚡ 30 秒快速開始

### 試試看現有外掛

```bash
cd /Users/fishtv/Development/buygo-plus-one
composer test
```

**預期結果**:
```
PHPUnit 9.6.31 by Sebastian Bergmann

.......

Time: 00:00.003, Memory: 6.00 MB

OK (7 tests, 9 assertions) ✓
```

---

## 📚 接下來做什麼？

### 選項 A: 快速學習 (30 分鐘)

1. **閱讀快速指南** (5 min)
   ```
   打開: .plugin-testing/docs/00-開始使用.md
   ```

2. **閱讀完整指南** (20 min)
   ```
   打開: .plugin-testing/docs/01-完整操作指南.md
   ```

3. **試試看** (5 min)
   ```bash
   cd /Users/fishtv/Development/buygo-plus-one
   composer test
   ```

**結果**: 理解完整系統

### 選項 B: 直接與 AI 協作 (推薦)

1. **打開這個文件夾**
   ```
   /Users/fishtv/Development/
   ```

2. **告訴 AI 你想做什麼**
   ```
   「測試 buygo-plus-one」
   「我改了代碼」
   「新增外掛 payment-gateway」
   ```

3. **AI 會自動完成**
   - 檢測環境
   - 執行操作
   - 驗證結果
   - 報告反饋

**結果**: AI 幫你完成任務

### 選項 C: 深入學習

1. **讀完整的 README**
   ```
   打開: /Users/fishtv/Development/README.md
   ```

2. **讀所有文檔**
   ```
   查看: .plugin-testing/docs/
   ```

3. **按步驟操作**

---

## 🎯 常見任務

### 任務 1: 執行測試

```bash
cd /Users/fishtv/Development/buygo-plus-one
composer test
```

### 任務 2: 修改代碼並驗證

```bash
# 1. 修改代碼
# (打開編輯器修改 includes/ 中的文件)

# 2. 執行測試
cd /Users/fishtv/Development/buygo-plus-one
composer test

# 3. 備份改變
git add .
git commit -m "說明你的改變"

# 4. 查看歷史
git log --oneline -3
```

### 任務 3: 新增外掛

```bash
cd /Users/fishtv/Development
bash .plugin-testing/scripts/init-new-plugin.sh my-plugin-name

# 腳本會自動:
# ✓ 創建目錄結構
# ✓ 複製模板文件
# ✓ 安裝依賴
# ✓ 運行首個測試
```

### 任務 4: 與 AI 協作

打開 `/Users/fishtv/Development/`，告訴 AI:

```
「幫我測試 buygo-plus-one」
「我改了 ProductService，驗證一下」
「新增一個 shipping 外掛」
「所有外掛都測試」
```

AI 會自動:
- 理解你的需求
- 找到相關代碼
- 執行必要操作
- 報告結果
- 建議下一步

---

## 📖 文檔快速索引

| 需求 | 文檔 | 時間 |
|------|------|------|
| 快速開始 | [00-開始使用.md](.plugin-testing/docs/00-開始使用.md) | 5 min |
| 完整理解 | [01-完整操作指南.md](.plugin-testing/docs/01-完整操作指南.md) | 30 min |
| AI 協作 | [02-與AI協作.md](.plugin-testing/docs/02-與AI協作.md) | 15 min |
| 排查問題 | [03-故障排除.md](.plugin-testing/docs/03-故障排除.md) | as needed |
| 文檔索引 | [INDEX.md](.plugin-testing/docs/INDEX.md) | 5 min |
| 完整說明 | [README.md](README.md) | 20 min |

---

## 🌟 系統特點

| 特點 | 說明 |
|------|------|
| 🤖 智能 AI | AI 自動理解上下文並執行操作 |
| 🔍 自動檢測 | 自動檢查環境和依賴 |
| 💾 自動備份 | 每次修改都可以備份 (git) |
| 📊 測試驗證 | 修改後自動執行測試 |
| 🔄 可回滾 | 任何時刻都可回滾到之前版本 |
| 📚 完整文檔 | 詳細的指南和最佳實踐 |
| 🚀 易於擴展 | 輕鬆添加新外掛 |

---

## ✅ 系統狀態

```
✅ 框架層完成
   ├─ 文檔 (5 個完整文檔)
   ├─ 模板 (5 個項目模板)
   ├─ 腳本 (自動化腳本)
   └─ 配置 (統一配置)

✅ 項目層完成
   ├─ buygo-plus-one (初始化完成)
   ├─ 7 個測試全部通過
   └─ 可立即使用

✅ AI 協作就緒
   ├─ 自動上下文檢測
   ├─ 智能命令理解
   └─ 完整工作流程支持

狀態: 100% 準備就緒 ✓
```

---

## 🎓 學習路線

### 第一天

- [ ] 試 `composer test` (2 min)
- [ ] 讀快速開始指南 (5 min)
- [ ] 讀完整指南 (20 min)

### 第二天

- [ ] 讀 AI 協作指南 (15 min)
- [ ] 修改代碼並驗證
- [ ] 學習 git 備份

### 第三天

- [ ] 試試新增外掛
- [ ] 閱讀測試最佳實踐
- [ ] 編寫新測試

### 第一週

- [ ] 為所有 Service 編寫測試
- [ ] 達到 80%+ 代碼覆蓋率
- [ ] 建立完整工作流

---

## 💡 核心概念

### 分層架構

```
框架層 (.plugin-testing/)
│
├─ 統一配置
├─ 自動化腳本
├─ 完整文檔
└─ 項目模板
│
└─ ↓ 被所有外掛使用
│
項目層 (各個外掛/)
│
├─ 代碼
├─ 測試
└─ 業務邏輯
```

**好處**:
- 配置不重複 ✓
- 易於維護 ✓
- 易於擴展 ✓
- 統一標準 ✓

### AI 工作流程

```
你: 「測試 buygo-plus-one」

AI:
1. 理解命令
2. 掃描目錄
3. 找到外掛
4. 執行 composer test
5. 收集結果
6. 報告並建議下一步
```

**所有這些都是自動的**，你只需要說你想做什麼。

---

## 🚀 立即開始

### 方式 1: 終端操作 (3 分鐘)

```bash
# 進入外掛
cd /Users/fishtv/Development/buygo-plus-one

# 執行測試
composer test

# 結果: OK (7 tests, 9 assertions) ✓
```

### 方式 2: 與 AI 協作 (推薦)

1. 打開文件夾: `/Users/fishtv/Development/`
2. 告訴 AI: 「測試 buygo-plus-one」
3. AI 完成所有操作

### 方式 3: 新增外掛 (5 分鐘)

```bash
cd /Users/fishtv/Development
bash .plugin-testing/scripts/init-new-plugin.sh payment-gateway

# 自動完成初始化並運行測試
```

---

## 📁 文件位置速查

```
/Users/fishtv/Development/           ← 你現在在這裡
├─ README.md                         ← 根目錄說明
├─ GETTING-STARTED.md                ← 本文件
│
├─ .plugin-testing/                  ← 框架層
│  ├─ README.md
│  ├─ SETUP-COMPLETE.md
│  ├─ docs/                          ← 5 個完整文檔
│  ├─ templates/                     ← 5 個項目模板
│  ├─ scripts/                       ← 自動化腳本
│  └─ .gitignore
│
└─ buygo-plus-one/                   ← 項目層
   ├─ includes/
   ├─ tests/
   ├─ composer.json
   ├─ phpunit-unit.xml
   ├─ TESTING.md
   └─ bin/
```

---

## 🎯 下一步

### 立即做 (今天)

- [ ] 執行 `composer test` 驗證系統
- [ ] 選擇一個學習路線
- [ ] 開始閱讀相應文檔

### 本周做 (這週)

- [ ] 完成基礎文檔閱讀
- [ ] 修改代碼並驗證
- [ ] 學習與 AI 協作

### 下週做 (下週)

- [ ] 新增第二個外掛
- [ ] 提高測試覆蓋率
- [ ] 建立完整工作流

---

## 🆘 需要幫助？

### 問題 1: 不知道怎麼做

**查閱**:
- [README.md](README.md) - 完整說明
- [.plugin-testing/docs/INDEX.md](.plugin-testing/docs/INDEX.md) - 文檔索引
- [.plugin-testing/docs/01-完整操作指南.md](.plugin-testing/docs/01-完整操作指南.md) - 詳細步驟

### 問題 2: 執行過程出錯

**查閱**:
- [.plugin-testing/docs/03-故障排除.md](.plugin-testing/docs/03-故障排除.md) - 問題排查

### 問題 3: 想與 AI 協作

**查閱**:
- [.plugin-testing/docs/02-與AI協作.md](.plugin-testing/docs/02-與AI協作.md) - AI 使用指南

### 問題 4: 直接詢問 AI

打開這個文件夾，直接告訴 AI 你的問題。AI 會幫你快速診斷和解決。

---

## ✨ 記住這些

✅ **直接告訴 AI 你想做什麼**
- 「測試代碼」
- 「我改了什麼」
- 「新增外掛」

❌ **不要**
- 給 AI 一堆命令
- 隱藏錯誤訊息
- 假設 AI 知道文件位置

**最重要的**: 這個系統設計的目的就是為了方便你。有問題直接問 AI，不用記住任何命令。

---

## 🎉 準備好了嗎？

### 選擇你的起點

**我是新手** → 讀 [00-開始使用.md](.plugin-testing/docs/00-開始使用.md) (5 min)

**我想快速開始** → 執行 `composer test` (2 min)

**我想與 AI 協作** → 告訴 AI 你的需求 (1 min)

**我想深入理解** → 讀 [README.md](README.md) (20 min)

---

**祝你開發愉快！** 🚀

系統已完全就緒。立即開始吧！

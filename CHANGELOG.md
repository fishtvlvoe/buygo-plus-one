# 版本記錄

## v0.2.0 - 2026-01-31

### 新增功能
- ✨ 建立 GitHub 自動發布工作流程
- ✨ 整合自動更新機制，支援從 GitHub Releases 自動更新
- ✨ 新增發布腳本 (`release.sh`) 簡化發布流程
- 📝 新增發布指南文件 (`RELEASE-GUIDE.md`)
- 📝 新增更新測試文件 (`TESTING-UPDATE.md`)

### 改進
- 🔧 更新外掛名稱從「BuyGo+1 開發版」改為「BuyGo+1」
- 🔧 標準化版本號為 0.2.0（遵循語意化版本規範）
- 📦 建立 `.zipignore` 檔案，排除開發檔案不打包

### 技術改進
- 🏗️ 實作 `Updater` 類別處理自動更新邏輯
- 🏗️ 建立 GitHub Actions 自動建置和發布 Release
- 🔄 自動更新快取機制（12 小時快取週期）

## 2026-01-22 05:30
- 初始化 CHANGELOG.md
- 更新 .gitignore 新增 node_modules 排除規則

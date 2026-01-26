# 自動發布使用指南

## 快速發布新版本

### 方法一：使用發布腳本（推薦）

```bash
cd /Users/fishtv/Development/buygo-plus-one
./release.sh
```

腳本會自動：
1. 詢問版本更新類型（Patch/Minor/Major）
2. 自動更新版本號
3. Commit 並 push 到 GitHub
4. 建立 tag 並推送
5. 自動建立 GitHub Release

### 方法二：告訴 Claude

直接告訴 Claude：
- 「發布新版本」
- 「我要發布新版本到 GitHub」
- 「幫我發布 v0.0.4」

Claude 會自動執行發布流程。

## 版本號規則

- **修訂號 +1** (Patch)：`0.0.3` → `0.0.4` - 只修 bug、小修正
- **次版本號 +1** (Minor)：`0.0.3` → `0.1.0` - 新增功能
- **主版本號 +1** (Major)：`0.0.3` → `1.0.0` - 重大變更、不相容

## 檢查更新機制

發布後，客戶端會在 12 小時內自動檢查更新，或可手動在 WordPress 後台點擊「檢查更新」。

## 相關檔案

- `release.sh` - 完整自動化發布腳本
- `bump-version.sh` - 版本號更新腳本
- `includes/class-updater.php` - 更新器類別
- `GITHUB-SETUP.md` - 詳細設定說明

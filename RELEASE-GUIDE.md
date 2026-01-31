# BuyGo+1 發布指南

本指南說明如何發布新版本的 BuyGo+1 外掛到 GitHub，並讓使用者自動更新。

## 發布流程

### 1. 準備發布

在發布前，確保：

- [ ] 所有功能已完成並測試通過
- [ ] 更新 `CHANGELOG.md` 記錄所有變更
- [ ] 更新版本號（見下方）
- [ ] 所有變更已提交到 git

### 2. 更新版本號

需要更新兩個地方的版本號：

**buygo-plus-one.php**（第 6 行）：
```php
 * Version:           0.2.0
```

**buygo-plus-one.php**（第 25 行）：
```php
define('BUYGO_PLUS_ONE_VERSION', '0.2.0');
```

### 3. 提交版本變更

```bash
git add buygo-plus-one.php CHANGELOG.md
git commit -m "chore: bump version to 0.2.0"
git push origin main
```

### 4. 執行發布腳本

```bash
./release.sh
```

腳本會：
1. 檢查未提交的變更
2. 從 `buygo-plus-one.php` 讀取版本號
3. 建立 git tag（例如：v0.2.0）
4. 推送 tag 到 GitHub

### 5. 自動化發布

推送 tag 後，GitHub Actions 會自動：

1. 安裝 Composer 依賴（僅生產環境）
2. 排除開發檔案（測試、文件等）
3. 建立 ZIP 檔案
4. 在 GitHub Releases 建立新版本
5. 上傳 ZIP 檔案作為 Release Asset

**監控進度：**
https://github.com/fishtvlvoe/buygo-plus-one/actions

**查看 Release：**
https://github.com/fishtvlvoe/buygo-plus-one/releases

## 自動更新機制

### 運作原理

1. WordPress 每 12 小時檢查一次外掛更新
2. `Updater` 類別會查詢 GitHub API 取得最新版本
3. 如果有新版本，會在 WordPress 後台顯示更新通知
4. 使用者點擊「更新」後，WordPress 會下載並安裝新版本

### 更新檢查流程

```
WordPress 後台
  ↓
檢查更新（每 12 小時）
  ↓
Updater::check_update()
  ↓
GitHub API (cached 12 hours)
  ↓
比較版本號
  ↓
顯示更新通知
```

### 清除更新快取

如果需要手動清除快取：

```php
delete_transient('buygo_plus_one_release_info');
```

或在 WordPress 後台：
1. 外掛 → 安裝的外掛
2. 點擊「檢查更新」

## 版本號規範

遵循語意化版本（Semantic Versioning）：

- **主版本號**（Major）：不向下相容的重大變更
- **次版本號**（Minor）：向下相容的新功能
- **修訂號**（Patch）：向下相容的錯誤修復

範例：
- `0.1.0` → `0.2.0`：新增功能
- `0.2.0` → `0.2.1`：錯誤修復
- `0.9.0` → `1.0.0`：重大版本發布

## 常見問題

### Q: 如何刪除已發布的版本？

在 GitHub Releases 頁面刪除 Release，然後刪除 tag：

```bash
git tag -d v0.2.0
git push origin :refs/tags/v0.2.0
```

### Q: 發布失敗怎麼辦？

1. 檢查 GitHub Actions 日誌找出錯誤原因
2. 修復問題後，刪除失敗的 tag
3. 重新執行 `./release.sh`

### Q: 如何測試自動更新？

1. 在測試網站安裝舊版本外掛
2. 發布新版本到 GitHub
3. 在測試網站後台檢查更新
4. 確認看到更新通知並執行更新

### Q: 更新後資料會遺失嗎？

不會。外掛更新只會替換檔案，不會刪除：
- 資料庫資料
- 外掛設定
- 上傳的檔案

## 緊急回退

如果新版本有嚴重問題，可以：

1. **標記為 Prerelease**：在 GitHub Release 編輯頁面勾選 "This is a pre-release"
2. **發布修復版本**：儘快發布修復版本（例如 0.2.1）
3. **通知使用者**：透過 LINE 或其他管道通知使用者暫緩更新

## 檔案排除規則

以下檔案不會包含在發布的 ZIP 中（定義於 `.zipignore`）：

- Git 檔案（`.git`, `.gitignore`, `.github`）
- 開發文件（`*.md` 除了 `README.md`）
- 測試檔案（`tests/`, `phpunit*.xml`）
- 開發依賴（`composer.json`, `package.json`）
- 建置檔案（`release/`, `*.log`）

## 相關連結

- [GitHub Repository](https://github.com/fishtvlvoe/buygo-plus-one)
- [GitHub Releases](https://github.com/fishtvlvoe/buygo-plus-one/releases)
- [GitHub Actions](https://github.com/fishtvlvoe/buygo-plus-one/actions)

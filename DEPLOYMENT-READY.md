# BuyGo+1 v0.2.0 部署就緒

## 完成狀態

✅ **所有發布系統已建立完成，隨時可以發布到 GitHub Releases**

## 已完成項目

### 1. 版本號更新
- ✅ 外掛名稱從「BuyGo+1 開發版」改為「BuyGo+1」
- ✅ 版本號更新為 0.2.0（符合語意化版本規範）
- ✅ 兩處版本號已同步更新（Header 和常數定義）

### 2. GitHub Actions 自動發布
- ✅ [.github/workflows/release.yml](.github/workflows/release.yml) - 自動發布工作流程
  - 當推送 tag（如 v0.2.0）時自動觸發
  - 自動安裝 Composer 依賴（僅生產環境）
  - 自動排除開發檔案並建立 ZIP
  - 自動建立 GitHub Release
  - 自動上傳 ZIP 檔案

### 3. WordPress 自動更新機制
- ✅ [includes/class-updater.php](includes/class-updater.php) - 自動更新器類別
  - 每 12 小時檢查一次 GitHub Releases
  - 快取機制減少 API 呼叫
  - 版本比較和更新通知
  - 外掛資訊頁面整合

### 4. 發布工具
- ✅ [release.sh](release.sh) - 發布腳本
  - 自動讀取版本號
  - 建立並推送 git tag
  - 互動式確認流程
  - 錯誤檢查和防護

### 5. 檔案排除規則
- ✅ [.zipignore](.zipignore) - 打包排除清單
  - 排除 Git 檔案
  - 排除開發文件
  - 排除測試檔案
  - 排除開發依賴

### 6. 文件
- ✅ [RELEASE-GUIDE.md](RELEASE-GUIDE.md) - 完整發布指南
  - 發布流程說明
  - 自動更新機制說明
  - 版本號規範
  - 常見問題解答

- ✅ [TESTING-UPDATE.md](TESTING-UPDATE.md) - 更新測試指南
  - 測試環境準備
  - 詳細測試步驟
  - 測試檢查清單
  - 問題排查指南

- ✅ [README.md](README.md) - 更新安裝說明
  - 從 GitHub Releases 安裝的說明
  - 自動更新功能說明
  - 發布流程快速參考

- ✅ [CHANGELOG.md](CHANGELOG.md) - 版本變更記錄
  - v0.2.0 詳細變更記錄
  - 新增功能、改進、技術更新

## 發布流程

### 現在就可以發布！

```bash
# 1. 推送到 GitHub（如果還沒推送）
git push origin main

# 2. 執行發布腳本
./release.sh

# 3. 監控 GitHub Actions
# 訪問 https://github.com/fishtvlvoe/buygo-plus-one/actions
# 確認工作流程執行成功

# 4. 驗證 Release
# 訪問 https://github.com/fishtvlvoe/buygo-plus-one/releases
# 確認 v0.2.0 已建立並包含 ZIP 檔案
```

### 後續步驟

1. **測試自動更新**
   - 在測試 WordPress 網站安裝舊版本（如果有）
   - 發布 v0.2.0 到 GitHub
   - 在測試網站檢查是否出現更新通知
   - 執行更新並驗證功能

2. **部署到生產環境**
   - 從 GitHub Releases 下載 ZIP
   - 在生產網站安裝/更新外掛
   - 確認所有功能正常運作

3. **監控更新**
   - 檢查 GitHub API 呼叫情況
   - 確認快取機制正常運作
   - 收集使用者反饋

## 檔案清單

### 新增檔案
- `.github/workflows/release.yml` - GitHub Actions 工作流程
- `includes/class-updater.php` - 自動更新器
- `release.sh` - 發布腳本
- `.zipignore` - 打包排除清單
- `RELEASE-GUIDE.md` - 發布指南
- `TESTING-UPDATE.md` - 測試指南
- `DEPLOYMENT-READY.md` - 本檔案

### 修改檔案
- `buygo-plus-one.php` - 更新版本號和載入更新器
- `README.md` - 更新安裝說明
- `CHANGELOG.md` - 新增 v0.2.0 變更記錄

## 技術細節

### 自動更新運作原理

```
WordPress 後台
    ↓
檢查更新（每 12 小時）
    ↓
Updater::check_update()
    ↓
查詢 GitHub API
    ↓
/repos/fishtvlvoe/buygo-plus-one/releases/latest
    ↓
快取 12 小時
    ↓
比較版本號
    ↓
如果有新版本 → 顯示更新通知
    ↓
使用者點擊更新
    ↓
WordPress 下載 ZIP
    ↓
安裝並啟用
```

### GitHub Actions 工作流程

```
推送 tag (v0.2.0)
    ↓
觸發 GitHub Actions
    ↓
Checkout 程式碼
    ↓
安裝 PHP 7.4
    ↓
composer install --no-dev
    ↓
使用 .zipignore 排除檔案
    ↓
建立 ZIP
    ↓
建立 Release
    ↓
上傳 ZIP 作為 Asset
```

### 版本號位置

1. **buygo-plus-one.php** (第 6 行)
   ```php
   * Version:           0.2.0
   ```

2. **buygo-plus-one.php** (第 25 行)
   ```php
   define('BUYGO_PLUS_ONE_VERSION', '0.2.0');
   ```

## 依賴關係

### 必要條件
- ✅ GitHub 倉庫：https://github.com/fishtvlvoe/buygo-plus-one
- ✅ GitHub Actions 啟用
- ✅ Git tag 推送權限

### WordPress 環境
- WordPress 5.8+
- PHP 7.4+
- `wp_remote_get()` 功能正常（檢查 GitHub API）
- Transients 支援（快取機制）

### 外掛依賴
- `buygo-line-notify` 外掛（LINE 功能必需）

## 注意事項

### 重要提醒
1. 發布前確保所有測試通過
2. 確認 CHANGELOG.md 已更新
3. 版本號必須同步（Header 和常數）
4. 發布後無法輕易回退，請謹慎發布

### 已知限制
1. GitHub API 速率限制：60 次/小時（未認證）
2. ZIP 檔案大小限制：取決於 GitHub Release 限制
3. WordPress 更新檢查頻率：12 小時（可調整）

## 支援資源

### 文件
- [發布指南](RELEASE-GUIDE.md)
- [測試指南](TESTING-UPDATE.md)
- [README](README.md)
- [CHANGELOG](CHANGELOG.md)

### 連結
- [GitHub Repository](https://github.com/fishtvlvoe/buygo-plus-one)
- [GitHub Actions](https://github.com/fishtvlvoe/buygo-plus-one/actions)
- [GitHub Releases](https://github.com/fishtvlvoe/buygo-plus-one/releases)

## 版本資訊

- **外掛名稱**：BuyGo+1
- **版本號**：0.2.0
- **發布日期**：2026-01-31
- **WordPress 最低版本**：5.8
- **PHP 最低版本**：7.4
- **授權**：GPL v2 或更新版本

---

**準備就緒！** 🚀

執行 `./release.sh` 即可開始發布流程。

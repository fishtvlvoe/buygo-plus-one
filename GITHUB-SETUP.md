# GitHub 自動更新設定指南

本文件說明如何設定 `buygo-plus-one` 外掛的 GitHub 自動更新功能。

## 前置需求

1. **GitHub Repository**
   - 在 GitHub 建立 repository：`fishtvlvoe/buygo-plus-one`
   - 確保本地專案已連接到 GitHub remote

2. **GitHub Personal Access Token**
   - 前往：https://github.com/settings/tokens
   - 點擊「Generate new token (classic)」
   - 勾選 `repo` 權限
   - 複製 token

3. **設定環境變數**
   ```bash
   export GITHUB_TOKEN='你的token'
   ```
   
   或加入 `~/.zshrc`：
   ```bash
   echo "export GITHUB_TOKEN='你的token'" >> ~/.zshrc
   source ~/.zshrc
   ```

## 設定步驟

### 1. 初始化 GitHub Repository

如果還沒有建立 GitHub repository：

```bash
cd /Users/fishtv/Development/buygo-plus-one

# 檢查是否已有 git remote
git remote -v

# 如果沒有，新增 GitHub remote
git remote add origin https://github.com/fishtvlvoe/buygo-plus-one.git

# Push 到 GitHub
git push -u origin main
```

### 2. 設定腳本權限

```bash
chmod +x release.sh
chmod +x bump-version.sh
```

### 3. 驗證更新器設定

確認 `includes/class-updater.php` 中的 repository URL 正確：

```php
private const UPDATE_SERVER_URL = 'https://api.github.com/repos/fishtvlvoe/buygo-plus-one/releases/latest';
```

## 發布新版本

### 方法一：使用完整自動化腳本（推薦）

```bash
./release.sh
```

這個腳本會：
1. 詢問版本更新類型（Patch/Minor/Major）
2. 自動更新版本號
3. Commit 並 push
4. 建立 tag
5. 自動建立 GitHub Release（需要 GITHUB_TOKEN）

### 方法二：使用半自動腳本

```bash
./bump-version.sh
```

這個腳本會：
1. 詢問版本更新類型
2. 自動更新版本號
3. 可選擇是否 commit/push/tag
4. 需要手動建立 GitHub Release

### 方法三：手動發布

1. 更新版本號（在 `buygo-plus-one.php`）
2. Commit 並 push
3. 建立 tag：`git tag v0.0.4 && git push origin v0.0.4`
4. 前往 GitHub Releases 頁面建立 Release

## 建立 Release ZIP 檔案

當建立 GitHub Release 時，有兩種方式提供下載檔案：

### 方式一：使用 GitHub 自動產生的 ZIP

GitHub 會自動為每個 tag 產生 ZIP 檔案，URL 格式：
```
https://github.com/fishtvlvoe/buygo-plus-one/archive/refs/tags/v0.0.4.zip
```

更新器會自動使用這個 URL。

### 方式二：手動上傳 ZIP 檔案

1. 建立外掛 ZIP 檔案（只包含必要檔案，排除開發檔案）
2. 在建立 Release 時上傳 ZIP 檔案
3. 更新器會優先使用 Release Assets 中的 ZIP

## 測試自動更新

1. 在測試網站安裝舊版本外掛
2. 發布新版本到 GitHub
3. 等待 12 小時（或清除 transient 快取）
4. 前往 WordPress 後台 → 外掛 → 檢查更新
5. 應該會看到新版本可用

### 手動清除快取（測試用）

```php
// 在 WordPress 中執行
delete_transient('buygo_plus_one_update_info');
```

## 版本號規則

- **修訂號 +1** (Patch)：`0.0.3` → `0.0.4` - 只修 bug、小修正
- **次版本號 +1** (Minor)：`0.0.3` → `0.1.0` - 新增功能
- **主版本號 +1** (Major)：`0.0.3` → `1.0.0` - 重大變更、不相容

## 故障排除

### 更新器無法檢查更新

1. 檢查 `UPDATE_SERVER_URL` 是否正確
2. 檢查 GitHub repository 是否公開（或 token 有權限）
3. 檢查 WordPress 錯誤日誌

### Release 建立失敗

1. 確認 `GITHUB_TOKEN` 已設定
2. 確認 token 有 `repo` 權限
3. 檢查 repository 名稱是否正確

### 客戶端無法更新

1. 確認 Release 已建立且 tag 正確
2. 確認 ZIP 檔案可下載
3. 檢查 WordPress 版本是否符合要求
4. 清除 transient 快取後重試

## 相關檔案

- `includes/class-updater.php` - 更新器類別
- `release.sh` - 完整自動化發布腳本
- `bump-version.sh` - 版本號更新腳本
- `.gitattributes` - 控制發布時包含的檔案

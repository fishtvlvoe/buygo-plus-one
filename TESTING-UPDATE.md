# 測試自動更新功能

本文件說明如何測試 BuyGo+1 的自動更新功能。

## 測試環境準備

### 1. 建立測試 WordPress 網站

建議使用以下任一方式：
- Local by Flywheel（本地測試）
- InstaWP（臨時測試網站）
- 獨立的測試伺服器

### 2. 安裝舊版本外掛

為了測試更新功能，需要先安裝一個舊版本：

1. 從 GitHub Releases 下載舊版本（例如 v0.1.0）
2. 在 WordPress 後台上傳並啟用

或手動建立舊版本：
1. 將當前版本號改為 `0.1.0`
2. 建立 ZIP 檔案並安裝

## 測試步驟

### 階段 1：發布新版本

1. **確保版本號已更新**
   ```bash
   # 檢查版本號
   grep "Version:" buygo-plus-one.php
   grep "BUYGO_PLUS_ONE_VERSION" buygo-plus-one.php
   ```

2. **提交變更**
   ```bash
   git add .
   git commit -m "chore: prepare for v0.2.0 release"
   git push origin main
   ```

3. **執行發布腳本**
   ```bash
   ./release.sh
   ```

4. **監控 GitHub Actions**
   - 訪問：https://github.com/fishtvlvoe/buygo-plus-one/actions
   - 確認工作流程執行成功
   - 檢查是否建立了 Release 和上傳了 ZIP 檔案

### 階段 2：測試更新檢查

1. **手動觸發更新檢查**

   在 WordPress 後台執行以下其中一個操作：
   - 外掛 → 安裝的外掛 → 點擊「檢查更新」
   - 控制台 → 更新 → 點擊「重新檢查」

2. **驗證 API 呼叫**

   透過瀏覽器開發者工具（Network 標籤）檢查：
   - WordPress 是否呼叫了 GitHub API
   - API 回應是否包含最新版本資訊

3. **檢查快取**

   在 WordPress 中執行：
   ```php
   $cache = get_transient('buygo_plus_one_release_info');
   var_dump($cache);
   ```

### 階段 3：測試更新安裝

1. **確認更新通知**
   - 在「外掛」頁面應該看到 BuyGo+1 有新版本
   - 檢查版本號是否正確（應為 0.2.0）

2. **查看更新詳情**
   - 點擊「查看版本 0.2.0 詳細資訊」
   - 確認顯示的更新內容正確

3. **執行更新**
   - 點擊「立即更新」
   - 等待更新完成

4. **驗證更新結果**
   ```bash
   # 檢查版本號
   在 WordPress 後台 → 外掛 → 已安裝外掛
   確認 BuyGo+1 版本為 0.2.0
   ```

### 階段 4：功能測試

更新完成後，測試主要功能：

- [ ] 外掛正常啟用
- [ ] 資料表結構完整
- [ ] 舊資料保留完整
- [ ] 設定未遺失
- [ ] 主要功能運作正常（商品、訂單、出貨）
- [ ] LINE 整合正常

## 測試檢查清單

### 發布前檢查
- [ ] 版本號已更新（兩處）
- [ ] CHANGELOG.md 已更新
- [ ] 所有測試通過
- [ ] 無未提交的變更
- [ ] 在 main 分支

### GitHub Actions 檢查
- [ ] 工作流程執行成功
- [ ] Release 已建立
- [ ] ZIP 檔案已上傳
- [ ] Release 描述正確
- [ ] 下載連結有效

### 更新功能檢查
- [ ] 更新通知顯示
- [ ] 版本號正確
- [ ] 更新說明正確
- [ ] 下載連結有效
- [ ] 更新過程順利
- [ ] 更新後功能正常

### 相容性檢查
- [ ] WordPress 5.8+ 相容
- [ ] PHP 7.4+ 相容
- [ ] 與 buygo-line-notify 相容
- [ ] 資料庫遷移正確

## 常見問題排查

### 看不到更新通知

1. **清除快取**
   ```php
   delete_transient('buygo_plus_one_release_info');
   wp_cache_flush();
   ```

2. **手動檢查 API**
   ```bash
   curl -H "Accept: application/vnd.github.v3+json" \
     https://api.github.com/repos/fishtvlvoe/buygo-plus-one/releases/latest
   ```

3. **檢查錯誤日誌**
   - 啟用 WordPress 除錯模式
   - 檢查 `wp-content/debug.log`

### 更新失敗

1. **檢查下載連結**
   - 確認 GitHub Release 有 ZIP 檔案
   - 測試下載連結是否有效

2. **檢查權限**
   - 確認 WordPress 有寫入權限
   - 檢查 `wp-content/plugins/` 目錄權限

3. **手動更新**
   - 下載 ZIP 檔案
   - 手動上傳並覆蓋舊版本

### API 限制

GitHub API 有速率限制（每小時 60 次）。如果超過限制：

1. 等待一小時後重試
2. 使用 Personal Access Token（需修改 Updater 類別）
3. 增加快取時間

## 進階測試

### 測試快取機制

1. **安裝舊版本**
2. **執行首次更新檢查**（建立快取）
3. **檢查資料庫**
   ```sql
   SELECT * FROM wp_options
   WHERE option_name LIKE '%buygo_plus_one_release_info%';
   ```
4. **12 小時內再次檢查**（應使用快取）
5. **清除快取後檢查**（應重新呼叫 API）

### 測試版本比較

建立不同版本號測試：
- `0.1.0` → `0.2.0`（應顯示更新）
- `0.2.0` → `0.2.0`（不應顯示更新）
- `0.3.0` → `0.2.0`（不應顯示更新）

### 測試錯誤處理

模擬各種錯誤情況：
1. GitHub API 無回應
2. Release 沒有 ZIP 檔案
3. 下載連結失效
4. 網路連線問題

## 效能測試

### 檢查 API 呼叫次數

使用以下工具監控：
- WordPress Query Monitor 外掛
- 瀏覽器開發者工具
- 伺服器日誌

確認：
- 快取生效時不呼叫 API
- 每 12 小時最多呼叫一次
- 更新後清除快取

## 自動化測試腳本

可以建立 PHP 腳本自動測試：

```php
<?php
// test-updater.php

require_once 'wp-load.php';

// 清除快取
delete_transient('buygo_plus_one_release_info');

// 觸發更新檢查
wp_update_plugins();

// 檢查是否有更新
$update_plugins = get_site_transient('update_plugins');
$plugin_slug = 'buygo-plus-one/buygo-plus-one.php';

if (isset($update_plugins->response[$plugin_slug])) {
    echo "✅ 偵測到新版本: " . $update_plugins->response[$plugin_slug]->new_version . "\n";
} else {
    echo "❌ 沒有偵測到更新\n";
}

// 顯示快取資料
$cache = get_transient('buygo_plus_one_release_info');
echo "\n快取資料:\n";
print_r($cache);
```

執行：
```bash
php test-updater.php
```

## 參考資源

- [WordPress Plugin Update Checker](https://developer.wordpress.org/plugins/plugin-basics/determining-plugin-and-content-directories/)
- [GitHub Releases API](https://docs.github.com/en/rest/releases/releases)
- [Semantic Versioning](https://semver.org/)

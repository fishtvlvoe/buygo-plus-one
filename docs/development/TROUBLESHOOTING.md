# BuyGo+1 問題排查清單

> 更新日期：2026-01-23

---

## 常見問題與解決方案

### 1. 網站出現 502 Bad Gateway (Cloudflare)

**症狀**：
- 瀏覽器顯示 Cloudflare 502 Bad Gateway 錯誤頁面
- 錯誤訊息：「The web server reported a bad gateway error」

**可能原因**：
1. Cloudflare Tunnel 斷線
2. Local by Flywheel 網站未啟動
3. PHP-FPM 或 Web Server 當掉

**排查步驟**：

```bash
# 1. 檢查 cloudflared 是否運行
ps aux | grep cloudflared

# 2. 檢查 tunnel 狀態
cloudflared tunnel list

# 3. 如果沒有運行，重啟 tunnel
sudo pkill cloudflared
sudo cloudflared tunnel run buygo-test

# 4. 檢查本地伺服器是否正常（Local by Flywheel 預設 port）
curl -I http://localhost:10004/
```

**快速解決**：
1. 在 Local by Flywheel 中 Stop 再 Start 網站
2. 重啟 Cloudflare Tunnel

---

### 2. CORS 錯誤（跨域資源被封鎖）

**症狀**：
- Console 出現 `Access to script at 'http://test.buygo.me/...' from origin 'http://buygo.local' has been blocked by CORS policy`
- 頁面部分功能失效

**可能原因**：
1. WordPress `siteurl` / `home` 設定與訪問的域名不一致
2. 資料庫從線上環境複製到本地，但未更新網址設定

**排查步驟**：

```bash
# 使用 WP-CLI 檢查設定
wp option get siteurl
wp option get home
```

**解決方案**：

方案 A - 修改 `wp-config.php`（推薦用於本地開發）：
```php
define('WP_HOME', 'http://buygo.local');
define('WP_SITEURL', 'http://buygo.local');
```

方案 B - 使用 WP-CLI 更新資料庫：
```bash
wp option update siteurl 'http://buygo.local'
wp option update home 'http://buygo.local'
```

---

### 3. 修改共用元件後網站壞掉

**症狀**：
- 修改 `components/shared/` 下的檔案後，網站出錯
- JavaScript 錯誤或 PHP Fatal Error
- FluentCart 或其他外掛功能異常

**可能原因**：
- 共用元件被 `template.php` 引用，改變結構會影響整個網站
- Vue 元件名稱或 props 變更導致註冊失敗

**重要檔案對照**：

| 檔案 | 被引用於 | 注意事項 |
|------|---------|---------|
| `page-header.php` | `template.php:64` | 不要改變 `$page_header_component` 變數名和 `PageHeader` 元件名 |
| `pagination.php` | `template.php:67` | 不要改變 `BuyGoPagination` 元件名和 props |
| `smart-search-box.php` | 多個頁面 | 保持 API 相容性 |

**安全修改策略**：
1. **新建檔案**：新功能用新檔案（如 `loading-state.php`、`data-table.php`）
2. **保持 API 不變**：現有元件只做增量修改，不改變變數名、元件名、props
3. **只改樣式 class**：CSS 類別可以安全修改

**緊急還原**：
```bash
# 查看最近修改
git status
git diff

# 還原所有修改
git checkout -- .

# 或還原到特定 commit
git reset --hard HEAD~1
```

---

### 4. API 返回 401 Unauthorized

**症狀**：
- 前端 API 呼叫返回 401 錯誤
- 已登入但仍無法存取 API

**可能原因**：
- 缺少 `X-WP-Nonce` header
- Nonce 過期

**解決方案**：

確保所有 fetch 請求都包含 nonce：
```javascript
fetch('/wp-json/buygo-plus-one/v1/products', {
    headers: {
        'X-WP-Nonce': wpNonce  // 從 PHP 傳入
    }
})
```

PHP 端定義 nonce：
```php
$wpNonce = wp_create_nonce('wp_rest');
```

---

### 5. LINE Webhook 無反應

**詳細說明**：請參閱 [ARCHITECTURE.md](ARCHITECTURE.md)

**快速檢查**：
1. Channel Secret 是否正確設定
2. HTTP Header 大小寫：使用 `x-line-signature`（小寫）
3. 檢查 Debug Log：`/wp-content/debug.log`

---

## Git 快速指令

```bash
# 查看當前狀態
git status

# 查看所有分支
git branch -a

# 查看最近 commit
git log --oneline -10

# 還原單一檔案
git checkout -- <file>

# 還原所有修改
git checkout -- .

# 回到上一個 commit（會丟失當前修改）
git reset --hard HEAD~1

# 暫存當前修改
git stash
git stash pop  # 恢復
```

---

## 開發環境資訊

- **本地網址**: `http://buygo.local`
- **測試網址**: `https://test.buygo.me` (透過 Cloudflare Tunnel)
- **Tunnel 名稱**: `buygo-test`
- **開發工具**: Local by Flywheel

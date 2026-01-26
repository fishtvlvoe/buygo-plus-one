# BuyGo+1 Debug Skill

當用戶報告 Bug 或問題時，**自動執行以下診斷流程**，不要等用戶提供截圖或複製貼上資訊。

## 快速診斷命令

### 1. 查詢 Webhook 日誌（最常用）

```bash
cd "/Users/fishtv/Local Sites/buygo" && ./db-query.sh "SELECT id, event_type, LEFT(event_data, 200) as data, created_at FROM wp_buygo_webhook_logs ORDER BY id DESC LIMIT 20"
```

### 2. 查詢特定錯誤

```bash
cd "/Users/fishtv/Local Sites/buygo" && ./db-query.sh "SELECT event_type, event_data, created_at FROM wp_buygo_webhook_logs WHERE event_type = 'error' ORDER BY id DESC LIMIT 10"
```

### 3. 查詢 LINE 設定

```bash
cd "/Users/fishtv/Local Sites/buygo" && ./db-query.sh "SELECT option_name, LEFT(option_value, 50) as value_preview, LENGTH(option_value) as length FROM wp_options WHERE option_name LIKE 'buygo_line%'"
```

### 4. 查詢 WordPress debug.log

```bash
tail -50 "/Users/fishtv/Local Sites/buygo/app/public/wp-content/debug.log"
```

### 5. 監控即時日誌

```bash
cd "/Users/fishtv/Local Sites/buygo" && for i in 1 2 3 4 5; do ./db-query.sh "SELECT id, event_type, created_at FROM wp_buygo_webhook_logs ORDER BY id DESC LIMIT 3" 2>/dev/null | grep -v "Warning" && sleep 2; done
```

---

## 常見問題診斷流程

### 問題：LINE 上傳圖片/文字沒反應

**診斷步驟：**

1. **查詢最新 webhook 日誌**
   ```bash
   cd "/Users/fishtv/Local Sites/buygo" && ./db-query.sh "SELECT event_type, LEFT(event_data, 150), created_at FROM wp_buygo_webhook_logs ORDER BY id DESC LIMIT 15"
   ```

2. **檢查錯誤類型：**

   | 錯誤 | 原因 | 解決方案 |
   |------|------|----------|
   | `signature_verification_failed` + "Signature mismatch" | Channel Secret 讀取錯誤 | 檢查 `SettingsService::get('line_channel_secret')` |
   | `signature_verification_failed` + "Missing header" | Webhook URL 設定錯誤 | 檢查 LINE Developers Console |
   | `error` + "401" + "Authentication failed" | Channel Access Token 錯誤 | 檢查 `SettingsService::get('line_channel_access_token')` |
   | `permission_denied` | 用戶沒有上傳權限 | 檢查 `wp_buygo_helpers` 資料表或角色 |
   | `error` + "User not bound" | LINE UID 未綁定 | 用戶需要先完成 LINE Login 綁定 |

3. **如果是 Token/Secret 問題，檢查數據來源：**
   ```bash
   # 檢查新外掛 options
   cd "/Users/fishtv/Local Sites/buygo" && ./db-query.sh "SELECT option_name, LEFT(option_value, 30), LENGTH(option_value) FROM wp_options WHERE option_name IN ('buygo_line_channel_access_token', 'buygo_line_channel_secret')"

   # 檢查舊外掛 core_settings
   cd "/Users/fishtv/Local Sites/buygo" && ./db-query.sh "SELECT LEFT(option_value, 200) FROM wp_options WHERE option_name = 'buygo_core_settings'"
   ```

---

### 問題：商品上架失敗

**診斷步驟：**

1. **查詢商品相關日誌**
   ```bash
   cd "/Users/fishtv/Local Sites/buygo" && ./db-query.sh "SELECT event_type, event_data FROM wp_buygo_webhook_logs WHERE event_type LIKE '%product%' ORDER BY id DESC LIMIT 10"
   ```

2. **檢查 FluentCart 是否正常**
   ```bash
   cd "/Users/fishtv/Local Sites/buygo" && ./db-query.sh "SELECT ID, post_title, post_status FROM wp_posts WHERE post_type = 'fluent-products' ORDER BY ID DESC LIMIT 5"
   ```

---

### 問題：權限相關錯誤

**診斷步驟：**

1. **檢查小幫手資料表**
   ```bash
   cd "/Users/fishtv/Local Sites/buygo" && ./db-query.sh "DESCRIBE wp_buygo_helpers"
   cd "/Users/fishtv/Local Sites/buygo" && ./db-query.sh "SELECT * FROM wp_buygo_helpers LIMIT 10"
   ```

2. **檢查用戶角色**
   ```bash
   cd "/Users/fishtv/Local Sites/buygo" && ./db-query.sh "SELECT u.ID, u.user_login, m.meta_value as capabilities FROM wp_users u JOIN wp_usermeta m ON u.ID = m.user_id WHERE m.meta_key = 'wp_capabilities' LIMIT 10"
   ```

---

## 關鍵檔案位置

| 檔案 | 用途 |
|------|------|
| `/includes/services/class-settings-service.php` | 設定讀取/解密邏輯 |
| `/includes/services/class-line-webhook-handler.php` | LINE Webhook 處理 |
| `/includes/api/class-line-webhook-api.php` | 簽名驗證 |
| `/includes/services/class-image-uploader.php` | 圖片上傳 |
| `/includes/services/class-fluentcart-service.php` | FluentCart 商品建立 |
| `/includes/services/class-webhook-logger.php` | 日誌記錄 |

---

## 資料庫存取

**Local by Flywheel 資料庫查詢腳本：**
```
/Users/fishtv/Local Sites/buygo/db-query.sh
```

**用法：**
```bash
./db-query.sh "你的 SQL 語句"
```

**資料表：**
- `wp_buygo_webhook_logs` - Webhook 日誌
- `wp_buygo_helpers` - 小幫手權限
- `wp_buygo_notification_logs` - 通知日誌
- `wp_options` - WordPress 設定（含 LINE 設定）

---

## 重要提醒

1. **先查日誌，再問用戶** - 大部分問題都可以從日誌找到答案
2. **Token/Secret 問題** - 檢查 `SettingsService::get()` 的優先級和解密邏輯
3. **簽名驗證問題** - 確認 Channel Secret 來源正確
4. **權限問題** - 檢查 `wp_buygo_helpers` 資料表結構

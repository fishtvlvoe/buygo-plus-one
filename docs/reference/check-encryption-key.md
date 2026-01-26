# 加密金鑰檢查與診斷

## 問題分析

根據程式碼分析，新舊外掛使用相同的加密方式：
- **加密方法**: AES-128-ECB
- **加密金鑰**: `BUYGO_ENCRYPTION_KEY` 常數（若未定義則使用 `'buygo-secret-key-default'`）

## 可能的問題原因

### 1. 加密金鑰不一致
如果 `wp-config.php` 沒有定義 `BUYGO_ENCRYPTION_KEY`：
- 舊外掛加密時可能使用了不同的金鑰（或已定義的金鑰）
- 新外掛解密時使用預設金鑰 `'buygo-secret-key-default'`
- 導致解密失敗

### 2. 資料尚未遷移
- 資料可能還在舊外掛的獨立 options 中
- 新外掛優先讀取 `buygo_core_settings`，但該資料可能不存在或未加密

## 診斷步驟

### 步驟 1: 檢查資料位置

在 WordPress 後台執行以下 SQL 查詢（工具 → Site Health → Info → Database 或使用 phpMyAdmin）：

```sql
-- 檢查舊外掛的統一設定
SELECT option_name, LENGTH(option_value) as value_length,
       SUBSTRING(option_value, 1, 50) as value_preview
FROM wp_options
WHERE option_name = 'buygo_core_settings';

-- 檢查舊外掛的獨立 options
SELECT option_name, LENGTH(option_value) as value_length,
       SUBSTRING(option_value, 1, 50) as value_preview
FROM wp_options
WHERE option_name IN (
    'mygo_line_channel_access_token',
    'mygo_line_channel_secret',
    'mygo_liff_id'
);

-- 檢查新外掛的 options
SELECT option_name, LENGTH(option_value) as value_length,
       SUBSTRING(option_value, 1, 50) as value_preview
FROM wp_options
WHERE option_name IN (
    'buygo_line_channel_access_token',
    'buygo_line_channel_secret',
    'buygo_line_liff_id'
);
```

### 步驟 2: 檢查 debug.log

1. 確保 WordPress debug 已啟用（在 `wp-config.php`）：
   ```php
   define('WP_DEBUG', true);
   define('WP_DEBUG_LOG', true);
   define('WP_DEBUG_DISPLAY', false);
   ```

2. 清空舊的 log：
   ```bash
   echo "" > "/Users/fishtv/Local Sites/buygo/app/public/wp-content/debug.log"
   ```

3. 訪問後台設定頁面或執行 LINE 相關操作

4. 查看 log：
   ```bash
   tail -f "/Users/fishtv/Local Sites/buygo/app/public/wp-content/debug.log"
   ```

預期會看到類似以下的 debug 訊息：
```
[SettingsService] Get from buygo_core_settings - Key: line_channel_access_token
[SettingsService] Raw value: U1234567890abcdef...
[SettingsService] Value length: 162
[SettingsService] Attempting to decrypt line_channel_access_token
[SettingsService] Decrypt - Input length: 162
[SettingsService] Decrypt - Cipher: AES-128-ECB
[SettingsService] Decrypt - Key exists: YES
[SettingsService] Decrypt - Result: SUCCESS (length: 172)
[SettingsService] Decryption result: SUCCESS (length: 172)
[SettingsService] Using decrypted value
```

## 解決方案

### 方案 A: 在後台重新保存設定（最簡單）

1. 登入 WordPress 後台
2. 進入 BuyGo 設定頁面
3. 重新輸入（或確認）LINE Channel Access Token、Channel Secret 等設定
4. 點擊「儲存」
5. 測試 LINE 連線

這樣會使用新外掛的加密方式重新加密並儲存資料。

### 方案 B: 手動檢查並修正加密金鑰

如果 `wp-config.php` 中沒有 `BUYGO_ENCRYPTION_KEY`，加入：

```php
// 在 wp-config.php 的適當位置加入
define('BUYGO_ENCRYPTION_KEY', 'buygo-secret-key-default');
```

**注意**: 如果舊外掛使用了不同的金鑰，需要使用相同的金鑰才能解密舊資料。

### 方案 C: 建立資料遷移腳本

如果資料在多個位置或格式不一致，可以建立一個遷移腳本來統一處理。

## 下一步

請先執行**步驟 1** 和 **步驟 2**，確認：
1. 資料存在哪裡
2. 解密是成功還是失敗
3. 使用的金鑰是否正確

根據結果再決定使用哪個解決方案。

# BuyGo+1 架構文件

> **重要提醒**：未來所有 AI 開發新功能時，請先閱讀本文件，避免重複踩坑！

---

## 📋 目錄

1. [外掛架構](#外掛架構)
2. [資料庫存取規範](#資料庫存取規範)
3. [LINE API 整合規範](#line-api-整合規範)
4. [命名規範與大小寫](#命名規範與大小寫)
5. [常見錯誤與解決方案](#常見錯誤與解決方案)

---

## 外掛架構

### 雙外掛系統

BuyGo+1 系統由**兩個外掛**組成：

1. **舊外掛（BuyGo Core）**
   - 路徑：`/wp-content/plugins/buygo`
   - 負責：核心功能、設定管理、資料存儲
   - 命名空間：`BuyGo\Core`
   - 主要類別：`BuyGo_Core`

2. **新外掛（BuyGo+1）**
   - 路徑：`/wp-content/plugins/buygo-plus-one`
   - 負責：Plus One 功能擴充
   - 命名空間：`BuyGoPlus`

### 資料共享原則

⚠️ **關鍵規則**：新外掛的資料存儲在**舊外掛的資料表**中，沒有另外新增資料表。

---

## 資料庫存取規範

### 1. LINE 設定存取（Channel Secret / Access Token）

#### ✅ 正確做法

```php
// 方法 1：使用 BuyGo_Core SettingsService（推薦）
if ( class_exists( 'BuyGo_Core' ) && method_exists( 'BuyGo_Core', 'settings' ) ) {
    $channel_secret = \BuyGo_Core::settings()->get( 'line_channel_secret', '' );
    $access_token = \BuyGo_Core::settings()->get( 'line_channel_access_token', '' );
}

// 方法 2：降級方案（當 BuyGo_Core 不可用時）
$channel_secret = get_option( 'mygo_line_channel_secret', '' );
$access_token = get_option( 'mygo_line_channel_access_token', '' );
```

#### ❌ 錯誤做法

```php
// ❌ 錯誤：這些 option 不存在於資料庫
$channel_secret = get_option( 'buygo_line_channel_secret', '' );
$access_token = get_option( 'buygo_plus_line_channel_access_token', '' );
```

#### 設定存儲位置

LINE 設定存儲在 **加密** 的 `buygo_core_settings` option 中：

- **Option 名稱**：`buygo_core_settings`
- **資料類型**：陣列（加密存儲）
- **加密演算法**：AES-128-ECB
- **解密**：自動由 `BuyGo_Core::settings()->get()` 處理

**設定 key 對應表**：

| 新系統 Key | 舊系統 Option Key | 用途 |
|-----------|------------------|------|
| `line_channel_secret` | `mygo_line_channel_secret` | LINE Channel Secret |
| `line_channel_access_token` | `mygo_line_channel_access_token` | LINE Channel Access Token |
| `line_liff_id` | `mygo_liff_id` | LINE LIFF ID |
| `line_login_channel_id` | `mygo_line_login_channel_id` | LINE Login Channel ID |
| `line_login_channel_secret` | `mygo_line_login_channel_secret` | LINE Login Channel Secret |

### 2. 小幫手（Helpers）權限管理

#### ✅ 正確做法

```php
global $wpdb;
$table_name = $wpdb->prefix . 'buygo_helpers';

// 檢查用戶是否為小幫手
$is_helper = $wpdb->get_var( $wpdb->prepare(
    "SELECT COUNT(*) FROM {$table_name} WHERE user_id = %d",
    $user_id
) );
```

#### 資料表結構

- **資料表名稱**：`wp_buygo_helpers`
- **欄位**：
  - `id` (INT): 主鍵
  - `user_id` (BIGINT): WordPress 用戶 ID
  - `seller_id` (BIGINT): 賣家 ID（用於多賣家過濾）
  - `created_at` (DATETIME): 建立時間

---

## LINE API 整合規範

### Webhook 簽名驗證

#### HTTP Header 規範

⚠️ **關鍵：HTTP Header 名稱必須使用小寫！**

```php
// ✅ 正確：使用小寫
$signature = $request->get_header( 'x-line-signature' );

// ❌ 錯誤：大寫會導致取不到 header
$signature = $request->get_header( 'X-Line-Signature' );
$signature = $request->get_header( 'X-LINE-SIGNATURE' );
```

**原因**：WordPress 的 `WP_REST_Request::get_header()` 方法內部會將 header 名稱轉為小寫進行比對。

#### 簽名驗證流程

```php
// 1. 取得 header 中的簽名（必須小寫）
$signature = $request->get_header( 'x-line-signature' );

// 2. 取得 Channel Secret（從舊外掛讀取）
if ( class_exists( 'BuyGo_Core' ) && method_exists( 'BuyGo_Core', 'settings' ) ) {
    $channel_secret = \BuyGo_Core::settings()->get( 'line_channel_secret', '' );
} else {
    $channel_secret = get_option( 'mygo_line_channel_secret', '' );
}

// 3. 計算簽名
$body = $request->get_body();
$hash = hash_hmac( 'sha256', $body, $channel_secret, true );
$computed_sig = base64_encode( $hash );

// 4. 比對簽名（使用安全比對函式）
$is_valid = hash_equals( $signature, $computed_sig );
```

#### REST API 權限設定

```php
register_rest_route(
    'buygo-plus-one/v1',
    '/line/webhook',
    array(
        'methods'             => 'POST',
        'callback'            => array( $this, 'handle_webhook' ),
        'permission_callback' => '__return_true', // ✅ 必須允許所有請求
    )
);
```

⚠️ **重要**：
- `permission_callback` 必須設為 `'__return_true'`
- 簽名驗證必須在 `handle_webhook()` **內部**執行
- 不可將 `verify_signature()` 作為 `permission_callback`，否則會導致 401 錯誤

#### LINE Verify Event 處理

當 LINE Developers Console 點擊「驗證」按鈕時，會發送一個特殊事件：

```php
// 檢查 replyToken 是否為 32 個 0（Verify Event）
foreach ( $data['events'] as $event ) {
    $reply_token = isset( $event['replyToken'] ) ? $event['replyToken'] : '';
    if ( '00000000000000000000000000000000' === $reply_token ) {
        // 立即返回成功，不處理此事件
        return rest_ensure_response( array( 'success' => true ) );
    }
}
```

---

## 命名規範與大小寫

### HTTP Header

| Header 名稱 | 正確寫法 | 錯誤寫法 |
|------------|---------|---------|
| x-line-signature | ✅ `'x-line-signature'` | ❌ `'X-Line-Signature'` |
| content-type | ✅ `'content-type'` | ❌ `'Content-Type'` |

### 資料庫 Option Key

| 用途 | 正確 Key | 錯誤 Key |
|------|---------|---------|
| Channel Secret | ✅ `mygo_line_channel_secret` | ❌ `buygo_line_channel_secret` |
| Access Token | ✅ `mygo_line_channel_access_token` | ❌ `buygo_line_channel_access_token` |

### 資料表名稱

| 用途 | 資料表名稱 | 前綴 |
|------|-----------|------|
| 小幫手列表 | `wp_buygo_helpers` | `$wpdb->prefix . 'buygo_helpers'` |
| Webhook 日誌 | `wp_buygo_webhook_logs` | `$wpdb->prefix . 'buygo_webhook_logs'` |

---

## 常見錯誤與解決方案

### 錯誤 1：Signature Mismatch

**症狀**：
```
signature_verification_failed
reason: Signature mismatch
```

**可能原因**：
1. ❌ 使用錯誤的 Channel Secret 來源（`buygo_line_channel_secret` vs `mygo_line_channel_secret`）
2. ❌ Header 名稱大小寫錯誤（`X-Line-Signature` vs `x-line-signature`）
3. ❌ Channel Secret 設定錯誤或未設定

**解決方案**：
```php
// 1. 確認使用正確的 Channel Secret 來源
if ( class_exists( 'BuyGo_Core' ) && method_exists( 'BuyGo_Core', 'settings' ) ) {
    $channel_secret = \BuyGo_Core::settings()->get( 'line_channel_secret', '' );
} else {
    $channel_secret = get_option( 'mygo_line_channel_secret', '' );
}

// 2. 確認 header 名稱使用小寫
$signature = $request->get_header( 'x-line-signature' );
```

### 錯誤 2：401 Unauthorized

**症狀**：
LINE Developers Console 顯示 "401 Unauthorized"

**可能原因**：
1. ❌ 將 `verify_signature()` 設為 `permission_callback`
2. ❌ `permission_callback` 沒有設為 `'__return_true'`

**解決方案**：
```php
// ✅ 正確：permission_callback 允許所有請求
register_rest_route(
    'buygo-plus-one/v1',
    '/line/webhook',
    array(
        'methods'             => 'POST',
        'callback'            => array( $this, 'handle_webhook' ),
        'permission_callback' => '__return_true',
    )
);

// ✅ 正確：在 handle_webhook() 內部驗證簽名
public function handle_webhook( $request ) {
    if ( ! $this->verify_signature( $request ) ) {
        return new \WP_Error( 'invalid_signature', 'Invalid signature', array( 'status' => 401 ) );
    }
    // ... 處理事件
}
```

### 錯誤 3：找不到 Channel Secret

**症狀**：
```
signature_verification_skipped
reason: Channel secret not configured
```

**可能原因**：
1. ❌ Channel Secret 未在舊外掛後台設定
2. ❌ 使用錯誤的 option key 讀取

**解決方案**：
1. 檢查舊外掛後台是否已設定 Channel Secret
2. 確認使用 `BuyGo_Core::settings()->get('line_channel_secret')` 讀取

---

## Debug 工具

### Webhook 日誌查詢

路徑：`/wp-admin/admin.php?page=buygo-settings&tab=workflow`

可查看：
- `webhook_request_received` - Webhook 請求收到
- `signature_verification_success` - 簽名驗證成功
- `signature_verification_failed` - 簽名驗證失敗
- `line_verify_event_detected` - LINE Verify Event 偵測
- `permission_denied` - 權限被拒絕

### WP-CLI 查詢資料庫

```bash
# 查詢 Channel Secret 相關設定
cd "/Users/fishtv/Local Sites/buygo/app/public"
wp option get buygo_core_settings

# 查詢舊系統 option
wp option get mygo_line_channel_secret

# 查詢小幫手列表
wp db query "SELECT * FROM wp_buygo_helpers"
```

---

## 修改歷史

| 日期 | 修改內容 | Commit |
|------|---------|--------|
| 2026-01-22 | 修復 Channel Secret 讀取邏輯 | `3ef405e` |
| 2026-01-22 | 修正 HTTP Header 大小寫 | `3ef405e` |
| 2026-01-22 | 修復 401 權限問題 | `7a6577d` |
| 2026-01-22 | 增強簽名驗證日誌 | `cff61df` |
| 2026-01-22 | 修復權限檢查 Bug | `fce684e` |

---

**建立日期**：2026-01-23
**最後更新**：2026-01-23
**維護者**：BuyGo Development Team

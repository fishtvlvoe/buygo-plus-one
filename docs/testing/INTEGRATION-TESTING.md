# BuyGo Plus One - 整合測試指南

## 概述

本文件說明如何測試 `buygo-plus-one-dev` 與 `buygo-line-notify` 外掛的整合功能。

## 前置條件

### 1. 外掛安裝

確保以下外掛都已安裝並啟用：

- ✅ `buygo-line-notify` - LINE 基礎設施外掛（必須先啟用）
- ✅ `buygo-plus-one-dev` - BuyGo 業務邏輯外掛

### 2. 外掛依賴檢查

`buygo-plus-one-dev` 依賴 `buygo-line-notify` 才能正常運作 LINE 相關功能。如果 `buygo-line-notify` 未啟用，系統會顯示管理員通知。

### 3. LINE 設定

在 `buygo-line-notify` 外掛中完成以下設定：

- LINE Channel Access Token
- LINE Channel Secret
- Webhook URL（設定為：`https://yourdomain.com/wp-json/buygo-plus-one/v1/webhook`）

## 測試項目

### 1. 圖片上傳測試

**測試目標**：驗證透過 LINE 上傳圖片後，圖片能正確下載並上傳到 WordPress。

**測試步驟**：

1. 在 LINE 中傳送一張圖片給 Bot
2. 檢查 WordPress 媒體庫是否有新上傳的圖片
3. 檢查圖片是否正確下載並儲存
4. 驗證圖片 URL 是否可正常存取

**預期結果**：

- ✅ 圖片成功下載到 WordPress
- ✅ 圖片出現在媒體庫中
- ✅ 圖片 URL 可正常存取
- ✅ 日誌記錄顯示上傳成功

**測試程式碼範例**：

```php
// 在測試環境中，可以模擬 LINE Webhook 請求
$webhook_data = [
    'events' => [
        [
            'type' => 'message',
            'message' => [
                'type' => 'image',
                'id' => 'test_image_id'
            ],
            'source' => [
                'userId' => 'test_user_id',
                'type' => 'user'
            ]
        ]
    ]
];

// 發送 Webhook 請求到測試端點
$response = wp_remote_post(
    'http://localhost/wp-json/buygo-plus-one/v1/webhook',
    [
        'body' => json_encode($webhook_data),
        'headers' => [
            'Content-Type' => 'application/json',
            'X-Line-Signature' => 'test_signature'
        ]
    ]
);
```

### 2. 訊息發送測試（Reply）

**測試目標**：驗證 Webhook Handler 能正確使用 `buygo-line-notify` 的 Facade 發送回覆訊息。

**測試步驟**：

1. 在 LINE 中傳送文字訊息給 Bot
2. 檢查 Bot 是否正確回覆
3. 驗證回覆訊息內容是否正確
4. 檢查日誌記錄

**預期結果**：

- ✅ Bot 正確回覆訊息
- ✅ 回覆內容符合預期
- ✅ 使用 `BuygoLineNotify::messaging()->send_reply()` 發送
- ✅ 日誌記錄顯示發送成功

**測試場景**：

- 一般文字訊息回覆
- 關鍵字觸發回覆（如 `/help`、`/one`、`/many`）
- 錯誤訊息回覆

### 3. 訊息發送測試（Push）

**測試目標**：驗證訂單通知能正確使用 `buygo-line-notify` 的 Facade 推播訊息。

**測試步驟**：

1. 在 FluentCart 中建立測試訂單
2. 檢查 LINE 是否收到訂單通知
3. 驗證通知內容是否正確
4. 檢查重試機制是否正常運作

**預期結果**：

- ✅ 訂單建立後收到 LINE 通知
- ✅ 通知內容包含正確的訂單資訊
- ✅ 使用 `BuygoLineNotify::messaging()->push_message()` 發送
- ✅ 重試機制正常運作（失敗時會重試）

**測試場景**：

- 訂單建立通知
- 出貨通知
- 通知失敗重試（模擬失敗情況）

### 4. Webhook 處理測試

**測試目標**：驗證 LINE Webhook 事件能正確處理。

**測試步驟**：

1. 發送各種 LINE Webhook 事件（圖片、文字、follow/unfollow）
2. 檢查事件是否正確處理
3. 驗證業務邏輯是否正常執行
4. 檢查錯誤處理

**預期結果**：

- ✅ 圖片訊息正確處理
- ✅ 文字訊息正確處理
- ✅ Follow/Unfollow 事件正確處理
- ✅ 錯誤情況有適當的錯誤處理

**測試場景**：

- 圖片訊息處理
- 文字訊息處理（含關鍵字）
- Follow/Unfollow 事件
- 無效訊息處理
- 權限檢查

### 5. 訂單通知測試

**測試目標**：驗證訂單通知系統能正確運作。

**測試步驟**：

1. 建立測試訂單
2. 觸發訂單建立事件
3. 檢查 LINE 通知是否發送
4. 測試重試機制
5. 測試去重邏輯

**預期結果**：

- ✅ 訂單建立後發送通知
- ✅ 通知內容正確
- ✅ 重試機制正常（1/2/5 分鐘）
- ✅ 同一事件同一訂單只發送一次（去重）

**測試場景**：

- 訂單建立通知
- 出貨通知
- 通知失敗重試
- 去重邏輯（同一事件不重複發送）

### 6. 外掛依賴檢查測試

**測試目標**：驗證當 `buygo-line-notify` 未啟用時，系統能正確處理。

**測試步驟**：

1. 停用 `buygo-line-notify` 外掛
2. 嘗試使用 LINE 相關功能
3. 檢查是否顯示錯誤訊息
4. 檢查功能是否優雅降級

**預期結果**：

- ✅ 顯示明確的錯誤訊息（管理員通知）
- ✅ 功能優雅降級（不崩潰）
- ✅ 日誌記錄錯誤

## 測試環境設定

### 本地測試環境

1. **Local by Flywheel** 設定
   - 確保 WordPress 環境正常運作
   - 確保 MySQL 資料庫正常運作

2. **外掛安裝**
   ```bash
   # 確保兩個外掛都在 wp-content/plugins/ 目錄下
   wp-content/plugins/
   ├── buygo-line-notify/
   └── buygo-plus-one-dev/
   ```

3. **啟用外掛**
   - 在 WordPress 後台啟用 `buygo-line-notify`
   - 在 WordPress 後台啟用 `buygo-plus-one-dev`

### 測試工具

1. **LINE Developers Console**
   - 建立測試 Bot
   - 取得 Channel Access Token 和 Channel Secret
   - 設定 Webhook URL

2. **ngrok**（本地測試用）
   ```bash
   ngrok http 80
   # 使用 ngrok 提供的 HTTPS URL 設定 LINE Webhook
   ```

3. **Postman** 或 **curl**
   - 用於發送測試 Webhook 請求

## 測試檢查清單

### 功能測試

- [ ] 圖片上傳功能正常
- [ ] 訊息回覆（Reply）功能正常
- [ ] 訊息推播（Push）功能正常
- [ ] Webhook 處理正常
- [ ] 訂單通知正常
- [ ] 重試機制正常
- [ ] 去重邏輯正常

### 整合測試

- [ ] `buygo-line-notify` 未啟用時顯示錯誤訊息
- [ ] Facade API 正常運作
- [ ] 錯誤處理正常
- [ ] 日誌記錄正常

### 效能測試

- [ ] 圖片上傳速度可接受
- [ ] 訊息發送延遲可接受
- [ ] Webhook 處理速度可接受

## 除錯技巧

### 1. 檢查日誌

```php
// buygo-line-notify 的日誌
$logger = \BuygoLineNotify\BuygoLineNotify::logger();
$logs = $logger->get_recent_logs(10);

// buygo-plus-one-dev 的日誌
// 檢查 WordPress debug.log
```

### 2. 檢查外掛狀態

```php
// 檢查 buygo-line-notify 是否啟用
if ( class_exists( '\BuygoLineNotify\BuygoLineNotify' ) ) {
    $is_active = \BuygoLineNotify\BuygoLineNotify::is_active();
    var_dump($is_active);
}
```

### 3. 檢查 Facade API

```php
// 測試 Facade API
$image_uploader = \BuygoLineNotify\BuygoLineNotify::image_uploader();
$messaging = \BuygoLineNotify\BuygoLineNotify::messaging();
$settings = \BuygoLineNotify\BuygoLineNotify::settings();
$logger = \BuygoLineNotify\BuygoLineNotify::logger();
```

## 常見問題

### Q: 圖片上傳失敗？

**A**: 檢查：
1. `buygo-line-notify` 是否啟用
2. LINE Channel Access Token 是否正確
3. WordPress 上傳目錄權限
4. 日誌記錄中的錯誤訊息

### Q: 訊息發送失敗？

**A**: 檢查：
1. LINE Channel Access Token 是否有效
2. 使用者是否已加 Bot 為好友
3. 訊息格式是否正確
4. 日誌記錄中的錯誤訊息

### Q: Webhook 無法接收？

**A**: 檢查：
1. Webhook URL 是否正確設定
2. LINE Webhook 是否啟用
3. 伺服器是否能接收 HTTPS 請求
4. 防火牆設定

### Q: 訂單通知未發送？

**A**: 檢查：
1. FluentCart 訂單事件是否正常觸發
2. 訂單是否有綁定 LINE 使用者
3. 重試機制是否正常運作
4. 日誌記錄中的錯誤訊息

## 參考資源

- [BuyGo Plus One 測試指南](./TESTING.md)
- [BuyGo Line Notify 文件](../../buygo-line-notify/README.md)
- [LINE Messaging API 文件](https://developers.line.biz/en/docs/messaging-api/)

# BuyGo+1 - Claude Code 專案指南

> 這是 Claude Code 自動讀取的專案說明檔，包含重要的開發規範和 Debug 流程。

## 專案概述

BuyGo+1 是一個 WordPress 外掛，用於從 LINE 上架商品到 FluentCart。

**技術架構文件：** 詳見 `/ARCHITECTURE.md`

---

## Debug 流程（重要！）

當用戶報告 Bug 時，**不要等用戶提供截圖**，直接執行以下診斷：

### 快速診斷命令

```bash
# 1. 查詢最新 Webhook 日誌
cd "/Users/fishtv/Local Sites/buygo" && ./db-query.sh "SELECT id, event_type, LEFT(event_data, 150), created_at FROM wp_buygo_webhook_logs ORDER BY id DESC LIMIT 15"

# 2. 查詢錯誤日誌
cd "/Users/fishtv/Local Sites/buygo" && ./db-query.sh "SELECT event_type, event_data FROM wp_buygo_webhook_logs WHERE event_type = 'error' ORDER BY id DESC LIMIT 10"

# 3. 查詢 LINE 設定
cd "/Users/fishtv/Local Sites/buygo" && ./db-query.sh "SELECT option_name, LEFT(option_value, 30), LENGTH(option_value) FROM wp_options WHERE option_name LIKE 'buygo_line%'"

# 4. 監控即時日誌（請用戶操作後執行）
cd "/Users/fishtv/Local Sites/buygo" && for i in 1 2 3 4 5; do ./db-query.sh "SELECT id, event_type FROM wp_buygo_webhook_logs ORDER BY id DESC LIMIT 3" 2>/dev/null | grep -v "Warning" && sleep 2; done
```

### 常見錯誤對照表

| 日誌事件 | 錯誤原因 | 解決方案 |
|----------|----------|----------|
| `signature_verification_failed` + "Signature mismatch" | Channel Secret 讀取錯誤 | 檢查 `SettingsService::get('line_channel_secret')` 解密邏輯 |
| `error` + "401" + "Authentication failed" | Token 無效 | 檢查 `SettingsService::get('line_channel_access_token')` |
| `permission_denied` | 權限不足 | 檢查 `wp_buygo_helpers` 資料表 |
| `error` + "User not bound" | LINE 未綁定 | 用戶需完成 LINE Login |

**完整 Debug 指南：** 詳見 `/.claude/commands/debug-buygo.md`

---

## 開發規範

### 資料讀取優先級

`SettingsService::get()` 讀取順序：
1. **優先**：新外掛 options (`buygo_line_*`) - 需解密
2. **Fallback**：舊外掛 `buygo_core_settings` - 需解密

### 加密欄位

以下欄位需要解密：
- `line_channel_access_token`
- `line_channel_secret`

### Git 工作流程

- 大型功能請開新分支
- Commit 訊息要清楚描述改動
- 測試通過後才合併

---

## 檔案結構

```
/includes/
  /services/
    class-settings-service.php    # 設定讀取/解密
    class-line-webhook-handler.php # LINE 訊息處理
    class-image-uploader.php      # 圖片上傳
    class-fluentcart-service.php  # 商品建立
  /api/
    class-line-webhook-api.php    # 簽名驗證
  /views/pages/
    settings.php                  # 後台設定頁面
```

---

## Local 開發環境

- **站點路徑：** `/Users/fishtv/Local Sites/buygo/`
- **外掛開發路徑：** `/Users/fishtv/Development/buygo-plus-one/`
- **資料庫查詢：** `./db-query.sh "SQL語句"`
- **部署命令：** `rsync -av --delete "/Users/fishtv/Development/buygo-plus-one/" "/Users/fishtv/Local Sites/buygo/app/public/wp-content/plugins/buygo-plus-one/" --exclude='.git' --exclude='node_modules'`

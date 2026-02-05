# BuyGo Plus One 自動更新系統部署指南

## 系統架構

```
┌─────────────────────┐
│  GitHub Actions     │ 當推送 tag 時自動觸發
│  自動打包 & 發布     │
└──────────┬──────────┘
           │ webhook
           ↓
┌─────────────────────┐
│ Cloudflare Workers  │ 儲存版本資訊到 KV
│  更新 API           │
└──────────┬──────────┘
           │ HTTP API
           ↓
┌─────────────────────┐
│  WordPress 外掛     │ 每 12 小時檢查更新
│  自動更新檢測       │
└─────────────────────┘
```

## 部署步驟

### 第一階段：部署 Cloudflare Workers

#### 1. 安裝 Wrangler CLI

```bash
npm install -g wrangler@latest

# 登入 Cloudflare
wrangler login
```

#### 2. 建立 KV Namespace

```bash
cd /Users/fishtv/Development/buygo-plugin-updater

# 建立 production KV
wrangler kv:namespace create "PLUGIN_VERSIONS"
# 輸出範例：
# ⛅️ wrangler 3.x.x
# 🌀 Creating namespace with title "buygo-plugin-updater-PLUGIN_VERSIONS"
# ✨ Success!
# Add the following to your wrangler.toml:
# [[kv_namespaces]]
# binding = "PLUGIN_VERSIONS"
# id = "abcdef1234567890"

# 建立 staging KV（可選）
wrangler kv:namespace create "PLUGIN_VERSIONS" --env staging
```

**重要**：記下輸出的 `id`，例如 `abcdef1234567890`

#### 3. 更新 wrangler.toml

編輯 `/Users/fishtv/Development/buygo-plugin-updater/wrangler.toml`：

```toml
[[kv_namespaces]]
binding = "PLUGIN_VERSIONS"
id = "YOUR_ACTUAL_KV_ID"  # 替換為步驟 2 取得的 ID

[env.production]
# ...
[[env.production.kv_namespaces]]
binding = "PLUGIN_VERSIONS"
id = "YOUR_ACTUAL_KV_ID"  # 同上
```

#### 4. 設定 UPDATE_TOKEN Secret

```bash
# 生成強密碼
openssl rand -base64 32

# 設定到 Cloudflare Workers
wrangler secret put UPDATE_TOKEN
# 貼上上面生成的密碼

# 如果有 staging 環境
wrangler secret put UPDATE_TOKEN --env staging
```

**重要**：記住這個密碼，稍後需要設定到 GitHub Secrets

#### 5. 部署 Worker

```bash
cd /Users/fishtv/Development/buygo-plugin-updater

# 安裝依賴
npm install

# 部署到 production
npm run deploy:production

# 輸出範例：
# Published buygo-plugin-updater (1.23 sec)
#   https://buygo-plugin-updater.your-subdomain.workers.dev
```

記下部署後的 Worker URL，例如：
```
https://buygo-plugin-updater.your-subdomain.workers.dev
```

#### 6. 測試 Worker

```bash
# 測試 health check
curl https://buygo-plugin-updater.your-subdomain.workers.dev/health

# 應該回傳：
# {"status":"ok","service":"buygo-plugin-updater","timestamp":"2026-02-05T..."}
```

---

### 第二階段：設定 GitHub

#### 1. 設定 GitHub Secrets

前往 GitHub 倉庫：https://github.com/fishtvlvoe/buygo-plus-one

點擊 **Settings** → **Secrets and variables** → **Actions** → **New repository secret**

加入以下 Secrets：

##### CLOUDFLARE_WORKER_WEBHOOK_URL

```
https://buygo-plugin-updater.your-subdomain.workers.dev/webhook/release
```

（替換為實際的 Worker URL）

##### CLOUDFLARE_UPDATE_TOKEN

貼上步驟 4 設定的密碼（`openssl rand -base64 32` 生成的）

##### LINE_NOTIFY_TOKEN（選用）

如果要接收 LINE 通知，設定你的 LINE Notify Token。

取得方式：
1. 前往 https://notify-bot.line.me/
2. 登入並建立 Token
3. 複製 Token 並貼到這裡

#### 2. 測試 GitHub Actions

推送一個測試 tag：

```bash
cd /Users/fishtv/Development/buygo-plus-one-dev

# 確保所有變更已提交
git add .
git commit -m "feat: 加入自動更新系統"

# 建立並推送 tag
git tag v0.2.5
git push origin v0.2.5
```

前往 GitHub Actions 查看執行狀態：
https://github.com/fishtvlvoe/buygo-plus-one/actions

---

### 第三階段：更新 WordPress 外掛

#### 1. 設定 API URL

在 `wp-config.php` 中加入（推薦）：

```php
// BuyGo Plus One 更新 API
define('BUYGO_UPDATE_API_URL', 'https://buygo-plugin-updater.your-subdomain.workers.dev');
```

或在外掛中直接修改 `class-auto-updater.php` 的預設 URL：

```php
$this->api_url = !empty($api_url)
    ? $api_url
    : 'https://buygo-plugin-updater.your-subdomain.workers.dev';  // 改為實際 URL
```

#### 2. 清除更新快取（開發用）

```
https://test.buygo.me/wp-admin/plugins.php?clear_update_cache=1
```

訪問此 URL 會清除更新快取，立即檢查新版本。

---

## 驗證完整流程

### 1. 發布新版本

```bash
cd /Users/fishtv/Development/buygo-plus-one-dev

# 1. 更新版本號
# 編輯 buygo-plus-one.php:
# Version: 0.2.6

# 2. 提交變更
git add .
git commit -m "chore: 發布 v0.2.6"

# 3. 建立 tag
git tag v0.2.6

# 4. 推送
git push origin main
git push origin v0.2.6
```

### 2. GitHub Actions 執行

前往 https://github.com/fishtvlvoe/buygo-plus-one/actions 確認：

- ✅ 建立 ZIP 檔案
- ✅ 建立 GitHub Release
- ✅ 上傳 ZIP 到 Release
- ✅ 呼叫 Cloudflare Workers webhook
- ✅ 發送 LINE 通知（如果有設定）

### 3. Cloudflare Workers 儲存版本

```bash
# 查詢最新版本
curl https://buygo-plugin-updater.your-subdomain.workers.dev/info/buygo-plus-one

# 應該看到新版本：
# {
#   "name": "BuyGo Plus One",
#   "slug": "buygo-plus-one",
#   "version": "0.2.6",
#   ...
# }
```

### 4. WordPress 檢測更新

1. 前往 https://test.buygo.me/wp-admin/plugins.php
2. 應該看到「有可用的更新」通知
3. 點擊「查看詳情」可以看到版本資訊
4. 點擊「立即更新」即可自動更新

---

## 疑難排解

### GitHub Actions 失敗

#### 錯誤：`Cloudflare Workers webhook 失敗`

**檢查**：
1. `CLOUDFLARE_WORKER_WEBHOOK_URL` 格式正確？
2. `CLOUDFLARE_UPDATE_TOKEN` 與 Worker 中的一致？

**測試 webhook**：

```bash
curl -X POST "https://buygo-plugin-updater.your-subdomain.workers.dev/webhook/release" \
  -H "Content-Type: application/json" \
  -H "X-Update-Token: YOUR_TOKEN" \
  -d '{
    "plugin": "buygo-plus-one",
    "version": "0.2.5",
    "download_url": "https://github.com/fishtvlvoe/buygo-plus-one/releases/download/v0.2.5/buygo-plus-one-0.2.5.zip",
    "sha256": "abc123",
    "size": "512K"
  }'

# 應該回傳：
# {"success":true,"plugin":"buygo-plus-one","version":"0.2.5","message":"Version updated successfully"}
```

### WordPress 不顯示更新

#### 1. 清除快取

```
https://test.buygo.me/wp-admin/plugins.php?clear_update_cache=1
```

#### 2. 檢查 WordPress debug.log

查看 `/Users/fishtv/Local Sites/buygo/app/public/wp-content/debug.log`：

```bash
tail -f "/Users/fishtv/Local Sites/buygo/app/public/wp-content/debug.log"
```

應該看到：

```
BuyGo Plus One 發現新版本: 0.2.4 -> 0.2.5
```

#### 3. 手動測試 API

```bash
# 測試更新檢查
curl "https://buygo-plugin-updater.your-subdomain.workers.dev/update/buygo-plus-one?version=0.2.4"

# 應該回傳更新資訊
```

### Cloudflare Workers 錯誤

#### 查看即時日誌

```bash
cd /Users/fishtv/Development/buygo-plugin-updater
wrangler tail
```

#### 檢查 KV 儲存

```bash
# 列出所有 keys
wrangler kv:key list --binding PLUGIN_VERSIONS

# 讀取特定 key
wrangler kv:key get "buygo-plus-one" --binding PLUGIN_VERSIONS
```

---

## 維護

### 手動更新版本資訊

如果需要手動更新 KV 中的版本資訊：

```bash
cd /Users/fishtv/Development/buygo-plugin-updater

# 寫入版本資訊
wrangler kv:key put "buygo-plus-one" \
  '{"version":"0.2.5","download_url":"https://...","last_updated":"2026-02-05T12:00:00Z"}' \
  --binding PLUGIN_VERSIONS
```

### 刪除舊版本歷史

```bash
# 列出所有歷史版本
wrangler kv:key list --binding PLUGIN_VERSIONS --prefix "buygo-plus-one:history:"

# 刪除特定版本
wrangler kv:key delete "buygo-plus-one:history:0.2.3" --binding PLUGIN_VERSIONS
```

### 監控 Worker 效能

前往 Cloudflare Dashboard：
https://dash.cloudflare.com/

選擇 Workers & Pages → buygo-plugin-updater

可以看到：
- 請求數量
- 錯誤率
- CPU 使用時間
- KV 讀寫次數

---

## 成本估算

### Cloudflare Workers 免費方案

- ✅ 每天 100,000 個請求
- ✅ 每個請求 10ms CPU 時間
- ✅ KV 免費版：1 GB 儲存、每天 1,000 次寫入、100,000 次讀取

**預估使用量**（假設 100 個外掛安裝）：

- 每個外掛每 12 小時檢查 1 次 = 每天 200 次檢查
- 每次發布觸發 1 次 webhook 寫入
- KV 儲存：< 1 MB

**結論**：完全在免費額度內 ✅

### GitHub Actions

- ✅ 公開倉庫：無限制免費
- ✅ 私人倉庫：每月 2,000 分鐘免費

**預估使用量**：

- 每次發布約 2-3 分鐘
- 每月發布 10 次 = 30 分鐘

**結論**：完全在免費額度內 ✅

---

## 安全性考量

### ✅ 已實施

1. **Webhook 驗證**：使用 `X-Update-Token` header 驗證 GitHub Actions
2. **HTTPS 傳輸**：所有通訊使用 HTTPS
3. **版本驗證**：比對版本號避免降級攻擊
4. **SHA256 校驗**：ZIP 檔案包含 SHA256 雜湊值

### 🔒 建議加強

1. **IP 白名單**：限制 webhook 只能從 GitHub Actions IP 發出
2. **簽章驗證**：使用 HMAC 簽章驗證 webhook payload
3. **速率限制**：防止 API 濫用

---

## 下一步

### 功能擴充

- [ ] 支援 Beta 測試版本（預發布版本）
- [ ] 版本回滾功能
- [ ] 更新統計和分析
- [ ] Email 通知（更新成功/失敗）
- [ ] 自動建立 CHANGELOG

### 其他外掛

可以使用同一個 Cloudflare Worker 支援多個外掛：

1. 在 `src/index.js` 中加入新的路由
2. 使用不同的 KV key（如 `buygo-line-notify`）
3. GitHub Actions 傳遞 `plugin` 參數

---

## 總結

現在您有一個完整的自動更新系統：

1. ✅ 推送 tag → 自動打包
2. ✅ 自動建立 GitHub Release
3. ✅ 通知 Cloudflare Workers
4. ✅ WordPress 自動檢測更新
5. ✅ 一鍵更新外掛
6. ✅ LINE 通知發布狀態

**不需要手動操作任何步驟！** 🎉

只要推送 tag，其他全自動完成。

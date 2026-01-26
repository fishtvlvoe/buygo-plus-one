# BuyGo+1

完整的 WordPress 商品和訂單管理系統。

## 功能特性

- 📱 LINE 商品上架（支援圖片和文字）
- 📦 訂單管理與追蹤
- 🚚 出貨管理系統
- 🔍 強大的商品搜尋
- 📊 庫存分配管理
- ⚙️ 彈性設定系統

## 外掛依賴

**重要**：`buygo-plus-one-dev` 需要 `buygo-line-notify` 外掛才能正常運作 LINE 相關功能。

### 安裝順序

1. **先安裝並啟用 `buygo-line-notify`**
   - 這是 LINE 基礎設施外掛，提供圖片上傳、訊息發送等核心功能
   - 在 `buygo-line-notify` 中完成 LINE Channel 設定（Access Token、Channel Secret）

2. **再安裝並啟用 `buygo-plus-one-dev`**
   - 這是業務邏輯外掛，提供商品上架、訂單通知等功能
   - 依賴 `buygo-line-notify` 提供的 Facade API

### 依賴關係

```
buygo-line-notify (基礎設施層)
    ├── ImageUploader - 圖片上傳
    ├── LineMessagingService - 訊息發送
    ├── SettingsService - 設定管理
    └── Logger - 日誌服務
    └── BuygoLineNotify (Facade API)

buygo-plus-one-dev (業務邏輯層)
    ├── LineWebhookHandler - 使用 BuygoLineNotify::image_uploader()
    ├── LineOrderNotifier - 使用 BuygoLineNotify::messaging()
    └── 其他業務邏輯服務
```

如果 `buygo-line-notify` 未啟用，系統會顯示管理員通知提醒。

## 快速開始

### 安裝

1. **安裝 `buygo-line-notify`**
   - 上傳到 `/wp-content/plugins/buygo-line-notify/`
   - 在 WordPress 後台啟用
   - 完成 LINE Channel 設定

2. **安裝 `buygo-plus-one-dev`**
   - 上傳到 `/wp-content/plugins/buygo-plus-one/`
   - 在 WordPress 後台啟用
   - 訪問 `yoursite.com/buygo-portal/dashboard`

### 開發部署流程

```bash
# 1. 修改代碼
git add .
git commit -m "feat: 新增功能描述"

# 2. 推送到 GitHub
git push origin main

# 3. InstaWP 自動部署到臨時網站
# 訪問 InstaWP 臨時網站進行測試
```

## 技術棧

- **前端**：Vue 3 + Tailwind CSS + React
- **後端**：PHP 7.4+ + WordPress REST API
- **架構**：標準 WordPress 外掛架構

## 專案結構

```
buygo-plus-one/
├── buygo-plus-one.php    # 主入口
├── includes/             # PHP 核心模組
├── admin/                # 後台管理
├── components/           # 前端組件
├── public/               # 公開資源
├── assets/               # CSS/JS
└── tests/                # 測試檔案
```

## 開發階段

- **第 1 階段**：穩定期（1 月 24-28 日）
- **第 2 階段**：發布期（1 月 29-31 日）
- **第 3 階段**：重構期（2 月 8 日 - 3 月 7 日）
- **第 4 階段**：新功能開發（3 月 8 日起）

## 提交規範

```
feat:   新增功能
fix:    修復 Bug
docs:   文件更新
style:  代碼風格
refactor: 代碼重構
test:   測試相關
chore:  構建/工具
```

## 授權

GPL v2 或更新版本

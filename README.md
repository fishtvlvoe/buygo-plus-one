# BuyGo+1

完整的 WordPress 商品和訂單管理系統。

## 功能特性

- 📱 LINE 商品上架（支援圖片和文字）
- 📦 訂單管理與追蹤
- 🚚 出貨管理系統
- 🔍 強大的商品搜尋
- 📊 庫存分配管理
- ⚙️ 彈性設定系統

## 快速開始

### 安裝

#### 方式一：從 GitHub Releases 下載（推薦）

1. 訪問 [GitHub Releases](https://github.com/fishtvlvoe/buygo-plus-one/releases)
2. 下載最新版本的 ZIP 檔案
3. 在 WordPress 後台上傳並啟用
4. 確保已安裝並啟用 `buygo-line-notify` 外掛

**自動更新**：安裝後會自動檢查更新，有新版本時會在後台顯示更新通知。

#### 方式二：手動安裝

1. 上傳到 `/wp-content/plugins/buygo-plus-one/`
2. 在 WordPress 後台啟用外掛
3. 訪問 `yoursite.com/buygo-portal/dashboard`

### 發布新版本

```bash
# 1. 更新版本號和 CHANGELOG
# 編輯 buygo-plus-one.php 和 CHANGELOG.md

# 2. 提交變更
git add .
git commit -m "chore: prepare for v0.2.0 release"
git push origin main

# 3. 執行發布腳本
./release.sh

# 4. GitHub Actions 會自動建立 Release 和上傳 ZIP
```

詳細發布流程請參考 [RELEASE-GUIDE.md](RELEASE-GUIDE.md)

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

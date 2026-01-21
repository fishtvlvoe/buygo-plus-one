# BuyGo+1

BuyGo 獨立賣場後台系統

## 說明

BuyGo+1 是完全獨立的 WordPress 外掛，提供 BuyGo 賣場後台管理功能。

## 功能

- 儀表板
- 商品管理
- 訂單管理
- 出貨商品
- 出貨明細
- 客戶管理
- 系統設定

## 技術棧

- **前端**：Vue 3 + Tailwind CSS
- **後端**：PHP + WordPress REST API
- **架構**：標準 WordPress 外掛架構

## 安裝

1. 上傳 `buygo-plus-one` 資料夾到 `/wp-content/plugins/` 目錄
2. 在 WordPress 後台的「外掛」選單中啟用 BuyGo+1
3. 訪問 `yoursite.com/buygo-portal/dashboard` 開始使用

## 開發

### 檔案結構

```
buygo-plus-one/
├── buygo-plus-one.php    # 主要外掛檔案
├── includes/              # PHP 類別和函數
├── components/            # Vue 元件
└── assets/                # 前端資源
```

## 授權

GPL v2 or later

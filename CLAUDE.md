# BuyGo+1 - Claude Code 專案指南

> ⚠️ **重要**：這是 Claude Code 每次對話開始時自動讀取的專案說明檔。
>
> **請在修改任何代碼前，先閱讀「修改前檢查清單」！**

---

## 📋 快速導航

| 文件 | 用途 | 何時閱讀 |
|------|------|----------|
| [IMPLEMENTATION-CHECKLIST.md](IMPLEMENTATION-CHECKLIST.md) | **實施檢查清單（進度追蹤）** | **每次對話開始時** |
| [CODING-STANDARDS.md](CODING-STANDARDS.md) | **編碼規範和模式** | **修改任何代碼前** |
| [TODO-BUYGO.md](TODO-BUYGO.md) | 待完成任務與已完成歸檔 | 開始新任務前 |
| [ARCHITECTURE.md](ARCHITECTURE.md) | 技術架構（資料庫、API、LINE 整合） | 修改 LINE API、資料庫查詢前 |
| [BUGFIX-CHECKLIST.md](BUGFIX-CHECKLIST.md) | 已修復問題清單（防止再次踩坑） | 修改已修復功能前 |

---

## 🚨 修改前檢查清單（必讀）

### 修改任何頁面前必須確認：

- [ ] `wpNonce` 變數存在且已定義（`wp_create_nonce("wp_rest")`）
- [ ] 所有 `fetch()` 都帶有 `X-WP-Nonce` header
- [ ] CSS 類名使用頁面前綴（`products-`, `orders-`, `customers-` 等）
- [ ] JavaScript 變數使用明確命名（避免 `data`, `items`, `loading` 等通用名稱）

### 修改 LINE 相關代碼前：

- [ ] Channel Secret 使用 `\BuyGo_Core::settings()->get('line_channel_secret')`
- [ ] HTTP Header 使用小寫 `x-line-signature`（不是 `X-Line-Signature`）
- [ ] `permission_callback` 設為 `__return_true`（不是 `verify_signature`）
- [ ] 權限檢查使用 `wp_buygo_helpers` 資料表（不是 `buygo_helpers` option）

### 修改搜尋功能前：

- [ ] `smart-search-box` 的三個事件（@search, @select, @clear）都有綁定
- [ ] `handleSearch` 方法會調用 `loadData()` 或類似方法
- [ ] API 的 `search` 參數有正確傳遞

---

## ✅ 修改後驗證清單（必做）

**每次修改代碼後，必須驗證以下功能沒有壞掉：**

### 基本功能（每次都要測試）

- [ ] 所有頁面可以正常載入（無 JS 錯誤）
- [ ] 所有 API 請求返回 200（無 401/403/500）
- [ ] 搜尋框可以正常搜尋
- [ ] 分頁可以正常切換

### 特定頁面測試

| 頁面 | 必測項目 |
|------|----------|
| **商品頁** | 列表顯示、搜尋、編輯、下單名單、採購數量編輯 |
| **訂單頁** | 列表顯示、父子訂單、訂單詳情、狀態切換 |
| **LINE** | Developers Console 驗證 200、發送圖片有回應、商品能建立 |

---

## 🔧 快速 Debug 命令

```bash
# 查看最新日誌
tail -50 /Volumes/insta-mount/wp-content/buygo-plus-one.log

# 查看權限日誌
tail -50 /Volumes/insta-mount/wp-content/buygo-plus-one.log | grep PERMISSION

# 查看分配日誌
tail -50 /Volumes/insta-mount/wp-content/buygo-plus-one.log | grep ALLOCATION

# 查看資料庫升級
tail -50 /Volumes/insta-mount/wp-content/buygo-plus-one.log | grep UPGRADE
```

---

## 📁 關鍵檔案位置

```
/includes/
  /services/
    class-settings-service.php      # 設定讀取/解密（LINE Channel Secret）
    class-line-webhook-handler.php  # LINE 訊息處理（權限檢查）
    class-order-service.php         # 訂單邏輯（父子訂單、產品名稱）
    class-allocation-service.php    # 庫存分配（SQL NULL 處理）
  /api/
    class-api.php                   # 統一權限檢查
    class-line-webhook-api.php      # 簽名驗證（Header 大小寫）

/admin/partials/
    products.php    # 商品頁（wpNonce、搜尋事件）
    orders.php      # 訂單頁（wpNonce、父子訂單）
    customers.php   # 客戶頁（wpNonce）
    settings.php    # 設定頁（wpNonce）

/components/
  /shared/
    smart-search-box.php  # 搜尋組件（emit events）
  /order/
    order-detail-modal.php  # 訂單詳情（wpNonce prop）
```

---

## 💡 開發原則

1. **修改前先讀檢查清單** - 避免破壞已修復的功能
2. **修改後做驗證** - 確保沒有副作用
3. **使用命名空間** - CSS 類名和 JavaScript 變數都要有前綴
4. **小步迭代** - 每次只修改一個功能，驗證後再繼續
5. **有疑問就問** - 不確定的地方，先與用戶確認

---

## 🌐 開發環境

| 項目 | 說明 |
|------|------|
| **網域** | buygo.me（DNS A Record 指向 InstaWP） |
| **主機** | InstaWP 雲端開發環境 |
| **雲端掛載** | `/Volumes/insta-mount/`（直接連接，修改立即生效） |
| **本地開發** | `/Users/fishtv/Development/buygo-plus-one-dev/` |
| **舊外掛** | `buygo`（客戶使用中） |
| **新外掛** | `buygo-plus-one-dev`（開發中） |
| **資料庫版本** | `1.1.0`（含出貨單資料表） |

---

## 📚 延伸閱讀

需要更多細節時，請閱讀以下檔案：

- **[BUGFIX-CHECKLIST.md](BUGFIX-CHECKLIST.md)** - 5 個已修復問題的詳細說明
- **[ARCHITECTURE.md](ARCHITECTURE.md)** - 雙外掛架構、資料庫規範、常見錯誤
- **[TODO-BUYGO.md](TODO-BUYGO.md)** - 完整的任務清單與歸檔記錄

---

**最後更新**：2026-01-23
**維護者**：Development Team

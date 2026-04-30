=== BuyGo+1 ===
貢獻者：buygoteam
標籤：電商、市集、後台管理
需要 WordPress 最少版本：5.8
測試至 WordPress 版本：6.4
需要 PHP 版本：7.4
穩定版本標籤：1.7.3
授權條款：GPLv2 或更新版本
授權條款網址：http://www.gnu.org/licenses/gpl-2.0.html

BuyGo 獨立賣場後台系統

== Description ==

BuyGo+1 是完全獨立的 WordPress 外掛，提供 BuyGo 賣場後台管理功能。

**重要**：此外掛需要 `buygo-line-notify` 外掛才能正常運作 LINE 相關功能。請先安裝並啟用 `buygo-line-notify`。

== Installation ==

1. **先安裝 `buygo-line-notify` 外掛**
   - 上傳 `buygo-line-notify` 資料夾到 `/wp-content/plugins/` 目錄
   - 在 WordPress 後台的「外掛」選單中啟用 BuyGo Line Notify
   - 完成 LINE Channel 設定（Access Token、Channel Secret）

2. **再安裝 `buygo-plus-one` 外掛**
   - 上傳 `buygo-plus-one` 資料夾到 `/wp-content/plugins/` 目錄
   - 在 WordPress 後台的「外掛」選單中啟用 BuyGo+1
   - 訪問 `yoursite.com/buygo-portal/dashboard` 開始使用

== Requirements ==

* WordPress 5.8 或更新版本
* PHP 7.4 或更新版本
* buygo-line-notify 外掛（必須先安裝並啟用）

== Changelog ==

= 1.7.3 =
* 修復：多變體商品分配時，BCD 變體訂單建出的子訂單 object_id 全變成 A 變體（致命：父訂單品項查詢用 IN 取第一筆）
* 修復：一鍵分配多變體訂單時，後變體 needed 數量覆蓋前變體（allocations key 改用 per-item）
* 改善：AllocationService 介面擴充支援 per-item 格式 `[{order_id, object_id, quantity}]`，向下相容舊格式
* 新增：bin/fix-cross-variant-child-orders.php 修復腳本（dry-run + commit 兩階段，未來可重複用於其他多變體商品污染）
* 測試：新增 AllocationCrossVariantTest 6 個 regression test，全套 311 tests 通過

= 1.7.2 =
* 重構：將 AllocationService 與 ProductService 拆分為較小的 facade-backed services
* 修復：已取消或已退款的子訂單不再被計入 allocation demand
* 改善：將 API 商業邏輯持續遷移至 service layer，降低 integration 與 API 耦合

= 0.2.7 =
* 修復：LINE 通知無限循環問題（狀態洩漏導致重複發送）
* 修復：FluentCart 賣家權限授予的 SQL 欄位錯誤（product_id → post_id）
* 修復：賣家權限通知中的後台連結錯誤
* 改善：WebhookLogger 錯誤日誌記錄

= 0.0.2 =
* 初始版本
* 建立基礎外掛架構

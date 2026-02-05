=== BuyGo+1 ===
貢獻者：buygoteam
標籤：電商、市集、後台管理
需要 WordPress 最少版本：5.8
測試至 WordPress 版本：6.4
需要 PHP 版本：7.4
穩定版本標籤：0.2.8
授權條款：GPLv2 或更新版本
授權條款網址：http://www.gnu.org/licenses/gpl-2.0.html

BuyGo 獨立賣場後台系統

== Description ==

BuyGo+1 是完全獨立的 WordPress 外掛，提供 BuyGo 賣場後台管理功能。

== Installation ==

1. 上傳 `buygo-plus-one` 資料夾到 `/wp-content/plugins/` 目錄
2. 在 WordPress 後台的「外掛」選單中啟用 BuyGo+1
3. 訪問 `yoursite.com/buygo-portal/dashboard` 開始使用

== Changelog ==

= 0.2.8 =
* fix: 移除硬編碼錯誤訊息，改為靜默處理無效商品資料
* fix: 修正 /one 關鍵字無限循環問題
* feat: 新增 build-release.sh 腳本，確保 ZIP 檔案內資料夾名稱固定為 buygo-plus-one

= 0.2.7 =
* fix: 修復 LINE webhook handler 狀態洩漏問題
* fix: 修正賣家權限授予後台網址

= 0.0.2 =
* 初始版本
* 建立基礎外掛架構

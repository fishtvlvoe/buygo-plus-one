# Phase 39: FluentCart 自動賦予賣家權限 - Context

**Gathered:** 2026-02-04
**Status:** Ready for planning

<domain>
## Phase Boundary

購買指定 FluentCart 虛擬商品並完成付款後，系統自動賦予顧客 `buygo_admin` WordPress 角色和預設商品配額（3 個），並發送通知引導其開始使用賣家功能。

這個階段實作 FluentCart 購買流程與 BuyGo 權限系統的自動化整合，降低手動賦予權限的成本和錯誤率。

</domain>

<decisions>
## Implementation Decisions

### 後台設定介面
- **商品 ID 儲存方式**：單一商品 ID（使用 text input）
- **UI 放置位置**：角色權限設定頁面（與現有商品限制設定放在一起）
- **驗證規則**：完整驗證 — 輸入時查詢 FluentCart 資料庫，確認商品存在
- **設定顯示內容**：顯示商品 ID、商品名稱、商品價格，並提供預覽連結到 FluentCart 後台

### 觸發時機與條件判斷
- **監聽的 Hook**：同時監聽 `fluent_cart/order_created` 和 `fluent_cart/order_paid`，但使用去重機制確保只執行一次
- **商品類型限制**：賣家商品必須是**虛擬商品**（virtual product），不能是實體商品
- **訂單商品數量**：訂單中只能有一個產品項目（單一商品訂單），確保購買意圖明確
- **重複購買處理**：如果顧客已經有 `buygo_admin` 角色，跳過所有操作，不更新配額
- **退款處理**：監聽 FluentCart 退款 hook，當賣家商品訂單退款時，自動移除 `buygo_admin` 角色和相關 user meta

### 錯誤處理與 Log 策略
- **Hook 執行失敗**：實作重試機制（失敗時重試 3 次）
- **Debug log 記錄深度**：完整詳細 — 記錄 hook 參數、訂單資料、使用者資料、每個步驟執行狀態
- **失敗通知方式**：發送 email 給管理員，通知權限賦予失敗
- **賦予記錄儲存**：建立新資料表 `wp_buygo_seller_grants`，記錄每次權限賦予（時間、訂單 ID、使用者 ID、結果）

### 使用者通知流程
- **通知時機**：權限賦予成功後立即發送通知
- **通知管道判斷**：
  - 已綁定 LINE：發送 LINE 通知訊息
  - 未綁定 LINE：發送 Email 通知
- **通知內容**（LINE 和 Email 相同）：
  - 恭喜成為 BuyGo 賣家
  - 說明已獲得的權限和配額數量（預設 3 個商品）
  - 提供後台管理連結
  - 引導加入 LINE 官方帳號（LINE@）
  - 說明可使用 `/id` 指令查詢自己的身份
  - （Email 額外包含）LINE 綁定教學連結

### Claude's Discretion
- 重試機制的具體實作方式（延遲時間、錯誤類型判斷）
- 資料表 schema 設計細節
- Email 的 HTML 樣式設計
- LINE 通知訊息的排版和 emoji 使用
- 商品驗證的快取策略

</decisions>

<specifics>
## Specific Ideas

- **虛擬商品判斷**：FluentCart 商品必須標記為 virtual product，這是賣家商品的必要條件
- **單一商品訂單**：訂單必須只包含一個產品項目，避免混合購買的歧義
- **身份查詢功能已存在**：LINE 官方帳號已經實作 `/id` 指令，使用者可以查詢自己的身份（賣家/買家/小幫手）
- **去重機制**：使用 user meta 或資料表記錄已處理的訂單 ID，避免 `order_created` 和 `order_paid` 兩個 hook 重複執行

</specifics>

<deferred>
## Deferred Ideas

**以下功能不在 Phase 39 範圍內：**

- **多商品支援** — 目前只支援單一賣家商品 ID，未來可擴充為多個商品（Phase 40 或更後面）
- **配額累加機制** — 多次購買不累加配額，如需要調整配額由管理員手動處理
- **LINE 通知模板管理** — 通知內容硬編碼，未來可建立模板系統讓管理員自訂
- **賦予記錄查詢介面** — 資料表建立後，管理後台查詢介面留待未來開發
- **自動化測試購買流程** — 需要手動測試，自動化測試留待未來實作
- **賣家升級配額功能** — 賣家購買更多配額的自助升級功能（另一個 milestone）

</deferred>

---

*Phase: 39-fluentcart-自動賦予賣家權限*
*Context gathered: 2026-02-04*

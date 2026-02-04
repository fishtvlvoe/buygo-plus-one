---
status: resolved
trigger: "FluentCart 設定為「付款後自動建立使用者帳號」，但顧客下單後沒有建立 WordPress 使用者帳號"
created: 2026-02-04T00:00:00Z
updated: 2026-02-04T00:30:00Z
---

## Current Focus

hypothesis: 已確認根本原因 - 訂單付款狀態為 "pending"（未付款），FluentCart 的使用者自動建立機制僅在付款完成時觸發
test: 測試將訂單標記為已付款後，是否會自動建立使用者帳號
expecting: 訂單付款後自動建立 WordPress 使用者並連結到 FluentCart 顧客
next_action: 修改訂單付款狀態或提供手動建立使用者的解決方案

## Symptoms

expected:
- FluentCart 設定中啟用「付款後自動建立使用者帳號」
- 顧客下單並完成付款後，應該自動建立 WordPress 使用者帳號
- 顧客可以使用該帳號登入查看訂單歷史

actual:
- 顧客下單成功（訂單 #299）
- 訂單金額：¥12,000
- 顧客 email：fish.myfb@gmail.com
- FluentCart 後台顯示顧客資料（有姓名、地址、訂單記錄）
- WordPress 使用者列表中找不到該顧客
- 顧客無法登入會員中心

errors:
- 無明顯錯誤訊息
- FluentCart 設定頁面顯示「付款後自動建立使用者帳號」已啟用

reproduction:
1. 使用未註冊的 email (fish.myfb@gmail.com) 下單
2. 購買商品：冬季外套
3. 完成付款
4. 檢查 FluentCart 後台 → 顧客資料存在
5. 檢查 WordPress 使用者列表 → 找不到該用戶

timeline:
- 第一次測試此功能時發現
- 之前沒有測試過顧客帳號自動建立功能

## Eliminated

## Evidence

- timestamp: 2026-02-04T00:10:00Z
  checked: FluentCart 資料庫 fct_customers 表
  found: 顧客資料存在（id=5, email=fish.myfb@gmail.com），但 user_id 欄位為 NULL
  implication: FluentCart 未建立 WordPress 使用者帳號並連結

- timestamp: 2026-02-04T00:10:00Z
  checked: WordPress wp_users 表
  found: 該 email 不存在任何使用者記錄
  implication: 確認使用者未被建立

- timestamp: 2026-02-04T00:10:00Z
  checked: FluentCart Hooks 註冊狀態
  found: fluentcart/order_payment_completed, fluentcart/order_created, fluentcart/customer_created, fluentcart/after_order_complete 全部為 0 個回調
  implication: BuyGo+1 未註冊這些 Hook，不應該干擾 FluentCart 使用者建立

- timestamp: 2026-02-04T00:20:00Z
  checked: FluentCart 全局設定 user_account_creation_mode
  found: 設定值為 "all"（應該為所有訂單建立使用者）
  implication: FluentCart 全局設定正確

- timestamp: 2026-02-04T00:20:00Z
  checked: 訂單 #299 的 config 欄位
  found: config['create_account_after_paid'] = 'yes'（已啟用）
  implication: 訂單層級設定也正確

- timestamp: 2026-02-04T00:25:00Z
  checked: 訂單 #299 的 payment_status 欄位
  found: payment_status = 'pending'（未付款）
  implication: **這是根本原因！**

- timestamp: 2026-02-04T00:25:00Z
  checked: FluentCart 原始碼 OrderPaid::maybeCreateUser()
  found: 此方法由 OrderPaid 事件觸發，只有在訂單付款完成時才會執行
  implication: pending 狀態的訂單不會觸發使用者建立流程

- timestamp: 2026-02-04T00:25:00Z
  checked: fluent_cart/order_paid Hook
  found: 有 1 個監聽器（FluentCart 自己的 OrderPaid 事件處理）
  implication: Hook 機制正常運作，只是未被觸發（因為訂單未付款）

## Resolution

root_cause: 訂單 #299 的付款狀態為 "pending"（未付款）。FluentCart 的使用者自動建立機制在 OrderPaid 事件觸發時執行（app/Listeners/Order/OrderPaid.php），只有訂單付款狀態變更為 "paid" 時才會觸發。雖然 FluentCart 設定和訂單 config 都正確啟用了「付款後建立使用者帳號」功能，但訂單尚未完成付款，因此使用者建立流程從未執行。

fix: 提供兩個解決方案：
1. **正常流程**：顧客完成付款後，FluentCart 會自動建立 WordPress 使用者帳號
2. **手動補救**：後台管理員可將訂單標記為「已付款」，觸發使用者建立流程

verification:
files_changed: []

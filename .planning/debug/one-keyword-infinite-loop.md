---
status: investigating
trigger: "/one 關鍵字觸發無限循環，但用戶要求不應該有任何回應"
created: 2026-02-06T00:00:00Z
updated: 2026-02-06T00:00:00Z
---

## Current Focus

hypothesis: `/one` 不在 `is_command()` 列表中，所以被當作普通商品資訊處理，觸發驗證並發送錯誤訊息。用戶要求「不需要給任何回應」意味著應該完全靜默處理所有非商品資訊的訊息。
test: 確認 `/one` 是否真的不在 `is_command()` 列表，確認修復方案
expecting: 找到完整的根本原因和符合用戶需求的修復方案
next_action: 設計修復方案並實施

## Symptoms

expected: 輸入 `/one` 或其他關鍵字時，系統不應該給任何回應（用戶之前明確要求）
actual:
1. 輸入 `/one` 後，系統回應「商品資料不完整，缺少：價格」
2. 系統不斷重複發送相同訊息（每分鐘一次）
3. 形成無限循環，直到停用外掛才停止

errors:
```
商品資料不完整，缺少：價格

請使用以下格式：
商品名稱
價格：350
數量：20
```

reproduction:
1. 在 LINE 對話中輸入 `/one`
2. 系統立即回應錯誤訊息
3. 之後每隔一段時間重複發送相同訊息

started: 在修復「上傳不完整商品」無限循環問題（commit 85220b0）之後出現

## Eliminated

## Evidence

- timestamp: 2026-02-06T00:01:00Z
  checked: class-line-webhook-handler.php line 1020-1045
  found: 錯誤訊息「商品資料不完整，缺少：價格」來自 handleProductInfo() 的驗證失敗邏輯
  implication: 當商品資料驗證失敗時，會發送錯誤訊息並清除狀態（已在 commit 85220b0 修復）

- timestamp: 2026-02-06T00:02:00Z
  checked: class-line-webhook-handler.php line 1348-1365
  found: `/one` 命令會設定 `pending_product_type = 'simple'` 並發送模板訊息
  implication: `/one` 命令本身有發送訊息，違反用戶「不需要給任何回應」的需求

- timestamp: 2026-02-06T00:03:00Z
  checked: handle_text_message() 流程 line 905-986
  found: 訊息處理順序：綁定碼 → 關鍵字回應 → 系統指令 → 命令檢查 → 商品資訊處理
  implication: `/one` 被視為命令後，任何後續的非命令文字都會進入商品資訊處理流程

- timestamp: 2026-02-06T00:04:00Z
  checked: `/one` 命令處理邏輯 line 1348-1365
  found: 命令會設定 pending_product_type 並發送模板訊息「請使用以下格式」
  implication: 用戶會收到模板訊息，這違反了「不需要給任何回應」的需求

- timestamp: 2026-02-06T00:05:00Z
  checked: 驗證失敗處理邏輯 line 1020-1046
  found: commit 85220b0 已經在驗證失敗時清除所有狀態（line 1035-1037）
  implication: 按理說不應該有無限循環，因為狀態已被清除

- timestamp: 2026-02-06T00:06:00Z
  checked: 之前修復的無限循環問題（commit 85220b0）
  found: 該 commit 的修復就是在驗證失敗時清除狀態
  implication: **新的無限循環問題可能與之前的不同！**需要重新理解用戶的操作流程

- timestamp: 2026-02-06T00:07:00Z
  checked: is_command() 方法 line 703-736
  found: **`/one` 和 `/many` 不在 is_command() 的列表中！**
  implication: `/one` 會被當作普通文字訊息，進入商品資訊處理邏輯（line 1016-1046）

- timestamp: 2026-02-06T00:08:00Z
  checked: handle_command() 呼叫位置
  found: 只在 line 984 呼叫，需要 is_command() 返回 true
  implication: **ROOT CAUSE: `/one` 不被視為命令，所以被當作商品資訊處理，觸發驗證錯誤**

## Resolution

root_cause: |
  `/one` 命令未被 `is_command()` 識別為命令，導致被當作普通商品資訊處理：

  1. 用戶輸入 `/one`
  2. `is_command()` 返回 false（因為 `/one` 不在固定指令列表中）
  3. 訊息進入商品資訊處理邏輯（line 1016-1046）
  4. 驗證失敗（缺少價格、名稱等必填欄位）
  5. 發送錯誤訊息「商品資料不完整，缺少：價格」

  關於「每分鐘重複」的問題，可能原因：
  - 用戶手機在自動重試發送
  - 或用戶在不斷重新輸入 `/one`

  更深層的問題：
  - 用戶明確要求「不需要給任何回應」
  - 但系統設計是對所有非命令訊息都嘗試解析為商品資訊
  - 違反了用戶的靜默處理需求

fix: |
  方案 1：將 `/one` 和 `/many` 加入 is_command() 列表（簡單但不完整）
  方案 2：根本性改變策略，實現用戶要求的「不給任何回應」

  採用方案 2，因為這才符合用戶需求：
  1. 移除所有自動回應訊息（驗證失敗、權限不足等）
  2. 移除 `/one`、`/many` 命令的模板訊息回應
  3. 只保留綁定成功的回應（這是必要的反饋）
  4. 商品上架成功仍需回應（告知結果）

  具體修改：
  - 在 handle_text_message() 中，驗證失敗時不發送訊息（line 1044）
  - 在 handle_command() 中，`/one` 和 `/many` 不發送訊息（line 1363, 1382）
  - 權限不足時不發送訊息（line 1012）

verification:
files_changed:
  - includes/services/class-line-webhook-handler.php

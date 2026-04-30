## Context

BuyGo+1 的庫存分配流程 `AllocationService` 在 2026-04-27 拆檔為 4 個 sub-service（query / write / batch / calculator）。拆檔後沿用既有 SQL 邏輯，但 `createChildOrder`（write-service line 226-281）和 `allocateAllForCustomer`（batch-service line 65-135）保留了單一變體商品時代的設計假設：「同一商品、同一訂單只會有一個 variation」。

當多變體商品（has_variations=true 且實際使用多個 variation）出現「同一父訂單同時購買 ABCD 多個變體」時，這個假設破裂：

1. `createChildOrder` 找父訂單品項時 SQL 用 `object_id IN ($var_placeholders)`，`get_row` 只取第一筆 → 永遠取到 id 最小的變體（A）→ 子訂單的 object_id 寫成 A
2. `allocateAllForCustomer` 收集 needed 用 `[order_id => qty]`，多變體同訂單時後者 needed 覆蓋前者，再加上 (1) 的問題，最終所有變體的子訂單都被建成 A 變體

這不是「採購池」的業務語意問題，而是「資料寫入時的 variation 對應」實作 bug。

當前狀態（正式站 buygo.instawp.xyz post_id=2650 已驗證）：5 筆子訂單全部錯標 A 變體，A 採購 7 被假分配吃光，BCD 永遠顯示待分配，前端 UI 看似「BCD 無法分配」。

## Goals / Non-Goals

**Goals:**

- 修正 `createChildOrder` 確保子訂單 `object_id` 等於目標 variation_id，不再「永遠是 A」
- 修正 `allocateAllForCustomer` 多變體同訂單時 needed 不互相覆蓋
- 後端 `allocate_stock` API 正確處理前端傳來的 `object_id` 欄位（前端 JSON 已包含但後端目前忽略）
- 一次性資料修復：將 5 筆錯標 A 的子訂單還原為對應的真實 variation
- 修復後新一次跨變體分配能正確產生 4 個變體各自獨立的子訂單，且 UI 顯示對齊

**Non-Goals:**

- 不重構分配流程整體架構
- 不變更採購池的「跨變體共用」業務語意（採購數仍可在 ABCD 變體間共用）
- 不修 D 變體採購流程缺 `_buygo_purchased` meta 的根因（採購流程的 bug，本 change 只一次性補值）
- 不變更前端 Vue 元件邏輯（後端正確後前端自然顯示對）
- 不處理已 cancelled / refunded 的子訂單（這些不會被誤計）

## Decisions

### Decision 1: `createChildOrder` 加 `$variation_id` 參數，SQL 改精確過濾

**做法**：

- `createChildOrder($product_id, $parent_order_id, $quantity)` 改為 `createChildOrder($parent_order_id, $variation_id, $quantity)`
- 內部 SQL 從 `WHERE order_id = %d AND object_id IN ($var_placeholders)` 改為 `WHERE order_id = %d AND object_id = %d`
- 移除 `$product_id` 參數和 `getAllVariationIds` 呼叫（caller 已知 variation_id）

**理由**：

- 子訂單必須對應一個明確的 variation，不應在 callee 用「IN + 取第一筆」做隱式選擇
- caller 端（`updateOrderAllocations`）已從 items query 拿到每筆 `$item['object_id']`，傳給 callee 是最自然的設計

**替代方案**：

- 方案 B：`createChildOrder` 內保留 `IN`，加 `LIMIT 1 ORDER BY` + 篩條件 → 不採用（仍是隱式選擇，無法表達「就是這個變體」的語意）
- 方案 C：在 SQL 同時傳 `object_id` 和 `IN`，作為驗證 → 不採用（多餘檢核，不是業務需要）

### Decision 2: `allocateAllForCustomer` 改用 `order_item_id` 為 allocations key

**做法**：

- 內部資料結構從 `[order_id => needed]` 改為 `[order_item_id => ['order_id' => X, 'object_id' => Y, 'quantity' => Z]]`
- 呼叫 `updateOrderAllocations` 時，把 array 攤平為新的介面格式（見 Decision 3）

**理由**：

- `order_item_id` 是同一訂單不同變體的唯一識別（一個訂單對 ABCD 各有一筆 order_item）
- 用 order_id 為 key 在多變體情境下會 silently 覆蓋，是 Decision 2 的源頭

**替代方案**：

- 方案 B：用複合鍵 `"$order_id:$object_id"` → 不採用（字串 key 容易在 PHP 隱式轉型造成 bug）

### Decision 3: `updateOrderAllocations` 介面擴充支援 per-item 格式

**做法**：

新介面接受兩種格式（向下相容）：

```php
// 舊格式（單變體商品仍可用）
[order_id => quantity]

// 新格式（多變體必須用此）
[
  ['order_id' => 1687, 'object_id' => 1040, 'quantity' => 3],  // C 變體
  ['order_id' => 1687, 'object_id' => 1041, 'quantity' => 2],  // D 變體
  ...
]
```

內部偵測：若 array 第一個元素是 array → 新格式；否則用舊格式且自動補 `object_id` 為「該訂單該商品的第一個 variation」（保持單變體商品行為不變）。

驗證採購數時跨變體加總的邏輯不變（採購池跨變體共用是現有行為）。

**理由**：

- 前端 admin/partials/products.php line 1011 早已傳 `{order_id, allocated, object_id}` 但後端 line 1010-1014 忽略了 object_id 欄位
- 向下相容讓單變體商品的舊測試和舊 caller 不受影響

**替代方案**：

- 方案 B：直接 breaking change，所有 caller 同步改 → 不採用（外部 LIFF 端可能也有用到，影響範圍大）

### Decision 4: `Products_API::allocate_stock` 把 `object_id` 透過新格式傳給 service

**做法**：

修改 `class-products-api.php` line 1004-1021 的 `$raw_allocations` 解析迴圈，把 `object_id` 一併保留：

```php
$allocations = [];
foreach ($raw_allocations as $key => $value) {
  if (is_array($value)) {
    $order_id = (int)($value['order_id'] ?? 0);
    $object_id = (int)($value['object_id'] ?? 0);
    $quantity = (int)($value['allocated'] ?? $value['quantity'] ?? 0);
    if ($order_id > 0 && $quantity > 0) {
      if ($object_id > 0) {
        $allocations[] = ['order_id' => $order_id, 'object_id' => $object_id, 'quantity' => $quantity];
      } else {
        $allocations[$order_id] = $quantity;  // 舊格式 fallback
      }
    }
  }
  // ... 物件格式維持舊行為
}
```

**理由**：保留前端已傳的 `object_id`，不破壞舊 client。

### Decision 5: 資料修復策略 — 按父訂單實際變體比例還原

**做法**：

寫一次性 WP-CLI 命令 `bin/fix-cross-variant-child-orders.php`，邏輯：

1. 找出所有「子訂單 object_id ≠ 父訂單同 order_item_id 的 object_id」的紀錄
2. 對每個錯標的子訂單：
   - 讀父訂單的 ABCD 各 order_items 和已分配量
   - 推算「哪個變體還缺多少」
   - 把子訂單 object_id 修正為「最缺貨的變體」（FIFO）
3. 重新計算 `wp_postmeta._buygo_allocated`（按 post_id 重算）
4. 補 D 變體（id=1041）的 `_buygo_purchased` meta（從 fct_purchase_orders 推算或直接補一個合理值）
5. 全程 dry-run + commit 兩階段，dry-run 印出計劃，commit 才真改

**理由**：

- 5 筆錯標子訂單的客戶（黃宜茜 #1687、Ya-chieh #1658）已經有實際出貨需求，不能全部刪掉重建（會破壞訂單歷史）
- 按父訂單實際需求推算最合理
- WP-CLI 命令而非 SQL 直接執行，方便驗證和重複跑

**替代方案**：

- 方案 B：cancel 5 筆錯標子訂單，重新跑分配 → 不採用（破壞客戶訂單歷史，且 cancel 邏輯本身可能也有 bug）

### Decision 6: 測試覆蓋多變體場景

**做法**：

新增 `tests/Unit/Services/AllocationCrossVariantTest.php`，至少 5 個 test case：

1. `test_create_child_order_uses_correct_variation_id` — 父訂單有 ABCD，分配 C 應建立 object_id=C 的子訂單
2. `test_allocate_all_for_customer_with_multiple_variants` — 一鍵分配同訂單 ABCD，應建立 4 個子訂單各對應正確變體
3. `test_update_order_allocations_per_item_format` — 新格式 `[{order_id, object_id, quantity}]` 正確運作
4. `test_update_order_allocations_legacy_format_compat` — 舊格式 `[order_id => qty]` 在單變體商品仍正常
5. `test_purchased_pool_shared_across_variants` — 跨變體採購池共用檢核（current_child_allocated + new > purchased 應失敗）

**理由**：

- 防止這個 bug 再次發生（regression test）
- 為未來介面再次調整提供 safety net

## Risks / Trade-offs

- **Risk**：資料修復腳本選錯變體還原 → 客戶仍出錯貨
  → Mitigation：dry-run 階段印出每筆修正前後 object_id，由 Fish 人工核對才執行 commit；正式站先在 Local 重現 + 修復驗證；修復前 mysqldump 備份 `wp_fct_orders` + `wp_fct_order_items` + `wp_fct_meta` + `wp_postmeta`
- **Risk**：介面相容偵測邏輯（array vs scalar）誤判 → 舊 caller 行為破壞
  → Mitigation：用「第一個 element 是 array」明確判斷，加單元測試覆蓋兩種格式
- **Risk**：舊測試（`AllocationDemandCalculationTest`、`AllocationServiceTest`）可能依賴舊的 `createChildOrder` 簽名
  → Mitigation：跑全套測試確認，必要時補上向後相容的 wrapper 簽名
- **Trade-off**：保留向下相容讓 service 介面變複雜（要維護兩種格式）
  → 採用：避免破壞 LIFF 端可能存在的舊呼叫；可在後續 release 中移除舊格式

## Migration Plan

1. **Pre-deployment**（Local 環境）：
   - 跑全套測試 baseline（記錄當前 pass/fail 數）
   - 用測試 fixture 重現多變體分配 bug
2. **Code change**：依任務序實作 Decision 1-4 + 補測試
3. **Local 驗證**：
   - 全套測試 0 失敗
   - 手動跑 Local 環境的多變體分配場景，確認 5 個 case 都對
4. **Staging 驗證**（用正式站 mysqldump 還原到 Local）：
   - 跑修復腳本 dry-run，產出修正計畫
   - Fish 人工核對計畫
   - 跑修復腳本 commit
   - 重新分配並驗證 BCD 子訂單建立正確
5. **Production 部署**：
   - mysqldump 備份相關資料表
   - 部署 code（用 /deploy）
   - 跑修復腳本 dry-run（正式站）
   - Fish 核對後執行 commit
   - 客戶端驗收（黃宜茜 #1687、Ya-chieh #1658 訂單）
6. **Rollback**：
   - 若部署後新一次分配仍有問題：rsync 回退舊版本 code（資料修復不可回退，已備份在 mysqldump）
   - 資料修復後若發現變體還原錯誤：用 mysqldump 還原 `wp_fct_orders` + `wp_fct_order_items`

## Open Questions

- D 變體 `_buygo_purchased` 應補幾？（從業務面確認：Fish 是否實體採購了 D 變體？採購了多少？）→ 在 Phase 4 實作前由 Fish 提供
- 5 筆錯標子訂單的還原比例若有歧義（例如多個變體都缺貨且需求量相同），FIFO 是否合理？→ dry-run 階段呈現給 Fish 決定

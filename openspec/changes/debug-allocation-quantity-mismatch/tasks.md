## 1. 紅燈測試 — 模擬真實 bug 場景（先不改 production code）

- [ ] 1.1 建立 tests/unit/CancelSpellingFilterTest.php，模擬 DB 實查發現的 cancel 拼寫問題：建立兩筆子訂單，一筆 status="cancelled"、一筆 status="canceled"（美式拼寫），呼叫 splitOrder() 計算已拆分數量，驗證 "canceled" 的子訂單仍被算成 active（紅燈，重現 bug）。同時測試 updateOrderAllocations() 的 per-item 驗證和全域驗證的 SQL 是否也漏了 "canceled"。涵蓋 spec：「cancelled child order does not consume split quota」和「admin can cancel an unshipped child order」[Tool: sonnet]

- [ ] 1.2 在 CancelSpellingFilterTest.php 新增測試：splitOrder() 行 914-922 更新 `_allocated_qty` 時的 SQL 也沒過濾 cancelled/canceled 狀態，驗證 `_allocated_qty` 被高估（紅燈，重現 Haiku 發現的新問題 #1）[Tool: sonnet]

- [ ] 1.3 建立 tests/unit/ShipOrderMetaSyncTest.php，模擬出貨後 meta 不更新：建立 product（purchased=10）+ 3 筆子訂單（各 quantity=3，共 9），執行 shipOrder() 出貨其中一筆，驗證 `_buygo_allocated` post meta 沒有從 9 降到 6（紅燈，重現 bug）。涵蓋 spec：「allocated quantity meta stays consistent after shipment」[Tool: sonnet]

- [ ] 1.4 在 ShipOrderMetaSyncTest.php 新增測試：故意設 `_allocated_qty` 初始值為 7（實際子訂單 SUM=5，漂移 +2），執行 shipOrder() 出貨 quantity=2，驗證累減法算出 7-2=5（碰巧對）但若初始值為 8 則算出 8-2=6（應該是 3）。用兩組數據證明累減法在漂移場景下不可靠（紅燈）。涵蓋 spec：「_allocated_qty uses recalculation not subtraction」[Tool: sonnet]

- [ ] 1.5 建立 tests/unit/AllocationLockTest.php，測試「allocation operations use exclusive lock per product」：驗證目前 updateOrderAllocations() 無排他鎖。此測試在 Phase 2 修復後會轉綠（紅燈，理論風險的防禦測試）[Tool: sonnet]

- [ ] 1.6 建立 tests/unit/SplitOrderTransactionTest.php，測試「splitOrder executes within database transaction」：模擬 INSERT fct_order_items 失敗，驗證目前沒有 ROLLBACK，fct_orders 殘留孤兒記錄（紅燈）[Tool: sonnet]

- [ ] 1.7 執行 `composer test` 確認所有紅燈測試的失敗原因符合預期（bug 確實存在，不是測試寫錯）[Tool: sonnet]

## 2. 修復 P0 — cancel 拼寫統一 + cancelled filter（DB 已確認的真實問題）

- [x] 2.1 全局搜尋代碼中所有 `NOT IN ('cancelled', 'refunded')` 的 SQL，統一改為 `NOT IN ('cancelled', 'canceled', 'refunded')`，覆蓋兩種拼寫。涉及檔案：includes/services/class-allocation-write-service.php（行 130, 147, 182）、includes/services/class-allocation-query-service.php（行 133, 143）、includes/services/class-order-service.php（所有相關 SQL）[Tool: sonnet]

- [x] 2.2 在 OrderService::splitOrder() 已拆分數量 SQL（行 726-736）加入 `AND o.status NOT IN ('cancelled', 'canceled', 'refunded')` 條件。同時修復行 914-922 更新 `_allocated_qty` 時的 SQL，也加入相同過濾條件。涵蓋 Decision 2: splitOrder 過濾已取消子訂單 [Tool: sonnet]

- [x] 2.3 更新 CancelSpellingFilterTest.php，驗證修復後：(1) "cancelled" 和 "canceled" 兩種拼寫的子訂單都被正確排除（綠燈）(2) splitOrder 可分配額度正確歸還（綠燈）(3) 行 914 的 `_allocated_qty` 計算也正確排除（綠燈）。執行 `composer test --filter CancelSpelling` 全綠 [Tool: sonnet]

## 3. 修復 P0 — 出貨後同步重算 meta（DB 已確認的 drift）

- [x] 3.1 在 OrderService::shipOrder() 完成出貨邏輯後，呼叫 AllocationWriteService::recalcAllocatedMeta($product_id) 重算 `_buygo_allocated` post meta。注意：recalcAllocatedMeta 的 SQL 也需要包含 "canceled" 拼寫的過濾。涵蓋 Decision 3: 出貨後同步重算 _buygo_allocated。修改檔案：includes/services/class-order-service.php [Tool: sonnet]

- [x] 3.2 在 OrderService::shipOrder() 中，將 `_allocated_qty` 更新邏輯從 `max(0, $current - $shipped_qty)` 累減，改為 `SUM(child_oi.quantity) WHERE child_o.status NOT IN ('cancelled', 'canceled', 'refunded', 'shipped')` 重算。涵蓋 Decision 5: _allocated_qty 改用重算取代累減。修改檔案：includes/services/class-order-service.php [Tool: sonnet]

- [x] 3.3 更新 ShipOrderMetaSyncTest.php，驗證：(1) 出貨後 `_buygo_allocated` 正確下降（綠燈）(2) `_allocated_qty` 用重算後即使初始值漂移也能修正（綠燈）。執行 `composer test --filter ShipOrderMetaSync` 全綠 [Tool: sonnet]

## 4. 修復 P1 — 分配排他鎖（防禦性，DB 未發生但遲早會）

- [x] 4.1 在 AllocationWriteService::updateOrderAllocations() 入口加入 `GET_LOCK('buygo_allocate_{$product_id}', 10)` 排他鎖，完成後（成功或失敗）`RELEASE_LOCK()`。鎖取不到時回傳 WP_Error code=`allocation_locked`。涵蓋 Decision 1: 分配排他鎖用 MySQL GET_LOCK。修改檔案：includes/services/class-allocation-write-service.php [Tool: sonnet]

- [x] 4.2 更新 AllocationLockTest.php，驗證：(1) 加鎖後並發分配不產生雙重子訂單（綠燈）(2) 不同 product_id 互不阻擋（綠燈）(3) lock timeout 回傳正確錯誤（綠燈）。執行 `composer test --filter AllocationLock` 全綠 [Tool: sonnet]

## 5. 修復 P1 — splitOrder 加 Transaction（防禦性）

- [x] 5.1 在 OrderService::splitOrder() 核心寫入段包裹 `START TRANSACTION / COMMIT / ROLLBACK`。涵蓋 Decision 4: splitOrder 加 Transaction 保護。修改檔案：includes/services/class-order-service.php [Tool: sonnet]

- [x] 5.2 更新 SplitOrderTransactionTest.php，驗證 INSERT 失敗時 ROLLBACK 生效、無孤兒訂單（綠燈）。執行 `composer test --filter SplitOrderTransaction` 全綠 [Tool: sonnet]

## 6. 修復 P2 — Legacy object_id=0 嚴格比對 + allocateAll 邏輯修正

- [x] 6.1 修復「legacy object_id=0 rejected for multi-variation products」：在 AllocationWriteService 處理 object_id=0 時，若 has_variations=true 且 variation 數量 > 1，回傳 WP_Error code=`variation_required`。涵蓋 Decision 6: legacy object_id=0 fallback 加嚴格比對。修改檔案：includes/services/class-allocation-write-service.php [Tool: sonnet]

- [x] 6.2 在 AllocationBatchService::allocateAllForCustomer() 行 107，將 `max($child_allocated, $_allocated_qty, $shipped)` 簡化為 `max($child_allocated, $shipped)`，移除對可能已漂移的 `_allocated_qty` meta 的依賴（Haiku 新問題 #3）。修改檔案：includes/services/class-allocation-batch-service.php [Tool: sonnet]

- [x] 6.3 補對應測試驗證 6.1 和 6.2 的修復（綠燈）。執行 `composer test` 確認全綠 [Tool: sonnet]

## 7. 全量測試與 Code Review

- [x] 7.1 執行 `composer test` 全量測試，確認所有測試綠燈、無 regression [Tool: sonnet]

- [x] 7.2 Code Review：審查所有修改檔案的 diff（AllocationWriteService、OrderService、AllocationBatchService、AllocationQueryService），確認無邏輯遺漏、無安全風險 [Tool: kimi]

## 8. 修復 Codex 遺漏項目

- [ ] 8.1 在 `includes/services/class-allocation-calculator.php` 第 245 行和第 268 行，將 `NOT IN ('cancelled', 'refunded')` 改為 `NOT IN ('cancelled', 'canceled', 'refunded')` [Tool: sonnet]

- [ ] 8.2 在 `includes/services/class-product-stats-calculator.php` 第 54 行和第 167 行，將 `NOT IN ('cancelled', 'refunded')` 改為 `NOT IN ('cancelled', 'canceled', 'refunded')` [Tool: sonnet]

- [ ] 8.3 重寫 `tests/Unit/Services/ShipOrderMetaSyncTest.php`：移除 file_get_contents() 靜態掃描，改用 mock/stub 行為測試。測試 shipOrder 後 `_buygo_allocated` 從 9 降為 6（3 筆子訂單各 qty=3，出貨 1 筆），以及 `_allocated_qty` 重算為 SUM 而非累減。參考 AllocationLockTest.php 的 mock 模式 [Tool: sonnet]

- [ ] 8.4 執行全域 grep 確認所有 `NOT IN.*cancelled.*refunded` 都包含 `canceled`，執行 `composer test` 確認全綠 [Tool: sonnet]

## 9. 雲端 DB 修復 + 部署

- [ ] 9.1 SSH 到雲端主機，將 status="canceled" 的子訂單統一更新為 "cancelled"：`UPDATE wp_fct_orders SET status='cancelled' WHERE status='canceled'`（先 SELECT 確認筆數再 UPDATE）

- [ ] 9.2 用 `/deploy` skill 部署代碼到 buygo.instawp.xyz

- [ ] 9.3 部署後 SSH 執行驗證：(1) 確認 cancel 拼寫已統一 (2) 重新觸發一次分配+出貨流程，檢查 `_buygo_allocated` 和 `_allocated_qty` 數字正確

## 10. Fish 驗收

- [ ] 10.1 Fish 進入後台驗收多變體商品的分配流程，確認數量一致

## 11. Spectra 收尾

- [ ] 11.1 `spectra archive debug-allocation-quantity-mismatch`

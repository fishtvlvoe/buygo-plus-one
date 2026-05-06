## 1. 紅燈測試 — 模擬真實 bug 場景（先不改 production code）

- [x] 1.1 建立 tests/unit/CancelSpellingFilterTest.php，模擬 DB 實查發現的 cancel 拼寫問題：建立兩筆子訂單，一筆 status="cancelled"、一筆 status="canceled"（美式拼寫），呼叫 splitOrder() 計算已拆分數量，驗證 "canceled" 的子訂單仍被算成 active（紅燈，重現 bug）。同時測試 updateOrderAllocations() 的 per-item 驗證和全域驗證的 SQL 是否也漏了 "canceled"。涵蓋 spec：「cancelled child order does not consume split quota」和「admin can cancel an unshipped child order」[Tool: sonnet]

- [x] 1.2 在 CancelSpellingFilterTest.php 新增測試：splitOrder() 行 914-922 更新 `_allocated_qty` 時的 SQL 也沒過濾 cancelled/canceled 狀態，驗證 `_allocated_qty` 被高估（紅燈，重現 Haiku 發現的新問題 #1）[Tool: sonnet]

- [x] 1.3 建立 tests/unit/ShipOrderMetaSyncTest.php，模擬出貨後 meta 不更新：建立 product（purchased=10）+ 3 筆子訂單（各 quantity=3，共 9），執行 shipOrder() 出貨其中一筆，驗證 `_buygo_allocated` post meta 沒有從 9 降到 6（紅燈，重現 bug）。涵蓋 spec：「allocated quantity meta stays consistent after shipment」[Tool: sonnet]

- [x] 1.4 在 ShipOrderMetaSyncTest.php 新增測試：故意設 `_allocated_qty` 初始值為 7（實際子訂單 SUM=5，漂移 +2），執行 shipOrder() 出貨 quantity=2，驗證累減法算出 7-2=5（碰巧對）但若初始值為 8 則算出 8-2=6（應該是 3）。用兩組數據證明累減法在漂移場景下不可靠（紅燈）。涵蓋 spec：「_allocated_qty uses recalculation not subtraction」[Tool: sonnet]

- [x] 1.5 建立 tests/unit/AllocationLockTest.php，測試「allocation operations use exclusive lock per product」：驗證目前 updateOrderAllocations() 無排他鎖。此測試在 Phase 2 修復後會轉綠（紅燈，理論風險的防禦測試）[Tool: sonnet]

- [x] 1.6 建立 tests/unit/SplitOrderTransactionTest.php，測試「splitOrder executes within database transaction」：模擬 INSERT fct_order_items 失敗，驗證目前沒有 ROLLBACK，fct_orders 殘留孤兒記錄（紅燈）[Tool: sonnet]

- [x] 1.7 執行 `composer test` 確認所有紅燈測試的失敗原因符合預期（bug 確實存在，不是測試寫錯）[Tool: sonnet]

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

- [x] 8.1 在 `includes/services/class-allocation-calculator.php` 第 245 行和第 268 行，將 `NOT IN ('cancelled', 'refunded')` 改為 `NOT IN ('cancelled', 'canceled', 'refunded')` [Tool: sonnet]

- [x] 8.2 在 `includes/services/class-product-stats-calculator.php` 第 54 行和第 167 行，將 `NOT IN ('cancelled', 'refunded')` 改為 `NOT IN ('cancelled', 'canceled', 'refunded')` [Tool: sonnet]

- [x] 8.3 重寫 `tests/Unit/Services/ShipOrderMetaSyncTest.php`：移除 file_get_contents() 靜態掃描，改用 mock/stub 行為測試。測試 shipOrder 後 `_buygo_allocated` 從 9 降為 6（3 筆子訂單各 qty=3，出貨 1 筆），以及 `_allocated_qty` 重算為 SUM 而非累減。參考 AllocationLockTest.php 的 mock 模式 [Tool: sonnet]

- [x] 8.4 執行全域 grep 確認所有 `NOT IN.*cancelled.*refunded` 都包含 `canceled`，執行 `composer test` 確認全綠（319 tests, 0 failures）[Tool: sonnet]

## 9. CR 發現的 5 個問題修復

- [x] 9.1 **[High] Lock key 改用 parent product ID**：`includes/services/class-allocation-write-service.php` 約第 31 行，目前 lock name 是 `buygo_allocate_{$product_id}`，但傳入的 `$product_id` 可能是 variation ID。後續 `getAllVariationIds()` 會對同商品所有 variations 做總量檢查，兩個不同 variation 同時分配會拿到不同鎖，仍可能 oversubscribe。修法：在取 lock 前，先用 `wp_get_post_parent_id($product_id)` 取得 parent post ID（如果是 variation 的話），確保同一商品的所有 variation 共用同一把鎖。若 `$product_id` 本身就是 parent（simple product），parent_id = 0，則用原值。lock name 改為 `buygo_allocate_{$parent_id}`

- [x] 9.2 **[High] mark_shipped() 加 meta 重算**：`includes/services/class-shipment-service.php` 約第 157 行，`mark_shipped()` 將子訂單 status 改為 `shipped` 後，沒有觸發 `_allocated_qty` 和 `_buygo_allocated` 的重算。而 `shipOrder()` 的重算 SQL 用 `NOT IN (..., 'shipped')` 過濾，依賴的是子訂單已經是 shipped 狀態。但 `create_shipment()` 只設 `processing`，真正改 `shipped` 是 `mark_shipped()`。修法：在 `mark_shipped()` 成功將 status 改為 `shipped` 後，加入與 `shipOrder()` 相同的 `_allocated_qty` SUM 重算（排除 cancelled/canceled/refunded/shipped）和 `_buygo_allocated` post meta 重算。可抽成共用方法 `recalcAllocationMeta($parent_order_id, $product_id)` 避免重複代碼

- [x] 9.3 **[Medium] splitOrder() transaction 任何 item insert 失敗都 ROLLBACK**：`includes/services/class-order-service.php` 約第 879 行，目前邏輯是 `$items_inserted > 0` 就繼續 commit。應改為：遍歷所有品項 insert，任何一個失敗就 ROLLBACK 整筆交易。具體改法：在 item insert loop 內，若 `$wpdb->insert()` 回傳 false，立即 `$wpdb->query('ROLLBACK')` 並 return WP_Error。移除 `$items_inserted > 0` 的判斷，改為「全部品項都 insert 成功才 COMMIT」

- [x] 9.4 **[Medium] 多個出貨路徑補 meta 重算**：目前 meta 重算只在 `OrderService::shipOrder()` 內。但 `Shipments_API::create_shipment()`（`includes/api/class-shipments-api.php` 約第 572 行）和 `Orders_API::prepare_order()`（`includes/api/class-orders-api.php` 約第 497 行）都直接呼叫 `ShipmentService::create_shipment()`，繞過 meta 重算。修法：在 `ShipmentService::create_shipment()` 完成後，呼叫 9.2 抽出的共用方法 `recalcAllocationMeta()` 做重算。或者用 WordPress action hook：在 `ShipmentService` 的出貨完成點 `do_action('buygo_shipment_created', $order_id, $product_id)`，然後在 `AllocationWriteService` 監聽這個 hook 觸發重算。建議用 hook 方式，維持 service 間解耦

- [x] 9.5 **[Low] 清除 trailing blank lines**：`tests/Unit/Services/CancelSpellingFilterTest.php` 和 `tests/Unit/Services/SplitOrderTransactionTest.php` 檔案末尾有多餘空行，用 `sed -i '' -e :a -e '/^\n*$/{$d;N;ba' -e '}' <file>` 或手動移除

- [x] 9.6 執行 `composer test` 確認全綠，無 regression

## 10. Fish 驗收本機測試結果

- [x] 10.1 Fish 確認本機所有測試全綠、CR 問題全修、整合測試場景正確

## 11. Spectra 收尾

- [x] 11.1 `spectra archive debug-allocation-quantity-mismatch`

## 12. CR 二輪 — 修復驗證發現的 7 個問題

- [x] 12.1 **[Critical] resolveAllocationLockId() 移除 $GLOBALS mock**：`includes/services/class-allocation-write-service.php` 的 `resolveAllocationLockId()` 用 `$GLOBALS['mock_product_variation_map']` 和 `$GLOBALS['mock_variation_map']` 做測試 fallback，這滲入了生產代碼。改法：抽出 `protected function getVariationParentId(int $product_id): int` 方法，只放 FluentCart ORM 查詢 + `wp_get_post_parent_id` fallback。測試中用匿名子類別 override 這個方法，不用 $GLOBALS

- [x] 12.2 **[Critical] mark_shipped → syncForShipment() 測試 mock 修正**：`tests/Unit/Services/ShipOrderMetaSyncTest.php` 中測試 `mark_shipped` 的場景，mock 的 `get_results()` 沒有正確模擬 `buygo_shipment_items` 資料表查詢（用了 `fct_order_items`）。修正 mock 讓它回傳正確格式的 shipment items 資料

- [x] 12.3 **[Critical] 補 simple product lock key 測試**：`tests/Unit/Services/AllocationLockTest.php` 或 `AllocationIntegrationTest.php` 新增測試：simple product（`wp_get_post_parent_id` 回 0）時 lock key 用自身 product_id，不會出現 `buygo_allocate_0`

- [x] 12.4 **[Warning] cancel filter SQL 狀態列表抽成常數**：在 `AllocationMetaSyncService` 或共用位置定義 `const INACTIVE_STATUSES = ['cancelled', 'canceled', 'refunded']` 和 `const NON_ALLOCATABLE_STATUSES = ['cancelled', 'canceled', 'refunded', 'shipped']`。shipOrder() 和 AllocationMetaSyncService 的 SQL 都引用這些常數，避免未來兩套不同步

- [x] 12.5 **[Warning] AllocationMetaSyncService 加 class_exists 保護**：與其他 service 一致，檔案頂部加 `if (!defined('ABSPATH')) exit;` 保護

- [x] 12.6 **[Warning] 測試 tearDown 清理 $GLOBALS['wpdb']**：`AllocationLockTest.php` 的 `tearDown()` 加 `unset($GLOBALS['wpdb'])` 或 restore 原值，避免測試間互相污染

- [x] 12.7 **[Warning] ShipOrderMetaSyncTest mock SQL 比對改寬鬆**：將硬編碼 SQL 字串比對（如 `strpos($sql, 'SELECT status FROM wp_fct_orders WHERE id = 5001')`）改為正則或關鍵字比對（如 `strpos($sql, 'fct_orders') && strpos($sql, '5001')`），避免 SQL 格式微調就假通過

- [x] 12.8 執行 `composer test` 確認全綠

---

> **後續獨立任務（不在本 Change 範圍）：**
> - SSH 雲端主機：`UPDATE wp_fct_orders SET status='cancelled' WHERE status='canceled'`
> - 部署代碼到 buygo.instawp.xyz
> - 部署後驗證分配+出貨流程數字正確

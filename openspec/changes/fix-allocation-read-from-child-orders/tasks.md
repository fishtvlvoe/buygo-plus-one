<!--
TDD（.spectra.yaml: tdd=true）：先寫紅燈測試（任務 1）→ 再寫實作（任務 2、3）。
Parallel（parallel_tasks=true）：同群組內 [P] 可並行；不同群組串行。
工具標記：[Tool: sonnet] / [Tool: copilot] / [Tool: kimi] / [Tool: claude]。
-->

## 1. Red — 失敗測試先行

- [ ] 1.1 [Tool: sonnet] 滿足「Allocated Quantity MUST Be Computed From Child Orders」之 calculator 端紅燈：新增測試檔 `tests/Unit/Services/ProductStatsCalculatorAllocatedTest.php`，骨架參考既有 `tests/Unit/Api/IsPlatformAdminTest.php`（PHPUnit + 純 PHP，無 WP 環境，bootstrap-unit.php 已 require autoload）。撰寫 `test_calculateAllocatedPerParentOrder_groups_by_parent_and_variation`：mock `$wpdb`（用測試 stub class 重定 prefix + 注入 `get_results` 回傳 fixture rows `[parent_id, variation_id, allocated_qty]`），對 calculator 呼叫尚不存在的 `calculateAllocatedPerParentOrder([1746,1747], [1055])` 斷言回傳 `[1746 => [1055 => 1], 1747 => [1055 => 1]]`。驗證：跑 `composer test -- --filter ProductStatsCalculatorAllocatedTest` 紅燈，錯誤訊息含 `Call to undefined method calculateAllocatedPerParentOrder`。
- [ ] 1.2 [P] [Tool: sonnet] 滿足同一需求之 `cancelled/refunded` 排除規則紅燈：同檔新增 `test_calculateAllocatedPerParentOrder_excludes_cancelled_and_refunded`：以 `prepare()` mock 截獲實際 SQL 字串，斷言含 `parent_id IS NOT NULL`、`type = 'split'`、`status NOT IN ('cancelled', 'refunded')` 三個 WHERE clause。驗證：紅燈（方法不存在）。
- [ ] 1.3 [P] [Tool: sonnet] 滿足同一需求之空輸入保護紅燈：同檔新增 `test_calculateAllocatedPerParentOrder_empty_inputs_return_empty_array`：呼叫 `calculateAllocatedPerParentOrder([], [1055])` 與 `calculateAllocatedPerParentOrder([1746], [])` 皆斷言回 `[]`，且 mock `$wpdb->get_results` 計數為 0（empty 直接 early return）。驗證：紅燈。
- [ ] 1.4 [P] [Tool: sonnet] 滿足「All three read paths return the same allocated value」需求之 buyer 端紅燈：新增測試檔 `tests/Unit/Services/ProductBuyerQueryServiceAllocatedTest.php`，撰寫 `test_buildBuyerOrderEntry_uses_child_orders_not_line_meta`：mock parent order item line_meta 為 `{"_allocated_qty":"0"}`，但注入 `calculateAllocatedPerParentOrder` mock 回傳該 parent+variation 為 2，呼叫 `buildBuyerOrderEntry` 後斷言 entry 的 `allocated_quantity === 2`、`pending_quantity === max(0, quantity-2)`。驗證：紅燈（目前實作仍讀 line_meta=0）。
- [ ] 1.5 [P] [Tool: sonnet] 同檔新增 `test_buildBuyerOrderEntry_pending_equals_quantity_minus_child_allocated`：mock quantity=5、child sum=3，斷言 `allocated_quantity=3, pending_quantity=2`；另一 case quantity=3、child sum=5（超分配），斷言 `pending_quantity=0`。驗證：紅燈。
- [ ] 1.6 [Tool: claude] 主對話跑 `composer test -- --filter "ProductStatsCalculatorAllocatedTest|ProductBuyerQueryServiceAllocatedTest"`，將 fail 輸出貼 `/tmp/red-evidence-fix-allocation.log`，確認 1.1–1.5 全紅才進任務 2。驗證：每個測試 status=failed，無 fatal error。

## 2. Green — Calculator 新方法實作

- [ ] 2.1 [Tool: copilot] 在 `includes/services/class-product-stats-calculator.php` 新增 `public function calculateAllocatedPerParentOrder(array $parentOrderIds, array $variationIds): array`：空輸入回 `[]`；以 `wpdb->prepare` 安全嵌入 `$parentOrderIds` 與 `$variationIds` 兩組 `%d` placeholder；SQL 為 `SELECT parent.id AS parent_id, child_item.object_id AS variation_id, SUM(child_item.quantity) AS allocated_qty FROM {prefix}fct_order_items child_item INNER JOIN {prefix}fct_orders child ON child.id=child_item.order_id INNER JOIN {prefix}fct_orders parent ON parent.id=child.parent_id WHERE parent.id IN (...) AND child_item.object_id IN (...) AND child.type='split' AND child.status NOT IN ('cancelled','refunded') GROUP BY parent.id, child_item.object_id`；組 nested map 回傳；try/catch 透過 `$this->debugService->log(...)` 記錯誤、catch 回 `[]`（沿用既有 `calculateAllocatedToChildOrders` 錯誤處理模式）。驗證：1.1、1.2、1.3 三個測試由紅轉綠。

## 3. Green — 接到三個讀取點

- [ ] 3.1 [Tool: copilot] 滿足「List endpoint reports allocated equal to live child-order sum」：修改 `includes/api/class-products-api.php` 列表 endpoint 處理迴圈，先 collect 所有商品的 variation ids（或 post_id 若無 variation），呼叫 `ProductStatsCalculator::calculateAllocatedToChildOrders($ids)` 取 map；每個商品的 `allocated` 改用 map 對應值（無對應視為 0），移除 `get_post_meta($postId, '_buygo_allocated', true)` 的讀取與 fallback。驗證：手動以 production fixture 跑（mock get_results 回傳 child orders 加總=2 + post_meta=`''`），斷言回傳 allocated=2；既有 `ProductsApiStatsTest::test_no_transient_calls_in_products_api` 仍綠（PR #11 行為保留）。
- [ ] 3.2 [P] [Tool: copilot] 滿足同需求之單品端點：同檔單品 endpoint（`get_product`）對 `allocated` 欄位同樣改用 `calculateAllocatedToChildOrders([variationOrPostId])` 結果取代 `get_post_meta` 與 ProductService fallback。驗證：與 3.1 互相對照測試 — 給定同 fixture，list 與 single 回傳 allocated 相等。
- [ ] 3.3 [Tool: copilot] 滿足「Buyers endpoint per-order allocated equals child-order sum scoped to that parent order」：修改 `includes/services/class-product-buyer-query-service.php` 的 `getProductBuyers()`：在迴圈進入前先 collect 所有 parent order id 與 variation id，呼叫新方法 `calculateAllocatedPerParentOrder()` 取 nested map；改 `buildBuyerOrderEntry()` 簽名加入 `$allocatedMap` 參數（或建構 service 私有屬性），entry 內 `allocated_quantity` 改用 `$allocatedMap[$parentOrderId][$objectId] ?? 0`，徹底不再讀 `$metaData['_allocated_qty']`。驗證：1.4 + 1.5 兩個測試由紅轉綠。
- [ ] 3.4 [Tool: claude] 主對話跑 `composer test` 全套，確認 5 個新測試 + 既有 344 全綠，無新 fail。驗證：exit code 0、test count >= 349。

## 4. Deprecation 註解 + 線上驗收

- [ ] 4.1 [P] [Tool: sonnet] 滿足 D-R4 風險對策：在 `_buygo_allocated` 寫入點（grep 該字串於 `includes/services/class-allocation-*.php` 與 `includes/services/class-product-stats-calculator.php` 等檔），於該欄位寫入函式上方加 `@deprecated` PHP doc 註解，文字含「請改用 `ProductStatsCalculator::calculateAllocatedToChildOrders`」。同樣處理 `_allocated_qty` 寫入點。**不刪除寫入**。驗證：`grep -r "@deprecated.*ProductStatsCalculator" includes/` 命中至少 2 處；`composer test` 仍全綠。
- [ ] 4.2 [Tool: claude] 線上驗收（以 sshpass 連 buygo.me 部署後測）：對 product post_id=1055 開啟 `view=allocation&id=1055` 與 `view=buyers&id=1055` 兩頁，截圖兩處卡片「已分配」皆為 2、訂單明細每筆 已分配=1。再對 product post_id=1258 開啟同樣兩頁，截圖兩處卡片皆為 52（迴歸保護）。驗證：4 張截圖數值對應 + 列入 PR 描述。
- [ ] 4.3 [Tool: claude] bump 版本 1.7.8 → 1.7.9（patch，L060）：改 `buygo-plus-one.php` 兩處（`Version:` header + `BUYGO_PLUS_ONE_VERSION` define）+ `package.json` 的 `version`。conventional commit + 開 PR：`fix(allocation): read allocated from child orders as single source of truth`。push 到 `fix/allocation-read-from-child-orders` 分支。驗證：PR 已開、CI 全綠、`spectra validate fix-allocation-read-from-child-orders` 通過。

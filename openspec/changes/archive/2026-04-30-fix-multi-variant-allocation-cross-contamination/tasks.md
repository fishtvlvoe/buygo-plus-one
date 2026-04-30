## 1. TDD 紅燈 — 撰寫多變體分配測試

- [x] 1.1 [Tool: sonnet] 建立測試檔 `tests/Unit/Services/AllocationCrossVariantTest.php`，建立 fixture：父訂單 #1687 含 ABCD 四變體 order_items（A=3, B=3, C=3, D=2），post_id=2650，採購 meta A=7/B=4/C=4/D=0
- [x] 1.2 [P] [Tool: sonnet] 撰寫紅燈測試 `test_create_child_order_uses_correct_variation_id`：對應 design Decision 1 — 直接呼叫 `AllocationWriteService::createChildOrder` 分配 D（variation_id=1041）qty=2，斷言新建子訂單 order_item 的 object_id=1041 而非 1038（涵蓋 spec「Child orders MUST preserve target variation identity」）
- [x] 1.3 [P] [Tool: sonnet] 撰寫紅燈測試 `test_allocate_all_for_customer_with_multiple_variants`：對應 design Decision 2 — 一鍵分配 #1687 全部待配，斷言建出 4 個子訂單，object_id 分別為 1038/1039/1040/1041，總分配量=11（涵蓋 spec「Allocate-all for a customer MUST create independent child orders per variation」）
- [x] 1.4 [P] [Tool: sonnet] 撰寫紅燈測試 `test_update_order_allocations_per_item_format`：對應 design Decision 3 — 用新格式 `[{order_id, object_id, quantity}]` 呼叫，斷言 C+D 各建立獨立子訂單 object_id 正確（涵蓋 spec「Allocate stock API MUST accept per-item allocations carrying object_id」）
- [x] 1.5 [P] [Tool: sonnet] 撰寫紅燈測試 `test_update_order_allocations_legacy_format_compat`：對應 design Decision 3 — 用舊格式 `[order_id => qty]` 呼叫單變體商品仍正常運作，斷言 object_id 從 parent_item 自動解析正確
- [x] 1.6 [P] [Tool: sonnet] 撰寫紅燈測試 `test_purchased_pool_shared_across_variants`：對應 design Decision 1 採購池檢核 — 採購總量=11、已分配 9、嘗試再分 3 應回傳 INSUFFICIENT_STOCK（涵蓋 spec「Cross-variant purchased pool validation MUST remain enforced」）
- [x] 1.7 [Tool: sonnet] 跑 `composer test -- --filter AllocationCrossVariant`，確認 6 個測試全部紅燈（fail），記錄失敗訊息作為實作 baseline

## 2. 綠燈實作 — Decision 1: `createChildOrder` 加 `$variation_id` 參數，SQL 改精確過濾

- [x] 2.1 [Tool: copilot-codex] 實作 Decision 1: `createChildOrder` 加 `$variation_id` 參數，SQL 改精確過濾 — 修改 `includes/services/class-allocation-write-service.php` 的 `createChildOrder`：簽名改為 `createChildOrder(int $parent_order_id, int $variation_id, int $quantity)`；移除 `$product_id` 參數和內部 `getAllVariationIds` 呼叫；line 236-239 的 SQL `WHERE order_id = %d AND object_id IN (...)` 改為 `WHERE order_id = %d AND object_id = %d`
- [x] 2.2 [Tool: copilot-codex] 修改 `updateOrderAllocations` 內呼叫 `createChildOrder` 的位置（line 127）：改傳 `$item['object_id']` 作為 variation_id；確保迴圈是 foreach `$items`（per-item）而非 foreach `$allocations`（per-order_id）
- [x] 2.3 [Tool: copilot-codex] 跑 `composer test -- --filter test_create_child_order_uses_correct_variation_id` 確認該測試轉綠

## 3. 綠燈實作 — Decision 3: `updateOrderAllocations` 介面擴充支援 per-item 格式

- [x] 3.1 [Tool: copilot-codex] 實作 Decision 3: `updateOrderAllocations` 介面擴充支援 per-item 格式 — 修改 `class-allocation-write-service.php` 的 `updateOrderAllocations`：在方法開頭偵測 allocations 格式（第一個 element 是 array → 新格式；否則舊格式）；新格式直接 foreach 處理 per-item；舊格式維持「先 query 找 items 再對應」的行為
- [x] 3.2 [Tool: copilot-codex] 確保 items 收集邏輯涵蓋 per-item：當新格式時，items 直接從 allocations 的 `object_id` 過濾；當舊格式時，沿用既有 `object_id IN ($var_placeholders) AND order_id IN ($order_placeholders)` query 但補充：若同一訂單只回一筆，作為相容路徑
- [x] 3.3 [Tool: copilot-codex] 跑 `composer test -- --filter "test_update_order_allocations"` 確認新舊格式測試都轉綠

## 4. 綠燈實作 — Decision 2: `allocateAllForCustomer` 改用 `order_item_id` 為 allocations key

- [x] 4.1 [Tool: copilot-codex] 實作 Decision 2: `allocateAllForCustomer` 改用 `order_item_id` 為 allocations key — 修改 `includes/services/class-allocation-batch-service.php` line 65-135 `allocateAllForCustomer`：把 `$allocations[(int) $item->order_id] = $needed`（line 118）改為 `$allocations[] = ['order_id' => (int) $item->order_id, 'object_id' => (int) $item->object_id, 'quantity' => $needed]`
- [x] 4.2 [Tool: copilot-codex] 確認 `array_sum($allocations)` 改為 `array_sum(array_column($allocations, 'quantity'))`（line 131）；`skipped_orders` 收集邏輯不變
- [x] 4.3 [Tool: copilot-codex] 跑 `composer test -- --filter test_allocate_all_for_customer_with_multiple_variants` 確認測試轉綠

## 5. 綠燈實作 — Decision 4: `Products_API::allocate_stock` 把 `object_id` 透過新格式傳給 service

- [x] 5.1 [Tool: copilot-codex] 實作 Decision 4: `Products_API::allocate_stock` 把 `object_id` 透過新格式傳給 service — 修改 `includes/api/class-products-api.php` 的 `allocate_stock` line 1004-1021：陣列 element 解析時，若 `value['object_id']` 存在且 > 0，allocations 改為新格式 push；否則維持舊格式 map
- [ ] 5.2 [Tool: copilot-codex] 用 wp eval 或 cURL 對本機 Local 站發 `POST /wp-json/buygo-plus-one/v1/products/2650/allocate` 測試請求，body 為 `{"product_id":2650,"allocations":[{"order_id":1687,"object_id":1040,"allocated":3},{"order_id":1687,"object_id":1041,"allocated":2}]}`，確認回傳 success 且兩筆子訂單 object_id 正確

## 6. 一次性資料修復 — Decision 5

- [ ] 6.1 [Tool: copilot-codex] 建立 `bin/fix-cross-variant-child-orders.php` — One-time data repair SHALL fix existing cross-variant contaminated child orders — 註冊 WP-CLI 命令 `buygo fix-cross-variant-child-orders` 接受 `--dry-run`、`--commit`、`--post-id=<id>` 參數（對應 design Decision 5: 資料修復策略 — 按父訂單實際變體比例還原）
- [ ] 6.2 [Tool: copilot-codex] 實作偵測邏輯：找出 `child_o.type='split'` 且 `child_oi.object_id` 不在「同 parent_id 父訂單的所有 order_item.object_id 集合」中、或對應 variation 沒有實際下單需求的子訂單
- [ ] 6.3 [Tool: copilot-codex] 實作還原邏輯：對每個錯標子訂單，計算父訂單各變體的 `(已下單 - 已分配)`，把該子訂單 object_id 改為缺額最大的變體；多個變體缺額相同時按 variation_id 升序選
- [ ] 6.4 [Tool: copilot-codex] 實作 dry-run 輸出：印出表格 `child_id | parent_id | old_object_id | new_object_id | quantity`；commit 模式包在 transaction 內，失敗 rollback
- [ ] 6.5 [Tool: copilot-codex] 補 D 變體 meta 邏輯：偵測 post_id 範圍內哪些 variation 缺 `_buygo_purchased` meta，列入 dry-run 報告，commit 時等待操作員透過 `--purchased-d=<n>` 參數提供值
- [ ] 6.6 [Tool: copilot-codex] 重算 `wp_postmeta._buygo_allocated` 邏輯：對每個受影響的 post_id，從 child_orders 重新加總並覆寫 meta
- [ ] 6.7 [Tool: copilot-codex] 在 SSH 主機 buygo.instawp.xyz 跑 dry-run（針對 post_id=2650）：`wp buygo fix-cross-variant-child-orders --dry-run --post-id=2650`，把輸出 dump 到 change 目錄 `repair-plan-dryrun.txt` 供 Fish 核對

## 7. 測試覆蓋與驗收 — Decision 6

- [x] 7.1 [Tool: copilot-codex] 跑 `composer test`：全部測試應通過 0 失敗（對應 design Decision 6: 測試覆蓋多變體場景）
- [x] 7.2 [P] [Tool: kimi] 對 diff 做 Code Review（focus on：跨變體 SQL 正確性、向下相容、transaction 邊界、WP-CLI 安全性）
- [x] 7.3 [Tool: copilot-codex] 跑 `composer test:coverage`，確認 `class-allocation-write-service.php` 和 `class-allocation-batch-service.php` 覆蓋率 > 80%

## 8. 部署與正式站修復

- [ ] 8.1 [Tool: copilot-codex] 在 SSH 主機備份相關資料表：`wp db export backup-$(date +%Y%m%d-%H%M).sql --tables=wp_fct_orders,wp_fct_order_items,wp_fct_meta,wp_postmeta`
- [ ] 8.2 用 `/deploy` skill 部署 code 到 buygo.instawp.xyz
- [ ] 8.3 [Tool: copilot-codex] 在 SSH 主機跑正式站 dry-run：`wp buygo fix-cross-variant-child-orders --dry-run --post-id=2650`，輸出存 `repair-plan-prod-dryrun.txt`，等待 Fish 確認還原計畫
- [ ] 8.4 Fish 核可後在 SSH 主機執行 commit：`wp buygo fix-cross-variant-child-orders --commit --post-id=2650 --purchased-d=<Fish 提供的值>`
- [ ] 8.5 [Tool: copilot-codex] 用 wp db query 驗證修復結果：(a) 查 #1687 和 #1658 的子訂單 object_id 分布應對應 ABCD；(b) `_buygo_allocated` 應重算為實際子訂單總量；(c) D 變體 `_buygo_purchased` 應有值
- [ ] 8.6 由 Fish 進入後台分配頁面實際操作驗收：確認 BCD 變體訂單可正常分配、A 變體已分配數正確、四個變體可獨立操作

## 9. Spectra 收尾

- [x] 9.1 git commit 用 conventional：`fix(allocation): correct cross-variant child order object_id contamination` + 引用 change 名稱
- [x] 9.2 跑 `spectra validate fix-multi-variant-allocation-cross-contamination` 確認 0 警告
- [ ] 9.3 `spectra archive fix-multi-variant-allocation-cross-contamination`

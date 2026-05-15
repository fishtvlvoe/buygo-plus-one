<!--
TDD（.spectra.yaml: tdd=true）：先寫紅燈測試（任務 1）→ 再寫實作（任務 2-4）。
Parallel（parallel_tasks=true）：同群組內 [P] 可並行；不同群組串行。
工具標記：[Tool: sonnet] / [Tool: claude]。本次任務全程派 Sonnet（用戶指定）。
-->

## 1. Red — 失敗測試先行

- [ ] 1.1 [Tool: sonnet] 滿足「Shipment Detail Endpoint MUST Return Variation Identification」之 service 端紅燈：新增 `tests/Unit/Services/ShipmentServiceGetItemsTest.php`，骨架參考既有 `tests/Unit/Services/ShipmentServiceTest.php` 與 `tests/Unit/Api/IsPlatformAdminTest.php`。撰寫 `test_get_shipment_items_includes_variation_fields`：用 FakeWpdb stub `get_results` 回傳含 `variation_id=976`、`variation_title="(A) 薄荷巧克力"`、`variation_identifier="BUYGO-2560-A"` 的 fixture rows；呼叫 `ShipmentService::get_shipment_items(420)`，斷言回傳第一筆含上述三個欄位且值正確。**預期紅燈**（service 當前 SQL 是 `SELECT * FROM buygo_shipment_items`，沒 JOIN，回不到 variation 欄位）。驗證：`composer test -- --filter ShipmentServiceGetItemsTest` 失敗訊息含 `Undefined array key "variation_title"` 或 `null`。
- [ ] 1.2 [P] [Tool: sonnet] 同檔撰寫 `test_get_shipment_items_null_variation_for_missing_join`：fixture 含一筆 variation 欄位皆 null 的 row（模擬 LEFT JOIN 沒對到），呼叫後斷言該筆 `variation_id === null`、`variation_title === null`、`variation_identifier === null`，且不丟 PHP warning / exception。預期紅燈。驗證：紅燈訊息與 1.1 類似。
- [ ] 1.3 [P] [Tool: sonnet] 同檔撰寫 `test_get_shipment_items_preserves_existing_fields`：fixture 5 個既有欄位（`id`、`shipment_id`、`order_id`、`order_item_id`、`product_id`、`quantity`、`created_at`）皆有值；斷言回傳這 7 個欄位 name 與 type 不變。預期紅燈（值不變但測試靠陣列 key 比對，若實作不對會抓到）。
- [ ] 1.4 [P] [Tool: sonnet] 同檔撰寫 `test_get_shipment_items_sql_has_single_join_per_table`：用 FakeWpdb 截獲 prepare 出來的 SQL 字串，斷言含且僅含一個 `LEFT JOIN .*fct_order_items` 與一個 `LEFT JOIN .*fct_product_variations`（用 regex `preg_match_all`），不可有 N+1 子查詢或重複 JOIN。預期紅燈（service 目前沒任何 JOIN）。
- [ ] 1.5 [Tool: claude] 主對話跑 `composer test -- --filter ShipmentServiceGetItemsTest 2>&1 | tee /tmp/red-evidence-shipment.log`，確認 1.1–1.4 全紅才進任務 2。驗證：4 個測試 status=failed，無 fatal error；如有意外綠燈或 fatal STOP 回報。

## 2. Green — 後端 service SQL JOIN

- [ ] 2.1 [Tool: sonnet] 滿足同需求之實作：修改 `includes/services/class-shipment-service.php` 的 `get_shipment_items(int $shipment_id): array`，將原 `SELECT * FROM buygo_shipment_items WHERE shipment_id=%d` 改為含 LEFT JOIN：
  ```
  SELECT si.*,
         oi.object_id AS variation_id,
         pv.variation_title,
         pv.variation_identifier
  FROM {prefix}buygo_shipment_items si
  LEFT JOIN {prefix}fct_order_items oi ON oi.id = si.order_item_id
  LEFT JOIN {prefix}fct_product_variations pv ON pv.id = oi.object_id
  WHERE si.shipment_id = %d
  ```
  保留 ARRAY_A 回傳格式。
  驗證：1.1、1.2、1.3、1.4 全部由紅轉綠；run `composer test -- --filter ShipmentServiceGetItemsTest` 全綠。

## 3. Green — 前端 mergeItemsByProduct 帶 subItems

- [ ] 3.1 [Tool: sonnet] 修改 `includes/views/composables/useShipmentDetails.js` 的 `mergeItemsByProduct(items)`：

  **既有合併邏輯的明確規範**（cross-impact 報告 B 抓到的潛在 bug：原本 `map[pid] = { ...item }` 第二筆會整個覆蓋第一筆，導致 parent row 的 `variation_*` 欄位變最後一筆值）。本任務一併釐清：

  - 第一次見到 `product_id`：建 `map[pid] = { product_id, product_name, quantity, price, subtotal, subItems: [] }`，**只保留 product 層級欄位（不複製 variation_* 到 parent）**。
  - 已存在的 `product_id`：`map[pid].quantity += item.quantity`、`map[pid].subtotal += item.quantity * item.price`，**不覆蓋 product_name / price**。
  - 每筆都查 `subItems` 是否已有同 `variation_id`：有則 `existing.quantity += item.quantity`、無則 push `{variation_id, variation_title, quantity: item.quantity}`。

  **不修改既有欄位 schema**（保 product_id、product_name、quantity、price、subtotal 鍵名與型別）。

  驗證：新增 `tests/Unit/Frontend/MergeItemsByProductTest.js`（或在既有 JS 測試框架下 — 若無，用 Node 跑簡單 require 驗證 fixture 對應；無前端測試框架時改在 PHP 端用 mock data 驗）：對 fixture `[{product_id:2560,product_name:"K",quantity:1,price:4000,variation_id:976,variation_title:"(A)薄荷"},{product_id:2560,product_name:"K",quantity:2,price:4000,variation_id:977,variation_title:"(B)?"},{product_id:2560,product_name:"K",quantity:1,price:4000,variation_id:978,variation_title:"(C)?"}]` 斷言：(a) 結果 length=1；(b) 第一筆 `quantity===4`、`subtotal===16000`、`product_name==="K"`；(c) `subItems.length===3` 且 quantity 加總=4。若無 JS 測試框架，至少在 spectra-apply 後手動 console 驗證並截圖。

## 4. Green — UI 子列縮排顯示

- [ ] 4.1 [Tool: sonnet] 修改 `admin/partials/shipment-details.php` 的「商品明細」table tbody（`<tr v-for="item in mergedDetailItems">` 區段，line 約 480-520）：在父商品 `<tr>` 之後新增 `<template v-if="mergeEnabled && item.subItems && item.subItems.length > 1">`，內含 `<tr v-for="sub in item.subItems" :key="sub.variation_id" class="bg-slate-50/50">` 子列，欄位順序：（1）商品名稱欄縮排顯示 `└ {{ sub.variation_title || '未命名 variation' }}`，使用 `class="pl-8 text-xs text-slate-500"`；（2）數量欄顯示 `× {{ sub.quantity }}`；（3）單價欄與小計欄留空 `<td></td>`。**禁用 `v-html` 渲染 `sub.variation_title`**（防 XSS）。
- [ ] 4.2 [P] [Tool: sonnet] 同檔對「標記出貨」頁面的 `<tr v-for="item in mergedMarkShippedItems">` 區段（line 約 613-650）做同樣處理，保持兩處 UI 一致。
- [ ] 4.3 [Tool: claude] 主對話跑 `composer test` 全套迴歸，確認沒新 fail。驗證：347+4=351 全綠（既有 + 新增 4 個）。

## 5. Cross-impact 驗證（A 壞 B 檢查，用戶要求）

- [ ] 5.1 [Tool: sonnet] 派 Sonnet 子代理跑 cross-impact 分析：grep `mergeItemsByProduct`、`get_shipment_items`、`shipment_items`、`variation_title` 在整個 codebase 的所有 caller / consumer，確認本 change 改動的 4 個檔案外**沒有**第三方 code path 會被破壞。產出報告含：(a) 所有 caller 列表 (b) 每個 caller 受影響評估 (c) 潛在 A 壞 B 警告。若發現未列入本 change 範圍的 caller 會被破壞，**STOP 並要求新增任務或調整 design.md**，不可繼續往下。驗證：報告寫入 `/tmp/cross-impact-shipment.md`，主對話 review 後決定是否進任務 5.2。
- [ ] 5.2 [Tool: claude] 主對話 review cross-impact 報告。若 OK 標記 5.1 完成；若有破壞風險，新增任務或開新 change 處理，不可硬上。

## 6. 線上驗收 + bump 版本 + PR

- [ ] 6.1 [Tool: claude] 線上手動驗收：以 sshpass 連 buygo.me，部署 plugin 後開啟 `https://buygo.me/buygo-portal/shipments/?view=detail&id=420`（或對應 URL）：
  - 「合併顯示中」狀態：父行「【預購】Kitty 可麗餅吊飾 × 4」+ 3 個縮排子列各顯示 variation_title 與數量；
  - 按「列印」→ 瀏覽器列印預覽 DOM 含子列（cross-impact 報告 F：codebase 無任何 `@media print` 規則，子列預期會印出來。如果客戶要求列印不要顯示子列，後續調整為加 `print:hidden` class，本 change 預設顯示）；
  - 切「展開顯示中」→ 4 行扁平 list（無縮排）；
  - 「標記出貨」流程同樣對 id=420 開啟，確認子列在標記出貨頁也顯示且不破壞「下單／已配／待配」欄位（cross-impact 報告 B 的對策驗收）。
  - 截圖 4 張存到 PR 描述。
- [ ] 6.2 [Tool: claude] bump 版本（patch +0.0.1，L060）：改 `buygo-plus-one.php` 兩處（`Version:` header + `BUYGO_PLUS_ONE_VERSION` define）+ `package.json` 的 `version`。驗證：三處版號一致。
- [ ] 6.3 [Tool: claude] Conventional Commit + push + 開 PR：`feat(shipment): expand variation breakdown in shipment detail modal`。Base = main。PR 描述含 cross-impact 報告連結與 3 張截圖。驗證：PR 開好、CI 全綠、`spectra validate fix-shipment-details-expand-variations` 通過。

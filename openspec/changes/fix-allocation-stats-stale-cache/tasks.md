<!--
TDD 模式（.spectra.yaml: tdd=true）：先寫紅燈測試（任務 1）→ 再寫實作（任務 2）。
Parallel（.spectra.yaml: parallel_tasks=true）：同任務群組內 [P] 可並行；不同群組串行。
工具標記（routing）：[Tool: copilot] / [Tool: sonnet] / [Tool: claude] / [Tool: kimi]。
-->

## 1. Red — 失敗測試先行

- [ ] 1.1 [Tool: sonnet] 建立 `tests/Unit/Api/ProductsApiStatsTest.php` 骨架（含 namespace、`use PHPUnit\Framework\TestCase`、與既有 `tests/Unit/` 目錄結構一致）。驗證：`composer test -- --filter ProductsApiStatsTest` 能找到測試類別並執行（無致命錯誤）。
- [ ] 1.2 [P] [Tool: sonnet] 撰寫測試 `test_reserved_subtracts_allocated`：以 ordered=10、purchased=10、allocated=4 輸入呼叫待測的純函數，斷言 `reserved === 6`。預期此測試在實作未動前 fail（因列表公式漏扣 allocated）。驗證：跑測試出現 red 並印出 expected 6 vs actual 0。
- [ ] 1.3 [P] [Tool: sonnet] 撰寫測試 `test_reserved_floor_at_zero`：以 ordered=10、purchased=10、allocated=12 輸入，斷言 `reserved === 0`（不可為負）。驗證：紅燈，且錯誤訊息顯示 actual=-2 或 0 視實作而定。
- [ ] 1.4 [P] [Tool: sonnet] 撰寫測試 `test_list_and_single_product_reserved_match`：對表格輸入 [(0,0,0),(10,5,0),(10,10,0),(10,10,4),(10,10,12),(2,2,2)] 依序餵入兩個公式（list 與 single），斷言相等且等於 `max(0, ordered-purchased-allocated)`。驗證：紅燈，至少一組顯示不一致。
- [ ] 1.5 [P] [Tool: sonnet] 撰寫測試 `test_no_transient_calls_in_list_endpoint`：以 `file_get_contents` 讀 `includes/api/class-products-api.php` 內容，斷言不含 `get_transient('buygo_products_` 與 `set_transient('buygo_products_` 兩個 substring。驗證：紅燈（目前兩處字串存在）。
- [ ] 1.6 [Tool: claude] 主對話跑 `composer test -- --filter ProductsApiStatsTest` 確認 1.2–1.5 全紅，將 fail 輸出貼到本 change 的 `tasks.md` 註解（或附在 commit message）作為紅燈證據。驗證：四個測試皆 status=failed 才能進任務 2。

## 2. Green — 拔快取 + 統一公式

- [ ] 2.1 [Tool: copilot] 滿足需求「Products List Endpoint MUST NOT Cache Stat Fields」之讀取側：在 `includes/api/class-products-api.php` 刪除列表 endpoint 的 `get_transient('buygo_products_...')` 讀取分支（含 cache hit 後直接 return 的 if 區塊）。驗證：grep 該檔不再含 `get_transient('buygo_products_`，且 `test_no_transient_calls_in_list_endpoint` 部分通過（get 部分）。
- [ ] 2.2 [Tool: copilot] 滿足需求「Products List Endpoint MUST NOT Cache Stat Fields」之寫入側：同檔刪除列表 endpoint 結尾的 `set_transient('buygo_products_..., 30)` 寫入。驗證：grep 該檔不再含 `set_transient('buygo_products_`，`test_no_transient_calls_in_list_endpoint` 完全綠燈。
- [ ] 2.3 [Tool: copilot] 滿足需求「Reserved Quantity Formula MUST Be Consistent Across Endpoints」：修改列表 endpoint 內每個商品的 reserved 計算為 `max(0, ($product['ordered'] ?? 0) - ($product['purchased'] ?? 0) - $allocated)`，與單品 endpoint 公式一致；保留欄位名稱與型別不變。驗證：`test_reserved_subtracts_allocated`、`test_reserved_floor_at_zero`、`test_list_and_single_product_reserved_match` 三個測試由紅轉綠。
- [ ] 2.4 [Tool: claude] 主對話跑 `composer test -- --filter ProductsApiStatsTest` 確認 1.2–1.5 全綠，再跑 `composer test` 全套迴歸。驗證：兩次測試全綠（exit code 0），無新失敗測試出現。

## 3. Refactor & 驗收

- [ ] 3.1 [Tool: kimi] Code Review：派 Kimi CLI 對 `includes/api/class-products-api.php` 的 diff 做 review，聚焦（a）是否誤刪其他端點共用邏輯（b）是否引入 N+1 query。驗證：Kimi 回報無 Critical issue；若有則回任務 2 修正再 review。
- [ ] 3.2 [Tool: claude] 在 buygo.me 線上站手動驗收（用 agent-browser 或 SSH 驗證）：對商品 ID=1055 確認分配後列表頁立即顯示 `已分配=2、待分配=0`，與分配詳情頁一致。驗證：兩個畫面數值一致並截圖存檔；如線上站不便測，至少於本機 Local 環境重現原 bug 並確認修復。
- [ ] 3.3 [Tool: claude] 更新 `buygo-plus-one.php` 與 `package.json` 的 Version（patch +0.0.1，遵循 L060），更新 plugin header `Version:` 與 `define('..._VERSION', ...)`。驗證：三處版號一致，`git diff` 顯示三行變動。
- [ ] 3.4 [Tool: claude] Conventional Commit + push：`fix(allocation): products API list endpoint drop transient cache and align reserved formula`。push 到 `fix/allocation-stats-stale-cache` 分支，開 PR。驗證：PR 已開、CI 全綠、`spectra validate fix-allocation-stats-stale-cache` 通過。

## Context

BuyGo+1 的分配系統負責將客戶訂單中的商品數量拆分成子訂單（type='split'）。核心流程：

1. 賣家在後台點「分配」→ 打 POST /products/allocate API
2. `AllocationWriteService::updateOrderAllocations()` 驗證數量上限 → 建立子訂單
3. 子訂單建立後更新 `line_meta._allocated_qty` 和 `wp_postmeta._buygo_allocated`

當前問題：多變體商品在實際操作中出現數量疊加、比例不對的情況，涉及六個獨立的代碼缺陷，跨 AllocationWriteService 和 OrderService 兩個 Service。

## Goals / Non-Goals

**Goals:**
- 消除並發分配導致的雙重建單風險
- 確保取消子訂單後額度正確歸還
- 確保出貨後 `_buygo_allocated` post meta 與實際子訂單數量一致
- 確保 splitOrder 在 Transaction 保護下執行
- 將 `_allocated_qty` 改為重算邏輯，消除累減漂移風險

**Non-Goals:**
- 不重構整體分配架構（保持現有 Service 邊界）
- 不修改前端防抖邏輯（後端鎖是根本解法，前端防抖可作後續 enhancement）
- 不做歷史資料批量修復（由既有 `fix-multi-variant-allocation-cross-contamination` change 負責）
- 不修改 API 介面（入參出參不變，只修內部邏輯）

## Decisions

### Decision 1: 分配排他鎖用 MySQL GET_LOCK

**選擇**：在 `AllocationWriteService::updateOrderAllocations()` 入口用 `GET_LOCK('buygo_allocate_{product_id}', 10)` 取得排他鎖，離開時 `RELEASE_LOCK()`。

**替代方案**：
- `SELECT ... FOR UPDATE`：需要鎖定 fct_order_items 的多筆 row，鎖粒度太大，可能影響其他讀取操作
- PHP `flock()`：單機鎖，不適用多 PHP-FPM worker 或未來多機部署
- WordPress transient 鎖：非原子操作，有 race window

**理由**：GET_LOCK 是 MySQL 原生的 named lock，粒度可控（per product_id），不影響其他表操作，10 秒 timeout 足夠正常分配完成但不會讓用戶等太久。

**影響 Service**：AllocationWriteService（includes/services/class-allocation-write-service.php）

### Decision 2: splitOrder 過濾已取消子訂單

**選擇**：在 `OrderService::splitOrder()` 的已拆分數量查詢（行 726-736）加入 `AND o.status NOT IN ('cancelled', 'refunded')` 條件。

**替代方案**：
- 在取消子訂單時立即重算額度：會增加 cancel 流程的複雜度，且歷史取消動作沒有觸發重算
- 新增 `recalcSplitQuantity()` 獨立方法：過度抽象，只有一處調用

**理由**：最小改動原則。`AllocationWriteService` 的 per-item 驗證（行 124-134）已正確過濾，只有 `splitOrder` 漏了。統一邏輯即可。

**影響 Service**：OrderService（includes/services/class-order-service.php）

### Decision 3: 出貨後同步重算 _buygo_allocated

**選擇**：在 `OrderService::shipOrder()` 完成出貨邏輯後，呼叫現有的 `AllocationWriteService::recalcAllocatedMeta($product_id)` 重算 post meta。

**替代方案**：
- 用 WordPress hook `do_action('buygo_after_ship')` 解耦：增加複雜度，目前只有一處需要
- 在前端讀取時即時計算（不存 meta）：會增加商品列表頁的 DB 查詢量

**理由**：`AllocationWriteService` 已有重算邏輯（行 177-185），直接呼叫，不重複寫。

**影響 Service**：OrderService（呼叫端）、AllocationWriteService（被呼叫端，不修改）

### Decision 4: splitOrder 加 Transaction 保護

**選擇**：在 `OrderService::splitOrder()` 方法的核心邏輯用 `$wpdb->query('START TRANSACTION')` / `COMMIT` / `ROLLBACK` 包裹。

**範圍**：從 INSERT fct_orders 到 INSERT fct_order_items 到更新 line_meta 的整段，任一步驟失敗即 ROLLBACK。

**理由**：防止並發場景下部分寫入成功導致孤兒訂單。

**影響 Service**：OrderService（includes/services/class-order-service.php）

### Decision 5: _allocated_qty 改用重算取代累減

**選擇**：`OrderService::shipOrder()` 中更新 `_allocated_qty` 時，改用 `SUM(child_oi.quantity) WHERE child_o.status NOT IN ('cancelled', 'refunded', 'shipped')` 重算，取代現有的 `current - shipped_qty` 累減。

**替代方案**：
- 保持累減但加驗證（若結果 < 0 則重算）：半套方案，不如直接重算
- 獨立 cron 定期重算：延遲太大，用戶會看到暫時不一致

**理由**：重算是 O(n) 查詢（n = 該訂單的子訂單數，通常 < 20），效能可接受。消除所有累減漂移風險。

**影響 Service**：OrderService（includes/services/class-order-service.php）

### Decision 6: legacy object_id=0 fallback 加嚴格比對

**選擇**：`AllocationWriteService` 處理 `object_id=0` 的舊資料時，若商品 `has_variations=true` 且有多個 variation，拒絕模糊 match 並回傳錯誤訊息要求指定 variation_id。

**替代方案**：
- 自動選 default_variation：可能配錯
- 按 variation 順序取第一個有庫存的：隱含邏輯，難除錯

**理由**：明確拒絕比隱含猜測安全。舊格式資料量少，強制指定不會影響正常流程。

**影響 Service**：AllocationWriteService（includes/services/class-allocation-write-service.php）

## Risks / Trade-offs

| 風險 | 緩解措施 |
|------|---------|
| GET_LOCK 在高並發下可能讓請求排隊等待 10 秒 | 10 秒 timeout 後回傳錯誤而非無限等待；正常分配 < 1 秒，排隊只在真正並發時發生 |
| splitOrder 加 Transaction 可能影響效能 | Transaction 範圍限定在核心寫入段（< 50ms），不包含驗證查詢 |
| legacy object_id=0 拒絕可能影響現有工作流 | 只在 has_variations=true 且多 variation 時拒絕；單 variation 或 has_variations=false 的商品不受影響 |
| 重算 _allocated_qty 多一次 DB 查詢 | 查詢只涉及單一訂單的子訂單（通常 < 20 筆），效能影響可忽略 |

## Open Questions

- 無（六個問題的修復方案已確定，待 Fish 確認計畫後開始實作）

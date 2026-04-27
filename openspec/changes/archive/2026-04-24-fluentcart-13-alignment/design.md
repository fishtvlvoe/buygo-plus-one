## Context

FluentCart 於 2026-04-20 起連續四週釋出 1.3.19 → 1.3.22，每版都觸及 BuyGo+1 既有 Service 的範圍：

| FluentCart 版本 | 發布日 | 關鍵新增 |
|---|---|---|
| 1.3.19 | 2026-04-20 | 變體編輯新 UI、**Packing 物件**（名稱/尺寸/重量/運費重量）、產品清單篩選 |
| 1.3.20 | 2026-04-21 | EDD 遷移工具 |
| 1.3.21 | 2026-04-22 | **Cloudflare R2 / S3 公開存取** |
| 1.3.22 | 2026-04-23 | **進階庫存管理（Pro）**：批量更新、調整歷史、舊值/新值/原因/備註/操作者 Ledger、專用 API |

BuyGo+1 現況（靜態程式碼分析）：

- **AllocationService**（1024 行）— 自管 `_allocated_qty` line_meta、無結構化歷史、扣減/釋放需手動計算
- **ShippingStatusService / OrderShippingManager** — 自算重量、自管出貨狀態機
- **LineFlexTemplates** — 運送文案自拼字串，硬編碼欄位
- **LineProductCreator / LineProductUploadHandler** — 本機 WP media 上傳，無 CDN
- **BatchCreateService** — 自造批次建單管線

**利益相關者**：
- Fish（架構決策）
- BuyGo+1 部署站（核流有限公司、若干試用賣家）
- FluentCart 核心 / Pro 授權持有狀態（**未確認**）

## Goals / Non-Goals

**Goals:**

- 為每個候選項目產出「現況程式碼 vs 官方機制」靜態對照表
- 列出每個項目的遷移前置條件（Pro 授權、最低 FluentCart 版本、資料遷移需求）
- 產出 ROI 排序（減少行數 / 遷移成本 / 風險）決定實作優先順序
- 標記所有需實機驗證的 Open Questions，避免盲目實作

**Non-Goals:**

- 不產出可執行的遷移腳本（只給策略描述）
- 不做效能實測（沒有對照基準）
- 不涵蓋 FluentCart 1.3.18 以前的變更
- 不處理非 FluentCart 的整合（LINE、LIFF、自訂支付）
- 不評估向後相容性（假設可升級到最新 FluentCart）

## Decisions

### D1：AllocationService vs FluentCart Pro Inventory Ledger — 最高優先評估

**現況**：
- `AllocationService::reserve()` / `release()` 手動更新 `fct_order_items.line_meta` 的 `_allocated_qty`
- 無調整歷史表，賣家無法查「為什麼庫存少 3 件」
- 1024 行含大量 race condition 防護、批次釋放邏輯

**FluentCart 1.3.22 機制**（**Pro only**）：
- 內建 Stock Ledger：`old_value` / `new_value` / `reason` / `notes` / `operator_id`
- 批量更新 API、調整歷史查詢 API（SQL 優化）
- 專用 WP Admin 子選單

**候選策略**：
- A. 完全替換：`reserve/release` 改呼叫 FluentCart Pro API，自製 ledger 表退場（**減少 ~300 行**）
- B. 併存：BuyGo 管「分配狀態」，FluentCart 管「實際庫存異動」，ledger 兩邊都寫（減少 ~100 行，複雜度上升）
- C. 不動：只接 Pro 的 `inventory_adjusted` hook 做審計追蹤，不改 reserve/release 邏輯

**決策邏輯**：A 的 ROI 最高但**前置條件最多**（需 Pro 授權 + API 規格公開）。評估報告需給出決策樹，而非單一建議。

**替代方案評估**：
- **WooCommerce 遷移**：被否決。全專案綁 FluentCart，遷移成本數月起跳
- **自己實作 Ledger 表**：被否決。與官方路線圖衝突，6 個月內必被淘汰

### D2：ShippingStatusService 對齊 FluentCart Packing 物件

**現況**：
- `ShippingStatusService` 自管出貨狀態機（pending / partial / completed）
- `OrderShippingManager` 自算訂單總重量（累加 line item）
- 重量欄位在 BuyGo 自有 meta，未同步 FluentCart

**FluentCart 1.3.19+ 機制**：
- Product 層級的 `packing` 物件：name / dimensions / product_weight / shipping_weight
- Checkout / Email 顯示快照（升級後不會變動）
- FSE 佈景主題 block 可直接渲染

**候選策略**：
- A. 讀 `$order->packing` 取代自算重量，LINE / Email 改讀官方欄位（**減少 ~150 行**）
- B. 保留 BuyGo 自算，但與 FluentCart packing 雙向同步（高複雜度，不推薦）

**決策邏輯**：A 方案依賴 `$order->packing` 在 `type='split'` 子訂單上是否同步存在 — 必須實機驗證。

### D3：LINE Flex Template 改吃 packing 欄位

**現況**：LineFlexTemplates 硬編碼「重量：{x} kg、尺寸：{y}」組字串，欄位改動需修改模板

**FluentCart 1.3.19+ 機制**：`$order->packing->name` 可直接顯示「宅配-小箱」等語意標籤

**候選策略**：Flex Template 直接讀 `packing` 物件（**減少 ~50 行** + UX 一致性提升）

**決策邏輯**：低風險、低 ROI，但可搭在 D2 的遷移 wave 一起做，邊際成本趨近 0

### D4：LINE 產品圖片改走 R2/S3 外部 URL

**現況**：`LineProductUploadHandler` 把 LINE OA 收到的圖下載到 WP media，產出 attachment_id

**FluentCart 1.3.21 機制**：商品圖可直接掛 R2/S3 公開 URL，繞過 WP media

**候選策略**：
- A. 圖片上傳改呼叫 FluentCart 的 S3/R2 代理 API，WP media 不留（**減少 ~80 行**）
- B. 保留 WP media 為主，R2 當備援 CDN（不省行數，只換協定）

**決策邏輯**：前置條件為「部署站願意付 R2/S3 成本」。評估報告需標記這是商業決策，非技術決策。

### D5：BatchCreateService — DEFER 至 1.3.23+

**理由**：FluentCart 1.3.20 僅推出「EDD 遷移工具」，尚未開放通用批次建單 API。強行對齊會做白工。

**動作**：評估報告標 `DEFER`，加到 `memory/failure-patterns/` 當監控點，等官方 release notes 再回來評估。

## Risks / Trade-offs

| 風險 | 機率 | 影響 | 緩解 |
|---|---|---|---|
| FluentCart 1.3.22 官方 docs 延遲數週 | 高 | 評估報告結論不精準 | 直接讀 FluentCart plugin 原始碼 + `dev.fluentcart.com` 交叉驗證 |
| 部署站無 FluentCart Pro 授權 | 中 | D1 的 A 策略整個失效 | 評估報告分「Pro 情境」/「Free 情境」兩版結論 |
| `$order->packing` 在 split 子訂單不同步 | 中 | D2/D3 失效 | tasks.md 加實機驗證步驟，失敗則回退 D2 B 策略 |
| 未來 FluentCart 改動 packing schema | 低 | D2/D3 的程式碼再次需改 | 遷移時在 BuyGo 側加薄轉接層（adapter pattern） |
| 評估報告變質為實作文件 | 中 | 違反 Non-Goals | tasks.md 明確禁止產出 src/ 變更；archive 時只收 Markdown |

## Migration Plan

**本 change 無遷移**（純評估，不改程式碼）。

**後續實作 change 的排序建議**（評估報告需具體化）：

1. 先驗證 Open Questions（Pro 授權、packing schema）
2. 若通過 → 開 `migrate-allocation-to-fc-ledger`（D1）
3. 同時開 `align-shipping-with-packing`（D2+D3 合併）
4. 最後評估 `line-media-to-r2`（D4，視商業決策）

**Rollback**：評估 change 無需 rollback。若 Fish 不採納結論，直接 archive 即可。

## Open Questions

1. **BuyGo+1 部署站是否持有 FluentCart Pro 授權？**（影響 D1 最高 ROI 項是否可行）
2. **FluentCart 1.3.22 的進階庫存 API 端點 URL / filter hook 具體名稱為何？**（需讀 plugin 原始碼驗證）
3. **`$order->packing` 是否在 `type='split'` 子訂單自動繼承？** 還是只存在父訂單？（影響 D2/D3）
4. **FluentCart 對 `ProductVariation::update_stock()` 的行為在 1.3.22 是否有變動？**（AllocationService 直接呼叫此方法）
5. **R2/S3 公開存取是否支援 LINE OA 送來的動態圖片？**（影響 D4 可行性）

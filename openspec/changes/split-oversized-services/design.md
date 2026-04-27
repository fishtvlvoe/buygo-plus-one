## Context

AllocationService（1026 行）和 ProductService（946 行）嚴重違反專案 300 行上限規範。兩個 class 各自承擔讀取、寫入、計算等多種職責，造成修改風險高、測試困難。

現有呼叫端包含：
- `includes/api/` 下的 15+ REST API 檔案直接依賴 AllocationService 和 ProductService
- 測試目錄：`tests/services/AllocationServiceTest.php`、`tests/services/ProductServiceTest.php`

## Goals / Non-Goals

**Goals:**
- 拆分後每個新 Service class ≤ 300 行
- 保留 AllocationService 和 ProductService 作為 facade，所有現有呼叫端零改動
- 測試跟著方法走，分散到對應的新 Service 測試
- autoload 無需修改（facade 保留原類名）

**Non-Goals:**
- 不遷移 API 層呼叫端（facade 提供向後相容）
- 不移除 facade class（留待後續 change）
- 不處理其他超標 Service

## Decisions

### Facade 過渡模式取代直接遷移

**決策**：保留原有 class（AllocationService、ProductService）作為委派 facade，新 Service 承載實際邏輯。

**理由**：API 層有 15+ 呼叫端，若直接遷移需一次改動範圍過大，風險高。Facade 讓拆分可獨立部署，呼叫端遷移可分批進行。

**反向選項**：直接遷移所有呼叫端 → 拒絕，一次改 15+ 檔案，單一 PR 難以 review。

### AllocationService 拆分邊界：讀取 / 寫入 / 計算

**決策**：
- `AllocationQueryService`：`getAllVariationIds()`、`getProductOrders()`
- `AllocationWriteService`：`updateOrderAllocations()`、`cancelChildOrder()`
- `AllocationCalculator`：`validateAdjustment()`、`adjustAllocation()`

**理由**：三類職責邊界清晰，QueryService 純讀資料庫、WriteService 有副作用、Calculator 為純計算邏輯（可單元測試，無 DB 依賴）。

**反向選項**：讀寫合併為一個 ReadWriteService → 拒絕，仍會超過 300 行。

### ProductService 拆分邊界：查詢 / 寫入 / Variation

**決策**：
- `ProductQueryService`：`getProductsWithOrderCount()`、`getProductById()`、`getProductBuyers()`、`canAddProduct()`、`canAddImage()`、`isVariableProduct()`
- `ProductWriteService`：`updateProduct()`
- `ProductVariationService`：`getVariations()`、`deleteProductPost()`、`getVariationStats()`、`getVariationMeta()`、`updateVariationMeta()`

**理由**：Variation 相關方法有獨立的職責域，單獨成 class 結構最清晰。ProductWriteService 雖然目前只有一個 public 方法（`updateProduct` 內有大量私有邏輯），預計拆出後約 200-280 行，符合規範。

**反向選項**：Query + Write 合併 → 拒絕，getProductsWithOrderCount 單一方法就有 230+ 行，加上 Write 必超標。

### 測試拆分策略：測試跟著方法走

**決策**：
- `AllocationQueryServiceTest.php`：原 AllocationServiceTest 的查詢相關 case
- `AllocationWriteServiceTest.php`：寫入相關 case
- `AllocationCalculatorTest.php`：計算相關 case
- `ProductQueryServiceTest.php`、`ProductWriteServiceTest.php`、`ProductVariationServiceTest.php`：同上策略

**理由**：測試與實作保持相同結構，方便定位失敗的 case。

## Risks / Trade-offs

- [風險] Facade 委派若傳遞 `$this` 相關狀態，可能需要注入自身依賴 → 緩解：新 Service 使用相同 constructor 注入模式，facade 持有新 Service 實例並委派
- [風險] getAllVariationIds 和 getProductOrders 是 AllocationService 最大的兩個方法（分別約 78 行和 118 行），拆出後 AllocationQueryService 預估約 220 行，符合規範
- [風險] updateOrderAllocations 是 AllocationService 最大的 private 邏輯區（行 233-633，約 400 行），該方法本身超標 → 緩解：本 change 只做 class 拆分，方法內部不重構；AllocationWriteService 整體預估 450 行，仍超標 → 此為已知限制，在 tasks.md 中標記，需後續 change 處理

## Migration Plan

1. 建立 6 個新 Service class（AllocationQueryService、AllocationWriteService、AllocationCalculator、ProductQueryService、ProductWriteService、ProductVariationService）
2. 將對應方法從原 class 移入新 class
3. 原 AllocationService、ProductService 改為委派 facade
4. 建立對應測試 class，遷移相關測試 case
5. 跑 `composer test` 確認全部通過
6. 用 `wc -l` 確認新 Service 行數符合規範

**Rollback**：facade 保留原接口，若新 Service 有問題只需還原 facade 委派邏輯即可。

## Open Questions

- AllocationWriteService 的 `updateOrderAllocations` 方法本身約 400 行，是否在本 change 同步拆解？→ 決策：不拆，本 change 只做 class 拆分，方法內部重構留待後續

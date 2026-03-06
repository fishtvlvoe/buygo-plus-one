# Phase 59: 提交與結果回饋 - Context

**Gathered:** 2026-03-03
**Status:** Ready for planning

<domain>
## Phase Boundary

賣家在批量表單填寫完成後，可提交批量上架請求，得到明確的成功或失敗結果，並能在失敗時修改重試。包含：提交按鈕（手機版底部固定欄 + 桌面版右上角）、呼叫 POST /products/batch-create API、三種結果回饋（全部成功/部分失敗/全部失敗）、桌面版提示文字。

</domain>

<decisions>
## Implementation Decisions

### 提交成功後的跳轉體驗
- 全部成功時使用 toast 通知「成功上架 N 個商品」，同時跳回商品列表頁
- 跳回列表頁後回到頁面頂部，不特別定位新商品
- 提交期間按鈕 disabled + spinner 動畫 + 文字改為「上架中...」
- 網路錯誤（API 完全無回應）時顯示錯誤提示 + 重試按鈕，保留表單資料，按鈕回復可點擊

### 部分失敗的展示方式
- 頂部顯示摘要 toast：「成功 N 個，失敗 M 個」
- 失敗的卡片（手機版）/ 行（桌面版）加紅色邊框標記
- 每個失敗商品下方顯示後端回傳的原始錯誤訊息
- 自動移除已成功上架的商品，表單只保留失敗的商品
- 賣家修改後可再次點擊「批量上架」重新提交（只送剩餘的失敗商品）

### 全部失敗的處理
- 頂部紅色 toast 顯示「上架失敗：[錯誤原因]」
- 保留所有已填寫資料，按鈕回復可點擊
- 賣家可修改後直接重試

### 手機版底部固定欄布局
- 左側顯示「N 件商品」文字（有效商品計數）
- 右側顯示「批量上架」藍色按鈕
- 類似購物車底部欄的常見模式（左資訊 + 右 CTA）
- 有效商品計數 = 商品名稱和售價都已填寫的商品數量
- 有效商品數為 0 時，按鈕灰色 disabled

### 桌面版按鈕位置
- 「批量上架 (N)」藍色按鈕放在「匯入 CSV」按鈕右側，同一行
- N 顯示有效商品數量，計算方式同手機版

### 提示文字
- 桌面版：表格下方（「+ 新增商品列」按鈕下方），淡灰色小字
- 完整文字：「商品名稱和售價為必填。數量填 0 代表無限上架。支援 CSV 匯入（欄位：名稱、售價、數量、描述）。」
- 手機版：省略提示文字，保持簡潔

### Claude's Discretion
- Toast 的具體動畫和顯示時長
- 紅色邊框的精確樣式（色調、粗細）
- 成功跳轉的延遲時間（如有）
- 錯誤重試按鈕的具體樣式
- spinner 的動畫樣式

</decisions>

<code_context>
## Existing Code Insights

### Reusable Assets
- `bp-toast` / `bp-toast-success` / `bp-toast-error`：既有的提示訊息樣式，可直接用於成功/失敗回饋
- `useBatchCreate.js` composable：已有完整的表單狀態管理（items CRUD、配額進度、CSV 匯入），需在此基礎上擴充提交邏輯
- `useApi()` composable：提供 `get()` 方法呼叫 REST API，需確認是否有 `post()` 方法
- 底部固定欄樣式：數量選擇步驟已有 `sticky bottom-0` 的底部按鈕欄位，可參考其結構
- `bp-delete-btn` hover 效果：已有的按鈕互動樣式模式

### Established Patterns
- Vue 3 Composition API + 全域函式（非 ES6 module），WordPress 環境相容
- 行內 CSS（透過 PHP include 繞過 InstaWP WAF）
- 響應式斷點 768px：`.bp-mobile-only` / `.bp-desktop-only`
- `BuyGoRouter.spaNavigate('products')` 用於 SPA 頁面跳轉

### Integration Points
- `POST /wp-json/buygo-plus-one/v1/products/batch-create`：已完成的後端 API
  - 請求格式：`{ items: [{ name, price, quantity, description }] }`
  - 回傳格式：`{ success, results: [{ index, success, product_id, error }], created, failed }`
  - 整批失敗時回傳：`{ success: false, error: '...' }`（HTTP 422 或 403）
- `BatchCreateService::batchCreate()`：後端逐筆處理，每筆獨立回報成功/失敗
- `batch-create.php` template：需新增提交按鈕和結果回饋的 HTML

</code_context>

<specifics>
## Specific Ideas

- 底部固定欄像「購物車結帳」的模式：左邊是資訊摘要，右邊是 CTA 按鈕
- 部分失敗時自動清除成功商品，減少賣家操作步驟
- 保持表單資料不遺失是最高優先：任何錯誤情境都不能清空已填寫的資料

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 59-submit-and-feedback*
*Context gathered: 2026-03-03*

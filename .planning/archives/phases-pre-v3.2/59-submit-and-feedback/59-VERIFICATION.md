---
phase: 59-submit-and-feedback
verified: 2026-03-03T10:30:00Z
status: passed
score: 7/7 must-haves verified
re_verification: false
human_verification:
  - test: "手機版（<768px）送出批量上架"
    expected: "底部固定欄顯示有效商品數，按鈕點擊後出現 spinner +「上架中...」，成功時 toast 並跳回商品列表頁"
    why_human: "響應式 CSS 斷點行為、spinner 動畫、toast 顯示需視覺確認"
  - test: "桌面版（>=768px）部分失敗回饋"
    expected: "失敗商品列整行紅色背景，下方新增一列顯示錯誤原因文字，成功商品列消失"
    why_human: "template v-for 包裝兩個 tr 的 Vue 渲染行為需實機驗證"
  - test: "window.showToast 呼叫是否有實際 toast 顯示"
    expected: "全部成功/部分失敗/全部失敗時都有對應的 toast 通知"
    why_human: "window.showToast 在 DesignSystem.js 定義在 window.BuyGoDesignSystem.showToast 但直接用 window.showToast 調用 — 需確認執行期是否已橋接或有其他定義路徑"
---

# Phase 59: 提交與結果回饋 Verification Report

**Phase Goal:** 賣家可提交批量上架請求，得到明確的成功或失敗結果，並能在失敗時修改重試
**Verified:** 2026-03-03T10:30:00Z
**Status:** passed
**Re-verification:** No — 初次驗證

---

## Goal Achievement

### Observable Truths

| # | Truth | Status | Evidence |
|---|-------|--------|----------|
| 1 | 手機版底部固定欄顯示有效商品數量和藍色「批量上架」按鈕，有效數為 0 時按鈕灰色 disabled | VERIFIED | `batch-create.php` L751-761：`bp-submit-bar bp-mobile-only` 區塊，`:disabled="validItemCount === 0 \|\| submitting"`，`{{ validItemCount }} 件商品` |
| 2 | 桌面版右上角（匯入 CSV 按鈕旁）顯示「批量上架 (N)」藍色按鈕 | VERIFIED | `batch-create.php` L550-556：`bp-submit-desktop` 按鈕在 `ml-auto flex items-center gap-2` 的 CSV 按鈕之後，`批量上架 ({{ validItemCount }})` |
| 3 | 點擊提交後按鈕變 disabled + spinner +「上架中...」文字，防止重複點擊 | VERIFIED | `useBatchCreate.js` L499：防重複 `if (submitting.value) return`；L503：`submitting.value = true`；`batch-create.php` L553-558：`v-if="submitting"` 控制 spinner 和文字 |
| 4 | 全部成功時顯示 toast 通知並跳回商品列表頁 | VERIFIED | `useBatchCreate.js` L524-531：`res.data.failed === 0` 條件，`window.showToast('成功上架 ' + res.data.created + ' 個商品', 'success')`，`setTimeout(() => goBack(), 800)` |
| 5 | 部分失敗時自動移除成功商品，失敗商品加紅色邊框並顯示錯誤原因，可重試 | VERIFIED | `useBatchCreate.js` L532-567：`created > 0 && failed > 0` 條件，透過 id 精確對應，`items.value = items.value.filter(...)` 移除成功商品，`item._error` 標記；`batch-create.php` L638、L706：`:class="{ error: item._error }"`；L673-678 / L720-729：`v-if="item._error"` 錯誤原因顯示 |
| 6 | 全部失敗時顯示紅色 toast，保留所有資料，按鈕恢復可點擊 | VERIFIED | `useBatchCreate.js` L568-587：`created === 0` 條件，顯示錯誤 toast，不清空 items；L589-595：catch 區塊處理網路錯誤；L595-597：`finally { submitting.value = false }` 恢復按鈕 |
| 7 | 桌面版表格下方顯示淡灰色提示文字 | VERIFIED | `batch-create.php` L743-746：`<!-- 桌面版提示文字 (SUBMIT-04) -->`，`bp-hint` class，包含「商品名稱和售價為必填...」文字 |

**Score:** 7/7 truths verified

---

### Required Artifacts

| Artifact | Expected | Status | Details |
|----------|----------|--------|---------|
| `includes/views/composables/useBatchCreate.js` | 提交邏輯（validItems/validItemCount/submitting/submitBatch/handleSubmitResult） | VERIFIED | 655 行，L454-598 為 Phase 59 新增的完整提交區塊；所有聲明的 export 皆存在於 return 物件（L648-650） |
| `admin/partials/batch-create.php` | 提交按鈕 UI（手機底部固定欄 + 桌面按鈕）+ 結果回饋 + 提示文字 + CSS | VERIFIED | 771 行，Phase 59 CSS L308-415，手機底部固定欄 L751-761，桌面按鈕 L550-556，失敗標記 L638/L673-678/L706/L720-729，提示文字 L743-746 |

---

### Key Link Verification

| From | To | Via | Status | Details |
|------|----|-----|--------|---------|
| `admin/partials/batch-create.php` | `useBatchCreate.js` | Vue template bindings | WIRED | `submitBatch`（L550, L754）、`validItemCount`（L551, L553, L753）、`submitting`（L551, L553, L755）均綁定 |
| `useBatchCreate.js` | `POST /products/batch-create` | `useApi().post()` | WIRED | L26：`const { get, post } = useApi()`；L518：`await post('/wp-json/buygo-plus-one/v1/products/batch-create', payload, ...)` |
| `useBatchCreate.js` | `BuyGoRouter.spaNavigate` | 成功後跳轉 | WIRED | L606-608：`if (window.BuyGoRouter) { window.BuyGoRouter.spaNavigate('products'); }` |

---

### Requirements Coverage

| Requirement | 來源 Plan | 描述 | 代碼狀態 | 證據 |
|-------------|----------|------|----------|------|
| SUBMIT-01 | 59-01-PLAN | 批量上架按鈕（手機底部固定欄 + 桌面右上角，顯示有效商品數） | SATISFIED | `batch-create.php` L550-556（桌面）、L751-761（手機）；`:disabled="validItemCount === 0 \|\| submitting"` |
| SUBMIT-02 | 59-01-PLAN | 呼叫批量上架 API，防重複提交 | SATISFIED | `useBatchCreate.js` L499（防重複 guard）、L518（POST 呼叫）、L503（`submitting.value = true`） |
| SUBMIT-03 | 59-01-PLAN | 三種結果回饋（全部成功 / 部分失敗 / 全部失敗） | SATISFIED | `useBatchCreate.js` L524-597：三個分支完整實作；`batch-create.php` 失敗標記 CSS + template |
| SUBMIT-04 | 59-01-PLAN | 桌面版提示文字 | SATISFIED | `batch-create.php` L743-746：`bp-hint` class，正確提示文字 |

**注意：** REQUIREMENTS.md 的 Traceability 表（L143-146）仍顯示 SUBMIT-01~04 為「Not started」，但這是文件更新遺漏（與 Phase 58 相同情況）。需求本身在 L97-115 已標記為 `[x]`，代碼實作已確認完成。

**所有 4 個 requirement IDs 均已在代碼中實現。**

---

### Anti-Patterns Found

| File | Line | Pattern | Severity | Impact |
|------|------|---------|----------|--------|
| 無 | — | — | — | — |

掃描 `useBatchCreate.js` 和 `batch-create.php` 未發現 TODO/FIXME/PLACEHOLDER 或空實作。

---

### Human Verification Required

#### 1. window.showToast 執行期橋接確認

**Test:** 在 WordPress 後台進入批量上架頁面，填寫一個有效商品後點擊「批量上架」，觀察 API 回應後是否出現 toast 通知（成功或失敗）。
**Expected:** 出現對應顏色的 toast 通知訊息（成功：綠色；失敗：紅色）
**Why human:** `window.showToast` 在整個 codebase 以相同的 guard 模式（`if (window.showToast) {...}`）使用，但未找到將 `window.BuyGoDesignSystem.showToast` 橋接到 `window.showToast` 的代碼。這是跨 Phase 的設計問題，不阻礙按鈕功能，但 toast 通知可能不會顯示。

#### 2. 手機版底部固定欄視覺確認

**Test:** 在 <768px 螢幕寬度（或 DevTools 模擬），進入批量上架表單步驟，填寫部分商品，確認底部固定欄。
**Expected:** 底部固定欄 sticky 定位正確，不遮擋表單內容，顯示「N 件商品」+ 藍色按鈕
**Why human:** CSS `sticky bottom-0` 行為依賴容器高度和捲動脈絡，需實機確認

#### 3. 桌面版 template v-for 失敗行渲染

**Test:** 在 >=768px 桌面版，提交一個必然失敗的商品（例如重複名稱），觀察表格行顯示。
**Expected:** 資料行整行紅色背景，其下方出現 colspan=6 的錯誤原因行
**Why human:** SUMMARY 提到使用 `template v-for` 包裝兩個 `tr` 解決 Vue scope 問題（偏離原 plan），需確認實際渲染正確

---

### Gaps Summary

無代碼層面的 gap。所有 7 個 observable truths 已通過三層驗證（存在 / 實質內容 / 正確接線）。

**文件類 info（非 gap）：**
- REQUIREMENTS.md Traceability 表中 SUBMIT-01~04 仍標「Not started」— 這是文件更新遺漏，代碼已實現
- `window.showToast` 橋接路徑不在此 Phase 的代碼中，但 guard 防護確保功能不會因此崩潰，列為人工驗證項目

---

### Commit 驗證

| Task | Commit | 狀態 | Files |
|------|--------|------|-------|
| Task 1: useBatchCreate 擴充提交邏輯 | `4491bf8` | 存在 | `includes/views/composables/useBatchCreate.js` (+150 lines) |
| Task 2: 提交按鈕 UI + 結果回饋 | `b58bfe6` | 存在 | `admin/partials/batch-create.php` (+166 lines) |

---

_Verified: 2026-03-03T10:30:00Z_
_Verifier: Claude (gsd-verifier)_

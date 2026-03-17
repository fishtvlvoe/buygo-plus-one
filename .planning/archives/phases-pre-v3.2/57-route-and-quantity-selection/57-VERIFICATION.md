---
phase: 57-route-and-quantity-selection
verified: 2026-03-02T12:40:17Z
status: gaps_found
score: 9/10 must-haves verified
gaps:
  - truth: "「開始填寫（N 個商品）→」按鈕動態顯示數量，點擊後可進入下一步"
    status: partial
    reason: "startFilling() 目前只執行 console.log，未切換至下一步（step.value = 'form' 被註解掉），Phase 58 的表單步驟亦不存在。按鈕本身存在且動態顯示數量，但點擊後無實際效果。"
    artifacts:
      - path: "includes/views/composables/useBatchCreate.js"
        issue: "startFilling() 只有 console.log，phase 58 預留接口尚未接線"
    missing:
      - "Phase 58 完成後，將 startFilling() 中的 step.value = 'form' 解除註解，接上表單步驟"
human_verification:
  - test: "點擊商品列表頁「+ 上架」按鈕，確認 URL 切換至 /buygo-portal/batch-create/ 且無全頁重載"
    expected: "URL 更新，頁面內容切換至數量選擇頁，無白屏或全頁重載"
    why_human: "SPA 導航行為只能在瀏覽器中觀察"
  - test: "直接在瀏覽器輸入 https://[站點]/buygo-portal/batch-create/ 並存取"
    expected: "正確載入數量選擇頁，不顯示 404"
    why_human: "需要實際 WordPress 環境確認 rewrite rule 已 flush"
  - test: "選擇快選按鈕（例如 10），確認按鈕變為藍色實心；再在自訂輸入框輸入數字，確認快選按鈕恢復灰色"
    expected: "快選與自訂輸入互斥切換"
    why_human: "Vue 響應式狀態需要瀏覽器執行"
  - test: "在配額資訊區域確認顯示「剩餘配額：N 個（已用 X / 上限 Y）」"
    expected: "配額資訊從 /products/limit-check API 載入並顯示正確數字"
    why_human: "API 呼叫與真實資料顯示需人工確認"
  - test: "選擇超過配額的數量，觀察「開始填寫」按鈕狀態"
    expected: "按鈕變為灰色且禁用（cursor-not-allowed）"
    why_human: "條件渲染與 API 資料結合的互動行為"
---

# Phase 57: 路由與數量選擇 Verification Report

**Phase Goal:** 賣家可從商品列表頁進入批量上架流程，選擇要上架的商品數量後準備進入填寫
**Verified:** 2026-03-02T12:40:17Z
**Status:** gaps_found
**Re-verification:** No — initial verification

---

## Goal Achievement

### Observable Truths

| #  | Truth                                                                       | Status      | Evidence                                                                                                                    |
|----|-----------------------------------------------------------------------------|-------------|-----------------------------------------------------------------------------------------------------------------------------|
| 1  | 商品列表頁搜尋框右側顯示藍色「+ 上架」按鈕（手機版和桌面版皆有）                 | ✓ VERIFIED  | `admin/partials/products.php:78` 有 `@click="goToBatchCreate"` 藍色按鈕，位於搜尋框容器之後，在 `v-show="currentView === 'list'` 範圍內自動顯示 |
| 2  | 點擊「+ 上架」按鈕後 URL 切換到 /buygo-portal/batch-create/ 且無全頁重載       | ? UNCERTAIN | `useProducts.js:324` 呼叫 `BuyGoRouter.spaNavigate('batch-create')`，`useRouter.js:68` 路由為 `/buygo-portal/` 前綴。邏輯正確，需人工在瀏覽器確認 |
| 3  | 瀏覽器直接存取 /buygo-portal/batch-create/ 可正確載入頁面                     | ✓ VERIFIED  | `class-routes.php:41` catch-all `^buygo-portal/([a-z-]+)/?$` 已涵蓋 `batch-create`（`[a-z-]+` 可匹配含連字符路徑）；`template.php:316` `page_partials` 包含 `batch-create` |
| 4  | 返回箭頭可回到商品列表頁（SPA 導航）                                           | ✓ VERIFIED  | `batch-create.php:45` `@click="goBack"`；`useBatchCreate.js:118-121` `goBack()` 呼叫 `BuyGoRouter.spaNavigate('products')`  |
| 5  | 數量選擇頁顯示標題「要上架幾個商品？」和副標「選擇數量後，一次展開所有欄位填寫」   | ✓ VERIFIED  | `batch-create.php:68-69` 標題和副標文字完全符合                                                                              |
| 6  | 四個快選按鈕（5/10/15/20 個）可點擊，選中的以藍色實心顯示                       | ✓ VERIFIED  | `batch-create.php:73-77` `v-for="num in presetOptions"`，`:class="{ active: selectedPreset === num }"`；CSS `.batch-preset-btn.active { background: #2563EB }` |
| 7  | 自訂輸入框可輸入 1-20，輸入時自動取消快選按鈕選取狀態                           | ✓ VERIFIED  | `batch-create.php:94` `@input="onCustomInput"`；`useBatchCreate.js:59-61` `onCustomInput()` 將 `selectedPreset.value = null` |
| 8  | 頁面顯示剩餘配額資訊，選擇數量超過配額時「開始填寫」按鈕禁用                     | ✓ VERIFIED  | `batch-create.php:108` 顯示「剩餘配額：{{ remaining }} 個...」；`:disabled="!canProceed"` 正確接線；`useBatchCreate.js` `isOverQuota` 計算屬性驅動禁用狀態 |
| 9  | 「開始填寫（N 個商品）→」按鈕動態顯示數量，點擊後可進入下一步                   | ✗ PARTIAL   | 按鈕文字 `batch-create.php:139` 正確動態顯示；但 `startFilling()` 只執行 `console.log`，Phase 58 接口尚未實作，點擊後無實際頁面切換 |
| 10 | SPA 路由表已登記 batch-create 對應 BatchCreatePageComponent                   | ✓ VERIFIED  | `useRouter.js:28` `'batch-create': 'BatchCreatePageComponent'`；`template.php:339` pageComponents 已加入；`BatchCreatePage.js:17` 元件定義完整 |

**Score:** 9/10 truths verified（1 partial）

---

## Required Artifacts

| Artifact                                              | Expected                                          | Status      | Details                                                                               |
|-------------------------------------------------------|---------------------------------------------------|-------------|--------------------------------------------------------------------------------------|
| `includes/views/composables/useRouter.js`             | batch-create 路由對應 BatchCreatePageComponent    | ✓ VERIFIED  | 第 28 行：`'batch-create': 'BatchCreatePageComponent'`；第 39 行 permissions 也已加入   |
| `includes/views/template.php`                         | BatchCreatePageComponent 在 SPA 元件表中註冊       | ✓ VERIFIED  | pageComponents（339）、page_partials（316）、titles（388）三處皆已同步                  |
| `admin/partials/products.php`                         | 商品列表頁的「+ 上架」按鈕                          | ✓ VERIFIED  | 第 78 行藍色按鈕，`@click="goToBatchCreate"`，含加號 SVG + 「上架」文字                 |
| `admin/partials/batch-create.php`                     | 數量選擇頁的 Vue template                          | ✓ VERIFIED  | 162 行，含完整 UI：標題、快選按鈕、自訂輸入、配額區、底部 CTA                            |
| `includes/views/composables/useBatchCreate.js`        | 批量上架的響應式狀態和邏輯                          | ✓ VERIFIED  | 160 行，全域函式，含 quantity/quota/canProceed 等計算屬性                               |
| `admin/js/components/BatchCreatePage.js`              | BatchCreatePageComponent 定義                     | ✓ VERIFIED  | 24 行，thin shell 模式：`setup() { return useBatchCreate(); }`                        |

---

## Key Link Verification

### Plan 01 Key Links

| From                       | To                          | Via                                              | Status      | Details                                                      |
|----------------------------|-----------------------------|--------------------------------------------------|-------------|--------------------------------------------------------------|
| `admin/partials/products.php` | `useRouter.js`            | `BuyGoRouter.spaNavigate('batch-create')`        | ✓ WIRED     | `products.php:78` 按鈕 click → `useProducts.js:324` 呼叫 spaNavigate |
| `includes/views/template.php` | `admin/partials/batch-create.php` | page_partials 陣列載入                    | ✓ WIRED     | `template.php:316` `page_partials` 包含 'batch-create'，foreach 迴圈 include 實際檔案 |

### Plan 02 Key Links

| From                              | To                                               | Via                               | Status      | Details                                                                        |
|-----------------------------------|--------------------------------------------------|-----------------------------------|-------------|--------------------------------------------------------------------------------|
| `useBatchCreate.js`               | `/wp-json/buygo-plus-one/v1/products/limit-check` | fetch 取得配額（透過 useApi()）    | ✓ WIRED     | `useBatchCreate.js:100` `get('/wp-json/buygo-plus-one/v1/products/limit-check')` |
| `admin/js/components/BatchCreatePage.js` | `useBatchCreate.js`                      | `setup() return useBatchCreate()` | ✓ WIRED     | `BatchCreatePage.js:21` `return useBatchCreate()`，template 載入後全域函式可用  |

---

## Requirements Coverage

| Requirement | Source Plan | Description                                      | Status         | Evidence                                                                         |
|-------------|-------------|--------------------------------------------------|----------------|---------------------------------------------------------------------------------|
| ROUTE-01    | 57-01-PLAN  | 商品列表頁新增「+上架」按鈕（桌面 + 手機版）        | ✓ SATISFIED    | `products.php:78-84` 藍色按鈕存在，在 `v-show="currentView === 'list'"` 容器內，手機和桌面都顯示（無 hidden class） |
| ROUTE-02    | 57-01-PLAN  | 批量上架路由註冊，支援子步驟，返回箭頭可回列表        | ✓ SATISFIED    | `useRouter.js` 路由表已登記；PHP catch-all 已涵蓋；`goBack()` 導航回 products；步驟狀態 `step` ref 已就緒 |
| SELECT-01   | 57-02-PLAN  | 數量選擇頁：標題、副標、四個快選按鈕、選中藍色實心     | ✓ SATISFIED    | `batch-create.php:68-77` 標題/副標文字完全符合；`v-for` 渲染 4 個按鈕；active class 接藍色 CSS |
| SELECT-02   | 57-02-PLAN  | 自訂數量輸入框（1-20），輸入時取消快選狀態           | ✓ SATISFIED    | `batch-create.php:93-96` 有 `min=1 max=20`；`@input="onCustomInput"` 清除 selectedPreset |
| SELECT-03   | 57-02-PLAN  | 配額資訊從 API 取得，超過配額時禁用開始填寫按鈕      | ✓ SATISFIED    | `useBatchCreate.js:100` 呼叫 limit-check；`isOverQuota` 計算屬性；`:disabled="!canProceed"` |
| SELECT-04   | 57-02-PLAN  | 底部全寬藍色按鈕，動態顯示數量，點擊後進入表單頁      | ✗ PARTIAL      | 按鈕存在且動態顯示數量；但 `startFilling()` 只有 console.log，Phase 58 接口尚未連結，點擊後無實際跳轉 |

### 孤兒需求檢查

REQUIREMENTS.md 中所有 Phase 57 的需求（ROUTE-01、ROUTE-02、SELECT-01~04）皆在 Plans 中宣告，無孤兒需求。

---

## Anti-Patterns Found

| File                                 | Line | Pattern          | Severity | Impact                                         |
|--------------------------------------|------|------------------|----------|------------------------------------------------|
| `useBatchCreate.js`                  | 131  | `console.log` 只有 log，無實際跳轉 | ⚠️ Warning | startFilling() 點擊後用戶看不到反應，但此為 Phase 58 預留接口，屬已知設計決策 |

**注意事項：** `startFilling()` 的 console.log 是刻意設計（Plans 文件有說明「Phase 58 實作前暫時只記錄 log」），但從 SELECT-04 要求角度（「點擊後進入表單填寫頁」）看，此功能目前不完整。Phase 58 完成後需解除 `step.value = 'form'` 的註解。

---

## Human Verification Required

### 1. SPA 路由切換行為

**Test:** 在商品列表頁點擊「+ 上架」按鈕
**Expected:** URL 從 `/buygo-portal/products/` 變為 `/buygo-portal/batch-create/`，頁面顯示數量選擇頁，無全頁重載（導航列不閃爍）
**Why human:** SPA 導航的無重載行為只能在瀏覽器實際執行中觀察

### 2. 直接 URL 存取

**Test:** 直接在瀏覽器輸入站點 URL + `/buygo-portal/batch-create/`
**Expected:** 正確載入數量選擇頁，不顯示 404 頁面
**Why human:** 需要確認 WordPress rewrite rules 已正確 flush（PHP 路由本身正確，但需 flush 才生效）

### 3. 快選按鈕互斥行為

**Test:** 點擊「10」快選按鈕（確認藍色實心），再在自訂輸入框輸入「8」
**Expected:** 快選「10」按鈕變回灰色，自訂輸入顯示 8；「開始填寫（8 個商品）」按鈕文字正確
**Why human:** Vue 響應式狀態切換在瀏覽器才能觀察

### 4. 配額 API 資料顯示

**Test:** 進入數量選擇頁後，觀察配額資訊區域
**Expected:** 顯示「剩餘配額：N 個（已用 X / 上限 Y）」，數字與實際賣家配額吻合
**Why human:** 需要真實 API 回應和資料庫中實際的配額記錄

### 5. 超額禁用邏輯

**Test:** 如果賣家有上限（quota.limit > 0），選擇一個超過剩餘配額的數量
**Expected:** 「開始填寫」按鈕變為灰色（bg-slate-200 text-slate-400 cursor-not-allowed）
**Why human:** 需要特定的測試賬號和配額設定才能觸發

---

## Gaps Summary

Phase 57 的 9/10 must-haves 已驗證，唯一的缺口是 SELECT-04 的「點擊後進入下一步」。

**缺口說明：**
`startFilling()` 函式目前只執行 `console.log`，`step.value = 'form'` 的切換被註解掉。這是**已知的刻意設計** — Phase 57 範圍不包含 Phase 58 的表單實作，但 SELECT-04 需求明確要求「點擊後進入表單填寫頁」。

這個缺口的修補路徑清楚：Phase 58 實作表單 UI 後，只需在 `startFilling()` 中將 `step.value = 'form'` 解除註解即可。

**不影響 Phase 57 核心目標達成：**
- 賣家可以從商品列表頁進入批量上架流程 — 已完成
- 數量選擇頁面完整可互動 — 已完成（按鈕/輸入/配額皆正確）
- 路由基礎設施就緒 — 已完成
- 唯一缺口是跨 Phase 的連結，等 Phase 58 補上

**Phase 58 接手時的工作：** 解除 `useBatchCreate.js:130` 的 `// step.value = 'form'` 註解，並實作 step === 'form' 的表單 UI。

---

_Verified: 2026-03-02T12:40:17Z_
_Verifier: Claude (gsd-verifier)_

---
phase: 58-batch-form-and-csv
verified: 2026-03-03T12:00:00Z
status: passed
score: 13/13 must-haves verified
re_verification: false
gaps: []
human_verification:
  - test: "手機版（<768px）確認卡片與桌面版表格自動切換"
    expected: "手機寬度顯示 bp-card 卡片式表單；桌面寬度顯示 bp-table 表格式表單"
    why_human: "CSS 媒體查詢行為需瀏覽器實際渲染才能確認"
  - test: "拖放 CSV 檔案到上傳區，邊框是否變藍"
    expected: "拖放懸停時 .bp-csv-upload.dragging 樣式啟動（border-color #2563EB + 淡藍背景）"
    why_human: "拖放互動需要瀏覽器事件操作"
  - test: "上傳有效 CSV 後自動切回手動模式，資料填入表單"
    expected: "green toast 顯示「成功匯入 N 筆商品」，formMode 切回 manual，items 填入解析結果"
    why_human: "FileReader 實際讀取 CSV 需瀏覽器環境"
---

# Phase 58: batch-form-and-csv Verification Report

**Phase Goal:** 賣家可在響應式表單中填寫多個商品資料，也可上傳 CSV 批次填入
**Verified:** 2026-03-03
**Status:** PASSED
**Re-verification:** No — initial verification

---

## Goal Achievement

### Observable Truths

| # | Truth | Status | Evidence |
|---|-------|--------|----------|
| 1 | 手機版以卡片式顯示每個商品（標題「商品 #N」），每張卡片含名稱/售價/數量/描述欄位和刪除按鈕 | VERIFIED | batch-create.php L521-565：`.bp-card` + `v-for items` + `removeItem(item.id)` + 4 欄位 v-model |
| 2 | 桌面版以表格式顯示（# / 商品名稱 / 售價 / 數量 / 描述 / 操作），行內直接編輯 | VERIFIED | batch-create.php L567-608：`.bp-table` + `thead` 6 欄 + `v-for items` + `v-model` 綁定 |
| 3 | 兩種佈局透過 CSS 響應式斷點自動切換（<768px 卡片，>=768px 表格） | VERIFIED | batch-create.php L205-211：`@media (min-width: 768px)` 切換 `.bp-mobile-only`/`.bp-desktop-only` |
| 4 | 底部「+ 新增商品」按鈕新增空白商品；刪除按鈕可移除商品（至少保留 1 個） | VERIFIED | batch-create.php L559, 600：`@click="addItem"`；L529,589：`v-if="items.length > 1" @click="removeItem(item.id)"`；useBatchCreate.js L158-161：`if (items.value.length <= 1) return` |
| 5 | 頂部顯示配額進度（手機版文字+進度條，桌面版 badge），新增/刪除商品時即時更新 | VERIFIED | batch-create.php L422-460：桌面 badge `quotaUsed/quota.limit`；手機進度條 `bp-quota-bar-fill` + `quotaPercent%`；useBatchCreate.js L172,178：`quotaUsed = quota.current + itemCount`，`quotaPercent` computed |
| 6 | 點擊「開始填寫」按鈕後，畫面從數量選擇切換到商品表單填寫介面 | VERIFIED | useBatchCreate.js L468-472：`startFilling()` → `step.value = 'form'` + `initItems(quantity.value)`；batch-create.php L420：`v-if="step === 'form'"` |
| 7 | 手機版有「手動輸入」/「CSV 匯入」pill 按鈕可切換模式 | VERIFIED | batch-create.php L462-480：`.bp-pill-group` + `@click="setFormMode('manual')"` / `@click="setFormMode('csv')"` + `:class="{ active: formMode === 'manual' }"` |
| 8 | 桌面版有「匯入 CSV」按鈕（在配額 badge 旁邊） | VERIFIED | batch-create.php L433-441：`.bp-csv-btn` label 包裝 `<input type="file" @change="handleCsvUpload">` |
| 9 | 切換到 CSV 模式時保留已填寫的手動資料 | VERIFIED | useBatchCreate.js L211-215：`setFormMode()` 只更新 `formMode.value`，不清空 `items`；batch-create.php L521：`v-if="formMode === 'manual'"` 隱藏卡片但資料不清除 |
| 10 | CSV 上傳區支援點擊選擇檔案和拖放上傳，拖放時邊框變藍 | VERIFIED | batch-create.php L504-518：`@dragover.prevent="isDragging = true"` / `@dragleave.prevent="isDragging = false"` / `@drop.prevent="handleDrop"`；`:class="{ dragging: isDragging }"`；CSS L259 `.dragging { border-color: #2563EB }` |
| 11 | CSV 上傳後正確解析名稱/售價/數量/描述欄位，數量缺失時預設 0 | VERIFIED | useBatchCreate.js L223-302：`parseCSV()` 支援中英文表頭別名，L281：`parsedQty = qty && !isNaN(...) ? ... : '0'` |
| 12 | CSV 格式錯誤時顯示錯誤提示 | VERIFIED | useBatchCreate.js L248-253,292-295：缺少必填欄位或無有效資料時 `return { data: [], error: '...' }`；batch-create.php L494-500：`bp-toast-error` 顯示 `csvError` |
| 13 | 解析成功後填入表單並顯示匯入筆數提示，使用者可在表單中繼續修改 | VERIFIED | useBatchCreate.js L283-358：`handleCsvUpload` 解析後 `items.value = [...filledItems, ...newItems]`；L352：`csvSuccessMsg.value = '成功匯入 N 筆商品'`；L358：`formMode.value = 'manual'`（切回可編輯模式） |

**Score:** 13/13 truths verified

---

### Required Artifacts

| Artifact | Expected | Status | Details |
|----------|----------|--------|---------|
| `includes/views/composables/useBatchCreate.js` | 表單狀態管理（items 陣列 + 新增/刪除/配額進度）+ CSV 解析 + 模式切換 | VERIFIED | 506 行；匯出 items/addItem/removeItem/quotaUsed/quotaPercent/isFormOverQuota/formMode/parseCSV/handleCsvUpload/handleDrop/isDragging/csvError/csvSuccessMsg |
| `admin/partials/batch-create.php` | 響應式表單 UI（手機卡片 + 桌面表格 + 配額進度條）+ CSV 上傳 UI | VERIFIED | 619 行；包含 `#batch-create-page-template`、完整響應式 CSS、手機/桌面雙佈局、pill 切換、CSV 上傳區 |

**Artifact Level 3 (Wiring):**
- `useBatchCreate.js` 被 `includes/views/template.php` (L291) 載入，被 `BatchCreatePage.js` (L21) 透過 `return useBatchCreate()` 完整匯出至 Vue 元件
- `batch-create-page-template` 模板 ID 被 `BatchCreatePageComponent` (L19) 正確引用
- `BatchCreatePageComponent` 被 `template.php` (L339) 註冊至 Vue app，被 `useRouter.js` (L28) 綁定至 `batch-create` 路由

---

### Key Link Verification

| From | To | Via | Status | Details |
|------|-----|-----|--------|---------|
| batch-create.php | useBatchCreate.js | `v-for.*items` 綁定 | WIRED | L522,582：`v-for="(item, index) in items" :key="item.id"` |
| batch-create.php | useBatchCreate.js | `addItem`/`removeItem` 呼叫 | WIRED | L529,559,589,600：`@click="addItem"` / `@click="removeItem(item.id)"` |
| useBatchCreate.js | step='form' | `startFilling()` → `step.value = 'form'` | WIRED | L470：`step.value = 'form'`；L471：`initItems(quantity.value)` |
| useBatchCreate.js | items ref | `importCsv()/parseCSV()` 解析後填入 items | WIRED | L345：`items.value = [...filledItems, ...newItems]` |
| batch-create.php | useBatchCreate.js | `formMode`/`handleCsvUpload`/`handleDrop` 綁定 | WIRED | L466,472,508,509：`setFormMode()`/`handleDrop`/`handleCsvUpload` 全部出現 |
| batch-create.php | useBatchCreate.js | `csvError`/`csvSuccessMsg` 顯示 | WIRED | L486-500：`v-if="csvSuccessMsg \|\| csvError"` + toast 元件 |
| BatchCreatePage.js | useBatchCreate.js | `setup() { return useBatchCreate() }` | WIRED | BatchCreatePage.js L21 |
| template.php | BatchCreatePage.js | Vue app 元件註冊 | WIRED | template.php L339：`'batch-create': BatchCreatePageComponent` |

---

### Requirements Coverage

| Requirement | 描述 | 實作狀態 | 證據 |
|-------------|------|----------|------|
| FORM-01 | 手機版卡片式表單（商品 #N、4 欄位、刪除按鈕） | SATISFIED | batch-create.php L521-565：`.bp-card` + `商品 #{{ index + 1 }}` + v-model 4 欄位 + `removeItem` |
| FORM-02 | 桌面版表格式表單（6 欄表頭、行內編輯、刪除按鈕） | SATISFIED | batch-create.php L567-608：`.bp-table` thead 6 欄 + `v-for items` 行內 input |
| FORM-03 | 新增/刪除商品列（+ 新增按鈕、最少保留 1 個） | SATISFIED | addItem@click L559,600；removeItem `v-if="items.length > 1"` L529,589；useBatchCreate.js L158 guard |
| FORM-04 | 配額進度即時顯示（手機進度條 + 桌面 badge） | SATISFIED | batch-create.php L422-460；useBatchCreate.js L172-190：quotaUsed/quotaPercent computed |
| FORM-05 | 手動輸入/CSV 匯入 pill 切換；桌面版「匯入 CSV」按鈕 | SATISFIED | batch-create.php L462-480 pill 切換；L433-441 桌面 CSV 按鈕 |
| CSV-01 | CSV 檔案上傳（file input + 拖放，解析後填入表單） | SATISFIED | batch-create.php L504-518 拖放 label；useBatchCreate.js L309-372 handleCsvUpload；L345 items 填入 |
| CSV-02 | CSV 解析驗證（必填欄位、數量缺失預設 0、錯誤提示） | SATISFIED | useBatchCreate.js L248-253 必填驗證；L281 數量缺失預設 '0'；L325 csvError 顯示 |
| CSV-03 | CSV 匯入結果預覽（顯示解析結果、可修改、匯入筆數提示） | SATISFIED | useBatchCreate.js L352 `csvSuccessMsg = '成功匯入 N 筆商品'`；L358 切回 manual 模式供繼續編輯 |

**無孤立需求（orphaned requirements）：** REQUIREMENTS.md 中 FORM-01~05、CSV-01~03 全部對應到 Phase 58，並已由 58-01 / 58-02 PLAN 認領。

---

### Anti-Patterns Found

| 檔案 | 行號 | 模式 | 嚴重性 | 影響 |
|------|------|------|--------|------|
| 無 | - | - | - | - |

以下誤判排除：
- `batch-create.php` 中 `placeholder="..."` 是 HTML input placeholder 屬性，非程式碼 stub
- `admin/partials/batch-create.php` 中 CSS `.bp-input::placeholder` 是樣式規則，非程式碼問題
- `useBatchCreate.js` 中 `handleCsvUpload` 和 `handleDrop` 有部分重複程式碼（FileReader 邏輯），已在 PLAN 中記錄為「可接受的 DRY 折衷，因為觸發路徑不同」

---

### Human Verification Required

#### 1. 響應式佈局切換

**Test:** 在瀏覽器中將視窗寬度縮至 767px 以下（手機模擬），進入批量上架表單步驟
**Expected:** 顯示 `.bp-card` 卡片式表單（bp-desktop-only 表格隱藏）；擴大至 768px 以上後切換為 `.bp-table` 表格式表單
**Why human:** CSS `@media (min-width: 768px)` 切換行為需瀏覽器實際渲染確認

#### 2. CSV 拖放視覺回饋

**Test:** 在手機版（或模擬窄版）CSV 匯入模式下，拖曳 CSV 檔案到 `.bp-csv-upload` 上傳區
**Expected:** 懸停時上傳區邊框變藍（border-color #2563EB）、背景變淡藍（#eff6ff）；放下後觸發解析
**Why human:** 拖放事件（dragover/drop）需瀏覽器互動操作

#### 3. 完整 CSV 匯入流程

**Test:** 準備 CSV 檔案（含欄位：名稱, 售價, 數量, 描述），在 CSV 匯入模式上傳
**Expected:** 綠色 toast「成功匯入 N 筆商品」顯示；formMode 自動切回 manual；表單中出現解析後的商品資料，且可繼續手動編輯
**Why human:** FileReader API 實際讀取 CSV 需瀏覽器環境

---

### Gaps Summary

無 gap。

所有 8 個需求（FORM-01~05、CSV-01~03）的實作均存在於程式碼中，且三個層級（存在、有實質內容、已正確串接）均通過。

---

## 補充說明

### 設計決策符合規格
- `price` / `quantity` 存為字串型態（非 number），避免 number input 的 0 預設值問題 — 與 PLAN 設計決策一致
- `isFormOverQuota` 獨立計算（不複用數量選擇的 `isOverQuota`），因為兩者語意不同 — 正確
- CSV 匯入策略：保留已填寫資料 + 追加 CSV 資料（不清空），避免覆蓋賣家已輸入內容 — 符合 CSV-01 規格「覆蓋現有空白列或新增列」

### 範圍邊界確認
- SUBMIT-01~04 提交按鈕和 API 呼叫屬於 Phase 59 範圍，本次不驗證（batch-create.php 中無「批量上架」提交按鈕，符合預期）
- 桌面版 CSV 上傳無拖放功能（只有 file input button），PLAN 明確說明桌面版「匯入 CSV」是獨立按鈕，非拖放區，設計正確

---

_Verified: 2026-03-03_
_Verifier: Claude (gsd-verifier)_

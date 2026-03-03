<?php // 批量上架頁面元件（Phase 57 Plan 02 — 數量選擇 UI）?>
<!-- 批量上架專用樣式（行內，繞過 InstaWP WAF） -->
<style>
.batch-preset-btn {
    width: 72px;
    height: 72px;
    border-radius: 12px;
    border: 2px solid #e2e8f0;
    background: white;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s;
    font-weight: 700;
    padding: 0;
}
.batch-preset-btn:hover {
    border-color: #2563EB;
    background: #f0f5ff;
}
.batch-preset-btn.active {
    background: #2563EB;
    border-color: #2563EB;
    color: white;
}
.batch-preset-btn .bp-number {
    font-size: 24px;
    line-height: 1;
}
.batch-preset-btn .bp-unit {
    font-size: 12px;
    margin-top: 2px;
    opacity: 0.8;
}

/* ===== Phase 58: 批量表單樣式 ===== */

/* 配額進度條 */
.bp-quota-bar {
    height: 6px;
    border-radius: 3px;
    background: #e2e8f0;
    overflow: hidden;
    flex: 1;
    min-width: 80px;
}
.bp-quota-bar-fill {
    height: 100%;
    border-radius: 3px;
    background: #2563EB;
    transition: width 0.3s ease;
}
.bp-quota-bar-fill.over {
    background: #dc2626;
}

/* 手機版卡片 */
.bp-card {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 16px;
    margin-bottom: 12px;
}
.bp-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
}
.bp-card-title {
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 700;
    font-size: 15px;
    color: #1e293b;
}
.bp-card-num {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    background: #2563EB;
    color: white;
    font-size: 12px;
    font-weight: 700;
}
.bp-field-label {
    font-size: 13px;
    font-weight: 600;
    color: #475569;
    margin-bottom: 4px;
}
.bp-field-label .required {
    color: #dc2626;
}
.bp-input {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    font-size: 14px;
    outline: none;
    transition: border-color 0.2s;
}
.bp-input:focus {
    border-color: #2563EB;
    box-shadow: 0 0 0 2px rgba(37,99,235,0.1);
}
.bp-input::placeholder {
    color: #94a3b8;
}
.bp-delete-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border-radius: 8px;
    border: none;
    background: transparent;
    color: #94a3b8;
    cursor: pointer;
    transition: all 0.2s;
}
.bp-delete-btn:hover {
    background: #fef2f2;
    color: #dc2626;
}
.bp-add-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    width: 100%;
    padding: 12px;
    border: 2px dashed #cbd5e1;
    border-radius: 12px;
    background: transparent;
    color: #2563EB;
    font-weight: 600;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.2s;
}
.bp-add-btn:hover {
    border-color: #2563EB;
    background: #f0f5ff;
}

/* 桌面版表格 */
.bp-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
}
.bp-table th {
    font-size: 12px;
    font-weight: 600;
    color: #64748b;
    text-align: left;
    padding: 8px 12px;
    border-bottom: 1px solid #e2e8f0;
    white-space: nowrap;
}
.bp-table td {
    padding: 8px 12px;
    vertical-align: middle;
    border-bottom: 1px solid #f1f5f9;
}
.bp-table .bp-row-num {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    background: #eff6ff;
    color: #2563EB;
    font-size: 12px;
    font-weight: 700;
}
.bp-table input {
    width: 100%;
    padding: 6px 10px;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    font-size: 14px;
    outline: none;
    transition: border-color 0.2s;
}
.bp-table input:focus {
    border-color: #2563EB;
    box-shadow: 0 0 0 2px rgba(37,99,235,0.1);
}
.bp-table input::placeholder {
    color: #94a3b8;
}

/* 響應式斷點：768px */
.bp-mobile-only { display: block; }
.bp-desktop-only { display: none; }
@media (min-width: 768px) {
    .bp-mobile-only { display: none; }
    .bp-desktop-only { display: block; }
}

/* ===== Phase 58 Plan 02: CSV 匯入樣式 ===== */

/* 手機版 pill 切換 */
.bp-pill-group {
    display: inline-flex;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    overflow: hidden;
    background: #f8fafc;
}
.bp-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 16px;
    border: none;
    background: transparent;
    font-size: 13px;
    font-weight: 600;
    color: #64748b;
    cursor: pointer;
    transition: all 0.2s;
    white-space: nowrap;
}
.bp-pill.active {
    background: #2563EB;
    color: white;
}
.bp-pill:not(.active):hover {
    background: #e2e8f0;
}

/* CSV 上傳區域 */
.bp-csv-upload {
    border: 2px dashed #cbd5e1;
    border-radius: 12px;
    padding: 32px 16px;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s;
    background: #f8fafc;
}
.bp-csv-upload:hover {
    border-color: #2563EB;
    background: #f0f5ff;
}
.bp-csv-upload.dragging {
    border-color: #2563EB;
    background: #eff6ff;
}

/* 提示訊息 */
.bp-toast {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    padding: 12px 16px;
    border-radius: 8px;
    font-size: 13px;
    line-height: 1.5;
    margin-bottom: 12px;
}
.bp-toast-success {
    background: #f0fdf4;
    color: #166534;
    border: 1px solid #bbf7d0;
}
.bp-toast-error {
    background: #fef2f2;
    color: #991b1b;
    border: 1px solid #fecaca;
}

/* 桌面版 CSV 按鈕 */
.bp-csv-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 14px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    background: white;
    font-size: 13px;
    font-weight: 600;
    color: #475569;
    cursor: pointer;
    transition: all 0.2s;
    white-space: nowrap;
}
.bp-csv-btn:hover {
    border-color: #2563EB;
    color: #2563EB;
    background: #f0f5ff;
}

/* ===== Phase 59: 提交按鈕與結果回饋樣式 ===== */

/* 手機版底部固定欄 */
.bp-submit-bar {
    position: sticky;
    bottom: 0;
    z-index: 10;
    background: white;
    border-top: 1px solid #e2e8f0;
    padding: 12px 16px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}
.bp-submit-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 10px 24px;
    border-radius: 10px;
    border: none;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s;
    white-space: nowrap;
}
.bp-submit-btn.primary {
    background: #2563EB;
    color: white;
}
.bp-submit-btn.primary:hover:not(:disabled) {
    background: #1d4ed8;
}
.bp-submit-btn:disabled {
    background: #e2e8f0;
    color: #94a3b8;
    cursor: not-allowed;
}
/* spinner 動畫 */
.bp-spinner {
    display: inline-block;
    width: 16px;
    height: 16px;
    border: 2px solid rgba(255,255,255,0.3);
    border-top-color: white;
    border-radius: 50%;
    animation: bp-spin 0.6s linear infinite;
}
@keyframes bp-spin {
    to { transform: rotate(360deg); }
}
/* 失敗商品紅色邊框 */
.bp-card.error {
    border-color: #fca5a5;
    background: #fef2f2;
}
.bp-table tr.error td {
    background: #fef2f2;
}
.bp-table tr.error td:first-child {
    border-left: 3px solid #dc2626;
}
.bp-error-msg {
    font-size: 12px;
    color: #dc2626;
    margin-top: 4px;
    display: flex;
    align-items: center;
    gap: 4px;
}
/* 桌面版提交按鈕 */
.bp-submit-desktop {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 16px;
    border-radius: 8px;
    border: none;
    background: #2563EB;
    color: white;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s;
    white-space: nowrap;
}
.bp-submit-desktop:hover:not(:disabled) {
    background: #1d4ed8;
}
.bp-submit-desktop:disabled {
    background: #e2e8f0;
    color: #94a3b8;
    cursor: not-allowed;
}
.bp-submit-desktop .bp-spinner {
    width: 14px;
    height: 14px;
}
/* 提示文字 */
.bp-hint {
    font-size: 12px;
    color: #94a3b8;
    padding: 8px 0 0;
    line-height: 1.6;
}
</style>

<script type="text/x-template" id="batch-create-page-template">
<div class="min-h-screen bg-slate-50 text-slate-900 font-sans antialiased">
  <main class="flex flex-col min-w-0 relative bg-slate-50 min-h-screen">

    <!-- 頂部導航列 -->
    <div class="sticky top-0 z-10 bg-white border-b border-slate-200 px-4 py-3 flex items-center gap-3">
      <button @click="goBack" class="p-1 rounded-lg hover:bg-slate-100 transition">
        <svg class="w-5 h-5 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
        </svg>
      </button>
      <h1 class="text-lg font-bold text-slate-800">批量上架</h1>
    </div>

    <!-- 步驟 1: 數量選擇 (SELECT-01 ~ SELECT-04) -->
    <div v-if="step === 'select'" class="flex-1 flex flex-col">
      <div class="flex-1 px-4 py-8 md:py-12">
        <div class="max-w-sm mx-auto">

          <!-- 圖示 -->
          <div class="flex justify-center mb-6">
            <div class="w-16 h-16 rounded-full bg-blue-50 flex items-center justify-center">
              <svg class="w-8 h-8 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
              </svg>
            </div>
          </div>

          <!-- 標題 (SELECT-01) -->
          <h2 class="text-xl font-bold text-slate-900 text-center mb-1">要上架幾個商品？</h2>
          <p class="text-sm text-slate-500 text-center mb-8">選擇數量後，一次展開所有欄位填寫</p>

          <!-- 快選按鈕 (SELECT-01) -->
          <div class="flex justify-center gap-3 mb-6">
            <button v-for="num in presetOptions" :key="num"
              @click="selectPreset(num)"
              class="batch-preset-btn"
              :class="{ active: selectedPreset === num }">
              <span class="bp-number">{{ num }}</span>
              <span class="bp-unit">個</span>
            </button>
          </div>

          <!-- 分隔線 + 「或」(SELECT-02) -->
          <div class="flex items-center gap-3 mb-6">
            <div class="flex-1 border-t border-slate-200"></div>
            <span class="text-sm text-slate-400">或</span>
            <div class="flex-1 border-t border-slate-200"></div>
          </div>

          <!-- 自訂數量輸入 (SELECT-02) -->
          <div class="relative mb-6">
            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-lg font-bold">#</span>
            <input type="number"
              v-model="customQuantity"
              @input="onCustomInput"
              min="1" max="20"
              placeholder="自訂數量 (1-20)"
              class="w-full pl-10 pr-4 py-3 border border-slate-300 rounded-xl text-base focus:border-primary focus:ring-2 focus:ring-blue-100 outline-none transition">
          </div>

          <!-- 配額資訊 (SELECT-03) — 有上限時顯示 -->
          <div v-if="!quotaLoading && quota.limit > 0"
            class="flex items-center gap-2 px-4 py-3 rounded-xl mb-6"
            :class="isOverQuota ? 'bg-red-50 text-red-600' : 'bg-blue-50 text-blue-600'">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <span class="text-sm">
              剩餘配額：{{ remaining }} 個（已用 {{ quota.current }} / 上限 {{ quota.limit }}）
            </span>
          </div>

          <!-- 無限制賣家提示 (SELECT-03) -->
          <div v-if="!quotaLoading && quota.limit === 0"
            class="flex items-center gap-2 px-4 py-3 rounded-xl bg-green-50 text-green-600 mb-6">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <span class="text-sm">無商品數量限制</span>
          </div>

          <!-- 配額載入中 -->
          <div v-if="quotaLoading"
            class="flex items-center gap-2 px-4 py-3 rounded-xl bg-slate-100 text-slate-400 mb-6">
            <span class="text-sm">載入配額中...</span>
          </div>

        </div>
      </div>

      <!-- 底部按鈕 (SELECT-04) — sticky 固定在底部 -->
      <div class="sticky bottom-0 bg-white border-t border-slate-200 p-4">
        <div class="max-w-sm mx-auto">
          <button @click="startFilling"
            :disabled="!canProceed"
            class="w-full py-3.5 rounded-xl font-bold text-base transition flex items-center justify-center gap-2"
            :class="canProceed
              ? 'bg-primary text-white hover:bg-blue-700 shadow-lg shadow-blue-200'
              : 'bg-slate-200 text-slate-400 cursor-not-allowed'">
            <span v-if="quantity > 0">開始填寫（{{ quantity }} 個商品）</span>
            <span v-else>請選擇數量</span>
            <svg v-if="canProceed" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
            </svg>
          </button>
        </div>
      </div>
    </div>

    <!-- 步驟 2: 表單填寫 (FORM-01 ~ FORM-05) -->
    <div v-if="step === 'form'" class="flex-1 flex flex-col">

      <!-- === 桌面版配額 badge (FORM-04 桌面) === -->
      <div class="bp-desktop-only">
        <div class="flex items-center gap-3 px-6 py-3 border-b border-slate-200 bg-white">
          <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold"
            :class="isFormOverQuota ? 'bg-red-50 text-red-600' : 'bg-blue-50 text-blue-600'">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
            </svg>
            配額 {{ quotaUsed }}/{{ quota.limit === 0 ? '∞' : quota.limit }}
          </span>
          <!-- 桌面版右側按鈕區 -->
          <div class="ml-auto flex items-center gap-2">
            <label class="bp-csv-btn">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
              </svg>
              匯入 CSV
              <input type="file" accept=".csv" @change="handleCsvUpload" class="hidden">
            </label>
            <button @click="submitBatch"
              :disabled="validItemCount === 0 || submitting"
              class="bp-submit-desktop">
              <span v-if="submitting" class="bp-spinner"></span>
              <span v-if="submitting">上架中...</span>
              <span v-else>批量上架 ({{ validItemCount }})</span>
            </button>
          </div>
        </div>
      </div>

      <!-- === 手機版配額進度條 (FORM-04) === -->
      <div class="bp-mobile-only">
        <div class="px-4 py-3 bg-white border-b border-slate-200">
          <div class="flex items-center gap-3">
            <svg class="w-4 h-4 shrink-0" :class="isFormOverQuota ? 'text-red-500' : 'text-blue-500'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
            </svg>
            <span class="text-sm font-semibold" :class="isFormOverQuota ? 'text-red-600' : 'text-slate-700'">
              商品配額：{{ quotaUsed }} / {{ quota.limit === 0 ? '∞' : quota.limit }}
            </span>
            <div v-if="quota.limit > 0" class="bp-quota-bar">
              <div class="bp-quota-bar-fill" :class="{ over: isFormOverQuota }" :style="{ width: quotaPercent + '%' }"></div>
            </div>
          </div>
        </div>
      </div>

      <!-- === 手機版 pill 切換 (FORM-05) === -->
      <div class="bp-mobile-only">
        <div class="px-4 pt-3">
          <div class="bp-pill-group">
            <button @click="setFormMode('manual')" class="bp-pill" :class="{ active: formMode === 'manual' }">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
              </svg>
              手動輸入
            </button>
            <button @click="setFormMode('csv')" class="bp-pill" :class="{ active: formMode === 'csv' }">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
              </svg>
              CSV 匯入
            </button>
          </div>
        </div>
      </div>

      <!-- === 表單主體 === -->
      <div class="flex-1 overflow-y-auto">

        <!-- CSV 匯入結果提示（成功/錯誤） -->
        <div class="px-4 pt-3" v-if="csvSuccessMsg || csvError">
          <div v-if="csvSuccessMsg" class="bp-toast bp-toast-success">
            <svg class="w-4 h-4 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <span>{{ csvSuccessMsg }}</span>
            <button @click="clearCsvMessages" class="ml-auto text-green-500 hover:text-green-700">&times;</button>
          </div>
          <div v-if="csvError" class="bp-toast bp-toast-error">
            <svg class="w-4 h-4 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <span>{{ csvError }}</span>
            <button @click="clearCsvMessages" class="ml-auto text-red-500 hover:text-red-700">&times;</button>
          </div>
        </div>

        <!-- 手機版 CSV 上傳區（formMode === 'csv' 時顯示，支援點擊和拖放） -->
        <div v-if="formMode === 'csv'" class="bp-mobile-only px-4 py-4">
          <label class="bp-csv-upload block" :class="{ dragging: isDragging }"
            @dragover.prevent="isDragging = true"
            @dragleave.prevent="isDragging = false"
            @drop.prevent="handleDrop">
            <input type="file" accept=".csv" @change="handleCsvUpload" class="hidden">
            <div class="flex flex-col items-center gap-2">
              <svg class="w-10 h-10 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
              </svg>
              <p class="text-sm font-semibold text-slate-600">點擊或拖放 CSV 檔案到此處</p>
              <p class="text-xs text-slate-400">欄位：名稱、售價、數量、描述</p>
            </div>
          </label>
        </div>

        <!-- ===== 手機版：卡片式表單 (FORM-01) ===== -->
        <div v-if="formMode === 'manual'" class="bp-mobile-only px-4 py-4">
          <div v-for="(item, index) in items" :key="item.id" class="bp-card" :class="{ error: item._error }">
            <!-- 卡片標題 -->
            <div class="bp-card-header">
              <div class="bp-card-title">
                <span class="bp-card-num">{{ index + 1 }}</span>
                商品 #{{ index + 1 }}
              </div>
              <button v-if="items.length > 1" @click="removeItem(item.id)" class="bp-delete-btn" title="刪除此商品">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                </svg>
              </button>
            </div>
            <!-- 商品名稱 -->
            <div class="mb-3">
              <div class="bp-field-label">商品名稱 <span class="required">*</span></div>
              <input type="text" v-model="item.name" class="bp-input" placeholder="輸入商品名稱">
            </div>
            <!-- 售價 + 數量（並排） -->
            <div class="flex gap-3 mb-3">
              <div class="flex-1">
                <div class="bp-field-label">售價 <span class="required">*</span></div>
                <input type="number" v-model="item.price" class="bp-input" placeholder="售價" min="0">
              </div>
              <div class="flex-1">
                <div class="bp-field-label">數量 (0=無限)</div>
                <input type="number" v-model="item.quantity" class="bp-input" placeholder="數量" min="0">
              </div>
            </div>
            <!-- 描述 -->
            <div>
              <div class="bp-field-label">描述（選填）</div>
              <input type="text" v-model="item.description" class="bp-input" placeholder="商品描述...">
            </div>
            <!-- 失敗原因 -->
            <div v-if="item._error" class="bp-error-msg">
              <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
              </svg>
              {{ item._error }}
            </div>
          </div>

          <!-- 新增商品按鈕 (FORM-03) -->
          <button @click="addItem" class="bp-add-btn">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
            </svg>
            新增商品
          </button>
        </div>

        <!-- ===== 桌面版：表格式表單 (FORM-02) ===== -->
        <div class="bp-desktop-only px-6 py-4">
          <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
            <table class="bp-table">
              <thead>
                <tr>
                  <th style="width:48px">#</th>
                  <th>商品名稱</th>
                  <th style="width:120px">售價</th>
                  <th style="width:100px">數量</th>
                  <th>描述</th>
                  <th style="width:60px">操作</th>
                </tr>
              </thead>
              <tbody>
                <template v-for="(item, index) in items" :key="item.id">
                  <tr :class="{ error: item._error }">
                    <td><span class="bp-row-num">{{ index + 1 }}</span></td>
                    <td><input type="text" v-model="item.name" placeholder="商品名稱"></td>
                    <td><input type="number" v-model="item.price" placeholder="售價" min="0"></td>
                    <td><input type="number" v-model="item.quantity" placeholder="數量" min="0"></td>
                    <td><input type="text" v-model="item.description" placeholder="描述（選填）"></td>
                    <td>
                      <button v-if="items.length > 1" @click="removeItem(item.id)" class="bp-delete-btn" title="刪除">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                        </svg>
                      </button>
                    </td>
                  </tr>
                  <tr v-if="item._error" class="error">
                    <td colspan="6" style="padding: 2px 12px 8px; border-bottom: 1px solid #fecaca;">
                      <div class="bp-error-msg">
                        <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        {{ item._error }}
                      </div>
                    </td>
                  </tr>
                </template>
              </tbody>
            </table>
            <!-- 新增商品列按鈕 (FORM-03) -->
            <div class="p-3 border-t border-slate-100">
              <button @click="addItem" class="bp-add-btn">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                新增商品列
              </button>
            </div>
          </div>
          <!-- 桌面版提示文字 (SUBMIT-04) -->
          <div class="bp-hint">
            商品名稱和售價為必填。數量填 0 代表無限上架。支援 CSV 匯入（欄位：名稱、售價、數量、描述）。
          </div>
        </div>

      </div><!-- /overflow-y-auto -->

      <!-- 手機版底部提交欄 (SUBMIT-01) -->
      <div class="bp-submit-bar bp-mobile-only">
        <span class="text-sm font-semibold text-slate-700">{{ validItemCount }} 件商品</span>
        <button @click="submitBatch"
          :disabled="validItemCount === 0 || submitting"
          class="bp-submit-btn primary">
          <span v-if="submitting" class="bp-spinner"></span>
          <span v-if="submitting">上架中...</span>
          <span v-else>批量上架</span>
        </button>
      </div>

    </div><!-- /step === 'form' -->

  </main>
</div>
</script>

<?php // 載入 BatchCreatePage 元件 JS ?>
<script><?php include plugin_dir_path(dirname(__FILE__)) . 'js/components/BatchCreatePage.js'; ?></script>

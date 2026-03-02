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

    <!-- 步驟 2: 表單填寫（Phase 58 實作） -->
    <div v-if="step === 'form'" class="flex-1 flex items-center justify-center p-4">
      <div class="text-center text-slate-500">
        <p class="text-lg font-bold mb-2">表單填寫</p>
        <p>Phase 58 實作</p>
      </div>
    </div>

  </main>
</div>
</script>

<?php // 載入 BatchCreatePage 元件 JS ?>
<script><?php include plugin_dir_path(dirname(__FILE__)) . 'js/components/BatchCreatePage.js'; ?></script>

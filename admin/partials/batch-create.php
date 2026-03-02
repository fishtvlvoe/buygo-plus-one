<?php
// 批量上架頁面元件（Phase 57 — 空殼，Plan 02 實作完整 UI）
?>
<script type="text/x-template" id="batch-create-page-template">
<div class="min-h-screen bg-slate-50 text-slate-900 font-sans antialiased">
  <main class="flex flex-col min-w-0 relative bg-slate-50 min-h-screen">
    <div class="p-4 md:p-6">
      <div class="max-w-lg mx-auto text-center py-12">
        <h1 class="text-xl font-bold text-slate-800 mb-2">批量上架</h1>
        <p class="text-slate-500">頁面載入中...</p>
      </div>
    </div>
  </main>
</div>
</script>

<script>
// BatchCreatePageComponent — 批量上架頁面（Phase 57 ROUTE-01/ROUTE-02）
// Plan 02 會替換此骨架為完整的數量選擇 UI
var BatchCreatePageComponent = {
    name: 'BatchCreatePage',
    template: '#batch-create-page-template',
    setup() {
        const { ref } = Vue;
        const loading = ref(false);

        return {
            loading
        };
    }
};
</script>

/**
 * BatchCreatePage Component
 * BuyGo+1 Plugin
 *
 * 批量上架頁面 Vue 元件（thin shell）
 * 業務邏輯已抽取至 composables/useBatchCreate.js
 *
 * Dependencies:
 * - Vue 3
 * - BuyGoRouter (global)
 * - useBatchCreate (composable)
 *
 * Required window variables:
 * - window.buygoWpNonce: WordPress REST API nonce
 */

const BatchCreatePageComponent = {
    name: 'BatchCreatePage',
    template: '#batch-create-page-template',
    setup() {
        return useBatchCreate();
    }
};
// 注意：不再自行掛載，由 template.php 統一管理 Vue app

/**
 * Products Page Component
 * BuyGo+1 Plugin
 *
 * 商品管理頁面 Vue 組件（thin shell）
 * 業務邏輯已抽取至 composables/useProducts.js
 *
 * Dependencies:
 * - Vue 3
 * - BuyGoRouter (global)
 * - useCurrency (composable)
 * - useProducts (composable)
 * - BuyGoSmartSearchBox (component)
 *
 * Required window variables:
 * - window.buygoWpNonce: WordPress REST API nonce
 */

const ProductsPageComponent = {
    name: 'ProductsPage',
    components: {
        'smart-search-box': BuyGoSmartSearchBox
    },
    template: '#products-page-template',
    setup() {
        return useProducts();
    }
};
// 注意：不再自行掛載，由 template.php 統一管理 Vue app

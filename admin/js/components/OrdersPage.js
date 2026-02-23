/**
 * Orders Page Component
 * BuyGo+1 Plugin
 *
 * 訂單管理頁面 Vue 組件（thin shell）
 * 業務邏輯已抽取至 composables/useOrders.js
 *
 * Dependencies:
 * - Vue 3
 * - BuyGoRouter (global)
 * - useCurrency (composable)
 * - useOrders (composable)
 * - BuyGoSmartSearchBox (component)
 * - OrderDetailModal (component)
 *
 * Required window variables:
 * - window.buygoWpNonce: WordPress REST API nonce
 */

const OrdersPageComponent = {
    name: 'OrdersPage',
    components: {
        'order-detail-modal': OrderDetailModal,
        'smart-search-box': BuyGoSmartSearchBox
    },
    template: '#orders-page-template',
    setup() {
        return useOrders();
    }
};

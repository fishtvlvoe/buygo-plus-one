/**
 * Shipment Products Page Component
 * BuyGo+1 Plugin
 *
 * 備貨管理頁面 Vue 組件（薄殼，邏輯委派給 useShipmentProducts composable）
 *
 * Dependencies:
 * - Vue 3
 * - BuyGoRouter (global)
 * - useCurrency (composable)
 * - useShipmentProducts (composable)
 * - BuyGoSmartSearchBox (component)
 */
const ShipmentProductsPageComponent = {
    name: 'ShipmentProductsPage',
    components: {
        'smart-search-box': BuyGoSmartSearchBox
    },
    template: '#shipment-products-page-template',
    setup() {
        return useShipmentProducts();
    }
};

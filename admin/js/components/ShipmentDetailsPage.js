/**
 * Shipment Details Page Component
 * BuyGo+1 Plugin
 *
 * 出貨明細頁面 Vue 組件
 *
 * Dependencies:
 * - Vue 3
 * - BuyGoRouter (global)
 * - useCurrency (composable)
 * - useShipmentDetails (composable)
 * - BuyGoSmartSearchBox (component)
 */
const ShipmentDetailsPageComponent = {
    name: 'ShipmentDetailsPage',
    components: {
        'smart-search-box': BuyGoSmartSearchBox
    },
    template: '#shipment-details-page-template',
    setup() {
        return useShipmentDetails();
    }
};

<?php
// 出貨明細頁面元件
$shipment_details_component_template = <<<'HTML'
<main class="min-h-screen bg-gray-50">
    <!-- 頁面標題 -->
    <div class="bg-white shadow-sm border-b border-gray-200 px-6 py-4">
        <h1 class="text-2xl font-bold text-gray-900">出貨明細</h1>
    </div>

    <!-- 出貨明細列表容器 -->
    <div class="p-6">
        <!-- 桌面版表格 -->
        <div class="hidden md:block overflow-x-auto">
            <table class="min-w-full bg-white shadow-sm rounded-lg overflow-hidden">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">訂單編號</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">客戶名稱</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">商品</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">出貨日期</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">物流單號</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">狀態</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <tr v-for="detail in shipmentDetails" :key="detail.id" class="hover:bg-gray-50">
                        <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">{{ detail.orderNumber }}</td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">{{ detail.customerName }}</td>
                        <td class="px-4 py-3 text-sm text-gray-900">
                            <div v-for="item in detail.items" :key="item.id" class="mb-1">
                                {{ item.name }} x{{ item.quantity }}
                            </div>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">{{ detail.shipmentDate }}</td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">{{ detail.trackingNumber || '-' }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <span class="px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                {{ detail.status }}
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- 手機版卡片 -->
        <div class="md:hidden space-y-4">
            <div v-for="detail in shipmentDetails" :key="detail.id" class="bg-white rounded-lg shadow-sm p-4 border border-gray-200">
                <div class="mb-3">
                    <div class="text-base font-semibold text-gray-900 mb-1">{{ detail.orderNumber }}</div>
                    <div class="text-sm text-gray-600 mb-2">{{ detail.customerName }}</div>
                    <div class="text-sm text-gray-500 mb-2">出貨日期：{{ detail.shipmentDate }}</div>
                    <div class="text-sm text-gray-900 mb-2" v-if="detail.trackingNumber">物流單號：{{ detail.trackingNumber }}</div>
                    <span class="px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                        {{ detail.status }}
                    </span>
                </div>
            </div>
        </div>
    </div>
</main>
HTML;

// 假資料
$mock_shipment_details = [
    ['id' => 1, 'orderNumber' => 'ORD-2026-001', 'customerName' => '王小明', 'items' => [['id' => 1, 'name' => '測試商品 A', 'quantity' => 2]], 'shipmentDate' => '2026-01-15', 'trackingNumber' => '1234567890', 'status' => '已出貨'],
    ['id' => 2, 'orderNumber' => 'ORD-2026-002', 'customerName' => '李小華', 'items' => [['id' => 2, 'name' => '測試商品 B', 'quantity' => 1]], 'shipmentDate' => '2026-01-14', 'trackingNumber' => '0987654321', 'status' => '已出貨'],
    ['id' => 3, 'orderNumber' => 'ORD-2026-003', 'customerName' => '張小美', 'items' => [['id' => 3, 'name' => '測試商品 C', 'quantity' => 3]], 'shipmentDate' => '2026-01-13', 'trackingNumber' => '1122334455', 'status' => '已出貨'],
];
?>

<script>
const ShipmentDetailsPageComponent = {
    name: 'ShipmentDetailsPage',
    template: `<?php echo $shipment_details_component_template; ?>`,
    setup() {
        const { ref } = Vue;
        const shipmentDetails = ref(<?php echo json_encode($mock_shipment_details); ?>);

        return {
            shipmentDetails
        };
    }
};
</script>

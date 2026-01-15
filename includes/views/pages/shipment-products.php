<?php
// 出貨商品頁面元件
$shipment_products_component_template = <<<'HTML'
<main class="min-h-screen bg-gray-50">
    <!-- 頁面標題 -->
    <div class="bg-white shadow-sm border-b border-gray-200 px-6 py-4">
        <h1 class="text-2xl font-bold text-gray-900">出貨商品</h1>
    </div>

    <!-- 出貨商品列表容器 -->
    <div class="p-6">
        <!-- 桌面版表格 -->
        <div class="hidden md:block overflow-x-auto">
            <table class="min-w-full bg-white shadow-sm rounded-lg overflow-hidden">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">客戶名稱</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">商品清單</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">數量</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">出貨狀態</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">操作</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <tr v-for="item in shipmentProducts" :key="item.id" class="hover:bg-gray-50">
                        <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">{{ item.customerName }}</td>
                        <td class="px-4 py-3 text-sm text-gray-900">
                            <div v-for="product in item.products" :key="product.id" class="mb-1">
                                {{ product.name }}
                            </div>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">{{ item.totalQuantity }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <span class="px-3 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                {{ item.status }}
                            </span>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm">
                            <button class="text-blue-600 hover:text-blue-800 font-medium">出貨</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- 手機版卡片 -->
        <div class="md:hidden space-y-4">
            <div v-for="item in shipmentProducts" :key="item.id" class="bg-white rounded-lg shadow-sm p-4 border border-gray-200">
                <div class="mb-3">
                    <div class="text-base font-semibold text-gray-900 mb-2">{{ item.customerName }}</div>
                    <div class="text-sm text-gray-600 mb-2">
                        <div v-for="product in item.products" :key="product.id" class="mb-1">
                            {{ product.name }} x{{ product.quantity }}
                        </div>
                    </div>
                    <div class="text-sm text-gray-900 mb-2">總數量：{{ item.totalQuantity }}</div>
                    <span class="px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                        {{ item.status }}
                    </span>
                </div>
                <button class="w-full px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium text-sm">出貨</button>
            </div>
        </div>
    </div>
</main>
HTML;

// 假資料
$mock_shipment_products = [
    ['id' => 1, 'customerName' => '王小明', 'products' => [['id' => 1, 'name' => '測試商品 A', 'quantity' => 2]], 'totalQuantity' => 2, 'status' => '待出貨'],
    ['id' => 2, 'customerName' => '李小華', 'products' => [['id' => 2, 'name' => '測試商品 B', 'quantity' => 1]], 'totalQuantity' => 1, 'status' => '待出貨'],
    ['id' => 3, 'customerName' => '張小美', 'products' => [['id' => 3, 'name' => '測試商品 C', 'quantity' => 3]], 'totalQuantity' => 3, 'status' => '待出貨'],
];
?>

<script>
const ShipmentProductsPageComponent = {
    name: 'ShipmentProductsPage',
    template: `<?php echo $shipment_products_component_template; ?>`,
    setup() {
        const { ref } = Vue;
        const shipmentProducts = ref(<?php echo json_encode($mock_shipment_products); ?>);

        return {
            shipmentProducts
        };
    }
};
</script>

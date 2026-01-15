<?php
// 訂單管理頁面元件
$orders_component_template = <<<'HTML'
<main class="min-h-screen bg-gray-50">
    <!-- 頁面標題 -->
    <div class="bg-white shadow-sm border-b border-gray-200 px-6 py-4">
        <h1 class="text-2xl font-bold text-gray-900">訂單管理</h1>
    </div>

    <!-- 訂單列表容器 -->
    <div class="p-6">
        <!-- 桌面版表格 -->
        <div class="hidden md:block overflow-x-auto">
            <table class="min-w-full bg-white shadow-sm rounded-lg overflow-hidden">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">訂單編號</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">客戶名稱</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">商品</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">總金額</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">狀態</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">下單日期</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">操作</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <tr v-for="order in orders" :key="order.id" class="hover:bg-gray-50">
                        <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">{{ order.orderNumber }}</td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">{{ order.customerName }}</td>
                        <td class="px-4 py-3 text-sm text-gray-900">
                            <div v-for="item in order.items" :key="item.id" class="mb-1">
                                {{ item.name }} x{{ item.quantity }}
                            </div>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm font-semibold text-gray-900">{{ formatPrice(order.total, order.currency) }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <span :class="getStatusClass(order.status)" class="px-3 py-1 rounded-full text-xs font-medium">
                                {{ order.status }}
                            </span>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">{{ order.orderDate }}</td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm">
                            <button class="text-blue-600 hover:text-blue-800 font-medium">詳情</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- 手機版卡片 -->
        <div class="md:hidden space-y-4">
            <div v-for="order in orders" :key="order.id" class="bg-white rounded-lg shadow-sm p-4 border border-gray-200">
                <div class="flex items-start justify-between mb-3">
                    <div class="flex-1">
                        <div class="text-sm font-semibold text-gray-900 mb-1">{{ order.orderNumber }}</div>
                        <div class="text-sm text-gray-600 mb-2">{{ order.customerName }}</div>
                        <div class="text-lg font-bold text-gray-900 mb-2">{{ formatPrice(order.total, order.currency) }}</div>
                        <span :class="getStatusClass(order.status)" class="px-2 py-1 rounded-full text-xs font-medium">
                            {{ order.status }}
                        </span>
                    </div>
                </div>
                <div class="text-xs text-gray-500 mt-2">下單日期：{{ order.orderDate }}</div>
                <button class="mt-3 w-full px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium text-sm">查看詳情</button>
            </div>
        </div>
    </div>
</main>
HTML;

// 假資料
$mock_orders = [
    ['id' => 1, 'orderNumber' => 'ORD-2026-001', 'customerName' => '王小明', 'items' => [['id' => 1, 'name' => '測試商品 A', 'quantity' => 2]], 'total' => 2000, 'currency' => 'TWD', 'status' => '待處理', 'orderDate' => '2026-01-15'],
    ['id' => 2, 'orderNumber' => 'ORD-2026-002', 'customerName' => '李小華', 'items' => [['id' => 2, 'name' => '測試商品 B', 'quantity' => 1]], 'total' => 2000, 'currency' => 'TWD', 'status' => '已備貨', 'orderDate' => '2026-01-14'],
    ['id' => 3, 'orderNumber' => 'ORD-2026-003', 'customerName' => '張小美', 'items' => [['id' => 3, 'name' => '測試商品 C', 'quantity' => 3]], 'total' => 9000, 'currency' => 'TWD', 'status' => '已出貨', 'orderDate' => '2026-01-13'],
];
?>

<script>
const OrdersPageComponent = {
    name: 'OrdersPage',
    template: `<?php echo $orders_component_template; ?>`,
    setup() {
        const { ref } = Vue;
        const orders = ref(<?php echo json_encode($mock_orders); ?>);

        const formatPrice = (price, currency) => {
            return `${price.toLocaleString()} ${currency}`;
        };

        const getStatusClass = (status) => {
            const statusMap = {
                '待處理': 'bg-yellow-100 text-yellow-800',
                '已備貨': 'bg-blue-100 text-blue-800',
                '已出貨': 'bg-green-100 text-green-800',
                '已完成': 'bg-gray-100 text-gray-800'
            };
            return statusMap[status] || 'bg-gray-100 text-gray-800';
        };

        return {
            orders,
            formatPrice,
            getStatusClass
        };
    }
};
</script>

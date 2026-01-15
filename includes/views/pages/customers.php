<?php
// 客戶管理頁面元件
$customers_component_template = <<<'HTML'
<main class="min-h-screen bg-gray-50">
    <!-- 頁面標題 -->
    <div class="bg-white shadow-sm border-b border-gray-200 px-6 py-4">
        <h1 class="text-2xl font-bold text-gray-900">客戶管理</h1>
    </div>

    <!-- 客戶列表容器 -->
    <div class="p-6">
        <!-- 桌面版表格 -->
        <div class="hidden md:block overflow-x-auto">
            <table class="min-w-full bg-white shadow-sm rounded-lg overflow-hidden">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">客戶名稱</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">電話</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">訂單數</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">總消費</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">最後下單日期</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">操作</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <tr v-for="customer in customers" :key="customer.id" class="hover:bg-gray-50">
                        <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">{{ customer.name }}</td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">{{ customer.phone }}</td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">{{ customer.email }}</td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">{{ customer.orderCount }}</td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm font-semibold text-gray-900">{{ formatPrice(customer.totalSpent, customer.currency) }}</td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">{{ customer.lastOrderDate }}</td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm">
                            <button class="text-blue-600 hover:text-blue-800 font-medium">詳情</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- 手機版卡片 -->
        <div class="md:hidden space-y-4">
            <div v-for="customer in customers" :key="customer.id" class="bg-white rounded-lg shadow-sm p-4 border border-gray-200">
                <div class="mb-3">
                    <div class="text-base font-semibold text-gray-900 mb-1">{{ customer.name }}</div>
                    <div class="text-sm text-gray-600 mb-1">{{ customer.phone }}</div>
                    <div class="text-sm text-gray-600 mb-2">{{ customer.email }}</div>
                    <div class="flex items-center justify-between mt-2">
                        <div>
                            <div class="text-xs text-gray-500">訂單數</div>
                            <div class="text-sm font-semibold text-gray-900">{{ customer.orderCount }}</div>
                        </div>
                        <div>
                            <div class="text-xs text-gray-500">總消費</div>
                            <div class="text-sm font-semibold text-gray-900">{{ formatPrice(customer.totalSpent, customer.currency) }}</div>
                        </div>
                    </div>
                    <div class="text-xs text-gray-500 mt-2">最後下單：{{ customer.lastOrderDate }}</div>
                </div>
                <button class="w-full px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium text-sm">查看詳情</button>
            </div>
        </div>
    </div>
</main>
HTML;

// 假資料
$mock_customers = [
    ['id' => 1, 'name' => '王小明', 'phone' => '0912-345-678', 'email' => 'wang@example.com', 'orderCount' => 5, 'totalSpent' => 15000, 'currency' => 'TWD', 'lastOrderDate' => '2026-01-15'],
    ['id' => 2, 'name' => '李小華', 'phone' => '0923-456-789', 'email' => 'li@example.com', 'orderCount' => 3, 'totalSpent' => 8000, 'currency' => 'TWD', 'lastOrderDate' => '2026-01-14'],
    ['id' => 3, 'name' => '張小美', 'phone' => '0934-567-890', 'email' => 'zhang@example.com', 'orderCount' => 8, 'totalSpent' => 25000, 'currency' => 'TWD', 'lastOrderDate' => '2026-01-13'],
];
?>

<script>
const CustomersPageComponent = {
    name: 'CustomersPage',
    template: `<?php echo $customers_component_template; ?>`,
    setup() {
        const { ref } = Vue;
        const customers = ref(<?php echo json_encode($mock_customers); ?>);

        const formatPrice = (price, currency) => {
            return `${price.toLocaleString()} ${currency}`;
        };

        return {
            customers,
            formatPrice
        };
    }
};
</script>

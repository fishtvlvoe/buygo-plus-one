<?php
// 系統設定頁面元件
$settings_component_template = <<<'HTML'
<main class="min-h-screen bg-gray-50">
    <!-- 頁面標題 -->
    <div class="bg-white shadow-sm border-b border-gray-200 px-6 py-4">
        <h1 class="text-2xl font-bold text-gray-900">系統設定</h1>
    </div>

    <!-- 設定內容容器 -->
    <div class="p-6">
        <!-- 分頁標籤 -->
        <div class="bg-white rounded-lg shadow-sm mb-6">
            <div class="border-b border-gray-200">
                <nav class="flex -mb-px">
                    <button 
                        v-for="tab in tabs" 
                        :key="tab.id"
                        @click="activeTab = tab.id"
                        :class="activeTab === tab.id ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                        class="px-6 py-3 text-sm font-medium border-b-2 transition-colors"
                    >
                        {{ tab.label }}
                    </button>
                </nav>
            </div>
        </div>

        <!-- 權限管理 -->
        <div v-if="activeTab === 'permissions'" class="bg-white rounded-lg shadow-sm p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">權限管理</h2>
            <div class="space-y-4">
                <div class="border border-gray-200 rounded-lg p-4">
                    <div class="text-sm font-medium text-gray-900 mb-2">管理員權限</div>
                    <div class="text-sm text-gray-600">可管理所有功能</div>
                </div>
                <div class="border border-gray-200 rounded-lg p-4">
                    <div class="text-sm font-medium text-gray-900 mb-2">小幫手權限</div>
                    <div class="text-sm text-gray-600">可查看和編輯訂單、商品</div>
                </div>
            </div>
        </div>

        <!-- LINE Bot 設定 -->
        <div v-if="activeTab === 'linebot'" class="bg-white rounded-lg shadow-sm p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">LINE Bot 設定</h2>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Channel Access Token</label>
                    <input type="text" :value="settings.lineBot.token" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:outline-none" placeholder="輸入 Channel Access Token">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Channel Secret</label>
                    <input type="text" :value="settings.lineBot.secret" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:outline-none" placeholder="輸入 Channel Secret">
                </div>
                <button class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium">測試連線</button>
            </div>
        </div>

        <!-- 匯率設定 -->
        <div v-if="activeTab === 'exchange'" class="bg-white rounded-lg shadow-sm p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">匯率設定</h2>
            <div class="space-y-4">
                <div v-for="rate in settings.exchangeRates" :key="rate.currency" class="flex items-center justify-between border border-gray-200 rounded-lg p-4">
                    <div>
                        <div class="text-sm font-medium text-gray-900">{{ rate.currency }}</div>
                        <div class="text-xs text-gray-500">{{ rate.label }}</div>
                    </div>
                    <input type="number" :value="rate.rate" step="0.01" class="w-32 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:outline-none" placeholder="匯率">
                </div>
                <button class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium">儲存匯率</button>
            </div>
        </div>
    </div>
</main>
HTML;

// 假資料
$mock_settings = [
    'lineBot' => [
        'token' => 'xxxxxxxxxxxxx',
        'secret' => 'yyyyyyyyyyyyy'
    ],
    'exchangeRates' => [
        ['currency' => 'TWD', 'label' => '台幣', 'rate' => 1.0],
        ['currency' => 'JPY', 'label' => '日幣', 'rate' => 0.22],
        ['currency' => 'USD', 'label' => '美元', 'rate' => 31.5],
        ['currency' => 'KRW', 'label' => '韓幣', 'rate' => 0.024],
        ['currency' => 'THB', 'label' => '泰銖', 'rate' => 0.9]
    ]
];
?>

<script>
const SettingsPageComponent = {
    name: 'SettingsPage',
    template: `<?php echo $settings_component_template; ?>`,
    setup() {
        const { ref } = Vue;
        const activeTab = ref('permissions');
        const settings = ref(<?php echo json_encode($mock_settings); ?>);
        
        const tabs = [
            { id: 'permissions', label: '權限管理' },
            { id: 'linebot', label: 'LINE Bot 設定' },
            { id: 'exchange', label: '匯率設定' }
        ];

        return {
            activeTab,
            settings,
            tabs
        };
    }
};
</script>

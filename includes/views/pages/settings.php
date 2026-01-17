<?php
// 系統設定頁面元件

$settings_component_template = <<<'HTML'
<main class="min-h-screen bg-slate-50">
    <!-- 頁面標題 -->
    <div class="bg-white shadow-sm border-b border-slate-200 px-6 py-4">
        <h1 class="text-2xl font-bold text-slate-900 font-title">系統設定</h1>
    </div>

    <!-- 設定內容容器 -->
    <div class="p-6">
        <!-- 模板設定 -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 mb-6">
            <h2 class="text-lg font-semibold text-slate-900 mb-4">📝 訂單通知模板</h2>
            
            <div class="space-y-6">
                <!-- 買家版模板 -->
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">買家版模板</label>
                    <div class="border border-slate-200 rounded-lg p-4 bg-slate-50">
                        <textarea 
                            v-model="buyerTemplate"
                            rows="6"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-1 focus:ring-primary focus:border-primary outline-none text-sm font-mono bg-white"
                            placeholder="輸入買家版模板..."></textarea>
                    </div>
                    <p class="text-xs text-slate-500 mt-2">
                        可用變數：{{客戶名稱}}、{{訂單編號}}、{{商品名稱}}、{{訂單金額}}、{{出貨日期}}
                    </p>
                </div>
                
                <!-- 賣家版模板 -->
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">賣家版模板</label>
                    <div class="border border-slate-200 rounded-lg p-4 bg-slate-50">
                        <textarea 
                            v-model="sellerTemplate"
                            rows="6"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-1 focus:ring-primary focus:border-primary outline-none text-sm font-mono bg-white"
                            placeholder="輸入賣家版模板..."></textarea>
                    </div>
                    <p class="text-xs text-slate-500 mt-2">
                        可用變數：{{客戶名稱}}、{{訂單編號}}、{{商品名稱}}、{{訂單金額}}、{{出貨日期}}
                    </p>
                </div>
            </div>
            
            <div class="mt-6 flex justify-end">
                <button 
                    @click="saveTemplates"
                    :disabled="savingTemplates"
                    class="px-6 py-2 bg-primary text-white rounded-lg hover:bg-blue-700 font-medium transition shadow-sm disabled:opacity-50 disabled:cursor-not-allowed">
                    <span v-if="savingTemplates">儲存中...</span>
                    <span v-else>儲存</span>
                </button>
            </div>
        </div>

        <!-- 小幫手管理 -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold text-slate-900">👥 小幫手管理</h2>
                <button 
                    v-if="isAdmin"
                    @click="showAddHelperModal = true"
                    class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-700 font-medium transition shadow-sm">
                    新增小幫手
                </button>
            </div>
            
            <!-- 載入狀態 -->
            <div v-if="loadingHelpers" class="text-center py-8">
                <p class="text-slate-600">載入中...</p>
            </div>
            
            <!-- 小幫手列表 -->
            <div v-else>
                <!-- 桌面版表格 -->
                <div class="hidden md:block overflow-hidden">
                    <table class="w-full">
                        <thead class="bg-slate-50 border-b border-slate-200">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">使用者</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Email</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">操作</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-slate-200">
                            <tr v-for="helper in helpers" :key="helper.id" class="hover:bg-slate-50 transition">
                                <td class="px-4 py-3 text-sm font-medium text-slate-900">{{ helper.name }}</td>
                                <td class="px-4 py-3 text-sm text-slate-600">{{ helper.email }}</td>
                                <td class="px-4 py-3">
                                    <button 
                                        v-if="isAdmin"
                                        @click="removeHelper(helper.id)"
                                        class="text-red-600 hover:text-red-700 text-sm font-medium">
                                        移除
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="helpers.length === 0">
                                <td colspan="3" class="px-4 py-8 text-center text-slate-500">
                                    尚無小幫手
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <!-- 手機版卡片 -->
                <div class="md:hidden space-y-4">
                    <div v-for="helper in helpers" :key="helper.id" class="border border-slate-200 rounded-xl p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="text-base font-bold text-slate-900 mb-1">{{ helper.name }}</div>
                                <div class="text-sm text-slate-600">{{ helper.email }}</div>
                            </div>
                            <button 
                                v-if="isAdmin"
                                @click="removeHelper(helper.id)"
                                class="px-4 py-2 bg-red-50 text-red-600 rounded-lg text-sm font-medium hover:bg-red-100">
                                移除
                            </button>
                        </div>
                    </div>
                    <div v-if="helpers.length === 0" class="text-center py-8 text-slate-500">
                        尚無小幫手
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- 新增小幫手 Modal -->
    <div v-if="showAddHelperModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50" @click.self="closeAddHelperModal">
        <div class="bg-white rounded-2xl shadow-xl max-w-md w-full mx-4 max-h-[90vh] overflow-y-auto">
            <!-- 標題列 -->
            <div class="p-6 border-b border-slate-200">
                <div class="flex items-center justify-between">
                    <h2 class="text-xl font-bold text-slate-900 font-title">新增小幫手</h2>
                    <button @click="closeAddHelperModal" class="text-slate-400 hover:text-slate-600 transition">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            </div>
            
            <!-- 內容區域 -->
            <div class="p-6">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-slate-700 mb-2">搜尋使用者</label>
                    <input 
                        v-model="userSearchQuery"
                        @input="searchUsers"
                        type="text"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-1 focus:ring-primary focus:border-primary outline-none"
                        placeholder="輸入姓名或 Email...">
                </div>
                
                <!-- 搜尋結果 -->
                <div v-if="userSearchResults.length > 0" class="space-y-2 max-h-64 overflow-y-auto">
                    <button
                        v-for="user in userSearchResults"
                        :key="user.id"
                        @click="selectUser(user)"
                        class="w-full px-4 py-3 text-left border border-slate-200 rounded-lg hover:bg-slate-50 transition flex items-center gap-3">
                        <div class="flex-1">
                            <div class="font-medium text-slate-900">{{ user.name }}</div>
                            <div class="text-sm text-slate-600">{{ user.email }}</div>
                        </div>
                    </button>
                </div>
                
                <div v-else-if="userSearchQuery && !searchingUsers" class="text-center py-8 text-slate-500">
                    找不到符合的使用者
                </div>
                
                <div v-else-if="!userSearchQuery" class="text-center py-8 text-slate-500">
                    請輸入搜尋關鍵字
                </div>
                
                <!-- 按鈕列 -->
                <div class="flex justify-end space-x-3 pt-4 border-t border-slate-200 mt-4">
                    <button
                        @click="closeAddHelperModal"
                        class="px-4 py-2 bg-white border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50 font-medium">
                        取消
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Toast 通知 -->
    <div 
        v-if="toastMessage.show" 
        class="fixed top-4 right-4 z-50 animate-slide-in"
    >
        <div :class="[
            'px-6 py-4 rounded-lg shadow-lg flex items-center gap-3',
            toastMessage.type === 'success' ? 'bg-green-500 text-white' : 
            toastMessage.type === 'error' ? 'bg-red-500 text-white' : 
            'bg-blue-500 text-white'
        ]">
            <svg v-if="toastMessage.type === 'success'" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
            </svg>
            <svg v-else-if="toastMessage.type === 'error'" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
            <span class="font-medium">{{ toastMessage.message }}</span>
        </div>
    </div>
</main>
HTML;
?>

<script>
const SettingsPageComponent = {
    name: 'SettingsPage',
    template: `<?php echo $settings_component_template; ?>`,
    setup() {
        const { ref, onMounted } = Vue;
        
        // 模板設定狀態
        const buyerTemplate = ref('');
        const sellerTemplate = ref('');
        const savingTemplates = ref(false);
        
        // 小幫手管理狀態
        const helpers = ref([]);
        const loadingHelpers = ref(false);
        const isAdmin = ref(false);
        
        // 新增小幫手 Modal 狀態
        const showAddHelperModal = ref(false);
        const userSearchQuery = ref('');
        const userSearchResults = ref([]);
        const searchingUsers = ref(false);
        
        // Toast 通知狀態
        const toastMessage = ref({
            show: false,
            message: '',
            type: 'success'
        });
        
        // 顯示 Toast 訊息
        const showToast = (message, type = 'success') => {
            toastMessage.value = { show: true, message, type };
            setTimeout(() => {
                toastMessage.value.show = false;
            }, 3000);
        };
        
        // 載入模板設定
        const loadTemplates = async () => {
            try {
                const response = await fetch('/wp-json/buygo-plus-one/v1/settings/templates', {
                    credentials: 'include'
                });
                
                const result = await response.json();
                
                if (result.success && result.data) {
                    buyerTemplate.value = result.data.buyer_template || '';
                    sellerTemplate.value = result.data.seller_template || '';
                }
            } catch (err) {
                console.error('載入模板設定錯誤:', err);
            }
        };
        
        // 儲存模板設定
        const saveTemplates = async () => {
            savingTemplates.value = true;
            
            try {
                const response = await fetch('/wp-json/buygo-plus-one/v1/settings/templates', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    credentials: 'include',
                    body: JSON.stringify({
                        buyer_template: buyerTemplate.value,
                        seller_template: sellerTemplate.value,
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showToast('模板設定已儲存', 'success');
                } else {
                    showToast('儲存失敗：' + result.message, 'error');
                }
            } catch (err) {
                console.error('儲存模板設定錯誤:', err);
                showToast('儲存失敗', 'error');
            } finally {
                savingTemplates.value = false;
            }
        };
        
        // 載入小幫手列表
        const loadHelpers = async () => {
            loadingHelpers.value = true;
            
            try {
                const response = await fetch('/wp-json/buygo-plus-one/v1/settings/helpers', {
                    credentials: 'include'
                });
                
                const result = await response.json();
                
                if (result.success && result.data) {
                    helpers.value = result.data;
                }
            } catch (err) {
                console.error('載入小幫手列表錯誤:', err);
                showToast('載入小幫手列表失敗', 'error');
            } finally {
                loadingHelpers.value = false;
            }
        };
        
        // 移除小幫手
        const removeHelper = async (userId) => {
            if (!confirm('確定要移除這個小幫手嗎？')) {
                return;
            }
            
            try {
                const response = await fetch(`/wp-json/buygo-plus-one/v1/settings/helpers/${userId}`, {
                    method: 'DELETE',
                    credentials: 'include'
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showToast('小幫手已移除', 'success');
                    await loadHelpers();
                } else {
                    showToast('移除失敗：' + result.message, 'error');
                }
            } catch (err) {
                console.error('移除小幫手錯誤:', err);
                showToast('移除失敗', 'error');
            }
        };
        
        // 搜尋使用者
        const searchUsers = async () => {
            if (!userSearchQuery.value || userSearchQuery.value.length < 2) {
                userSearchResults.value = [];
                return;
            }
            
            searchingUsers.value = true;
            
            try {
                const response = await fetch(`/wp-json/buygo-plus-one/v1/settings/users/search?query=${encodeURIComponent(userSearchQuery.value)}`, {
                    credentials: 'include'
                });
                
                const result = await response.json();
                
                if (result.success && result.data) {
                    userSearchResults.value = result.data;
                } else {
                    userSearchResults.value = [];
                }
            } catch (err) {
                console.error('搜尋使用者錯誤:', err);
                userSearchResults.value = [];
            } finally {
                searchingUsers.value = false;
            }
        };
        
        // 選擇使用者
        const selectUser = async (user) => {
            try {
                const response = await fetch('/wp-json/buygo-plus-one/v1/settings/helpers', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    credentials: 'include',
                    body: JSON.stringify({ user_id: user.id })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showToast('小幫手已新增', 'success');
                    closeAddHelperModal();
                    await loadHelpers();
                } else {
                    showToast('新增失敗：' + result.message, 'error');
                }
            } catch (err) {
                console.error('新增小幫手錯誤:', err);
                showToast('新增失敗', 'error');
            }
        };
        
        // 關閉新增小幫手 Modal
        const closeAddHelperModal = () => {
            showAddHelperModal.value = false;
            userSearchQuery.value = '';
            userSearchResults.value = [];
        };
        
        // 檢查是否為管理員
        const checkAdmin = async () => {
            try {
                const response = await fetch('/wp-json/buygo-plus-one/v1/settings/user/permissions', {
                    credentials: 'include'
                });
                
                const result = await response.json();
                
                if (result.success && result.data) {
                    isAdmin.value = result.data.is_admin || false;
                }
            } catch (err) {
                console.error('檢查權限錯誤:', err);
                isAdmin.value = false;
            }
        };
        
        // 初始化
        onMounted(async () => {
            await checkAdmin();
            await loadTemplates();
            await loadHelpers();
        });
        
        return {
            buyerTemplate,
            sellerTemplate,
            savingTemplates,
            saveTemplates,
            helpers,
            loadingHelpers,
            isAdmin,
            removeHelper,
            showAddHelperModal,
            userSearchQuery,
            userSearchResults,
            searchingUsers,
            searchUsers,
            selectUser,
            closeAddHelperModal,
            toastMessage
        };
    }
};
</script>

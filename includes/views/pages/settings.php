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
            <h2 class="text-lg font-semibold text-slate-900 mb-4">📝 通知模板管理</h2>
            <p class="text-sm text-slate-600 mb-6">選擇分類和類型，然後編輯對應的訊息模板</p>
            
            <!-- 標籤分類 -->
            <div class="flex space-x-2 mb-6 border-b border-slate-200 overflow-x-auto">
                <button 
                    v-for="tab in templateTabs" 
                    :key="tab.key"
                    @click="activeTemplateTab = tab.key"
                    :class="[
                        'px-4 py-2 font-medium md:text-sm text-xs transition whitespace-nowrap',
                        activeTemplateTab === tab.key 
                            ? 'text-primary border-b-2 border-primary' 
                            : 'text-slate-600 hover:text-slate-900'
                    ]">
                    {{ tab.label }}
                </button>
            </div>
            
            <!-- 模板列表 -->
            <div class="space-y-4">
                <!-- 系統標籤：顯示兩個折疊區塊 -->
                <template v-if="activeTemplateTab === 'system'">
                    <!-- 系統通知折疊區塊 -->
                    <div class="border border-slate-200 rounded-lg overflow-hidden">
                        <button 
                            @click="expandedSystemNotifications = !expandedSystemNotifications"
                            class="w-full px-4 py-3 flex items-center justify-between bg-slate-50 hover:bg-slate-100 transition text-left">
                            <div class="flex items-center gap-3">
                                <svg 
                                    :class="['w-5 h-5 text-slate-400 transition-transform', expandedSystemNotifications ? 'rotate-90' : '']"
                                    fill="none" 
                                    stroke="currentColor" 
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                </svg>
                                <div>
                                    <div class="font-semibold text-slate-900 md:text-base text-sm">系統通知</div>
                                    <div class="md:text-sm text-xs text-slate-500">系統自動發送的通知訊息</div>
                                </div>
                            </div>
                        </button>
                        <div v-if="expandedSystemNotifications" class="p-4 border-t border-slate-200 space-y-4">
                            <template v-for="template in getSystemNotificationTemplates()" :key="template.key">
                                <!-- 折疊式模板項目 -->
                                <div class="border border-slate-200 rounded-lg overflow-hidden">
                                    <!-- 標題列（可點擊展開/收合） -->
                                    <button 
                                        @click="toggleTemplate(template.key)"
                                        class="w-full px-4 py-3 flex items-center justify-between bg-slate-50 hover:bg-slate-100 transition text-left">
                                        <div class="flex items-center gap-3">
                                            <svg 
                                                :class="['w-5 h-5 text-slate-400 transition-transform', isTemplateExpanded(template.key) ? 'rotate-90' : '']"
                                                fill="none" 
                                                stroke="currentColor" 
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                            </svg>
                                            <div class="flex-1">
                                                <div class="font-semibold text-slate-900 md:text-base text-sm whitespace-nowrap overflow-hidden text-ellipsis">{{ template.name }}</div>
                                                <div class="md:text-sm text-xs text-slate-500 whitespace-nowrap overflow-hidden text-ellipsis">{{ template.description }}</div>
                                            </div>
                                        </div>
                                    </button>
                                    
                                    <!-- 編輯器（展開時顯示） -->
                                    <div v-if="isTemplateExpanded(template.key)" class="p-4 border-t border-slate-200">
                                        <!-- 文字模板編輯器 -->
                                        <div v-if="template.type !== 'flex'" class="relative">
                                            <div class="flex items-center justify-between mb-2">
                                                <label class="text-sm font-medium text-slate-700">LINE 訊息內容</label>
                                                <!-- 可用變數下拉選單（標題右邊） -->
                                                <div v-if="template.variables && template.variables.length > 0" class="relative">
                                                    <button
                                                        @click.stop="toggleVariableDropdown(template.key)"
                                                        :data-key="template.key"
                                                        data-variable-button
                                                        class="flex items-center gap-1 px-2 py-1 text-xs text-slate-600 hover:text-slate-900 hover:bg-slate-100 rounded transition">
                                                        <span class="font-mono">{{ }}</span>
                                                        <span>點擊可用變數</span>
                                                        <svg 
                                                            :class="['w-3 h-3 transition-transform', isVariableDropdownOpen(template.key) ? 'rotate-180' : '']"
                                                            fill="none" 
                                                            stroke="currentColor" 
                                                            viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                                        </svg>
                                                    </button>
                                                    <!-- 下拉選單 -->
                                                    <div 
                                                        v-if="isVariableDropdownOpen(template.key)"
                                                        :data-variable-dropdown="template.key"
                                                        class="absolute right-0 top-full mt-1 bg-white border border-slate-300 rounded-lg shadow-lg z-50 p-3 min-w-[200px] max-w-[300px] max-h-[300px] overflow-y-auto"
                                                        style="box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);">
                                                        <div class="flex flex-wrap gap-2">
                                                            <div v-for="variable in template.variables" :key="variable" class="flex flex-col gap-0.5 items-center">
                                                                <button
                                                                    @click="copyVariable(variable); closeVariableDropdown(template.key)"
                                                                    class="px-2 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded md:text-xs text-[10px] font-mono transition cursor-pointer border border-slate-300 hover:border-primary">
                                                                    { {{ variable }} }
                                                                </button>
                                                                <span class="md:text-[10px] text-[9px] text-slate-500 text-center leading-tight">{{ getVariableDescription(variable) }}</span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <textarea 
                                                v-model="templateEdits[template.key].line.message"
                                                rows="8"
                                                class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-1 focus:ring-primary focus:border-primary outline-none text-sm font-mono bg-white"
                                                placeholder="輸入模板內容..."></textarea>
                                        </div>
                                        
                                        <!-- 卡片式訊息編輯器 -->
                                        <div v-else class="space-y-4">
                                            <div>
                                                <label class="block text-sm font-medium text-slate-700 mb-2">Logo 圖片 URL</label>
                                                <input 
                                                    type="text"
                                                    v-model="templateEdits[template.key].line.flex_template.logo_url"
                                                    class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-1 focus:ring-primary focus:border-primary outline-none text-sm"
                                                    placeholder="https://example.com/logo.png"
                                                />
                                            </div>
                                            
                                            <div>
                                                <label class="block text-sm font-medium text-slate-700 mb-2">標題文字</label>
                                                <input 
                                                    type="text"
                                                    v-model="templateEdits[template.key].line.flex_template.title"
                                                    class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-1 focus:ring-primary focus:border-primary outline-none text-sm"
                                                    placeholder="輸入標題..."
                                                />
                                            </div>
                                            
                                            <div>
                                                <label class="block text-sm font-medium text-slate-700 mb-2">說明文字</label>
                                                <textarea 
                                                    v-model="templateEdits[template.key].line.flex_template.description"
                                                    rows="3"
                                                    class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-1 focus:ring-primary focus:border-primary outline-none text-sm"
                                                    placeholder="輸入說明..."></textarea>
                                            </div>
                                            
                                            <div>
                                                <label class="block text-sm font-medium text-slate-700 mb-3">按鈕設定</label>
                                                <div class="space-y-3">
                                                    <div v-for="(button, index) in templateEdits[template.key].line.flex_template.buttons" :key="index" class="border border-slate-200 rounded-lg p-3">
                                                        <div class="font-medium text-sm text-slate-700 mb-2">按鈕 {{ index + 1 }}</div>
                                                        <div class="space-y-2">
                                                            <div>
                                                                <label class="block text-xs text-slate-600 mb-1">文字</label>
                                                                <input 
                                                                    type="text"
                                                                    v-model="button.label"
                                                                    class="w-full px-2 py-1.5 border border-slate-300 rounded focus:ring-1 focus:ring-primary focus:border-primary outline-none text-sm"
                                                                    placeholder="按鈕文字"
                                                                />
                                                            </div>
                                                            <div>
                                                                <label class="block text-xs text-slate-600 mb-1">關鍵字</label>
                                                                <div class="flex gap-2">
                                                                    <input 
                                                                        type="text"
                                                                        v-model="button.action"
                                                                        class="flex-1 px-2 py-1.5 border border-slate-300 rounded focus:ring-1 focus:ring-primary focus:border-primary outline-none text-sm font-mono"
                                                                        placeholder="/keyword"
                                                                    />
                                                                    <button
                                                                        @click="copyVariable(button.action.replace('/', ''))"
                                                                        class="px-2 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded text-xs border border-slate-300">
                                                                        複製
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                    
                    <!-- 關鍵字訊息折疊區塊 -->
                    <div class="border border-slate-200 rounded-lg overflow-hidden">
                        <button 
                            @click="expandedKeywords = !expandedKeywords"
                            class="w-full px-4 py-3 flex items-center justify-between bg-slate-50 hover:bg-slate-100 transition text-left">
                            <div class="flex items-center gap-3">
                                <svg 
                                    :class="['w-5 h-5 text-slate-400 transition-transform', expandedKeywords ? 'rotate-90' : '']"
                                    fill="none" 
                                    stroke="currentColor" 
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                </svg>
                                <div>
                                    <div class="font-semibold text-slate-900 md:text-base text-sm">關鍵字訊息</div>
                                    <div class="md:text-sm text-xs text-slate-500">管理 LINE 關鍵字自動回覆訊息</div>
                                </div>
                            </div>
                        </button>
                        <div v-if="expandedKeywords" class="p-4 border-t border-slate-200">
                            <!-- 關鍵字列表將在這裡顯示 -->
                            <div class="text-sm text-slate-600">關鍵字管理功能開發中...</div>
                        </div>
                    </div>
                </template>
                
                <!-- 客戶和賣家標籤：正常顯示模板列表 -->
                <template v-else>
                    <template v-for="template in getTemplatesByTab(activeTemplateTab)" :key="template.key">
                    <!-- 折疊式模板項目 -->
                    <div class="border border-slate-200 rounded-lg overflow-hidden">
                        <!-- 標題列（可點擊展開/收合） -->
                        <button 
                            @click="toggleTemplate(template.key)"
                            class="w-full px-4 py-3 flex items-center justify-between bg-slate-50 hover:bg-slate-100 transition text-left">
                            <div class="flex items-center gap-3">
                                <svg 
                                    :class="['w-5 h-5 text-slate-400 transition-transform', isTemplateExpanded(template.key) ? 'rotate-90' : '']"
                                    fill="none" 
                                    stroke="currentColor" 
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                </svg>
                                <div class="flex-1">
                                    <div class="font-semibold text-slate-900 md:text-base text-sm whitespace-nowrap overflow-hidden text-ellipsis">{{ template.name }}</div>
                                    <div class="md:text-sm text-xs text-slate-500 whitespace-nowrap overflow-hidden text-ellipsis">{{ template.description }}</div>
                                </div>
                            </div>
                        </button>
                        
                        <!-- 編輯器（展開時顯示） -->
                        <div v-if="isTemplateExpanded(template.key)" class="p-4 border-t border-slate-200">
                            <!-- 文字模板編輯器 -->
                            <div v-if="template.type !== 'flex'" class="relative">
                                <div class="flex items-center justify-between mb-2">
                                    <label class="text-sm font-medium text-slate-700">LINE 訊息內容</label>
                                    <!-- 可用變數下拉選單（標題右邊） -->
                                    <div v-if="template.variables && template.variables.length > 0" class="relative">
                                        <button
                                            @click.stop="toggleVariableDropdown(template.key)"
                                            :data-key="template.key"
                                            data-variable-button
                                            class="flex items-center gap-1 px-2 py-1 text-xs text-slate-600 hover:text-slate-900 hover:bg-slate-100 rounded transition">
                                            <span class="font-mono">{{ }}</span>
                                            <span>點擊可用變數</span>
                                            <svg 
                                                :class="['w-3 h-3 transition-transform', isVariableDropdownOpen(template.key) ? 'rotate-180' : '']"
                                                fill="none" 
                                                stroke="currentColor" 
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                            </svg>
                                        </button>
                                        <!-- 下拉選單 -->
                                        <div 
                                            v-if="isVariableDropdownOpen(template.key)"
                                            :data-variable-dropdown="template.key"
                                            class="absolute right-0 top-full mt-1 bg-white border border-slate-300 rounded-lg shadow-lg z-50 p-3 min-w-[200px] max-w-[300px] max-h-[300px] overflow-y-auto"
                                            style="box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);">
                                            <div class="flex flex-wrap gap-2">
                                                <div v-for="variable in template.variables" :key="variable" class="flex flex-col gap-0.5 items-center">
                                                    <button
                                                        @click="copyVariable(variable); closeVariableDropdown(template.key)"
                                                        class="px-2 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded md:text-xs text-[10px] font-mono transition cursor-pointer border border-slate-300 hover:border-primary">
                                                        { {{ variable }} }
                                                    </button>
                                                    <span class="md:text-[10px] text-[9px] text-slate-500 text-center leading-tight">{{ getVariableDescription(variable) }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <textarea 
                                    v-model="templateEdits[template.key].line.message"
                                    rows="8"
                                    class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-1 focus:ring-primary focus:border-primary outline-none text-sm font-mono bg-white"
                                    placeholder="輸入模板內容..."></textarea>
                            </div>
                            
                            <!-- 卡片式訊息編輯器 -->
                            <div v-else class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-2">Logo 圖片 URL</label>
                                    <input 
                                        type="text"
                                        v-model="templateEdits[template.key].line.flex_template.logo_url"
                                        class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-1 focus:ring-primary focus:border-primary outline-none text-sm"
                                        placeholder="https://example.com/logo.png"
                                    />
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-2">標題文字</label>
                                    <input 
                                        type="text"
                                        v-model="templateEdits[template.key].line.flex_template.title"
                                        class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-1 focus:ring-primary focus:border-primary outline-none text-sm"
                                        placeholder="圖片已收到！"
                                    />
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-2">說明文字</label>
                                    <textarea 
                                        v-model="templateEdits[template.key].line.flex_template.description"
                                        rows="3"
                                        class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-1 focus:ring-primary focus:border-primary outline-none text-sm"
                                        placeholder="請選擇您要使用的上架格式："
                                    ></textarea>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-3">按鈕設定</label>
                                    <div class="space-y-3">
                                        <div v-for="(button, index) in templateEdits[template.key].line.flex_template.buttons" :key="index" class="p-3 bg-slate-50 rounded-lg border border-slate-200">
                                            <div class="font-medium text-sm text-slate-700 mb-2">按鈕 {{ index + 1 }}</div>
                                            <div class="grid grid-cols-2 gap-3">
                                                <div>
                                                    <label class="block text-xs text-slate-600 mb-1">文字</label>
                                                    <input 
                                                        type="text"
                                                        v-model="button.label"
                                                        class="w-full px-2 py-1.5 border border-slate-300 rounded focus:ring-1 focus:ring-primary focus:border-primary outline-none text-sm"
                                                        placeholder="單一商品模板"
                                                    />
                                                </div>
                                                <div>
                                                    <label class="block text-xs text-slate-600 mb-1">關鍵字</label>
                                                    <div class="flex gap-1">
                                                        <input 
                                                            type="text"
                                                            v-model="button.action"
                                                            class="flex-1 px-2 py-1.5 border border-slate-300 rounded focus:ring-1 focus:ring-primary focus:border-primary outline-none text-sm font-mono"
                                                            placeholder="/one"
                                                        />
                                                        <button
                                                            @click="copyVariable(button.action)"
                                                            class="px-2 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded text-xs border border-slate-300">
                                                            複製
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
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
    
    <!-- 複製變數提示 -->
    <div 
        v-if="copyToast.show" 
        class="fixed top-4 right-4 z-50 animate-slide-in"
    >
        <div class="px-4 py-3 bg-slate-800 text-white rounded-lg shadow-lg text-sm">
            {{ copyToast.message }}
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
        const { ref, onMounted, onUnmounted } = Vue;
        
        // 模板設定狀態
        const activeTemplateTab = ref('buyer');
        const templateTabs = [
            { key: 'buyer', label: '客戶' },
            { key: 'seller', label: '賣家' },
            { key: 'system', label: '系統' }
        ];
        const expandedTemplates = ref(new Set());
        const expandedSystemNotifications = ref(false);
        const expandedKeywords = ref(false);
        const expandedVariables = ref(new Set()); // 新增：追蹤哪些模板的變數已展開
        const variableDropdownOpen = ref(new Set()); // 新增：追蹤哪些模板的下拉選單已打開
        const templateEdits = ref({});
        const savingTemplates = ref(false);
        const copyToast = ref({ show: false, message: '' });
        const keywords = ref([]);
        const loadingKeywords = ref(false);
        
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
        
        // 模板定義（包含分類和變數資訊）
        const templateDefinitions = {
            buyer: [
                {
                    key: 'order_created',
                    name: '訂單已建立',
                    description: '訂單建立時（完整或拆分）發送給客戶',
                    category: '客戶',
                    type: 'text',
                    variables: ['order_id', 'total']
                },
                {
                    key: 'order_cancelled',
                    name: '訂單已取消',
                    description: '訂單取消時（僅客戶自行取消）發送給客戶',
                    category: '客戶',
                    type: 'text',
                    variables: ['order_id', 'note']
                },
                {
                    key: 'plusone_order_confirmation',
                    name: '訂單確認',
                    description: '訂單確認（留言回覆）發送給買家',
                    category: '客戶',
                    type: 'text',
                    variables: ['product_name', 'quantity', 'total']
                }
            ],
            seller: [
                {
                    key: 'seller_order_created',
                    name: '新訂單通知',
                    description: '有人下訂單時發送給賣家',
                    category: '賣家',
                    type: 'text',
                    variables: ['order_id', 'buyer_name', 'order_total', 'order_url']
                },
                {
                    key: 'seller_order_cancelled',
                    name: '訂單已取消',
                    description: '訂單取消時發送給賣家',
                    category: '賣家',
                    type: 'text',
                    variables: ['order_id', 'buyer_name', 'note', 'order_url']
                }
            ],
            system: [
                {
                    key: 'system_line_follow',
                    name: '加入好友通知',
                    description: '加入好友時發送（含第一則通知）',
                    category: '系統',
                    type: 'text',
                    variables: []
                },
                {
                    key: 'flex_image_upload_menu',
                    name: '圖片上傳成功（卡片式訊息）',
                    description: '圖片上傳成功後發送的卡片式訊息',
                    category: '系統',
                    type: 'flex',
                    variables: []
                },
                {
                    key: 'system_image_upload_failed',
                    name: '圖片上傳失敗',
                    description: '圖片上傳失敗時發送',
                    category: '系統',
                    type: 'text',
                    variables: ['error_message']
                },
                {
                    key: 'system_product_published',
                    name: '商品上架成功',
                    description: '商品上架成功時發送',
                    category: '系統',
                    type: 'text',
                    variables: ['product_name', 'price', 'quantity', 'product_url', 'currency_symbol', 'original_price_section', 'category_section', 'arrival_date_section', 'preorder_date_section', 'community_url_section']
                },
                {
                    key: 'system_product_publish_failed',
                    name: '商品上架失敗',
                    description: '商品上架失敗時發送',
                    category: '系統',
                    type: 'text',
                    variables: ['error_message']
                },
                {
                    key: 'system_product_data_incomplete',
                    name: '商品資料不完整',
                    description: '商品資料不完整時發送',
                    category: '系統',
                    type: 'text',
                    variables: ['missing_fields']
                },
                {
                    key: 'system_keyword_reply',
                    name: '關鍵字回覆訊息',
                    description: '關鍵字回覆訊息',
                    category: '系統',
                    type: 'text',
                    variables: []
                }
            ]
        };

        // 取得當前標籤的模板列表
        const getTemplatesByTab = (tab) => {
            const templates = templateDefinitions[tab] || [];
            return templates.map(template => {
                // 確保 templateEdits 中有這個模板的資料
                if (!templateEdits.value[template.key]) {
                    if (template.type === 'flex') {
                        templateEdits.value[template.key] = {
                            type: 'flex',
                            line: {
                                flex_template: {
                                    logo_url: '',
                                    title: '',
                                    description: '',
                                    buttons: [
                                        { label: '', action: '' },
                                        { label: '', action: '' },
                                        { label: '', action: '' }
                                    ]
                                }
                            }
                        };
                    } else {
                        templateEdits.value[template.key] = {
                            line: {
                                message: ''
                            }
                        };
                    }
                }
                return template;
            });
        };
        
        // 取得系統通知模板（排除關鍵字回覆）
        const getSystemNotificationTemplates = () => {
            const templates = templateDefinitions['system'] || [];
            // 過濾掉 system_keyword_reply
            return templates
                .filter(template => template.key !== 'system_keyword_reply')
                .map(template => {
                    // 確保 templateEdits 中有這個模板的資料
                    if (!templateEdits.value[template.key]) {
                        if (template.type === 'flex') {
                            templateEdits.value[template.key] = {
                                type: 'flex',
                                line: {
                                    flex_template: {
                                        logo_url: '',
                                        title: '',
                                        description: '',
                                        buttons: [
                                            { label: '', action: '' },
                                            { label: '', action: '' },
                                            { label: '', action: '' }
                                        ]
                                    }
                                }
                            };
                        } else {
                            templateEdits.value[template.key] = {
                                line: {
                                    message: ''
                                }
                            };
                        }
                    }
                    return template;
                });
        };

        // 切換模板展開/收合
        const toggleTemplate = (key) => {
            if (expandedTemplates.value.has(key)) {
                expandedTemplates.value.delete(key);
            } else {
                expandedTemplates.value.add(key);
            }
        };

        // 檢查模板是否展開
        const isTemplateExpanded = (key) => {
            return expandedTemplates.value.has(key);
        };
        
        // 切換變數展開/收合（舊版，保留以備用）
        const toggleVariables = (templateKey) => {
            if (expandedVariables.value.has(templateKey)) {
                expandedVariables.value.delete(templateKey);
            } else {
                expandedVariables.value.add(templateKey);
            }
        };
        
        // 檢查變數是否展開（舊版，保留以備用）
        const isVariablesExpanded = (templateKey) => {
            return expandedVariables.value.has(templateKey);
        };
        
        // 切換變數下拉選單
        const toggleVariableDropdown = (templateKey) => {
            if (variableDropdownOpen.value.has(templateKey)) {
                variableDropdownOpen.value.delete(templateKey);
            } else {
                variableDropdownOpen.value.add(templateKey);
            }
        };
        
        // 檢查變數下拉選單是否打開
        const isVariableDropdownOpen = (templateKey) => {
            return variableDropdownOpen.value.has(templateKey);
        };
        
        // 點擊外部關閉下拉選單
        const closeVariableDropdown = (templateKey) => {
            variableDropdownOpen.value.delete(templateKey);
        };
        
        // 監聽點擊外部事件
        const handleClickOutside = (e) => {
            // 檢查點擊是否在下拉選單外部
            const dropdowns = document.querySelectorAll('[data-variable-dropdown]');
            dropdowns.forEach(dropdown => {
                const templateKey = dropdown.getAttribute('data-variable-dropdown');
                const button = e.target.closest('[data-variable-button]');
                const isClickInsideDropdown = dropdown.contains(e.target);
                const isClickOnButton = button && button.getAttribute('data-key') === templateKey;
                
                if (!isClickInsideDropdown && !isClickOnButton) {
                    closeVariableDropdown(templateKey);
                }
            });
        };
        
        onMounted(() => {
            document.addEventListener('click', handleClickOutside);
        });
        
        onUnmounted(() => {
            document.removeEventListener('click', handleClickOutside);
        });

        // 變數說明對應表
        const variableDescriptions = {
            'order_id': '訂單編號',
            'total': '訂單總金額',
            'note': '備註說明',
            'product_name': '商品名稱',
            'quantity': '數量',
            'buyer_name': '買家名稱',
            'order_total': '訂單總額',
            'order_url': '訂單連結',
            'error_message': '錯誤訊息',
            'product_url': '商品連結',
            'price': '價格',
            'currency_symbol': '貨幣符號',
            'original_price_section': '原價區塊',
            'category_section': '分類區塊',
            'arrival_date_section': '到貨日期區塊',
            'preorder_date_section': '預購日期區塊',
            'community_url_section': '社群連結區塊',
            'missing_fields': '缺少欄位'
        };
        
        // 取得變數說明
        const getVariableDescription = (variable) => {
            return variableDescriptions[variable] || variable;
        };
        
        // 複製變數到剪貼簿
        const copyVariable = async (variable) => {
            const variableText = `{${variable}}`;
            try {
                await navigator.clipboard.writeText(variableText);
                copyToast.value = { show: true, message: `已複製 ${variableText}` };
                setTimeout(() => {
                    copyToast.value.show = false;
                }, 2000);
            } catch (err) {
                console.error('複製失敗:', err);
                copyToast.value = { show: true, message: '複製失敗，請手動複製' };
                setTimeout(() => {
                    copyToast.value.show = false;
                }, 2000);
            }
        };

        // 載入模板設定
        const loadTemplates = async () => {
            try {
                const response = await fetch('/wp-json/buygo-plus-one/v1/settings/templates', {
                    credentials: 'include'
                });
                
                const result = await response.json();
                
                if (result.success && result.data) {
                    // 處理新的資料結構
                    const allTemplates = result.data.all || {};
                    
                    // 初始化所有模板的編輯資料
                    Object.keys(templateDefinitions).forEach(category => {
                        templateDefinitions[category].forEach(template => {
                            const templateData = allTemplates[template.key];
                            
                            if (template.type === 'flex') {
                                const flexTemplate = templateData?.line?.flex_template || {
                                    logo_url: '',
                                    title: '',
                                    description: '',
                                    buttons: [
                                        { label: '', action: '' },
                                        { label: '', action: '' },
                                        { label: '', action: '' }
                                    ]
                                };
                                
                                templateEdits.value[template.key] = {
                                    type: 'flex',
                                    line: {
                                        flex_template: {
                                            logo_url: flexTemplate.logo_url || '',
                                            title: flexTemplate.title || '',
                                            description: flexTemplate.description || '',
                                            buttons: flexTemplate.buttons || [
                                                { label: '', action: '' },
                                                { label: '', action: '' },
                                                { label: '', action: '' }
                                            ]
                                        }
                                    }
                                };
                            } else {
                                // 文字模板：優先讀取 line.message，如果沒有則讀取 line.text，最後使用空字串
                                const message = templateData?.line?.message || templateData?.line?.text || '';
                                templateEdits.value[template.key] = {
                                    line: {
                                        message: message
                                    }
                                };
                            }
                        });
                    });
                }
            } catch (err) {
                console.error('載入模板設定錯誤:', err);
            }
        };
        
        // 儲存模板設定
        const saveTemplates = async () => {
            savingTemplates.value = true;
            
            try {
                // 準備所有模板資料
                const templatesToSave = {};
                
                Object.keys(templateEdits.value).forEach(key => {
                    const edit = templateEdits.value[key];
                    if (edit.type === 'flex') {
                        templatesToSave[key] = {
                            type: 'flex',
                            line: {
                                flex_template: {
                                    logo_url: edit.line.flex_template.logo_url || '',
                                    title: edit.line.flex_template.title || '',
                                    description: edit.line.flex_template.description || '',
                                    buttons: (edit.line.flex_template.buttons || []).filter(btn => btn.label || btn.action)
                                }
                            }
                        };
                    } else {
                        templatesToSave[key] = {
                            line: {
                                message: edit.line.message || ''
                            }
                        };
                    }
                });
                
                const response = await fetch('/wp-json/buygo-plus-one/v1/settings/templates', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    credentials: 'include',
                    body: JSON.stringify({
                        templates: templatesToSave
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
            activeTemplateTab,
            templateTabs,
            templateEdits,
            expandedTemplates,
            savingTemplates,
            copyToast,
            getTemplatesByTab,
            getSystemNotificationTemplates,
            toggleTemplate,
            isTemplateExpanded,
            expandedSystemNotifications,
            expandedKeywords,
            toggleVariables,
            isVariablesExpanded,
            toggleVariableDropdown,
            isVariableDropdownOpen,
            closeVariableDropdown,
            copyVariable,
            getVariableDescription,
            loadTemplates,
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

<?php
// 系統設定頁面元件
?>
<style>
/* Transitions for mobile search */
.search-slide-enter-active, .search-slide-leave-active {
    transition: all 0.2s ease;
}
.search-slide-enter-from, .search-slide-leave-to {
    opacity: 0;
    transform: translateY(-10px);
}
</style>
<?php
$settings_component_template = <<<'HTML'
<main class="min-h-screen bg-slate-50">
    <!-- Header（與其他頁面一致） -->
    <header class="h-16 bg-white border-b border-slate-200 flex items-center justify-between px-4 md:px-6 shrink-0 z-10 sticky top-0 md:static relative">
        <div class="flex items-center gap-3 md:gap-4 overflow-hidden flex-1">
            <div class="flex flex-col overflow-hidden min-w-0 pl-12 md:pl-0" v-show="!showMobileSearch">
                <h1 class="text-xl font-bold text-slate-900 leading-tight truncate">設定</h1>
                <nav class="hidden md:flex text-[10px] md:text-xs text-slate-500 gap-1 items-center truncate">
                    <a href="/buygo-portal/dashboard" class="text-slate-500 hover:text-primary">首頁</a>
                    <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                    <span class="text-slate-900 font-medium">設定</span>
                </nav>
            </div>
        </div>

        <!-- 右側操作區 -->
        <div class="flex items-center gap-2 md:gap-3 shrink-0">
            <!-- 手機版搜尋按鈕 -->
            <button @click="showMobileSearch = !showMobileSearch"
                class="md:hidden p-2 text-slate-500 hover:text-slate-700 hover:bg-slate-100 rounded-lg transition">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
            </button>

            <!-- 桌面版全域搜尋框 -->
            <div class="relative hidden sm:block w-32 md:w-48 lg:w-64 transition-all duration-300">
                <input type="text" placeholder="全域搜尋..." v-model="globalSearchQuery" @input="handleGlobalSearch"
                    class="pl-9 pr-4 py-2 bg-slate-100 border-none rounded-lg text-sm focus:ring-2 focus:ring-primary w-full transition-all">
                <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
            </div>

            <!-- 通知鈴鐺 -->
            <button class="p-2 text-slate-400 hover:text-slate-600 rounded-full hover:bg-slate-100 relative">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
            </button>
        </div>

        <!-- 手機版搜尋覆蓋層 -->
        <transition name="search-slide">
            <div v-if="showMobileSearch" class="absolute inset-0 z-20 bg-white flex items-center px-4 gap-2 md:hidden">
                <div class="relative flex-1">
                    <input type="text" placeholder="全域搜尋..." v-model="globalSearchQuery" @input="handleGlobalSearch"
                        class="w-full pl-9 pr-4 py-2 bg-slate-100 border-none rounded-lg text-sm focus:ring-2 focus:ring-primary">
                    <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </div>
                <button @click="showMobileSearch = false" class="text-sm font-medium text-slate-500 p-2">取消</button>
            </div>
        </transition>
    </header>

    <!-- 設定內容容器 -->
    <div class="p-6">
        <!-- 模板設定 -->
        <div class="buygo-card p-6 mb-6">
            <h2 class="text-lg font-semibold text-slate-900 mb-4 flex items-center">
                <svg class="w-5 h-5 mr-2 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                </svg>
                LINE 通知模板管理
            </h2>
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
                                </div>
                            </div>
                        </button>
                        <div v-if="expandedSystemNotifications" class="p-4 border-t border-slate-200 space-y-4">
                            <draggable 
                                v-model="sortedSystemTemplates"
                                @end="onSystemTemplateDragEnd"
                                :animation="200"
                                handle=".drag-handle"
                                item-key="key"
                                class="space-y-4">
                                <template #item="{ element: template }">
                                    <div class="border border-slate-200 rounded-lg overflow-hidden">
                                        <!-- 標題列（可點擊展開/收合） -->
                                        <button 
                                            @click="toggleTemplate(template.key)"
                                        class="w-full px-4 py-3 flex items-center justify-between bg-slate-50 hover:bg-slate-100 transition text-left">
                                        <div class="flex items-center gap-3">
                                            <!-- 拖拉把手 -->
                                            <div 
                                                class="drag-handle cursor-move text-slate-400 hover:text-slate-600"
                                                @mousedown.stop
                                                @click.stop>
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"></path>
                                                </svg>
                                            </div>
                                            <svg 
                                                :class="['w-5 h-5 text-slate-400 transition-transform', isTemplateExpanded(template.key) ? 'rotate-90' : '']"
                                                fill="none" 
                                                stroke="currentColor" 
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                            </svg>
                                            <div class="flex-1">
                                                <div class="font-semibold text-slate-900 md:text-base text-sm whitespace-nowrap overflow-hidden text-ellipsis">{{ template.name }}</div>
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
                                                                    @click="copyVariable(variable, template.key); closeVariableDropdown(template.key)"
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
                            </draggable>
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
                                </div>
                            </div>
                        </button>
                        <div v-if="expandedKeywords" class="p-4 border-t border-slate-200">
                            <!-- 新增關鍵字按鈕 -->
                            <div class="mb-4 flex justify-end">
                                <button
                                    @click="showAddKeywordModal = true"
                                    class="buygo-btn buygo-btn-primary">
                                    + 新增關鍵字
                                </button>
                            </div>

                            <!-- 載入狀態 -->
                            <div v-if="loadingKeywords" class="buygo-loading">
                                <div class="buygo-loading-spinner"></div>
                                <p>載入中...</p>
                            </div>
                            
                            <!-- 關鍵字列表 -->
                            <div v-else>
                                <div v-if="keywords.length === 0" class="text-center py-8 text-slate-500">
                                    <p>尚無關鍵字，點擊「+ 新增關鍵字」開始新增</p>
                                </div>
                                
                                <draggable 
                                    v-model="keywords"
                                    @end="onKeywordDragEnd"
                                    :animation="200"
                                    handle=".keyword-drag-handle"
                                    item-key="id"
                                    class="space-y-3">
                                    <template #item="{ element: keyword }">
                                        <div class="border border-slate-200 rounded-lg overflow-hidden">
                                            <!-- 關鍵字標題列 -->
                                            <button 
                                                @click="toggleKeyword(keyword.id)"
                                                class="w-full px-4 py-3 flex items-center justify-between bg-slate-50 hover:bg-slate-100 transition text-left">
                                                <div class="flex items-center gap-3">
                                                    <!-- 拖拉把手 -->
                                                    <div 
                                                        class="keyword-drag-handle cursor-move text-slate-400 hover:text-slate-600"
                                                        @mousedown.stop
                                                        @click.stop>
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"></path>
                                                        </svg>
                                                    </div>
                                                    <svg 
                                                        :class="['w-5 h-5 text-slate-400 transition-transform', isKeywordExpanded(keyword.id) ? 'rotate-90' : '']"
                                                        fill="none" 
                                                        stroke="currentColor" 
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                                    </svg>
                                                    <div class="flex-1">
                                                        <div class="font-semibold text-slate-900 md:text-base text-sm">
                                                            {{ keyword.keyword }}
                                                            <span v-if="keyword.aliases && keyword.aliases.length > 0" class="text-xs text-slate-500 font-normal ml-2">
                                                                ({{ keyword.aliases.join(', ') }})
                                                            </span>
                                                        </div>
                                                    </div>
                                                    <div class="flex items-center gap-2">
                                                        <button
                                                            @click.stop="editKeyword(keyword)"
                                                            class="px-3 py-1 text-xs bg-slate-100 hover:bg-slate-200 text-slate-700 rounded transition">
                                                            編輯
                                                        </button>
                                                        <button
                                                            @click.stop="deleteKeyword(keyword.id)"
                                                            class="px-3 py-1 text-xs bg-red-100 hover:bg-red-200 text-red-700 rounded transition">
                                                            刪除
                                                        </button>
                                                    </div>
                                                </div>
                                            </button>
                                            
                                            <!-- 關鍵字編輯器（展開時顯示） -->
                                            <div v-if="isKeywordExpanded(keyword.id)" class="p-4 border-t border-slate-200">
                                                <div class="space-y-4">
                                                    <div>
                                                        <label class="block text-sm font-medium text-slate-700 mb-2">關鍵字</label>
                                                        <input 
                                                            type="text"
                                                            v-model="keywordEdits[keyword.id].keyword"
                                                            class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-1 focus:ring-primary focus:border-primary outline-none text-sm font-mono"
                                                            placeholder="/help"
                                                        />
                                                    </div>
                                                    
                                                    <div>
                                                        <label class="block text-sm font-medium text-slate-700 mb-2">別名</label>
                                                        <!-- Tag 標籤顯示區 -->
                                                        <div class="flex flex-wrap gap-2 mb-2 p-3 border border-slate-300 rounded-lg min-h-[42px] bg-white">
                                                            <span 
                                                                v-for="(alias, index) in keywordEdits[keyword.id].aliases" 
                                                                :key="index"
                                                                class="inline-flex items-center gap-1 px-3 py-1 bg-primary/10 text-primary rounded-full text-sm font-medium">
                                                                {{ alias }}
                                                                <button 
                                                                    @click="removeAlias(keyword.id, index)"
                                                                    class="ml-1 text-primary hover:text-primary/70 focus:outline-none">
                                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                                    </svg>
                                                                </button>
                                                            </span>
                                                            <input 
                                                                type="text"
                                                                v-model="keywordEdits[keyword.id].aliasInput"
                                                                @keydown.enter.prevent="addAlias(keyword.id)"
                                                                @keydown.comma.prevent="addAlias(keyword.id)"
                                                                class="flex-1 min-w-[120px] px-2 py-1 border-0 outline-none text-sm"
                                                                placeholder="輸入別名後按 Enter"
                                                            />
                                                        </div>
                                                        <p class="text-xs text-slate-500 mt-1">輸入別名後按 Enter 鍵新增標籤</p>
                                                    </div>
                                                    
                                                    <div>
                                                        <label class="block text-sm font-medium text-slate-700 mb-2">回覆訊息</label>
                                                        <textarea 
                                                            v-model="keywordEdits[keyword.id].message"
                                                            rows="8"
                                                            class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-1 focus:ring-primary focus:border-primary outline-none text-sm font-mono bg-white"
                                                            placeholder="輸入回覆訊息內容..."></textarea>
                                                    </div>
                                                    
                                                    <div class="flex justify-end gap-2">
                                                        <button
                                                            @click="cancelKeywordEdit(keyword.id)"
                                                            class="buygo-btn buygo-btn-secondary">
                                                            取消
                                                        </button>
                                                        <button
                                                            @click="saveKeyword(keyword.id)"
                                                            class="buygo-btn buygo-btn-primary">
                                                            儲存
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </template>
                                </draggable>
                            </div>
                        </div>
                    </div>
                </template>
                
                <!-- 客戶和賣家標籤：正常顯示模板列表 -->
                <template v-else>
                    <draggable 
                        v-model="sortedTemplates[activeTemplateTab]"
                        @end="onTemplateDragEnd"
                        :animation="200"
                        handle=".drag-handle"
                        item-key="key"
                        class="space-y-4">
                        <template #item="{ element: template }">
                            <div class="border border-slate-200 rounded-lg overflow-hidden">
                                <!-- 標題列（可點擊展開/收合） -->
                                <button 
                                    @click="toggleTemplate(template.key)"
                                    class="w-full px-4 py-3 flex items-center justify-between bg-slate-50 hover:bg-slate-100 transition text-left">
                                    <div class="flex items-center gap-3">
                                        <!-- 拖拉把手 -->
                                        <div 
                                            class="drag-handle cursor-move text-slate-400 hover:text-slate-600"
                                            @mousedown.stop
                                            @click.stop>
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"></path>
                                            </svg>
                                        </div>
                                        <svg 
                                            :class="['w-5 h-5 text-slate-400 transition-transform', isTemplateExpanded(template.key) ? 'rotate-90' : '']"
                                            fill="none" 
                                            stroke="currentColor" 
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                        </svg>
                                        <div class="flex-1">
                                            <div class="font-semibold text-slate-900 md:text-base text-sm whitespace-nowrap overflow-hidden text-ellipsis">{{ template.name }}</div>
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
                                                                @click="copyVariable(variable, template.key); closeVariableDropdown(template.key)"
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
                    </draggable>
                </template>
            </div>
            
            <div class="mt-6 flex justify-end">
                <button
                    @click="saveTemplates"
                    :disabled="savingTemplates"
                    class="buygo-btn buygo-btn-primary">
                    <span v-if="savingTemplates">儲存中...</span>
                    <span v-else>儲存</span>
                </button>
            </div>
        </div>

        <!-- 會員管理（僅管理員可見） -->
        <div v-if="isAdmin" class="buygo-card">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold text-slate-900 flex items-center">
                    <svg class="w-5 h-5 mr-2 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                    </svg>
                    會員管理
                </h2>
                <!-- 新增小幫手按鈕（列表視圖時顯示） -->
                <button
                    @click="memberView = 'add'"
                    v-if="memberView === 'list'"
                    class="buygo-btn buygo-btn-primary flex items-center">
                    <svg class="w-4 h-4 md:mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                    <span class="hidden md:inline">新增小幫手</span>
                </button>
                <!-- 返回列表按鈕（新增視圖時顯示） -->
                <button
                    @click="memberView = 'list'; userSearchQuery = ''; userSearchResults = []; showRecentUsers = false"
                    v-if="memberView === 'add'"
                    class="buygo-btn buygo-btn-secondary flex items-center">
                    <svg class="w-4 h-4 md:mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    <span class="hidden md:inline">返回列表</span>
                </button>
            </div>

            <!-- 子分頁：列表視圖 -->
            <div v-if="memberView === 'list'">
                <!-- 載入狀態 -->
                <div v-if="loadingHelpers" class="buygo-loading">
                    <div class="buygo-loading-spinner"></div>
                    <p>載入中...</p>
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
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-3">
                                            <img :src="helper.avatar || 'https://www.gravatar.com/avatar/?d=mp&s=100'" :alt="helper.name" class="w-10 h-10 rounded-full bg-slate-100 shrink-0 border border-slate-200 object-cover">
                                            <span class="text-sm font-medium text-slate-900">{{ helper.name }}</span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-slate-600">{{ helper.email }}</td>
                                    <td class="px-4 py-3">
                                        <button
                                            @click="removeHelper(helper.id)"
                                            class="text-red-600 hover:text-red-700 text-sm font-medium flex items-center">
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                            </svg>
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
                                <div class="flex items-center gap-3">
                                    <img :src="helper.avatar || 'https://www.gravatar.com/avatar/?d=mp&s=100'" :alt="helper.name" class="w-10 h-10 rounded-full bg-slate-100 shrink-0 border border-slate-200 object-cover">
                                    <div>
                                        <div class="text-base font-bold text-slate-900">{{ helper.name }}</div>
                                        <div class="text-sm text-slate-600">{{ helper.email }}</div>
                                    </div>
                                </div>
                                <button
                                    @click="removeHelper(helper.id)"
                                    class="px-3 py-2 bg-red-50 text-red-600 rounded-lg text-sm font-medium hover:bg-red-100 flex items-center shrink-0">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>
                        <div v-if="helpers.length === 0" class="text-center py-8 text-slate-500">
                            尚無小幫手
                        </div>
                    </div>
                </div>
            </div>

            <!-- 子分頁：新增小幫手 -->
            <div v-if="memberView === 'add'">
                <p class="text-sm text-slate-600 mb-4">搜尋 WordPress 使用者，點擊即可新增為小幫手</p>

                <!-- 搜尋框 -->
                <div class="relative mb-4">
                    <div class="flex gap-2">
                        <div class="relative flex-1">
                            <input
                                ref="userSearchInput"
                                v-model="userSearchQuery"
                                @input="searchUsers"
                                @focus="onUserSearchFocus"
                                @blur="onUserSearchBlur"
                                type="text"
                                class="w-full pl-10 pr-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary outline-none"
                                placeholder="搜尋使用者名稱或 Email...">
                            <svg class="w-5 h-5 text-slate-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                        </div>
                        <button
                            @click="searchUsers"
                            :disabled="!userSearchQuery"
                            class="buygo-btn buygo-btn-primary px-4">
                            搜尋
                        </button>
                    </div>

                    <!-- 搜尋提示下拉選單（點擊搜尋框時顯示最新會員） -->
                    <div
                        v-if="showRecentUsers && recentUsers.length > 0 && !userSearchQuery"
                        class="absolute z-10 w-full mt-1 bg-white border border-slate-200 rounded-lg shadow-lg">
                        <div class="px-3 py-2 text-xs text-slate-500 border-b border-slate-100">最近加入的會員</div>
                        <button
                            v-for="user in recentUsers"
                            :key="user.id"
                            @mousedown.prevent="selectUser(user)"
                            class="w-full px-4 py-3 text-left hover:bg-slate-50 transition flex items-center gap-3 border-b border-slate-100 last:border-b-0">
                            <img :src="user.avatar || 'https://www.gravatar.com/avatar/?d=mp&s=100'" :alt="user.name" class="w-8 h-8 rounded-full bg-slate-100 shrink-0 border border-slate-200 object-cover">
                            <div class="flex-1 min-w-0">
                                <div class="font-medium text-slate-900 truncate">{{ user.name }}</div>
                                <div class="text-sm text-slate-500 truncate">{{ user.email }}</div>
                            </div>
                        </button>
                    </div>
                </div>

                <!-- 搜尋中狀態 -->
                <div v-if="searchingUsers" class="py-8 text-center">
                    <div class="buygo-loading-spinner mx-auto mb-2"></div>
                    <p class="text-slate-500 text-sm">搜尋中...</p>
                </div>

                <!-- 搜尋結果 -->
                <div v-else-if="userSearchResults.length > 0" class="space-y-2">
                    <p class="text-sm text-slate-500 mb-2">找到 {{ userSearchResults.length }} 位使用者</p>
                    <div class="border border-slate-200 rounded-lg divide-y divide-slate-200">
                        <button
                            v-for="user in userSearchResults"
                            :key="user.id"
                            @click="selectUser(user)"
                            class="w-full px-4 py-3 text-left hover:bg-slate-50 transition flex items-center gap-3">
                            <img :src="user.avatar || 'https://www.gravatar.com/avatar/?d=mp&s=100'" :alt="user.name" class="w-10 h-10 rounded-full bg-slate-100 shrink-0 border border-slate-200 object-cover">
                            <div class="flex-1 min-w-0">
                                <div class="font-medium text-slate-900 truncate">{{ user.name }}</div>
                                <div class="text-sm text-slate-500 truncate">{{ user.email }}</div>
                            </div>
                            <svg class="w-5 h-5 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- 無結果 -->
                <div v-else-if="userSearchQuery && !searchingUsers" class="py-8 text-center">
                    <svg class="w-12 h-12 text-slate-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <p class="text-slate-500">找不到符合「{{ userSearchQuery }}」的使用者</p>
                    <p class="text-sm text-slate-400 mt-1">請嘗試其他關鍵字</p>
                </div>

                <!-- 初始狀態提示 -->
                <div v-else-if="!showRecentUsers" class="py-8 text-center">
                    <svg class="w-12 h-12 text-slate-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                    <p class="text-slate-500">輸入使用者名稱或 Email 來搜尋</p>
                    <p class="text-sm text-slate-400 mt-1">或點擊搜尋框查看最近加入的會員</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- 新增關鍵字 Modal -->
    <div v-if="showAddKeywordModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50" @click.self="showAddKeywordModal = false">
        <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="p-6 border-b border-slate-200">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-slate-900">新增關鍵字</h3>
                    <button @click="showAddKeywordModal = false" class="text-slate-400 hover:text-slate-600 transition">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            </div>
            
            <div class="p-6 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">關鍵字</label>
                    <input 
                        type="text"
                        v-model="newKeyword.keyword"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-1 focus:ring-primary focus:border-primary outline-none text-sm font-mono"
                        placeholder="/help"
                    />
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">別名</label>
                    <!-- Tag 標籤顯示區 -->
                    <div class="flex flex-wrap gap-2 mb-2 p-3 border border-slate-300 rounded-lg min-h-[42px] bg-white">
                        <span 
                            v-for="(alias, index) in newKeyword.aliases" 
                            :key="index"
                            class="inline-flex items-center gap-1 px-3 py-1 bg-primary/10 text-primary rounded-full text-sm font-medium">
                            {{ alias }}
                            <button 
                                @click="removeNewAlias(index)"
                                class="ml-1 text-primary hover:text-primary/70 focus:outline-none">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </span>
                        <input 
                            type="text"
                            v-model="newKeyword.aliasInput"
                            @keydown.enter.prevent="addNewAlias()"
                            @keydown.comma.prevent="addNewAlias()"
                            class="flex-1 min-w-[120px] px-2 py-1 border-0 outline-none text-sm"
                            placeholder="輸入別名後按 Enter"
                        />
                    </div>
                    <p class="text-xs text-slate-500 mt-1">輸入別名後按 Enter 鍵新增標籤</p>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">回覆訊息</label>
                    <textarea 
                        v-model="newKeyword.message"
                        rows="8"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-1 focus:ring-primary focus:border-primary outline-none text-sm font-mono bg-white"
                        placeholder="輸入回覆訊息內容..."></textarea>
                </div>
            </div>
            
            <div class="p-6 border-t border-slate-200 flex justify-end gap-2">
                <button
                    @click="showAddKeywordModal = false"
                    class="buygo-btn buygo-btn-secondary">
                    取消
                </button>
                <button
                    @click="addKeywordFromModal"
                    class="buygo-btn buygo-btn-primary">
                    新增
                </button>
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
    components: {
        draggable: vuedraggable
    },
    setup() {
        const { ref, onMounted, onUnmounted } = Vue;

        // WordPress REST API nonce（用於 API 認證）
        const wpNonce = '<?php echo wp_create_nonce("wp_rest"); ?>';

        // UI 狀態（全域搜尋）
        const showMobileSearch = ref(false);
        const globalSearchQuery = ref('');

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
        const keywordEdits = ref({});
        const expandedKeywordsSet = ref(new Set());
        const showAddKeywordModal = ref(false);
        const editingKeywordId = ref(null);
        const newKeyword = ref({
            keyword: '',
            aliases: [],
            aliasInput: '',
            message: ''
        });
        
        // 拖拉排序狀態
        const sortedTemplates = ref({
            buyer: [],
            seller: []
        });
        const sortedSystemTemplates = ref([]);
        
        // 小幫手管理狀態
        const helpers = ref([]);
        const loadingHelpers = ref(false);
        const isAdmin = ref(false);

        // 會員管理子分頁狀態
        const memberView = ref('list'); // 'list' | 'add'
        const userSearchQuery = ref('');
        const userSearchResults = ref([]);
        const searchingUsers = ref(false);
        const showRecentUsers = ref(false);
        const recentUsers = ref([]);
        const userSearchInput = ref(null);
        
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

        // 全域搜尋處理
        const handleGlobalSearch = (event) => {
            // 預留給未來實作全域搜尋邏輯
            // TODO: 實作全域搜尋功能
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
                    name: '圖片上傳成功發送',
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

        // 初始化排序模板列表（從資料庫讀取順序）
        const initSortedTemplates = async () => {
            try {
                // 從 API 讀取模板順序
                const response = await fetch('/wp-json/buygo-plus-one/v1/settings/templates/order', {
                    headers: { 'X-WP-Nonce': wpNonce },
                    credentials: 'include'
                });
                
                if (response.ok) {
                    const result = await response.json();
                    if (result.success && result.data) {
                        const orderData = result.data;
                        
                        // 排序買家模板
                        if (orderData.buyer) {
                            sortedTemplates.value.buyer = sortTemplatesByOrder(templateDefinitions.buyer || [], orderData.buyer);
                        } else {
                            sortedTemplates.value.buyer = [...(templateDefinitions.buyer || [])];
                        }
                        
                        // 排序賣家模板
                        if (orderData.seller) {
                            sortedTemplates.value.seller = sortTemplatesByOrder(templateDefinitions.seller || [], orderData.seller);
                        } else {
                            sortedTemplates.value.seller = [...(templateDefinitions.seller || [])];
                        }
                        
                        // 排序系統模板
                        if (orderData.system) {
                            sortedSystemTemplates.value = sortTemplatesByOrder(getSystemNotificationTemplates(), orderData.system);
                        } else {
                            sortedSystemTemplates.value = getSystemNotificationTemplates();
                        }
                    } else {
                        // 如果沒有順序資料，使用預設順序
                        sortedTemplates.value.buyer = [...(templateDefinitions.buyer || [])];
                        sortedTemplates.value.seller = [...(templateDefinitions.seller || [])];
                        sortedSystemTemplates.value = getSystemNotificationTemplates();
                    }
                } else {
                    // 如果 API 失敗，使用預設順序
                    sortedTemplates.value.buyer = [...(templateDefinitions.buyer || [])];
                    sortedTemplates.value.seller = [...(templateDefinitions.seller || [])];
                    sortedSystemTemplates.value = getSystemNotificationTemplates();
                }
            } catch (err) {
                console.error('讀取模板順序錯誤:', err);
                // 使用預設順序
                sortedTemplates.value.buyer = [...(templateDefinitions.buyer || [])];
                sortedTemplates.value.seller = [...(templateDefinitions.seller || [])];
                sortedSystemTemplates.value = getSystemNotificationTemplates();
            }
        };
        
        // 根據順序排序模板
        const sortTemplatesByOrder = (templates, orderData) => {
            const orderMap = {};
            orderData.forEach(item => {
                orderMap[item.key] = item.order;
            });
            
            return templates.sort((a, b) => {
                const orderA = orderMap[a.key] !== undefined ? orderMap[a.key] : 999;
                const orderB = orderMap[b.key] !== undefined ? orderMap[b.key] : 999;
                return orderA - orderB;
            });
        };
        
        // 取得當前標籤的模板列表（使用排序後的列表）
        const getTemplatesByTab = (tab) => {
            if (tab === 'buyer' || tab === 'seller') {
                return sortedTemplates.value[tab] || [];
            }
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
            const filtered = templates
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
            return filtered;
        };
        
        // 模板拖拉結束處理
        const onTemplateDragEnd = async () => {
            const tab = activeTemplateTab.value;
            if (tab === 'buyer' || tab === 'seller') {
                await saveTemplateOrder(tab, sortedTemplates.value[tab]);
            }
        };
        
        // 系統模板拖拉結束處理
        const onSystemTemplateDragEnd = async () => {
            await saveTemplateOrder('system', sortedSystemTemplates.value);
        };
        
        // 儲存模板順序
        const saveTemplateOrder = async (tab, templates) => {
            try {
                const order = templates.map((template, index) => ({
                    key: template.key,
                    order: index
                }));
                
                const response = await fetch('/wp-json/buygo-plus-one/v1/settings/templates/order', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': wpNonce
                    },
                    credentials: 'include',
                    body: JSON.stringify({
                        tab: tab,
                        order: order
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showToast('順序已儲存', 'success');
                } else {
                    showToast('儲存順序失敗：' + result.message, 'error');
                }
            } catch (err) {
                console.error('儲存順序錯誤:', err);
                showToast('儲存順序失敗', 'error');
            }
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
        
        // 插入變數到模板內容（直接插入，不複製到剪貼簿）
        const copyVariable = (variable, templateKey = null) => {
            const variableText = `{${variable}}`;
            
            // 如果提供了 templateKey，直接插入到對應的模板
            if (templateKey && templateEdits.value[templateKey]) {
                const template = templateEdits.value[templateKey];
                
                // 文字模板：插入到 line.message
                if (template.line && template.line.message !== undefined) {
                    const currentMessage = template.line.message || '';
                    // 嘗試從當前焦點的 textarea 取得游標位置
                    const activeElement = document.activeElement;
                    let cursorPos = currentMessage.length;
                    
                    if (activeElement && activeElement.tagName === 'TEXTAREA' && activeElement.value === currentMessage) {
                        cursorPos = activeElement.selectionStart || currentMessage.length;
                    }
                    
                    const textBefore = currentMessage.substring(0, cursorPos);
                    const textAfter = currentMessage.substring(cursorPos);
                    template.line.message = textBefore + variableText + textAfter;
                    
                    // 設定游標位置（使用 setTimeout 確保 DOM 更新後再設定）
                    setTimeout(() => {
                        const textarea = document.querySelector(`textarea[v-model*="${templateKey}"]`);
                        if (textarea && textarea.value === template.line.message) {
                            const newPos = cursorPos + variableText.length;
                            textarea.setSelectionRange(newPos, newPos);
                            textarea.focus();
                        }
                    }, 0);
                }
                // Flex Message 模板：插入到 description
                else if (template.line && template.line.flex_template && template.line.flex_template.description !== undefined) {
                    const currentDesc = template.line.flex_template.description || '';
                    const activeElement = document.activeElement;
                    let cursorPos = currentDesc.length;
                    
                    if (activeElement && activeElement.tagName === 'TEXTAREA' && activeElement.value === currentDesc) {
                        cursorPos = activeElement.selectionStart || currentDesc.length;
                    }
                    
                    const textBefore = currentDesc.substring(0, cursorPos);
                    const textAfter = currentDesc.substring(cursorPos);
                    template.line.flex_template.description = textBefore + variableText + textAfter;
                    
                    // 設定游標位置
                    setTimeout(() => {
                        const textarea = document.querySelector(`textarea[v-model*="${templateKey}"][v-model*="description"]`);
                        if (textarea && textarea.value === template.line.flex_template.description) {
                            const newPos = cursorPos + variableText.length;
                            textarea.setSelectionRange(newPos, newPos);
                            textarea.focus();
                        }
                    }, 0);
                }
                
                copyToast.value = { show: true, message: `已插入 ${variableText}` };
                setTimeout(() => {
                    copyToast.value.show = false;
                }, 2000);
                return;
            }
            
            // 備用方案：如果找不到對應模板，則複製到剪貼簿
            try {
                navigator.clipboard.writeText(variableText).then(() => {
                    copyToast.value = { show: true, message: `已複製 ${variableText}` };
                    setTimeout(() => {
                        copyToast.value.show = false;
                    }, 2000);
                });
            } catch (err) {
                console.error('複製失敗:', err);
                copyToast.value = { show: true, message: '插入失敗，請手動輸入' };
                setTimeout(() => {
                    copyToast.value.show = false;
                }, 2000);
            }
        };

        // 載入模板設定
        const loadTemplates = async () => {
            try {
                const response = await fetch('/wp-json/buygo-plus-one/v1/settings/templates', {
                    headers: { 'X-WP-Nonce': wpNonce },
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
                            
                            // 如果沒有模板資料，使用空值
                            if (!templateData) {
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
                                return;
                            }
                            
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
                                // 確保能讀取到後台儲存的內容
                                const message = templateData?.line?.message || 
                                             templateData?.line?.text || 
                                             (templateData?.line ? '' : '') || 
                                             '';
                                
                                templateEdits.value[template.key] = {
                                    line: {
                                        message: message || ''
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
                        'X-WP-Nonce': wpNonce
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
                    headers: { 'X-WP-Nonce': wpNonce },
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
                    headers: { 'X-WP-Nonce': wpNonce },
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
            showRecentUsers.value = false;

            if (!userSearchQuery.value || userSearchQuery.value.length < 2) {
                userSearchResults.value = [];
                return;
            }

            searchingUsers.value = true;

            try {
                const response = await fetch(`/wp-json/buygo-plus-one/v1/settings/users/search?query=${encodeURIComponent(userSearchQuery.value)}`, {
                    headers: { 'X-WP-Nonce': wpNonce },
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

        // 載入最新會員（用於搜尋框提示）
        const loadRecentUsers = async () => {
            try {
                const response = await fetch('/wp-json/buygo-plus-one/v1/settings/users/recent', {
                    headers: { 'X-WP-Nonce': wpNonce },
                    credentials: 'include'
                });

                const result = await response.json();

                if (result.success && result.data) {
                    recentUsers.value = result.data;
                }
            } catch (err) {
                console.error('載入最新會員錯誤:', err);
            }
        };

        // 搜尋框取得焦點
        const onUserSearchFocus = () => {
            if (!userSearchQuery.value) {
                showRecentUsers.value = true;
                if (recentUsers.value.length === 0) {
                    loadRecentUsers();
                }
            }
        };

        // 搜尋框失去焦點
        const onUserSearchBlur = () => {
            // 延遲關閉，讓點擊事件可以先觸發
            setTimeout(() => {
                showRecentUsers.value = false;
            }, 200);
        };

        // 選擇使用者
        const selectUser = async (user) => {
            showRecentUsers.value = false;

            try {
                const response = await fetch('/wp-json/buygo-plus-one/v1/settings/helpers', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': wpNonce
                    },
                    credentials: 'include',
                    body: JSON.stringify({ user_id: user.id })
                });

                const result = await response.json();

                if (result.success) {
                    showToast('小幫手已新增', 'success');
                    // 清空搜尋並返回列表
                    userSearchQuery.value = '';
                    userSearchResults.value = [];
                    memberView.value = 'list';
                    await loadHelpers();
                } else {
                    showToast('新增失敗：' + result.message, 'error');
                }
            } catch (err) {
                console.error('新增小幫手錯誤:', err);
                showToast('新增失敗', 'error');
            }
        };
        
        // 檢查是否為管理員
        const checkAdmin = async () => {
            try {
                const response = await fetch('/wp-json/buygo-plus-one/v1/settings/user/permissions', {
                    headers: { 'X-WP-Nonce': wpNonce },
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
        
        // 載入關鍵字列表
        const loadKeywords = async () => {
            loadingKeywords.value = true;
            
            try {
                const response = await fetch('/wp-json/buygo-plus-one/v1/settings/line-keywords', {
                    headers: { 'X-WP-Nonce': wpNonce },
                    credentials: 'include'
                });

                // 檢查 HTTP 狀態
                if (!response.ok) {
                    console.error('API 回應錯誤:', response.status, response.statusText);
                    showToast(`載入關鍵字列表失敗 (${response.status})`, 'error');
                    return;
                }
                
                const result = await response.json();

                if (result.success && result.data && Array.isArray(result.data)) {
                    keywords.value = result.data.map(kw => ({
                        ...kw,
                        aliases: Array.isArray(kw.aliases) ? kw.aliases : []
                    }));
                    
                    // 初始化編輯資料
                    keywords.value.forEach(keyword => {
                        if (!keywordEdits.value[keyword.id]) {
                            keywordEdits.value[keyword.id] = {
                                keyword: keyword.keyword || '',
                                aliases: Array.isArray(keyword.aliases) ? [...keyword.aliases] : [],
                                aliasInput: '',
                                message: keyword.message || ''
                            };
                        }
                    });
                } else {
                    console.warn('關鍵字 API 回應格式不正確:', result);
                    keywords.value = [];
                }
            } catch (err) {
                console.error('載入關鍵字列表錯誤:', err);
                showToast('載入關鍵字列表失敗: ' + err.message, 'error');
                keywords.value = [];
            } finally {
                loadingKeywords.value = false;
            }
        };
        
        // 切換關鍵字展開/收合
        const toggleKeyword = (keywordId) => {
            if (expandedKeywordsSet.value.has(keywordId)) {
                expandedKeywordsSet.value.delete(keywordId);
            } else {
                expandedKeywordsSet.value.add(keywordId);
            }
        };
        
        // 檢查關鍵字是否展開
        const isKeywordExpanded = (keywordId) => {
            return expandedKeywordsSet.value.has(keywordId);
        };
        
        // 關鍵字拖拉結束處理
        const onKeywordDragEnd = async () => {
            await saveKeywords();
        };
        
        // 儲存關鍵字列表
        const saveKeywords = async () => {
            try {
                // 更新 order
                keywords.value.forEach((keyword, index) => {
                    keyword.order = index;
                });
                
                const response = await fetch('/wp-json/buygo-plus-one/v1/settings/line-keywords', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': wpNonce
                    },
                    credentials: 'include',
                    body: JSON.stringify({
                        keywords: keywords.value.map(kw => ({
                            id: kw.id,
                            keyword: kw.keyword,
                            aliases: kw.aliases || [],
                            message: kw.message || '',
                            order: kw.order || 0
                        }))
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showToast('關鍵字順序已儲存', 'success');
                } else {
                    showToast('儲存失敗：' + result.message, 'error');
                }
            } catch (err) {
                console.error('儲存關鍵字列表錯誤:', err);
                showToast('儲存失敗', 'error');
            }
        };
        
        // 編輯關鍵字
        const editKeyword = (keyword) => {
            editingKeywordId.value = keyword.id;
            toggleKeyword(keyword.id);
        };
        
        // 儲存單個關鍵字
        const saveKeyword = async (keywordId) => {
            const edit = keywordEdits.value[keywordId];
            if (!edit) {
                return;
            }
            
            // 處理別名（如果還有 aliasInput，先加入）
            if (edit.aliasInput && edit.aliasInput.trim()) {
                addAlias(keywordId);
            }
            
            // 使用 aliases 陣列
            const aliases = Array.isArray(edit.aliases) ? [...edit.aliases] : [];
            
            // 更新關鍵字資料
            const keywordIndex = keywords.value.findIndex(kw => kw.id === keywordId);
            if (keywordIndex !== -1) {
                keywords.value[keywordIndex] = {
                    ...keywords.value[keywordIndex],
                    keyword: edit.keyword,
                    aliases: aliases,
                    message: edit.message
                };
            }
            
            // 儲存到後端
            await saveKeywords();
            
            editingKeywordId.value = null;
            showToast('關鍵字已儲存', 'success');
        };
        
        // 取消編輯
        const cancelKeywordEdit = (keywordId) => {
            const keyword = keywords.value.find(kw => kw.id === keywordId);
            if (keyword) {
                keywordEdits.value[keywordId] = {
                    keyword: keyword.keyword || '',
                    aliases: Array.isArray(keyword.aliases) ? [...keyword.aliases] : [],
                    aliasInput: '',
                    message: keyword.message || ''
                };
            }
            expandedKeywordsSet.value.delete(keywordId);
            editingKeywordId.value = null;
        };
        
        // 新增別名標籤（編輯模式）
        const addAlias = (keywordId) => {
            const edit = keywordEdits.value[keywordId];
            if (edit && edit.aliasInput && edit.aliasInput.trim()) {
                const alias = edit.aliasInput.trim();
                if (!edit.aliases.includes(alias)) {
                    edit.aliases.push(alias);
                }
                edit.aliasInput = '';
            }
        };
        
        // 移除別名標籤（編輯模式）
        const removeAlias = (keywordId, index) => {
            const edit = keywordEdits.value[keywordId];
            if (edit && edit.aliases) {
                edit.aliases.splice(index, 1);
            }
        };
        
        // 新增別名標籤（新增模式）
        const addNewAlias = () => {
            if (newKeyword.value.aliasInput && newKeyword.value.aliasInput.trim()) {
                const alias = newKeyword.value.aliasInput.trim();
                if (!newKeyword.value.aliases.includes(alias)) {
                    newKeyword.value.aliases.push(alias);
                }
                newKeyword.value.aliasInput = '';
            }
        };
        
        // 移除別名標籤（新增模式）
        const removeNewAlias = (index) => {
            newKeyword.value.aliases.splice(index, 1);
        };
        
        // 刪除關鍵字
        const deleteKeyword = async (keywordId) => {
            if (!confirm('確定要刪除這個關鍵字嗎？')) {
                return;
            }
            
            keywords.value = keywords.value.filter(kw => kw.id !== keywordId);
            delete keywordEdits.value[keywordId];
            expandedKeywordsSet.value.delete(keywordId);
            
            await saveKeywords();
            showToast('關鍵字已刪除', 'success');
        };
        
        // 從 Modal 新增關鍵字
        const addKeywordFromModal = () => {
            if (!newKeyword.value.keyword || !newKeyword.value.keyword.trim()) {
                showToast('請輸入關鍵字', 'error');
                return;
            }
            
            // 處理別名（如果還有 aliasInput，先加入）
            if (newKeyword.value.aliasInput && newKeyword.value.aliasInput.trim()) {
                addNewAlias();
            }
            
            const newId = 'kw_' + Date.now();
            const aliases = Array.isArray(newKeyword.value.aliases) ? [...newKeyword.value.aliases] : [];
            
            const keywordData = {
                id: newId,
                keyword: newKeyword.value.keyword.trim(),
                aliases: aliases,
                message: newKeyword.value.message || '',
                order: keywords.value.length
            };
            
            keywords.value.push(keywordData);
            keywordEdits.value[newId] = {
                keyword: keywordData.keyword,
                aliasesText: aliases.join(', '),
                message: keywordData.message
            };
            
            // 儲存到後端
            saveKeywords().then(() => {
                showAddKeywordModal.value = false;
                newKeyword.value = {
                    keyword: '',
                    aliases: [],
                    aliasInput: '',
                    message: ''
                };
                toggleKeyword(newId);
                editingKeywordId.value = newId;
            });
        };
        
        // 初始化
        onMounted(async () => {
            await checkAdmin();
            await loadTemplates();
            await loadHelpers();
            await loadKeywords();
            await initSortedTemplates();
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
            // 會員管理子分頁
            memberView,
            userSearchQuery,
            userSearchResults,
            searchingUsers,
            searchUsers,
            selectUser,
            showRecentUsers,
            recentUsers,
            onUserSearchFocus,
            onUserSearchBlur,
            userSearchInput,
            toastMessage,
            sortedTemplates,
            sortedSystemTemplates,
            onTemplateDragEnd,
            onSystemTemplateDragEnd,
            keywords,
            loadingKeywords,
            loadKeywords,
            toggleKeyword,
            isKeywordExpanded,
            onKeywordDragEnd,
            editKeyword,
            saveKeyword,
            cancelKeywordEdit,
            deleteKeyword,
            keywordEdits,
            showAddKeywordModal,
            addKeywordFromModal,
            newKeyword,
            addAlias,
            removeAlias,
            addNewAlias,
            removeNewAlias,
            editingKeywordId,
            expandedKeywordsSet,
            showToast,
            // 全域搜尋相關
            showMobileSearch,
            globalSearchQuery,
            handleGlobalSearch
        };
    }
};
</script>

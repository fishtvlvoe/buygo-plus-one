# BuyGo+1 開發任務清單

> **最後更新**: 2026-01-24
> **當前環境**: 舊版與新版共存問題已解決 ✅
> **可安全開發**: buygo-plus-one-dev 環境

---

## 🎯 任務總覽

| 優先級 | 類別 | 任務數 | 預估工時 | 狀態 |
|--------|------|--------|---------|------|
| 🔴 P1 | 安全性修復 | 4 | 1.5h | ⏸️ 待執行 |
| 🟡 P2 | 功能性修復 | 2 | 0.5h | ⏸️ 待執行 |
| 🟠 P3 | 代碼品質 | 4 | 1h | ⏸️ 待執行 |
| 🔵 P4 | 會員權限系統 | 5 | 8-10h | ⏸️ 待執行 |
| 🟢 P5 | UI 優化 | 7 | 53h | ⏸️ 待執行（可選）|

---

## 🔴 Priority 1: 安全性修復（高優先級）

> **參考文件**: [repair-strategy.md](planning/repair-strategy.md)
> **預估總工時**: 1.5 小時
> **建議執行順序**: 依序執行 → 測試 → Git commit

### Task 1.1: 修復 Debug API 權限

**檔案**: `includes/api/class-debug-api.php`
**風險等級**: 🔴 高（暴露系統除錯資訊）
**預估工時**: 20 分鐘

#### 執行步驟

1. **檢查當前權限設定**
   ```bash
   grep -n "permission_callback" includes/api/class-debug-api.php
   ```

2. **修改為管理員專用**
   - 找到所有 `register_rest_route()` 呼叫
   - 將 `'permission_callback' => '__return_true'` 改為：
     ```php
     'permission_callback' => function() {
         return current_user_can('manage_options');
     }
     ```

3. **測試驗證**
   ```bash
   # 未登入測試（應該返回 401）
   curl -X GET "http://localhost/wp-json/buygo-plus-one/v1/debug"

   # 管理員登入測試（應該返回 200 + 資料）
   curl -X GET "http://localhost/wp-json/buygo-plus-one/v1/debug" \
     -H "X-WP-Nonce: [管理員 nonce]"
   ```

4. **Git commit**
   ```bash
   git add includes/api/class-debug-api.php
   git commit -m "fix: restrict debug API to administrators only

   - Change permission_callback from __return_true to admin check
   - Prevent unauthorized access to debug information
   - Security risk: HIGH

   Co-Authored-By: Claude Sonnet 4.5 <noreply@anthropic.com>"
   ```

#### 驗收標準

- [ ] 未登入使用者無法訪問 Debug API (401)
- [ ] 一般使用者（subscriber）無法訪問 (403)
- [ ] 管理員可以正常訪問 (200)

---

### Task 1.2: 修復 Settings API 權限

**檔案**: `includes/api/class-settings-api.php`
**風險等級**: 🔴 高（可能修改系統設定）
**預估工時**: 20 分鐘

#### 執行步驟

1. **分析端點類型**
   ```bash
   grep -n "register_rest_route" includes/api/class-settings-api.php
   ```

2. **在 class-api.php 新增管理員權限檢查**
   ```php
   // 在 includes/api/class-api.php
   public static function check_admin_permission(): bool
   {
       if (!is_user_logged_in()) {
           return false;
       }
       return current_user_can('manage_options')
           || current_user_can('buygo_admin');
   }
   ```

3. **差異化權限控制**
   - **讀取設定** (GET) → 使用 `[API::class, 'check_permission']` (所有 BuyGo 成員)
   - **修改設定** (POST/PUT) → 使用 `[API::class, 'check_admin_permission']` (只有管理員)
   - **管理小幫手** → 使用 `[API::class, 'check_admin_permission']`

4. **測試驗證**
   ```bash
   # 小幫手嘗試修改設定（應該 403）
   curl -X POST "http://localhost/wp-json/buygo-plus-one/v1/settings" \
     -H "X-WP-Nonce: [小幫手 nonce]" \
     -d '{"key": "value"}'
   ```

5. **Git commit**
   ```bash
   git add includes/api/class-api.php includes/api/class-settings-api.php
   git commit -m "fix: add granular permission control for settings API

   - Add check_admin_permission() method
   - Read settings: all BuyGo members
   - Modify settings: admins only
   - Security risk: HIGH

   Co-Authored-By: Claude Sonnet 4.5 <noreply@anthropic.com>"
   ```

#### 驗收標準

- [ ] 小幫手可以讀取設定但不能修改
- [ ] 小幫手不能新增其他小幫手
- [ ] BuyGo 管理員可以修改設定和管理小幫手
- [ ] WordPress 管理員有完整權限

---

### Task 1.3: 修復 Global Search API 權限

**檔案**: `includes/api/class-global-search-api.php`
**風險等級**: 🟡 中（可能暴露商業資料）
**預估工時**: 15 分鐘

#### 執行步驟

1. **檢查並修改權限**
   ```bash
   grep -n "permission_callback" includes/api/class-global-search-api.php
   ```

   將 `'permission_callback' => '__return_true'` 改為：
   ```php
   'permission_callback' => [API::class, 'check_permission']
   ```

2. **測試驗證**
   ```bash
   # 未登入測試（應該 401）
   curl -X GET "http://localhost/wp-json/buygo-plus-one/v1/search?q=test"
   ```

3. **Git commit**
   ```bash
   git add includes/api/class-global-search-api.php
   git commit -m "fix: restrict global search API to logged-in users

   - Prevent unauthorized access to business data
   - Security risk: MEDIUM

   Co-Authored-By: Claude Sonnet 4.5 <noreply@anthropic.com>"
   ```

#### 驗收標準

- [ ] 未登入使用者無法使用全域搜尋 (401)
- [ ] BuyGo 成員可以正常搜尋 (200)

---

### Task 1.4: 修復 Keywords API 權限

**檔案**: `includes/api/class-keywords-api.php`
**風險等級**: 🟡 中（可能暴露商業邏輯）
**預估工時**: 15 分鐘

#### 執行步驟

同 Task 1.3 的流程。

#### Git commit

```bash
git add includes/api/class-keywords-api.php
git commit -m "fix: restrict keywords API to logged-in users

- Prevent unauthorized access to automation rules
- Security risk: MEDIUM

Co-Authored-By: Claude Sonnet 4.5 <noreply@anthropic.com>"
```

---

### Task 1.5: 整合測試與驗證

**預估工時**: 20 分鐘

#### 執行步驟

1. **運行結構驗證**
   ```bash
   bash scripts/validate-structure.sh
   ```
   預期結果：API 權限相關的 4 個錯誤消失

2. **運行 API 權限測試腳本**
   ```bash
   bash scripts/test-api-permissions.sh
   ```
   （如果腳本不存在，可以根據 repair-strategy.md 建立）

3. **手動測試**
   - 以管理員身份登入，測試所有 API
   - 以小幫手身份登入，測試權限限制
   - 未登入狀態測試

4. **記錄結果**
   更新 structure-validation-report.md 的修復狀態

---

## 🟡 Priority 2: 功能性修復（中優先級）

> **預估總工時**: 0.5 小時

### Task 2.1: 修復 settings.php wpNonce 導出

**檔案**: `admin/partials/settings.php`
**預估工時**: 10 分鐘

#### 執行步驟

1. **檢查是否真的需要**
   ```bash
   grep -n "wpNonce" admin/partials/settings.php
   ```

2. **如果有使用，則添加導出**
   在 Vue setup() 的 return 區塊中添加：
   ```javascript
   return {
       wpNonce: wpNonce,  // ✅ 添加這行
       activeTab: activeTab,
       // ... 其他已導出的變數
   }
   ```

3. **測試**
   - 打開設定頁面
   - 檢查瀏覽器 console 是否有錯誤
   - 測試新增/刪除小幫手功能

4. **Git commit**
   ```bash
   git add admin/partials/settings.php
   git commit -m "fix: export wpNonce in settings page

- Ensure API requests can access nonce token
- Prevent potential 401 errors

Co-Authored-By: Claude Sonnet 4.5 <noreply@anthropic.com>"
   ```

---

### Task 2.2: 修復 shipment-details.php Header 結構

**檔案**: `admin/partials/shipment-details.php`
**預估工時**: 15 分鐘

#### 執行步驟

1. **檢查當前結構**
   ```bash
   grep -A 10 "<header" admin/partials/shipment-details.php
   ```

2. **修改為標準結構**
   ```html
   <header class="shipment-details-header h-16 flex items-center justify-between px-6 sticky top-0 z-10 bg-white border-b border-slate-200">
       <div class="flex items-center gap-4">
           <!-- 左側：返回按鈕 + 標題 -->
           <button onclick="history.back()" class="...">← 返回</button>
           <h1 class="text-xl font-bold text-slate-900">出貨單詳情</h1>
       </div>
       <div class="flex items-center gap-3">
           <!-- 右側：操作按鈕 -->
       </div>
   </header>
   ```

3. **測試**
   - 檢查 Header 高度是否為 64px
   - 檢查是否固定在頂部
   - 測試響應式行為

4. **Git commit**

---

## 🟠 Priority 3: 代碼品質改進（低優先級）

> **預估總工時**: 1 小時
> **可選執行**：不影響功能，主要提升可維護性

### Task 3.1: 統一 CSS 前綴規範

**檔案**: `admin/partials/settings.php`
**預估工時**: 20 分鐘

使用 `settings-` 前綴或改用 Vue scoped CSS。

### Task 3.2: 添加結構註解

**預估工時**: 10 分鐘

### Task 3.3-3.4: View Switching 邏輯優化

**檔案**: `admin/partials/orders.php`, `admin/partials/shipment-details.php`
**預估工時**: 20 分鐘

統一使用 URL 參數方式。

---

## 🔵 Priority 4: 會員權限管理系統（新功能）

> **參考文件**: [member-permission-system-detailed.md](planning/member-permission-system-detailed.md)
> **預估總工時**: 8-10 小時
> **執行前提**: Priority 1-3 完成

### Phase 1: 後端 - 資料表與服務（3 小時）

#### Task 4.1: 建立 wp_buygo_helpers 資料表

**檔案**: `includes/class-database.php`
**預估工時**: 1 小時

**步驟**:
1. 在 `create_tables()` 方法中新增呼叫
2. 新增 `create_helpers_table()` 方法
3. 可選：新增 `migrate_old_helpers_data()` 方法
4. 測試資料表建立
5. Git commit

**驗收標準**:
- [ ] 資料表成功建立
- [ ] 索引正確設置
- [ ] 唯一約束正常運作

---

#### Task 4.2: 修改 Settings_Service 方法

**檔案**: `includes/services/class-settings-service.php`
**預估工時**: 2 小時

**子任務**:
- [ ] 修改 `get_helpers()` - 新增 `$seller_id` 參數，從資料表查詢
- [ ] 修改 `add_helper()` - 新增 `$seller_id` 參數，插入資料表
- [ ] 修改 `remove_helper()` - 新增 `$seller_id` 參數，智能移除角色
- [ ] 新增向後相容方法（Options API fallback）
- [ ] 單元測試（可選）
- [ ] Git commit

**驗收標準**:
- [ ] 支援多賣家隔離
- [ ] 向後相容（資料表不存在時降級）
- [ ] 智能角色管理（檢查是否還是其他人的小幫手）

---

### Phase 2: API 權限檢查完整化（1 小時）

#### Task 4.3: 完善 API 權限控制

**檔案**:
- `includes/api/class-api.php`
- `includes/api/class-products-api.php`
- `includes/api/class-orders-api.php`
- `includes/api/class-shipments-api.php`
- `includes/api/class-customers-api.php`

**步驟**:
1. 確認 `check_permission()` 包含 `buygo_helper`
2. 將所有 API 的 `__return_true` 改為 `[API::class, 'check_permission']`
3. 整合測試
4. Git commit

---

### Phase 3: 前端 UI 改造（2 小時）

#### Task 4.4: Settings 頁面 UI 更新

**檔案**: `admin/partials/settings.php`

**子任務**:
- [ ] 重新命名「小幫手管理」→「會員權限管理」
- [ ] 小幫手角色隱藏此區塊（`v-if="isAdmin"`）
- [ ] 新增「新增時間」欄位
- [ ] 改善錯誤訊息顯示
- [ ] 新增 `formatDate()` 方法
- [ ] Git commit

---

### Phase 4: FluentCommunity 整合（1 小時）

#### Task 4.5: 側邊欄連結整合

**新建檔案**: `includes/class-fluent-community.php`

**步驟**:
1. 建立 FluentCommunity 類別
2. 實作 `add_buygo_menu()` 方法
3. 在 `includes/class-plugin.php` 中載入
4. 測試
5. Git commit

---

### Phase 5: 整合測試（1-2 小時）

#### Task 4.6: 完整功能測試

**測試項目**:
- [ ] 資料表建立與遷移
- [ ] 多賣家隔離功能
- [ ] 共用小幫手場景
- [ ] 角色管理邏輯
- [ ] API 權限測試
- [ ] UI 功能測試
- [ ] FluentCommunity 整合測試

**參考**: member-permission-system-detailed.md 中的測試計畫

---

## 🟢 Priority 5: UI 組件庫優化（可選）

> **參考文件**: [phase-5-ui-optimization.md](planning/phase-5-ui-optimization.md)
> **預估總工時**: 53 小時（3 週）
> **狀態**: 可選，非必要

### 需要提取的元件（7 個）

1. **GlobalSearchBox** - 全域搜尋框（8h）
2. **NotificationHeader** - 通知訊息（5h）
3. **DataTable** - 資料表格（12h）
4. **FormElements** - 表單元素（8h）
5. **StatusTag** - 狀態標籤（4h）
6. **ActionButtons** - 操作按鈕群組（6h）
7. **ModalDialog** - 模態對話框（10h）

**建議**: 除非有明確需求，否則先完成 Priority 1-4，UI 優化可以之後再做。

---

## 📊 執行建議

### 建議的執行順序

```
Week 1: 安全性與功能性修復
├─ Day 1: Priority 1 (1.5h) - API 權限修復
├─ Day 2: Priority 2 (0.5h) - 功能性修復
└─ Day 3: Priority 3 (1h, 可選) - 代碼品質

Week 2-3: 會員權限管理系統
├─ Week 2 Day 1-2: Phase 1 - 後端 (3h)
├─ Week 2 Day 3: Phase 2 - API (1h)
├─ Week 2 Day 4: Phase 3 - 前端 (2h)
├─ Week 2 Day 5: Phase 4 - 整合 (1h)
└─ Week 3 Day 1: Phase 5 - 測試 (2h)

Week 4+: UI 優化（可選）
└─ 根據需求決定是否執行
```

### 每個任務完成後的檢查清單

- [ ] 代碼修改完成
- [ ] 本地測試通過
- [ ] Git commit（遵循 conventional commits 格式）
- [ ] 更新相關文檔
- [ ] 運行結構驗證（如適用）

### Git Commit 規範

```bash
# 格式
<type>: <subject>

<body>

Co-Authored-By: Claude Sonnet 4.5 <noreply@anthropic.com>

# Type 類型
- feat: 新功能
- fix: 修復 bug
- docs: 文檔更新
- style: 代碼格式（不影響功能）
- refactor: 重構
- perf: 性能優化
- test: 測試
- chore: 構建/工具

# 範例
git commit -m "fix: restrict debug API to administrators only

- Change permission_callback from __return_true to admin check
- Prevent unauthorized access to debug information
- Security risk: HIGH

Co-Authored-By: Claude Sonnet 4.5 <noreply@anthropic.com>"
```

---

## 🎯 當前建議：從哪裡開始？

### 立即可以開始的任務（按推薦順序）

1. **最推薦：Task 1.1-1.4 - API 權限修復** ✨
   - 只需 1.5 小時
   - 解決安全性問題
   - 立即提升系統健康度到 95+/100
   - 低風險，高收益

2. **次推薦：Task 2.1-2.2 - 功能性修復**
   - 只需 0.5 小時
   - 解決潛在功能問題
   - 低風險

3. **可選：Task 3.x - 代碼品質改進**
   - 1 小時
   - 提升可維護性
   - 不影響功能

4. **中期：Task 4.x - 會員權限管理系統**
   - 8-10 小時
   - 全新功能
   - 需要完整測試

5. **長期：Task 5.x - UI 優化**
   - 53 小時
   - 可選，非必要
   - 可以分階段執行

---

## 📝 相關文檔快速連結

- [結構修復策略](planning/repair-strategy.md) - Priority 1-3 詳細指南
- [會員權限管理詳細規格](planning/member-permission-system-detailed.md) - Priority 4 完整實作指南
- [UI 優化計畫](planning/phase-5-ui-optimization.md) - Priority 5 元件提取計畫
- [結構驗證報告](analysis/structure-validation-report.md) - 當前問題清單
- [WordPress 最佳實踐](wordpress-plugin-dev/best-practices.md) - 開發參考

---

**版本**: 1.0
**建立日期**: 2026-01-24
**維護者**: Claude Code
**狀態**: 📋 待執行

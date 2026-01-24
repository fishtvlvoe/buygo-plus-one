# BuyGo+1 結構修復策略

> **基於**: [structure-validation-report.md](../analysis/structure-validation-report.md)
> **制定日期**: 2026-01-24
> **執行時機**: 環境穩定後（舊版與新版共存測試完成）
> **預估工時**: 2-3 小時

---

## 🎯 修復總覽

### 問題分類

| 類別 | 數量 | 優先級 | 預估工時 |
|------|------|--------|---------|
| 🔴 安全性問題 | 4 個 | **高** | 1.5 小時 |
| 🟡 功能性問題 | 2 個 | 中 | 0.5 小時 |
| 🟠 代碼品質 | 4 個 | 低 | 1 小時 |
| **總計** | **10 個** | - | **3 小時** |

### 修復順序

```
Phase 1 (高優先級 - 安全性)
├── API 權限修復
│   ├── class-debug-api.php (最高風險)
│   ├── class-settings-api.php (高風險)
│   ├── class-global-search-api.php (中風險)
│   └── class-keywords-api.php (中風險)

Phase 2 (中優先級 - 功能性)
├── settings.php wpNonce 導出
└── shipment-details.php header 結構

Phase 3 (低優先級 - 代碼品質)
├── CSS 前綴規範
├── 結構註解完整性
└── view switching 優化
```

---

## 🔴 Phase 1: 安全性修復（高優先級）

### 修復 #1: class-debug-api.php - 限制為管理員專用

**檔案**: `includes/api/class-debug-api.php`
**風險等級**: 🔴 高（暴露系統除錯資訊）
**預估工時**: 20 分鐘

#### 問題分析

Debug API 端點可能暴露敏感的系統資訊，包括：
- 資料庫結構
- 外掛配置
- 系統環境變數
- 錯誤日誌

#### 修復策略

**Step 1: 檢查當前權限設定**
```bash
# 確認當前設定
grep -n "permission_callback" includes/api/class-debug-api.php
```

**Step 2: 修改權限為管理員專用**
```php
// 位置：register_rest_route() 呼叫中

// ❌ 修復前（可能）
'permission_callback' => '__return_true'

// ✅ 修復後
'permission_callback' => function() {
    return current_user_can('manage_options');
}
```

**Step 3: 測試驗證**
```bash
# 未登入測試（應該返回 401）
curl -X GET "http://localhost/wp-json/buygo-plus-one/v1/debug"

# 管理員登入測試（應該返回 200 + 資料）
curl -X GET "http://localhost/wp-json/buygo-plus-one/v1/debug" \
  -H "X-WP-Nonce: [管理員 nonce]"
```

#### 替代方案

如果 Debug API 需要給小幫手使用：
```php
'permission_callback' => [API::class, 'check_permission']
// 這樣管理員、BuyGo Admin、小幫手都可以使用
```

#### 驗收標準

- [ ] 未登入使用者無法訪問 Debug API
- [ ] 一般使用者（subscriber）無法訪問
- [ ] 管理員可以正常訪問
- [ ] 小幫手角色根據需求決定是否可訪問

---

### 修復 #2: class-settings-api.php - 限制為有權限者

**檔案**: `includes/api/class-settings-api.php`
**風險等級**: 🔴 高（可能修改系統設定）
**預估工時**: 20 分鐘

#### 問題分析

Settings API 端點可能包含：
- 修改外掛設定
- 新增/刪除小幫手
- 修改 API 金鑰
- 變更整合設定

#### 修復策略

**Step 1: 分析端點類型**

Settings API 可能包含多個端點：
- `GET /settings` - 讀取設定（較低風險）
- `POST /settings` - 修改設定（高風險）
- `POST /settings/helpers` - 管理小幫手（高風險）

**Step 2: 差異化權限控制**

```php
// 讀取設定 - BuyGo 成員都可以
register_rest_route($namespace, '/settings', [
    'methods' => 'GET',
    'callback' => [$this, 'get_settings'],
    'permission_callback' => [API::class, 'check_permission'],
]);

// 修改設定 - 只有管理員
register_rest_route($namespace, '/settings', [
    'methods' => 'POST',
    'callback' => [$this, 'update_settings'],
    'permission_callback' => function() {
        return current_user_can('manage_options')
            || current_user_can('buygo_admin');
    },
]);

// 管理小幫手 - 只有可新增小幫手的角色
register_rest_route($namespace, '/settings/helpers', [
    'methods' => 'POST',
    'callback' => [$this, 'manage_helpers'],
    'permission_callback' => [API::class, 'check_admin_permission'],
]);
```

**Step 3: 新增統一的管理員權限檢查**

在 `includes/api/class-api.php` 新增：
```php
public static function check_admin_permission(): bool
{
    if (!is_user_logged_in()) {
        return false;
    }
    return current_user_can('manage_options')
        || current_user_can('buygo_admin');
}
```

#### 驗收標準

- [ ] 小幫手可以讀取設定但不能修改
- [ ] 小幫手不能新增其他小幫手
- [ ] BuyGo 管理員可以修改設定和管理小幫手
- [ ] WordPress 管理員有完整權限

---

### 修復 #3: class-global-search-api.php - 限制登入使用者

**檔案**: `includes/api/class-global-search-api.php`
**風險等級**: 🟡 中（可能暴露商業資料）
**預估工時**: 15 分鐘

#### 問題分析

Global Search 可能搜尋：
- 客戶資料（姓名、電話、Email）
- 訂單資訊（金額、地址）
- 商品資料（成本、庫存）

#### 修復策略

**簡單修復**：
```php
'permission_callback' => [API::class, 'check_permission']
```

這樣所有 BuyGo 成員（管理員、BuyGo Admin、小幫手）都可以使用全域搜尋。

#### 驗收標準

- [ ] 未登入使用者無法使用全域搜尋
- [ ] BuyGo 成員可以正常搜尋
- [ ] 搜尋結果不包含敏感資訊（或已適當遮罩）

---

### 修復 #4: class-keywords-api.php - 限制登入使用者

**檔案**: `includes/api/class-keywords-api.php`
**風險等級**: 🟡 中（可能暴露商業邏輯）
**預估工時**: 15 分鐘

#### 問題分析

Keywords API 可能包含：
- 自動化關鍵字規則
- 商業邏輯配置
- 分單邏輯

#### 修復策略

**簡單修復**：
```php
'permission_callback' => [API::class, 'check_permission']
```

#### 驗收標準

- [ ] 未登入使用者無法訪問
- [ ] BuyGo 成員可以正常使用

---

## 🟡 Phase 2: 功能性修復（中優先級）

### 修復 #5: settings.php - wpNonce 導出

**檔案**: `admin/partials/settings.php`
**風險等級**: 🟡 中（功能性問題）
**預估工時**: 10 分鐘

#### 問題分析

Vue setup() 中建立了 `wpNonce` 變數但沒有導出，可能導致：
- API 請求失敗（如果代碼使用 `this.wpNonce`）
- 或者是誤報（如果代碼沒有使用 `this.wpNonce`）

#### 修復策略

**Step 1: 先確認是否真的需要**

檢查 settings.php 中是否有使用 `wpNonce`：
```bash
grep -n "wpNonce" admin/partials/settings.php
```

**Step 2: 如果有使用，則修復**

找到 Vue setup() 的 return 區塊（約在檔案末尾），添加：
```javascript
return {
    wpNonce: wpNonce,  // ✅ 添加這行
    activeTab: activeTab,
    // ... 其他已導出的變數
}
```

**Step 3: 如果沒有使用，則刪除定義**

如果代碼中沒有使用 `this.wpNonce`，則刪除定義：
```javascript
// 刪除這行（如果沒用到）
const wpNonce = '<?php echo wp_create_nonce("wp_rest"); ?>';
```

#### 驗收標準

- [ ] 設定頁面的所有 API 請求都正常運作
- [ ] 沒有 JavaScript console 錯誤
- [ ] 新增/刪除小幫手功能正常

---

### 修復 #6: shipment-details.php - Header 結構

**檔案**: `admin/partials/shipment-details.php`
**風險等級**: 🟢 低（UI 一致性）
**預估工時**: 15 分鐘

#### 問題分析

驗證工具檢測到 header 結構可能不符合標準：
- 應該有固定高度（64px）
- 應該使用 sticky positioning
- 左右兩側應該明確分離

#### 修復策略

**檢查當前結構**：
```bash
grep -A 10 "<header" admin/partials/shipment-details.php
```

**標準 header 結構**：
```html
<header class="shipment-details-header h-16 flex items-center justify-between px-6 sticky top-0 z-10 bg-white border-b border-slate-200">
    <div class="flex items-center gap-4">
        <!-- 左側：返回按鈕 + 標題 -->
        <button onclick="history.back()" class="...">← 返回</button>
        <h1 class="text-xl font-bold text-slate-900">出貨單詳情</h1>
    </div>

    <div class="flex items-center gap-3">
        <!-- 右側：操作按鈕 -->
        <button class="...">列印</button>
        <button class="...">編輯</button>
    </div>
</header>
```

#### 驗收標準

- [ ] Header 高度為 64px
- [ ] Header 固定在頂部（sticky top-0）
- [ ] 左右內容明確分離
- [ ] 響應式行為正常

---

## 🟠 Phase 3: 代碼品質改進（低優先級）

### 改進 #7: CSS 前綴規範

**檔案**: `admin/partials/settings.php`
**影響**: 代碼一致性
**預估工時**: 20 分鐘

#### 問題分析

Settings 頁面可能混用了不同的 CSS 前綴或沒有使用前綴。

#### 改進建議

**統一使用 `settings-` 前綴**：
```css
/* 當前可能的情況 */
.helper-list { ... }
.tab-content { ... }

/* ✅ 應該改為 */
.settings-helper-list { ... }
.settings-tab-content { ... }
```

**或者使用 Vue scoped CSS**（更推薦）：
```html
<style scoped>
/* 這樣不需要前綴，Vue 會自動處理 */
.helper-list { ... }
</style>
```

#### 驗收標準

- [ ] 所有 CSS class 都有統一前綴或使用 scoped
- [ ] 沒有 CSS 衝突

---

### 改進 #8: 結構註解完整性

**檔案**: `admin/partials/settings.php`
**影響**: 可維護性
**預估工時**: 10 分鐘

#### 改進建議

添加清晰的結構註解：
```html
<!-- ========== Header ========== -->
<header class="settings-header">...</header>

<!-- ========== Navigation Tabs ========== -->
<nav class="settings-tabs">...</nav>

<!-- ========== Content Area ========== -->
<div class="settings-content">
    <!-- Tab: 基本設定 -->
    <div v-show="activeTab === 'basic'">...</div>

    <!-- Tab: LINE 登入 -->
    <div v-show="activeTab === 'line'">...</div>

    <!-- Tab: 會員權限管理 -->
    <div v-show="activeTab === 'members'">...</div>
</div>
```

---

### 改進 #9-10: View Switching 邏輯優化

**檔案**:
- `admin/partials/orders.php`
- `admin/partials/shipment-details.php`

**影響**: 代碼一致性
**預估工時**: 20 分鐘

#### 問題分析

某些頁面可能沒有使用統一的 view switching 機制。

#### 改進建議

**統一使用 URL 參數方式**：
```javascript
// 使用 BuyGoRouter（如果有）
BuyGoRouter.navigateTo({
    page: 'orders',
    view: 'list'  // 或 'detail'
});

// 或直接使用 URL 參數
const urlParams = new URLSearchParams(window.location.search);
const currentView = urlParams.get('view') || 'list';
```

**在 orders.php 中**：
```javascript
// 檢測 view 參數
const currentView = new URLSearchParams(window.location.search).get('view') || 'list';

// 根據 view 顯示不同內容
if (currentView === 'list') {
    // 顯示訂單列表
} else if (currentView === 'detail') {
    // 顯示訂單詳情
}
```

---

## 📋 修復檢查清單

### Phase 1 檢查清單（執行前必讀）

**環境準備**:
- [ ] 確認舊版與新版外掛共存測試已完成
- [ ] 確認當前在開發版環境工作 (`buygo-plus-one-dev`)
- [ ] 備份當前代碼（git commit）
- [ ] 啟用 WordPress Debug 模式

**修復順序**:
- [ ] 修復 #1: class-debug-api.php
- [ ] 修復 #2: class-settings-api.php
- [ ] 修復 #3: class-global-search-api.php
- [ ] 修復 #4: class-keywords-api.php

**測試**:
- [ ] 運行結構驗證: `bash scripts/validate-structure.sh`
- [ ] API 權限測試（未登入、小幫手、管理員）
- [ ] 手動測試所有受影響的功能

### Phase 2 檢查清單

- [ ] 修復 #5: settings.php wpNonce
- [ ] 修復 #6: shipment-details.php header
- [ ] 功能測試：設定頁面所有功能
- [ ] 功能測試：出貨單詳情頁面

### Phase 3 檢查清單（可選）

- [ ] 改進 #7: CSS 前綴規範
- [ ] 改進 #8: 結構註解
- [ ] 改進 #9-10: View switching
- [ ] 代碼審查：確保一致性

---

## 🧪 測試計畫

### API 權限測試腳本

```bash
#!/bin/bash
# 檔案: scripts/test-api-permissions.sh

BASE_URL="http://localhost/wp-json/buygo-plus-one/v1"

echo "=== Testing API Permissions ==="

# Test 1: Debug API (應該拒絕未登入)
echo -e "\n1. Debug API (未登入) - 預期: 401"
curl -s -o /dev/null -w "%{http_code}\n" "$BASE_URL/debug"

# Test 2: Settings API (應該拒絕未登入)
echo -e "\n2. Settings API (未登入) - 預期: 401"
curl -s -o /dev/null -w "%{http_code}\n" "$BASE_URL/settings"

# Test 3: Global Search API (應該拒絕未登入)
echo -e "\n3. Global Search API (未登入) - 預期: 401"
curl -s -o /dev/null -w "%{http_code}\n" "$BASE_URL/search?q=test"

# Test 4: Keywords API (應該拒絕未登入)
echo -e "\n4. Keywords API (未登入) - 預期: 401"
curl -s -o /dev/null -w "%{http_code}\n" "$BASE_URL/keywords"

echo -e "\n=== 所有測試完成 ==="
```

### 手動測試檢查清單

**以管理員身份登入**:
- [ ] 設定頁面 - 所有標籤頁都能正常切換
- [ ] 設定頁面 - 新增小幫手功能正常
- [ ] 設定頁面 - 刪除小幫手功能正常
- [ ] 全域搜尋 - 可以搜尋並顯示結果
- [ ] Debug 功能 - 可以訪問（如有前端介面）

**以小幫手身份登入**:
- [ ] 設定頁面 - 只能查看，不能修改（如已實作權限控制）
- [ ] 全域搜尋 - 可以正常使用
- [ ] 不能訪問 Debug API

**未登入訪問**:
- [ ] 所有 API 都返回 401 錯誤

---

## 🚀 執行建議

### 建議的執行時機

1. **最佳時機**: 舊版與新版外掛共存測試完成，環境穩定後
2. **所需時間**: 預留半天時間（包含測試）
3. **執行順序**: Phase 1 → 測試 → Phase 2 → 測試 → Phase 3（可選）

### 風險控制

**低風險修復**（可以立即執行）:
- Phase 3 的所有改進（代碼品質）
- shipment-details.php header 結構

**中風險修復**（需要完整測試）:
- API 權限設定（Phase 1）
- settings.php wpNonce

**緊急回退計畫**:
```bash
# 如果修復後出現問題，立即回退
git reset --hard HEAD~1

# 或者使用 git stash
git stash  # 暫存修改
# 測試原始版本是否正常
git stash pop  # 恢復修改繼續調試
```

---

## 📊 預期成果

### 修復完成後

**安全性提升**:
- ✅ 4 個 API 端點有正確的權限控制
- ✅ 未登入使用者無法訪問任何敏感資料
- ✅ 小幫手只能訪問其權限範圍內的功能

**代碼品質提升**:
- ✅ CSS 命名一致性
- ✅ 結構清晰，易於維護
- ✅ 符合 WordPress 最佳實踐

**驗證結果**:
```
預期執行 validate-structure.sh 結果：
- 錯誤: 0 個（從 6 個減少到 0）
- 警告: 0-2 個（從 4 個減少）
- 健康度: 🟢 A (95-100/100)
```

---

## 📝 相關文檔

- [結構驗證分析報告](../analysis/structure-validation-report.md)
- [會員權限管理計畫](~/.claude/plans/calm-mapping-hedgehog.md)
- [WordPress Plugin 最佳實踐](../wordpress-plugin-dev/best-practices.md)

---

**版本**: 1.0
**制定者**: Claude Code
**最後更新**: 2026-01-24
**狀態**: ⏸️ 待執行（等待環境穩定）

# Phase 42 Research: 角色權限優化

**Date:** 2026-02-20
**Phase Goal:** 角色權限 Tab 只顯示 BGO 相關用戶、支援細粒度 5 大項權限設定、改善搜尋 UX

---

## 現有架構分析

### 1. 表格資料來源（roles-tab.php:1-142）

目前表格列出的使用者來源：
- `get_users(['role' => 'administrator'])` — 所有 WP 管理員
- `get_users(['role' => 'buygo_admin'])` — 所有 BGO 管理員（賣家）
- `get_users(['role' => 'buygo_helper'])` — 所有 BGO 小幫手
- `SettingsService::get_helpers()` — 從 wp_options 中取得的小幫手紀錄

**問題：** WP Admin 無 BGO 角色時也會出現在表格中（SC #1 要求過濾掉）。

### 2. 新增角色 Modal（roles-tab.php:260-318 + admin-settings.js:55-245）

目前 Modal 有 3 個欄位：
1. 搜尋使用者（需輸入 ≥ 2 字元）
2. 選擇角色 dropdown（buygo_admin / buygo_helper）
3. 歸屬賣家搜尋（僅 buygo_helper 時顯示）

**SC #3 要求簡化：** 移除角色 dropdown 和歸屬賣家，直接賦予 buygo_admin。

### 3. 搜尋 API（class-settings-api.php:361-428）

`search_users()` 方法：
- 空 query 回傳空陣列（SC #2 要求回傳前 20 筆）
- 最多回傳 10 筆（需改為 20 筆或按需求調整）
- 支援 role 過濾
- 搜尋欄位：user_login, user_nicename, user_email, display_name

### 4. Portal 權限（template.php:130-132）

```php
$has_portal_access = current_user_can('manage_options')
    || current_user_can('buygo_admin')
    || current_user_can('buygo_helper');
```

目前小幫手有完整 Portal 存取權限，與賣家相同。SC #5 要求細粒度控制。

### 5. 現有角色模型

| 角色 | WP capability | 用途 |
|------|--------------|------|
| `administrator` | `manage_options` | WP 超級管理員 |
| `buygo_admin` | `buygo_admin` | BGO 賣家 |
| `buygo_helper` | `buygo_helper` | BGO 小幫手 |

### 6. 小幫手綁定表（wp_buygo_helpers）

```sql
id, seller_id, helper_id, created_at
```

小幫手必須綁定到一個賣家（seller_id）。

---

## 5 大項權限定義

根據 Roadmap SC #4：
1. **商品** — 查看/編輯/刪除商品
2. **訂單** — 查看/處理訂單
3. **出貨** — 查看/管理出貨
4. **客戶** — 查看客戶資料
5. **設定** — 存取設定頁面

儲存位置：`user_meta` key = `buygo_helper_permissions`
格式：`['products' => true, 'orders' => true, 'shipments' => true, 'customers' => false, 'settings' => false]`
預設：全部 `true`（與目前行為一致，向後相容）

---

## 技術決策

### 表格過濾策略
- 只查 `buygo_admin` 和 `buygo_helper` 角色
- 不再查 `administrator` 角色
- WP Admin 如果也有 `buygo_admin` 角色，才出現

### 搜尋 UX
- 修改 `search_users()` API：空 query 回傳前 20 筆
- 前端：focus 時觸發搜尋（不等 2 字元）

### buygo_helper_can() 函式
- 放在 `includes/services/class-settings-service.php`（靜態方法）
- 參數：`($user_id, $permission)` 或 `($permission)`（當前用戶）
- 賣家/WP Admin 永遠回傳 true
- 小幫手查 user_meta

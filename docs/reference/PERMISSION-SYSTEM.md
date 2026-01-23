# BuyGo+1 權限系統設計文件

> 建立日期：2026-01-22
> 最後更新：2026-01-22

## 概述

本文件說明 BuyGo+1 的權限系統設計，包含角色定義、權限邏輯、與 FluentCart/FluentCommunity 的整合方式。

---

## 1. 角色定義

### 1.1 三層角色架構

```
WordPress 管理員
       │
       │ 在 WP 後台授權
       ▼
BuyGo 管理員（賣家）
       │
       │ 在 BuyGo+1 前台授權
       ▼
小幫手
```

### 1.2 角色權限對照表

| 角色 | WordPress 後台 | FluentCart 後台 | BuyGo+1 前台 | 會員權限管理 |
|-----|---------------|----------------|-------------|-------------|
| WordPress 管理員 | ✓ 可進入 | ✓ 可進入 | ✓ 全部功能 | 可新增 BuyGo 管理員 |
| BuyGo 管理員 | ✗ 不可進入 | ✗ 不可進入 | ✓ 全部功能 | 可新增/刪除小幫手 |
| 小幫手 | ✗ 不可進入 | ✗ 不可進入 | ✓ 全部功能 | ✗ 看不到此頁面 |

### 1.3 簡化原則

**BuyGo 管理員與小幫手的唯一差異：能不能管理其他人**

- 小幫手可以執行所有前台功能（商品、訂單、出貨、分配等）
- 小幫手唯一不能做的事：看到或操作「會員權限管理」頁面
- 不需要細項權限設定（如：只能改商品、只能看訂單）

---

## 2. WordPress 角色設定

### 2.1 角色定義（class-settings-service.php）

```php
// BuyGo 管理員
add_role('buygo_admin', 'BuyGo 管理員', [
    'read' => true,
    'buygo_manage_all' => true,
    'buygo_add_helper' => true,  // 可新增小幫手
]);

// 小幫手
add_role('buygo_helper', 'BuyGo 小幫手', [
    'read' => true,
    'buygo_manage_all' => true,
    'buygo_add_helper' => false,  // 不可新增小幫手
]);
```

### 2.2 權限能力說明

| 能力 | 說明 |
|-----|------|
| `read` | WordPress 基本讀取權限 |
| `buygo_manage_all` | 可使用 BuyGo+1 前台所有功能 |
| `buygo_add_helper` | 可新增/刪除小幫手 |

---

## 3. API 權限檢查

### 3.1 檢查邏輯

所有 BuyGo+1 的 REST API 端點都使用統一的權限檢查：

```php
// 檢查是否有權限使用 BuyGo+1
function check_buygo_permission() {
    return current_user_can('manage_options')      // WordPress 管理員
        || current_user_can('buygo_admin')         // BuyGo 管理員
        || current_user_can('buygo_helper');       // 小幫手
}

// 檢查是否能管理小幫手
function check_helper_management_permission() {
    return current_user_can('manage_options')      // WordPress 管理員
        || current_user_can('buygo_add_helper');   // 有新增小幫手權限的人
}
```

### 3.2 需要修改的檔案

目前權限檢查被暫時關閉（`'permission_callback' => '__return_true'`），需要啟用：

- `/includes/api/class-api.php`
- `/includes/api/class-products-api.php`
- `/includes/api/class-orders-api.php`
- `/includes/api/class-shipments-api.php`
- `/includes/api/class-settings-api.php`

---

## 4. 前台 UI 權限控制

### 4.1 側邊欄選單

根據角色顯示/隱藏選單項目：

| 選單項目 | WordPress 管理員 | BuyGo 管理員 | 小幫手 |
|---------|-----------------|-------------|-------|
| 首頁 | ✓ | ✓ | ✓ |
| 商品 | ✓ | ✓ | ✓ |
| 訂單 | ✓ | ✓ | ✓ |
| 出貨 | ✓ | ✓ | ✓ |
| 設定 | ✓ | ✓ | ✓ |
| 會員權限管理 | ✓ | ✓ | ✗ 隱藏 |

### 4.2 會員權限管理頁面

**WordPress 管理員看到的內容：**
- 在 WordPress 後台操作
- 可新增/刪除 BuyGo 管理員

**BuyGo 管理員看到的內容：**
- 自己的資訊（不可編輯）
- 小幫手列表
- 新增小幫手按鈕
- 刪除小幫手按鈕

**小幫手：**
- 看不到此頁面（側邊欄不顯示此選項）

---

## 5. 與 FluentCart 的整合

### 5.1 為什麼不需要 FluentCart Pro 的角色系統？

BuyGo+1 採用「直接資料庫操作」的方式，繞過 FluentCart 的權限檢查：

```php
// BuyGo+1 直接寫入 FluentCart 資料表
$wpdb->insert($wpdb->prefix . 'fct_products', [...]);
$wpdb->insert($wpdb->prefix . 'fct_product_variants', [...]);
```

這與庫存功能的處理方式相同（庫存也是 Pro 功能，但 BuyGo+1 可以操作）。

### 5.2 資料表操作

BuyGo+1 直接操作的 FluentCart 資料表：

| 資料表 | 用途 |
|-------|------|
| `fct_products` | 商品主表 |
| `fct_product_variants` | 商品變體/庫存 |
| `fct_orders` | 訂單 |
| `fct_order_items` | 訂單明細 |
| `fct_customers` | 客戶 |

---

## 6. 與 FluentCommunity 的整合

### 6.1 側邊欄連結

在 FluentCommunity 的側邊欄添加 BuyGo+1 入口連結，只有特定角色可見：

```php
add_filter('fluent_community/sidebar_menu_groups_config', function($config, $user) {
    // 檢查是否為 BuyGo 成員
    $wp_user = get_user_by('id', $user->user_id);
    if (!$wp_user) return $config;

    $is_buygo_member = user_can($wp_user, 'manage_options')
                    || user_can($wp_user, 'buygo_admin')
                    || user_can($wp_user, 'buygo_helper');

    if (!$is_buygo_member) {
        return $config;  // 一般會員看不到
    }

    // 添加 BuyGo+1 連結
    $config['primaryItems'][] = [
        'title'     => 'BuyGo+1 管理',
        'permalink' => '/buygo-portal/dashboard',
        'slug'      => 'buygo-portal',
        'shape_svg' => '<svg>...</svg>',  // 使用 icon 而非 emoji
    ];

    return $config;
}, 10, 2);
```

### 6.2 連結顯示邏輯

| 角色 | 能看到 BuyGo+1 連結 |
|-----|-------------------|
| WordPress 管理員 | ✓ |
| BuyGo 管理員 | ✓ |
| 小幫手 | ✓ |
| 一般會員 | ✗ |
| 訪客 | ✗ |

---

## 7. 資料表設計

### 7.1 小幫手資料表（wp_buygo_helpers）

```sql
CREATE TABLE wp_buygo_helpers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,        -- 小幫手的 WordPress user ID
    seller_id BIGINT UNSIGNED NOT NULL,      -- 管理員的 WordPress user ID
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_helper (user_id, seller_id),
    INDEX idx_seller (seller_id)
);
```

### 7.2 為什麼需要 seller_id？

確保每個管理員只能看到/管理自己的小幫手：

```php
// 取得當前管理員的小幫手列表
$helpers = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}buygo_helpers WHERE seller_id = %d",
    get_current_user_id()
));
```

---

## 8. 相關檔案

### 後端

| 檔案 | 說明 |
|-----|------|
| `/includes/services/class-settings-service.php` | 角色初始化 |
| `/includes/api/class-settings-api.php` | 小幫手管理 API |
| `/includes/admin/class-settings-page.php` | WordPress 後台設定頁 |

### 前端

| 檔案 | 說明 |
|-----|------|
| `/includes/views/pages/settings.php` | 設定頁面（含會員權限管理） |
| `/includes/views/components/sidebar.php` | 側邊欄（權限控制） |

---

## 9. 開發檢查清單

### 9.1 後端任務

- [ ] 建立 `wp_buygo_helpers` 資料表
- [ ] 修改 `SettingsService::get_helpers()` 依 seller_id 過濾
- [ ] 修改 `SettingsService::add_helper()` 記錄 seller_id
- [ ] 啟用 API 權限檢查（移除 `__return_true`）
- [ ] 新增 FluentCommunity 側邊欄 Hook

### 9.2 前端任務

- [ ] 側邊欄根據角色顯示/隱藏「會員權限管理」
- [ ] 會員權限管理頁面改用子分頁（非彈出視窗）
- [ ] 移除 Emoji，改用 Icon
- [ ] 簡化 UI：只有新增/刪除小幫手功能

---

## 10. 附錄：常見問題

### Q1: 小幫手可以上架商品嗎？
**可以。** 小幫手擁有所有前台功能的權限。

### Q2: 小幫手可以修改價格嗎？
**可以。** 小幫手擁有所有前台功能的權限。

### Q3: 小幫手可以看到其他小幫手嗎？
**不行。** 小幫手看不到「會員權限管理」頁面。

### Q4: 需要購買 FluentCart Pro 嗎？
**不需要。** BuyGo+1 直接操作資料庫，不依賴 FluentCart 的權限系統。

### Q5: 一個人可以同時是多個管理員的小幫手嗎？
**可以。** 資料表設計支援一對多關係（同一個 user_id 可對應多個 seller_id）。

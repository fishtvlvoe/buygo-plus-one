# LINE 綁定查詢 API 研究報告

**專案:** BuyGo+1 LINE 綁定狀態顯示
**研究日期:** 2026-01-31
**信心等級:** HIGH（直接讀取原始碼確認）

## 研究結論

buygo-line-notify 外掛已提供完整的 LINE 綁定查詢 API，**無需新增功能**即可滿足需求。

## 可用的查詢方式

### 方式 1: 直接使用 LineUserService（推薦）

`LineUserService` 提供靜態方法，可直接呼叫，無需實例化。

```php
use BuygoLineNotify\Services\LineUserService;

// 檢查用戶是否已綁定 LINE
$is_linked = LineUserService::isUserLinked($user_id);  // bool

// 取得用戶的 LINE UID
$line_uid = LineUserService::getLineUidByUserId($user_id);  // string|null

// 取得完整綁定資料
$binding = LineUserService::getBinding($user_id);  // object|null
// 回傳: { ID, type, identifier, user_id, register_date, link_date }
```

**優點:**
- 直接、簡潔
- 靜態方法，無需依賴注入
- 類別在 `plugins_loaded` 優先級 20 時載入

**使用前提:**
- buygo-line-notify 外掛已啟用
- 呼叫時機在 `plugins_loaded` 之後

### 方式 2: 使用 Facade（需確認可用性）

API 文件記載了 `BuygoLineNotify::line_users()` 方法，但實際 Facade 類別**尚未實作**此方法。

**目前 Facade 可用方法:**
- `BuygoLineNotify::is_active()` - 檢查外掛是否啟用
- `BuygoLineNotify::messaging()` - 訊息服務
- `BuygoLineNotify::image_uploader()` - 圖片上傳
- `BuygoLineNotify::settings()` - 設定服務
- `BuygoLineNotify::logger()` - 日誌服務

**注意:** `line_users()` 方法在文件中有記載，但 Facade 程式碼中未實作。若需使用 Facade 風格，需先在 buygo-line-notify 新增此方法。

## 完整 API 參考

### LineUserService 可用方法

| 方法 | 簽章 | 說明 |
|------|------|------|
| `isUserLinked` | `(int $user_id): bool` | 檢查用戶是否已綁定 LINE |
| `getLineUidByUserId` | `(int $user_id): ?string` | 取得用戶的 LINE UID |
| `getUserByLineUid` | `(string $line_uid): ?int` | 根據 LINE UID 查詢 User ID |
| `getBinding` | `(int $user_id): ?object` | 取得完整綁定資料 |
| `getBindingByLineUid` | `(string $line_uid): ?object` | 根據 LINE UID 取得綁定資料 |
| `linkUser` | `(int $user_id, string $line_uid, bool $is_registration = false): bool` | 建立綁定 |
| `unlinkUser` | `(int $user_id): bool` | 解除綁定 |

### 綁定資料物件結構

```php
$binding = LineUserService::getBinding($user_id);

// $binding 物件屬性:
// - ID: bigint - 主鍵
// - type: string - 類型（固定為 'line'）
// - identifier: string - LINE UID
// - user_id: int - WordPress User ID
// - register_date: datetime|null - 透過 LINE 註冊的時間
// - link_date: datetime - 綁定時間
```

## BuyGo+1 實作建議

### Settings 頁面整合範例

```php
// 在 SettingsService 或 API 端點中

/**
 * 取得小幫手的 LINE 綁定狀態
 *
 * @param int $helper_user_id 小幫手的 WordPress User ID
 * @return array
 */
public function get_helper_line_status(int $helper_user_id): array {
    // 確認 buygo-line-notify 外掛已載入
    if (!class_exists('BuygoLineNotify\Services\LineUserService')) {
        return [
            'is_linked' => false,
            'line_uid' => null,
            'plugin_active' => false,
            'message' => '請先啟用 BuyGo LINE Notify 外掛',
        ];
    }

    $is_linked = \BuygoLineNotify\Services\LineUserService::isUserLinked($helper_user_id);
    $line_uid = \BuygoLineNotify\Services\LineUserService::getLineUidByUserId($helper_user_id);
    $binding = \BuygoLineNotify\Services\LineUserService::getBinding($helper_user_id);

    return [
        'is_linked' => $is_linked,
        'line_uid' => $line_uid,
        'link_date' => $binding ? $binding->link_date : null,
        'plugin_active' => true,
        'message' => $is_linked ? '已綁定 LINE' : '尚未綁定 LINE',
    ];
}
```

### 前端顯示建議

```vue
<template>
  <div class="line-binding-status">
    <div v-if="!lineStatus.plugin_active" class="warning">
      {{ lineStatus.message }}
    </div>
    <div v-else-if="lineStatus.is_linked" class="success">
      <span class="icon">✓</span>
      LINE 已綁定
      <span class="detail">{{ lineStatus.link_date }}</span>
    </div>
    <div v-else class="warning">
      <span class="icon">!</span>
      LINE 尚未綁定
      <a :href="lineBindingUrl">立即綁定</a>
    </div>
  </div>
</template>
```

## 依賴關係

```
buygo-plus-one-dev
    └── buygo-line-notify (soft dependency)
            └── BuygoLineNotify\Services\LineUserService
```

**Soft Dependency 處理:**
- 使用 `class_exists()` 檢查
- 外掛未啟用時顯示提示訊息
- 不需要在 plugin header 宣告 dependency

## 資料庫結構參考

### wp_buygo_line_users 表

```sql
CREATE TABLE wp_buygo_line_users (
    ID bigint(20) NOT NULL AUTO_INCREMENT,
    type varchar(20) NOT NULL DEFAULT 'line',
    identifier varchar(64) NOT NULL,
    user_id bigint(20) NOT NULL,
    register_date datetime DEFAULT NULL,
    link_date datetime NOT NULL,
    PRIMARY KEY (ID),
    UNIQUE KEY unique_binding (type, identifier),
    KEY user_id (user_id)
);
```

## 質量檢查

- [x] 現有 API 已確認
- [x] 使用方式清楚
- [x] 是否需要在 buygo-line-notify 新增功能已確認

## 建議行動

1. **直接使用 `LineUserService` 靜態方法**（無需等待 Facade 更新）
2. 在 buygo-plus-one-dev 的 API 端點中封裝查詢邏輯
3. 處理外掛未啟用的 graceful degradation

## 來源

- `/Users/fishtv/Development/buygo-line-notify/includes/services/class-line-user-service.php` - 原始碼確認
- `/Users/fishtv/Development/buygo-line-notify/docs/API.md` - API 文件
- `/Users/fishtv/Development/buygo-line-notify/includes/class-buygo-line-notify.php` - Facade 類別

---

*研究完成時間: 2026-01-31*

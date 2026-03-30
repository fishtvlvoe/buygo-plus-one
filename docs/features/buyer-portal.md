# 買家會員頁面整合計畫

## 目標

在 FluentCart 的會員頁面（`[fluent_cart_customer_profile]`）注入 BuyGo 的功能頁面，
讓買家在同一個介面看到訂單進度和 LINE 綁定狀態。

## 技術基礎

FluentCart 提供官方 API：
```php
fluent_cart_api()->addCustomerDashboardEndpoint($slug, $args);
```
- 自動在側邊欄加選單
- 自動處理路由
- `render_callback` 輸出自訂 HTML
- 不改 FluentCart 程式碼

## 買家看到的畫面

```
┌──────────────┬─────────────────────────────────┐
│ 👤 余啟彰     │                                 │
│              │  （依選單切換顯示不同內容）        │
│ ────────────  │                                 │
│ 🏠 儀表板     │  ← FluentCart 原生               │
│ 🛒 購買歷史   │  ← FluentCart 原生               │
│ 📦 訂單進度   │  ← BuyGo 注入（新增）            │
│ 🟢 LINE 綁定  │  ← BuyGo 注入（從 shortcode 搬來）│
│ 📥 下載       │  ← FluentCart 原生               │
│ 👤 個人資料   │  ← FluentCart 原生               │
└──────────────┴─────────────────────────────────┘
```

### 頁面 1：訂單進度

買家最關心的：我買的東西到哪了？

```
📦 您有 3 筆進行中訂單

┌──────────────────────────────────────┐
│ 日本限定長腿Kitty (C) 日曬豹紋       │
│ 1 × ¥2,900                          │
│ 狀態：待分配 ⏳                       │
├──────────────────────────────────────┤
│ 日本限定長腿Kitty (A) 日曬花裙       │
│ 1 × ¥2,900                          │
│ 狀態：已出貨 🚚                       │
├──────────────────────────────────────┤
│ 三麗鷗角色 (B) 布丁狗               │
│ 1 × ¥2,900                          │
│ 狀態：備貨中 📦                       │
└──────────────────────────────────────┘

合計：¥8,700
```

- 只顯示進行中的訂單（排除已完成、已取消）
- 每筆商品顯示：商品名 + variant + 數量 + 單價 + 狀態
- 手機版一張卡片一筆，自然堆疊
- 資料來源：跟 LINE `/訂單` 功能共用同一個 Service

### 頁面 2：LINE 綁定

現有的 LINE 綁定功能搬進來：

```
🟢 LINE 帳號已綁定

┌──────────────────────────────────────┐
│ 👤 Fish 老魚                         │
│ LINE UID: U823e48d899...             │
│ 綁定日期：2026-03-06                  │
│                                      │
│ [解除綁定]                            │
└──────────────────────────────────────┘
```

未綁定時顯示綁定引導。

## 實作步驟

### Step 1：註冊兩個 endpoint

在 `includes/integrations/` 新增一個檔案：

```
includes/integrations/class-fluentcart-customer-portal.php
```

```php
class FluentCartCustomerPortal {
    public static function init() {
        if (!function_exists('fluent_cart_api')) return;

        // 訂單進度頁
        fluent_cart_api()->addCustomerDashboardEndpoint('order-tracking', [
            'title' => '訂單進度',
            'icon_svg' => '📦的SVG',
            'render_callback' => [self::class, 'renderOrderTracking'],
        ]);

        // LINE 綁定頁
        fluent_cart_api()->addCustomerDashboardEndpoint('line-bindindg', [
            'title' => 'LINE 綁定',
            'icon_svg' => 'LINE的SVG',
            'render_callback' => [self::class, 'renderLineBinding'],
        ]);
    }

    public static function renderOrderTracking() {
        // 查詢當前用戶的訂單 + 狀態
        // 輸出 HTML
    }

    public static function renderLineBinding() {
        // 現有的 LINE 綁定 shortcode 內容搬過來
    }
}
```

### Step 2：在 Plugin 載入時 init

`class-plugin.php` 加一行：
```php
add_action('init', ['FluentCartCustomerPortal', 'init']);
```

### Step 3：訂單進度的資料查詢

新建 `LineOrderQueryService`（跟 LINE `/訂單` 共用）：
- 用 `IdentityService` 取當前用戶
- 用 `OrderService` 查進行中訂單
- 用 `ShippingStatusService` 判斷狀態
- 輸出 HTML 卡片

### Step 4：LINE 綁定頁面

從現有的 `[buygo_line_bindinding]` shortcode 的 render 邏輯搬過來。

## 與 LINE /訂單 功能的關係

```
LineOrderQueryService（共用）
  │
  ├─ LINE /訂單 命令 → 輸出 Flex Message（卡片）
  │
  └─ 買家會員頁面 → 輸出 HTML（網頁）
```

同一個 Service，兩種輸出格式。

## 不動的部分

- FluentCart 原始碼（完全不改）
- 現有的賣家後台（不受影響）
- 現有的 LINE 通知功能

## 時間估計

| Step | 內容 | 工時 |
|------|------|------|
| 1 | 註冊 endpoint + 基本框架 | 30 分鐘 |
| 2 | 訂單進度頁面（HTML + CSS） | 1 小時 |
| 3 | LINE 綁定頁面（搬移現有邏輯）| 30 分鐘 |
| 4 | 手機版排版調整 | 30 分鐘 |
| 5 | 測試 | 30 分鐘 |

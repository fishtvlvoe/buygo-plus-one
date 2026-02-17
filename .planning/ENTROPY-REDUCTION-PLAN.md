# 熵減設計標準（通用）

> 建立日期：2026-02-17
> 適用範圍：所有外掛（BGO、LineHub、PayUNi、未來新外掛）
> 核心哲學：功能越多，代碼越簡潔。每個文件只做一件事。

---

## 第一章：設計哲學

### 以終為始

不管外掛功能多複雜，最終目標是：
- **新增功能 = 新增小文件**，而非修改大文件
- **修一個 Bug = 開一個小文件**，而非在 3000 行裡找引號
- **部署到新站 = 上傳 ZIP + 一鍵啟用**，而非 SSH 手動調整

### PHP 只做加載

```
⭕ 正確：PHP 文件只負責 require/include
┌─────────────────────────────────┐
│ class-settings-page.php         │
│                                 │
│  require tabs/line-tab.php      │
│  require tabs/roles-tab.php     │
│  wp_enqueue_script('settings')  │
│  wp_enqueue_style('settings')   │
│                                 │
│  （完，不超過 100 行）            │
└─────────────────────────────────┘

❌ 錯誤：PHP 文件裡面什麼都塞
┌─────────────────────────────────┐
│ class-settings-page.php         │
│                                 │
│  HTML 模板（800 行）             │
│  JavaScript（500 行）            │
│  CSS 樣式（200 行）              │
│  AJAX handler（600 行）          │
│  業務邏輯（900 行）              │
│                                 │
│  （共 3000 行，找 Bug 要 1 小時）│
└─────────────────────────────────┘
```

### 模板歸屬原則

通知模板（業務內容）永遠放在業務外掛，不放在 LineHub：

```
BGO 外掛 ─── 定義模板：「您的訂單已出貨，追蹤編號 {tracking}」
             ↓
          do_action('line_hub_send_notification', $data)
             ↓
LineHub ──── 只負責：接收資料 → 轉成 Flex 訊息 → LINE API 發送


Webinarjam 外掛 ─── 定義模板：「您報名的研討會即將開始」
                     ↓
                  do_action('line_hub_send_notification', $data)
                     ↓
LineHub ──────────── 同一個通道，零修改
```

**為什麼？** 如果模板放 LineHub，每接一個新外掛就要改 LineHub → LineHub 越來越肥 = 熵增。

### WordPress 規範對齊

| WP 規範要求 | 我們的做法 |
|------------|-----------|
| 子目錄分類（includes/admin/public/languages） | 遵循 |
| 全域名稱加前綴，避免衝突 | buygo_ / line_hub_ / payuni_ |
| is_admin() 分離前後台 | 前台不載入後台代碼 |
| i18n 國際化（__() / _e()） | 支援，未來可上架 WP.org |
| readme.txt | 每個外掛都有 |
| 安全性（ABSPATH 檢查） | 所有 PHP 文件開頭檢查 |

---

## 第二章：通用文件結構標準

所有外掛（無論大小）都遵循同一套結構：

```
任何外掛/
├── plugin-name.php              # 入口：定義常數 + require class-plugin（< 50 行）
├── uninstall.php                # 移除時清理
├── readme.txt                   # WP 規範
│
├── includes/
│   ├── class-plugin.php         # 主載入器：註冊 hooks + autoloader（< 150 行）
│   ├── class-database.php       # 資料表建立
│   │
│   ├── services/                # 業務邏輯（每個 < 300 行）
│   │   ├── class-order-query.php      # 只做訂單查詢
│   │   ├── class-order-status.php     # 只做狀態變更
│   │   └── class-order-export.php     # 只做匯出
│   │
│   ├── api/                     # REST 端點（每個 < 300 行）
│   │   └── class-orders-api.php       # 只做路由 + 參數驗證
│   │
│   ├── admin/                   # 後台頁面
│   │   ├── class-admin-page.php       # Tab 路由框架（< 100 行）
│   │   ├── tabs/                      # 每個 Tab 獨立（< 300 行）
│   │   └── ajax/                      # AJAX handler 獨立（< 200 行）
│   │
│   └── templates/               # 通知模板等業務內容
│       ├── notification-order-paid.php
│       └── notification-shipped.php
│
├── assets/
│   ├── css/                     # 獨立 CSS，不內嵌在 PHP 中
│   └── js/                      # 獨立 JS，不內嵌在 PHP 中
│
└── languages/                   # 翻譯檔（.pot / .po / .mo）
```

### 文件大小規則

| 類型 | 理想 | 上限 | 超過怎麼辦 |
|------|------|------|-----------|
| 入口文件（plugin-name.php） | < 50 行 | 50 行 | 不應超過 |
| 主載入器（class-plugin.php） | < 100 行 | 150 行 | 拆出 hooks 註冊 |
| Tab 路由（class-admin-page.php） | < 80 行 | 100 行 | 不應超過 |
| 單一 Tab 頁面 | < 200 行 | 300 行 | 拆成子區塊 |
| Service 類別 | < 200 行 | 300 行 | 按職責拆分 |
| API 類別 | < 200 行 | 300 行 | 按資源拆分 |
| AJAX handler | < 100 行 | 200 行 | 拆成獨立 handler |

### 載入規則

```php
// ⭕ 正確：用到才載入
spl_autoload_register(function ($class) {
    // 只在需要時才 require 對應的 PHP 文件
});

// ⭕ 正確：前台不載入後台
if (is_admin()) {
    require_once 'admin/class-admin-page.php';
}

// ❌ 錯誤：一口氣全部載入
require_once 'services/class-product-service.php';
require_once 'services/class-order-service.php';
// ... 還有 38 個
```

---

## 第三章：BGO 現況分析

### 問題：程式碼熵增

MVP 快速開發階段，功能不斷往現有檔案堆疊，導致：

| 問題 | 影響 |
|------|------|
| 單一檔案過大 | class-settings-page.php（2994 行）、settings.php（2262 行） |
| services/ 有 40 個檔案 | 每次請求全部載入，多數用不到 |
| 職責混雜 | 設定頁 PHP 裡面內嵌 JavaScript、HTML、AJAX handler |
| 前後端耦合 | Vue 元件散落在 PHP partials 中 |
| 部署風險 | 檔案權限、版本差異等問題反覆出現 |

### 肥大檔案 TOP 10

| 檔案 | 行數 | 問題 |
|------|------|------|
| class-settings-page.php | 2994 | 7 個 Tab + AJAX + 內嵌 JS，什麼都做 |
| settings.php (partial) | 2262 | 巨大的 HTML/PHP 模板 |
| class-shipments-api.php | 1365 | API 邏輯過重 |
| class-products-api.php | 1330 | 同上 |
| class-settings-service.php | 1208 | 設定邏輯集中營 |
| class-order-service.php | 1156 | 訂單全部邏輯 |
| class-product-service.php | 1094 | 商品全部邏輯 |
| class-notification-templates.php | 1043 | 所有通知模板混在一起 |
| products.php (partial) | 941 | 前端模板過大 |
| orders.php (partial) | 908 | 同上 |

---

## 第四章：BGO 優化階段

### Phase A：部署自動化（優先級最高）

**目標：** 消除手動部署的所有問題，為商業化做準備

| 項目 | 說明 |
|------|------|
| A-1 | 完善 build-release.sh 打包腳本（BGO + LineHub） |
| A-2 | 確保 ZIP 包含所有必要檔案，排除開發檔案 |
| A-3 | GitHub Release 自動更新機制驗證（BGO 已有，LineHub 補上） |
| A-4 | 建立部署前自動檢查（權限、檔案完整性、PHP 版本相容性） |

**完成標準：** 跑一個指令就能產生可部署的 ZIP，客戶後台一鍵更新

---

### Phase B：設定頁拆分（最大瓶頸）

**目標：** 把 3000 行的 class-settings-page.php 拆成獨立模組

現在的結構（全部塞在一個檔案）：
```
class-settings-page.php (2994 行)
├── LINE 設定 Tab
├── 通知設定 Tab
├── 結帳設定 Tab
├── 工作流程 Tab
├── 角色權限 Tab
├── 測試工具 Tab
├── Debug 中心 Tab
├── 12 個 AJAX handler
└── 內嵌 JavaScript
```

拆分後：
```
admin/
├── class-admin-page.php          # 主框架（Tab 路由，< 100 行）
├── tabs/
│   ├── class-line-tab.php        # LINE 設定
│   ├── class-notifications-tab.php
│   ├── class-checkout-tab.php
│   ├── class-workflow-tab.php
│   ├── class-roles-tab.php
│   ├── class-test-tools-tab.php
│   └── class-debug-tab.php
└── ajax/
    ├── class-line-ajax.php       # LINE 相關 AJAX
    ├── class-roles-ajax.php      # 角色相關 AJAX
    └── class-test-ajax.php       # 測試工具 AJAX
```

**完成標準：** 每個 Tab 檔案 < 300 行，只在需要時載入

---

### Phase C：Service 按需載入

**目標：** 40 個 service 不再全部載入，改為用到時才載入

現在的問題：
```php
// class-plugin.php 裡面：一口氣全部 require
require_once 'services/class-product-service.php';
require_once 'services/class-order-service.php';
require_once 'services/class-shipment-service.php';
// ... 還有 37 個
```

改為：
```php
// Autoloader：用到才載入
spl_autoload_register(function ($class) {
    // BuyGo\Services\ProductService → services/class-product-service.php
});
```

**完成標準：** 首頁請求只載入 3-5 個必要類別，而非全部 40 個

---

### Phase D：大型 Service 拆分

**目標：** 超過 500 行的 Service 拆成專職小類別

| 原始 | 拆分方向 |
|------|---------|
| OrderService (1156 行) | OrderQuery + OrderStatus + OrderExport |
| ProductService (1094 行) | ProductQuery + ProductStock + ProductUpload |
| SettingsService (1208 行) | 按功能區塊拆分 |
| NotificationTemplates (1043 行) | 拆成每個通知類型一個文件（模板留在 BGO） |

---

### Phase E：前後端分離

**目標：** PHP 不再內嵌 JS/CSS，實現完全分離

| 項目 | 說明 |
|------|------|
| E-1 | 設定頁的內嵌 JS 抽出為獨立 .js 檔案 |
| E-2 | 模板頁的內嵌 CSS 抽出為獨立 .css 檔案 |
| E-3 | Vue 元件統一管理路徑 |

---

### Phase F：清理 LINE 舊代碼殘留

**現況：** BGO 已改為透過 LineHub 的 MessagingService 發送（已完成），
但 services/ 裡還殘留 11 個 `class-line-*.php` 舊文件，部分已是 facade 模式。

**目標：** 清理殘留代碼，讓 BGO 只透過 hooks 與 LineHub 通訊

| 文件 | 處置 |
|------|------|
| class-line-messaging-facade.php | 簡化為純 hook 呼叫或移除 |
| class-line-order-notifier.php | 保留觸發邏輯，移除 LINE API 呼叫 |
| class-line-keyword-responder.php | 遷移到 LineHub |
| class-line-service.php | 遷移到 LineHub |
| class-line-text-router.php | 遷移到 LineHub |
| class-line-webhook-handler.php | 遷移到 LineHub（已拆分為 6 個類別） |
| class-line-response-provider.php | 遷移到 LineHub |
| class-line-product-creator.php | 遷移到 LineHub |
| class-line-product-upload-handler.php | 遷移到 LineHub |
| class-line-binding-receipt.php | 遷移到 LineHub |
| class-line-permission-validator.php | 遷移到 LineHub |

**注意：** 通知模板（NotificationTemplates）留在 BGO，因為那是業務內容。
LineHub 只是通道，不存任何業務外掛的模板。

**完成標準：** BGO 的 services/ 裡面沒有直接呼叫 LINE API 的代碼

---

## 執行順序

```
A（部署自動化）→ B（設定頁拆分）→ C（按需載入）→ D（Service 拆分）→ E（前後端分離）→ F（LINE 遷移）
```

- **A 最優先**：每次部署都踩坑，商業化前必須解決
- **B + E 一起做**：拆設定頁時順便把內嵌 JS/CSS 抽出
- **C 效益最大**：autoloader 一次搞定，所有頁面都加速
- **D 持續進行**：每次碰到大文件就順手拆
- **F 搭配 LineHub Phase 4-5**：LineHub 準備好才能遷移

---

## 預期效果

| 指標 | 現在 | 優化後 |
|------|------|--------|
| 最大檔案行數 | 2994 行 | < 500 行 |
| services/ 檔案數 | 40 個 | ~25 個（LINE 遷走後） |
| 每次請求載入類別數 | ~40 個 | 3-5 個 |
| 部署方式 | SSH + rsync | ZIP 一鍵更新 |
| 內嵌 JS/CSS | 大量 | 0 |
| LINE 直接呼叫在 BGO | 11 個檔案 | 0 個（只剩 hook 呼叫） |

---

## 雲端部署已解決的問題

以下問題在 2026-02-17 已永久修復，未來部署不會再遇到：

| 問題 | 原因 | 修復方式 | 再發生？ |
|------|------|---------|---------|
| NSL 表欄位不同 | 不同版本用不同欄位名 | detectNslColumns() 動態偵測 | 不會 |
| Webhook 文字訊息不回應 | WP Cron 不可靠 | 改為同步處理 | 不會 |
| ilab-media-tools 衝突 | Avatar filter 無限遞迴 | 靜態 guard flag | 不會 |
| JS/CSS 返回 403 | 目錄權限 700 | chmod 755/644 | ZIP 安裝不會 |
| Cloudflare 快取舊 JS | 版本號未更新 | 版本號寫在 enqueue 中 | 記得更新即可 |

---

*此計畫為迭代式執行，每個 Phase 獨立完成、獨立驗證，不影響現有功能。*

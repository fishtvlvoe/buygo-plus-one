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

## 第四章：整合執行計畫（BGO + LineHub）

> LineHub 已有 autoloader，BGO 還沒有。
> BGO 後台設定頁有 LINE/通知/工作流程 Tab，跟 LineHub 後台重疊。
> 目標：統一兩邊架構，消除重疊，每步可自主執行。

---

### Step 1：BGO Autoloader（基礎建設）

**涉及：** BGO
**目標：** 把 glob() 全載入改成跟 LineHub 一樣的 PSR-4 autoloader

```
現在（BGO class-plugin.php）：
  glob('services/class-*.php')    → 一口氣載入 40 個 service
  glob('api/class-*.php')         → 一口氣載入 15 個 API

改為：
  spl_autoload_register()         → 用到哪個才載入哪個
  （參考 LineHub 的 includes/autoload.php，已有現成範本）
```

| 項目 | 內容 |
|------|------|
| 建立 | `buygo-plus-one/includes/autoload.php`（參考 LineHub） |
| 修改 | `class-plugin.php` 移除所有 glob() 和逐行 require |
| 驗證 | 後台所有頁面正常運作 |

**自主度：** 高 — 不需要用戶介入
**預估：** 1 個對話

---

### Step 2：部署自動化（BGO + LineHub 一起）

**涉及：** BGO + LineHub
**目標：** 一個指令產生 ZIP，客戶後台一鍵更新

| 項目 | 內容 |
|------|------|
| BGO | 完善 `build-release.sh`（排除 .planning/、tests/、.git/ 等） |
| LineHub | 建立 `build-release.sh` + 加入 `class-auto-updater.php` |
| 驗證 | 產生的 ZIP 可在 WP 後台正常安裝 |

**自主度：** 高 — 不需要用戶介入
**預估：** 1 個對話

---

### Step 3：BGO 設定頁拆分 + JS/CSS 抽出

**涉及：** BGO
**目標：** 3000 行 → 每個 Tab 獨立文件 + 獨立 AJAX + 獨立 JS/CSS

```
之前（1 個文件做所有事）：             之後（每個 Tab 獨立文件）：

class-settings-page.php (2994 行)    admin/
├── LINE 設定 Tab                    ├── class-settings-page.php (< 100 行，只做路由)
├── 通知設定 Tab                     ├── tabs/
├── 結帳設定 Tab                     │   ├── class-line-tab.php
├── 工作流程 Tab                     │   ├── class-notifications-tab.php
├── 角色權限 Tab                     │   ├── class-checkout-tab.php
├── 測試工具 Tab                     │   ├── class-workflow-tab.php
├── Debug 中心 Tab                   │   ├── class-roles-tab.php
├── 12 個 AJAX handler              │   ├── class-test-tools-tab.php
├── 內嵌 JavaScript                  │   └── class-debug-tab.php
└── 內嵌 CSS                        ├── ajax/
                                     │   ├── class-line-ajax.php
                                     │   ├── class-roles-ajax.php
                                     │   └── class-test-ajax.php
                                     └── assets/
                                         ├── js/settings-*.js（獨立 JS）
                                         └── css/settings-*.css（獨立 CSS）
```

分 3 個子對話執行：
- **3a**：拆 LINE 設定 + 通知設定 + 工作流程 Tab
- **3b**：拆 角色權限 + 結帳設定 Tab
- **3c**：拆 測試工具 + Debug Tab + 所有 AJAX handler

**自主度：** 中 — 每批完成後建議打開後台確認頁面正常
**預估：** 2-3 個對話

---

### Step 4：LINE Tab 搬到 LineHub + LineHub 後台 Phase 7

**涉及：** BGO + LineHub
**目標：** BGO 的 LINE 相關 Tab 移到 LineHub 後台，消除重疊

```
BGO 後台（拆分後）：                 LineHub 後台（升級後）：
├── 結帳設定 Tab                    ├── 入門引導 Tab（快速開始）
├── 角色權限 Tab                    ├── LINE 設定 Tab（從 BGO 搬過來）
├── 測試工具 Tab                    ├── 通知設定 Tab（從 BGO 搬過來）
└── Debug 中心 Tab                  ├── 工作流程 Tab（從 BGO 搬過來）
                                    ├── Webhook 記錄 Tab
    BGO = 純 ERP 管理               └── 用法說明 Tab
    LineHub = 所有 LINE 功能             LineHub = 完整 LINE 管理介面
```

同時清理 BGO 裡的 11 個 `class-line-*.php` 殘留文件。

**自主度：** 中 — 完成後需確認兩邊後台都正常
**預估：** 2 個對話

---

### Step 5：大型 Service 拆分

**涉及：** BGO
**目標：** 超過 500 行的 Service 拆成專職小類別

| 原始 | 行數 | 拆分方向 |
|------|------|---------|
| SettingsService | 1208 | 按 Tab 對應拆分 |
| OrderService | 1156 | OrderQuery + OrderStatus + OrderExport |
| ProductService | 1094 | ProductQuery + ProductStock + ProductUpload |
| NotificationTemplates | 1043 | 每個通知類型一個文件（留在 BGO） |

**自主度：** 高 — 每個 Service 獨立拆，不影響其他
**預估：** 2-3 個對話

---

## 執行總覽

```
Step 1 ── BGO Autoloader ─────────────────── 1 個對話 ── 自主
  │
Step 2 ── 部署自動化（BGO + LineHub）──────── 1 個對話 ── 自主
  │
Step 3 ── BGO 設定頁拆分 + JS/CSS 抽出 ──── 2-3 個對話 ── 每批確認
  │        3a: LINE + 通知 + 工作流程 Tab
  │        3b: 角色權限 + 結帳 Tab
  │        3c: 測試 + Debug Tab + AJAX
  │
Step 4 ── LINE Tab → LineHub + 後台 Phase 7 ─ 2 個對話 ── 完成後確認
  │        + 清理 BGO 的 class-line-*.php
  │
Step 5 ── 大型 Service 拆分 ──────────────── 2-3 個對話 ── 自主
  │
  ▼
完成 ── 總計 8-10 個對話
```

### 你需要做的事

| 時刻 | 動作 |
|------|------|
| 每個對話開始 | 說「做第 X 步」或「繼續」 |
| Step 3 每批完成後 | 打開 WP 後台看設定頁正不正常（30 秒） |
| Step 4 完成後 | 打開 BGO + LineHub 後台各看一眼（1 分鐘） |
| 其他步驟 | 不需要介入 |

---

## 預期效果

| 指標 | 現在 | 優化後 |
|------|------|--------|
| BGO 最大檔案行數 | 2994 行 | < 300 行 |
| BGO services/ 檔案數 | 40 個 | ~25 個（LINE 遷走後） |
| BGO 每次請求載入類別數 | ~55 個（全部） | 按需載入（3-5 個） |
| LineHub 後台 | 僅基本設定 | 完整管理介面 |
| BGO 後台設定頁 | 7 個 Tab 混在一起 | 4 個 ERP Tab（LINE 的搬走了） |
| 部署方式 | SSH + rsync | ZIP 一鍵更新 |
| PHP 內嵌 JS/CSS | 大量 | 0 |
| BGO 裡的 LINE 程式碼 | 11 個檔案 | 0 個（只剩 hook 呼叫） |

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

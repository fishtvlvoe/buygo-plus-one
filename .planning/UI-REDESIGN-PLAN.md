# BGO 後台 UI 統一化計畫

## 背景

BGO 後台與 LineHub 介面風格不一致，Tab 結構混亂（使用者工具和開發者工具混在一起），角色管理流程過於複雜。

## 目標

1. 選單 `BuyGo+1` → `BGO`，取消子選單，改為單頁 6-Tab 導航
2. Tab 導航 CSS 跟 LineHub 對齊（共用結構，各自品牌色）
3. 角色權限系統簡化（表格只顯示 BGO 角色，搜尋 UX 改進）
4. 新增「資料管理」Tab（訂單/商品/客戶篩選+編輯+刪除）
5. 新增「功能管理」Tab（Free/Pro 功能列表 + 授權碼管理）
6. 開發者工具合併（流程日誌 + 資料清除 + SQL 查詢）
7. 刪除已壞的「通知記錄」Tab
8. 預留未來功能 API 端點（批量上架、自定義欄位）

## 決策紀錄

| 決策 | 結論 |
|------|------|
| 子選單 vs Tab | 合併為單頁 Tab |
| 色彩策略 | 方案 A：各自品牌色（BGO 藍 #3b82f6 / LineHub 綠 #06C755）+ 共用結構 |
| 實施範圍 | 只改 wp-admin 設定頁，不動前端 Portal |
| 通知記錄 Tab | 刪除（自 2025/12/14 後已無記錄，功能已移至 LineHub Webhook） |
| 流程監控/測試工具/除錯中心 | 合併為「開發者」Tab |
| 角色表格顯示 | 只顯示 BGO 管理員和小幫手，純 WP Admin 不顯示 |
| 新增流程 | 簡化為「新增賣家」，移除角色選擇 |
| 搜尋 UX | 點擊搜尋框立即顯示使用者列表（0 字元開始） |
| Pro 授權 | 透過 BuyGo 外掛控制，不用 FluentCart Licensing |

---

## Tab 結構

```
BGO（單一選單，無子選單）
├── Tab 1：角色權限
├── Tab 2：LINE 模板
├── Tab 3：結帳設定
├── Tab 4：資料管理
├── Tab 5：功能管理
└── Tab 6：開發者
```

### Tab 1：角色權限

- 賣家管理表格（只顯示 BGO 角色，不顯示純 WP Admin）
- 「新增賣家」按鈕（簡化版，點擊搜尋框即顯示列表）
- 小幫手透過賣家的「綁定關係」欄位管理
- WP Admin + BGO Admin 的人可移除 BGO 角色

### Tab 2：LINE 模板

- 不改動，維持現有的客戶/賣家/系統通知模板 + 關鍵字訊息

### Tab 3：結帳設定

- 不改動，維持現有的 3 個 checkbox

### Tab 4：資料管理

- 篩選區：資料類型（訂單/商品/客戶）+ 時間範圍 + 關鍵字
- 查詢結果表格（全選/反選 checkbox）
- 批次刪除（二次確認：Modal → 輸入 DELETE）
- 客戶資料編輯 Modal（姓名、電話、地址、身分證字號）
- 訂單刪除（繞過 FluentCart 限制，直接操作資料庫）

需要新增的 API 端點：

| 方法 | 端點 | 功能 |
|------|------|------|
| GET | `/data/orders` | 按日期範圍查詢訂單 |
| GET | `/data/products` | 按日期範圍查詢商品 |
| GET | `/data/customers` | 按日期範圍查詢客戶 |
| DELETE | `/data/orders/{id}` | 刪除單筆訂單 |
| POST | `/data/orders/batch-delete` | 批次刪除訂單 |
| PUT | `/data/customers/{id}` | 編輯客戶資料 |
| DELETE | `/data/customers/{id}` | 刪除客戶 |
| POST | `/data/customers/batch-delete` | 批次刪除客戶 |

### Tab 5：功能管理

- 功能列表：Free 功能（已啟用）+ Pro 功能（鎖定或已啟用）
- 各功能的啟用/關閉開關（Pro 啟用後才能切換）
- 授權資訊區（授權碼輸入、驗證按鈕、狀態、到期日）
- `buygo_is_pro()` 輔助函式（這次先永遠回傳 true）

版本體系：

| 版本 | 對象 | 功能 |
|------|------|------|
| Free | 一般用戶 | 基本功能（角色、模板、結帳、訂單、出貨） |
| Pro | 付費用戶 | Free + 小幫手、合併訂單、批次操作、資料管理、自定義欄位 |
| Dev | 自架站客戶 | Pro + 開發者工具（流程日誌、資料清除、SQL） |

### Tab 6：開發者

合併原本的三個 Tab：

- Section 1：流程日誌（原 workflow-tab，Webhook/圖片上傳/商品建立統計+日誌）
- Section 2：資料清除（原 test-tools-tab，統計+一鍵清除）
- Section 3：SQL 查詢（原 debug-center-tab，只允許 SELECT）

---

## 實施步驟

### Phase 1：基礎架構（CSS + 選單合併）

#### Step 1.1：新建 admin-tabs.css
- **新建**：`admin/css/admin-tabs.css`
- 仿照 `line-hub/assets/css/admin-tabs.css` 結構
- 用 `.bgo-` 前綴和藍色品牌色 `#3b82f6`
- 所有間距、字重、transition、響應式斷點跟 LineHub 一致

#### Step 1.2：合併選單 + Tab 導航
- **修改**：`includes/admin/class-settings-page.php`
- 刪除兩個 `add_submenu_page()`
- `add_menu_page()` 標題 `'BuyGo+1'` → `'BGO'`
- 新增 `TABS` 常數（6 個 Tab）
- 新增統一的 `render_page()` 方法
- 載入新的 `admin-tabs.css`
- 頁面大標 `<h1>BGO</h1>`

### Phase 2：角色權限優化

#### Step 2.1：表格顯示規則
- **修改**：`includes/admin/tabs/roles-tab.php`
- 只收集 `buygo_admin` 和 `buygo_helper` 角色
- WP Admin 不作為收集條件（只作為附註）

#### Step 2.2：新增流程簡化 + 搜尋 UX
- **修改**：`includes/admin/tabs/roles-tab.php`（Modal 簡化）
- **修改**：`admin/js/admin-settings.js`（搜尋 focus 即觸發）
- **修改**：`includes/api/class-settings-api.php`（search 支援空 query）

### Phase 3：資料管理 Tab

#### Step 3.1：API 端點
- **新建**：`includes/api/class-data-management-api.php`
- 訂單/商品/客戶的查詢、刪除、編輯端點

#### Step 3.2：Tab UI
- **新建**：`includes/admin/tabs/data-management-tab.php`
- **新建**：`admin/js/data-management.js`
- 篩選、表格、批次刪除（二次確認）、客戶編輯 Modal

### Phase 4：功能管理 Tab

#### Step 4.1：授權基礎
- **新建**：`includes/services/class-license-service.php`
- `buygo_is_pro()` 輔助函式（目前永遠回傳 true）
- 功能清單定義（Free/Pro 分類）

#### Step 4.2：Tab UI
- **新建**：`includes/admin/tabs/feature-management-tab.php`
- 功能列表 + 啟用/關閉開關 + 授權碼欄位

### Phase 5：開發者 Tab + 預留 API

#### Step 5.1：合併開發者 Tab
- **新建**：`includes/admin/tabs/developer-tab.php`
- 合併 workflow + test-tools + debug-center

#### Step 5.2：預留 API 端點
- **新建**：`includes/api/class-batch-products-api.php`（骨架）
- 批量上架、多圖上傳、自定義欄位端點（回傳 501）

### Phase 6：清理

#### Step 6.1：刪除過時檔案
- **刪除**：`includes/admin/tabs/notifications-tab.php`
- **刪除**：`includes/admin/tabs/workflow-tab.php`
- **刪除**：`includes/admin/tabs/test-tools-tab.php`
- **刪除**：`includes/admin/tabs/debug-center-tab.php`

#### Step 6.2：清理 CSS
- **修改**：`admin/css/admin-settings.css`（移除被取代的樣式）

---

## 檔案清單

### 新建（8 個）
| 檔案 | 用途 |
|------|------|
| `admin/css/admin-tabs.css` | Tab 導航樣式 |
| `includes/admin/tabs/data-management-tab.php` | 資料管理 Tab |
| `includes/admin/tabs/feature-management-tab.php` | 功能管理 Tab |
| `includes/admin/tabs/developer-tab.php` | 合併的開發者 Tab |
| `includes/api/class-data-management-api.php` | 資料管理 API |
| `includes/api/class-batch-products-api.php` | 預留 API（骨架） |
| `includes/services/class-license-service.php` | 授權服務 |
| `admin/js/data-management.js` | 資料管理 JS |

### 修改（5 個）
| 檔案 | 改動 |
|------|------|
| `includes/admin/class-settings-page.php` | 核心重構：選單+Tab+路由 |
| `includes/admin/tabs/roles-tab.php` | 角色邏輯簡化 |
| `admin/js/admin-settings.js` | 搜尋 UX |
| `includes/api/class-settings-api.php` | search 支援空 query |
| `admin/css/admin-settings.css` | 清理被取代的樣式 |

### 刪除（4 個）
| 檔案 | 原因 |
|------|------|
| `includes/admin/tabs/notifications-tab.php` | 已壞，功能移至 LineHub |
| `includes/admin/tabs/workflow-tab.php` | 合併至 developer-tab |
| `includes/admin/tabs/test-tools-tab.php` | 合併至 developer-tab |
| `includes/admin/tabs/debug-center-tab.php` | 合併至 developer-tab |

### 不修改
- `includes/admin/tabs/templates-tab.php`
- `includes/admin/tabs/checkout-tab.php`
- `includes/admin/ajax/*.php`
- LineHub 所有檔案
- 前端 Vue Portal

---

## 驗證方式

每個 Phase 完成後在 https://test.buygo.me/wp-admin/ 驗證：

1. 側邊欄選單顯示「BGO」（無子選單）
2. 預設顯示「角色權限」Tab，藍色底線標示活動 Tab
3. 6 個 Tab 都能正常切換
4. 角色表格：只顯示 BGO 角色（純 WP Admin 不顯示）
5. 新增賣家：點擊搜尋框立即顯示使用者列表
6. LINE 模板：展開/收合/儲存正常
7. 結帳設定：3 個 checkbox 正常
8. 資料管理：可按日期篩選，刪除需二次確認
9. 功能管理：功能列表顯示正確，授權碼欄位可輸入
10. 開發者：流程日誌+資料清除+SQL 查詢三區塊正常
11. `composer test` 全部通過

---

## 未來待做（不在本次範圍）

- 授權伺服器（驗證 license key）
- 功能鎖定邏輯（Free 用戶實際看到鎖定 UI）
- 購買頁面（buygo.me/pricing）
- 批量上架前端 UI
- 自定義欄位前端 UI
- 多圖片輪播
- 異地備份模組
- 服務條款

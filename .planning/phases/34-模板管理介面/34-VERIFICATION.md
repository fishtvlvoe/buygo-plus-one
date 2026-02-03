---
phase: 34-模板管理介面
verified: 2026-02-02T03:45:00Z
status: gaps_found
score: 7/8 must-haves verified
gaps:
  - truth: "Settings 頁面顯示「通知模板管理」區塊，列出所有可用通知類型（商品上架、新訂單、訂單狀態變更、出貨通知）"
    status: partial
    reason: "前端 UI 顯示所有四種通知類型，但後端僅 shipment_shipped 有預設模板定義。其他三種（product_available, new_order, order_status_changed）為 UI 佔位符，無後端支援"
    artifacts:
      - path: "admin/partials/settings.php"
        issue: "templateDefinitions 包含全部四種類型，但後端 definitions() 僅支援 shipment_shipped"
      - path: "includes/services/class-notification-templates.php"
        issue: "definitions() 缺少 product_available、new_order、order_status_changed 預設模板"
    missing:
      - "後端 definitions() 需新增 product_available 預設模板（或標示為未來功能）"
      - "後端 definitions() 需新增 new_order 預設模板（或標示為未來功能）"
      - "後端 definitions() 需新增 order_status_changed 預設模板（或標示為未來功能）"
    impact: "中等 - 使用者可以在 UI 看到這些模板選項並嘗試編輯，但儲存後無法使用（NotificationTemplates::get() 會返回 null），且「重設為預設值」無意義（沒有預設值可重設）"
    recommendation: "Phase 34 主要目標是出貨通知模板，其他三種可能是計劃中的未來功能。建議：(1) 在前端 UI 標示這些模板為「即將推出」，或 (2) 在後端新增基本預設模板以保持一致性"
---

# Phase 34: 模板管理介面 Verification Report

**Phase Goal:** 提供後台 UI 讓賣家自訂出貨通知模板，並在出貨單建立/編輯表單中新增預計送達時間輸入欄位

**Verified:** 2026-02-02T03:45:00Z
**Status:** gaps_found
**Re-verification:** No — initial verification

## Goal Achievement

### Observable Truths

| # | Truth | Status | Evidence |
|---|-------|--------|----------|
| 1 | Settings 頁面的「客戶」分類下顯示出貨通知模板（shipment_shipped） | ✓ VERIFIED | admin/partials/settings.php:1093 定義 shipment_shipped 模板，包含變數 product_list, shipping_method, estimated_delivery |
| 1a | Settings 頁面列出所有可用通知類型（商品上架、新訂單、訂單狀態變更、出貨通知） | ⚠️ PARTIAL | 前端 templateDefinitions 包含全部四種（行 1069, 1077, 1085, 1093），但後端僅 shipment_shipped 有預設模板。其他三種為 UI 佔位符，無後端支援 |
| 2 | 賣家可以編輯出貨通知模板，系統顯示可用變數列表 | ✓ VERIFIED | variableDescriptions 包含 product_list, shipping_method, estimated_delivery（行 1469-1471），模板編輯器顯示變數列表 |
| 3 | 提供「重設為預設值」按鈕 | ✓ VERIFIED | 前端 resetTemplate() 方法（行 1719），DELETE API 端點 class-settings-api.php:127-130，delete_template() 實作完整 |
| 4 | 模板儲存到 wp_options（key: buygo_notification_template_shipment_shipped） | ✓ VERIFIED | 使用 buygo_notification_templates 集合式儲存（class-notification-templates.php:339, 462），多層快取實作（靜態快取 line 27 + wp_cache line 331, 343） |
| 5 | 出貨單建立/編輯頁面顯示「預計送達時間」日期選擇器（選填） | ✓ VERIFIED | shipment-details.php:552-561 顯示 HTML5 date input，min 設為今天（getTodayDate()），標示為選填 |
| 6 | 儲存時格式化為 MySQL DATETIME 格式 | ✓ VERIFIED | ShipmentDetailsPage.js:163 轉換為 'YYYY-MM-DD 00:00:00' 格式，ShipmentService::mark_shipped() 接收 estimated_delivery_at 參數（line 305），資料庫更新邏輯 line 344-345 |
| 7 | 資料庫 buygo_shipments 表包含 estimated_delivery_at 欄位 | ✓ VERIFIED | class-database.php 包含 estimated_delivery_at datetime 欄位定義和遷移邏輯 |
| 8 | Vue 組件正確處理日期狀態和 API 呼叫 | ✓ VERIFIED | ShipmentDetailsPage.js 實作 getTodayDate() (line 556), formatDateForInput() (line 562), confirmMarkShipped() 發送 estimated_delivery_at 到 API (line 148-163) |

**Score:** 7/8 truths verified (Truth 1a partial - UI 完整但後端支援不完整)

### Required Artifacts

| Artifact | Expected | Status | Details |
|----------|----------|--------|---------|
| admin/partials/settings.php | 前端模板編輯 UI，包含 shipment_shipped 模板定義 | ✓ VERIFIED | shipment_shipped 定義完整（line 1093-1099），變數說明完整（line 1469-1471），resetTemplate 按鈕和處理器存在（line 172, 248, 1719） |
| admin/partials/settings.php (四種通知類型) | templateDefinitions 包含 product_available, new_order, order_status_changed, shipment_shipped | ⚠️ PARTIAL | 前端定義完整（四個模板都存在），但後端僅 shipment_shipped 可用。其他三個為 UI 佔位符 |
| includes/services/class-notification-templates.php | 後端模板定義，包含出貨通知模板預設值和多層快取 | ✓ VERIFIED | shipment_shipped 預設模板（line 945-949），多層快取實作（靜態 line 27, wp_cache line 331/343/343），clear_cache() line 584 |
| includes/api/class-settings-api.php | REST API 端點，處理模板的讀取和儲存 | ✓ VERIFIED | POST /settings/templates (line 30), DELETE /settings/templates/{key} (line 128), delete_template() 實作（line 218-239），語法正確 |
| admin/partials/shipment-details.php | 出貨單頁面模板，包含預計送達時間欄位 | ✓ VERIFIED | 標記出貨 Modal 包含 date input（line 552-561），顯示已出貨狀態的預計送達時間（line 530） |
| admin/js/components/ShipmentDetailsPage.js | Vue 組件邏輯，處理日期選擇和 API 呼叫 | ✓ VERIFIED | markShippedModal 狀態（line 51, 132, 141），日期處理方法（getTodayDate line 556, formatDateForInput line 562），API 呼叫整合（line 148-163） |
| includes/api/class-shipments-api.php | REST API 端點，接收和儲存 estimated_delivery_at | ✓ VERIFIED | batch_mark_shipped 接收 estimated_delivery_at（line 580-581），日期驗證（line 589），傳遞給 ShipmentService（line 596），語法正確 |
| includes/services/class-shipment-service.php | 服務層，處理 estimated_delivery_at 欄位更新 | ✓ VERIFIED | mark_shipped($shipment_ids, $estimated_delivery_at) 簽名（line 305），資料庫更新邏輯（line 344-345），語法正確 |

### Key Link Verification

| From | To | Via | Status | Details |
|------|----|----|--------|---------|
| admin/partials/settings.php | includes/api/class-settings-api.php | REST API POST /settings/templates | ✓ WIRED | 前端呼叫 POST /wp-json/buygo-plus-one/v1/settings/templates，後端端點註冊（line 30），接收 templates 參數 |
| admin/partials/settings.php | includes/api/class-settings-api.php | REST API DELETE /settings/templates/{key} (重設為預設值) | ✓ WIRED | resetTemplate() 呼叫 DELETE 端點（settings.php:1719），後端 delete_template() 實作（settings-api.php:218），清除快取（NotificationTemplates::clear_cache()） |
| includes/api/class-settings-api.php | includes/services/class-notification-templates.php | SettingsService -> NotificationTemplates::save_custom_templates() | ✓ WIRED | DELETE 端點呼叫 NotificationTemplates::clear_cache()，get_option('buygo_notification_templates') 讀寫一致 |
| admin/js/components/ShipmentDetailsPage.js | includes/api/class-shipments-api.php | REST API POST /shipments/batch-mark-shipped，呼叫 confirmMarkShipped 方法 | ✓ WIRED | confirmMarkShipped() 發送 estimated_delivery_at（line 163），API 接收並驗證（line 580-589） |
| includes/api/class-shipments-api.php | includes/services/class-shipment-service.php | ShipmentService::mark_shipped() 方法呼叫 | ✓ WIRED | API 呼叫 mark_shipped($shipment_ids, $estimated_delivery_at)（line 596），Service 接收並更新資料庫（line 344-345） |

### Requirements Coverage

Phase 34 的需求基於 v1.3 milestone「出貨通知與 FluentCart 同步系統」。核心需求：

| Requirement | Status | Evidence |
|-------------|--------|----------|
| 出貨通知模板管理 UI | ✓ SATISFIED | Settings 頁面顯示出貨通知模板編輯器，支援變數列表、儲存、重設功能 |
| 模板儲存機制 | ✓ SATISFIED | 使用 wp_options（buygo_notification_templates），多層快取實作完整 |
| 預計送達時間欄位 | ✓ SATISFIED | 出貨單 Modal 顯示日期選擇器，資料流完整（UI → API → Service → Database） |
| 資料庫欄位支援 | ✓ SATISFIED | buygo_shipments 表包含 estimated_delivery_at datetime 欄位，遷移邏輯正確 |
| TMPL-01 四種通知類型 | ⚠️ PARTIAL | 前端 UI 包含所有四種，但後端僅 shipment_shipped 可用。其他三種為計劃功能的 UI 佔位符 |

### Anti-Patterns Found

| File | Line | Pattern | Severity | Impact |
|------|------|---------|----------|--------|
| includes/services/class-notification-templates.php | 54-58 | 模板不存在時返回 null（product_available, new_order, order_status_changed 無後端定義） | ⚠️ Warning | 前端顯示這些模板選項，但後端無法處理。使用者編輯後無法真正使用這些模板 |
| admin/partials/settings.php | 1069-1091 | UI 顯示未實作的模板（product_available, new_order, order_status_changed） | ⚠️ Warning | 可能造成使用者困惑，期待功能可用但實際無法使用 |

### Human Verification Required

#### 1. 模板編輯器 UI 功能性測試

**Test:** 
1. 開啟 Settings 頁面
2. 切換到「客戶」標籤
3. 找到「出貨通知」模板
4. 點擊展開編輯器
5. 確認顯示變數列表（product_list, shipping_method, estimated_delivery）
6. 點擊變數複製到編輯器
7. 修改模板內容
8. 點擊「儲存」
9. 重新載入頁面，確認修改已保存
10. 點擊「重設為預設值」
11. 確認對話框顯示，點擊確認
12. 確認模板內容恢復為預設值

**Expected:** 
- 所有步驟順利完成
- 模板儲存和重設功能正常
- 變數點擊複製功能正常
- Toast 通知顯示正確

**Why human:** 需要瀏覽器互動和視覺確認，無法通過程式碼掃描驗證

#### 2. 預計送達時間欄位功能性測試

**Test:**
1. 開啟出貨單頁面
2. 選擇一個「處理中」狀態的出貨單
3. 點擊「標記為已出貨」按鈕
4. 確認 Modal 顯示「預計送達時間」日期選擇器
5. 點擊日期選擇器，選擇未來日期（例如明天）
6. 確認無法選擇過去日期（min 限制）
7. 點擊「確認出貨」
8. 重新開啟該出貨單詳情
9. 確認「預計送達時間」顯示剛剛選擇的日期

**Expected:**
- 日期選擇器顯示正確
- 只能選擇今天或未來日期
- 儲存後正確顯示預計送達時間
- 格式為 YYYY/MM/DD

**Why human:** 需要瀏覽器互動、日期選擇器操作、資料庫變更確認

#### 3. 資料庫欄位驗證

**Test:**
1. 使用 WordPress CLI 或 phpMyAdmin 連接資料庫
2. 執行 SQL: `DESCRIBE wp_buygo_shipments;`
3. 確認 estimated_delivery_at 欄位存在，類型為 datetime，可為 NULL
4. 執行步驟 2 的測試（標記出貨並設定預計送達時間）
5. 執行 SQL: `SELECT id, shipment_number, estimated_delivery_at FROM wp_buygo_shipments WHERE status = 'shipped' ORDER BY id DESC LIMIT 5;`
6. 確認 estimated_delivery_at 欄位包含正確的 YYYY-MM-DD HH:MM:SS 格式值（時間部分為 00:00:00）

**Expected:**
- estimated_delivery_at 欄位存在且格式正確
- 資料儲存為 MySQL DATETIME 格式
- 時間部分固定為 00:00:00

**Why human:** 需要資料庫存取和 SQL 查詢操作

#### 4. 出貨通知模板變數替換測試

**Test:**
1. 設定出貨通知模板包含所有變數：{product_list}, {shipping_method}, {estimated_delivery}
2. 建立一個測試出貨單，包含多個商品
3. 設定物流方式（例如「宅配」）
4. 設定預計送達時間（例如 2026-02-05）
5. 標記為已出貨
6. 確認 LINE 通知發送（如果已整合 buygo-line-notify）
7. 檢查通知內容，確認所有變數正確替換：
   - {product_list} 顯示商品清單
   - {shipping_method} 顯示「宅配」
   - {estimated_delivery} 顯示「2026/02/05」或類似格式

**Expected:**
- 所有變數正確替換為實際值
- 商品清單格式正確（多行或逗號分隔）
- 日期格式友善（非 MySQL DATETIME 格式）

**Why human:** 需要整合測試（出貨單 + 模板引擎 + LINE 通知），無法通過單元測試驗證

#### 5. 未實作模板的使用者體驗測試

**Test:**
1. 開啟 Settings 頁面
2. 切換到「客戶」標籤
3. 找到「商品上架通知」、「新訂單通知」、「訂單狀態變更通知」模板
4. 嘗試編輯這些模板並儲存
5. 觀察是否有任何提示說明這些模板尚未實作
6. 嘗試使用這些模板（觸發相應的通知事件）
7. 確認是否發送通知或顯示錯誤

**Expected:**
- **如果這些是計劃功能的佔位符**：應該有 UI 提示說明「即將推出」或「尚未啟用」
- **如果這些應該可用**：需要後端新增預設模板定義

**Why human:** 需要使用者體驗評估和功能可用性判斷

### Gaps Summary

Phase 34 的主要目標（出貨通知模板管理和預計送達時間欄位）已完整實作並驗證通過。所有核心功能正常運作：

**✓ 已驗證功能：**
- 出貨通知模板（shipment_shipped）完整實作（前端 UI + 後端定義 + API + 快取）
- 模板編輯器 UI（變數列表、點擊複製、儲存、重設功能）
- 預計送達時間欄位（HTML5 date input + Vue 狀態管理 + API 驗證 + 資料庫儲存）
- 多層快取機制（靜態快取 + WordPress 物件快取）
- REST API 端點（POST /settings/templates, DELETE /settings/templates/{key}）

**⚠️ 發現的問題：**

1. **前後端不一致（TMPL-01 需求）**：
   - 前端 templateDefinitions 包含四種通知類型（product_available, new_order, order_status_changed, shipment_shipped）
   - 後端 definitions() 僅包含 shipment_shipped
   - 其他三種模板為 UI 佔位符，無法實際使用

**影響評估：**
- **出貨通知功能**：完全可用，無影響
- **預計送達時間功能**：完全可用，無影響
- **其他三種通知類型**：使用者可以在 UI 看到選項並嘗試編輯，但後端無法處理（NotificationTemplates::get() 返回 null）

**建議行動：**
1. **選項 A（計劃功能）**：如果 product_available、new_order、order_status_changed 是未來計劃功能，建議在前端 UI 標示為「即將推出」或暫時隱藏
2. **選項 B（立即可用）**：如果這些模板應該立即可用，需要在後端 definitions() 新增預設模板定義

---

_Verified: 2026-02-02T03:45:00Z_
_Verifier: Claude (gsd-verifier)_

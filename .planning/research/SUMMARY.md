# Research Summary: WordPress 出貨通知系統

**Domain:** WordPress E-commerce Plugin Shipment Notification System
**Researched:** 2026-02-02
**Overall confidence:** HIGH

## Executive Summary

本研究專注於 WordPress 出貨通知系統的架構設計，為 BuyGo Plus One 外掛的 Milestone v1.3 提供技術指引。研究涵蓋四個核心面向：

1. **資料庫升級模式**：WordPress 標準的 dbDelta + version check 模式，確保向下相容且不丟失資料
2. **通知觸發架構**：基於 WordPress Action Hook 的事件驅動架構，實現鬆耦合和可擴展性
3. **模板管理架構**：使用 wp_options + 多層快取，搭配 Vue 3 前端實現模板 CRUD
4. **跨外掛通訊**：Soft dependency 模式整合 buygo-line-notify，優雅降級處理依賴缺失

研究發現現有 BuyGo Plus One 架構已具備良好的 Service Layer 模式和 WordPress 整合基礎，僅需新增一個 NotificationHandler 元件和擴充資料庫升級邏輯即可實現出貨通知功能。

## Key Findings

**架構模式：** WordPress Action Hook Event-Driven + Soft Dependency Cross-Plugin Communication

**核心元件：** 需新增 NotificationHandler（事件監聽器）+ 擴充 Database（dbDelta 升級）+ 擴充 SettingsPage（模板管理 UI）

**Critical pitfall：** dbDelta 語法嚴格（空格、大小寫敏感），需嚴格遵守格式；避免使用手動 ALTER TABLE

## Implications for Roadmap

基於研究發現，建議 Milestone v1.3 採用以下階段結構：

### 1. **Foundation Phase: 資料庫升級** - 建立資料模型基礎
   - **Addresses:** 新增 `estimated_delivery_at` 欄位到 `buygo_shipments` 資料表
   - **Avoids:** 後續階段因缺少欄位而失敗（Pitfall: 未版本化資料庫升級）
   - **Duration:** 0.5 天
   - **依賴關係:** 無（獨立階段）
   - **風險:** 低（dbDelta 自動偵測差異）

### 2. **Core Logic Phase: 通知觸發器** - 實現業務邏輯
   - **Addresses:** 監聽出貨事件、觸發通知流程
   - **Avoids:** 緊耦合問題（Pitfall: 直接在 Service 中硬編碼通知邏輯）
   - **Duration:** 1 天
   - **依賴關係:** 依賴 Phase 1（需要資料表欄位）
   - **風險:** 中（需要整合現有 NotificationService）

### 3. **Frontend Phase: 模板管理 UI** - 提供使用者介面
   - **Addresses:** 後台模板編輯、預覽、儲存
   - **Avoids:** 效能問題（Pitfall: 每次請求都從資料庫讀取模板）
   - **Duration:** 1.5 天
   - **依賴關係:** 依賴 Phase 2（需要通知邏輯完成以測試模板）
   - **風險:** 低（現有 Settings 頁面架構成熟）

**階段順序理由：**
- 從內到外（Database → Service → UI）符合依賴關係
- 優先完成高風險項目（資料庫升級、跨外掛通訊）
- UI 最後完成，可獨立測試且風險最低

**研究標記（Research Flags）：**
- Phase 1（資料庫升級）：標準模式，無需深入研究
- Phase 2（通知觸發器）：標準模式，無需深入研究
- Phase 3（模板管理 UI）：標準模式，無需深入研究

**預計總工時：** 3 天（0.5 + 1 + 1.5）

## Confidence Assessment

| Area | Confidence | Notes |
|------|------------|-------|
| Database Upgrade | HIGH | WordPress 官方文件完整，專案已有成熟的 Database class |
| Notification Trigger | HIGH | WordPress Action Hook 是標準模式，專案已有 NotificationService |
| Template Management | HIGH | 專案已有 NotificationTemplates class，僅需新增 UI |
| Cross-Plugin Communication | HIGH | 專案已實作 Soft Dependency 模式（NotificationService::isLineNotifyAvailable）|

所有面向都達到 HIGH 信心水準，因為：
1. WordPress 官方文件提供權威指引（dbDelta、Hooks）
2. 專案現有架構已實作大部分模式（Service Layer、Soft Dependency）
3. 社群最佳實踐驗證架構可行性（Smashing Magazine、WPShout）

## Component Boundaries

### 新增元件

**NotificationHandler** (新增)
- **職責:** 監聽 `buygo/shipment/marked_as_shipped` 事件，觸發通知流程
- **通訊對象:** ShipmentService（接收事件）、NotificationService（發送通知）
- **檔案位置:** `includes/services/class-notification-handler.php`

### 擴充元件

**Database** (擴充)
- **新增方法:** `upgrade_shipments_table_v1_3()` - 新增 estimated_delivery_at 欄位
- **通訊對象:** WordPress wpdb（資料庫操作）

**SettingsPage** (擴充)
- **新增區塊:** 通知模板管理區塊
- **通訊對象:** NotificationTemplates（透過 REST API）

### 資料流方向

```
[賣家操作]
  → [ShipmentService::mark_shipped()]
  → [do_action('buygo/shipment/marked_as_shipped')]
  → [NotificationHandler::send_shipment_notification()]
  → [NotificationService::send()]
  → [NotificationTemplates::get()]
  → [buygo-line-notify API]
  → [LINE Messaging API]
  → [買家收到通知]
```

## Build Order Implications

### 建議建置順序（由內而外）

1. **資料庫基礎層** (0.5 天)
   - 更新 `Plugin::DB_VERSION` 為 '1.3.0'
   - 在 `Database::upgrade_tables()` 中新增 estimated_delivery_at 欄位
   - 測試 dbDelta 升級流程（模擬舊版本升級到新版本）

2. **服務邏輯層** (1 天)
   - 建立 `NotificationHandler` class
   - 在 `ShipmentService::mark_shipped()` 中新增 `do_action()` Hook
   - 註冊事件監聽器到 `Plugin::register_hooks()`
   - 新增 'order_shipped' 模板到 `NotificationTemplates::definitions()`
   - 整合測試（標記出貨 → 觸發通知）

3. **使用者介面層** (1.5 天)
   - 建立 Vue 3 元件 `NotificationTemplates.vue`
   - 新增 REST API 端點 `/templates`（GET, POST）
   - 整合到 Settings 頁面
   - E2E 測試（編輯模板 → 儲存 → 觸發通知 → 驗證買家收到）

### 為什麼這個順序？

- **由內到外原則:** 資料庫 → 業務邏輯 → UI，符合依賴關係
- **測試友善:** 每層完成後都可獨立測試，減少整合問題
- **風險管理:** 優先完成高風險項目（資料庫升級），UI 最後完成風險最低
- **可交付性:** 完成 Phase 2 後功能已可用（即使沒有 UI，仍可透過程式碼測試）

## Open Questions

研究過程中未發現需要進一步調查的重大問題，以下是一些潛在的優化方向（非阻礙項）：

1. **非同步通知處理:** 當用戶量 > 10k 時，是否需要引入 Action Scheduler 實現非同步通知？
   - **現況:** 目前使用同步通知（標記出貨後立即發送）
   - **建議:** Milestone v1.3 維持同步，待用戶量增長後再考慮非同步

2. **通知失敗重試機制:** 如果 LINE API 請求失敗，是否需要重試？
   - **現況:** NotificationService 會記錄錯誤日誌，但不重試
   - **建議:** Milestone v1.3 維持現狀，未來可透過 WordPress Cron 實現重試

3. **模板版本控制:** 是否需要追蹤模板修改歷史？
   - **現況:** 每次儲存都覆蓋舊版本
   - **建議:** Milestone v1.3 不實作，未來如有需求可使用 wp_options meta 或自訂資料表

## Gaps to Address

無重大缺口。研究涵蓋所有 Milestone v1.3 所需的技術面向：

- ✅ 資料庫升級模式（dbDelta + version check）
- ✅ 通知觸發架構（WordPress Action Hook）
- ✅ 模板管理架構（wp_options + 多層快取）
- ✅ 跨外掛通訊（Soft Dependency 模式）
- ✅ 前端整合（Vue 3 + REST API）

所有面向都有明確的實作指引和最佳實踐參考。

## Key Recommendations

### 1. 遵循 WordPress 標準模式
- 使用 dbDelta 進行資料庫升級（避免手動 ALTER TABLE）
- 使用 WordPress Action Hook 實現事件驅動架構
- 使用 wp_options 儲存模板（搭配多層快取）

### 2. 避免常見陷阱
- 不要在 Service 中硬編碼通知邏輯（使用 Hook 解耦）
- 不要每次請求都查詢資料庫（使用快取）
- 不要跳過版本檢查（確保升級邏輯不重複執行）

### 3. 測試策略
- Phase 1: 測試 dbDelta 升級（模擬舊版本 → 新版本）
- Phase 2: 整合測試（標記出貨 → 觸發通知 → 驗證日誌）
- Phase 3: E2E 測試（編輯模板 → 儲存 → 觸發通知 → 驗證買家收到）

### 4. 效能最佳化
- 啟用 Redis/Memcached Object Cache（確保 wp_cache 持久化）
- 使用多層快取（static cache + wp_cache）減少資料庫查詢
- 考慮非同步通知（當用戶量 > 10k 時）

## Ready for Roadmap

研究完成，所有技術面向都已明確：

✅ **架構模式已定義** - WordPress Action Hook Event-Driven + Soft Dependency
✅ **元件邊界已劃分** - NotificationHandler（新增）+ Database/SettingsPage（擴充）
✅ **資料流已設計** - 出貨事件 → 通知觸發器 → 跨外掛通訊 → LINE API
✅ **建置順序已建議** - Database → Service → UI（由內而外）
✅ **陷阱已識別** - dbDelta 語法嚴格、避免緊耦合、使用快取
✅ **測試策略已規劃** - 單元測試 + 整合測試 + E2E 測試

**下一步:** 根據此研究建立 Milestone v1.3 的詳細 Roadmap，包含：
- 每個 Phase 的具體任務（Tasks）
- 預估工時和依賴關係
- 驗收標準（Acceptance Criteria）
- 測試計畫（Testing Plan）

---

## 附錄：技術決策紀錄

### TDR-001: 為什麼使用 WordPress Action Hook 而非直接呼叫？

**決策:** 使用 `do_action('buygo/shipment/marked_as_shipped')` 而非直接呼叫 `NotificationService::send()`

**理由:**
1. 可擴展性：第三方外掛可以監聽相同事件
2. 可測試性：可以 mock Hook，單獨測試各元件
3. 符合 WordPress 生態：WooCommerce、FluentCRM 都使用這種模式

**Trade-off:** 除錯較困難，需要使用 `add_action()` 追蹤事件流

### TDR-002: 為什麼使用 wp_options 而非自訂資料表？

**決策:** 使用 `wp_options` 儲存模板，而非建立 `buygo_notification_templates` 資料表

**理由:**
1. 簡單性：模板數量不大（預計 < 50 個），wp_options 足夠
2. 原生支援：自動序列化/反序列化
3. 快取機制：搭配多層快取，效能足夠

**Trade-off:** 當模板數量 > 100 時，考慮遷移到自訂資料表

### TDR-003: 為什麼使用 dbDelta 而非手動 ALTER TABLE？

**決策:** 使用 `dbDelta($sql)` 而非 `$wpdb->query("ALTER TABLE ...")`

**理由:**
1. WordPress 標準：官方推薦的資料庫升級方式
2. 向下相容：自動偵測差異，不會重複執行
3. 安全性：減少手動 SQL 錯誤風險

**Trade-off:** dbDelta 語法嚴格（空格、大小寫敏感）

---

*Research Summary for: WordPress 出貨通知系統*
*Researched: 2026-02-02*
*Confidence: HIGH*
*Ready for Roadmap Creation: YES*

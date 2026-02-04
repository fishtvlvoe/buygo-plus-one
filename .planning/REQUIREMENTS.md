# Requirements: BuyGo+1

**Core Value:** 讓 LINE 社群賣家能夠在一個統一的後台管理所有銷售活動，每個賣家只能看到自己的商品和訂單

---

## v1.5 Requirements (Active)

**Milestone:** v1.5 - 賣家商品數量限制與 ID 對應系統
**Defined:** 2026-02-04

**Goal:** 重構賣家管理功能，移除「賣家類型」概念，改用統一的「商品數量限制」機制，並整合 FluentCart 自動賦予權限。

**Context:** 現有的「賣家類型」（測試/真實）系統過於複雜，且小幫手配額未與賣家共享。本 milestone 簡化為單一商品數量限制，並整合 FluentCart 購買流程自動賦予權限。

### 角色權限頁面 UI 改造 (UI)

- [ ] **UI-01**: 使用者欄位顯示 WordPress User ID
  - 格式：`張三\nWP-5`（兩行顯示）
  - 所有使用者都顯示 WP ID

- [ ] **UI-02**: 角色欄位顯示 BuyGo ID
  - 小幫手顯示：`BuyGo 小幫手\nBuyGo-15`
  - 賣家顯示：`BuyGo 管理員\n（無 BuyGo ID）`
  - BuyGo ID 來源：`wp_buygo_helpers.id`

- [ ] **UI-03**: 完全隱藏賣家類型欄位
  - 移除整個「賣家類型」欄位（包含標題和內容）
  - 保留 `buygo_seller_type` user meta 但不顯示
  - 新用戶不再寫入此 meta

- [ ] **UI-04**: 移除發送綁定按鈕
  - 「操作」欄位只保留「移除」按鈕
  - 完全移除「發送綁定」功能
  - 用戶自行從前台綁定 LINE

- [ ] **UI-05**: 商品限制欄位全部可編輯
  - 移除「真實賣家時 disabled」的邏輯
  - 所有賣家都可以編輯商品限制
  - 預設值改為 3（目前是 2）
  - 0 = 無限制

### FluentCart 自動賦予權限 (FC)

- [ ] **FC-01**: 後台設定賣家商品 ID
  - 在「角色權限設定」頁面新增設定區塊
  - 輸入框標籤：「賣家商品 ID（FluentCart）」
  - 儲存到 `buygo_seller_product_id` option
  - 支援空值（未設定時不自動賦予）

- [ ] **FC-02**: 監聽訂單付款完成事件
  - Hook: `fluent_cart/order_paid`
  - 檢查訂單中是否包含指定的商品 ID
  - 只在包含該商品時執行賦予流程

- [ ] **FC-03**: 自動賦予賣家角色
  - 賦予 WordPress 角色：`buygo_admin`
  - 取得訂單的 customer 並轉換為 WordPress User ID
  - 記錄 debug log（包含訂單 ID、用戶 ID、商品 ID）

- [ ] **FC-04**: 設定預設商品限制
  - 自動設定 user meta：`buygo_product_limit` = 3
  - 自動設定 user meta：`buygo_seller_type` = 'test'（保留但不顯示）
  - 如果用戶已有這些 meta，不覆蓋

### 小幫手共享配額驗證 (QUOTA)

- [ ] **QUOTA-01**: 小幫手上架計入賣家配額
  - 商品上架時檢查上架者是否為小幫手
  - 如果是小幫手，統計賣家和所有小幫手的總上架數
  - 實作位置：`ProductService` 或商品上架相關服務

- [ ] **QUOTA-02**: 配額驗證邏輯
  - 查詢 `wp_buygo_helpers` 表取得賣家關係
  - 統計公式：`賣家商品數 + SUM(所有小幫手商品數) <= 賣家商品限制`
  - 支援小幫手同時屬於多個賣家的情況

- [ ] **QUOTA-03**: 阻止超限上架
  - 超限時拋出錯誤（不允許上架）
  - 錯誤訊息：「商品數量已達上限（X/Y），無法上架」
  - 前端顯示錯誤提示

### Out of Scope (v1.5)

- **刪除舊資料** — 保留 `buygo_seller_type` user meta，避免資料遷移風險
- **後台發送 LINE 綁定** — 完全移除「發送綁定」按鈕，用戶自行綁定
- **FluentCart 提前實作** — 如果 UI 改造複雜，FluentCart 整合可延後到 v1.6
- **多層級配額系統** — 維持簡單的單一數字限制，不引入複雜層級
- **配額歷史記錄** — 只計算當前上架商品數量
- **配額報表** — 簡化功能範圍

### Traceability (v1.5)

| Requirement | Phase | Status |
|-------------|-------|--------|
| UI-01 | TBD | Pending |
| UI-02 | TBD | Pending |
| UI-03 | TBD | Pending |
| UI-04 | TBD | Pending |
| UI-05 | TBD | Pending |
| FC-01 | TBD | Pending |
| FC-02 | TBD | Pending |
| FC-03 | TBD | Pending |
| FC-04 | TBD | Pending |
| QUOTA-01 | TBD | Pending |
| QUOTA-02 | TBD | Pending |
| QUOTA-03 | TBD | Pending |

**Coverage:**
- v1.5 requirements: 12 total
- Mapped to phases: 0/12 (roadmap pending)
- Will be mapped during roadmap creation

---

## v1.4 Requirements (Completed)

**Milestone:** v1.4 - 會員前台子訂單顯示功能
**Defined:** 2026-02-02
**Completed:** 2026-02-02

### FluentCart 訂單頁面整合 (INTEG)

- [x] **INTEG-01**: 找出 FluentCart 會員訂單詳情頁的 Hook 點位置 — Phase 35
- [x] **INTEG-02**: 透過 WordPress Hook 在主訂單下方注入「查看子訂單」按鈕 — Phase 35
- [x] **INTEG-03**: 注入子訂單列表容器（初始隱藏，點擊按鈕展開） — Phase 35

### 子訂單查詢服務 (QUERY)

- [x] **QUERY-01**: ChildOrderService 查詢指定主訂單的所有子訂單（含賣家資訊） — Phase 36
- [x] **QUERY-02**: 使用 Eager Loading 查詢子訂單商品清單（避免 N+1 查詢） — Phase 36
- [x] **QUERY-03**: 整合子訂單狀態資訊（payment_status、shipping_status、fulfillment_status） — Phase 36
- [x] **QUERY-04**: 子訂單金額小計計算（含幣別資訊） — Phase 36

### REST API 端點 (API)

- [x] **API-01**: GET /buygo-plus-one/v1/child-orders/{parent_order_id} 端點 — Phase 36
- [x] **API-02**: 三層權限驗證（API nonce + Service customer_id + SQL WHERE） — Phase 36
- [x] **API-03**: 回傳格式化的子訂單資料（編號、商品、狀態、金額、賣家） — Phase 36
- [x] **API-04**: 錯誤處理（訂單不存在、無權限、系統錯誤） — Phase 36

### 前端 UI 元件 (UI)

- [x] **UI-01**: 「查看子訂單」按鈕樣式（使用 BuyGo+1 .btn 設計系統） — Phase 37
- [x] **UI-02**: 子訂單列表卡片樣式（復用 .data-table 和 .card） — Phase 37
- [x] **UI-03**: 折疊/展開交互邏輯（Vanilla JavaScript，使用 .buygo- 命名空間） — Phase 37
- [x] **UI-04**: 子訂單狀態標籤顯示（使用 .status-tag 元件） — Phase 37
- [x] **UI-05**: RWD 響應式設計（手機優先，60%+ 流量） — Phase 37
- [x] **UI-06**: Loading 狀態和錯誤提示 — Phase 37

### Out of Scope (v1.4)

- **所有子訂單預設展開** — 頁面初始載入量過大，影響效能和 UX
- **即時編輯訂單資訊** — 前台不應允許編輯，避免資料不一致
- **過度詳細的商品規格** — 購買後不需要完整規格，只需基本識別資訊
- **已下單後比價功能** — 訂單已成立，比價無意義且影響滿意度
- **無限層級子訂單** — FluentCart 僅支援一層子訂單，過度設計
- **賣家後台子訂單顯示** — v1.4 僅做購物者前台，賣家後台未來再評估
- **商品縮圖顯示** — 延後至 v1.5+（視覺增強）
- **物流追蹤連結** — 延後至 v1.5+（需整合物流商 API）
- **訂單狀態時間軸** — 延後至 v1.5+（互動增強）
- **重複購買按鈕** — 延後至 v1.5+（提升 GMV）
- **LINE 通知整合** — 延後至 v1.5+（利用現有基礎設施）

### Traceability (v1.4)

| Requirement | Phase | Status |
|-------------|-------|--------|
| INTEG-01 | Phase 35 | ✅ Complete |
| INTEG-02 | Phase 35 | ✅ Complete |
| INTEG-03 | Phase 35 | ✅ Complete |
| QUERY-01 | Phase 36 | ✅ Complete |
| QUERY-02 | Phase 36 | ✅ Complete |
| QUERY-03 | Phase 36 | ✅ Complete |
| QUERY-04 | Phase 36 | ✅ Complete |
| API-01 | Phase 36 | ✅ Complete |
| API-02 | Phase 36 | ✅ Complete |
| API-03 | Phase 36 | ✅ Complete |
| API-04 | Phase 36 | ✅ Complete |
| UI-01 | Phase 37 | ✅ Complete |
| UI-02 | Phase 37 | ✅ Complete |
| UI-03 | Phase 37 | ✅ Complete |
| UI-04 | Phase 37 | ✅ Complete |
| UI-05 | Phase 37 | ✅ Complete |
| UI-06 | Phase 37 | ✅ Complete |

**Coverage:**
- v1.4 requirements: 17 total
- Mapped to phases: 17/17 (100% coverage) ✅ All Complete
- Phase 35 (FluentCart Hook 探索與注入點設定): 3 requirements ✅
- Phase 36 (子訂單查詢與 API 服務): 8 requirements ✅
- Phase 37 (前端 UI 元件與互動): 6 requirements ✅

---

## v1.3 Requirements (Planned)

**Milestone:** v1.3 - 出貨通知與 FluentCart 同步系統
**Defined:** 2026-02-02

### 資料模型擴充 (DATA)

- [ ] **DATA-01**: 新增 estimated_delivery_at 欄位到 buygo_shipments 表
  - **說明**: 在 buygo_shipments 資料表新增 DATETIME 類型的 estimated_delivery_at 欄位,用於儲存賣家設定的預計送達時間
  - **驗收標準**:
    - 欄位類型為 DATETIME (NULL allowed)
    - 使用 dbDelta 升級機制,確保向後相容
    - 現有出貨單資料不受影響
  - **優先級**: P0 (必須)

- [ ] **DATA-02**: 資料庫升級腳本
  - **說明**: 在 Database::upgrade_tables() 中實作 estimated_delivery_at 欄位升級邏輯
  - **驗收標準**:
    - 使用 idempotent 檢查,避免重複執行
    - 更新 DB_VERSION 版本號
    - 升級過程不鎖表,不影響現有功能
  - **優先級**: P0 (必須)

- [ ] **DATA-03**: 後台出貨單建立/編輯表單新增「預計送達時間」輸入欄位
  - **說明**: 在出貨單建立/編輯頁面新增日期選擇器,讓賣家輸入預計送達時間
  - **驗收標準**:
    - 使用 HTML5 datetime-local 或 Vue 日期選擇器元件
    - 欄位為選填 (optional)
    - 儲存時格式化為 MySQL DATETIME 格式
  - **優先級**: P1 (重要)

### LINE 出貨通知 (NOTIF)

- [ ] **NOTIF-01**: NotificationHandler 監聽出貨單標記為「已出貨」事件
  - **說明**: 建立 NotificationHandler 類別,監聽 `buygo/shipment/marked_as_shipped` WordPress Action Hook
  - **驗收標準**:
    - 在 ShipmentService::markAsShipped() 中觸發 `do_action('buygo/shipment/marked_as_shipped', $shipment_id)`
    - NotificationHandler 正確註冊到 Plugin::register_hooks()
    - 使用 try-catch 確保通知失敗不影響出貨流程
  - **優先級**: P0 (必須)

- [ ] **NOTIF-02**: 收集出貨單資訊
  - **說明**: 從資料庫查詢出貨單相關資訊,包含商品清單、數量、物流方式、預計送達時間
  - **驗收標準**:
    - 查詢 buygo_shipments 和 buygo_shipment_items
    - 整理成通知模板所需的資料結構
    - 處理 estimated_delivery_at 為 NULL 的情況
  - **優先級**: P0 (必須)

- [ ] **NOTIF-03**: 套用出貨通知模板
  - **說明**: 在 NotificationTemplates::definitions() 新增 shipment_shipped 模板定義
  - **驗收標準**:
    - 預設模板包含變數: {product_list}, {shipping_method}, {estimated_delivery}
    - 變數替換邏輯正確處理多商品情況
    - 支援自訂模板 (從 wp_options 讀取)
  - **優先級**: P0 (必須)

- [ ] **NOTIF-04**: 透過 NotificationService 發送 LINE 通知給買家
  - **說明**: 查詢買家的 LINE User ID,透過 NotificationService 發送通知
  - **驗收標準**:
    - 正確識別買家身份 (customer_id → WordPress user_id → LINE User ID)
    - 僅通知買家,不通知賣家和小幫手
    - 整合 buygo-line-notify 外掛 (使用 Soft Dependency 模式)
  - **優先級**: P0 (必須)

- [ ] **NOTIF-05**: 確保一張出貨單只發送一次通知
  - **說明**: 實作 idempotency 機制,防止重複發送通知
  - **驗收標準**:
    - 新增 notification_sent_at 欄位到 buygo_shipments 表 (或使用現有 shipped_at)
    - 檢查 notification_sent_at 是否為 NULL,已發送則跳過
    - 發送成功後更新 notification_sent_at 為當前時間
  - **優先級**: P0 (必須)

### 通知模板管理 (TMPL)

- [ ] **TMPL-01**: Settings 頁面新增「通知模板」設定區塊
  - **說明**: 在 Settings 頁面 (Vue 3 元件) 新增「通知模板管理」區塊
  - **驗收標準**:
    - 顯示所有可用通知類型 (商品上架、新訂單、訂單狀態變更、出貨通知)
    - 點擊「編輯」按鈕進入模板編輯器
    - 使用現有 Settings 頁面的設計系統樣式
  - **優先級**: P1 (重要)

- [ ] **TMPL-02**: 出貨通知模板編輯器
  - **說明**: 提供文字編輯器讓客戶自訂出貨通知模板內容
  - **驗收標準**:
    - 顯示可用變數列表: {product_list}, {shipping_method}, {estimated_delivery}
    - 即時預覽功能 (optional,可延後實作)
    - 提供「重設為預設值」按鈕
  - **優先級**: P1 (重要)

- [ ] **TMPL-03**: 預設出貨通知模板
  - **說明**: 定義預設的出貨通知模板內容
  - **驗收標準**:
    - 模板內容清晰易懂,符合台灣用戶習慣
    - 包含所有必要資訊 (商品、物流、預計送達)
    - 預設模板範例:
      ```
      您的訂單已出貨囉! 📦

      商品清單:
      {product_list}

      物流方式: {shipping_method}
      預計送達: {estimated_delivery}

      感謝您的購買!
      ```
  - **優先級**: P0 (必須)

- [ ] **TMPL-04**: 模板儲存到 wp_options
  - **說明**: 實作模板 CRUD REST API,儲存客戶自訂模板到 wp_options
  - **驗收標準**:
    - wp_options key: `buygo_notification_template_shipment_shipped`
    - 使用 update_option() 和 get_option()
    - 整合多層快取 (static cache + wp_cache)
  - **優先級**: P1 (重要)

- [ ] **TMPL-05**: 模板變數替換邏輯
  - **說明**: 實作變數替換邏輯,將 {product_list} 等變數替換為實際資料
  - **驗收標準**:
    - 使用 esc_html() 防止 XSS 漏洞
    - 正確處理多商品情況 (商品清單格式化)
    - 處理 estimated_delivery 為空的情況 (顯示「未設定」或類似訊息)
  - **優先級**: P0 (必須)

### Out of Scope (v1.3)

- **追蹤編號顯示** — 台灣物流商追蹤系統支援度低,模板中暫不顯示追蹤編號
- **出貨單編號顯示** — 買家不關心內部出貨單編號,模板中暫不顯示
- **賣家/小幫手出貨通知** — 賣家自己標記出貨不需通知自己,小幫手也不需收到出貨確認通知
- **多語系支援** — 僅支援繁體中文,未來可擴充英文/簡體中文
- **進階模板編輯器** — 基礎文字編輯即可,不需要 WYSIWYG 或拖拉式編輯器
- **智能預測送達時間** — v1.3 使用手動輸入,v1.4+ 可基於歷史資料實作智能預測
- **多筆出貨合併通知** — v1.3 每張出貨單獨立通知,未來可支援「同一買家多張出貨單合併為一次通知」
- **通知模板版本控制** — v1.3 每次儲存覆蓋舊版本,未來可實作版本歷史
- **LINE Flex Message 格式** — v1.3 使用純文字通知,未來可支援圖文訊息
- **Email/SMS 通知** — v1.3 僅支援 LINE 通知,未來可擴充其他通知管道
- **非同步通知處理** — v1.3 使用同步通知,用戶量 > 10k 時考慮引入 Action Scheduler

### Traceability (v1.3)

| Requirement | Phase | Status |
|-------------|-------|--------|
| DATA-01 | Phase 32 | Pending |
| DATA-02 | Phase 32 | Pending |
| DATA-03 | Phase 34 | Pending |
| NOTIF-01 | Phase 33 | Pending |
| NOTIF-02 | Phase 33 | Pending |
| NOTIF-03 | Phase 33 | Pending |
| NOTIF-04 | Phase 33 | Pending |
| NOTIF-05 | Phase 33 | Pending |
| TMPL-01 | Phase 34 | Pending |
| TMPL-02 | Phase 34 | Pending |
| TMPL-03 | Phase 33 | Pending |
| TMPL-04 | Phase 34 | Pending |
| TMPL-05 | Phase 33 | Pending |

**Coverage:**
- v1.3 requirements: 13 total
- Mapped to phases: 13/13 (100% coverage)
- Phase 32 (資料庫基礎升級): 2 requirements
- Phase 33 (通知觸發與模板引擎): 7 requirements
- Phase 34 (模板管理介面): 4 requirements

---

## v1.2 Requirements (Completed)

**Milestone:** v1.2 - LINE 通知觸發機制整合
**Completed:** 2026-02-01

### 商品上架通知 (PROD)

- [x] **PROD-01**: 當賣家透過 LINE 上架商品時，觸發通知事件 — Phase 30
- [x] **PROD-02**: 發送通知給商品擁有者（賣家） — Phase 30
- [x] **PROD-03**: 發送通知給所有已綁定 LINE 的小幫手 — Phase 30

### 訂單通知 (ORD)

- [x] **ORD-01**: 新訂單建立時，通知賣家 — Phase 31
- [x] **ORD-02**: 新訂單建立時，通知所有已綁定 LINE 的小幫手 — Phase 31
- [x] **ORD-03**: 新訂單建立時，通知買家（如果買家有 LINE 綁定） — Phase 31
- [x] **ORD-04**: 訂單狀態變更時，僅通知買家 — Phase 31

### 身份識別 (IDENT)

- [x] **IDENT-01**: 查詢 LINE UID 對應的 WordPress User ID — Phase 28
- [x] **IDENT-02**: 判斷用戶角色（buygo_admin = 賣家、buygo_helper = 小幫手、其他 = 買家） — Phase 28
- [x] **IDENT-03**: 判斷用戶是否有 LINE 綁定 — Phase 28

### Bot 回應邏輯 (BOT)

- [x] **BOT-01**: 賣家發送訊息時，bot 正常回應 — Phase 29
- [x] **BOT-02**: 小幫手發送訊息時，bot 正常回應 — Phase 29
- [x] **BOT-03**: 買家發送訊息時，bot 不回應（靜默） — Phase 29
- [x] **BOT-04**: 未綁定用戶發送訊息時，bot 不回應（靜默） — Phase 29

### 與 buygo-line-notify 整合 (INTEG)

- [x] **INTEG-01**: 監聽 buygo-line-notify 發出的 WordPress hooks — Phase 28
- [x] **INTEG-02**: 呼叫 MessagingService 發送 LINE 推播 — Phase 28
- [x] **INTEG-03**: 移植並使用 NotificationTemplates 模板系統 — Phase 28

---

*Last updated: 2026-02-02 after v1.4 roadmap creation*

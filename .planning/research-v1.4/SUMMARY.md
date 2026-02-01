# Project Research Summary

**Project:** BuyGo+1 v1.4 - 會員前台子訂單顯示功能
**Domain:** WordPress E-Commerce Multi-Vendor Order Management
**Researched:** 2026-02-02
**Confidence:** HIGH

## Executive Summary

BuyGo+1 v1.4 需要在 FluentCart 會員前台實作子訂單顯示功能，讓顧客查看多賣家訂單的拆分明細。研究顯示最佳實作方式是使用 WordPress Hook 注入模式搭配 REST API，而非深度修改 FluentCart 核心。這樣做可保持升級相容性，並充分利用現有的 OrderService、權限系統和 LINE 通知整合。

核心建議採用三層架構：(1) 使用 WordPress action hooks 在前台頁面注入 UI 容器，(2) 透過 inline JavaScript 呼叫自訂 REST API 取得子訂單資料，(3) 在 Service 層實作權限驗證和資料查詢邏輯。此方案技術複雜度低，開發時程約 6-9 小時，且與現有架構無縫整合。

最關鍵的風險有三個：(1) 權限隔離失效（顧客 A 看到顧客 B 的訂單），(2) N+1 查詢爆炸（訂單列表載入過慢），(3) FluentCart Hook 升級失效（版本更新後功能中斷）。防範策略分別是多層權限驗證、使用 Eager Loading 和 Hook 版本檢查機制。

## Key Findings

### Recommended Stack

基於現有 BuyGo+1 架構（WordPress + FluentCart + Vue 3），v1.4 不需要引入新技術棧。所有功能可使用現有的 WordPress 核心 API、FluentCart Eloquent Model 和 REST API 架構實作。

**Core technologies:**
- **WordPress Hook Injection** — 使用 `add_action('wp_footer')` 在 FluentCart 頁面注入子訂單 UI，保持與第三方外掛的低耦合，確保 FluentCart 升級時不會中斷功能
- **REST API + Cookie Auth** — 前台使用 WordPress 內建的 Cookie + Nonce 認證機制，無需額外設定，符合現有 API 架構標準
- **FluentCart Eloquent Model** — 使用 `Order::with(['children', 'order_items'])` Eager Loading 一次性載入父訂單和子訂單，避免 N+1 查詢問題
- **Inline JavaScript (MVP)** — 初期使用 inline JS 實作前台互動，快速驗證需求，未來可升級為獨立 Vue Component

**不建議使用的技術：**
- 修改 FluentCart 核心檔案（升級時會被覆蓋）
- 全域載入 JavaScript（影響效能）
- 前端直接查詢資料庫（安全漏洞）
- WordPress Cron 輪詢更新（延遲高、資源浪費）

### Expected Features

基於 WooCommerce Multi-Vendor、YITH、MarketKing 等競品研究和電商 UX 最佳實務，子訂單顯示功能的特徵需求如下：

**Must have (table stakes):**
- **子訂單編號、狀態、金額顯示** — 用戶期望看到每個賣家訂單的獨立追蹤編號、處理狀態和金額小計，這是多賣家場景的基本需求
- **展開/折疊互動** — 避免頁面過長造成認知負擔，使用 Accordion UI 模式優化手機版體驗（91% 消費者會追蹤訂單）
- **主訂單與子訂單關聯** — 使用麵包屑導航明確標示層級關係（「訂單 #BGO-001 > 子訂單 #SUB-001」）
- **賣家資訊顯示** — 多賣家場景必須標示每個子訂單的賣家來源，可整合 BuyGo LINE 賣場直接聯絡

**Should have (competitive):**
- **商品縮圖顯示** — 圖片識別速度比文字快 60 倍，當用戶反饋「難以識別商品」時新增（使用 50x50px 縮圖 + lazy loading）
- **子訂單物流追蹤連結** — 整合物流商 API 提供一鍵追蹤，減少客服查詢（待物流整合完成）
- **訂單狀態時間軸** — 視覺化顯示狀態變化歷程（下單→出貨→運送中→完成），減少用戶焦慮
- **即時通知整合** — 擴展現有 LINE 通知系統到子訂單狀態變更

**Defer (v2+):**
- **子訂單操作按鈕** — 需完整的退款、客訴工作流程設計，複雜度高
- **訂單備註/留言** — 需評估實際使用頻率後再決定
- **子訂單合併檢視** — 複雜功能，需用戶研究驗證價值

**Anti-features (避免實作):**
- 所有子訂單預設展開（頁面過長，手機版體驗差）
- 子訂單即時編輯（訂單確認後編輯會造成金流、庫存混亂）
- 無限層級的子訂單（UI 複雜度暴增，用戶難以理解）

### Architecture Approach

採用 WordPress Hook 注入模式，在不修改 FluentCart 核心的前提下整合子訂單顯示功能。此架構延續現有 BuyGo+1 的多賣家權限隔離系統和 Service Layer 模式。

**Major components:**

1. **ChildOrderFrontend** — Hook 註冊和前台注入
   - 使用 `add_action('wp_footer')` 在 FluentCart 訂單詳情頁注入 HTML 容器 `<div id="buygo-child-orders"></div>`
   - 透過 `wp_enqueue_script()` 載入 inline JavaScript 和 CSS
   - 使用 `wp_localize_script()` 傳遞 nonce 和 API URL 給前端

2. **Child_Orders_API** — REST API 端點
   - 端點：`GET /wp-json/buygo-plus-one/v1/child-orders/?parent_id={id}`
   - 權限檢查：驗證當前使用者是否為訂單所屬客戶（`customer_id === user_id`）
   - 回應格式：返回 JSON（包含子訂單列表、已付款/未付款金額）

3. **ChildOrderService** — 商業邏輯和資料查詢
   - `get_child_orders($parent_id, $customer_id)` — 使用 FluentCart Eloquent Model 查詢子訂單，搭配 `with(['order_items'])` Eager Loading 避免 N+1 查詢
   - `format_child_order_summary()` — 計算已付款/未付款金額，格式化輸出資料
   - Service 層權限檢查：防止繞過 API 直接呼叫 Service 的安全漏洞

**資料流：**
```
[客戶瀏覽訂單詳情頁]
  ↓ (WordPress 載入頁面)
[觸發 Hook: wp_footer]
  ↓ (ChildOrderFrontend 注入)
[輸出 HTML + JavaScript]
  ↓ (瀏覽器執行 JS)
[Fetch API: GET /child-orders/?parent_id=123]
  ↓ (Child_Orders_API 驗證權限)
[ChildOrderService 查詢資料]
  ↓ (返回 JSON)
[JavaScript 渲染子訂單到 DOM]
```

**整合點：**
- **現有權限系統** — 複用 OrderService 的 `customer_id` 驗證邏輯
- **現有 API 架構** — 遵循 `buygo-plus-one/v1` 命名空間和 nonce 驗證模式
- **FluentCart Model** — 直接使用 `FluentCart\App\Models\Order` 查詢，無需自訂 SQL
- **LINE 通知整合** — 未來可監聽子訂單狀態變更 Hook 觸發通知

### Critical Pitfalls

基於專案現有程式碼分析和 2026 WordPress 安全/效能最佳實務，識別出五個關鍵陷阱：

1. **權限隔離失效（CRITICAL）** — 顧客 A 透過修改 API 請求參數查看顧客 B 的子訂單
   - **防範策略：** 實作三層驗證（API 端點 `permission_callback` + Service 層 `customer_id` 檢查 + SQL 查詢條件），所有使用者輸入必須使用 `absint()` 或 `$wpdb->prepare()` 清理
   - **檢測方式：** 單元測試驗證不同使用者 ID 存取他人訂單時回傳 403，使用 Burp Suite 測試 API 請求參數竄改

2. **N+1 查詢爆炸（HIGH）** — 訂單列表頁顯示 10 筆父訂單時觸發 100+ 次資料庫查詢，頁面載入從 1 秒暴增到 30 秒
   - **防範策略：** 使用 FluentCart Eloquent `with(['children', 'order_items'])` Eager Loading，批次查詢父訂單地址（避免每個子訂單單獨查詢），前台使用分頁或延遲載入
   - **檢測方式：** Query Monitor 外掛檢查訂單列表頁查詢次數 < 10（不論顯示多少父訂單）

3. **FluentCart Hook 升級失效（MEDIUM）** — FluentCart 更新後改變 Hook 名稱或參數，子訂單顯示功能直接中斷
   - **防範策略：** 只使用 FluentCart 官方文件列出的穩定 Hook，加入版本檢查機制（`version_compare(FLUENT_CART_VERSION, '1.2.6', '>=')`），提供 Template Override 作為備用方案
   - **檢測方式：** 在 FluentCart 1.2.x 和 1.3.x 測試環境驗證 Hook 相容性

4. **JavaScript 衝突（MEDIUM）** — 注入的展開/折疊 JavaScript 與 FluentCart 原有 JS 衝突，觸發錯誤操作
   - **防範策略：** 使用唯一的 CSS class 前綴（`.buygo-child-orders-*`），JavaScript 命名空間（`window.BuyGoChildOrders`），Event Delegation 精確範圍，宣告 FluentCart 依賴
   - **檢測方式：** 瀏覽器 Console 無錯誤，Plugin Detective 無衝突警告

5. **狀態同步失效（LOW）** — 賣家在後台更新子訂單狀態後，前台刷新仍看到舊狀態（快取問題）
   - **防範策略：** 在 `OrderService::updateShippingStatus()` 中清除 Transient Cache 和 Object Cache，REST API 設定 `Cache-Control: no-cache`，使用 ETag 驗證
   - **檢測方式：** 後台更新狀態 → 前台刷新頁面 → 立即顯示新狀態（< 3 秒）

## Implications for Roadmap

基於研究發現，建議將 v1.4 拆分為四個階段，總時程約 6-9 小時：

### Phase 1: 後端 API 和權限驗證（2-3h）

**Rationale:** 權限驗證是安全性的第一道防線，必須優先實作並充分測試。此階段建立資料查詢和權限檢查邏輯，為前台 UI 奠定基礎。

**Delivers:**
- `ChildOrderService` 服務類別（`get_child_orders()`, `format_child_order_summary()`）
- `Child_Orders_API` REST 端點（`GET /child-orders/?parent_id={id}`）
- 多層權限驗證機制（API + Service + SQL）
- 單元測試（權限驗證、資料查詢、錯誤處理）

**Addresses:**
- Features: 子訂單編號、狀態、金額顯示（資料準備）
- Stack: REST API + Cookie Auth, FluentCart Eloquent Model
- Architecture: Service Layer 模式，權限隔離系統

**Avoids:**
- Pitfall #1: 權限隔離失效（透過三層驗證防範）
- Pitfall #2: N+1 查詢（使用 Eager Loading）

**Research flags:** 標準 REST API 模式，無需額外研究

**Verification:**
- API 端點可正常呼叫並回傳 JSON
- 單元測試覆蓋率 > 80%
- 不同使用者帳號測試無法存取他人訂單（回傳 403）

---

### Phase 2: 前台 Hook 注入和 UI 渲染（2-3h）

**Rationale:** 在 API 穩定後實作前台顯示邏輯。使用 WordPress Hook 注入模式保持與 FluentCart 的低耦合，避免升級時功能中斷。

**Delivers:**
- `ChildOrderFrontend` 類別（Hook 註冊、HTML 注入、Script enqueue）
- Inline JavaScript（API 呼叫、DOM 渲染、展開/折疊互動）
- CSS 樣式（Accordion UI、層級視覺區分、響應式設計）
- Hook 版本檢查機制（FluentCart 相容性）

**Uses:**
- Stack: WordPress Hook Injection, Inline JavaScript
- Architecture: ChildOrderFrontend 組件

**Implements:**
- Features: 展開/折疊互動、主訂單與子訂單關聯、賣家資訊顯示
- UI Pattern: Accordion Pattern, Breadcrumb Navigation

**Avoids:**
- Pitfall #3: FluentCart Hook 升級失效（版本檢查 + 備用方案）
- Pitfall #4: JavaScript 衝突（命名空間 + 唯一 class 前綴）

**Research flags:** 需確認 FluentCart 1.2.6+ 的穩定 Hook（參考官方文件 315+ Hooks）

**Verification:**
- 前台訂單詳情頁顯示子訂單區塊
- 展開/折疊互動正常（平滑動畫、Loading 狀態）
- 手機版測試（觸控目標 > 44px、垂直堆疊布局）

---

### Phase 3: UX 優化和樣式整合（1-2h）

**Rationale:** 確保前台顯示符合 FluentCart 風格，提升使用者體驗。此階段專注於視覺呈現、載入狀態和錯誤處理。

**Delivers:**
- 與 FluentCart 一致的視覺風格（顏色、字型、間距）
- 載入狀態指示（Skeleton loading、Spinner）
- 錯誤處理（API 失敗、無子訂單、權限不足）
- 空狀態設計（沒有子訂單時的提示訊息）

**Implements:**
- Features: 商品清單與數量、子訂單金額小計
- UI Pattern: Product Thumbnail Grid, Order Status Timeline（基礎版）
- Accessibility: ARIA 標籤、鍵盤導航、色盲友善

**Avoids:**
- UX Pitfall: 預設展開所有子訂單（改為預設折疊）
- UX Pitfall: 「展開」按鈕無載入狀態（加入 Loading 動畫）

**Verification:**
- 視覺符合 FluentCart 原生風格
- WCAG AA 標準對比度檢查（至少 4.5:1）
- 瀏覽器開發者工具 Lighthouse 評分 > 90

---

### Phase 4: 測試、快取優化和部署（1h）

**Rationale:** 在上線前進行完整測試，確保功能穩定、效能良好、安全無虞。

**Delivers:**
- 整合測試（權限、顯示、狀態同步）
- 效能測試（Query Monitor 檢查 N+1、頁面載入時間）
- 安全測試（API 參數竄改、SQL Injection）
- 快取策略（Transient Cache 5 分鐘、狀態更新時清除）

**Verification checklist:**
- [ ] 使用不同顧客帳號測試，無法看到其他人的子訂單
- [ ] Query Monitor 顯示訂單列表頁查詢次數 < 10
- [ ] 在 FluentCart 1.2.x, 1.3.x 測試 Hook 相容性
- [ ] 停用/啟用其他外掛測試 JavaScript 衝突
- [ ] 後台更新狀態 → 前台刷新 → 立即顯示新狀態
- [ ] 建立 50 筆子訂單測試，確認前台不會超時
- [ ] 測試沒有子訂單的父訂單，不顯示錯誤或空白區塊

**Avoids:**
- Pitfall #5: 狀態同步失效（快取清除機制）
- Performance Trap: 未分頁的子訂單列表（展開時分頁載入）

**Research flags:** 標準測試流程，無需額外研究

---

### Phase Ordering Rationale

**為何這個順序：**
1. **API 先行** — 後端 API 是前台 UI 的資料來源，必須先穩定才能開發前端
2. **權限優先** — 安全性是多賣家系統的核心，權限驗證失敗會導致嚴重資料洩漏
3. **UI 隔離** — 前台注入邏輯獨立於 API，可並行測試而不互相影響
4. **測試收尾** — 整合測試需要完整功能才能執行，必須在最後階段

**依賴關係：**
- Phase 2 依賴 Phase 1（前台需要呼叫 API）
- Phase 3 依賴 Phase 2（樣式優化需要基礎 UI）
- Phase 4 依賴 Phase 1-3（測試需要完整功能）

**如何避免陷阱：**
- Phase 1 實作三層權限驗證 → 防範 Pitfall #1
- Phase 1 使用 Eager Loading → 防範 Pitfall #2
- Phase 2 實作 Hook 版本檢查 → 防範 Pitfall #3
- Phase 2 使用命名空間和唯一 class → 防範 Pitfall #4
- Phase 4 實作快取清除機制 → 防範 Pitfall #5

### Research Flags

**Phases with standard patterns (skip research-phase):**
- **Phase 1** — WordPress REST API 和權限驗證是標準模式，現有 API 已實作相同邏輯
- **Phase 2** — WordPress Hook 注入是成熟模式，參考 buygo-line-notify 整合經驗
- **Phase 4** — 測試和部署流程已標準化，使用現有 PHPUnit 和 Query Monitor 工具

**Phases likely needing deeper research:**
- **Phase 2** — 需確認 FluentCart 1.2.6+ 提供的 Customization Hooks，官方文件提及 315+ Hooks 但未詳細列出所有穩定 Hook。建議在開發前查閱 [FluentCart Developer Docs](https://dev.fluentcart.com/getting-started) 確認訂單詳情頁的注入點。
- **Phase 3** — 如果需要實作訂單狀態時間軸（進階 UI），需研究 Tailwind CSS Timeline 組件和 FluentCart 的設計系統規範。

## Confidence Assessment

| Area | Confidence | Notes |
|------|------------|-------|
| Stack | **HIGH** | 完全使用現有技術棧（WordPress + FluentCart + REST API），無需引入新工具。DATETIME vs TIMESTAMP 選擇基於 MySQL 官方文件和 WordPress 核心慣例。 |
| Features | **HIGH** | 基於 MarketKing、YITH、FluentCart 官方文件和多個 UI/UX 最佳實務來源（Baymard、Malomo、Optimum7），特徵優先級清晰。 |
| Architecture | **HIGH** | WordPress Hook 注入模式是成熟方案，參考 buygo-line-notify 整合經驗，FluentCart Eloquent Model 使用模式已在現有 OrderService 驗證。 |
| Pitfalls | **HIGH** | 基於現有 BuyGo+1 程式碼分析（OrderService 權限邏輯、formatOrder N+1 風險點）和 2026 WordPress 安全最佳實務（68% API 漏洞源於不當驗證）。 |

**Overall confidence:** **HIGH**

所有核心技術決策基於官方文件、業界最佳實務和現有程式碼驗證。唯一的中低信心度領域是 FluentCart Hook 的長期穩定性（v1.2.6 引入 Customization Hooks，但未明確標示哪些 Hook 是穩定的），已透過版本檢查機制和 Template Override 備用方案降低風險。

### Gaps to Address

儘管整體信心度高，仍有三個領域需要在實作過程中驗證：

1. **FluentCart Hook 穩定性** — FluentCart 官方文件提及 315+ Hooks，但未詳細列出所有穩定 Hook 和棄用計畫
   - **處理方式：** Phase 2 開始前查閱 [FluentCart Developer Docs](https://dev.fluentcart.com/getting-started)，確認訂單詳情頁的注入點。如無穩定 Hook，改用 Template Override 方案（在主題或外掛中建立 `fluentcart/customer/order-details.php`）

2. **子訂單地址回補效能** — 現有 OrderService `formatOrder()` 方法在回補子訂單地址時可能觸發 N+1 查詢（第 1037-1062 行）
   - **處理方式：** Phase 1 實作時使用批次查詢（收集所有父訂單 ID，一次查詢所有地址），Phase 4 使用 Query Monitor 驗證查詢次數

3. **大量子訂單的前台效能** — 當單一父訂單包含 50+ 筆子訂單時，前台一次性載入可能導致頁面超時或白屏
   - **處理方式：** Phase 2 實作展開時使用分頁載入（每頁 20 筆，提供「載入更多」按鈕），Phase 4 建立測試訂單（50 筆子訂單）驗證效能

## Sources

### Primary (HIGH confidence)

**WordPress 官方文件：**
- [WordPress REST API Authentication](https://developer.wordpress.org/rest-api/using-the-rest-api/authentication/) — REST API 驗證機制和 Cookie + Nonce 模式
- [WordPress Action Hooks Reference](https://developer.wordpress.org/plugins/hooks/actions/) — Action hooks 實作指南和最佳實務
- [WordPress Nonces API](https://developer.wordpress.org/apis/security/nonces/) — Nonce 安全最佳實務和有效期管理

**FluentCart 官方文件：**
- [FluentCart Changelog](https://docs.fluentcart.com/guide/changelog) — v1.2.6 引入 Customization Hooks，Hook 變更歷史
- [FluentCart v1.2.5 公告](https://fluentcart.com/blog/fluentcart-v1-2-5/) — Checkout 和 Thank You 頁面新增 Customization Hooks
- [FluentCart Developer Docs](https://dev.fluentcart.com/getting-started) — 315+ Hooks 開發者指南

**電商 UI/UX 最佳實務：**
- [Baymard - 151 Order Tracking Page Design Examples](https://baymard.com/ecommerce-design-examples/63-order-tracking-page) — 訂單追蹤頁面設計範例
- [Order Tracking Design Best Practices - Malomo](https://gomalomo.com/order-tracking/order-tracking-design) — 91% 消費者會追蹤訂單，19% 會多次查看
- [Accordion UI Design Best Practices - PWSkills](https://pwskills.com/blog/accordions-ui-design-accordion-design/) — Accordion 模式最佳實務

### Secondary (MEDIUM confidence)

**多賣家平台實作模式：**
- [MarketKing - How MarketKing Splits Orders](https://woocommerce-multivendor.com/docs/how-marketking-splits-orders-composite-orders-sub-orders/) — 主訂單/子訂單架構模式
- [YITH WooCommerce Multi Vendor](https://yithemes.com/themes/plugins/yith-woocommerce-multi-vendor/) — 子訂單權限隔離實作

**WordPress 安全和效能：**
- [WordPress Security Best Practices 2026](https://www.adwaitx.com/wordpress-security-best-practices/) — Broken Access Control 佔 14.19% 漏洞
- [Killing the N+1 Query Problem](https://medium.com/techtrends-digest/killing-the-n-1-query-problem-practical-fixes-and-the-real-trade-offs-7e816d9266f1) — 30 倍效能改善案例
- [What is the n+1 problem? (WordPress edition)](https://accreditly.io/articles/what-is-the-n1-problem-wordpress-edition) — WordPress 特定的 N+1 查詢偵測

**JavaScript 衝突偵測：**
- [Plugin Detective – Troubleshooting Conflicts](https://wordpress.org/plugins/plugin-detective/) — 自動偵測 2000+ 外掛的不相容性
- [The Developer's Guide To Conflict-Free JavaScript](https://www.smashingmagazine.com/2011/10/developers-guide-conflict-free-javascript-css-wordpress/) — WordPress JavaScript 命名空間最佳實務

### Tertiary (LOW confidence)

**社群討論：**
- [WordPress Plugin Database Updates Discussion](https://wordpress.org/support/topic/adding-a-new-field-in-database-upon-plugin-update/) — WordPress.org 論壇關於資料庫升級的討論
- [Parent-Child Order Concept · Issue #20922](https://github.com/woocommerce/woocommerce/issues/20922) — WooCommerce 父子訂單架構討論

**現有 Codebase：**
- `includes/services/class-order-service.php` — 多賣家權限過濾實作（88-123 行）、formatOrder N+1 風險點（1037-1062 行）
- `includes/services/class-settings-service.php` — `get_accessible_seller_ids()` 權限驗證方法
- `includes/class-database.php` — `upgrade_tables()` 資料庫升級模式
- `buygo-line-notify/` — WordPress Hook 整合模式參考

---

**Research completed:** 2026-02-02
**Ready for roadmap:** Yes

**Next steps:**
1. 載入此 SUMMARY.md 作為 roadmap 創建的 context
2. 基於 Phase 1-4 建議結構建立詳細的 ROADMAP.md
3. 在 Phase 2 開始前確認 FluentCart Hook 穩定性
4. 在 Phase 1 實作時使用 Query Monitor 驗證 N+1 查詢防範

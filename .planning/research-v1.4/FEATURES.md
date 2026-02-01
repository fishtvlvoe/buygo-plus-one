# Feature Research - 子訂單顯示功能

**Domain:** 電商會員前台訂單管理（Multi-vendor Marketplace）
**Researched:** 2026-02-02
**Confidence:** HIGH

## Feature Landscape

### Table Stakes (Users Expect These)

Features users assume exist. Missing these = product feels incomplete.

| Feature | Why Expected | Complexity | Notes |
|---------|--------------|------------|-------|
| 子訂單編號顯示 | 每個賣家的訂單需要獨立追蹤編號，用於客服溝通和問題排查 | LOW | 需要明確區分主訂單編號（合併訂單）和子訂單編號 |
| 子訂單狀態顯示 | 用戶期望看到每個賣家的訂單處理進度（處理中、已出貨、已完成） | LOW | 狀態需與主訂單同步，但允許獨立狀態變化 |
| 商品清單與數量 | 用戶需要知道該子訂單包含哪些商品、數量多少 | LOW | 必須顯示商品名稱、數量、單價 |
| 子訂單金額小計 | 每個子訂單的總金額（包含該賣家的商品總計） | LOW | 需明確標示是否含運費、稅金 |
| 展開/折疊互動 | 避免頁面過長，預設折疊子訂單，點擊展開查看詳細資訊 | MEDIUM | 使用 Accordion UI 模式，視覺回饋需明確（icon 切換） |
| 主訂單與子訂單關聯 | 用戶需要理解子訂單是哪個主訂單的一部分 | LOW | 使用麵包屑或標題明確標示「訂單 #123 > 子訂單 #456」 |
| 賣家資訊顯示 | 在多賣家場景中，用戶需知道該子訂單來自哪個賣家 | LOW | 顯示賣家名稱，可連結到賣家店鋪頁面 |
| 訂單日期/時間 | 用戶需要知道何時下單，用於核對和追蹤 | LOW | 顯示下單時間和預計到貨時間 |

### Differentiators (Competitive Advantage)

Features that set the product apart. Not required, but valuable.

| Feature | Value Proposition | Complexity | Notes |
|---------|-------------------|------------|-------|
| 商品縮圖顯示 | 快速識別商品，提升用戶體驗（特別是商品數量多時） | LOW | 在訂單明細中顯示小尺寸產品圖片（50x50px） |
| 子訂單物流追蹤連結 | 一鍵跳轉到物流公司追蹤頁面，減少用戶手動查詢 | MEDIUM | 需整合物流商 API 或提供追蹤號碼 + 物流商名稱 |
| 子訂單操作按鈕 | 直接對子訂單進行操作（申請退款、聯絡賣家、確認收貨） | MEDIUM | 需與現有訂單管理系統整合，確保權限控制 |
| 訂單狀態時間軸 | 視覺化顯示訂單狀態變化歷程（下單→出貨→運送中→完成） | MEDIUM | 提供清晰的進度條或時間軸 UI |
| 子訂單合併檢視 | 同一賣家的多次購買可以分組顯示，方便批次管理 | HIGH | 需複雜邏輯判斷和 UI 設計 |
| 訂單備註/留言 | 用戶可對子訂單添加備註（配送要求、客製化需求） | MEDIUM | 需資料庫欄位支援和前後端互動 |
| 重複購買按鈕 | 一鍵將子訂單商品加入購物車，方便回購 | LOW | 提升用戶回購率，增加 GMV |
| 即時通知整合 | 子訂單狀態變更時透過 LINE/Email 通知用戶 | MEDIUM | BuyGo+1 已有 LINE 通知整合，可直接擴展 |

### Anti-Features (Commonly Requested, Often Problematic)

Features that seem good but create problems.

| Feature | Why Requested | Why Problematic | Alternative |
|---------|---------------|-----------------|-------------|
| 所有子訂單預設展開 | 用戶認為「一次看到全部」更方便 | 頁面過長造成認知負擔，滾動困難，特別是手機版 | 使用折疊模式，提供「全部展開」按鈕選項 |
| 子訂單即時編輯（修改數量/商品） | 用戶想要彈性調整訂單 | 訂單已確認後編輯會造成金流、庫存、物流混亂 | 提供「申請變更」流程，由賣家審核 |
| 顯示每個商品的詳細規格 | 用戶希望看到完整商品資訊 | 資訊過載，訂單頁面不是產品頁面 | 提供「查看商品詳情」連結到產品頁 |
| 子訂單間的比價功能 | 用戶想知道「哪個賣家最便宜」 | 已下單後比價無意義，且可能造成賣家競爭問題 | 在結帳前提供比價工具 |
| 無限層級的子訂單（子子訂單） | 複雜業務場景可能需要多層訂單結構 | UI 複雜度暴增，用戶難以理解，維護成本高 | 限制為兩層（主訂單 + 子訂單），通過其他方式處理複雜場景 |

## Feature Dependencies

```
[主訂單顯示]
    └──requires──> [子訂單列表]
                       └──requires──> [展開/折疊互動]
                                          └──requires──> [子訂單詳細資訊]

[子訂單狀態顯示] ──enhances──> [訂單狀態時間軸]
[物流追蹤連結] ──requires──> [子訂單編號 + 物流商資訊]
[重複購買按鈕] ──requires──> [商品清單資料]

[即時通知整合] ──enhances──> [子訂單狀態顯示]
[子訂單操作按鈕] ──conflicts──> [子訂單即時編輯] (避免狀態衝突)
```

### Dependency Notes

- **展開/折疊互動 requires 子訂單列表:** 沒有子訂單列表就沒有展開/折疊的需求，這是核心交互模式
- **訂單狀態時間軸 enhances 子訂單狀態顯示:** 時間軸是狀態顯示的進階版本，提供更豐富的視覺化
- **物流追蹤連結 requires 物流商資訊:** 必須有物流商名稱和追蹤號碼才能生成追蹤連結
- **子訂單操作按鈕 conflicts with 子訂單即時編輯:** 兩者都涉及訂單變更，應該整合到單一操作流程，避免用戶混淆

## MVP Definition

### Launch With (v1.4)

Minimum viable product — what's needed to validate the concept.

- [x] **子訂單編號顯示** — 核心識別功能，無此則無法追蹤
- [x] **子訂單狀態顯示** — 用戶最關心的資訊，必須有
- [x] **商品清單與數量** — 用戶需要知道買了什麼
- [x] **子訂單金額小計** — 財務透明度必備
- [x] **展開/折疊互動** — 避免頁面過長，提升 UX
- [x] **主訂單與子訂單關聯** — 用戶需要理解層級關係
- [x] **賣家資訊顯示** — 多賣家場景必備

### Add After Validation (v1.x)

Features to add once core is working.

- [ ] **商品縮圖顯示** — 當用戶反饋「難以識別商品」時新增
- [ ] **子訂單物流追蹤連結** — 當物流整合完成後新增
- [ ] **訂單狀態時間軸** — 當用戶需要更清晰的狀態追蹤時新增
- [ ] **重複購買按鈕** — 當回購率數據顯示需求時新增
- [ ] **即時通知整合** — 當 LINE 通知系統穩定後擴展到子訂單

### Future Consideration (v2+)

Features to defer until product-market fit is established.

- [ ] **子訂單操作按鈕** — 需完整的工作流程設計（退款、客訴流程）
- [ ] **訂單備註/留言** — 需評估用戶使用頻率後再決定
- [ ] **子訂單合併檢視** — 複雜功能，需用戶研究驗證價值

## Feature Prioritization Matrix

| Feature | User Value | Implementation Cost | Priority |
|---------|------------|---------------------|----------|
| 子訂單編號顯示 | HIGH | LOW | P1 |
| 子訂單狀態顯示 | HIGH | LOW | P1 |
| 商品清單與數量 | HIGH | LOW | P1 |
| 子訂單金額小計 | HIGH | LOW | P1 |
| 展開/折疊互動 | HIGH | MEDIUM | P1 |
| 主訂單與子訂單關聯 | MEDIUM | LOW | P1 |
| 賣家資訊顯示 | MEDIUM | LOW | P1 |
| 商品縮圖顯示 | MEDIUM | LOW | P2 |
| 子訂單物流追蹤連結 | HIGH | MEDIUM | P2 |
| 訂單狀態時間軸 | MEDIUM | MEDIUM | P2 |
| 重複購買按鈕 | MEDIUM | LOW | P2 |
| 即時通知整合 | MEDIUM | MEDIUM | P2 |
| 子訂單操作按鈕 | LOW | HIGH | P3 |
| 訂單備註/留言 | LOW | MEDIUM | P3 |
| 子訂單合併檢視 | LOW | HIGH | P3 |

**Priority key:**
- P1: Must have for launch (v1.4)
- P2: Should have, add when possible (v1.x)
- P3: Nice to have, future consideration (v2+)

## Competitor Feature Analysis

| Feature | WooCommerce Multi-Vendor (MarketKing) | YITH Multi Vendor | BuyGo+1 Approach |
|---------|--------------|--------------|--------------|
| 子訂單顯示方式 | 在 My Account 同時顯示主訂單和子訂單，點擊子訂單查看詳情 | Admin 可看到所有子訂單，賣家只看到自己的 | FluentCart 前台整合，展開/折疊模式，優化手機版體驗 |
| 主訂單關聯 | 子訂單詳情頁有連結回主訂單，並顯示麵包屑 | 主訂單包含所有商品，子訂單分別顯示 | 使用麵包屑導航 + 視覺化層級關係 |
| 賣家資訊 | 子訂單顯示賣家名稱，可連結到店鋪頁 | 訂單中標示賣家 | 整合 BuyGo LINE 賣場，可直接聯絡賣家 |
| 結帳後摘要 | 按賣家分組顯示子訂單，最後顯示合併總計 | 訂單確認頁分別顯示各賣家訂單 | 結帳後摘要頁 + 會員中心訂單列表雙重顯示 |
| 訂單狀態 | 每個子訂單獨立狀態，主訂單顯示整體狀態 | 子訂單狀態獨立管理 | 狀態同步機制：子訂單全部完成 → 主訂單完成 |
| 手機版 UX | 基本 RWD，未特別優化 | 基本 RWD | Accordion UI 優化手機版滾動體驗 |

## Implementation Context (BuyGo+1 Specific)

### Existing Features to Leverage

BuyGo+1 已經建立的功能可以直接支援子訂單顯示：

1. **LINE 通知整合** — 可擴展到子訂單狀態變更通知
2. **多賣家權限隔離** — 已有賣家身份管理，子訂單自然對應賣家
3. **全域搜尋** — 可擴展支援子訂單編號搜尋
4. **Dashboard 統計** — 可新增子訂單相關指標

### FluentCart Integration Points

FluentCart 提供的前台客製化能力：

- **My Account Dashboard** — 已有訂單列表，擴展顯示子訂單
- **Customizable Blocks** — 使用區塊系統建構子訂單 UI
- **Template Override** — 可完全控制訂單顯示模板
- **Hooks & Filters** — 透過 action/filter 擴展功能

### Technical Constraints

- **資料來源：** FluentCart 訂單資料結構，需確認是否支援子訂單關聯
- **前端框架：** Vue 3 + Tailwind CSS（與 BuyGo+1 主外掛一致）
- **API 端點：** 需建立 REST API 取得子訂單資料
- **權限控制：** 用戶只能看到自己的訂單（已有機制）

## UI/UX Pattern Recommendations

基於研究結果，建議採用以下 UI 模式：

### 1. Accordion Pattern（展開/折疊）

**Why:** 91% 消費者會追蹤訂單，19% 會多次查看。Accordion 可在保持資訊可及性的同時避免頁面過長。

**Best Practices:**
- 使用 chevron (▼/▶) 或 +/- 圖示作為視覺提示
- 平滑動畫過渡（約 300ms，ease-in-out）
- 預設折疊，但記住用戶的展開狀態（localStorage）
- 手機版觸控目標至少 44px

### 2. Order Status Timeline（訂單狀態時間軸）

**Why:** 用戶希望減少焦慮，時間軸提供清晰的進度視覺化。

**Best Practices:**
- 使用圓點或圖示標示各階段
- 已完成階段用主題色，未完成用灰色
- 顯示預計完成時間（非絕對時間，減少客訴）

### 3. Breadcrumb Navigation（麵包屑導航）

**Why:** 用戶需要理解「我在哪裡」，特別是在主訂單/子訂單之間切換時。

**Format:**
```
我的訂單 > 訂單 #BGO-20260202-001 > 子訂單 #SUB-001
```

### 4. Hierarchical List（層級列表）

**Why:** 適合父子關係顯示，用縮排或視覺層級區分主訂單和子訂單。

**Implementation:**
- 主訂單用較大字體、深色
- 子訂單用縮排（16-24px）、較小字體、淺色

### 5. Product Thumbnail Grid（商品縮圖網格）

**Why:** 圖片識別速度比文字快 60 倍，特別是在商品多時。

**Best Practices:**
- 縮圖尺寸 50x50px（列表）或 80x80px（詳情）
- 使用 lazy loading 優化效能
- 失敗時顯示 placeholder 圖片

## Mobile-First Considerations

超過 60% 的電商流量來自手機，子訂單顯示必須優化手機體驗：

### Key Optimizations

1. **垂直堆疊布局** — 避免橫向滾動
2. **較大觸控目標** — 按鈕、展開區域至少 44x44px
3. **簡化資訊層級** — 手機版隱藏次要資訊（如商品 SKU）
4. **固定操作按鈕** — 重要操作（聯絡賣家、追蹤物流）固定在底部
5. **下拉刷新** — 支援下拉更新訂單狀態

## Accessibility Requirements

確保所有用戶都能使用：

- **ARIA 標籤：** Accordion 使用 `aria-expanded`、`aria-controls`
- **鍵盤導航：** 支援 Tab、Enter、Space 操作展開/折疊
- **螢幕閱讀器：** 確保訂單層級關係被正確朗讀
- **色盲友善：** 狀態不只用顏色區分，搭配圖示或文字
- **對比度：** WCAG AA 標準（至少 4.5:1）

## Performance Considerations

訂單列表可能很長，需要優化載入效能：

- **分頁載入：** 每頁 10-20 筆主訂單
- **延遲載入子訂單：** 只在展開時載入子訂單詳情
- **快取策略：** 訂單資料使用 localStorage 快取（TTL 5 分鐘）
- **圖片優化：** 商品縮圖使用 WebP 格式 + CDN
- **API 節流：** 狀態查詢使用 debounce（避免過度請求）

## Sources

### Multi-Vendor Platform Research
- [MarketKing - How MarketKing Splits Orders](https://woocommerce-multivendor.com/docs/how-marketking-splits-orders-composite-orders-sub-orders/)
- [Webkul - WordPress WooCommerce Marketplace Split Order](https://webkul.com/blog/wordpress-woocommerce-marketplace-split-order-plugin/)
- [YITH WooCommerce Multi Vendor](https://yithemes.com/themes/plugins/yith-woocommerce-multi-vendor/)
- [WooCommerce Multi Vendor Marketplace](https://woocommerce.com/products/multi-vendor-marketplace/)

### FluentCart Platform
- [FluentCart Changelog - Shipping status to order summary](https://docs.fluentcart.com/guide/changelog)
- [FluentCart Features - Customer Portal](https://fluentcart.com/all-features/)
- [FluentCart Shop Design and Customization](https://fluentcart.com/store-design/)

### UI/UX Patterns
- [Accordion UI Design Best Practices - PWSkills](https://pwskills.com/blog/accordions-ui-design-accordion-design/)
- [Accordion Checkout for eCommerce - Optimum7](https://www.optimum7.com/custom-functionality/accordion-checkout)
- [Baymard - 151 Order Tracking Page Design Examples](https://baymard.com/ecommerce-design-examples/63-order-tracking-page)
- [Order Tracking Design Best Practices - Malomo](https://gomalomo.com/order-tracking/order-tracking-design)

### E-commerce Best Practices
- [WooCommerce My Account Page Documentation](https://woocommerce.com/document/the-my-account-page/)
- [WooCommerce Order Management Guide 2026 - LitExtension](https://litextension.com/blog/woocommerce-order-management/)
- [Design Best Practices for Ecommerce Order Tracking Pages - Loop Returns](https://www.loopreturns.com/blog/design-best-practices-ecommerce-order-tracking-pages/)

### B2B & Customer Portal
- [B2B Customer Portal Best Practices - k-ecommerce](https://k-ecommerce.com/blog/b2b-ecommerce-customer-portal-best-practices)
- [B2B Marketplace Features in 2026 - Rigby Blog](https://www.rigbyjs.com/blog/b2b-marketplace-features)

### Industry Trends
- [Multi-vendor Marketplace Trends 2025-2026 - Shipturtle](https://www.shipturtle.com/blog/multivendor-marketplace-trends-2025-2026)
- [WooCommerce Development Best Practices for 2026 - Medium](https://pamsalon.medium.com/woocommerce-development-best-practices-for-ecommerce-growth-9cae5e8622ea)

---
*Feature research for: BuyGo+1 v1.4 子訂單顯示功能*
*Researched: 2026-02-02*
*Confidence: HIGH (基於 MarketKing、YITH、FluentCart 官方文件 + 多個 UI/UX 最佳實踐來源)*

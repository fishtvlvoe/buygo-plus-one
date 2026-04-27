<!--
本 change 為「評估型」，所有任務**禁止修改 src/ 程式碼**。
任務交付物皆為 Markdown 報告，最終彙整於 assessment-report.md。
-->

## 1. 環境與授權驗證（前置條件）

- [ ] 1.1 [Tool: bash] 確認部署站 FluentCart 版本：`wp plugin list | grep fluent-cart`，記錄到 `notes/env-check.md`
- [ ] 1.2 [Tool: bash] 確認 FluentCart Pro 授權狀態（影響 D1 可行性）：檢查 `wp-content/plugins/` 是否存在 `fluent-cart-pro/` 及其 license status，結果寫入 `notes/env-check.md`（Open Question #1 解答）
- [ ] 1.3 [Tool: bash] 驗收步驟：確認 `notes/env-check.md` 存在且包含版本號與授權狀態兩項結論

## 2. Assessment Report Structure — D1：AllocationService vs FluentCart Pro Inventory Ledger — 最高優先評估

- [ ] 2.1 [Tool: kimi] 深度分析 `includes/services/class-allocation-service.php` 全部 1024 行：列出 reserve/release/cancel 三大流程所有 fct_order_items line_meta 欄位寫入點、所有 race condition 防護、所有 batch release 邏輯。產出 `notes/allocation-service-anatomy.md`
- [ ] 2.2 [Tool: gemini] 查 FluentCart 1.3.22 進階庫存管理官方 API 規格：端點 URL、filter/action hooks 名稱、Ledger 欄位 schema。搜尋對象包含 `dev.fluentcart.com`、`docs.fluentcart.com`、GitHub `fluent-cart/` 組織公開 repo、FluentCart 官方 YouTube changelog 影片。結果寫入 `notes/fc-pro-inventory-api.md`（Open Question #2 解答）
- [ ] 2.3 [Tool: bash] 讀 FluentCart Pro plugin 原始碼（若 1.2 確認有授權）：`grep -r "inventory\|stock_ledger" wp-content/plugins/fluent-cart-pro/` 找實際 API 簽章，補進 `notes/fc-pro-inventory-api.md`
- [ ] 2.4 [Tool: kimi] 產出 AllocationService vs Pro Inventory Ledger 的 Gap Analysis 對照表（遵循 Assessment Report Structure 規範的六欄位：Current State / Upstream Mechanism / Gap Analysis / Migration Strategy / Risks / ROI Score）→ 寫入 `notes/d1-allocation-gap.md`
- [ ] 2.5 驗收：`notes/d1-allocation-gap.md` 涵蓋 A/B/C 三個候選策略各自的估計減少行數、前置條件、風險分級

## 3. Assessment Report — D2：ShippingStatusService 對齊 FluentCart Packing 物件 與 D3：LINE Flex Template 改吃 packing 欄位

- [ ] 3.1 [Tool: kimi] 分析 `includes/services/class-shipping-status-service.php` 與 `includes/services/class-order-shipping-manager.php`：列出所有自算重量邏輯、出貨狀態機轉移、line_meta 寫入點。產出 `notes/shipping-service-anatomy.md`
- [ ] 3.2 [Tool: gemini] 查 FluentCart 1.3.19+ Packing 物件 schema（name/dimensions/product_weight/shipping_weight）與其在 `type='split'` 子訂單上的繼承行為（Open Question #3）。結果寫入 `notes/fc-packing-schema.md`
- [ ] 3.3 [Tool: bash] 若本機有 FluentCart 1.3.19+ 實例：`wp eval 'var_dump(FluentCart\App\Models\Order::find(<test_id>)->packing)'` 實測子訂單是否有 packing，記錄到 `notes/fc-packing-schema.md`
- [ ] 3.4 [Tool: kimi] 分析 `includes/services/class-line-flex-templates.php` 中所有運送文案拼接點，對照 `$order->packing` 可提供的欄位，產出 D3 的欄位映射表，寫入 `notes/d3-line-flex-mapping.md`
- [ ] 3.5 [Tool: kimi] 產出 D2 + D3 合併的 Gap Analysis（Current State / Upstream Mechanism / Migration Strategy / Risks / ROI Score）→ `notes/d2-d3-shipping-gap.md`
- [ ] 3.6 驗收：`notes/d2-d3-shipping-gap.md` 必須標記 Open Question #3 的答案，未解答則整個 D2/D3 標 BLOCKED_BY_PREREQUISITE

## 4. Assessment Report — D4：LINE 產品圖片改走 R2/S3 外部 URL

- [ ] 4.1 [Tool: kimi] 分析 `includes/services/class-line-product-creator.php` 與 `includes/services/class-line-product-upload-handler.php`：列出所有 WP media 上傳點、attachment_id 依賴、下載 LINE OA 圖片的流程。產出 `notes/line-media-anatomy.md`
- [ ] 4.2 [Tool: gemini] 查 FluentCart 1.3.21 R2/S3 公開存取 API：上傳端點、存取權限設定、是否支援動態圖片上傳（Open Question #5）。結果寫入 `notes/fc-r2-s3-api.md`
- [ ] 4.3 [Tool: kimi] 產出 D4 Gap Analysis（含商業決策成本估算，標註「需 Fish 裁決是否付 R2/S3 費用」）→ `notes/d4-line-media-gap.md`
- [ ] 4.4 驗收：`notes/d4-line-media-gap.md` 明確分「技術可行性」與「商業決策」兩段結論

## 5. Assessment Report — D5：BatchCreateService — DEFER 至 1.3.23+

- [ ] 5.1 [Tool: bash] 查 FluentCart 近 3 個版本 release notes 是否提及 batch/bulk order API：`curl -s https://docs.fluentcart.com/guide/changelog | grep -iE "batch|bulk|mass"`，結果寫入 `notes/d5-batch-defer.md`
- [ ] 5.2 產出 D5 DEFER 記錄：次要評估觸發條件（FluentCart 1.3.23+ release note 出現 "batch" 關鍵字）、監控方法、下次評估截止日期 → 併入 `notes/d5-batch-defer.md`
- [ ] 5.3 驗收：`notes/d5-batch-defer.md` 存在明確的 next-review trigger

## 6. Open Questions Registry

- [ ] 6.1 彙整 task 1.2、2.2–2.3、3.2–3.3、4.2 的驗證結果，產出 `notes/open-questions.md`，每項必須標 RESOLVED / UNRESOLVED 並附證據連結（符合 Open Questions Registry spec）
- [ ] 6.2 驗收：`notes/open-questions.md` 五個 Open Question 全數列出且每項有驗證方法

## 7. Decision Framework Output — 最終整合

- [ ] 7.1 [Tool: kimi] 彙整 `notes/` 所有產出，依 Decision Framework Output spec 規範產出單一 ranked decision table（含 Item / Current Lines / Est. Reduction / Decision: ADOPT/DEFER/REJECT / Next Action），寫入 `assessment-report.md` 的「Decision Table」段落
- [ ] 7.2 產出 `assessment-report.md` 完整報告，依序包含：Executive Summary、Environment Check（Task 1 結果）、每個評估項目（D1–D5）詳細段落、Open Questions Registry、Decision Table、Recommended Next Changes
- [ ] 7.3 Assessment Scope Discipline 自檢：搜尋 `assessment-report.md` 與 `notes/*.md` 是否含超過 5 行的程式碼片段或 pseudo migration script，有則移除並替換為「詳見後續實作 change」的 reference
- [ ] 7.4 驗收：`assessment-report.md` 通過所有 4 個 spec requirement（Report Structure / Open Questions Registry / Decision Framework Output / Scope Discipline），由 Fish 目視確認

## 8. Change 收尾

- [ ] 8.1 [Tool: bash] `spectra validate fluentcart-13-alignment` 通過
- [ ] 8.2 [Tool: bash] 本 change **不做 apply**（違反 proposal Non-Goals）；Fish 審閱 `assessment-report.md` 後直接 archive
- [ ] 8.3 若 Decision Table 有 ADOPT 項：告知 Fish 建議的下一個實作 change 名稱（如 `migrate-allocation-to-fc-ledger`），等 Fish 決定是否開新 change

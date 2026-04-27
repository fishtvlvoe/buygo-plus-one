# BuyGo Plus One — 文檔治理樞紐

> 本檔案定義文檔分層、命名規則與維護觸發條件。GenAI Agent 執行任何文檔任務前，必須先讀本檔案。

## SDD 文檔分類

| 分類 | 目錄 | 命名格式 | 觸發條件 |
|------|------|---------|---------|
| SA (系統分析) | `docs/analysis/` | `SA-{3-digit}_{desc}.md` | 里程碑或重大架構變更時 |
| ADR (架構決策) | `docs/adr/` | `ADR-{3-digit}_{desc}.md` | 跨模組的 either/or 技術決策 |
| SPEC (介面契約) | `docs/specs/` | `SPEC-{3-digit}_{desc}.md` | API 新增或行為變更時（**強制**） |
| INFRA (基礎設施) | `docs/infra/` | `INFRA-{3-digit}_{desc}.md` | 部署拓撲或 CI 變更時 |

命名規則正則：`^(SA|ADR|SPEC|INFRA)-\d{3}_[a-z0-9-]+\.md$`

## 來源真實性

SPEC 是開發和 GenAI 的主要參考。每次 API 新增或行為變更時，直接更新 SPEC 內容與 Changelog。

**最小維護規則**：所有涉及介面或行為變更的 PR，MUST 更新相應的 SPEC 內容與 Changelog。

## ADR 觸發條件

**✅ 需要 ADR**：
- 架構分層策略（如：Service 層 vs API 層分界）
- 認證方案設計（如：JWT 雙 token）
- 要不要的技術決策（Vite vs 傳統打包；Shell 快取 vs Server-Rendered）
- 跨模組共用元件設計決策

**❌ 不需 ADR**：
- 新增 CRUD API
- 變更快取 TTL 預設值
- 簡單 Bug 修正

## Candidate Documents (待建立)

| 候選文檔 | 觸發條件 | 狀態 |
|---------|---------|------|
| `SA-001_order-processing-pipeline.md` | 訂單處理流程全景圖（待里程碑確認） | — |
| `SPEC-001_allocation-variation-filter.md` | 庫存分配變數篩選 API | ✓ 已有 openspec/specs/ |
| `SPEC-002_cancel-child-order.md` | 子訂單取消功能 | ✓ 已有 openspec/specs/ |
| `SPEC-003_product-fluentcart-delete-sync.md` | 商品刪除時 FluentCart 同步 | ✓ 已有 openspec/specs/ |
| `SPEC-004_product-variation-stats-display.md` | 商品變數統計顯示 | ✓ 已有 openspec/specs/ |
| `SPEC-005_remove-order-item.md` | 訂單項目移除 | ✓ 已有 openspec/specs/ |
| `SPEC-006_fluentcart-13-alignment.md` | FluentCart 13 相容性對齊 | ✓ 已有 openspec/specs/ |
| `SPEC-007_cancel-order-item-from-detail.md` | 訂單詳情頁取消商品行 | ✓ 已建立 |
| `SPEC-008_order-quantity-fluentcart-sync.md` | 訂單數量與 FluentCart 庫存同步 | ✓ 已建立 |
| `ADR-001_shell-cache-vs-buygo-cache.md` | Shell 快取架構決策 | ✓ 已建立 |
| `ADR-002_spa-performance-upgrade.md` | SPA 效能升級遷移路徑 | ✓ 已建立 |
| `ADR-003_line-order-query-sync-boundary.md` | LINE 訂單查詢邊界決策 | ✓ 已建立 |

## 文檔索引

| 文檔 | 路徑 | 狀態 |
|------|------|------|
| Shell 快取架構 | `docs/features/shell-cache-architecture.md` | ✓ 設計文檔 |
| SPA 效能升級 | `docs/features/spa-performance-upgrade.md` | ✓ 設計文檔 |
| LINE 訂單查詢 | `docs/features/line-order-query.md` | ✓ 需求文檔 |
| 買家入口問題 | `docs/features/buyer-portal-issues.md` | ✓ 設計文檔 |
| 買家入口詳設 | `docs/features/buyer-portal.md` | ✓ 設計文檔 |
| BuyGo 快取分析 | `docs/features/buygo-cache-analysis.md` | ✓ 分析文檔 |
| 訂單取消規格 | `docs/specs/SPEC-007-cancel-order-item-from-detail.md` | ✓ 已建立 |
| 商品刪除同步 | `docs/specs/SPEC-008-order-quantity-fluentcart-sync.md` | ✓ 已建立 |
| Shell 決策 | `docs/adr/ADR-001-shell-cache-vs-buygo-cache.md` | ✓ 已建立 |
| 效能升級決策 | `docs/adr/ADR-002-spa-performance-upgrade.md` | ✓ 已建立 |
| LINE 邊界決策 | `docs/adr/ADR-003-line-order-query-sync-boundary.md` | ✓ 已建立 |

---

Retrofit 產生於 2026-04-27，來源：ZeroSpec 模板 + docs/features/ + openspec/specs/

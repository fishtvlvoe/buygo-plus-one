## Why

FluentCart 於 2026-04 釋出 1.3.19–1.3.22 連續四個版本，新增多項與 BuyGo+1 自製邏輯高度重疊的原生機制（進階庫存 Ledger、Packing 物件、R2/S3 儲存）。在擴充新功能前，需先評估哪些自製輪子可退場，避免後續升級衝突與維護重工。

本 change 只產出「評估報告」，**不修改任何程式碼**，結論交由 Fish 裁決是否另開實作型 change。

## What Changes

- 產出 FluentCart 1.3.19–1.3.22 新機制對 BuyGo+1 既有 Service 的逐項 Gap Analysis
- 產出重構優先順序（ROI 排序）與 Breaking Change 風險清單
- 產出每個候選項目的「現況 → 目標 → 遷移策略」對照表
- 標記關鍵不確定性（官方 docs 未同步、Pro 授權未確認）待驗證

本 change **不**包含：
- 任何 PHP / JS 程式碼修改
- 任何 Service class 重構
- 任何資料庫遷移

## Non-Goals

- **不進行實作**：本 change 交付物為 Markdown 分析報告，禁止排入 apply wave 執行程式碼變更
- **不包含 BatchCreateService 改造**：延後至 FluentCart 1.3.23+ 推出官方 batch API 後再評估
- **不做效能基準測試**：沒有實測對照組，只做靜態程式碼 vs 官方文件的 Gap Analysis
- **不取代 Spectra apply 流程**：評估通過後必須另開獨立實作 change（如 `migrate-allocation-to-fc-ledger`）走完整 SDD loop
- **不涉及 UX 或業務流程變更**：只評估「同樣業務結果下能否改用官方機制實現」

## Alternatives Considered

- **直接開實作 change 邊做邊評估**：被否決。官方 1.3.22 docs 尚未同步，Pro Inventory API 端點未公開，盲目實作風險高
- **只在對話中討論不寫 change**：被否決。評估報告需被後續實作 change 引用，必須落地為工件

## Capabilities

### New Capabilities

- `fluentcart-alignment-assessment`: 定義 FluentCart 版本對齊評估的交付規範 — 每次 FluentCart 主要版本升級時，BuyGo+1 需產出結構化評估報告（現況、官方機制、ROI、風險、Open Questions），作為後續實作 change 的決策依據

### Modified Capabilities

（無）

## Impact

- **Affected specs**: 新增 `fluentcart-alignment-assessment` spec（規範評估報告格式，非程式碼規範）
- **Affected code**: 無（評估報告不碰 src）
- **受評估的 Service 層**（僅做靜態分析，不修改）：
  - `includes/services/class-allocation-service.php`（1024 行）
  - `includes/services/class-shipping-status-service.php`
  - `includes/services/class-order-shipping-manager.php`
  - `includes/services/class-line-flex-templates.php`
  - `includes/services/class-line-product-creator.php`
  - `includes/services/class-line-product-upload-handler.php`
  - `includes/services/class-batch-create-service.php`（僅標 DEFER，不深入分析）
- **外部依賴**：FluentCart 1.3.22（核心）、FluentCartPro 1.3.22（Inventory Ledger 前置條件，待確認授權）

## Key Uncertainties（決策前必須驗證）

1. **FluentCart 1.3.22 官方 docs 未同步**：docs.fluentcart.com/guide/changelog 目前最新為 1.3.21。Pro 進階庫存 API 端點、新 filter/action hooks 具體名稱、參數簽章**全數未公開**。Gap Analysis 需靠 `dev.fluentcart.com` 側面推敲 + 直接讀 FluentCart 原始碼。
2. **FluentCart Pro 授權狀態未確認**：進階庫存管理（Inventory Ledger）為 Pro 功能。若 BuyGo+1 目標部署站未持有 Pro 授權，項目 #1（最高 ROI）整個無效。
3. **Packing 物件 Schema 未驗證**：1.3.19 的 `$order->packing` 欄位結構、是否在 split 子訂單上同步、是否可在 LINE 通知時序取得，皆需實機驗證。

## Deliverables

| 檔案 | 內容 |
|------|------|
| `proposal.md` | 本文件 |
| `design.md` | 5 個候選項目的詳細 Gap Analysis、遷移策略、風險分級 |
| `tasks.md` | 評估執行步驟（派 Kimi 做程式碼對照、派 Gemini 查官方 API） |
| `assessment-report.md`（tasks 產出） | 最終 ROI 排序與實作建議總表 |

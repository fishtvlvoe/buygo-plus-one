# BuyGo+1 開發文件總覽

> **用途**：所有開發文件的導航入口點。
>
> **Claude Code 入口**：`/CLAUDE.md`（專案根目錄）

---

## 快速導航

| 分類 | 文件 | 說明 |
|------|------|------|
| **進度追蹤** | [IMPLEMENTATION-CHECKLIST.md](planning/IMPLEMENTATION-CHECKLIST.md) | 實施檢查清單（每次對話開始時讀取） |
| **編碼規範** | [CODING-STANDARDS.md](development/CODING-STANDARDS.md) | 編碼規範和模式（修改代碼前必讀） |
| **已修復問題** | [BUGFIX-CHECKLIST.md](bugfix/BUGFIX-CHECKLIST.md) | 防止再次踩坑 |

---

## 文件結構

```
docs/
├── INDEX.md                    # 本文件（導航入口）
│
├── development/                # 開發相關
│   ├── CODING-STANDARDS.md     # 編碼規範（CSS/JS命名、HTML結構）
│   ├── ARCHITECTURE.md         # 技術架構（資料庫、API、LINE）
│   ├── FRONTEND-ARCHITECTURE.md # 前端架構（Vue 組件、狀態管理）
│   └── TROUBLESHOOTING.md      # 問題排解指南
│
├── planning/                   # 計畫相關
│   ├── IMPLEMENTATION-CHECKLIST.md  # 實施檢查清單（進度追蹤）
│   ├── DEVELOPMENT-PLAN.md     # 開發計畫
│   ├── LAUNCH-PLAN.md          # 上線計畫
│   ├── TODO-BUYGO.md           # 待辦任務清單
│   └── 多樣式商品工作計畫.md    # 多樣式商品功能計畫
│
├── bugfix/                     # 問題修復
│   ├── BUGFIX-CHECKLIST.md     # 已修復問題清單
│   └── Phase3-UI-Optimization-Complete-Report.md  # UI優化報告
│
├── testing/                    # 測試相關
│   ├── TESTING.md              # 測試指南
│   ├── TESTING-CHECKLIST.md    # 測試檢查清單
│   ├── INTEGRATION-TESTING.md  # 整合測試指南（buygo-line-notify）
│   └── WEBHOOK_TEST_MODE.md    # Webhook 測試模式
│
└── reference/                  # 參考資料
    ├── PERMISSION-SYSTEM.md    # 權限系統說明
    └── check-encryption-key.md # 加密金鑰檢查
```

---

## 依場景查找文件

### 開始新任務前
1. [IMPLEMENTATION-CHECKLIST.md](planning/IMPLEMENTATION-CHECKLIST.md) - 了解目前進度
2. [TODO-BUYGO.md](planning/TODO-BUYGO.md) - 查看待辦清單

### 修改代碼前
1. [CODING-STANDARDS.md](development/CODING-STANDARDS.md) - 了解編碼規範
2. [BUGFIX-CHECKLIST.md](bugfix/BUGFIX-CHECKLIST.md) - 確認不會破壞已修復功能

### 遇到問題時
1. [TROUBLESHOOTING.md](development/TROUBLESHOOTING.md) - 問題排解
2. [BUGFIX-CHECKLIST.md](bugfix/BUGFIX-CHECKLIST.md) - 查看是否為已知問題

### 了解架構時
1. [ARCHITECTURE.md](development/ARCHITECTURE.md) - 後端架構
2. [FRONTEND-ARCHITECTURE.md](development/FRONTEND-ARCHITECTURE.md) - 前端架構

### 測試相關
1. [TESTING-CHECKLIST.md](testing/TESTING-CHECKLIST.md) - 測試檢查清單
2. [TESTING.md](testing/TESTING.md) - 測試指南
3. [INTEGRATION-TESTING.md](testing/INTEGRATION-TESTING.md) - 整合測試指南（buygo-line-notify）

---

## 外部參考

| 文件 | 位置 | 說明 |
|------|------|------|
| 完整計畫 | `~/.claude/plans/golden-hopping-mockingbird.md` | 5 階段實施計畫（繁體中文） |
| Debug 技能 | `~/.claude/skills/debug-buygo/SKILL.md` | BuyGo+1 除錯技能 |

---

**最後更新**：2026-01-24

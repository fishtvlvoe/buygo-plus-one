---
phase: 23-deployment-optimization
plan: 03
subsystem: portal-ui
tags: [sidebar, community-link, navigation, user-experience]

# Dependency graph
requires:
  - phase: 23-01, 23-02
    provides: 部署基礎設施
provides:
  - Portal 社群連結按鈕
  - 桌面版和手機版社群導航
affects: [sidebar, navigation]

# Tech tracking
tech-stack:
  patterns:
    - Alpine.js 響應式 class binding
    - Heroicons SVG icon
    - Tailwind CSS hover 效果

key-files:
  modified:
    - components/shared/new-sidebar.php

key-decisions:
  - "使用 home_url('/community') 動態生成社群 URL"
  - "target=_blank 在新分頁開啟"
  - "桌面版和手機版分別實作"

# Metrics
duration: 本地開發完成
completed: 2026-02-01
---

# Phase 23 Plan 03: Portal UI 社群連結按鈕 Summary

**Status:** ✅ COMPLETE (本地開發)

## What Was Done

在 Portal 側邊欄新增「前往社群」按鈕，讓賣家和小幫手快速進入 FluentCommunity：

1. **桌面版側邊欄按鈕** (第 47-65 行)
   - 位於收合按鈕上方
   - 使用 Heroicons "users" SVG icon
   - hover 效果：藍色背景 + 主題色 icon
   - 收合時只顯示 icon，展開時顯示「前往社群」文字

2. **手機版選單連結** (第 118-130 行)
   - 位於選單底部
   - 包含 icon 和「前往社群」文字
   - 點擊後在新分頁開啟社群頁面

## Code Changes

`components/shared/new-sidebar.php`:
- 新增桌面版社群按鈕（收合按鈕上方）
- 新增手機版社群連結（選單底部）

## Verification

- [x] 桌面版側邊欄底部顯示社群 icon 按鈕
- [x] 社群按鈕使用正確的 SVG 圖示（群組/社群 icon）
- [x] 點擊後在新分頁開啟社群頁面（target="_blank"）
- [x] hover 效果正確（藍色背景 + 主題色 icon）
- [x] sidebar 收合時，icon 仍然顯示
- [x] sidebar 展開時，顯示「前往社群」文字
- [x] 手機版選單底部包含社群連結
- [x] 無權限限制（所有登入用戶都能看到）

## Notes

- **重要**：此版本僅完成本地開發，尚未同步到雲端
- 等待開發版穩定後，會發布到 GitHub 成為正式版
- 社群 URL：`/community`（使用 `home_url('/community')` 動態生成）

---

*Phase: 23-deployment-optimization*
*Completed: 2026-02-01 (本地開發)*

---
phase: 57-route-and-quantity-selection
plan: 01
subsystem: ui
tags: [vue3, spa, router, tailwind, php]

requires:
  - phase: 56-batch-create-api
    provides: batch-create 後端 API 已建立，前端路由可安全連結

provides:
  - batch-create SPA 路由（useRouter.js 路由表 + template.php 元件表 + PHP catch-all 已涵蓋）
  - admin/partials/batch-create.php 空殼頁面（Plan 02 替換為完整 UI）
  - products 列表頁「上架」按鈕（goToBatchCreate 導航至 batch-create 路由）

affects:
  - 57-02-PLAN（批量上架數量選擇 UI，將替換 batch-create.php 骨架）
  - 58-batch-form（需要 batch-create 路由已存在）

tech-stack:
  added: []
  patterns:
    - "SPA 新頁面接入模式：useRouter.js routes + permissions + template.php pageComponents + page_partials + titles 四處同步"
    - "空殼 partial 模式：先建立最小可用骨架，後續 Plan 替換完整 UI"

key-files:
  created:
    - admin/partials/batch-create.php
  modified:
    - includes/views/composables/useRouter.js
    - includes/views/template.php
    - admin/partials/products.php
    - includes/views/composables/useProducts.js

key-decisions:
  - "class-routes.php 不需修改 — 現有 catch-all regex [a-z-]+ 已涵蓋 batch-create 含連字符路徑"
  - "按鈕放在 v-show='currentView === list' 範圍內，自動只在列表視圖顯示，無需額外 v-if"
  - "空殼頁面使用 setup() 模式而非 Options API，與其他頁面元件保持一致"

patterns-established:
  - "goToBatchCreate 放在 useProducts.js 而非 products.php 內聯，維持邏輯集中於 composable 的慣例"

requirements-completed: [ROUTE-01, ROUTE-02]

duration: 11min
completed: 2026-03-02
---

# Phase 57 Plan 01: 路由與數量選擇 Summary

**batch-create SPA 路由入口 + 商品列表「上架」按鈕，以 Vue3 composable 模式接入現有路由系統**

## Performance

- **Duration:** 11 min
- **Started:** 2026-03-02T10:36:24Z
- **Completed:** 2026-03-02T10:47:30Z
- **Tasks:** 2
- **Files modified:** 5 (4 modified, 1 created)

## Accomplishments

- useRouter.js 路由表和 permissions 加入 batch-create 項目，SPA 可正確解析和導航
- template.php 同步更新 pageComponents、page_partials、titles 三處，直接存取 /buygo-portal/batch-create/ 可正確載入
- admin/partials/batch-create.php 空殼頁面建立，BatchCreatePageComponent 已定義（Plan 02 填入完整 UI）
- products.php 搜尋框右側加入藍色「上架」按鈕，useProducts.js 提供 goToBatchCreate() 導航方法

## Task Commits

每個 Task 獨立提交：

1. **Task 1: PHP + JS 路由註冊和 SPA 元件表擴充** - `0e492b1` (feat)
2. **Task 2: 商品列表頁「+ 上架」按鈕** - `aa454eb` (feat)

## Files Created/Modified

- `includes/views/composables/useRouter.js` — 路由表加入 'batch-create' -> 'BatchCreatePageComponent'，permissions 加入 'products' 權限
- `includes/views/template.php` — pageComponents、page_partials、onPageChange titles 三處同步加入 batch-create
- `admin/partials/batch-create.php` — 新建空殼頁面，定義 BatchCreatePageComponent（setup 模式）
- `admin/partials/products.php` — toolbar 加入藍色「上架」按鈕，@click="goToBatchCreate"
- `includes/views/composables/useProducts.js` — 加入 goToBatchCreate() 方法並在 return 物件暴露

## Decisions Made

- **class-routes.php 不需修改**：現有 catch-all `^buygo-portal/([a-z-]+)/?$` 中 `[a-z-]+` 已合法匹配含連字符的 `batch-create`，驗證確認後無需加額外規則
- **按鈕放在現有 v-show="currentView === 'list'" 內**：自然繼承列表視圖的顯示條件，無需額外 v-if
- **goToBatchCreate 放在 useProducts.js**：維持邏輯集中於 composable 的既有慣例，不將業務邏輯內聯到模板

## Deviations from Plan

None — 計畫執行完全照原計畫進行。唯一的「確認」動作是驗證現有 catch-all regex 已涵蓋 batch-create（計畫文件已預見此情況）。

## Issues Encountered

None

## User Setup Required

None — 純前端變更，無需外部服務配置。

## Next Phase Readiness

- batch-create 路由和空殼頁面就位，Plan 02 可立即開始實作數量選擇 UI
- 導航入口（商品列表「上架」按鈕）已可點擊驗證路由切換效果
- 無阻礙事項

---
*Phase: 57-route-and-quantity-selection*
*Completed: 2026-03-02*

## Self-Check: PASSED

- FOUND: includes/views/composables/useRouter.js
- FOUND: includes/views/template.php
- FOUND: admin/partials/batch-create.php
- FOUND: admin/partials/products.php
- FOUND: includes/views/composables/useProducts.js
- FOUND commit: 0e492b1 (Task 1)
- FOUND commit: aa454eb (Task 2)

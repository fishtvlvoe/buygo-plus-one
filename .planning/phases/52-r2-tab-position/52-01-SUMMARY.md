---
phase: 52-r2-tab-position
plan: 01
status: complete
started: 2026-02-24
completed: 2026-02-24
---

## Summary

修改 `bgo-r2/admin/class-settings.php` 的 `register_buygo_tab()` 方法，從直接附加 `$tabs['r2']` 改為使用 `array_search` + `array_slice` 定位 `roles` 鍵，將 R2 Tab 插入到角色權限之後。

## Changes

### Modified Files
- `bgo-r2/admin/class-settings.php` — `register_buygo_tab()` 方法（第 52-63 行）

### What Changed
- 原本：`$tabs['r2'] = __('R2 圖床', 'bgo-r2')` 直接附加到陣列尾部
- 現在：用 `array_search('roles', array_keys($tabs))` 找到 roles 的位置，用 `array_slice` 將 R2 插入其後
- Fallback：如果找不到 roles 鍵，R2 插入到最前面

### Commits
- `15d1a39` — feat: R2 Tab 位置調整 — 插入到角色權限之後（bgo-r2 repo）

## Self-Check: PASSED

- [x] PHP 語法檢查通過
- [x] array_slice 位置插入邏輯正確
- [x] Fallback 處理（roles 不存在時）
- [x] 不影響方法簽名和類別結構

## Verification

- **自動**: `php -l` 通過
- **手動**: 需在 https://test.buygo.me/wp-admin/admin.php?page=buygo-plus-one 確認 Tab 順序

## Deviations

無。完全依照計畫執行。

## Key Files

### Created
（無新建檔案）

### Modified
- `bgo-r2/admin/class-settings.php`

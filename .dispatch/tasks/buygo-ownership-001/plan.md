# BuyGo 多租戶隔離修復

## Worker 1 — Kimi 分析
- [ ] 讀 class-api.php：找 check_permission()、get_current_seller_id()、accessible_seller_ids 模式
- [ ] 讀 class-products-api.php：列出所有單筆端點方法名 + 行號
- [ ] 讀 class-orders-api.php：列出所有單筆端點方法名 + 行號
- [ ] 讀 class-customers-api.php：列出所有單筆端點方法名 + 行號
- [ ] 讀 class-shipments-api.php：列出所有單筆端點方法名 + 行號
- [ ] 設計 ownership guard 介面（方法名、參數、回傳值）
- [ ] 輸出 .dispatch/tasks/buygo-ownership-001/analysis.md

## Worker 2 — Sonnet 寫碼（等 analysis.md 完成後執行）
- [ ] 看 analysis.md，在 tests/ 寫跨 seller 403 測試
- [ ] 在 class-api.php 實作 ownership guard helper
- [ ] 修改 4 個 API 檔套用 guard
- [ ] 跑 composer test 確認全過

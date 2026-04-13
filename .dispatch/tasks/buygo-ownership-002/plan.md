# BuyGo 多租戶隔離修復 — Worker 2（寫碼）

- [x] 讀 analysis.md 了解設計
- [x] 讀 class-api.php 了解現有結構，找到加入 guard 方法的位置
- [x] 讀 class-customer-edit-service.php 行 214-246 複製 check_ownership 邏輯
- [x] 在 tests/ 建立 OwnershipGuardTest.php（跨 seller 存取應回傳 403）
- [x] 在 class-api.php 實作 verify_product_ownership()、verify_order_ownership()、verify_customer_ownership()、verify_shipment_ownership()
- [x] 修改 class-products-api.php：12 個端點加入 guard（依 analysis.md 行號）
- [x] 修改 class-orders-api.php：6 個端點加入 guard
- [x] 修改 class-customers-api.php：2 個端點加入 guard（get_customer + update_note）
- [x] 修改 class-shipments-api.php：9 個端點加入 guard
- [x] 執行 composer test，確認全部通過
- [x] 把結果寫入 .dispatch/tasks/buygo-ownership-002/output.md

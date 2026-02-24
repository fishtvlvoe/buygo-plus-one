# 56-02 Summary: Reserved_API 端點替換

## 狀態：完成

## 產出

### 修改檔案
- `includes/api/class-reserved-api.php` — batch_create 從 501 骨架替換為真實邏輯

## 變更內容

### 1. 權限升級
- 從 `API::check_permission` 升級為 `API::check_permission_with_scope('products')`
- 小幫手必須有「商品管理」權限才能使用批量上架

### 2. batch_create 方法實作
- 接受 JSON body：`{ "items": [...] }` 或直接傳陣列
- 呼叫 `BatchCreateService::batchCreate()` 處理
- 根據結果回傳適當的 HTTP 狀態碼：
  - 200：全部成功或部分成功
  - 400：請求格式錯誤（非 JSON 陣列）
  - 403：配額不足
  - 422：其他業務錯誤（空陣列、超過上限）

### 3. 路由註冊更新
- 新增 `args` 定義（items 參數）
- 更新類別註解，batch-create 移出「預留端點」列表

### 4. Autoload
- BatchCreateService 透過 PSR-4 autoloader 自動載入（`BuyGoPlus\Services\BatchCreateService` → `includes/services/class-batch-create-service.php`）
- 不需要在 class-api.php 額外 require

## 驗證
- `php -l` 語法檢查通過
- 127 個測試全部通過（含 BatchCreateService 的 12 個）
- `grep -c "not_implemented.*Batch"` 回傳 0（501 已移除）

## Git
- Commit: `feat: batch-create API 端點實作 — 替換 501 骨架`

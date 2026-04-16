## Why

管理員目前無法在後台對單一子訂單執行取消操作——只能整筆父訂單取消，導致部分商品已就緒、部分商品需退出時操作過度。客戶先下訂不付款的流程也意味著取消不涉及退款，應是低風險的即時操作。

## What Changes

- 新增 `AllocationService::cancelChildOrder(int $child_order_id)` 方法：將子訂單 status 改為 `cancelled`，並自動釋放該子訂單佔用的庫存分配名額
- 新增 REST API endpoint `DELETE /child-orders/{child_order_id}`（後台管理員權限），呼叫上述 Service 方法
- 後台訂單詳情頁的子訂單列表，針對 `shipping_status = 'unshipped'` 的子訂單顯示「取消」按鈕；已出貨（shipped/completed）或已取消（cancelled）的子訂單不顯示

## Non-Goals

- 不處理退款（本系統客戶先下訂不付款）
- 不影響父訂單狀態
- 不支援批次取消多筆子訂單
- 不允許取消 shipping_status 非 `unshipped` 的子訂單（已分配出貨的不可取消）
- 不提供客戶前台自助取消（操作者限後台管理員）

## Capabilities

### New Capabilities

- `cancel-child-order`: 管理員取消單一「待出貨」子訂單，並自動釋放對應的庫存分配

### Modified Capabilities

（無）

## Impact

- 新增檔案：`includes/api/class-child-orders-api.php`（新增 DELETE route）
- 修改檔案：`includes/services/class-allocation-service.php`（新增 `cancelChildOrder` 方法）
- 修改檔案：後台訂單詳情頁前端 JS/PHP（新增取消按鈕與 API 呼叫）

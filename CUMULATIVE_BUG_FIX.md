# Cumulative Quantity Bug Fix

## Problem

When allocating stock or splitting orders multiple times, the quantity was being double-counted, causing:
- Parent order item showing: 已分配 3 (should be 2)
- Total quantities being off by 1
- Example: Order #695 with 10 items, first allocation creates child with qty=1 ✓, but second allocation shows qty=2 instead of qty=1 ✗

**Root Cause**: Old plugin and new plugin both had hooks that modified `_allocated_qty`, causing conflicting updates.

## Solution

Changed from **incremental updates** to **recalculation from actual child orders**.

Instead of:
```
_allocated_qty = old_value + new_allocation
```

Now using:
```
_allocated_qty = SUM(all child order items)
```

This ensures quantities stay synchronized with the actual database state, regardless of which plugins are modifying the data.

## Files Modified

### 1. `includes/services/class-allocation-service.php` (allocateStock)

**Before**: Incremented `_allocated_qty` on parent order item
```php
$meta_data['_allocated_qty'] = $already_allocated + $to_allocate;
```

**After**: Queries actual child orders, then sets the total
```php
$actual_allocated = $wpdb->get_var(
    "SELECT COALESCE(SUM(oi.quantity), 0)
     FROM fct_order_items oi
     INNER JOIN fct_orders o ON oi.order_id = o.id
     WHERE o.parent_id = $order_id AND o.type = 'split'"
);
$new_allocated_total = $actual_allocated + $to_allocate;
$meta_data['_allocated_qty'] = $new_allocated_total;
```

Also fixed product-level `_buygo_allocated` to recalculate from child orders instead of incrementing.

### 2. `includes/api/class-orders-api.php` (split_order)

Added synchronization after creating child order to ensure parent order item's `_allocated_qty` is recalculated from all current child orders.

## Testing

1. Create a new test product (or use existing)
2. Create test orders for that product
3. Allocate stock **twice**:
   - First allocation: Child order should show qty=1, parent should show _allocated_qty=1
   - Second allocation: New child order should show qty=1, parent should show _allocated_qty=2
   - Total allocation should be 2 (not 3)

## Backward Compatibility

This fix is **fully compatible** with the old plugin because:
- It doesn't break old data structures
- It forces recalculation instead of relying on hook execution order
- If old plugin hooks run and modify `_allocated_qty`, they won't cause double-counting
  because new allocations will query the actual child orders, not read stale meta values

## Deployment

Changes automatically deployed to InstaWP staging environment via GitHub webhook.

Visit: https://buygo1.instawp.xyz/buygo-portal/products/ to test

<?php

namespace BuyGoPlus\Services;

use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Allocation Service - 庫存分配服務
 * 
 * 處理商品庫存分配給訂單的邏輯
 * 
 * @package BuyGoPlus\Services
 * @version 1.0.0
 */
class AllocationService
{
    private $batchService;
    private $queryService;
    private $writeService;
    private $calculator;

    public function __construct()
    {
        $this->queryService = new AllocationQueryService();
        $this->batchService = new AllocationBatchService($this, $this->queryService);
        $this->writeService = new AllocationWriteService($this, $this->queryService, $this->batchService);
        $this->calculator = new AllocationCalculator($this);
    }

    public function getAllVariationIds($variation_id)
    {
        return $this->queryService->getAllVariationIds($variation_id);
    }

    public function getProductOrders($product_id)
    {
        return $this->queryService->getProductOrders($product_id);
    }

    public function updateOrderAllocations($product_id, $allocations)
    {
        return $this->writeService->updateOrderAllocations($product_id, $allocations);
    }

    public function validateAdjustment(
        int $product_id,
        int $order_id,
        int $new_quantity,
        int $current_allocated,
        int $shipped_qty,
        int $order_quantity
    ) {
        return $this->calculator->validateAdjustment(
            $product_id,
            $order_id,
            $new_quantity,
            $current_allocated,
            $shipped_qty,
            $order_quantity
        );
    }

    public function adjustAllocation(int $product_id, int $order_id, int $new_quantity)
    {
        return $this->calculator->adjustAllocation($product_id, $order_id, $new_quantity);
    }

    public function cancelChildOrder(int $child_order_id): bool|\WP_Error
    {
        return $this->writeService->cancelChildOrder($child_order_id);
    }

    public function syncAllocatedQtyBatch(array $items): void
    {
        $this->writeService->syncAllocatedQtyBatch($items);
    }

    public function allocateAllForCustomer(int $product_id, int $order_item_id, int $customer_id)
    {
        return $this->writeService->allocateAllForCustomer($product_id, $order_item_id, $customer_id);
    }
}

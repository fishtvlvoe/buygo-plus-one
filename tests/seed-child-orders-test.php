<?php
/**
 * 測試用假資料：子訂單分配摘要功能
 *
 * 建立父訂單 + 子訂單 + _allocated_qty，用於測試會員中心分配摘要顯示
 *
 * 使用方式：在瀏覽器中存取
 * https://test.buygo.me/wp-content/plugins/buygo-plus-one/tests/seed-child-orders-test.php
 */

$doc_root = $_SERVER['DOCUMENT_ROOT'] ?? '';
$wp_load = $doc_root . '/wp-load.php';
if (!file_exists($wp_load)) {
    $wp_load = '/Users/fishtv/Local Sites/buygo/app/public/wp-load.php';
}
require_once $wp_load;

if (!current_user_can('manage_options')) {
    die('需要管理員權限');
}

header('Content-Type: text/html; charset=utf-8');
echo '<html><head><meta charset="utf-8"><title>子訂單分配摘要測試資料</title></head><body>';
echo '<h1>建立子訂單分配摘要測試資料</h1><pre>';

global $wpdb;

// ================================
// 確保測試客戶存在（至少 2 位）
// ================================
$customers = $wpdb->get_results(
    "SELECT id, first_name, last_name, email FROM {$wpdb->prefix}fct_customers ORDER BY id ASC LIMIT 2"
);

if (count($customers) < 2) {
    echo "現有客戶不足 2 位，建立測試客戶...\n";
    $names = [['測試', '客戶甲'], ['測試', '客戶乙']];
    for ($i = count($customers); $i < 2; $i++) {
        $wpdb->insert("{$wpdb->prefix}fct_customers", [
            'first_name' => $names[$i][0],
            'last_name'  => $names[$i][1],
            'email'      => "test_co_buyer_{$i}@buygo.test",
            'status'     => 'active',
            'notes'      => '',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ]);
        $newId = $wpdb->insert_id;
        if ($newId) {
            $customers[] = (object)[
                'id'         => $newId,
                'first_name' => $names[$i][0],
                'last_name'  => $names[$i][1],
                'email'      => "test_co_buyer_{$i}@buygo.test",
            ];
            echo "  建立客戶 #{$newId}\n";
        } else {
            echo "  [ERROR] 建立客戶失敗: {$wpdb->last_error}\n";
        }
    }
}

echo "使用客戶：\n";
foreach ($customers as $c) {
    $name = trim("{$c->first_name} {$c->last_name}") ?: $c->email;
    echo "  #{$c->id} {$name}\n";
}

// ================================
// 清除舊的子訂單測試資料
// ================================
echo "\n--- 清除舊測試資料 ---\n";
$oldParentIds = $wpdb->get_col(
    "SELECT id FROM {$wpdb->prefix}fct_orders WHERE note LIKE '%【子訂單測試】%' AND (parent_id IS NULL OR parent_id = 0)"
);
if (!empty($oldParentIds)) {
    foreach ($oldParentIds as $pid) {
        // 先取得子訂單 id
        $childIds = $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}fct_orders WHERE parent_id = %d", $pid
        ));
        foreach ($childIds as $cid) {
            $wpdb->delete("{$wpdb->prefix}fct_order_items", ['order_id' => $cid]);
            $wpdb->delete("{$wpdb->prefix}fct_orders", ['id' => $cid]);
        }
        $wpdb->delete("{$wpdb->prefix}fct_order_items", ['order_id' => $pid]);
        $wpdb->delete("{$wpdb->prefix}fct_orders", ['id' => $pid]);
    }
    echo "  已清除 " . count($oldParentIds) . " 筆舊父訂單及其子訂單\n";
} else {
    echo "  無舊測試資料\n";
}

// ================================
// 建立共用測試商品（若不存在）
// ================================
echo "\n--- 確保測試商品存在 ---\n";
$testPostId = $wpdb->get_var(
    "SELECT ID FROM {$wpdb->prefix}posts WHERE post_title = '【子訂單測試】測試商品' AND post_type = 'fct_product' LIMIT 1"
);
if (!$testPostId) {
    $testPostId = wp_insert_post([
        'post_title'  => '【子訂單測試】測試商品',
        'post_type'   => 'fct_product',
        'post_status' => 'publish',
    ]);
    $wpdb->insert("{$wpdb->prefix}fct_product_details", [
        'post_id'      => $testPostId,
        'product_type' => 'simple',
        'created_at'   => current_time('mysql'),
        'updated_at'   => current_time('mysql'),
    ]);
    echo "  建立測試商品 post_id={$testPostId}\n";
} else {
    echo "  使用現有測試商品 post_id={$testPostId}\n";
}

// 確保 variation 存在
$testVarId = $wpdb->get_var($wpdb->prepare(
    "SELECT id FROM {$wpdb->prefix}fct_product_variations WHERE post_id = %d LIMIT 1", $testPostId
));
if (!$testVarId) {
    $wpdb->insert("{$wpdb->prefix}fct_product_variations", [
        'post_id'         => $testPostId,
        'variation_title' => '【子訂單測試】測試商品',
        'item_price'      => 100000,
        'total_stock'     => 100,
        'available'       => 100,
        'stock_status'    => 'in_stock',
        'manage_stock'    => 1,
        'item_status'     => 'active',
        'created_at'      => current_time('mysql'),
        'updated_at'      => current_time('mysql'),
    ]);
    $testVarId = $wpdb->insert_id;
    echo "  建立測試 variation id={$testVarId}\n";
} else {
    echo "  使用現有 variation id={$testVarId}\n";
}

// ================================
// 父訂單 1（客戶甲）：含 2 筆子訂單，分配完整
// ================================
echo "\n--- 建立父訂單 1（客戶甲，2 筆子訂單，已分配）---\n";

$parent1Id = createParentOrder($wpdb, $customers[0]->id, 4, 100000, $testPostId, $testVarId, '已分配');
echo "  父訂單 1 id={$parent1Id}\n";

// 子訂單 1-1（賣家A）：分配 2 件
$child1aId = createChildOrder($wpdb, $customers[0]->id, $parent1Id, 2, 100000, $testPostId, $testVarId, 2);
echo "  子訂單 1-1 id={$child1aId}（賣家A，分配 2 件）\n";

// 子訂單 1-2（賣家B）：分配 2 件
$child1bId = createChildOrder($wpdb, $customers[0]->id, $parent1Id, 2, 100000, $testPostId, $testVarId, 2);
echo "  子訂單 1-2 id={$child1bId}（賣家B，分配 2 件）\n";

// ================================
// 父訂單 2（客戶乙）：含 2 筆子訂單，部分分配
// ================================
echo "\n--- 建立父訂單 2（客戶乙，2 筆子訂單，部分分配）---\n";

$parent2Id = createParentOrder($wpdb, $customers[1]->id, 3, 100000, $testPostId, $testVarId, '部分分配');
echo "  父訂單 2 id={$parent2Id}\n";

// 子訂單 2-1（賣家C）：分配 2 件
$child2aId = createChildOrder($wpdb, $customers[1]->id, $parent2Id, 2, 100000, $testPostId, $testVarId, 2);
echo "  子訂單 2-1 id={$child2aId}（賣家C，分配 2 件）\n";

// 子訂單 2-2（賣家D）：未分配 1 件
$child2bId = createChildOrder($wpdb, $customers[1]->id, $parent2Id, 1, 100000, $testPostId, $testVarId, 0);
echo "  子訂單 2-2 id={$child2bId}（賣家D，未分配）\n";

echo "\n\n========================================\n";
echo "測試資料建立完成！\n";
echo "========================================\n\n";
echo "驗證方式：\n";
echo "  1. 登入客戶甲帳號 → 造訪會員中心 → 應看到「訂單分配狀態」區塊\n";
echo "  2. 父訂單 1（id={$parent1Id}）：已分配 4 件，2 筆子單\n";
echo "  3. 登入客戶乙帳號 → 父訂單 2（id={$parent2Id}）：已分配 2 件（共 3 件），2 筆子單\n";
echo "\nAPI 測試：\n";
echo "  GET /wp-json/buygo-plus-one/v1/allocation-summary\n";

echo '</pre></body></html>';


/**
 * 建立父訂單（含 line_items）
 *
 * @param wpdb   $wpdb         資料庫
 * @param int    $customer_id  客戶 ID
 * @param int    $quantity     訂購數量
 * @param int    $unit_price   單價（分）
 * @param int    $post_id      商品 post_id
 * @param int    $variation_id variation id
 * @param string $note         備注（用於清除識別）
 * @return int 訂單 ID
 */
function createParentOrder($wpdb, $customer_id, $quantity, $unit_price, $post_id, $variation_id, $note = '') {
    $total = $quantity * $unit_price;
    $now   = current_time('mysql');

    $result = $wpdb->insert("{$wpdb->prefix}fct_orders", [
        'customer_id'          => $customer_id,
        'status'               => 'processing',
        'type'                 => 'payment',
        'mode'                 => 'live',
        'payment_status'       => 'paid',
        'payment_method'       => 'manual',
        'payment_method_title' => '手動',
        'shipping_status'      => 'unshipped',
        'currency'             => 'TWD',
        'subtotal'             => $total,
        'total_amount'         => $total,
        'total_paid'           => $total,
        'parent_id'            => null,
        'note'                 => "【子訂單測試】{$note}",
        'ip_address'           => '127.0.0.1',
        'uuid'                 => wp_generate_uuid4(),
        'created_at'           => $now,
        'updated_at'           => $now,
    ]);

    if (!$result) {
        echo "  [ERROR] 父訂單建立失敗: {$wpdb->last_error}\n";
        return 0;
    }

    $order_id = $wpdb->insert_id;

    // 建立父訂單的 line_item（代表原始訂購總量，_allocated_qty = 0）
    $result2 = $wpdb->insert("{$wpdb->prefix}fct_order_items", [
        'order_id'  => $order_id,
        'post_id'   => $post_id,
        'post_title' => '【子訂單測試】測試商品',
        'title'     => '【子訂單測試】測試商品',
        'object_id' => $variation_id,
        'quantity'  => $quantity,
        'unit_price' => $unit_price,
        'subtotal'  => $total,
        'line_total' => $total,
        'line_meta' => json_encode([
            '_allocated_qty' => 0,  // 父訂單本身不累計分配量，由子訂單的 line_meta 記錄
            '_shipped_qty'   => 0,
        ]),
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    if (!$result2) {
        echo "  [ERROR] 父訂單 line_item 建立失敗: {$wpdb->last_error}\n";
    }

    return $order_id;
}

/**
 * 建立子訂單（parent_id 指向父訂單）
 *
 * @param wpdb $wpdb          資料庫
 * @param int  $customer_id   客戶 ID
 * @param int  $parent_id     父訂單 ID
 * @param int  $quantity      數量
 * @param int  $unit_price    單價（分）
 * @param int  $post_id       商品 post_id
 * @param int  $variation_id  variation id
 * @param int  $allocated_qty 已分配數量（寫入 line_meta._allocated_qty）
 * @return int 子訂單 ID
 */
function createChildOrder($wpdb, $customer_id, $parent_id, $quantity, $unit_price, $post_id, $variation_id, $allocated_qty = 0) {
    $total = $quantity * $unit_price;
    $now   = current_time('mysql');

    $result = $wpdb->insert("{$wpdb->prefix}fct_orders", [
        'customer_id'          => $customer_id,
        'status'               => 'processing',
        'type'                 => 'payment',
        'mode'                 => 'live',
        'payment_status'       => 'paid',
        'payment_method'       => 'manual',
        'payment_method_title' => '手動',
        'shipping_status'      => 'unshipped',
        'currency'             => 'TWD',
        'subtotal'             => $total,
        'total_amount'         => $total,
        'total_paid'           => $total,
        'parent_id'            => $parent_id,
        'note'                 => '【子訂單測試】子訂單',
        'ip_address'           => '127.0.0.1',
        'uuid'                 => wp_generate_uuid4(),
        'created_at'           => $now,
        'updated_at'           => $now,
    ]);

    if (!$result) {
        echo "  [ERROR] 子訂單建立失敗: {$wpdb->last_error}\n";
        return 0;
    }

    $order_id = $wpdb->insert_id;

    // 建立子訂單的 line_item，帶入 _allocated_qty
    $result2 = $wpdb->insert("{$wpdb->prefix}fct_order_items", [
        'order_id'   => $order_id,
        'post_id'    => $post_id,
        'post_title' => '【子訂單測試】測試商品',
        'title'      => '【子訂單測試】測試商品',
        'object_id'  => $variation_id,
        'quantity'   => $quantity,
        'unit_price' => $unit_price,
        'subtotal'   => $total,
        'line_total' => $total,
        'line_meta'  => json_encode([
            '_allocated_qty' => $allocated_qty,
            '_shipped_qty'   => 0,
        ]),
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    if (!$result2) {
        echo "  [ERROR] 子訂單 line_item 建立失敗: {$wpdb->last_error}\n";
    }

    return $order_id;
}

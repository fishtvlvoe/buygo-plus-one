<?php
/**
 * 驗證子訂單地址複製功能
 *
 * 直接查詢資料庫，不依賴 WordPress
 */

// 資料庫連線資訊
$host = 'localhost';
$socket = '/Users/fishtv/Library/Application Support/Local/run/oFa4PFqBu/mysql/mysqld.sock';
$dbname = 'local';
$username = 'root';
$password = 'root';

try {
    $pdo = new PDO("mysql:host={$host};unix_socket={$socket};dbname={$dbname};charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "=== 子訂單地址複製驗證 ===\n\n";

    // 查詢最近的子訂單
    $stmt = $pdo->prepare("
        SELECT o.id, o.parent_id, o.invoice_no, o.created_at
        FROM wp_fct_orders o
        WHERE o.parent_id IS NOT NULL
        ORDER BY o.created_at DESC
        LIMIT 5
    ");
    $stmt->execute();
    $child_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "最近建立的 5 個子訂單：\n";
    foreach ($child_orders as $order) {
        echo "\n子訂單 #{$order['id']} (父訂單: #{$order['parent_id']})：\n";
        echo "  - 訂單編號：{$order['invoice_no']}\n";
        echo "  - 建立時間：{$order['created_at']}\n";

        // 查詢子訂單的地址
        $stmt = $pdo->prepare("
            SELECT id, type, name, address_1
            FROM wp_fct_order_addresses
            WHERE order_id = ?
        ");
        $stmt->execute([$order['id']]);
        $child_addresses = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($child_addresses)) {
            echo "  - 地址資料：❌ 沒有地址\n";
        } else {
            echo "  - 地址資料：✅ 有 " . count($child_addresses) . " 筆\n";
            foreach ($child_addresses as $addr) {
                echo "    * {$addr['type']}: {$addr['name']} - {$addr['address_1']}\n";
            }
        }

        // 查詢父訂單的地址（用於比對）
        $stmt = $pdo->prepare("
            SELECT id, type, name, address_1
            FROM wp_fct_order_addresses
            WHERE order_id = ?
        ");
        $stmt->execute([$order['parent_id']]);
        $parent_addresses = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo "  - 父訂單地址：" . (empty($parent_addresses) ? "❌ 沒有" : "✅ 有 " . count($parent_addresses) . " 筆") . "\n";
    }

} catch (PDOException $e) {
    echo "資料庫錯誤：" . $e->getMessage() . "\n";
}

<?php
/**
 * 檢查訂單 280 的完整資料
 */

$host = 'localhost';
$socket = '/Users/fishtv/Library/Application Support/Local/run/oFa4PFqBu/mysql/mysqld.sock';
$dbname = 'local';
$username = 'root';
$password = 'root';

try {
    $pdo = new PDO("mysql:host={$host};unix_socket={$socket};dbname={$dbname};charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "=== 訂單 #280 完整資料檢查 ===\n\n";

    // 1. 訂單基本資訊
    $stmt = $pdo->prepare("SELECT * FROM wp_fct_orders WHERE id = 280");
    $stmt->execute();
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    echo "【訂單基本資訊】\n";
    echo "ID: {$order['id']}\n";
    echo "Invoice No: {$order['invoice_no']}\n";
    echo "Parent ID: {$order['parent_id']}\n";
    echo "Status: {$order['status']}\n";
    echo "Payment Status: {$order['payment_status']}\n";
    echo "Total Amount: {$order['total_amount']}\n";
    echo "\n";

    // 2. 訂單項目
    $stmt = $pdo->prepare("
        SELECT * FROM wp_fct_order_items
        WHERE order_id = 280
    ");
    $stmt->execute();
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "【訂單項目】\n";
    if (empty($items)) {
        echo "❌ 沒有訂單項目\n";
    } else {
        foreach ($items as $item) {
            echo "- Item ID: {$item['id']}\n";
            echo "  Post ID: {$item['post_id']}\n";
            echo "  Object ID: {$item['object_id']}\n";
            echo "  Title: {$item['title']}\n";
            echo "  Post Title: {$item['post_title']}\n";
            echo "  Quantity: {$item['quantity']}\n";
            echo "  Unit Price: {$item['unit_price']}\n";
            echo "  Line Total: {$item['line_total']}\n";
            echo "\n";
        }
    }

    // 3. 父訂單的項目（比對）
    $stmt = $pdo->prepare("
        SELECT * FROM wp_fct_order_items
        WHERE order_id = 274
    ");
    $stmt->execute();
    $parent_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "【父訂單 #274 的項目】\n";
    foreach ($parent_items as $item) {
        echo "- Post ID: {$item['post_id']}, Title: {$item['title']}, Quantity: {$item['quantity']}\n";
    }
    echo "\n";

    // 4. 地址資料
    $stmt = $pdo->prepare("
        SELECT type, name, address_1
        FROM wp_fct_order_addresses
        WHERE order_id = 280
    ");
    $stmt->execute();
    $addresses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "【地址資料】\n";
    if (empty($addresses)) {
        echo "❌ 沒有地址\n";
    } else {
        foreach ($addresses as $addr) {
            echo "- {$addr['type']}: {$addr['name']} - {$addr['address_1']}\n";
        }
    }

} catch (PDOException $e) {
    echo "錯誤：" . $e->getMessage() . "\n";
}

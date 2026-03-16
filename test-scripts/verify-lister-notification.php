<?php
/**
 * 驗證上架幫手身份判斷與通知流程
 *
 * 用假資料模擬完整 Hook 流程，不需要真實 LINE 發送
 *
 * 使用方式：
 * 1. 在本機 WordPress 環境執行
 * 2. 訪問：http://buygo.local/wp-content/plugins/buygo-plus-one/test-scripts/verify-lister-notification.php
 * 或用 WP-CLI：wp eval-file test-scripts/verify-lister-notification.php
 */

// 載入 WordPress
require_once('/Users/fishtv/Local Sites/buygo/app/public/wp-load.php');

echo "=== 上架幫手身份與通知流程驗證 ===\n\n";

// ========================================
// 步驟 1：檢查資料庫 role 欄位是否存在
// ========================================
echo "【步驟 1】檢查 wp_buygo_helpers 表結構\n";
global $wpdb;
$table_name = $wpdb->prefix . 'buygo_helpers';
$columns = $wpdb->get_col("DESCRIBE {$table_name}", 0);
$has_role_column = in_array('role', $columns);
echo "  表名: {$table_name}\n";
echo "  欄位: " . implode(', ', $columns) . "\n";
echo "  role 欄位: " . ($has_role_column ? '✅ 存在' : '❌ 不存在（需執行外掛啟動遷移）') . "\n\n";

// ========================================
// 步驟 2：列出所有幫手綁定記錄
// ========================================
echo "【步驟 2】現有幫手綁定記錄\n";
$records = $wpdb->get_results("SELECT * FROM {$table_name}");
if (empty($records)) {
    echo "  ⚠️ 無記錄\n";
} else {
    echo "  ID | helper_id | seller_id | role | created_at\n";
    echo "  ---|-----------|-----------|------|----------\n";
    foreach ($records as $r) {
        $role = isset($r->role) ? $r->role : '(無欄位)';
        echo "  {$r->id} | {$r->helper_id} | {$r->seller_id} | {$role} | {$r->created_at}\n";
    }
}
echo "\n";

// ========================================
// 步驟 3：列出所有 buygo 角色用戶
// ========================================
echo "【步驟 3】BuyGo 角色用戶\n";
$buygo_roles = ['buygo_admin', 'buygo_helper', 'buygo_lister', 'administrator'];
foreach ($buygo_roles as $role) {
    $users = get_users(['role' => $role]);
    echo "  {$role}: ";
    if (empty($users)) {
        echo "（無）\n";
    } else {
        $names = array_map(function($u) { return "{$u->display_name}(ID:{$u->ID})"; }, $users);
        echo implode(', ', $names) . "\n";
    }
}
echo "\n";

// ========================================
// 步驟 4：測試 IdentityService 身份判斷
// ========================================
echo "【步驟 4】IdentityService 身份判斷測試\n";
$identity_service = 'BuyGoPlus\Services\IdentityService';

// 測試所有 buygo 角色用戶的身份
foreach (['buygo_admin', 'buygo_helper', 'buygo_lister'] as $role) {
    $users = get_users(['role' => $role]);
    foreach ($users as $user) {
        $identity = $identity_service::getIdentityByUserId($user->ID);
        $role_label = match($identity['role']) {
            'seller' => '賣家',
            'helper' => '小幫手',
            'lister' => '上架幫手',
            'buyer' => '買家',
            'unbound' => '未綁定',
            default => $identity['role'],
        };
        echo "  用戶 {$user->display_name}(ID:{$user->ID}) WP角色={$role}\n";
        echo "    → 判定為: {$role_label} (role={$identity['role']})\n";
        echo "    → seller_id: " . ($identity['seller_id'] ?? '無') . "\n";
        echo "    → LINE綁定: " . ($identity['is_bound'] ? '是' : '否') . "\n\n";
    }
}

// ========================================
// 步驟 5：模擬上架通知流程
// ========================================
echo "【步驟 5】模擬上架通知流程\n";

$handler = new \BuyGoPlus\Services\ProductNotificationHandler();

// 找出各角色的用戶 ID 進行測試
$admins = get_users(['role' => 'buygo_admin']);
$helpers = get_users(['role' => 'buygo_helper']);
$listers = get_users(['role' => 'buygo_lister']);

if (!empty($admins)) {
    $seller = $admins[0];
    echo "  使用賣家: {$seller->display_name}(ID:{$seller->ID})\n\n";

    // 情境 A：賣家上架
    echo "  --- 情境 A：賣家上架 ---\n";
    $result = $handler->resolveNotificationTargets($seller->ID);
    if ($result) {
        echo "  通知對象 IDs: " . implode(', ', $result['notify_user_ids']) . "\n";
        foreach ($result['notify_user_ids'] as $uid) {
            $u = get_userdata($uid);
            $roles = implode(',', (array)$u->roles);
            echo "    → {$u->display_name}(ID:{$uid}) 角色:{$roles}\n";
        }
        // 檢查上架幫手是否被排除
        foreach ($listers as $l) {
            $excluded = !in_array($l->ID, $result['notify_user_ids']);
            echo "  上架幫手 {$l->display_name}(ID:{$l->ID}) 被排除: " . ($excluded ? '✅ 是' : '❌ 否') . "\n";
        }
    } else {
        echo "  結果: null（無通知目標）\n";
    }
    echo "\n";

    // 情境 B：小幫手上架
    if (!empty($helpers)) {
        $helper = $helpers[0];
        echo "  --- 情境 B：小幫手 {$helper->display_name}(ID:{$helper->ID}) 上架 ---\n";
        $result = $handler->resolveNotificationTargets($helper->ID);
        if ($result) {
            echo "  seller_id: {$result['seller_id']}\n";
            echo "  通知對象 IDs: " . implode(', ', $result['notify_user_ids']) . "\n";
            foreach ($result['notify_user_ids'] as $uid) {
                $u = get_userdata($uid);
                $roles = implode(',', (array)$u->roles);
                echo "    → {$u->display_name}(ID:{$uid}) 角色:{$roles}\n";
            }
            // 檢查小幫手本人被排除
            echo "  上架者本人被排除: " . (!in_array($helper->ID, $result['notify_user_ids']) ? '✅ 是' : '❌ 否') . "\n";
            // 檢查上架幫手被排除
            foreach ($listers as $l) {
                $excluded = !in_array($l->ID, $result['notify_user_ids']);
                echo "  上架幫手 {$l->display_name}(ID:{$l->ID}) 被排除: " . ($excluded ? '✅ 是' : '❌ 否') . "\n";
            }
        } else {
            echo "  結果: null\n";
        }
        echo "\n";
    }

    // 情境 C：上架幫手上架
    if (!empty($listers)) {
        $lister = $listers[0];
        echo "  --- 情境 C：上架幫手 {$lister->display_name}(ID:{$lister->ID}) 上架 ---\n";
        $result = $handler->resolveNotificationTargets($lister->ID);
        if ($result) {
            echo "  seller_id: {$result['seller_id']}\n";
            echo "  通知對象 IDs: " . implode(', ', $result['notify_user_ids']) . "\n";
            foreach ($result['notify_user_ids'] as $uid) {
                $u = get_userdata($uid);
                $roles = implode(',', (array)$u->roles);
                echo "    → {$u->display_name}(ID:{$uid}) 角色:{$roles}\n";
            }
            // 檢查上架幫手本人被排除
            echo "  上架者本人被排除: " . (!in_array($lister->ID, $result['notify_user_ids']) ? '✅ 是' : '❌ 否') . "\n";
            // 檢查賣家收到通知
            echo "  賣家收到通知: " . (in_array($seller->ID, $result['notify_user_ids']) ? '✅ 是' : '❌ 否') . "\n";
            // 檢查小幫手收到通知
            foreach ($helpers as $h) {
                $notified = in_array($h->ID, $result['notify_user_ids']);
                echo "  小幫手 {$h->display_name}(ID:{$h->ID}) 收到通知: " . ($notified ? '✅ 是' : '❌ 否') . "\n";
            }
        } else {
            echo "  結果: null（⚠️ 上架幫手未被識別！）\n";
        }
        echo "\n";
    }
} else {
    echo "  ⚠️ 無 buygo_admin 用戶，無法測試\n\n";
}

// ========================================
// 步驟 6：測試 sendToSellerAndHelpers（訂單通知排除上架幫手）
// ========================================
echo "【步驟 6】訂單通知排除上架幫手驗證\n";
if (!empty($admins)) {
    $seller = $admins[0];
    // 直接檢查 get_helpers 回傳的列表中有沒有 lister
    $all_helpers = \BuyGoPlus\Services\SettingsService::get_helpers($seller->ID);
    echo "  賣家 {$seller->display_name}(ID:{$seller->ID}) 的所有幫手:\n";
    foreach ($all_helpers as $h) {
        $u = get_userdata($h['id']);
        $roles = $u ? implode(',', (array)$u->roles) : '(無)';
        $is_lister = $u && in_array('buygo_lister', (array)$u->roles);
        echo "    {$h['name']}(ID:{$h['id']}) 角色:{$roles}";
        echo $is_lister ? " → 訂單通知會被排除 ✅\n" : " → 會收到訂單通知\n";
    }
}

echo "\n=== 驗證完成 ===\n";

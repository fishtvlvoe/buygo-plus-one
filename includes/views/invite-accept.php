<?php
/**
 * 邀請接受頁 — LINE Login 完成後的回調頁面
 *
 * 流程：
 * 1. 檢查登入狀態（未登入 → 重導回 LINE Login）
 * 2. 從 URL 取得 token
 * 3. 呼叫 InviteTokenService::accept() 完成綁定
 * 4. 顯示成功/失敗結果
 *
 * @package BuyGoPlus
 * @since 0.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$token = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';
$result = null;

// 未登入 → 重導回 LINE Login（防禦性處理）
if (!is_user_logged_in()) {
    if (!empty($token)) {
        $accept_url = home_url('/buygo-invite/accept?token=' . urlencode($token));
        $line_login_url = home_url('/line-hub/auth/?redirect=' . urlencode($accept_url));
        wp_redirect($line_login_url);
        exit;
    }
    // 沒有 token 也未登入，顯示錯誤
    $result = ['success' => false, 'message' => '請先透過邀請連結登入'];
}

// 已登入，執行接受邀請
if (is_user_logged_in() && !empty($token)) {
    $result = \BuyGoPlus\Services\InviteTokenService::accept($token, get_current_user_id());
} elseif (is_user_logged_in() && empty($token)) {
    $result = ['success' => false, 'message' => '缺少邀請 Token'];
}

$success = !empty($result['success']);
$message = $result['message'] ?? '';
$site_name = get_bloginfo('name');
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html($site_name); ?> - 邀請結果</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { background: linear-gradient(135deg, #f0fdf4 0%, #ecfdf5 50%, #f0f9ff 100%); }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <div class="bg-white rounded-2xl shadow-lg p-8 text-center">
            <?php if ($success): ?>
                <!-- 成功 -->
                <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                </div>
                <h1 class="text-xl font-bold text-gray-800 mb-2">加入成功！</h1>
                <p class="text-gray-500 mb-6"><?php echo esc_html($message); ?></p>

                <div class="bg-green-50 rounded-xl p-4 mb-6 text-sm text-left space-y-2">
                    <p class="text-green-700 font-medium">接下來你可以：</p>
                    <ul class="text-green-600 space-y-1 ml-4 list-disc">
                        <li>在 LINE 中傳送商品圖片給官方帳號</li>
                        <li>系統會自動引導你完成商品上架</li>
                    </ul>
                </div>
            <?php else: ?>
                <!-- 失敗 -->
                <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </div>
                <h1 class="text-xl font-bold text-gray-800 mb-2">加入失敗</h1>
                <p class="text-gray-500 mb-6"><?php echo esc_html($message); ?></p>
            <?php endif; ?>
        </div>

        <p class="text-center text-xs text-gray-400 mt-4">
            <?php echo esc_html($site_name); ?> &copy; <?php echo date('Y'); ?>
        </p>
    </div>
</body>
</html>

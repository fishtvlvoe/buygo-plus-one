<?php
/**
 * 邀請落地頁 — 公開頁面，不需登入
 *
 * 流程：驗證 token → 顯示邀請資訊 → 「用 LINE 登入並接受邀請」按鈕
 * 按鈕跳轉到 /line-hub/auth/?redirect=/buygo-invite/accept?token=xxx
 *
 * @package BuyGoPlus
 * @since 0.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$token = get_query_var('invite_token', '');
$invite_data = null;
$error_message = '';

if (!empty($token)) {
    $invite_data = \BuyGoPlus\Services\InviteTokenService::verify($token);
}

if (!$invite_data) {
    $error_message = '邀請連結無效或不存在';
} elseif (!empty($invite_data['error'])) {
    $error_messages = [
        'expired'      => '此邀請連結已過期，請聯繫賣家重新產生',
        'already_used' => '此邀請連結已被使用',
        'revoked'      => '此邀請連結已被賣家撤銷',
    ];
    $error_message = $error_messages[$invite_data['error']] ?? '邀請連結無效';
}

// LINE Login redirect URL
$accept_url = home_url('/buygo-invite/accept?token=' . urlencode($token));
$line_login_url = home_url('/line-hub/auth/?redirect=' . urlencode($accept_url));

$site_name = get_bloginfo('name');
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html($site_name); ?> - 邀請加入</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { background: linear-gradient(135deg, #f0fdf4 0%, #ecfdf5 50%, #f0f9ff 100%); }
        .line-btn { background-color: #06C755; }
        .line-btn:hover { background-color: #05b04d; }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <?php if ($error_message): ?>
            <!-- 錯誤狀態 -->
            <div class="bg-white rounded-2xl shadow-lg p-8 text-center">
                <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </div>
                <h1 class="text-xl font-bold text-gray-800 mb-2">無法加入</h1>
                <p class="text-gray-500 mb-6"><?php echo esc_html($error_message); ?></p>
                <a href="<?php echo esc_url(home_url('/')); ?>"
                   class="inline-block px-6 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                    返回首頁
                </a>
            </div>
        <?php else: ?>
            <!-- 有效邀請 -->
            <div class="bg-white rounded-2xl shadow-lg p-8 text-center">
                <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                    </svg>
                </div>

                <h1 class="text-xl font-bold text-gray-800 mb-1">你被邀請加入</h1>
                <p class="text-gray-500 mb-6">
                    <strong class="text-gray-700"><?php echo esc_html($invite_data['seller_name']); ?></strong>
                    邀請你成為<strong class="text-green-600"><?php echo esc_html($invite_data['role_label']); ?></strong>
                </p>

                <div class="bg-gray-50 rounded-xl p-4 mb-6 text-left text-sm space-y-2">
                    <div class="flex justify-between">
                        <span class="text-gray-500">角色</span>
                        <span class="font-medium text-gray-700"><?php echo esc_html($invite_data['role_label']); ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">有效至</span>
                        <span class="font-medium text-gray-700">
                            <?php
                            $expires = new \DateTime($invite_data['expires_at'], new \DateTimeZone('UTC'));
                            $expires->setTimezone(new \DateTimeZone('Asia/Taipei'));
                            echo esc_html($expires->format('m/d H:i'));
                            ?>
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">可做的事</span>
                        <span class="font-medium text-gray-700">透過 LINE 傳圖上架商品</span>
                    </div>
                </div>

                <a href="<?php echo esc_url($line_login_url); ?>"
                   class="line-btn block w-full py-3 px-6 text-white font-bold rounded-xl text-center transition shadow-md hover:shadow-lg">
                    <span class="flex items-center justify-center gap-2">
                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M19.365 9.863c.349 0 .63.285.63.631 0 .345-.281.63-.63.63H17.61v1.125h1.755c.349 0 .63.283.63.63 0 .344-.281.629-.63.629h-2.386c-.345 0-.627-.285-.627-.629V8.108c0-.345.282-.63.63-.63h2.386c.346 0 .627.285.627.63 0 .349-.281.63-.63.63H17.61v1.125h1.755zm-3.855 3.016c0 .27-.174.51-.432.596-.064.021-.133.031-.199.031-.211 0-.391-.09-.51-.25l-2.443-3.317v2.94c0 .344-.279.629-.631.629-.346 0-.626-.285-.626-.629V8.108c0-.27.173-.51.43-.595.06-.023.136-.033.194-.033.195 0 .375.104.495.254l2.462 3.33V8.108c0-.345.282-.63.63-.63.345 0 .63.285.63.63v4.771zm-5.741 0c0 .344-.282.629-.631.629-.345 0-.627-.285-.627-.629V8.108c0-.345.282-.63.63-.63.346 0 .628.285.628.63v4.771zm-2.466.629H4.917c-.345 0-.63-.285-.63-.629V8.108c0-.345.285-.63.63-.63.348 0 .63.285.63.63v4.141h1.756c.348 0 .629.283.629.63 0 .344-.282.629-.629.629M24 10.314C24 4.943 18.615.572 12 .572S0 4.943 0 10.314c0 4.811 4.27 8.842 10.035 9.608.391.082.923.258 1.058.59.12.301.079.766.038 1.08l-.164 1.02c-.045.301-.24 1.186 1.049.645 1.291-.539 6.916-4.078 9.436-6.975C23.176 14.393 24 12.458 24 10.314"/>
                        </svg>
                        用 LINE 登入並接受邀請
                    </span>
                </a>

                <p class="text-xs text-gray-400 mt-4">
                    登入後將自動完成加入，不需要額外操作
                </p>
            </div>
        <?php endif; ?>

        <p class="text-center text-xs text-gray-400 mt-4">
            <?php echo esc_html($site_name); ?> &copy; <?php echo date('Y'); ?>
        </p>
    </div>
</body>
</html>

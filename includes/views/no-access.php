<?php
/**
 * 權限不足頁面
 *
 * 當已登入用戶沒有賣場後台權限時顯示。
 * 提供「一鍵成為測試賣家」按鈕，調用 REST API 直接賦予賣家權限。
 */

if (!defined('ABSPATH')) {
    exit;
}

$current_user = wp_get_current_user();
$display_name = $current_user->display_name ?: $current_user->user_login;
$user_email = $current_user->user_email;
$community_url = home_url('/portal/');
$home_url = home_url('/');
$wp_nonce = wp_create_nonce('wp_rest');
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BuyGo+1 - 成為賣家</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600;700&family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Open Sans', sans-serif; }
        h1, h2, h3 { font-family: 'Poppins', sans-serif; }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-50 min-h-screen flex items-center justify-center p-4">
    <div class="max-w-md w-full">
        <div class="bg-white rounded-2xl shadow-lg p-8">
            <!-- Logo -->
            <div class="text-center mb-6">
                <div class="inline-flex items-center justify-center w-16 h-16 bg-indigo-100 rounded-full mb-4">
                    <svg class="w-8 h-8 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                    </svg>
                </div>
                <h1 class="text-2xl font-bold text-gray-900">歡迎來到 BuyGo+1</h1>
                <p class="text-gray-500 mt-1"><?php echo esc_html($display_name); ?>，您好！</p>
            </div>

            <!-- 身份說明 -->
            <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 mb-6">
                <div class="flex items-start gap-3">
                    <svg class="w-5 h-5 text-amber-500 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                    </svg>
                    <div>
                        <p class="font-semibold text-amber-800">您目前是買家身份</p>
                        <p class="text-amber-700 text-sm mt-1">賣場後台僅供賣家使用，點擊下方按鈕即可立即開通。</p>
                    </div>
                </div>
            </div>

            <!-- 功能說明 -->
            <div class="bg-indigo-50 rounded-xl p-4 mb-6 space-y-2">
                <div class="flex items-center gap-2">
                    <svg class="h-5 w-5 text-green-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                    </svg>
                    <span class="text-sm text-gray-700">立即開通，無需審核</span>
                </div>
                <div class="flex items-center gap-2">
                    <svg class="h-5 w-5 text-green-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                    </svg>
                    <span class="text-sm text-gray-700">可上架最多 10 件商品</span>
                </div>
                <div class="flex items-center gap-2">
                    <svg class="h-5 w-5 text-green-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                    </svg>
                    <span class="text-sm text-gray-700">完整使用賣場後台功能</span>
                </div>
            </div>

            <!-- 主按鈕 -->
            <div class="mb-4">
                <button id="btn-become-seller"
                    onclick="becomeTestSeller()"
                    class="flex items-center justify-center gap-2 w-full py-3.5 px-4 rounded-xl text-white font-semibold text-base transition-all hover:opacity-90 active:scale-[0.98] bg-indigo-600 hover:bg-indigo-700">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                    一鍵成為測試賣家
                </button>
            </div>

            <!-- 狀態訊息 -->
            <div id="msg-area" class="mb-4 hidden">
                <div id="msg-content" class="p-3 rounded-lg text-sm"></div>
            </div>

            <!-- 分隔線 -->
            <div class="relative my-6">
                <div class="absolute inset-0 flex items-center"><div class="w-full border-t border-gray-200"></div></div>
                <div class="relative flex justify-center text-sm"><span class="bg-white px-3 text-gray-400">或</span></div>
            </div>

            <!-- 次要按鈕 -->
            <div class="space-y-2">
                <a href="<?php echo esc_url($community_url); ?>" class="block w-full text-center bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium py-2.5 px-4 rounded-lg transition">
                    前往社群了解更多
                </a>
                <a href="<?php echo esc_url($home_url); ?>" class="block w-full text-center text-gray-500 hover:text-gray-700 text-sm py-2 transition">
                    回到首頁
                </a>
            </div>
        </div>

        <!-- 底部提示 -->
        <p class="text-center text-gray-400 text-xs mt-4">
            測試賣家可上架最多 10 件商品
        </p>
    </div>

    <script>
    async function becomeTestSeller() {
        var btn = document.getElementById('btn-become-seller');
        var msgArea = document.getElementById('msg-area');
        var msgContent = document.getElementById('msg-content');

        btn.disabled = true;
        btn.innerHTML = '<svg class="animate-spin h-5 w-5 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> 申請中...';
        btn.classList.add('opacity-70');

        try {
            var response = await fetch('/wp-json/buygo-plus-one/v1/seller-application', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': '<?php echo esc_js($wp_nonce); ?>'
                },
                credentials: 'include',
                body: JSON.stringify({
                    name: '<?php echo esc_js($display_name); ?>',
                    email: '<?php echo esc_js($user_email); ?>',
                    reason: '從賣場後台一鍵申請'
                })
            });

            var result = await response.json();

            msgArea.classList.remove('hidden');

            if (result.success) {
                msgContent.className = 'p-3 rounded-lg text-sm bg-green-50 text-green-700 border border-green-200';
                msgContent.textContent = '開通成功！正在進入賣場後台...';
                btn.innerHTML = '開通成功！';
                btn.classList.remove('bg-indigo-600', 'hover:bg-indigo-700');
                btn.classList.add('bg-green-500');
                setTimeout(function() {
                    window.location.href = '/buygo-portal/dashboard/';
                }, 1500);
            } else {
                msgContent.className = 'p-3 rounded-lg text-sm bg-red-50 text-red-700 border border-red-200';
                msgContent.textContent = result.message || '申請失敗，請稍後再試';
                btn.disabled = false;
                btn.innerHTML = '一鍵成為測試賣家';
                btn.classList.remove('opacity-70');
            }
        } catch (err) {
            msgArea.classList.remove('hidden');
            msgContent.className = 'p-3 rounded-lg text-sm bg-red-50 text-red-700 border border-red-200';
            msgContent.textContent = '網路連線失敗，請檢查網路後再試';
            btn.disabled = false;
            btn.innerHTML = '一鍵成為測試賣家';
            btn.classList.remove('opacity-70');
        }
    }
    </script>
</body>
</html>

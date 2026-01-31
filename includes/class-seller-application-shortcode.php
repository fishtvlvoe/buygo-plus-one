<?php

namespace BuyGoPlus;

use BuyGoPlus\Services\SellerApplicationService;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Seller Application Shortcode - 賣家申請 Shortcode
 *
 * Phase 27: 提供前端賣家申請表單的 Shortcode
 *
 * 使用方式：[buygo_seller_application]
 *
 * @package BuyGoPlus
 * @version 1.0.0
 */
class SellerApplicationShortcode
{
    private static $instance = null;

    /**
     * 取得單例實例
     */
    public static function instance(): self
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 建構函數
     */
    private function __construct()
    {
        add_shortcode('buygo_seller_application', [$this, 'render']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
    }

    /**
     * 載入前端腳本和樣式
     */
    public function enqueue_scripts(): void
    {
        global $post;

        // 只在包含 shortcode 的頁面載入
        if (!is_a($post, 'WP_Post') || !has_shortcode($post->post_content, 'buygo_seller_application')) {
            return;
        }

        // 載入 Tailwind CSS（使用 CDN）
        wp_enqueue_script(
            'tailwindcss-cdn',
            'https://cdn.tailwindcss.com',
            [],
            null,
            false
        );

        // 載入 Vue 3
        wp_enqueue_script(
            'vue3',
            'https://unpkg.com/vue@3/dist/vue.global.prod.js',
            [],
            '3.4.0',
            true
        );
    }

    /**
     * 渲染 Shortcode
     */
    public function render($atts): string
    {
        // 檢查登入狀態
        if (!is_user_logged_in()) {
            return $this->render_login_required();
        }

        // 檢查申請狀態
        $service = new SellerApplicationService();
        $can_apply_result = $service->canApply();

        // 如果不能申請，顯示相應訊息
        if (!$can_apply_result['can_apply']) {
            return $this->render_cannot_apply($can_apply_result);
        }

        // 渲染申請表單
        return $this->render_application_form();
    }

    /**
     * 渲染需要登入的訊息
     */
    private function render_login_required(): string
    {
        $login_url = wp_login_url(get_permalink());

        ob_start();
        ?>
        <div class="max-w-lg mx-auto p-6 bg-white rounded-lg shadow-md">
            <div class="text-center">
                <svg class="mx-auto h-12 w-12 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <h3 class="mt-4 text-lg font-medium text-gray-900">請先登入</h3>
                <p class="mt-2 text-sm text-gray-500">
                    您需要登入才能申請成為賣家。
                </p>
                <div class="mt-6">
                    <a href="<?php echo esc_url($login_url); ?>" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        立即登入
                    </a>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * 渲染無法申請的訊息
     */
    private function render_cannot_apply(array $result): string
    {
        ob_start();
        ?>
        <div class="max-w-lg mx-auto p-6 bg-white rounded-lg shadow-md">
            <div class="text-center">
                <?php if ($result['is_seller']): ?>
                    <!-- 已是賣家 -->
                    <svg class="mx-auto h-12 w-12 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <h3 class="mt-4 text-lg font-medium text-gray-900">您已經是賣家</h3>
                    <p class="mt-2 text-sm text-gray-500">
                        您目前的身份是「<?php echo $result['seller_type'] === 'real' ? '正式賣家' : '測試賣家'; ?>」。
                        <?php if ($result['seller_type'] === 'test'): ?>
                            <br>如需升級為正式賣家，請聯繫管理員。
                        <?php endif; ?>
                    </p>
                <?php elseif ($result['has_pending']): ?>
                    <!-- 已有待審核申請 -->
                    <svg class="mx-auto h-12 w-12 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <h3 class="mt-4 text-lg font-medium text-gray-900">申請審核中</h3>
                    <p class="mt-2 text-sm text-gray-500">
                        您的賣家申請正在審核中，請耐心等候。
                        <br>審核結果將透過 Email 通知您。
                    </p>
                <?php else: ?>
                    <!-- 其他原因 -->
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                    </svg>
                    <h3 class="mt-4 text-lg font-medium text-gray-900">無法申請</h3>
                    <p class="mt-2 text-sm text-gray-500">
                        <?php echo esc_html($result['reason'] ?? '目前無法提交賣家申請。'); ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * 渲染申請表單
     */
    private function render_application_form(): string
    {
        $current_user = wp_get_current_user();
        $nonce = wp_create_nonce('wp_rest');

        ob_start();
        ?>
        <div id="seller-application-app" class="max-w-2xl mx-auto">
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <!-- 標題 -->
                <div class="bg-gradient-to-r from-indigo-600 to-purple-600 px-6 py-4">
                    <h2 class="text-xl font-bold text-white">賣家申請</h2>
                    <p class="text-indigo-100 text-sm mt-1">填寫以下資料成為 BuyGo 賣家</p>
                </div>

                <!-- 表單 -->
                <form @submit.prevent="submitApplication" class="p-6 space-y-6">
                    <!-- 商店名稱 -->
                    <div>
                        <label for="shop_name" class="block text-sm font-medium text-gray-700">
                            商店名稱 <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="text"
                            id="shop_name"
                            v-model="form.shop_name"
                            required
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                            :class="{ 'border-red-500': errors.shop_name }"
                            placeholder="輸入您的商店名稱"
                        >
                        <p v-if="errors.shop_name" class="mt-1 text-sm text-red-600">{{ errors.shop_name }}</p>
                    </div>

                    <!-- 商店描述 -->
                    <div>
                        <label for="shop_description" class="block text-sm font-medium text-gray-700">
                            商店描述
                        </label>
                        <textarea
                            id="shop_description"
                            v-model="form.shop_description"
                            rows="3"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                            placeholder="簡單介紹您的商店（選填）"
                        ></textarea>
                    </div>

                    <!-- 聯絡電話 -->
                    <div>
                        <label for="contact_phone" class="block text-sm font-medium text-gray-700">
                            聯絡電話
                        </label>
                        <input
                            type="tel"
                            id="contact_phone"
                            v-model="form.contact_phone"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                            placeholder="輸入聯絡電話（選填）"
                        >
                    </div>

                    <!-- 申請說明 -->
                    <div class="rounded-md bg-blue-50 p-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-blue-800">申請說明</h3>
                                <div class="mt-2 text-sm text-blue-700">
                                    <ul class="list-disc pl-5 space-y-1">
                                        <li>提交後將自動成為「測試賣家」</li>
                                        <li>測試賣家最多可上架 <?php echo SellerApplicationService::TEST_SELLER_PRODUCT_LIMIT; ?> 件商品</li>
                                        <li>如需升級為正式賣家（無限商品），請聯繫管理員</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 錯誤訊息 -->
                    <div v-if="errorMessage" class="rounded-md bg-red-50 p-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-red-700">{{ errorMessage }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- 成功訊息 -->
                    <div v-if="successMessage" class="rounded-md bg-green-50 p-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-green-700">{{ successMessage }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- 提交按鈕 -->
                    <div class="flex justify-end">
                        <button
                            type="submit"
                            :disabled="submitting"
                            class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <svg v-if="submitting" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            {{ submitting ? '提交中...' : '提交申請' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const { createApp, ref, reactive } = Vue;

            createApp({
                setup() {
                    const form = reactive({
                        shop_name: '',
                        shop_description: '',
                        contact_phone: ''
                    });

                    const errors = reactive({});
                    const submitting = ref(false);
                    const errorMessage = ref('');
                    const successMessage = ref('');

                    const validateForm = () => {
                        // 清除錯誤
                        Object.keys(errors).forEach(key => delete errors[key]);

                        if (!form.shop_name.trim()) {
                            errors.shop_name = '請輸入商店名稱';
                            return false;
                        }

                        if (form.shop_name.length > 100) {
                            errors.shop_name = '商店名稱不能超過 100 字';
                            return false;
                        }

                        return true;
                    };

                    const submitApplication = async () => {
                        if (!validateForm()) {
                            return;
                        }

                        submitting.value = true;
                        errorMessage.value = '';
                        successMessage.value = '';

                        try {
                            const response = await fetch('/wp-json/buygo-plus-one/v1/seller-application', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-WP-Nonce': '<?php echo $nonce; ?>'
                                },
                                credentials: 'include',
                                body: JSON.stringify(form)
                            });

                            const result = await response.json();

                            if (result.success) {
                                successMessage.value = result.message || '申請成功！您已成為測試賣家。';
                                // 清空表單
                                form.shop_name = '';
                                form.shop_description = '';
                                form.contact_phone = '';

                                // 3 秒後重新載入頁面
                                setTimeout(() => {
                                    window.location.reload();
                                }, 3000);
                            } else {
                                errorMessage.value = result.message || '申請失敗，請稍後再試。';
                            }
                        } catch (error) {
                            console.error('Submit error:', error);
                            errorMessage.value = '網路錯誤，請稍後再試。';
                        } finally {
                            submitting.value = false;
                        }
                    };

                    return {
                        form,
                        errors,
                        submitting,
                        errorMessage,
                        successMessage,
                        submitApplication
                    };
                }
            }).mount('#seller-application-app');
        });
        </script>
        <?php
        return ob_get_clean();
    }
}

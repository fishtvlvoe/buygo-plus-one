/**
 * Seller Management Admin Scripts
 * Phase 27: 賣家管理後台腳本
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        // 批准申請
        $('.approve-btn').on('click', function() {
            const userId = $(this).data('user-id');
            const row = $(this).closest('tr');

            if (!confirm('確定要批准此申請嗎？用戶將成為測試賣家。')) {
                return;
            }

            $.ajax({
                url: buygoSellerAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'buygo_approve_seller_application',
                    nonce: buygoSellerAdmin.nonce,
                    user_id: userId,
                    seller_type: 'test'
                },
                beforeSend: function() {
                    row.find('.approve-btn, .reject-btn').prop('disabled', true);
                },
                success: function(response) {
                    if (response.success) {
                        alert(response.data.message || '已批准申請');
                        location.reload();
                    } else {
                        alert(response.data.message || '操作失敗');
                        row.find('.approve-btn, .reject-btn').prop('disabled', false);
                    }
                },
                error: function() {
                    alert('網路錯誤，請稍後再試');
                    row.find('.approve-btn, .reject-btn').prop('disabled', false);
                }
            });
        });

        // 拒絕申請
        $('.reject-btn').on('click', function() {
            const userId = $(this).data('user-id');
            const row = $(this).closest('tr');

            const reason = prompt('請輸入拒絕原因（選填）：');

            if (reason === null) {
                return; // 用戶取消
            }

            $.ajax({
                url: buygoSellerAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'buygo_reject_seller_application',
                    nonce: buygoSellerAdmin.nonce,
                    user_id: userId,
                    reason: reason
                },
                beforeSend: function() {
                    row.find('.approve-btn, .reject-btn').prop('disabled', true);
                },
                success: function(response) {
                    if (response.success) {
                        alert(response.data.message || '已拒絕申請');
                        location.reload();
                    } else {
                        alert(response.data.message || '操作失敗');
                        row.find('.approve-btn, .reject-btn').prop('disabled', false);
                    }
                },
                error: function() {
                    alert('網路錯誤，請稍後再試');
                    row.find('.approve-btn, .reject-btn').prop('disabled', false);
                }
            });
        });

        // 升級賣家
        $('.upgrade-btn').on('click', function() {
            const userId = $(this).data('user-id');
            const row = $(this).closest('tr');

            if (!confirm('確定要將此賣家升級為正式賣家嗎？升級後將解除商品數量限制。')) {
                return;
            }

            $.ajax({
                url: buygoSellerAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'buygo_upgrade_seller',
                    nonce: buygoSellerAdmin.nonce,
                    user_id: userId
                },
                beforeSend: function() {
                    row.find('.upgrade-btn').prop('disabled', true).text('處理中...');
                },
                success: function(response) {
                    if (response.success) {
                        alert(response.data.message || '已升級為正式賣家');
                        location.reload();
                    } else {
                        alert(response.data.message || '操作失敗');
                        row.find('.upgrade-btn').prop('disabled', false).text('升級為正式賣家');
                    }
                },
                error: function() {
                    alert('網路錯誤，請稍後再試');
                    row.find('.upgrade-btn').prop('disabled', false).text('升級為正式賣家');
                }
            });
        });
    });

})(jQuery);

jQuery(document).ready(function($) {
    // 測試 LINE 連線（使用 REST API，因為更可靠）
    $('#test-line-connection').on('click', function() {
        const button = $(this);
        const originalText = button.text();
        
        button.prop('disabled', true).text('測試中...');
        $('#line-test-result').html('<p>測試中...</p>');
        
        const token = $('#line_channel_access_token').val();
        
        // 使用 REST API 端點
        $.ajax({
            url: buygoSettings.restUrl + '/settings/line/test-connection',
            type: 'POST',
            headers: {
                'X-WP-Nonce': buygoSettings.nonce,
                'Content-Type': 'application/json'
            },
            data: JSON.stringify({
                token: token
            }),
            success: function(response) {
                if (response.success) {
                    $('#line-test-result').html(
                        '<div class="notice notice-success"><p>連線成功！' + 
                        (response.data && response.data.data && response.data.data.displayName ? 'Bot 名稱：' + response.data.data.displayName : '') + 
                        '</p></div>'
                    );
                } else {
                    $('#line-test-result').html(
                        '<div class="notice notice-error"><p>連線失敗：' + 
                        (response.message || '未知錯誤') + 
                        '</p></div>'
                    );
                }
            },
            error: function(xhr) {
                let errorMsg = '連線失敗：請檢查網路連線';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = '連線失敗：' + xhr.responseJSON.message;
                } else if (xhr.status === 404) {
                    errorMsg = '連線失敗：API 端點不存在，請檢查外掛是否正確載入';
                }
                $('#line-test-result').html(
                    '<div class="notice notice-error"><p>' + errorMsg + '</p></div>'
                );
            },
            complete: function() {
                button.prop('disabled', false).text(originalText);
            }
        });
    });
    
    // 新增角色 Modal
    $('#add-role-btn').on('click', function() {
        $('#add-role-modal').show();
    });
    
    $('#cancel-add-role').on('click', function() {
        $('#add-role-modal').hide();
    });
    
    $('#confirm-add-role').on('click', function() {
        const userId = $('#add-role-user').val();
        const role = $('#add-role-type').val();
        
        if (!userId) {
            alert('請選擇使用者');
            return;
        }
        
        const button = $(this);
        const originalText = button.text();
        button.prop('disabled', true).text('處理中...');
        
        $.ajax({
            url: buygoSettings.restUrl + '/settings/helpers',
            type: 'POST',
            headers: {
                'X-WP-Nonce': buygoSettings.nonce,
                'Content-Type': 'application/json'
            },
            data: JSON.stringify({
                user_id: parseInt(userId),
                role: role
            }),
            success: function(response) {
                if (response.success) {
                    alert('角色已新增');
                    location.reload();
                } else {
                    alert('新增失敗：' + (response.message || '未知錯誤'));
                }
            },
            error: function(xhr) {
                let errorMsg = '新增失敗';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg += '：' + xhr.responseJSON.message;
                }
                alert(errorMsg);
            },
            complete: function() {
                button.prop('disabled', false).text(originalText);
            }
        });
    });
    
    // 移除小幫手
    $(document).on('click', '.delete-helper', function() {
        const userId = $(this).data('user-id');
        const button = $(this);
        
        if (!confirm('確定要移除這個小幫手嗎？')) {
            return;
        }
        
        const originalText = button.text();
        button.prop('disabled', true).text('處理中...');
        
        $.ajax({
            url: buygoSettings.restUrl + '/settings/helpers/' + userId,
            type: 'DELETE',
            headers: {
                'X-WP-Nonce': buygoSettings.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert('小幫手已移除');
                    location.reload();
                } else {
                    alert('移除失敗：' + (response.message || '未知錯誤'));
                }
            },
            error: function(xhr) {
                let errorMsg = '移除失敗';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg += '：' + xhr.responseJSON.message;
                }
                alert(errorMsg);
            },
            complete: function() {
                button.prop('disabled', false).text(originalText);
            }
        });
    });
    
    // 發送綁定連結
    $(document).on('click', '.send-binding-link', function() {
        const userId = $(this).data('user-id');
        const button = $(this);
        
        if (!confirm('確定要發送綁定連結給這個使用者嗎？')) {
            return;
        }
        
        const originalText = button.text();
        button.prop('disabled', true).text('發送中...');
        
        $.ajax({
            url: buygoSettings.restUrl + '/settings/binding/send',
            type: 'POST',
            headers: {
                'X-WP-Nonce': buygoSettings.nonce,
                'Content-Type': 'application/json'
            },
            data: JSON.stringify({
                user_id: parseInt(userId)
            }),
            success: function(response) {
                if (response.success) {
                    alert('綁定連結已發送：' + (response.message || ''));
                } else {
                    alert('發送失敗：' + (response.message || '未知錯誤'));
                }
            },
            error: function(xhr) {
                let errorMsg = '發送失敗';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg += '：' + xhr.responseJSON.message;
                }
                alert(errorMsg);
            },
            complete: function() {
                button.prop('disabled', false).text(originalText);
            }
        });
    });
    
    // 移除角色
    $(document).on('click', '.remove-role', function() {
        const userId = $(this).data('user-id');
        const role = $(this).data('role');
        const button = $(this);
        
        const roleName = role === 'buygo_admin' ? '管理員' : '小幫手';
        
        if (!confirm(`確定要移除這個使用者的「${roleName}」角色嗎？移除後將降級為一般顧客。`)) {
            return;
        }
        
        const originalText = button.text();
        button.prop('disabled', true).text('處理中...');
        
        $.ajax({
            url: buygoSettings.restUrl + '/settings/roles/remove',
            type: 'POST',
            headers: {
                'X-WP-Nonce': buygoSettings.nonce,
                'Content-Type': 'application/json'
            },
            data: JSON.stringify({
                user_id: parseInt(userId),
                role: role
            }),
            success: function(response) {
                if (response.success) {
                    alert(response.message || '角色已移除');
                    location.reload();
                } else {
                    alert('移除失敗：' + (response.message || '未知錯誤'));
                }
            },
            error: function(xhr) {
                let errorMsg = '移除失敗';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg += '：' + xhr.responseJSON.message;
                }
                alert(errorMsg);
            },
            complete: function() {
                button.prop('disabled', false).text(originalText);
            }
        });
    });

    // 賣家類型切換
    $('.seller-type-select').on('change', function() {
        const select = $(this);
        const userId = select.data('user-id');
        const sellerType = select.val();
        const originalValue = select.data('original-value') || select.val();

        // 儲存原始值（第一次變更時）
        if (!select.data('original-value')) {
            select.data('original-value', originalValue);
        }

        select.prop('disabled', true);

        $.ajax({
            url: buygoSettings.ajaxUrl,
            type: 'POST',
            data: {
                action: 'buygo_update_seller_type',
                user_id: userId,
                seller_type: sellerType,
                nonce: buygoSettings.nonce
            },
            success: function(response) {
                if (response.success) {
                    // 更新成功,顯示提示
                    const message = $('<div class="notice notice-success" style="padding: 8px 12px; margin: 10px 0;"><p>✅ 賣家類型已更新為：' +
                        (sellerType === 'test' ? '測試賣家 (2商品/2圖)' : '真實賣家 (無限制)') +
                        '</p></div>');
                    select.closest('tr').find('td:first').append(message);
                    setTimeout(function() {
                        message.fadeOut(function() { $(this).remove(); });
                    }, 3000);

                    // 更新原始值
                    select.data('original-value', sellerType);
                } else {
                    alert('更新失敗：' + (response.data || '未知錯誤'));
                    // 恢復原始值
                    select.val(originalValue);
                }
            },
            error: function(xhr) {
                alert('更新失敗：請檢查網路連線');
                // 恢復原始值
                select.val(originalValue);
            },
            complete: function() {
                select.prop('disabled', false);
            }
        });
    });
});

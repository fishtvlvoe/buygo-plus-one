jQuery(document).ready(function($) {
    // 測試 LINE 連線
    $('#test-line-connection').on('click', function() {
        const button = $(this);
        const originalText = button.text();
        
        button.prop('disabled', true).text('測試中...');
        $('#line-test-result').html('<p>測試中...</p>');
        
        $.ajax({
            url: buygoSettings.ajaxUrl,
            type: 'POST',
            data: {
                action: 'buygo_test_line_connection',
                nonce: buygoSettings.nonce,
                token: $('#line_channel_access_token').val()
            },
            success: function(response) {
                if (response.success) {
                    $('#line-test-result').html(
                        '<div class="notice notice-success"><p>連線成功！' + 
                        (response.data.data ? 'Bot 名稱：' + response.data.data.displayName : '') + 
                        '</p></div>'
                    );
                } else {
                    $('#line-test-result').html(
                        '<div class="notice notice-error"><p>連線失敗：' + 
                        (response.data.message || '未知錯誤') + 
                        '</p></div>'
                    );
                }
            },
            error: function() {
                $('#line-test-result').html(
                    '<div class="notice notice-error"><p>連線失敗：請檢查網路連線</p></div>'
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
});

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
                nonce: buygoSettings.nonce
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
        
        // 這裡可以透過 AJAX 新增角色
        // 暫時使用表單提交
        alert('功能開發中，請稍後');
    });
    
    // 移除小幫手
    $('.delete-helper').on('click', function() {
        const userId = $(this).data('user-id');
        
        if (!confirm('確定要移除這個小幫手嗎？')) {
            return;
        }
        
        // 這裡可以透過 AJAX 移除小幫手
        // 暫時使用表單提交
        alert('功能開發中，請稍後');
    });
});

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
                'X-WP-Nonce': buygoSettings.restNonce,
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
    
    // ===== 新增角色 Modal =====
    let userSearchTimeout = null;
    let sellerSearchTimeout = null;

    // 通用搜尋函式（v2.0: focus 即顯示前 20 筆）
    function setupSearchField(inputId, resultsId, hiddenId, selectedId, filterRole) {
        const $input = $(inputId);
        const $results = $(resultsId);
        const $hidden = $(hiddenId);
        const $selected = $(selectedId);
        let timeout = null;

        function triggerSearch(query) {
            $results.show().html('<div class="search-loading">搜尋中...</div>');

            let url = buygoSettings.restUrl + '/settings/users/search?query=' + encodeURIComponent(query);
            if (filterRole) {
                url += '&role=' + encodeURIComponent(filterRole);
            }

            $.ajax({
                url: url,
                type: 'GET',
                headers: { 'X-WP-Nonce': buygoSettings.restNonce },
                success: function(response) {
                    $results.empty();
                    if (response.success && response.data && response.data.length > 0) {
                        response.data.forEach(function(user) {
                            $results.append(
                                '<div class="search-result-item" data-id="' + user.id + '" data-name="' + $('<span>').text(user.name).html() + '" data-email="' + $('<span>').text(user.email).html() + '">' +
                                    '<div><span class="user-name">' + $('<span>').text(user.name).html() + '</span> <span class="user-email">' + $('<span>').text(user.email).html() + '</span></div>' +
                                    '<span class="user-id">WP-' + user.id + '</span>' +
                                '</div>'
                            );
                        });
                    } else {
                        $results.html('<div class="search-no-result">找不到符合的使用者</div>');
                    }
                    $results.show();
                },
                error: function() {
                    $results.html('<div class="search-no-result">搜尋失敗</div>').show();
                }
            });
        }

        // 輸入至少 1 字後觸發搜尋
        $input.on('input', function() {
            const query = $(this).val().trim();
            clearTimeout(timeout);
            if (query.length < 1) {
                $results.hide().empty();
                return;
            }
            timeout = setTimeout(function() {
                triggerSearch(query);
            }, 300);
        });

        // 點選搜尋結果
        $results.on('click', '.search-result-item', function() {
            const id = $(this).data('id');
            const name = $(this).data('name');
            const email = $(this).data('email');

            $hidden.val(id);
            $selected.find('.bgo-selected-name, .user-selected-name').text(name + ' (' + email + ')');
            $selected.show();
            $input.hide();
            $results.hide().empty();
        });

        // 清除已選
        $selected.on('click', '.bgo-selected-clear, .user-selected-clear', function() {
            $hidden.val('');
            $selected.hide();
            $input.val('').show().focus();
        });

        // 點擊搜尋區外部時關閉搜尋結果
        $(document).on('click', function(e) {
            if (!$(e.target).closest('.bgo-search-wrap, .user-search-wrap').length) {
                $results.hide();
            }
        });
    }

    // 初始化搜尋欄位（v2.0: 移除賣家搜尋欄位）
    setupSearchField('#add-role-user-search', '#add-role-user-results', '#add-role-user', '#add-role-user-selected', null);

    // 重置 Modal 狀態
    function resetAddRoleModal() {
        $('#add-role-user').val('');
        $('#add-role-user-search').val('').show();
        $('#add-role-user-selected').hide();
        $('#add-role-user-results').hide().empty();
    }

    // 開啟 Modal
    $('#add-role-btn').on('click', function() {
        resetAddRoleModal();
        $('#add-role-modal').show();
        setTimeout(function() { $('#add-role-user-search').focus(); }, 100);
    });

    // 關閉 Modal
    $('#cancel-add-role').on('click', function() {
        $('#add-role-modal').hide();
    });

    // 點擊背景關閉 Modal
    $('#add-role-modal').on('click', function(e) {
        if (e.target === this) {
            $(this).hide();
        }
    });

    // 確認新增（v2.0: 固定賦予 buygo_admin 角色）
    $('#confirm-add-role').on('click', function() {
        const userId = $('#add-role-user').val();

        if (!userId) {
            alert('請先搜尋並選擇使用者');
            return;
        }

        const button = $(this);
        const originalText = button.text();
        button.prop('disabled', true).text('處理中...');

        const payload = {
            user_id: parseInt(userId),
            role: 'buygo_admin'
        };

        $.ajax({
            url: buygoSettings.restUrl + '/settings/helpers',
            type: 'POST',
            headers: {
                'X-WP-Nonce': buygoSettings.restNonce,
                'Content-Type': 'application/json'
            },
            data: JSON.stringify(payload),
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
                'X-WP-Nonce': buygoSettings.restNonce
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
    
    // ===== 新增小幫手 Modal（從綁定關係欄位觸發）=====
    $(document).on('click', '.add-helper-link', function(e) {
        e.preventDefault();
        const sellerId = $(this).data('seller-id');
        const sellerName = $(this).data('seller-name');

        $('#add-helper-seller-id').val(sellerId);
        $('#add-helper-seller-label').text(sellerName);
        $('#add-helper-search').val('');
        $('#add-helper-results').hide().empty();

        // 載入目前的小幫手列表
        loadCurrentHelpers(sellerId);

        $('#add-helper-modal').show();
        setTimeout(function() { $('#add-helper-search').focus(); }, 100);
    });

    function loadCurrentHelpers(sellerId) {
        const $list = $('#add-helper-current-list');
        $list.html('<p style="color:#666;">載入中...</p>');

        $.ajax({
            url: buygoSettings.restUrl + '/settings/helpers?seller_id=' + sellerId,
            type: 'GET',
            headers: { 'X-WP-Nonce': buygoSettings.restNonce },
            success: function(response) {
                if (response.success && response.data && response.data.length > 0) {
                    let html = '<p style="font-weight:500; margin-bottom:8px;">目前小幫手</p>';
                    response.data.forEach(function(helper) {
                        html += '<div style="display:flex; justify-content:space-between; align-items:center; padding:6px 10px; background:#f9f9f9; border-radius:4px; margin-bottom:4px;">'
                            + '<span>' + $('<span>').text(helper.name).html() + ' <small style="color:#666;">' + $('<span>').text(helper.email).html() + '</small></span>'
                            + '<button type="button" class="remove-helper-inline button" data-user-id="' + helper.id + '" data-seller-id="' + sellerId + '" style="font-size:11px; padding:2px 8px; height:auto; color:#dc3232; border-color:#dc3232;">移除</button>'
                            + '</div>';
                    });
                    $list.html(html);
                } else {
                    $list.html('<p style="color:#999; font-size:13px;">尚無小幫手</p>');
                }
            },
            error: function() {
                $list.html('<p style="color:#dc3232;">載入失敗</p>');
            }
        });
    }

    // 小幫手搜尋
    let helperSearchTimeout = null;
    $('#add-helper-search').on('input', function() {
        const query = $(this).val().trim();
        const $results = $('#add-helper-results');
        clearTimeout(helperSearchTimeout);

        if (query.length < 2) {
            $results.hide().empty();
            return;
        }

        $results.show().html('<div class="search-loading">搜尋中...</div>');

        helperSearchTimeout = setTimeout(function() {
            $.ajax({
                url: buygoSettings.restUrl + '/settings/users/search?query=' + encodeURIComponent(query),
                type: 'GET',
                headers: { 'X-WP-Nonce': buygoSettings.restNonce },
                success: function(response) {
                    $results.empty();
                    if (response.success && response.data && response.data.length > 0) {
                        response.data.forEach(function(user) {
                            $results.append(
                                '<div class="search-result-item" data-id="' + user.id + '">'
                                    + '<div><span class="user-name">' + $('<span>').text(user.name).html() + '</span> <span class="user-email">' + $('<span>').text(user.email).html() + '</span></div>'
                                    + '<span class="user-id">WP-' + user.id + '</span>'
                                + '</div>'
                            );
                        });
                    } else {
                        $results.html('<div class="search-no-result">找不到符合的使用者</div>');
                    }
                    $results.show();
                }
            });
        }, 300);
    });

    // 點選搜尋結果 → 直接新增為小幫手
    $('#add-helper-results').on('click', '.search-result-item', function() {
        const userId = $(this).data('id');
        const sellerId = $('#add-helper-seller-id').val();

        $.ajax({
            url: buygoSettings.restUrl + '/settings/helpers',
            type: 'POST',
            headers: {
                'X-WP-Nonce': buygoSettings.restNonce,
                'Content-Type': 'application/json'
            },
            data: JSON.stringify({
                user_id: parseInt(userId),
                role: 'buygo_helper',
                seller_id: parseInt(sellerId)
            }),
            success: function(response) {
                if (response.success) {
                    $('#add-helper-search').val('');
                    $('#add-helper-results').hide().empty();
                    loadCurrentHelpers(sellerId);
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
            }
        });
    });

    // 移除小幫手（inline）
    $(document).on('click', '.remove-helper-inline', function() {
        const userId = $(this).data('user-id');
        const sellerId = $(this).data('seller-id');
        const btn = $(this);

        if (!confirm('確定要移除這個小幫手嗎？')) return;

        btn.prop('disabled', true).text('...');
        $.ajax({
            url: buygoSettings.restUrl + '/settings/helpers/' + userId,
            type: 'DELETE',
            headers: { 'X-WP-Nonce': buygoSettings.restNonce },
            success: function(response) {
                if (response.success) {
                    loadCurrentHelpers(sellerId);
                } else {
                    alert('移除失敗');
                    btn.prop('disabled', false).text('移除');
                }
            }
        });
    });

    // 關閉小幫手 Modal
    $('#close-add-helper').on('click', function() {
        $('#add-helper-modal').hide();
        location.reload(); // 重新整理以更新綁定關係欄位
    });

    $('#add-helper-modal').on('click', function(e) {
        if (e.target === this) {
            $(this).hide();
            location.reload();
        }
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
                'X-WP-Nonce': buygoSettings.restNonce,
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

    // 賣家類型切換 - 已移除（UI 重構）
    // 功能已停用，相關欄位已從 UI 移除
    // 保留註解以記錄歷史功能

    // 商品限制數量變更
    let limitUpdateTimeout;
    $('.product-limit-input').on('input', function() {
        const input = $(this);
        const userId = input.data('user-id');
        const productLimit = parseInt(input.val()) || 0;

        // 清除之前的 timeout
        if (limitUpdateTimeout) {
            clearTimeout(limitUpdateTimeout);
        }

        // 延遲 1 秒後才儲存 (避免每次輸入都送請求)
        limitUpdateTimeout = setTimeout(function() {
            $.ajax({
                url: buygoSettings.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'buygo_update_product_limit',
                    user_id: userId,
                    product_limit: productLimit,
                    nonce: buygoSettings.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // 顯示提示
                        const message = $('<div class="notice notice-success" style="padding: 8px 12px; margin: 10px 0;"><p>✅ 商品限制已更新為：' +
                            (productLimit === 0 ? '無限制' : productLimit + ' 個商品') +
                            '</p></div>');
                        input.closest('tr').find('td:first').append(message);
                        setTimeout(function() {
                            message.fadeOut(function() { $(this).remove(); });
                        }, 3000);
                    } else {
                        alert('更新失敗：' + (response.data || '未知錯誤'));
                    }
                },
                error: function(xhr) {
                    alert('更新失敗：請檢查網路連線');
                }
            });
        }, 1000);
    });

    // 賣家商品 ID 驗證
    $(document).on('click', '#validate-seller-product', function(e) {
        e.preventDefault();

        var $btn = $(this);
        var $input = $('#buygo-seller-product-id');
        var $result = $('#seller-product-validation-result');
        var productId = $input.val().trim();

        if (!productId) {
            $result.html('<span style="color: #dc3232;">請輸入商品 ID</span>');
            return;
        }

        $btn.prop('disabled', true).text('驗證中...');
        $result.html('<span style="color: #666;">正在驗證商品...</span>');

        $.ajax({
            url: buygoSettings.ajaxUrl,
            type: 'POST',
            data: {
                action: 'buygo_validate_seller_product',
                nonce: buygoSettings.nonce,
                product_id: productId
            },
            success: function(response) {
                if (response.success) {
                    var p = response.data.product;
                    var html = '<div style="background: #e7f5e7; padding: 10px; border-radius: 4px; margin-top: 10px;">';
                    html += '<strong style="color: #00a32a;">✓ 商品驗證成功</strong><br>';
                    html += '<table style="margin-top: 8px; font-size: 13px;">';
                    html += '<tr><td style="padding: 2px 10px 2px 0; color: #666;">商品名稱：</td><td>' + p.title + '</td></tr>';
                    html += '<tr><td style="padding: 2px 10px 2px 0; color: #666;">商品價格：</td><td>NT$ ' + p.price + '</td></tr>';
                    html += '<tr><td style="padding: 2px 10px 2px 0; color: #666;">虛擬商品：</td><td>' + (p.is_virtual ? '✓ 是' : '✗ 否') + '</td></tr>';
                    html += '<tr><td style="padding: 2px 10px 2px 0; color: #666;">後台連結：</td><td><a href="' + p.admin_url + '" target="_blank">編輯商品</a></td></tr>';
                    html += '</table></div>';
                    $result.html(html);
                } else {
                    $result.html('<span style="color: #dc3232;">✗ ' + response.data.message + '</span>');
                }
            },
            error: function() {
                $result.html('<span style="color: #dc3232;">驗證失敗，請稍後重試</span>');
            },
            complete: function() {
                $btn.prop('disabled', false).text('驗證商品');
            }
        });
    });

});

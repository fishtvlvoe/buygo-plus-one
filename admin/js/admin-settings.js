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
                'X-WP-Nonce': buygoSettings.restNonce,
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
    
    // 發送綁定連結 - 已移除（UI 重構）
    // 功能已停用，相關按鈕已從 UI 移除
    // 保留註解以記錄歷史功能
    
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

    // 商品 ID 輸入時清除驗證結果
    $(document).on('input', '#buygo-seller-product-id', function() {
        $('#seller-product-validation-result').html('');
    });

    // ========================================
    // 賣家商品搜尋式下拉選單
    // ========================================

    let searchTimeout;
    let $dropdown = null;

    /**
     * 建立下拉選單元素
     */
    function createDropdown() {
        if ($dropdown) {
            return $dropdown;
        }

        $dropdown = $('<div>')
            .attr('id', 'seller-product-dropdown')
            .css({
                position: 'absolute',
                backgroundColor: '#fff',
                border: '1px solid #ccc',
                borderTop: 'none',
                maxHeight: '300px',
                overflowY: 'auto',
                zIndex: 1000,
                width: '100%',
                boxShadow: '0 4px 6px rgba(0,0,0,0.1)',
                display: 'none'
            });

        // 插入到輸入框的父元素
        $('#buygo-seller-product-id').parent().css('position', 'relative').append($dropdown);

        return $dropdown;
    }

    /**
     * 搜尋虛擬商品
     */
    function searchVirtualProducts(searchTerm) {
        $.ajax({
            url: buygoSettings.ajaxUrl,
            type: 'POST',
            data: {
                action: 'buygo_search_virtual_products',
                nonce: buygoSettings.nonce,
                search: searchTerm
            },
            success: function(response) {
                if (response.success && response.data.products) {
                    showDropdown(response.data.products);
                } else {
                    hideDropdown();
                }
            },
            error: function() {
                console.error('搜尋失敗');
                hideDropdown();
            }
        });
    }

    /**
     * 顯示下拉選單
     */
    function showDropdown(products) {
        const $dd = createDropdown();

        if (products.length === 0) {
            $dd.html('<div style="padding: 10px; color: #666; text-align: center;">沒有找到虛擬商品</div>').show();
            return;
        }

        $dd.empty();

        products.forEach(function(product) {
            const $item = $('<div>')
                .addClass('dropdown-item')
                .attr('data-product-id', product.id)
                .css({
                    padding: '10px 12px',
                    cursor: 'pointer',
                    borderBottom: '1px solid #f0f0f0',
                    transition: 'background-color 0.2s'
                })
                .hover(
                    function() { $(this).css('backgroundColor', '#f0f0f0'); },
                    function() { $(this).css('backgroundColor', '#fff'); }
                )
                .html(
                    '<div><strong>#' + product.id + '</strong> - ' + product.title + '</div>' +
                    '<div style="font-size: 12px; color: #666; margin-top: 4px;">NT$ ' + product.price + '</div>'
                )
                .on('click', function() {
                    selectProduct(product);
                });

            $dd.append($item);
        });

        $dd.show();
    }

    /**
     * 隱藏下拉選單
     */
    function hideDropdown() {
        if ($dropdown) {
            $dropdown.hide();
        }
    }

    /**
     * 選擇商品
     */
    function selectProduct(product) {
        $('#buygo-seller-product-id').val(product.id);
        hideDropdown();

        // 自動觸發驗證
        $('#validate-seller-product').click();
    }

    // 監聽輸入事件
    $('#buygo-seller-product-id').on('input', function() {
        const searchTerm = $(this).val().trim();

        // 清除之前的 timeout
        if (searchTimeout) {
            clearTimeout(searchTimeout);
        }

        // 如果輸入為空，隱藏下拉選單
        if (searchTerm.length === 0) {
            hideDropdown();
            return;
        }

        // 延遲 300ms 後搜尋（避免每次輸入都送請求）
        searchTimeout = setTimeout(function() {
            searchVirtualProducts(searchTerm);
        }, 300);
    });

    // 點擊輸入框時，如果有值就觸發搜尋
    $('#buygo-seller-product-id').on('focus', function() {
        const searchTerm = $(this).val().trim();
        if (searchTerm.length > 0) {
            searchVirtualProducts(searchTerm);
        }
    });

    // 點擊外部時隱藏下拉選單
    $(document).on('click', function(e) {
        if (!$(e.target).closest('#buygo-seller-product-id, #seller-product-dropdown').length) {
            hideDropdown();
        }
    });

    // 鍵盤導航（上下鍵選擇）
    $('#buygo-seller-product-id').on('keydown', function(e) {
        const $dd = $('#seller-product-dropdown');

        if (!$dd.is(':visible')) {
            return;
        }

        const $items = $dd.find('.dropdown-item');
        const $selected = $dd.find('.dropdown-item.selected');
        let index = $items.index($selected);

        // 移除舊的選中狀態
        $items.removeClass('selected').css('backgroundColor', '#fff');

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            index = (index + 1) % $items.length;
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            index = (index - 1 + $items.length) % $items.length;
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if ($selected.length) {
                $selected.click();
            }
            return;
        } else if (e.key === 'Escape') {
            hideDropdown();
            return;
        } else {
            return;
        }

        // 設定新的選中狀態
        const $newSelected = $items.eq(index);
        $newSelected.addClass('selected').css('backgroundColor', '#e0f0ff');

        // 自動捲動到可視區域
        const ddTop = $dd.scrollTop();
        const ddHeight = $dd.height();
        const itemTop = $newSelected.position().top;
        const itemHeight = $newSelected.outerHeight();

        if (itemTop < 0) {
            $dd.scrollTop(ddTop + itemTop);
        } else if (itemTop + itemHeight > ddHeight) {
            $dd.scrollTop(ddTop + itemTop + itemHeight - ddHeight);
        }
    });
});

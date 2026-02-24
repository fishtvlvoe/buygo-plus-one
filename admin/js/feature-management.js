/**
 * BGO Feature Management Tab 互動邏輯
 *
 * 功能管理頁面：授權狀態管理 + 功能列表渲染 + toggle 開關操作
 * IIFE 封裝，透過 window.bgoFeatureManagement 接收 REST API 設定
 *
 * @package BuyGoPlus
 * @since 2.0.0
 */
(function() {
    'use strict';

    var config = window.bgoFeatureManagement || {};
    var restUrl = config.restUrl || '';
    var restNonce = config.restNonce || '';

    /**
     * 初始化功能管理 Tab
     */
    function init() {
        loadFeatures();
        loadLicense();
        bindLicenseActions();
    }

    /**
     * 載入功能列表（GET /features）
     */
    function loadFeatures() {
        fetch(restUrl + '/features', {
            headers: { 'X-WP-Nonce': restNonce }
        })
        .then(function(r) { return r.json(); })
        .then(function(resp) {
            if (resp.success) {
                renderFreeFeatures(resp.data.free);
                renderProFeatures(resp.data.pro);
            } else {
                showMessage(resp.message || '載入功能列表失敗', 'error');
            }
        })
        .catch(function(err) {
            showMessage('載入功能列表失敗：' + err.message, 'error');
        });
    }

    /**
     * 渲染 Free 功能（toggle 永遠 on + disabled，純視覺展示）
     *
     * @param {Array} features Free 功能列表
     */
    function renderFreeFeatures(features) {
        var container = document.getElementById('bgo-fm-free-list');
        if (!container) return;

        container.innerHTML = features.map(function(f) {
            return '<div class="bgo-fm-feature-card">' +
                '<div class="bgo-fm-feature-info">' +
                    '<div class="bgo-fm-feature-name">' + escHtml(f.name) + '</div>' +
                    '<div class="bgo-fm-feature-desc">' + escHtml(f.description) + '</div>' +
                '</div>' +
                '<label class="bgo-fm-toggle">' +
                    '<input type="checkbox" checked disabled>' +
                    '<span class="bgo-fm-toggle-slider"></span>' +
                '</label>' +
            '</div>';
        }).join('');
    }

    /**
     * 渲染 Pro 功能（toggle 可操作，即時呼叫 API）
     *
     * @param {Array} features Pro 功能列表
     */
    function renderProFeatures(features) {
        var container = document.getElementById('bgo-fm-pro-list');
        if (!container) return;

        container.innerHTML = features.map(function(f) {
            var checked = f.enabled ? ' checked' : '';
            return '<div class="bgo-fm-feature-card">' +
                '<div class="bgo-fm-feature-info">' +
                    '<div class="bgo-fm-feature-name">' + escHtml(f.name) + '</div>' +
                    '<div class="bgo-fm-feature-desc">' + escHtml(f.description) + '</div>' +
                '</div>' +
                '<label class="bgo-fm-toggle">' +
                    '<input type="checkbox" data-feature="' + escHtml(f.id) + '"' + checked + '>' +
                    '<span class="bgo-fm-toggle-slider"></span>' +
                '</label>' +
            '</div>';
        }).join('');

        // 綁定 toggle change 事件
        var inputs = container.querySelectorAll('input[data-feature]');
        for (var i = 0; i < inputs.length; i++) {
            inputs[i].addEventListener('change', handleToggleChange);
        }
    }

    /**
     * 處理 Pro 功能 toggle 切換
     * 收集所有 Pro toggle 狀態 → POST /features/toggles
     *
     * @param {Event} e change 事件
     */
    function handleToggleChange(e) {
        var checkbox = e.target;
        var toggles = {};

        var inputs = document.querySelectorAll('#bgo-fm-pro-list input[data-feature]');
        for (var i = 0; i < inputs.length; i++) {
            toggles[inputs[i].dataset.feature] = inputs[i].checked;
        }

        fetch(restUrl + '/features/toggles', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': restNonce
            },
            body: JSON.stringify({ toggles: toggles })
        })
        .then(function(r) { return r.json(); })
        .then(function(resp) {
            if (!resp.success) {
                // 回滾 checkbox 狀態
                checkbox.checked = !checkbox.checked;
                showMessage(resp.message || '儲存失敗', 'error');
            }
        })
        .catch(function(err) {
            checkbox.checked = !checkbox.checked;
            showMessage('儲存失敗：' + err.message, 'error');
        });
    }

    /**
     * 載入授權狀態（GET /features/license）
     */
    function loadLicense() {
        fetch(restUrl + '/features/license', {
            headers: { 'X-WP-Nonce': restNonce }
        })
        .then(function(r) { return r.json(); })
        .then(function(resp) {
            if (resp.success) {
                updateLicenseUI(resp.data);
            }
        })
        .catch(function() {
            // 授權載入失敗不影響頁面使用
        });
    }

    /**
     * 更新授權 UI 顯示
     *
     * @param {Object} license 授權資訊 {key, status, expires}
     */
    function updateLicenseUI(license) {
        var badge = document.getElementById('bgo-fm-license-badge');
        var keyInput = document.getElementById('bgo-fm-license-key');
        var activateBtn = document.getElementById('bgo-fm-activate-btn');
        var deactivateBtn = document.getElementById('bgo-fm-deactivate-btn');
        var expiresEl = document.getElementById('bgo-fm-license-expires');

        if (!badge || !keyInput || !activateBtn || !deactivateBtn || !expiresEl) return;

        if (license.status === 'active') {
            badge.textContent = 'Pro';
            badge.className = 'bgo-badge bgo-badge-success';
            keyInput.value = license.key;
            activateBtn.style.display = 'none';
            deactivateBtn.style.display = 'inline-block';
            if (license.expires) {
                expiresEl.textContent = '到期日：' + license.expires;
                expiresEl.style.display = 'inline';
            }
        } else {
            badge.textContent = 'Free';
            badge.className = 'bgo-badge bgo-badge-default';
            keyInput.value = '';
            activateBtn.style.display = 'inline-block';
            deactivateBtn.style.display = 'none';
            expiresEl.style.display = 'none';
        }
    }

    /**
     * 綁定授權操作（驗證 + 停用）
     */
    function bindLicenseActions() {
        var activateBtn = document.getElementById('bgo-fm-activate-btn');
        var deactivateBtn = document.getElementById('bgo-fm-deactivate-btn');

        if (!activateBtn || !deactivateBtn) return;

        // 驗證授權碼（POST /features/license）
        activateBtn.addEventListener('click', function() {
            var key = document.getElementById('bgo-fm-license-key').value.trim();
            if (!key) return;

            activateBtn.disabled = true;
            activateBtn.textContent = '驗證中...';

            fetch(restUrl + '/features/license', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': restNonce
                },
                body: JSON.stringify({ key: key })
            })
            .then(function(r) { return r.json(); })
            .then(function(resp) {
                if (resp.success) {
                    updateLicenseUI(resp.data);
                    showMessage('授權啟用成功', 'success');
                    loadFeatures();
                } else {
                    showMessage(resp.message || '驗證失敗', 'error');
                }
            })
            .catch(function(err) {
                showMessage('驗證失敗：' + err.message, 'error');
            })
            .finally(function() {
                activateBtn.disabled = false;
                activateBtn.textContent = '驗證';
            });
        });

        // 停用授權（DELETE /features/license）
        deactivateBtn.addEventListener('click', function() {
            if (!confirm('確定要停用授權嗎？Pro 功能將無法使用。')) return;

            deactivateBtn.disabled = true;
            deactivateBtn.textContent = '停用中...';

            fetch(restUrl + '/features/license', {
                method: 'DELETE',
                headers: { 'X-WP-Nonce': restNonce }
            })
            .then(function(r) { return r.json(); })
            .then(function(resp) {
                if (resp.success) {
                    updateLicenseUI(resp.data);
                    showMessage('授權已停用', 'success');
                    loadFeatures();
                }
            })
            .catch(function(err) {
                showMessage('停用失敗：' + err.message, 'error');
            })
            .finally(function() {
                deactivateBtn.disabled = false;
                deactivateBtn.textContent = '停用授權';
            });
        });
    }

    /**
     * 顯示操作結果訊息（3 秒後自動隱藏）
     *
     * @param {string} text 訊息內容
     * @param {string} type 'success' 或 'error'
     */
    function showMessage(text, type) {
        var el = document.getElementById('bgo-fm-message');
        if (!el) return;

        el.textContent = text;
        el.className = 'bgo-fm-message ' + type;
        el.style.display = 'block';
        setTimeout(function() {
            el.style.display = 'none';
        }, 3000);
    }

    /**
     * HTML 跳脫（防 XSS）
     *
     * @param {string} str 原始字串
     * @returns {string} 跳脫後的字串
     */
    function escHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(String(str)));
        return div.innerHTML;
    }

    // 頁面載入後初始化
    document.addEventListener('DOMContentLoaded', init);
})();

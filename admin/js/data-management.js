/**
 * 資料管理 Tab 互動邏輯
 * 功能：子 Tab 切換、查詢篩選、表格渲染、全選/刪除、客戶編輯
 * @package BuyGoPlus
 * @since 3.1.0
 */
(function () {
    'use strict';
    var cfg = window.bgoDataManagement || {};
    var restUrl = cfg.restUrl || '', nonce = cfg.restNonce || '';
    var curType = 'orders', curPage = 1, totPages = 1;

    // --- 工具函數 ---
    function $(id) { return document.getElementById(id); }
    function escHtml(s) { var d = document.createElement('div'); d.appendChild(document.createTextNode(String(s))); return d.innerHTML; }
    function escAttr(s) { return s.replace(/&/g,'&amp;').replace(/'/g,'&#39;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
    function fmtDate(d) { return d ? d.substring(0, 16) : '-'; }

    function api(method, path, body) {
        var opts = { method: method, headers: { 'X-WP-Nonce': nonce } };
        if (body) { opts.headers['Content-Type'] = 'application/json'; opts.body = JSON.stringify(body); }
        return fetch(restUrl + path, opts).then(function (r) { return r.json(); });
    }

    // --- 初始化 ---
    function init() {
        // 子 Tab 切換
        document.querySelectorAll('.bgo-dm-sub-tab').forEach(function (tab) {
            tab.addEventListener('click', function () {
                document.querySelectorAll('.bgo-dm-sub-tab').forEach(function (t) { t.classList.remove('active'); });
                tab.classList.add('active');
                curType = tab.getAttribute('data-type'); curPage = 1;
                $('bgo-dm-date-from').value = ''; $('bgo-dm-date-to').value = ''; $('bgo-dm-keyword').value = '';
                queryData();
            });
        });
        // 查詢
        $('bgo-dm-query-btn').addEventListener('click', function () { curPage = 1; queryData(); });
        $('bgo-dm-keyword').addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); curPage = 1; queryData(); } });
        // 全選
        $('bgo-dm-select-all').addEventListener('change', function () {
            var checked = this.checked;
            document.querySelectorAll('.bgo-dm-row-check').forEach(function (cb) { cb.checked = checked; });
            updateDelBtn();
        });
        // 刪除按鈕 → 開 Modal
        $('bgo-dm-delete-btn').addEventListener('click', openDeleteModal);
        // 刪除 Modal
        var delInput = $('bgo-dm-delete-confirm-input'), delConfirm = $('bgo-dm-delete-confirm');
        delInput.addEventListener('input', function () { delConfirm.disabled = delInput.value.trim() !== 'DELETE'; });
        $('bgo-dm-delete-cancel').addEventListener('click', closeDelModal);
        $('bgo-dm-delete-modal').querySelector('.bgo-modal-overlay').addEventListener('click', closeDelModal);
        delConfirm.addEventListener('click', doDelete);
        // 編輯 Modal
        $('bgo-dm-edit-cancel').addEventListener('click', closeEditModal);
        $('bgo-dm-edit-modal').querySelector('.bgo-modal-overlay').addEventListener('click', closeEditModal);
        $('bgo-dm-edit-save').addEventListener('click', doSave);
        // 預設載入
        queryData();
    }

    // --- 查詢 ---
    function queryData() {
        var params = new URLSearchParams({
            type: curType, date_from: $('bgo-dm-date-from').value, date_to: $('bgo-dm-date-to').value,
            keyword: $('bgo-dm-keyword').value, page: curPage, per_page: 20
        });
        $('bgo-dm-tbody').innerHTML = '<tr><td colspan="8" class="bgo-dm-loading">載入中...</td></tr>';
        $('bgo-dm-select-all').checked = false; updateDelBtn();

        api('GET', '/data-management/query?' + params.toString())
            .then(function (resp) {
                if (resp.success && resp.data) {
                    renderTable(resp.data);
                    totPages = Math.ceil((resp.data.total || 0) / (resp.data.per_page || 20));
                    renderPagination(resp.data.total || 0);
                } else { showEmpty(resp.message || '查詢失敗'); }
            })
            .catch(function (e) { showEmpty('請求失敗：' + e.message); });
    }

    // --- 表頭定義 ---
    function getHeaders() {
        var cols = {
            orders: [
                { k: 'id', l: 'ID' }, { k: 'invoice_no', l: '訂單編號' }, { k: 'customer_name', l: '客戶' },
                { k: 'total_amount', l: '金額', r: function (i) { return escHtml('$' + Number(i.total_amount||0).toLocaleString()); } },
                { k: 'status', l: '狀態', r: function (i) { return badge(i.status); } },
                { k: 'created_at', l: '建立日期', r: function (i) { return escHtml(fmtDate(i.created_at)); } }
            ],
            products: [
                { k: 'id', l: 'ID' }, { k: 'name', l: '商品名稱' },
                { k: 'price', l: '價格', r: function (i) { return escHtml('$' + Number(i.price||0).toLocaleString()); } },
                { k: 'item_status', l: '狀態', r: function (i) { return badge(i.item_status); } },
                { k: 'created_at', l: '建立日期', r: function (i) { return escHtml(fmtDate(i.created_at)); } }
            ],
            customers: [
                { k: 'id', l: 'ID' }, { k: 'full_name', l: '姓名' }, { k: 'email', l: 'Email' },
                { k: 'phone', l: '電話' }, { k: 'order_count', l: '訂單數' },
                { k: 'created_at', l: '建立日期', r: function (i) { return escHtml(fmtDate(i.created_at)); } }
            ]
        };
        return cols[curType] || [];
    }

    function badge(status) {
        var m = { completed:'success', paid:'success', active:'success', publish:'success',
                  pending:'warning', processing:'info', draft:'default',
                  cancelled:'error', failed:'error', refunded:'error', inactive:'error' };
        var cls = m[status] ? 'bgo-badge-' + m[status] : 'bgo-badge-default';
        return '<span class="bgo-badge ' + cls + '">' + escHtml(status||'-') + '</span>';
    }

    // --- 渲染表格 ---
    function renderTable(result) {
        var items = result.data || [], headers = getHeaders();
        var thead = $('bgo-dm-thead'), tbody = $('bgo-dm-tbody');
        // 表頭
        var th = '<tr><th class="bgo-dm-col-check"></th>';
        headers.forEach(function (h) { th += '<th>' + escHtml(h.l) + '</th>'; });
        if (curType === 'customers') th += '<th>操作</th>';
        thead.innerHTML = th + '</tr>';
        // 表身
        if (!items.length) {
            tbody.innerHTML = '<tr><td colspan="' + (headers.length+2) + '" class="bgo-dev-empty">沒有符合條件的資料</td></tr>';
            return;
        }
        var html = '';
        items.forEach(function (item) {
            html += '<tr><td class="bgo-dm-col-check"><input type="checkbox" class="bgo-dm-row-check" value="' + item.id + '"></td>';
            headers.forEach(function (h) { html += '<td>' + (h.r ? h.r(item) : escHtml(String(item[h.k]||'-'))) + '</td>'; });
            if (curType === 'customers') html += '<td><a class="bgo-dm-edit-link" data-id="' + item.id + '" data-item=\'' + escAttr(JSON.stringify(item)) + '\'>編輯</a></td>';
            html += '</tr>';
        });
        tbody.innerHTML = html;
        // 綁定事件
        tbody.querySelectorAll('.bgo-dm-row-check').forEach(function (cb) { cb.addEventListener('change', updateDelBtn); });
        tbody.querySelectorAll('.bgo-dm-edit-link').forEach(function (lk) {
            lk.addEventListener('click', function () { openEditModal(JSON.parse(lk.getAttribute('data-item'))); });
        });
    }

    // --- 分頁 ---
    function renderPagination(total) {
        var c = $('bgo-dm-pagination');
        if (totPages <= 1) { c.innerHTML = total > 0 ? '<span class="bgo-dm-page-info">共 ' + total + ' 筆</span>' : ''; return; }
        c.innerHTML = '<button id="bgo-dm-prev"' + (curPage<=1?' disabled':'') + '>上一頁</button>' +
            '<span class="bgo-dm-page-info">' + curPage + ' / ' + totPages + '（共 ' + total + ' 筆）</span>' +
            '<button id="bgo-dm-next"' + (curPage>=totPages?' disabled':'') + '>下一頁</button>';
        $('bgo-dm-prev').addEventListener('click', function () { if (curPage > 1) { curPage--; queryData(); } });
        $('bgo-dm-next').addEventListener('click', function () { if (curPage < totPages) { curPage++; queryData(); } });
    }

    function showEmpty(msg) {
        $('bgo-dm-thead').innerHTML = '';
        $('bgo-dm-tbody').innerHTML = '<tr><td colspan="8" class="bgo-dev-empty">' + escHtml(msg) + '</td></tr>';
        $('bgo-dm-pagination').innerHTML = '';
    }

    // --- 刪除按鈕狀態 ---
    function updateDelBtn() {
        var n = document.querySelectorAll('.bgo-dm-row-check:checked').length;
        $('bgo-dm-delete-btn').disabled = n === 0;
        $('bgo-dm-selected-info').textContent = n > 0 ? '已選 ' + n + ' 筆' : '';
    }

    // --- 刪除 Modal ---
    function openDeleteModal() {
        var checked = document.querySelectorAll('.bgo-dm-row-check:checked');
        if (!checked.length) return;
        var names = { orders:'訂單', products:'商品', customers:'客戶' };
        $('bgo-dm-delete-count').textContent = checked.length;
        $('bgo-dm-delete-type').textContent = names[curType] || '資料';
        $('bgo-dm-delete-confirm-input').value = '';
        $('bgo-dm-delete-confirm').disabled = true;
        $('bgo-dm-delete-modal').style.display = 'flex';
    }
    function closeDelModal() { $('bgo-dm-delete-modal').style.display = 'none'; }

    function doDelete() {
        var ids = []; document.querySelectorAll('.bgo-dm-row-check:checked').forEach(function (cb) { ids.push(parseInt(cb.value, 10)); });
        if (!ids.length) return;
        var btn = $('bgo-dm-delete-confirm'); btn.disabled = true; btn.textContent = '刪除中...';
        api('POST', '/data-management/delete-' + curType, { ids: ids, confirmation_token: 'DELETE' })
            .then(function (resp) { closeDelModal(); resp.success ? queryData() : alert('刪除失敗：' + (resp.message||'未知錯誤')); })
            .catch(function (e) { closeDelModal(); alert('請求失敗：' + e.message); })
            .finally(function () { btn.disabled = false; btn.textContent = '確認刪除'; });
    }

    // --- 客戶編輯 Modal ---
    function openEditModal(item) {
        $('bgo-dm-edit-id').value = item.id || '';
        $('bgo-dm-edit-last-name').value = '';
        $('bgo-dm-edit-first-name').value = '';
        $('bgo-dm-edit-phone').value = item.phone || '';
        $('bgo-dm-edit-address').value = '';
        $('bgo-dm-edit-city').value = '';
        $('bgo-dm-edit-postcode').value = '';
        $('bgo-dm-edit-taiwan-id').value = '';
        $('bgo-dm-edit-message').style.display = 'none';
        $('bgo-dm-edit-modal').style.display = 'flex';
    }
    function closeEditModal() { $('bgo-dm-edit-modal').style.display = 'none'; $('bgo-dm-edit-message').style.display = 'none'; }

    function doSave() {
        var id = $('bgo-dm-edit-id').value;
        var data = {
            last_name: $('bgo-dm-edit-last-name').value, first_name: $('bgo-dm-edit-first-name').value,
            phone: $('bgo-dm-edit-phone').value, address_1: $('bgo-dm-edit-address').value,
            city: $('bgo-dm-edit-city').value, postcode: $('bgo-dm-edit-postcode').value,
            taiwan_id_number: $('bgo-dm-edit-taiwan-id').value
        };
        var btn = $('bgo-dm-edit-save'); btn.disabled = true; btn.textContent = '儲存中...';
        api('PUT', '/data-management/customers/' + id, data)
            .then(function (resp) {
                var msg = $('bgo-dm-edit-message');
                if (resp.success) {
                    msg.className = 'bgo-dm-message success'; msg.textContent = '儲存成功'; msg.style.display = 'block';
                    setTimeout(function () { closeEditModal(); queryData(); }, 800);
                } else {
                    msg.className = 'bgo-dm-message error'; msg.textContent = resp.message||'儲存失敗'; msg.style.display = 'block';
                }
            })
            .catch(function (e) {
                var msg = $('bgo-dm-edit-message');
                msg.className = 'bgo-dm-message error'; msg.textContent = '請求失敗：' + e.message; msg.style.display = 'block';
            })
            .finally(function () { btn.disabled = false; btn.textContent = '儲存'; });
    }

    document.addEventListener('DOMContentLoaded', init);
})();

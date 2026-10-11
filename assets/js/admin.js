/* Expert Price & Stock Manager - admin script. Data and translations come from wp_localize_script (epmData). */
( function () {
	'use strict';
	var i18n = ( window.epmData && window.epmData.i18n ) || {};
	window.epmT = function ( s ) { return Object.prototype.hasOwnProperty.call( i18n, s ) ? i18n[ s ] : s; };
	window.epmTF = function ( s ) {
		var args = Array.prototype.slice.call( arguments, 1 ), i = 0;
		return window.epmT( s ).replace( /%(?:(\d+)\$)?s/g, function ( m, n ) { return String( n ? args[ n - 1 ] : args[ i++ ] ); } );
	};
	/* Parse an AJAX response; a redirect or HTML answer (another plugin / security rule interfering) becomes a readable error. */
	window.epmJson = function ( r ) {
		return r.text().then( function ( t ) {
			try { return JSON.parse( t ); } catch ( e ) {
				var where = r.redirected && r.url ? r.url : '';
				return { success: false, data: { message: where ? window.epmTF( 'The request was redirected to %s by another plugin or a security rule.', where ) : window.epmT( 'Unexpected server response. Check the browser Network tab or the PHP error log.' ) } };
			}
		} );
	};
} )();

    document.addEventListener('DOMContentLoaded', function () {
        var ajaxUrl = epmData.ajaxUrl;
        var nonce = epmData.nonce;
        var state = { page: 1, perPage: 20, search: '', sort: 'name_asc', category: 0, brand: 0, stock: '' };
        var table = document.getElementById('epm-admin-table');
        var body = document.getElementById('epm-admin-table-body');
        var msg = document.getElementById('epm-admin-message');
        var searchInput = document.getElementById('epm-admin-search');
        var catFilter = document.getElementById('epm-admin-category-filter');
        var brandFilter = document.getElementById('epm-admin-brand-filter');
        var sortSelect = document.getElementById('epm-admin-sort');
        var timer;
        var downloadBase = epmData.downloadBase;
        var exportTemplates = epmData.templates || {};

        function notify(text, cls) {
            msg.textContent = text; msg.className = 'notice ' + cls; msg.style.display = 'block';
            setTimeout(function () { msg.style.display = 'none'; }, 3000);
        }

        /**
         * Every value coming from the server (product name, SKU, prices, variation label...)
         * must be escaped before it is inserted into innerHTML, otherwise HTML/script inside a
         * product title or any other field would run in this admin page (stored XSS).
         */
        /* Inline SVG icons (no icon-font dependency; colour comes from currentColor) */
        function svg(path) {
            return '<svg class="epm-ico" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + path + '</svg>';
        }
        var ICON = {
            refresh: svg('<polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.5 9a9 9 0 0 1 14.9-3.4L23 10M1 14l4.6 4.4A9 9 0 0 0 20.5 15"/>'),
            trash: svg('<polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>')
        };
        var SAVE_BTN = '<button type="button" class="button epm-icon-btn epm-save" title="' + escapeHtml(epmT('Save / update')) + '" aria-label="' + escapeHtml(epmT('Save')) + '">' + ICON.refresh + '</button>';
        var DEL_BTN = '<button type="button" class="button epm-icon-btn epm-delete" title="' + escapeHtml(epmT('Delete product')) + '" aria-label="' + escapeHtml(epmT('Delete product')) + '">' + ICON.trash + '</button>';

        /* "Last updated: DATE by USER" (the user part is omitted for legacy records). */
        function updatedLabel(date, by) {
            return by ? epmTF('Last updated: %1$s by %2$s', date, by) : epmTF('Last updated: %s', date);
        }

        function escapeHtml(value) {
            return String(value === null || value === undefined ? '' : value).replace(/[&<>"']/g, function (ch) {
                return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch];
            });
        }

        /**
         * When "In stock" is unchecked the stock field becomes 0 and is disabled.
         * When it is checked again and the stock is 0 or empty, it becomes 1 and is enabled.
         */
        function syncStockWithCheckbox(row) {
            var checkbox = row.querySelector('.epm-instock');
            var stockInput = row.querySelector('.epm-stock');
            var manageBox = row.querySelector('.epm-manage');
            if (manageBox) row.dataset.manage = manageBox.checked ? '1' : '0';
            if (row.dataset.manage !== '1') {
                // Product without stock management: only the in-stock/out-of-stock status changes.
                stockInput.value = '';
                stockInput.disabled = true;
                stockInput.placeholder = '—';
                return;
            }
            if (checkbox.checked) {
                stockInput.disabled = false;
                if (!stockInput.value || parseFloat(stockInput.value) <= 0) {
                    stockInput.value = '1';
                }
            } else {
                stockInput.value = '0';
                stockInput.disabled = true;
            }
        }

        function wireSaveButton(row, productId) {
            row.querySelector('.epm-instock').addEventListener('change', function () { syncStockWithCheckbox(row); });
            row.querySelector('.epm-manage').addEventListener('change', function () {
                var si = row.querySelector('.epm-stock');
                if (this.checked && !si.value) si.value = row.querySelector('.epm-instock').checked ? '1' : '0';
                syncStockWithCheckbox(row);
            });
            row.addEventListener('input', function () { row.dataset.dirty = '1'; });
            row.querySelector('.epm-save').addEventListener('click', function () {
                var btn = this;
                btn.disabled = true;
                btn.classList.add('is-loading');
                var save = new FormData();
                save.append('action', 'epm_admin_save_price'); save.append('nonce', nonce);
                save.append('product_id', productId);
                save.append('sku', row.querySelector('.epm-sku').value);
                save.append('regular_price', row.querySelector('.epm-regular').value);
                save.append('sale_price', row.querySelector('.epm-sale').value);
                save.append('stock_quantity', row.querySelector('.epm-stock').value);
                save.append('in_stock', row.querySelector('.epm-instock').checked ? '1' : '0');
                save.append('manage_stock', row.querySelector('.epm-manage').checked ? '1' : '0');
                var soldBox = row.querySelector('.epm-single');
                if (soldBox) save.append('sold_individually', soldBox.checked ? '1' : '0');
                fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body: save })
                    .then(window.epmJson)
                    .then(function (res2) {
                        btn.disabled = false;
                        btn.classList.remove('is-loading');
                        if (res2.success) {
                            row.querySelector('.epm-manage').checked = res2.data.manage_stock;
                            if (res2.data.manage_stock) row.querySelector('.epm-stock').value = res2.data.stock_quantity;
                            row.querySelector('.epm-instock').checked = res2.data.in_stock;
                            row.dataset.dirty = '';
                            syncStockWithCheckbox(row);
                            var nameCell = row.querySelector('.epm-name-cell');
                            if (nameCell && res2.data.updated_at) {
                                var upd = nameCell.querySelector('.epm-updated');
                                if (!upd) { upd = document.createElement('div'); upd.className = 'epm-updated'; nameCell.appendChild(upd); }
                                upd.textContent = updatedLabel(res2.data.updated_at, res2.data.updated_by);
                            }
                        }
                        notify(res2.success ? epmT('Saved.') : (res2.data && res2.data.message) || epmT('Error'), res2.success ? 'notice-success' : 'notice-error');
                    })
                    .catch(function () {
                        btn.disabled = false;
                        btn.classList.remove('is-loading');
                        notify(epmT('Server connection error. Please try again.'), 'notice-error');
                    });
            });
        }

        function fieldsRowHtml(item, withDelete, withSingle) {
            return '<td><input type="text" class="epm-sku" value="' + escapeHtml(item.sku || '') + '"></td>' +
                '<td><input type="text" class="epm-regular" value="' + escapeHtml(item.regular_price || '') + '"></td>' +
                '<td><input type="text" class="epm-sale" value="' + escapeHtml(item.sale_price || '') + '"></td>' +
                '<td><input type="checkbox" class="epm-manage" title="' + escapeHtml(epmT('Track stock quantity for this product')) + '" ' + (item.manage_stock ? 'checked' : '') + '></td>' +
                '<td><input type="number" min="0" class="epm-stock" value="' + escapeHtml(item.manage_stock ? item.stock_quantity : '') + '" ' + (item.in_stock && item.manage_stock ? '' : 'disabled') + '></td>' +
                '<td><input type="checkbox" class="epm-instock" ' + (item.in_stock ? 'checked' : '') + '></td>' +
                (withSingle ? '<td><input type="checkbox" class="epm-single" title="' + escapeHtml(epmT('Limit purchases to 1 item per order')) + '" ' + (item.sold_individually ? 'checked' : '') + '></td>' : '') +
                '<td class="epm-actions">' + SAVE_BTN + (withDelete ? DEL_BTN : '') + '</td>';
        }

        function loadVariations(productId, box) {
            box.innerHTML = '<p class="epm-variation-loading"><span class="epm-spinner"></span>' + escapeHtml(epmT('Loading variations...')) + '</p>';
            var fd = new FormData();
            fd.append('action', 'epm_admin_get_variations'); fd.append('nonce', nonce); fd.append('product_id', productId);
            fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body: fd })
                .then(window.epmJson)
                .then(function (res) {
                    if (!res.success || !res.data.items.length) {
                        box.innerHTML = '<p class="epm-variation-loading">' + escapeHtml(epmT('No variations found.')) + '</p>';
                        return;
                    }
                    var tableEl = document.createElement('table');
                    tableEl.className = 'epm-variation-table';
                    tableEl.innerHTML =
                        '<thead><tr><th>' + escapeHtml(epmT('Variation')) + '</th><th>SKU</th><th>' + escapeHtml(epmT('Regular price')) + '</th><th>' + escapeHtml(epmT('Sale price')) + '</th><th>' + escapeHtml(epmT('Manage stock')) + '</th><th>' + escapeHtml(epmT('Stock quantity')) + '</th><th>' + escapeHtml(epmT('In stock')) + '</th><th>' + escapeHtml(epmT('Actions')) + '</th></tr></thead>' +
                        '<tbody></tbody>';
                    var tbody = tableEl.querySelector('tbody');
                    res.data.items.forEach(function (v) {
                        var vr = document.createElement('tr');
                        vr.innerHTML = '<td class="epm-var-name"><div class="epm-product-name">' + escapeHtml(v.label) + '</div>' +
                            '<div class="epm-row-links"><a href="' + escapeHtml(v.view_link) + '" target="_blank" rel="noopener">' + escapeHtml(epmT('View')) + '</a> | ' +
                            '<a href="' + escapeHtml(v.edit_link) + '" target="_blank" rel="noopener">' + escapeHtml(epmT('Edit')) + '</a></div></td>' + fieldsRowHtml(v, false, false);
                        vr.dataset.manage = v.manage_stock ? '1' : '0';
                        tbody.appendChild(vr);
                        syncStockWithCheckbox(vr);
                        wireSaveButton(vr, v.id);
                    });
                    box.innerHTML = '';
                    box.appendChild(tableEl);
                })
                .catch(function () { box.innerHTML = '<p class="epm-variation-loading">' + escapeHtml(epmT('Error loading variations.')) + '</p>'; });
        }

        var pagination = document.getElementById('epm-admin-pagination');

        function renderPagination(total, perPage) {
            pagination.innerHTML = '';
            var totalPages = Math.max(1, Math.ceil(total / perPage));
            if (totalPages <= 1) return;
            if (state.page > totalPages) state.page = totalPages;

            var wrap = document.createElement('div');
            wrap.className = 'epm-pagination';

            var prev = document.createElement('button');
            prev.type = 'button'; prev.className = 'button'; prev.textContent = epmT('Previous');
            prev.disabled = state.page <= 1;
            prev.addEventListener('click', function () { state.page -= 1; load(); });

            var info = document.createElement('span');
            info.className = 'epm-pagination-info';
            info.textContent = epmTF('Page %1$s of %2$s (%3$s products)', state.page, totalPages, total);

            var next = document.createElement('button');
            next.type = 'button'; next.className = 'button'; next.textContent = epmT('Next');
            next.disabled = state.page >= totalPages;
            next.addEventListener('click', function () { state.page += 1; load(); });

            wrap.appendChild(prev); wrap.appendChild(info); wrap.appendChild(next);
            pagination.appendChild(wrap);
        }

        /* Animated placeholder rows + a slim progress bar while products load. */
        var loadWrap = body.closest('table').parentElement;
        function skeletonRows() {
            var n = Math.max(3, Math.min(state.perPage, 8)), html = '';
            for (var r = 0; r < n; r++) {
                html += '<tr class="epm-skel-row"><td><span class="epm-skel epm-skel-w80"></span><span class="epm-skel epm-skel-w50"></span></td>';
                for (var c = 1; c < 8; c++) html += '<td><span class="epm-skel epm-skel-w60"></span></td>';
                html += '<td><span class="epm-skel epm-skel-w40"></span></td></tr>';
            }
            return html;
        }
        function setLoading(on) { loadWrap.classList.toggle('epm-is-loading', !!on); }
        function load() {
            setLoading(true);
            body.innerHTML = skeletonRows();
            pagination.innerHTML = '';
            var fd = new FormData();
            fd.append('action', 'epm_admin_get_products'); fd.append('nonce', nonce);
            fd.append('page', state.page); fd.append('per_page', state.perPage);
            fd.append('search', state.search); fd.append('sort', state.sort);
            fd.append('category_id', state.category); fd.append('brand_id', state.brand); fd.append('stock_status', state.stock);
            fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body: fd })
                .then(window.epmJson)
                .then(function (res) {
                    setLoading(false);
                    if (!res.success) { body.innerHTML = '<tr><td colspan="9">' + escapeHtml(epmT('Error loading products.')) + '</td></tr>'; return; }
                    var items = res.data.items;
                    if (!items.length) { body.innerHTML = '<tr><td colspan="9">' + escapeHtml(epmT('No products found.')) + '</td></tr>'; renderPagination(res.data.total, res.data.per_page); return; }
                    body.innerHTML = '';
                    items.forEach(function (item) {
                        var tr = document.createElement('tr'); tr.className = 'epm-row-in';
                        var nameCell =
                            '<td class="epm-name-cell">' +
                                '<div class="epm-product-name">' + escapeHtml(item.name) + '</div>' +
                                '<div class="epm-row-links">' +
                                    '<a href="' + escapeHtml(item.view_link) + '" target="_blank" rel="noopener">' + escapeHtml(epmT('View')) + '</a>' +
                                    ' | ' +
                                    '<a href="' + escapeHtml(item.edit_link) + '" target="_blank" rel="noopener">' + escapeHtml(epmT('Edit')) + '</a>' +
                                    (item.redirect_link ? ' | <a href="' + escapeHtml(item.redirect_link) + '" target="_blank" rel="noopener" title="' + escapeHtml(epmT('Add a redirect in Rank Math')) + '">' + escapeHtml(epmT('Redirect')) + '</a>' : '') +
                                    (item.is_variable ? ' | <button type="button" class="link-button epm-toggle-variations">' + escapeHtml(epmTF('%s variations', item.variation_count)) + ' &#9660;</button>' : '') +
                                '</div>' +
                                (item.price_updated ? '<div class="epm-updated">' + escapeHtml(updatedLabel(item.price_updated, item.updated_by)) + '</div>' : '') +
                            '</td>';

                        if (!item.editable) {
                            tr.innerHTML = nameCell +
                                '<td colspan="7" class="epm-variable-placeholder">' + escapeHtml(epmTF('The price of this product type (%s) cannot be edited from this panel.', item.type)) + '</td>' +
                                '<td class="epm-actions">' + (item.can_delete ? DEL_BTN : '') + '</td>';
                        } else if (item.is_variable) {
                            tr.innerHTML = nameCell +
                                '<td colspan="7" class="epm-variable-placeholder">' + escapeHtml(epmT('This product has several variations; set the price and stock of each variation in the box below.')) + '</td>' +
                                '<td class="epm-actions">' + (item.can_delete ? DEL_BTN : '') + '</td>';
                        } else {
                            tr.innerHTML = nameCell + fieldsRowHtml(item, item.can_delete, true);
                            tr.dataset.manage = item.manage_stock ? '1' : '0';
                        }
                        tr.dataset.pid = item.id;
                        body.appendChild(tr);
                        var delBtn = tr.querySelector('.epm-delete');
                        if (delBtn) delBtn.addEventListener('click', function () { openDeleteModal(item, tr); });

                        if (item.is_variable) {
                            var variationRow = document.createElement('tr');
                            variationRow.className = 'epm-variation-row';
                            variationRow.style.display = 'none';
                            var td = document.createElement('td');
                            td.colSpan = 9;
                            var box = document.createElement('div');
                            box.className = 'epm-variation-box';
                            td.appendChild(box);
                            variationRow.appendChild(td);
                            body.appendChild(variationRow);

                            var loaded = false;
                            tr.querySelector('.epm-toggle-variations').addEventListener('click', function () {
                                var isHidden = variationRow.style.display === 'none';
                                variationRow.style.display = isHidden ? '' : 'none';
                                if (isHidden && !loaded) {
                                    loaded = true;
                                    loadVariations(item.id, box);
                                }
                            });
                        } else if (item.editable) {
                            syncStockWithCheckbox(tr);
                            wireSaveButton(tr, item.id);
                        }
                    });
                    renderPagination(res.data.total, res.data.per_page);
                })
                .catch(function () { setLoading(false); body.innerHTML = '<tr><td colspan="9">' + escapeHtml(epmT('Server connection error.')) + '</td></tr>'; });
        }

        /* ---------------- Live refresh of "last updated" and stock (orders, other admins) ---------------- */
        function pollRows() {
            if (document.hidden) { return; } /* no background requests while the tab is not visible */
            if (document.hidden) return;
            var rows = body.querySelectorAll('tr[data-pid]');
            if (!rows.length) return;
            var fd = new FormData();
            fd.append('action', 'epm_admin_poll'); fd.append('nonce', nonce);
            rows.forEach(function (r) { fd.append('ids[]', r.dataset.pid); });
            fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body: fd })
                .then(window.epmJson)
                .then(function (res) {
                    if (!res.success) return;
                    rows.forEach(function (row) {
                        var d = res.data[row.dataset.pid];
                        if (!d) return;
                        var cell = row.querySelector('.epm-name-cell');
                        if (cell && d.updated_at) {
                            var upd = cell.querySelector('.epm-updated');
                            if (!upd) { upd = document.createElement('div'); upd.className = 'epm-updated'; cell.appendChild(upd); }
                            var txt = updatedLabel(d.updated_at, d.updated_by);
                            if (upd.textContent !== txt) upd.textContent = txt;
                        }
                        // Do not overwrite fields the user is editing.
                        if (row.querySelector('.epm-stock') && row.dataset.dirty !== '1') {
                            row.querySelector('.epm-manage').checked = d.manage_stock;
                            if (d.manage_stock) row.querySelector('.epm-stock').value = d.stock_quantity;
                            row.querySelector('.epm-instock').checked = d.in_stock;
                            syncStockWithCheckbox(row);
                        }
                    });
                })
                .catch(function () {});
        }
        setInterval(pollRows, 20000);

        /* ---------------- Delete product ---------------- */
        var delModal = document.getElementById('epm-delete-modal');
        var delUrl = document.getElementById('epm-delete-url');
        var delWarn = document.getElementById('epm-delete-warning');
        var delCtx = null;

        var typesBox = document.getElementById('epm-redirect-types');
        var confirmBtn = document.getElementById('epm-delete-confirm');
        function wantsRedirect() { return delModal.querySelector('input[name="epm-want-redirect"]:checked').value === 'yes'; }
        function selectedDeleteMode() {
            return wantsRedirect() ? delModal.querySelector('input[name="epm-delete-mode"]:checked').value : 'trash';
        }
        function refreshDeleteForm() {
            var yes = wantsRedirect();
            typesBox.style.display = yes ? '' : 'none';
            delUrl.style.display = (yes && selectedDeleteMode() === 'redirect_url') ? '' : 'none';
            var labels = { trash: epmT('Move to trash'), gone_410: epmT('Delete permanently + set 410'), redirect_cat: epmT('Delete permanently + 301 to category'), redirect_url: epmT('Delete permanently + 301 to destination') };
            confirmBtn.textContent = labels[selectedDeleteMode()];
        }
        delModal.querySelectorAll('input[name="epm-delete-mode"], input[name="epm-want-redirect"]').forEach(function (r) { r.addEventListener('change', refreshDeleteForm); });

        function openDeleteModal(item, tr) {
            delCtx = { id: item.id, tr: tr };
            document.getElementById('epm-delete-name').textContent = item.name;
            document.getElementById('epm-delete-info').textContent = epmT('Checking...');
            delWarn.style.display = 'none'; delUrl.value = '';
            delModal.querySelectorAll('input[name="epm-want-redirect"]').forEach(function (r) { r.disabled = false; });
            delModal.querySelector('input[name="epm-want-redirect"][value="no"]').checked = true;
            delModal.querySelector('input[name="epm-delete-mode"][value="gone_410"]').checked = true;
            refreshDeleteForm();
            delModal.style.display = 'flex';
            var fd = new FormData();
            fd.append('action', 'epm_admin_delete_preflight'); fd.append('nonce', nonce); fd.append('product_id', item.id);
            fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body: fd })
                .then(window.epmJson)
                .then(function (res) {
                    var info = document.getElementById('epm-delete-info');
                    if (!res.success) { info.textContent = (res.data && res.data.message) || epmT('Error'); return; }
                    info.textContent = epmTF('URL: %s', res.data.url) + (res.data.rankmath ? '' : epmT(' — The Rank Math Redirections module is not active; only moving to the trash (without a redirect) is possible.'));
                    if (res.data.orders) {
                        delWarn.textContent = epmTF('This product appears in %s existing orders. Deleting it permanently breaks the product link in past orders and reports.', res.data.orders);
                        delWarn.style.display = '';
                    }
                    if (!res.data.rankmath) {
                        delModal.querySelector('input[name="epm-want-redirect"][value="yes"]').disabled = true;
                    }
                })
                .catch(function () { document.getElementById('epm-delete-info').textContent = epmT('Server connection error.'); });
        }
        document.getElementById('epm-delete-cancel').addEventListener('click', function () { delModal.style.display = 'none'; delCtx = null; });
        document.getElementById('epm-delete-confirm').addEventListener('click', function () {
            if (!delCtx) return;
            var btn = this, mode = selectedDeleteMode();
            if (mode === 'redirect_url' && !delUrl.value.trim()) { notify(epmT('Enter the redirect destination URL.'), 'notice-error'); return; }
            if (mode === 'redirect_url' && !/^https?:\/\//i.test(delUrl.value.trim())) { notify(epmT('The destination URL must start with http:// or https://.'), 'notice-error'); return; }
            if (mode !== 'trash' && !window.confirm(epmT('The product will be deleted permanently and cannot be restored. Continue?'))) return;
            btn.disabled = true;
            var fd = new FormData();
            fd.append('action', 'epm_admin_delete_product'); fd.append('nonce', nonce);
            fd.append('product_id', delCtx.id); fd.append('mode', mode); fd.append('url_to', delUrl.value.trim());
            fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body: fd })
                .then(window.epmJson)
                .then(function (res) {
                    btn.disabled = false;
                    if (res.success) {
                        var tr = delCtx.tr, next = tr.nextElementSibling;
                        if (next && next.classList.contains('epm-variation-row')) next.remove();
                        tr.remove();
                        delModal.style.display = 'none'; delCtx = null;
                        notify(res.data.message + (res.data.redirect ? ' (' + res.data.redirect + ')' : ''), 'notice-success');
                    } else {
                        notify((res.data && res.data.message) || epmT('Error'), 'notice-error');
                    }
                })
                .catch(function () { btn.disabled = false; notify(epmT('Server connection error.'), 'notice-error'); });
        });

        /* ---------------- XLSX export ---------------- */
        var exportPanel = document.getElementById('epm-export-panel');
        var colList = document.getElementById('epm-export-cols');
        var tplSelect = document.getElementById('epm-template-select');
        var exStatus = document.getElementById('epm-export-status');
        var exBar = document.getElementById('epm-export-bar');
        var exProgress = document.getElementById('epm-export-progress');
        var exResult = document.getElementById('epm-export-result');

        document.getElementById('epm-export-toggle').addEventListener('click', function () {
            exportPanel.style.display = exportPanel.style.display === 'none' ? '' : 'none';
        });

        colList.addEventListener('click', function (e) {
            var li = e.target.closest('li');
            if (!li) return;
            if (e.target.classList.contains('epm-col-up') && li.previousElementSibling) colList.insertBefore(li, li.previousElementSibling);
            if (e.target.classList.contains('epm-col-down') && li.nextElementSibling) colList.insertBefore(li.nextElementSibling, li);
        });

        function checkedColumns() {
            var out = [];
            colList.querySelectorAll('li').forEach(function (li) {
                if (li.querySelector('.epm-col-check').checked) out.push(li.dataset.key);
            });
            return out;
        }

        function renderTemplates() {
            tplSelect.innerHTML = '<option value="">' + escapeHtml(epmT('— Select a template —')) + '</option>';
            Object.keys(exportTemplates).forEach(function (name) {
                var o = document.createElement('option'); o.value = name; o.textContent = name; tplSelect.appendChild(o);
            });
        }
        renderTemplates();

        tplSelect.addEventListener('change', function () {
            var t = exportTemplates[tplSelect.value];
            if (!t) return;
            var order = t.columns || [];
            colList.querySelectorAll('.epm-col-check').forEach(function (c) { c.checked = false; });
            for (var i = order.length - 1; i >= 0; i--) {
                var li = colList.querySelector('li[data-key="' + order[i] + '"]');
                if (li) { li.querySelector('.epm-col-check').checked = true; colList.insertBefore(li, colList.firstChild); }
            }
            document.getElementById('epm-export-meta').value = t.meta_keys || '';
        });

        function postForm(fd) {
            return fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body: fd }).then(window.epmJson);
        }

        document.getElementById('epm-template-save').addEventListener('click', function () {
            var name = document.getElementById('epm-template-name').value.trim();
            if (!name) { notify(epmT('Enter a template name.'), 'notice-error'); return; }
            var fd = new FormData();
            fd.append('action', 'epm_export_save_template'); fd.append('nonce', nonce); fd.append('name', name);
            checkedColumns().forEach(function (c) { fd.append('columns[]', c); });
            fd.append('meta_keys', document.getElementById('epm-export-meta').value);
            postForm(fd).then(function (res) {
                if (res.success) { exportTemplates = res.data.templates || {}; renderTemplates(); tplSelect.value = name; notify(epmT('Template saved.'), 'notice-success'); }
                else notify((res.data && res.data.message) || epmT('Error'), 'notice-error');
            }).catch(function () { notify(epmT('Server connection error.'), 'notice-error'); });
        });

        document.getElementById('epm-template-delete').addEventListener('click', function () {
            if (!tplSelect.value) return;
            var fd = new FormData();
            fd.append('action', 'epm_export_delete_template'); fd.append('nonce', nonce); fd.append('name', tplSelect.value);
            postForm(fd).then(function (res) {
                if (res.success) { exportTemplates = res.data.templates || {}; renderTemplates(); notify(epmT('Template deleted.'), 'notice-success'); }
            }).catch(function () { notify(epmT('Server connection error.'), 'notice-error'); });
        });

        document.getElementById('epm-export-start').addEventListener('click', function () {
            var btn = this;
            var cols = checkedColumns();
            var meta = document.getElementById('epm-export-meta').value;
            if (!cols.length && !meta.trim()) { notify(epmT('Select at least one column.'), 'notice-error'); return; }
            btn.disabled = true; exResult.style.display = 'none'; exProgress.style.display = ''; exBar.style.width = '0%';
            exStatus.textContent = epmT('Preparing...');
            var fd = new FormData();
            fd.append('action', 'epm_export_start'); fd.append('nonce', nonce);
            cols.forEach(function (c) { fd.append('columns[]', c); });
            fd.append('meta_keys', meta);
            exportPanel.querySelectorAll('.epm-status-check:checked').forEach(function (c) { fd.append('statuses[]', c.value); });
            fd.append('stock_filter', document.getElementById('epm-export-stock').value || state.stock);
            fd.append('extra_filter', document.getElementById('epm-export-extra').value);
            fd.append('with_variations', document.getElementById('epm-export-variations').checked ? '1' : '0');
            fd.append('search', state.search); fd.append('category_id', state.category); fd.append('brand_id', state.brand);

            function fail(message) { btn.disabled = false; exProgress.style.display = 'none'; exStatus.textContent = ''; notify(message || epmT('Error building the export.'), 'notice-error'); }

            postForm(fd).then(function (res) {
                if (!res.success) { fail(res.data && res.data.message); return; }
                var token = res.data.token, total = res.data.total;
                if (!total) { fail(epmT('No products found for these filters.')); return; }
                function step() {
                    var b = new FormData();
                    b.append('action', 'epm_export_batch'); b.append('nonce', nonce); b.append('token', token);
                    postForm(b).then(function (r) {
                        if (!r.success) { fail(r.data && r.data.message); return; }
                        var pct = r.data.pages ? Math.min(100, Math.round(r.data.page / r.data.pages * 100)) : 100;
                        exBar.style.width = pct + '%';
                        exStatus.textContent = epmTF('%1$s rows out of %2$s products', r.data.rows, total);
                        if (r.data.done) {
                            btn.disabled = false;
                            exResult.style.display = '';
                            exResult.innerHTML = '';
                            var a = document.createElement('a');
                            a.className = 'button button-primary';
                            a.href = downloadBase + '&token=' + encodeURIComponent(token);
                            a.textContent = epmTF('Download XLSX file (%s rows)', r.data.rows);
                            exResult.appendChild(a);
                        } else { step(); }
                    }).catch(function () { fail(epmT('Server connection error.')); });
                }
                step();
            }).catch(function () { fail(epmT('Server connection error.')); });
        });

        searchInput.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(function () { state.search = searchInput.value; state.page = 1; load(); }, 400); });
        catFilter.addEventListener('change', function () { state.category = parseInt(catFilter.value, 10) || 0; state.page = 1; load(); });
        if (brandFilter) brandFilter.addEventListener('change', function () { state.brand = parseInt(brandFilter.value, 10) || 0; state.page = 1; load(); });
        var perSelect = document.getElementById('epm-admin-perpage');
        if (perSelect) {
            try { var sp = parseInt(localStorage.getItem('epm_admin_per_page'), 10); if (sp && perSelect.querySelector('option[value="' + sp + '"]')) { perSelect.value = sp; state.perPage = sp; } } catch (e) {}
            perSelect.addEventListener('change', function () { state.perPage = parseInt(perSelect.value, 10) || 20; state.page = 1; try { localStorage.setItem('epm_admin_per_page', state.perPage); } catch (e) {} load(); });
        }
        var stockFilter = document.getElementById('epm-admin-stock');
        if (stockFilter) stockFilter.addEventListener('change', function () { state.stock = stockFilter.value; state.page = 1; load(); });
        sortSelect.addEventListener('change', function () { state.sort = sortSelect.value; state.page = 1; load(); });

        /* ---- Resizable columns, widths saved in the browser ---- */
        var STORAGE_KEY = 'epm_admin_col_widths_v8';
        var cols = table.querySelectorAll('colgroup col');
        try {
            var saved = JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}');
            cols.forEach(function (col) {
                var key = col.dataset.colKey;
                if (saved[key]) col.style.width = saved[key] + 'px';
            });
        } catch (e) {}

        function saveWidths() {
            var widths = {};
            cols.forEach(function (col) { widths[col.dataset.colKey] = parseInt(col.style.width, 10) || 0; });
            try { localStorage.setItem(STORAGE_KEY, JSON.stringify(widths)); } catch (e) {}
        }

        /* The table is as wide as its columns (never narrower than its container), so enlarging one column
           does not squeeze the others; the wrapper scrolls horizontally when needed. */
        function fitTable() {
            var total = 0;
            cols.forEach(function (col) { total += parseInt(col.style.width, 10) || col.getBoundingClientRect().width; });
            table.style.width = total + 'px';
            table.style.minWidth = '100%';
        }
        fitTable();
        var isRtl = getComputedStyle(table).direction === 'rtl';

        table.querySelectorAll('th .epm-resizer').forEach(function (handle) {
            handle.addEventListener('pointerdown', function (e) {
                e.preventDefault();
                e.stopPropagation();
                var th = handle.closest('th');
                var col = table.querySelector('colgroup col[data-col-key="' + th.dataset.colKey + '"]');
                var startX = e.clientX;
                var startWidth = col.getBoundingClientRect().width;
                handle.setPointerCapture(e.pointerId);
                document.body.classList.add('epm-resizing');
                function onMove(ev) {
                    var delta = ev.clientX - startX;
                    if (isRtl) delta = -delta;
                    col.style.width = Math.max(40, Math.round(startWidth + delta)) + 'px';
                    fitTable();
                }
                function onUp() {
                    handle.removeEventListener('pointermove', onMove);
                    handle.removeEventListener('pointerup', onUp);
                    handle.removeEventListener('pointercancel', onUp);
                    document.body.classList.remove('epm-resizing');
                    saveWidths();
                }
                handle.addEventListener('pointermove', onMove);
                handle.addEventListener('pointerup', onUp);
                handle.addEventListener('pointercancel', onUp);
            });
        });

        load();
    });

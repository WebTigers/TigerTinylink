// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * tinylink.pages-edit.js — the links-page editor.
 *
 * Loads the page + blocks (tinylink/page/get), saves the page (page/save), adds/edits blocks in a modal
 * (block/save), shows/hides and deletes them (block/setStatus|delete — delete via TigerModal), and
 * persists drag-to-reorder (block/reorder) with SortableJS. Images come from TigerMediaPicker. Stats from
 * page/stats; the preview iframe is the real public page, reloaded after each save. Every call carries the
 * screen's one CSRF token.
 */
document.addEventListener('DOMContentLoaded', function () {
    var root = document.getElementById('tlp-root');
    if (!root) { return; }
    var t = Tiger.t, pageId = root.getAttribute('data-page-id'), site = root.getAttribute('data-site');
    var form = document.getElementById('tlp-form'), fb = document.getElementById('tlp-feedback');
    var list = document.getElementById('tlp-items'), preview = document.getElementById('tlp-preview');
    var itemForm = document.getElementById('tlp-item-form'), ifb = document.getElementById('tlp-item-feedback');
    var itemModal = new bootstrap.Modal(document.getElementById('tlp-item-modal'));
    var state = { page: null, items: [], caps: {} }, products = null;

    function csrf() { var el = form.querySelector('[name="_csrf"]'); return el ? el.value : ''; }
    function api(service, method, params) {
        var body = new URLSearchParams({ module: 'tinylink', service: service, method: method, _csrf: csrf() });
        Object.keys(params || {}).forEach(function (k) {
            var v = params[k];
            if (Array.isArray(v)) { v.forEach(function (x) { body.append(k + '[]', x); }); }
            else if (v && typeof v === 'object') { Object.keys(v).forEach(function (sk) { body.append(k + '[' + sk + ']', v[sk]); }); }
            else { body.append(k, v == null ? '' : v); }
        });
        return fetch('/api', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: body })
            .then(function (r) { return r.json().catch(function () { return { result: 0 }; }); });
    }
    function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }
    function notify(el, res) {
        (res && res.messages || []).forEach(function (m) { TigerDOM.notify(el, m.message, { type: m.class || 'error' }); });
        Object.keys((res && res.form) || {}).forEach(function (f) {
            var m = res.form[f];
            (m && typeof m === 'object' ? Object.keys(m).map(function (k) { return m[k]; }) : [m]).forEach(function (x) { if (x) { TigerDOM.notify(el, String(x), { type: 'error' }); } });
        });
    }
    function setImg(img, idInput, id, url) {
        idInput.value = id || '';
        img.src = url || '';
        img.classList.toggle('d-none', !url);
    }
    function pick(cb) {
        if (typeof TigerMediaPicker === 'undefined') { return; }
        TigerMediaPicker.open({ kind: 'image', multiple: false, onSelect: function (items) {
            var m = items && items[0];
            if (m) { cb(m.media_id, m.url || m.thumb || ('/media/file/serve/id/' + m.media_id)); }
        } });
    }
    function reloadPreview() { if (state.page) { preview.src = state.page.path + '?preview=' + Date.now(); } }

    // ---- page -----------------------------------------------------------------------------------
    function fillPage(p) {
        state.page = p;
        document.getElementById('tlp-heading').textContent = p.title;
        document.getElementById('tlp-url').textContent = site + p.path;
        document.getElementById('tlp-open').href = p.path;
        form.title.value = p.title; form.handle.value = p.handle; form.bio.value = p.bio;
        form.layout.value = p.layout; form.background.value = p.background; form.seo_description.value = p.seo_description;
        document.getElementById('tlp-default').checked = p.is_default;
        document.getElementById('tlp-active').checked = p.status === 'active';
        setImg(document.getElementById('tlp-avatar'), document.getElementById('tlp-avatar-id'), p.avatar_media_id, p.avatar_url);
        setImg(document.getElementById('tlp-og'), document.getElementById('tlp-og-id'), p.og_image_media_id, '');
        document.querySelectorAll('[data-social]').forEach(function (inp) { inp.value = (p.social || {})[inp.getAttribute('data-social')] || ''; });
    }
    function load() {
        return api('page', 'get', { page_id: pageId }).then(function (res) {
            if (!res || res.result !== 1) { notify(fb, res); return; }
            fillPage(res.data.page);
            state.items = res.data.items; state.caps = res.data.capabilities;
            renderItems();
            reloadPreview();
        });
    }
    document.getElementById('tlp-save').addEventListener('click', function () {
        var social = {};
        document.querySelectorAll('[data-social]').forEach(function (inp) { social[inp.getAttribute('data-social')] = inp.value.trim(); });
        var p = {
            page_id: pageId, title: form.title.value, handle: form.handle.value, bio: form.bio.value,
            layout: form.layout.value, background: form.background.value, seo_description: form.seo_description.value,
            avatar_media_id: document.getElementById('tlp-avatar-id').value, og_image_media_id: document.getElementById('tlp-og-id').value,
            status: document.getElementById('tlp-active').checked ? 'active' : 'disabled', social: social
        };
        if (document.getElementById('tlp-default').checked) { p.is_default = '1'; }
        TigerButton.run(this, function () { return api('page', 'save', p); }).then(function (res) {
            if (res && res.result === 1) { fillPage(res.data.page); TigerDOM.notify(fb, t('saved'), { type: 'success' }); reloadPreview(); return; }
            notify(fb, res);
        }).catch(function () { TigerDOM.notify(fb, t('netError'), { type: 'error' }); });
    });
    document.getElementById('tlp-avatar-pick').addEventListener('click', function () { pick(function (id, url) { setImg(document.getElementById('tlp-avatar'), document.getElementById('tlp-avatar-id'), id, url); }); });
    document.getElementById('tlp-avatar-clear').addEventListener('click', function () { setImg(document.getElementById('tlp-avatar'), document.getElementById('tlp-avatar-id'), '', ''); });
    document.getElementById('tlp-og-pick').addEventListener('click', function () { pick(function (id, url) { setImg(document.getElementById('tlp-og'), document.getElementById('tlp-og-id'), id, url); }); });
    document.getElementById('tlp-og-clear').addEventListener('click', function () { setImg(document.getElementById('tlp-og'), document.getElementById('tlp-og-id'), '', ''); });

    // ---- blocks ---------------------------------------------------------------------------------
    var KIND_ICON = { link: 'fa-link', header: 'fa-heading', divider: 'fa-minus', signup: 'fa-envelope-open-text', product: 'fa-bag-shopping' };
    function renderItems() {
        if (!state.items.length) { list.innerHTML = '<li class="list-group-item text-body-secondary text-center py-4">' + esc(t('empty')) + '</li>'; return; }
        list.innerHTML = state.items.map(function (it) {
            var title = it.kind === 'divider' ? '—' : (it.label || t('kind_' + it.kind));
            var sub = it.kind === 'link' ? (it.short_path ? it.short_path + ' → ' + it.destination : it.destination) : (it.kind === 'product' ? it.ref : t('kind_' + it.kind));
            var badges = (it.status !== 'active' ? ' <span class="badge text-bg-secondary">' + esc(t('hidden')) + '</span>' : '')
                + ((it.show_from || it.show_until) ? ' <span class="badge text-bg-info-subtle text-info-emphasis">' + esc(t('scheduled')) + '</span>' : '')
                + (it.featured ? ' <span class="badge text-bg-warning-subtle text-warning-emphasis">' + esc(t('featured')) + '</span>' : '');
            var on = it.status === 'active';
            return '<li class="list-group-item d-flex align-items-center gap-2' + (on ? '' : ' opacity-50') + '" data-id="' + esc(it.item_id) + '">'
                + '<span class="tlp-handle text-body-secondary px-1" role="button" aria-label="Drag"><i class="fa-solid fa-grip-vertical"></i></span>'
                + '<i class="fa-solid ' + KIND_ICON[it.kind] + ' text-body-secondary" aria-hidden="true"></i>'
                + '<div class="flex-grow-1 min-w-0"><div class="fw-semibold text-truncate">' + esc(title) + badges + '</div>'
                + '<div class="small text-body-secondary text-truncate">' + esc(sub) + '</div></div>'
                + '<div class="text-nowrap">'
                + (it.kind !== 'divider' ? '<button type="button" class="btn btn-sm btn-outline-secondary" data-act="edit" aria-label="' + esc(t('edit')) + '"><i class="fa-solid fa-pen"></i></button> ' : '')
                + '<button type="button" class="btn btn-sm btn-outline-secondary" data-act="toggle" aria-label="' + esc(on ? t('hide') : t('show')) + '"><i class="fa-solid ' + (on ? 'fa-eye' : 'fa-eye-slash') + '"></i></button> '
                + '<button type="button" class="btn btn-sm btn-outline-danger" data-act="delete" aria-label="' + esc(t('del')) + '"><i class="fa-solid fa-trash"></i></button>'
                + '</div></li>';
        }).join('');
    }
    if (typeof Sortable !== 'undefined') {
        Sortable.create(list, { handle: '.tlp-handle', animation: 150, onEnd: function () {
            var ids = Array.prototype.map.call(list.querySelectorAll('[data-id]'), function (li) { return li.getAttribute('data-id'); });
            api('block', 'reorder', { page_id: pageId, item_ids: ids.join(',') }).then(function (res) {
                if (!res || res.result !== 1) { notify(fb, res); return load(); }
                state.items.sort(function (a, b) { return ids.indexOf(a.item_id) - ids.indexOf(b.item_id); });
                reloadPreview();
            });
        } });
    }

    function loadProducts() {
        if (products) { return Promise.resolve(products); }
        return fetch('/api', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: new URLSearchParams({ module: 'shop', service: 'product', method: 'catalog' }) })
            .then(function (r) { return r.json(); }).then(function (res) { products = (res && res.data && res.data.products) || []; return products; })
            .catch(function () { products = []; return products; });
    }
    function openItem(kind, it) {
        itemForm.reset(); ifb.innerHTML = '';
        itemForm.item_id.value = it ? it.item_id : '';
        itemForm.kind.value = kind;
        document.getElementById('tlp-item-title').textContent = (it ? t('editTitle') : t('addTitle')) + ' — ' + t('kind_' + kind);
        itemForm.querySelectorAll('[data-for]').forEach(function (el) { el.classList.toggle('d-none', el.getAttribute('data-for').split(' ').indexOf(kind) < 0); });
        itemForm.label.value = it ? it.label : '';
        itemForm.destination.value = it ? it.destination : '';
        document.getElementById('tlp-item-short').textContent = it && it.short_path ? site + it.short_path : '';
        itemForm.icon.value = it ? it.icon : '';
        document.getElementById('tlp-featured').checked = !!(it && it.featured);
        itemForm.show_from.value = it && it.show_from ? it.show_from.replace(' ', 'T').substring(0, 16) : '';
        itemForm.show_until.value = it && it.show_until ? it.show_until.replace(' ', 'T').substring(0, 16) : '';
        setImg(document.getElementById('tlp-thumb'), document.getElementById('tlp-thumb-id'), it ? it.thumbnail_media_id : '', it ? it.thumbnail_url : '');
        if (kind === 'product') {
            loadProducts().then(function (ps) {
                var sel = document.getElementById('tlp-item-product');
                sel.innerHTML = ps.map(function (p) { return '<option value="' + esc(p.slug) + '">' + esc(p.title) + '</option>'; }).join('');
                if (it) { sel.value = it.ref; }
            });
        }
        itemModal.show();
    }
    document.querySelectorAll('[data-tlp-add]').forEach(function (b) {
        b.addEventListener('click', function () {
            var kind = b.getAttribute('data-tlp-add');
            if (kind === 'divider') { saveItem({ page_id: pageId, kind: 'divider' }); return; }
            openItem(kind, null);
        });
    });
    document.getElementById('tlp-thumb-pick').addEventListener('click', function () { pick(function (id, url) { setImg(document.getElementById('tlp-thumb'), document.getElementById('tlp-thumb-id'), id, url); }); });

    function saveItem(p, btn) {
        var run = btn ? TigerButton.run.bind(TigerButton, btn) : function (task) { return task(); };
        return run(function () { return api('block', 'save', p); }).then(function (res) {
            if (res && res.result === 1) { itemModal.hide(); TigerDOM.notify(fb, t('itemSaved'), { type: 'success' }); return load(); }
            notify(btn ? ifb : fb, res);
        }).catch(function () { TigerDOM.notify(btn ? ifb : fb, t('netError'), { type: 'error' }); });
    }
    document.getElementById('tlp-item-save').addEventListener('click', function () {
        var kind = itemForm.kind.value, p = { kind: kind, label: itemForm.label.value, show_from: itemForm.show_from.value, show_until: itemForm.show_until.value };
        if (itemForm.item_id.value) { p.item_id = itemForm.item_id.value; delete p.kind; } else { p.page_id = pageId; }
        if (kind === 'link') {
            p.destination = itemForm.destination.value; p.icon = itemForm.icon.value;
            p.thumbnail_media_id = document.getElementById('tlp-thumb-id').value;
            p.featured = document.getElementById('tlp-featured').checked ? '1' : '0';
        }
        if (kind === 'product') { p.ref = document.getElementById('tlp-item-product').value; }
        saveItem(p, this);
    });

    list.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-act]');
        if (!btn) { return; }
        var id = btn.closest('[data-id]').getAttribute('data-id');
        var it = state.items.filter(function (x) { return x.item_id === id; })[0];
        if (!it) { return; }
        var act = btn.getAttribute('data-act');
        if (act === 'edit') { openItem(it.kind, it); return; }
        if (act === 'toggle') {
            TigerButton.run(btn, function () { return api('block', 'setStatus', { item_id: id, status: it.status === 'active' ? 'disabled' : 'active' }); })
                .then(function (res) { if (!res || res.result !== 1) { notify(fb, res); } load(); });
            return;
        }
        TigerModal.confirm({ title: t('del'), body: t('confirmItem'), confirmLabel: t('del'), variant: 'danger' }).then(function (ok) {
            if (!ok) { return; }
            TigerButton.run(btn, function () { return api('block', 'delete', { item_id: id }); }).then(function (res) {
                if (res && res.result === 1) { TigerDOM.notify(fb, t('itemDeleted'), { type: 'success' }); } else { notify(fb, res); }
                load();
            });
        });
    });

    // ---- stats ----------------------------------------------------------------------------------
    api('page', 'stats', { page_id: pageId, days: 30 }).then(function (res) {
        if (!res || res.result !== 1) { return; }
        var s = res.data.stats;
        document.getElementById('tlp-s-views').textContent = s.views;
        document.getElementById('tlp-s-taps').textContent = s.taps;
        document.getElementById('tlp-s-rate').textContent = s.tap_rate + '%';
        document.getElementById('tlp-s-items').innerHTML = s.items.length ? s.items.map(function (x) {
            return '<li class="list-group-item d-flex justify-content-between px-0"><span class="text-truncate">' + esc(x.label) + '</span><span class="text-body-secondary">' + esc(x.taps) + '</span></li>';
        }).join('') : '<li class="list-group-item px-0 text-body-secondary">—</li>';
        document.getElementById('tlp-s-refs').innerHTML = s.referrers.length ? s.referrers.map(function (x) {
            return '<li class="list-group-item d-flex justify-content-between px-0"><span>' + esc(x.host || t('noReferrer')) + '</span><span class="text-body-secondary">' + esc(x.views) + '</span></li>';
        }).join('') : '<li class="list-group-item px-0 text-body-secondary">—</li>';
    });

    load();
});

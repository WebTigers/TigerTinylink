// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * tinylink.pages-index.js — the links-page list: cards from Tinylink_Service_Page::list, the "new page" modal
 * (creates, then opens the editor) and delete through TigerModal.
 */
document.addEventListener('DOMContentLoaded', function () {
    var list = document.getElementById('tlp-list');
    if (!list) { return; }
    var t = Tiger.t, fb = document.getElementById('tlp-feedback');
    var form = document.getElementById('tlp-form'), mfb = document.getElementById('tlp-modal-feedback');
    var modal = new bootstrap.Modal(document.getElementById('tlp-modal'));

    function csrf() { var el = form.querySelector('[name="_csrf"]'); return el ? el.value : ''; }
    function api(method, params) {
        var body = new URLSearchParams(Object.assign({ module: 'tinylink', service: 'page', method: method, _csrf: csrf() }, params || {}));
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

    function load() {
        api('list').then(function (res) {
            var pages = (res && res.data && res.data.pages) || [];
            if (!pages.length) { list.innerHTML = '<div class="col-12"><div class="card"><div class="card-body text-center text-body-secondary py-5">' + esc(t('empty')) + '</div></div></div>'; return; }
            list.innerHTML = pages.map(function (p) {
                return '<div class="col-md-6 col-xl-4"><div class="card h-100"><div class="card-body d-flex gap-3 align-items-start">'
                    + (p.avatar_url ? '<img src="' + esc(p.avatar_url) + '" alt="" width="56" height="56" class="rounded-circle object-fit-cover flex-shrink-0">'
                                    : '<span class="rounded-circle bg-body-tertiary d-inline-flex align-items-center justify-content-center flex-shrink-0" style="width:56px;height:56px"><i class="fa-solid fa-address-card text-body-secondary"></i></span>')
                    + '<div class="min-w-0"><h2 class="h6 mb-1 text-truncate">' + esc(p.title)
                    + (p.is_default ? ' <span class="badge text-bg-primary-subtle text-primary-emphasis ms-1">' + esc(t('isDefault')) + '</span>' : '')
                    + (p.status !== 'active' ? ' <span class="badge text-bg-secondary ms-1">' + esc(t('disabled')) + '</span>' : '')
                    + '</h2><div class="small text-body-secondary text-truncate">' + esc(p.path) + '</div>'
                    + '<div class="small text-body-secondary">' + esc(p.view_count) + ' ' + esc(t('views')) + '</div></div></div>'
                    + '<div class="card-footer bg-transparent d-flex gap-2">'
                    + '<a class="btn btn-sm btn-primary" href="/tinylink/pages/edit/id/' + encodeURIComponent(p.page_id) + '"><i class="fa-solid fa-pen me-1"></i>' + esc(t('edit')) + '</a>'
                    + '<a class="btn btn-sm btn-outline-secondary" href="' + esc(p.path) + '" target="_blank" rel="noopener"><i class="fa-solid fa-arrow-up-right-from-square me-1"></i>' + esc(t('open')) + '</a>'
                    + '<button type="button" class="btn btn-sm btn-outline-danger ms-auto" data-tlp-delete="' + esc(p.page_id) + '" data-title="' + esc(p.title) + '" aria-label="' + esc(t('del')) + '"><i class="fa-solid fa-trash"></i></button>'
                    + '</div></div></div>';
            }).join('');
        }).catch(function () { TigerDOM.notify(fb, t('netError'), { type: 'error' }); });
    }

    document.getElementById('tlp-add').addEventListener('click', function () { form.reset(); mfb.innerHTML = ''; modal.show(); });
    form.title.addEventListener('input', function () {
        if (form.handle.dataset.touched) { return; }
        form.handle.value = form.title.value.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').substring(0, 64);
    });
    form.handle.addEventListener('input', function () { form.handle.dataset.touched = '1'; });

    document.getElementById('tlp-create').addEventListener('click', function () {
        mfb.innerHTML = '';
        TigerButton.run(this, function () { return api('save', { title: form.title.value, handle: form.handle.value }); }).then(function (res) {
            if (res && res.result === 1) { window.location = '/tinylink/pages/edit/id/' + encodeURIComponent(res.data.page.page_id); return; }
            notify(mfb, res);
        }).catch(function () { TigerDOM.notify(mfb, t('netError'), { type: 'error' }); });
    });

    list.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-tlp-delete]');
        if (!btn) { return; }
        TigerModal.confirm({ title: t('del'), body: t('confirmDel') + ' ' + btn.getAttribute('data-title'), confirmLabel: t('del'), variant: 'danger' }).then(function (ok) {
            if (!ok) { return; }
            TigerButton.run(btn, function () { return api('delete', { page_id: btn.getAttribute('data-tlp-delete') }); }).then(function (res) {
                if (res && res.result === 1) { TigerDOM.notify(fb, t('deleted'), { type: 'success' }); } else { notify(fb, res); }
                load();
            });
        });
    });

    load();
});

// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * tinylink.admin-index.js — the Short links admin screen.
 *
 * The grid's rows come from Tinylink_Service_Link::datatable; save / enable-disable / delete / stats are /api
 * calls carrying the screen's one CSRF token. Confirmations use TigerModal, feedback TigerDOM, busy states
 * TigerButton (AGENTS.md "UI/UX"). Strings arrive through the view's i18n carrier (Tiger.t).
 */
document.addEventListener('DOMContentLoaded', function () {
    if (typeof tigerDataTable !== 'function') { return; }
    var $ = jQuery, esc = tigerDataTable.esc, t = Tiger.t;
    var root = document.getElementById('tl-root');
    var shortBase = root ? root.getAttribute('data-short-base') : '/l/';
    var fb = document.getElementById('tl-feedback');
    var form = document.getElementById('tl-form');
    var modalEl = document.getElementById('tl-modal');
    var modal = new bootstrap.Modal(modalEl);
    var statsModal = new bootstrap.Modal(document.getElementById('tl-stats-modal'));
    var mfb = document.getElementById('tl-modal-feedback');
    var rows = {};

    function csrf() { var el = form.querySelector('[name="_csrf"]'); return el ? el.value : ''; }
    function api(method, params) {
        var body = new URLSearchParams(Object.assign({ module: 'tinylink', service: 'link', method: method, _csrf: csrf() }, params || {}));
        return fetch('/api', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: body })
            .then(function (r) { return r.json().catch(function () { return { result: 0 }; }); });
    }
    function messages(el, res, fallbackType) {
        (res && res.messages || []).forEach(function (m) { TigerDOM.notify(el, m.message, { type: m.class || fallbackType || 'error' }); });
    }
    function fieldErrors(res) {
        form.querySelectorAll('.is-invalid').forEach(function (x) { x.classList.remove('is-invalid'); });
        Object.keys((res && res.form) || {}).forEach(function (f) {
            var input = form.querySelector('[name="' + f + '"]');
            if (input) { input.classList.add('is-invalid'); }
            var m = res.form[f];
            (m && typeof m === 'object' ? Object.keys(m).map(function (k) { return m[k]; }) : [m])
                .forEach(function (txt) { if (txt) { TigerDOM.notify(mfb, String(txt), { type: 'error' }); } });
        });
    }
    function expired(r) { return r.expires_at && new Date(r.expires_at.replace(' ', 'T') + 'Z') <= new Date(); }

    var table = tigerDataTable('#tl-table', {
        service: { module: 'tinylink', service: 'link', action: 'datatable' },
        extraData: function () { return { status: document.getElementById('tl-status').value }; },
        order: [[4, 'desc']],
        language: { search: '', searchPlaceholder: t('search') },
        columns: [
            { data: 'slug', render: function (s, type, r) {
                rows[r.link_id] = r;
                return '<a href="' + esc(shortBase + r.slug) + '" target="_blank" rel="noopener" class="fw-semibold">/l/' + esc(r.slug) + '</a>'
                    + ' <button type="button" class="btn btn-sm btn-link p-0 ms-1 align-baseline" data-tl-copy="' + esc(r.link_id) + '" title="' + esc(t('copy')) + '" aria-label="' + esc(t('copy')) + '"><i class="fa-regular fa-copy"></i></button>'
                    + (r.notes ? '<div class="small text-body-secondary text-truncate">' + esc(r.notes) + '</div>' : '');
            } },
            { data: 'destination', render: function (d) {
                return '<span class="d-inline-block text-truncate small" style="max-width:28rem" title="' + esc(d) + '">' + esc(d) + '</span>';
            } },
            { data: 'click_count', className: 'text-end', render: function (n) { return esc(n); } },
            { data: 'status', render: function (s, type, r) {
                if (expired(r)) { return '<span class="badge text-bg-warning">' + esc(t('expired')) + '</span>'; }
                return s === 'active' ? '<span class="badge text-bg-success">' + esc(t('active')) + '</span>'
                                      : '<span class="badge text-bg-secondary">' + esc(t('disabled')) + '</span>';
            } },
            { data: 'updated_at', render: function (d) { return '<span class="small text-body-secondary">' + esc(String(d || '').substring(0, 16)) + '</span>'; } },
            { data: null, className: 'text-end text-nowrap', render: function (r) {
                var h = '<button type="button" class="btn btn-sm btn-outline-secondary" data-tl-stats="' + esc(r.link_id) + '" title="' + esc(t('stats')) + '" aria-label="' + esc(t('stats')) + '"><i class="fa-solid fa-chart-column"></i></button> ';
                if (r.can_edit) {
                    h += '<button type="button" class="btn btn-sm btn-outline-secondary" data-tl-edit="' + esc(r.link_id) + '" title="' + esc(t('edit')) + '" aria-label="' + esc(t('edit')) + '"><i class="fa-solid fa-pen"></i></button> ';
                    var on = r.status === 'active';
                    h += '<button type="button" class="btn btn-sm btn-outline-secondary" data-tl-toggle="' + esc(r.link_id) + '" data-to="' + (on ? 'disabled' : 'active') + '" title="' + esc(on ? t('disable') : t('enable')) + '" aria-label="' + esc(on ? t('disable') : t('enable')) + '"><i class="fa-solid ' + (on ? 'fa-toggle-on' : 'fa-toggle-off') + '"></i></button> ';
                }
                if (r.can_delete) {
                    h += '<button type="button" class="btn btn-sm btn-outline-danger" data-tl-delete="' + esc(r.link_id) + '" title="' + esc(t('del')) + '" aria-label="' + esc(t('del')) + '"><i class="fa-solid fa-trash"></i></button>';
                }
                return h;
            } }
        ]
    });
    document.getElementById('tl-status').addEventListener('change', function () { table.ajax.reload(); });

    function openModal(r) {
        form.reset();
        mfb.innerHTML = '';
        form.querySelectorAll('.is-invalid').forEach(function (x) { x.classList.remove('is-invalid'); });
        document.getElementById('tl-modal-title').textContent = r ? t('editTitle') : t('newTitle');
        form.link_id.value = r ? r.link_id : '';
        form.destination.value = r ? r.destination : '';
        form.slug.value = r ? r.slug : '';
        form.redirect_type.value = r ? String(r.redirect_type) : '302';
        form.expires_at.value = r && r.expires_at ? r.expires_at.replace(' ', 'T').substring(0, 16) : '';
        form.notes.value = r ? r.notes : '';
        document.getElementById('tl-merge').checked = r ? !!r.merge_params : true;
        modal.show();
    }
    document.getElementById('tl-add').addEventListener('click', function () { openModal(null); });
    modalEl.addEventListener('shown.bs.modal', function () { form.destination.focus(); });

    document.getElementById('tl-save').addEventListener('click', function () {
        var p = {
            link_id: form.link_id.value, destination: form.destination.value, slug: form.slug.value,
            redirect_type: form.redirect_type.value, expires_at: form.expires_at.value, notes: form.notes.value,
            merge_params: document.getElementById('tl-merge').checked ? '1' : '0'
        };
        mfb.innerHTML = '';
        TigerButton.run(this, function () { return api('save', p); }).then(function (res) {
            if (res && res.result === 1) { modal.hide(); TigerDOM.notify(fb, t('saved'), { type: 'success' }); table.ajax.reload(null, false); return; }
            fieldErrors(res);
            if (!res || !res.form) { messages(mfb, res); }
        }).catch(function () { TigerDOM.notify(mfb, t('networkError'), { type: 'error' }); });
    });

    $('#tl-table tbody').on('click', '[data-tl-edit]', function () { openModal(rows[this.getAttribute('data-tl-edit')]); });

    $('#tl-table tbody').on('click', '[data-tl-copy]', function () {
        var r = rows[this.getAttribute('data-tl-copy')];
        if (!r || !navigator.clipboard) { return; }
        navigator.clipboard.writeText(shortBase + r.slug).then(function () { TigerDOM.toast(t('copied'), { type: 'success' }); });
    });

    $('#tl-table tbody').on('click', '[data-tl-toggle]', function () {
        var btn = this;
        TigerButton.run(btn, function () { return api('setStatus', { link_id: btn.getAttribute('data-tl-toggle'), status: btn.getAttribute('data-to') }); })
            .then(function (res) { if (!res || res.result !== 1) { messages(fb, res); } table.ajax.reload(null, false); })
            .catch(function () { TigerDOM.notify(fb, t('networkError'), { type: 'error' }); });
    });

    $('#tl-table tbody').on('click', '[data-tl-delete]', function () {
        var btn = this, r = rows[btn.getAttribute('data-tl-delete')];
        TigerModal.confirm({ title: t('del'), body: t('confirmDel') + (r ? ' /l/' + r.slug : ''), confirmLabel: t('del'), variant: 'danger' }).then(function (ok) {
            if (!ok) { return; }
            TigerButton.run(btn, function () { return api('delete', { link_id: btn.getAttribute('data-tl-delete') }); }).then(function (res) {
                if (res && res.result === 1) { TigerDOM.notify(fb, t('deleted'), { type: 'success' }); } else { messages(fb, res); }
                table.ajax.reload(null, false);
            }).catch(function () { TigerDOM.notify(fb, t('networkError'), { type: 'error' }); });
        });
    });

    $('#tl-table tbody').on('click', '[data-tl-stats]', function () {
        var btn = this;
        TigerButton.run(btn, function () { return api('stats', { link_id: btn.getAttribute('data-tl-stats'), days: 30 }); }).then(function (res) {
            if (!res || res.result !== 1) { messages(fb, res); return; }
            var s = res.data.stats, l = res.data.link, bc = s.by_class || {};
            document.getElementById('tl-stats-link').textContent = '/l/' + l.slug + '  →  ' + l.destination;
            document.getElementById('tl-stats-humans').textContent = s.humans;
            document.getElementById('tl-stats-mobile').textContent = (bc.mobile || 0) + (bc.tablet || 0);
            document.getElementById('tl-stats-bots').textContent = bc.bot || 0;
            var max = Math.max.apply(null, [1].concat(s.series.map(function (d) { return d.clicks; })));
            var bars = document.getElementById('tl-stats-series');
            bars.innerHTML = '';
            bars.style.height = '6rem';
            s.series.forEach(function (d) {
                var b = document.createElement('div');
                b.className = 'flex-fill bg-primary rounded-top';
                b.style.height = Math.max(2, Math.round(d.clicks / max * 100)) + '%';
                b.title = d.date + ': ' + d.clicks;
                bars.appendChild(b);
            });
            var refs = document.getElementById('tl-stats-refs');
            refs.innerHTML = s.referrers.length ? s.referrers.map(function (x) {
                return '<li class="list-group-item d-flex justify-content-between px-0"><span>' + esc(x.host || t('noReferrer')) + '</span><span class="text-body-secondary">' + esc(x.clicks) + '</span></li>';
            }).join('') : '<li class="list-group-item px-0 text-body-secondary">—</li>';
            statsModal.show();
        }).catch(function () { TigerDOM.notify(fb, t('networkError'), { type: 'error' }); });
    });
});

<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tinylink_Service_Page — links pages (link-in-bio) over /api and MCP: list, read (with its blocks), create /
 * update, delete, and page stats (views, taps per button, referrers). Every write validates through a form;
 * everything is scoped to the caller's current org. Blocks are Tinylink_Service_Block.
 *
 * The static helpers at the bottom (social links, media URLs, capabilities) are shared with the public page
 * and are plain PHP, not operations.
 *
 * @api
 */
class Tinylink_Service_Page extends Tiger_Service_Service
{
    /** The social networks a page can link: key => [Font Awesome classes, label]. */
    const SOCIAL = [
        'instagram' => ['fa-brands fa-instagram', 'Instagram'],
        'tiktok'    => ['fa-brands fa-tiktok', 'TikTok'],
        'youtube'   => ['fa-brands fa-youtube', 'YouTube'],
        'x'         => ['fa-brands fa-x-twitter', 'X'],
        'threads'   => ['fa-brands fa-threads', 'Threads'],
        'facebook'  => ['fa-brands fa-facebook', 'Facebook'],
        'linkedin'  => ['fa-brands fa-linkedin', 'LinkedIn'],
        'pinterest' => ['fa-brands fa-pinterest', 'Pinterest'],
        'twitch'    => ['fa-brands fa-twitch', 'Twitch'],
        'discord'   => ['fa-brands fa-discord', 'Discord'],
        'spotify'   => ['fa-brands fa-spotify', 'Spotify'],
        'github'    => ['fa-brands fa-github', 'GitHub'],
        'website'   => ['fa-solid fa-globe', 'Website'],
        'email'     => ['fa-solid fa-envelope', 'Email'],
    ];

    /**
     * The org's links pages.
     *
     * @apiRequest none
     * @param  array $params
     * @return void
     */
    public function list(array $params): void
    {
        if (!$this->_isAllowed('list')) { $this->_error('core.api.error.not_allowed'); return; }
        $pages = array_map([$this, '_present'], (new Tinylink_Model_Page())->forOrg($this->_orgId()));
        $this->_success(['pages' => $pages]);
    }

    /**
     * One page with its blocks, and which optional blocks this site can show.
     *
     * @apiParam string page_id required The page.
     * @param  array $params `page_id`
     * @return void
     */
    public function get(array $params): void
    {
        if (!$this->_isAllowed('get')) { $this->_error('core.api.error.not_allowed'); return; }
        $org = $this->_orgId();
        $row = (new Tinylink_Model_Page())->findForOrg($org, (string) ($params['page_id'] ?? ''));
        if (!$row) { $this->_error('tinylink.error.page_not_found'); return; }
        $items = array_map([Tinylink_Service_Block::class, 'present'], (new Tinylink_Model_PageItem())->forPage($org, (string) $row->page_id));
        $this->_success(['page' => $this->_present($row->toArray()), 'items' => $items, 'capabilities' => self::capabilities()]);
    }

    /**
     * Create a page (no `page_id`: needs `handle` and `title`) or update one (only the fields sent change).
     * `social` is a JSON object or array of network => URL (or address, for email); an empty value removes it.
     * The org's first page — or one saved with `is_default` — is the one /links serves.
     *
     * @apiRequest Tinylink_Form_Page
     * @apiParam object social Social links: {instagram: "https://…", email: "me@example.com", …}.
     * @apiParam boolean is_default Serve this page at /links.
     * @param  array $params the form fields
     * @return void
     */
    public function save(array $params): void
    {
        if (!$this->_isAllowed('save')) { $this->_error('core.api.error.not_allowed'); return; }
        $org      = $this->_orgId();
        $model    = new Tinylink_Model_Page();
        $pageId   = (string) ($params['page_id'] ?? '');
        $existing = $pageId !== '' ? $model->findForOrg($org, $pageId) : null;
        if ($pageId !== '' && !$existing) { $this->_error('tinylink.error.page_not_found'); return; }

        $form = new Tinylink_Form_Page();
        if (is_array($params['social'] ?? null)) { $social = $params['social']; unset($params['social']); }
        if ($existing) {
            $params['_csrf'] = !empty($params['_csrf']) ? $params['_csrf'] : '';
            $valid = $form->isValidPartial($params);
        } else {
            $valid = $form->isValid($params);
        }
        if (!$valid) { $this->_formErrors($form); return; }
        if (isset($social)) { $params['social'] = $social; }

        $data = [];
        foreach (['handle', 'title'] as $req) {
            if (!$existing || array_key_exists($req, $params)) {
                $v = (string) $form->getValue($req);
                if ($v === '') { $form->getElement($req)->addError('tinylink.error.' . $req); $this->_formErrors($form); return; }
                $data[$req] = $req === 'handle' ? strtolower($v) : $v;
            }
        }
        if (isset($data['handle']) && $model->handleTaken($org, $data['handle'], $existing ? (string) $existing->page_id : null)) {
            $form->getElement('handle')->addError('tinylink.error.handle_taken');
            $this->_formErrors($form);
            return;
        }
        foreach (['bio', 'seo_description'] as $f) {
            if (array_key_exists($f, $params)) { $data[$f] = ((string) $form->getValue($f)) ?: null; }
        }
        foreach (['layout', 'background', 'status'] as $f) {
            if (array_key_exists($f, $params) && (string) $params[$f] !== '') { $data[$f] = (string) $form->getValue($f); }
        }
        foreach (['avatar_media_id', 'og_image_media_id'] as $f) {
            if (array_key_exists($f, $params)) { $data[$f] = ((string) $form->getValue($f)) ?: null; }
        }
        if (array_key_exists('social', $params)) {
            $social = self::parseSocial($params['social']);
            if ($social === null) { $form->getElement('social')->addError('tinylink.error.social'); $this->_formErrors($form); return; }
            $data['social'] = $social ? json_encode($social) : null;
        }
        $makeDefault = filter_var($params['is_default'] ?? false, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === true
            || (!$existing && !$model->defaultFor($org));

        try {
            $id = $this->_transaction(function ($db) use ($model, $existing, $org, $data, $makeDefault) {
                if ($existing) {
                    if ($data) { $model->update($data, $db->quoteInto('page_id = ?', $existing->page_id)); }
                    $id = (string) $existing->page_id;
                } else {
                    $id = (string) $model->insert($data + ['org_id' => $org, 'status' => Tinylink_Model_Page::STATUS_ACTIVE]);
                }
                if ($makeDefault) { $model->makeDefault($org, $id); }
                return $id;
            });
            $this->_success(['page' => $this->_present($model->findForOrg($org, $id)->toArray())], 'tinylink.page.saved');
        } catch (Throwable $e) {
            $this->_error(APPLICATION_ENV !== 'production' ? $e->getMessage() : 'core.api.error.general');
        }
    }

    /**
     * Delete a page and its blocks (soft). The short links its buttons used stay — they may be shared elsewhere.
     *
     * @apiRequest Tinylink_Form_PageId
     * @param  array $params `page_id`
     * @return void
     */
    public function delete(array $params): void
    {
        if (!$this->_isAllowed('delete')) { $this->_error('core.api.error.not_allowed'); return; }
        $form = new Tinylink_Form_PageId();
        if (!$form->isValid($params)) { $this->_formErrors($form); return; }
        $org   = $this->_orgId();
        $model = new Tinylink_Model_Page();
        $row   = $model->findForOrg($org, (string) $form->getValue('page_id'));
        if (!$row) { $this->_error('tinylink.error.page_not_found'); return; }
        try {
            $this->_transaction(function ($db) use ($model, $row, $org) {
                $model->softDelete($db->quoteInto('page_id = ?', $row->page_id));
                (new Tinylink_Model_PageItem())->softDelete($db->quoteInto('page_id = ?', $row->page_id) . ' AND ' . $db->quoteInto('org_id = ?', $org));
            });
            $this->_success(['page_id' => (string) $row->page_id], 'tinylink.page.deleted');
        } catch (Throwable $e) {
            $this->_error(APPLICATION_ENV !== 'production' ? $e->getMessage() : 'core.api.error.general');
        }
    }

    /**
     * A page's stats: views per day, taps per button, top referrers.
     *
     * @apiParam string page_id required The page.
     * @apiParam integer days The window in days (1-365, default 30).
     * @param  array $params `page_id`, `days`
     * @return void
     */
    public function stats(array $params): void
    {
        if (!$this->_isAllowed('stats')) { $this->_error('core.api.error.not_allowed'); return; }
        $org = $this->_orgId();
        $row = (new Tinylink_Model_Page())->findForOrg($org, (string) ($params['page_id'] ?? ''));
        if (!$row) { $this->_error('tinylink.error.page_not_found'); return; }
        $days  = (int) ($params['days'] ?? 30) ?: 30;
        $views = (new Tinylink_Model_PageView())->stats($org, (string) $row->page_id, $days);
        $taps  = (new Tinylink_Model_Click())->tapsForPage($org, (string) $row->page_id, $days);
        $items = [];
        foreach ((new Tinylink_Model_PageItem())->forPage($org, (string) $row->page_id) as $it) {
            if ($it['kind'] === 'link' && $it['link_id']) {
                $items[] = ['item_id' => $it['item_id'], 'label' => (string) $it['label'], 'taps' => (int) ($taps[$it['link_id']] ?? 0)];
            }
        }
        $total = array_sum(array_column($items, 'taps'));
        $this->_success(['page' => $this->_present($row->toArray()), 'stats' => $views + [
            'taps' => $total, 'tap_rate' => $views['views'] > 0 ? round($total / $views['views'] * 100, 1) : 0.0, 'items' => $items,
        ]]);
    }

    // ===================================================================== shared helpers (not operations)

    /**
     * Social links from a JSON string or an array: network => value. Unknown networks are dropped, empty values
     * removed; a URL must be http(s), an email a valid address. Null when a value is invalid.
     *
     * @param  mixed $raw
     * @return array<string,string>|null
     */
    public static function parseSocial($raw): ?array
    {
        if ($raw === null || $raw === '') { return []; }
        if (is_string($raw)) { $raw = json_decode($raw, true); }
        if (!is_array($raw)) { return null; }
        $out = [];
        foreach (self::SOCIAL as $net => $meta) {
            $v = trim((string) ($raw[$net] ?? ''));
            if ($v === '') { continue; }
            if ($net === 'email') {
                if (!filter_var($v, FILTER_VALIDATE_EMAIL)) { return null; }
            } elseif (!Tinylink_Service_Link::isSafeDestination($v)) {
                return null;
            }
            $out[$net] = $v;
        }
        return $out;
    }

    /**
     * A public URL for a media-library image, or '' (unknown, deleted, private, or no media module).
     *
     * @param  string|null $mediaId
     * @return string
     */
    public static function mediaUrl(?string $mediaId): string
    {
        if (!$mediaId || !class_exists('Tiger_Model_Media')) { return ''; }
        try {
            $m   = new Tiger_Model_Media();
            $row = $m->findById($mediaId);
            if (!$row || (string) ($row->visibility ?? 'public') !== 'public') { return ''; }
            return (string) $m->url($row->toArray());
        } catch (Throwable $e) {
            return '';
        }
    }

    /**
     * Which optional blocks this site can show: a newsletter signup (the core newsletter module) and product
     * cards (TigerShop). Absent modules simply hide those blocks.
     *
     * @return array{signup:bool,product:bool}
     */
    public static function capabilities(): array
    {
        return ['signup' => class_exists('Newsletter_Service_Render'), 'product' => class_exists('Shop_Model_Product')];
    }

    /** The public shape of a page row. */
    protected function _present(array $r): array
    {
        return [
            'page_id'           => (string) $r['page_id'],
            'handle'            => (string) $r['handle'],
            'path'              => '/links/' . $r['handle'],
            'title'             => (string) $r['title'],
            'bio'               => (string) ($r['bio'] ?? ''),
            'avatar_media_id'   => $r['avatar_media_id'] ?: null,
            'avatar_url'        => self::mediaUrl($r['avatar_media_id'] ?? null),
            'layout'            => (string) $r['layout'],
            'background'        => (string) $r['background'],
            'social'            => $r['social'] ? (json_decode((string) $r['social'], true) ?: []) : [],
            'seo_description'   => (string) ($r['seo_description'] ?? ''),
            'og_image_media_id' => $r['og_image_media_id'] ?: null,
            'is_default'        => (int) $r['is_default'] === 1,
            'view_count'        => (int) $r['view_count'],
            'status'            => (string) $r['status'],
            'updated_at'        => (string) ($r['updated_at'] ?: $r['created_at']),
        ];
    }
}

<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tinylink_Service_Link — short links over /api (and MCP): list, read, create/update, enable/disable, delete,
 * and per-link click stats. Every write validates through a Tinylink_Form_* (CSRF on the browser path) and
 * runs in a transaction; every read and write is scoped to the caller's current org.
 *
 * The public redirect (Tinylink_GoController) uses the static helpers at the bottom — resolve(), buildUrl(),
 * isSafeDestination(), uaClass(), referrerHost() — which are pure PHP, not operations.
 *
 * @api
 */
class Tinylink_Service_Link extends Tiger_Service_Service
{
    /**
     * Default public hits (redirects, links-page views) per visitor IP per minute; `tiger.tinylink.rate_limit`
     * overrides. Lives here, not on a controller: controllers are not autoloadable, so one controller can't
     * read another's constant at runtime.
     */
    const DEFAULT_RATE_LIMIT = 120;

    /**
     * The configured public rate limit (hits per IP per minute).
     *
     * @return int
     */
    public static function rateLimit(): int
    {
        $cfg = Zend_Registry::isRegistered('Zend_Config') ? Zend_Registry::get('Zend_Config') : null;
        $t   = $cfg ? $cfg->get('tiger') : null;
        $m   = $t ? $t->get('tinylink') : null;
        $v   = $m ? $m->get('rate_limit') : null;
        return $v !== null && is_numeric((string) $v) ? (int) (string) $v : self::DEFAULT_RATE_LIMIT;
    }

    /** Length of an auto-generated slug. */
    const AUTO_SLUG_LENGTH = 7;

    /** The alphabet an auto slug draws from: lower-case + digits, minus look-alikes (0/o, 1/l). */
    const AUTO_SLUG_CHARS = 'abcdefghijkmnpqrstuvwxyz23456789';

    /**
     * The org's links, for the admin grid.
     *
     * @apiRequest datatable
     * @apiParam string(active|disabled) status Only links in this status.
     * @param  array $params the DataTables request + `status`
     * @return void
     */
    public function datatable(array $params): void
    {
        if (!$this->_isAllowed('datatable')) { $this->_error('core.api.error.not_allowed'); return; }
        $dt   = $this->_dtParams($params);
        $data = (new Tinylink_Model_Link())->datatable($this->_orgId(), [
            'search'   => $dt['search'],
            'status'   => (string) ($params['status'] ?? ''),
            'orderCol' => isset($dt['order'][0]) ? $dt['order'][0]['column'] : -1,
            'orderDir' => isset($dt['order'][0]) ? $dt['order'][0]['dir'] : '',
            'offset'   => $dt['start'],
            'limit'    => $dt['length'],
        ]);
        $canEdit = $this->_isAllowed('save');
        $canDel  = $this->_isAllowed('delete');
        $rows = [];
        foreach ($data['rows'] as $r) {
            $rows[] = $this->_present($r) + ['can_edit' => $canEdit, 'can_delete' => $canDel];
        }
        $this->_dtResponse($dt['draw'], $data['total'], $data['filtered'], $rows);
    }

    /**
     * One link of the org.
     *
     * @apiParam string link_id required The link.
     * @param  array $params `link_id`
     * @return void
     */
    public function get(array $params): void
    {
        if (!$this->_isAllowed('get')) { $this->_error('core.api.error.not_allowed'); return; }
        $row = (new Tinylink_Model_Link())->findForOrg($this->_orgId(), (string) ($params['link_id'] ?? ''));
        if (!$row) { $this->_error('tinylink.error.not_found'); return; }
        $this->_success(['link' => $this->_present($row->toArray())]);
    }

    /**
     * Create a link (no `link_id`) or update one (with it). A blank slug on create gets a random one; on
     * update only the fields sent change. The destination must be an http(s) URL with a host.
     *
     * @apiRequest Tinylink_Form_Link
     * @apiParam boolean merge_params Pass the short link's own query parameters through to the destination (default true).
     * @param  array $params the form fields
     * @return void
     */
    public function save(array $params): void
    {
        if (!$this->_isAllowed('save')) { $this->_error('core.api.error.not_allowed'); return; }
        $org      = $this->_orgId();
        $model    = new Tinylink_Model_Link();
        $linkId   = trim((string) ($params['link_id'] ?? ''));
        $existing = null;
        if ($linkId !== '') {
            $existing = $model->findForOrg($org, $linkId);
            if (!$existing) { $this->_error('tinylink.error.not_found'); return; }
        }

        $form = new Tinylink_Form_Link();
        if ($existing) {
            // A partial update validates what was sent — seed the token so it is always checked (AGENTS.md).
            $params['_csrf'] = !empty($params['_csrf']) ? $params['_csrf'] : '';
            $valid = $form->isValidPartial($params);
        } else {
            $valid = $form->isValid($params);
        }
        if (!$valid) { $this->_formErrors($form); return; }

        $data = [];
        if (array_key_exists('destination', $params) || !$existing) {
            $dest = (string) $form->getValue('destination');
            if (!self::isSafeDestination($dest)) {
                $form->getElement('destination')->addError('tinylink.error.destination');
                $this->_formErrors($form);
                return;
            }
            $data['destination'] = $dest;
        }
        if (array_key_exists('slug', $params) || !$existing) {
            $slug = strtolower((string) $form->getValue('slug'));
            if ($slug === '' && !$existing) {
                $slug = $this->_freeSlug($model, $org);
            }
            if ($slug === '' || $model->slugTaken($org, $slug, $existing ? (string) $existing->link_id : null)) {
                $form->getElement('slug')->addError($slug === '' ? 'tinylink.error.slug' : 'tinylink.error.slug_taken');
                $this->_formErrors($form);
                return;
            }
            $data['slug'] = $slug;
        }
        if (array_key_exists('redirect_type', $params) && (string) $params['redirect_type'] !== '') {
            $data['redirect_type'] = (int) $form->getValue('redirect_type');
        }
        if (array_key_exists('merge_params', $params) || !$existing) {
            $data['merge_params'] = self::_flag($params['merge_params'] ?? null, true) ? 1 : 0;
        }
        if (array_key_exists('expires_at', $params)) {
            $exp = (string) $form->getValue('expires_at');
            $data['expires_at'] = $exp === '' ? null : gmdate('Y-m-d H:i:s', (int) strtotime(str_replace('T', ' ', $exp)));
        }
        if (array_key_exists('notes', $params)) {
            $data['notes'] = ((string) $form->getValue('notes')) ?: null;
        }
        if (array_key_exists('status', $params) && (string) $params['status'] !== '') {
            $data['status'] = (string) $form->getValue('status');
        }

        try {
            $id = $this->_transaction(function ($db) use ($model, $existing, $org, $data) {
                if ($existing) {
                    if ($data) { $model->update($data, $db->quoteInto('link_id = ?', $existing->link_id)); }
                    return (string) $existing->link_id;
                }
                return (string) $model->insert($data + ['org_id' => $org, 'status' => Tinylink_Model_Link::STATUS_ACTIVE]);
            });
            $row = $model->findForOrg($org, $id);
            $this->_success(['link' => $this->_present($row->toArray())], 'tinylink.saved');
        } catch (Throwable $e) {
            $this->_error(APPLICATION_ENV !== 'production' ? $e->getMessage() : 'core.api.error.general');
        }
    }

    /**
     * Enable or disable one link. A disabled link 404s at /l/<slug> but keeps its slug and its clicks.
     *
     * @apiRequest Tinylink_Form_Status
     * @param  array $params `link_id`, `status` (active|disabled)
     * @return void
     */
    public function setStatus(array $params): void
    {
        if (!$this->_isAllowed('setStatus')) { $this->_error('core.api.error.not_allowed'); return; }
        $form = new Tinylink_Form_Status();
        if (!$form->isValid($params)) { $this->_formErrors($form); return; }
        $model = new Tinylink_Model_Link();
        $row   = $model->findForOrg($this->_orgId(), (string) $form->getValue('link_id'));
        if (!$row) { $this->_error('tinylink.error.not_found'); return; }
        try {
            $this->_transaction(function ($db) use ($model, $row, $form) {
                $model->update(['status' => (string) $form->getValue('status')], $db->quoteInto('link_id = ?', $row->link_id));
            });
            $this->_success(['link_id' => (string) $row->link_id, 'status' => (string) $form->getValue('status')], 'tinylink.saved');
        } catch (Throwable $e) {
            $this->_error(APPLICATION_ENV !== 'production' ? $e->getMessage() : 'core.api.error.general');
        }
    }

    /**
     * Delete one link (soft): /l/<slug> stops resolving and the slug is free for a new link.
     *
     * @apiRequest Tinylink_Form_Id
     * @param  array $params `link_id`
     * @return void
     */
    public function delete(array $params): void
    {
        if (!$this->_isAllowed('delete')) { $this->_error('core.api.error.not_allowed'); return; }
        $form = new Tinylink_Form_Id();
        if (!$form->isValid($params)) { $this->_formErrors($form); return; }
        $model = new Tinylink_Model_Link();
        $row   = $model->findForOrg($this->_orgId(), (string) $form->getValue('link_id'));
        if (!$row) { $this->_error('tinylink.error.not_found'); return; }
        try {
            $this->_transaction(function ($db) use ($model, $row) {
                $model->softDelete($db->quoteInto('link_id = ?', $row->link_id));
            });
            $this->_success(['link_id' => (string) $row->link_id], 'tinylink.deleted');
        } catch (Throwable $e) {
            $this->_error(APPLICATION_ENV !== 'production' ? $e->getMessage() : 'core.api.error.general');
        }
    }

    /**
     * Click stats for one link: totals by device class, a daily series of human clicks, top referrers.
     *
     * @apiParam string link_id required The link.
     * @apiParam integer days The window in days (1-365, default 30).
     * @param  array $params `link_id`, `days`
     * @return void
     */
    public function stats(array $params): void
    {
        if (!$this->_isAllowed('stats')) { $this->_error('core.api.error.not_allowed'); return; }
        $org = $this->_orgId();
        $row = (new Tinylink_Model_Link())->findForOrg($org, (string) ($params['link_id'] ?? ''));
        if (!$row) { $this->_error('tinylink.error.not_found'); return; }
        $days = (int) ($params['days'] ?? 30) ?: 30;
        $this->_success(['link' => $this->_present($row->toArray()),
            'stats' => (new Tinylink_Model_Click())->stats($org, (string) $row->link_id, $days)]);
    }

    // ============================================================== the redirect (static, pure helpers)

    /**
     * Resolve /l/<slug> for one org: the live, active, unexpired link and the URL to send the visitor to.
     *
     * @param  string $orgId the site's org
     * @param  string $slug  the slug from the URL
     * @param  array  $query the short link's own query parameters ($_GET)
     * @return array{link:array,url:string,code:int}|null null = 404
     */
    public static function resolve(string $orgId, string $slug, array $query = []): ?array
    {
        if (!preg_match(Tinylink_Form_Link::SLUG_PATTERN, $slug)) {
            return null;
        }
        $row = (new Tinylink_Model_Link())->bySlug($orgId, $slug);
        if (!$row || (string) $row->status !== Tinylink_Model_Link::STATUS_ACTIVE) {
            return null;
        }
        if ($row->expires_at && strtotime((string) $row->expires_at . ' UTC') <= time()) {
            return null;
        }
        $dest = (string) $row->destination;
        if (!self::isSafeDestination($dest)) {
            return null;   // never redirect to something that would not pass validation today
        }
        return [
            'link' => $row->toArray(),
            'url'  => self::buildUrl($dest, (int) $row->merge_params === 1 ? $query : []),
            'code' => (int) $row->redirect_type === 301 ? 301 : 302,
        ];
    }

    /**
     * The destination with the short link's own query parameters merged in. The destination's parameters are
     * kept; a parameter on the short link REPLACES the same-named one (so /l/spring?utm_source=x tags a channel
     * per share). The fragment is kept.
     *
     * @param  string $destination the stored destination
     * @param  array  $query       the short link's query parameters
     * @return string
     */
    public static function buildUrl(string $destination, array $query): string
    {
        if (!$query) {
            return $destination;
        }
        $hash = '';
        if (($p = strpos($destination, '#')) !== false) {
            $hash        = substr($destination, $p);
            $destination = substr($destination, 0, $p);
        }
        $base = $destination;
        $own  = [];
        if (($q = strpos($destination, '?')) !== false) {
            $base = substr($destination, 0, $q);
            parse_str(substr($destination, $q + 1), $own);
        }
        $merged = array_replace($own, $query);
        return $base . ($merged ? '?' . http_build_query($merged, '', '&', PHP_QUERY_RFC3986) : '') . $hash;
    }

    /**
     * Is this a destination TigerTinylink will redirect to: an absolute http(s) URL with a host, no
     * whitespace or control characters, no credentials. (No javascript:, data:, protocol-relative or bare paths.)
     *
     * @param  string $url
     * @return bool
     */
    public static function isSafeDestination(string $url): bool
    {
        if ($url === '' || strlen($url) > 2048 || preg_match('/[\x00-\x20\x7f]/', $url)) {
            return false;
        }
        $p = parse_url($url);
        if (!is_array($p) || empty($p['host'])) {
            return false;
        }
        if (!in_array(strtolower((string) ($p['scheme'] ?? '')), ['http', 'https'], true)) {
            return false;
        }
        return !isset($p['user']) && !isset($p['pass']);
    }

    /**
     * A coarse device class from a user agent: bot | tablet | mobile | desktop. Bots include link-preview
     * fetchers (Slack, Twitter, Facebook…), which would otherwise inflate a count the moment a link is shared.
     *
     * @param  string $ua
     * @return string
     */
    public static function uaClass(string $ua): string
    {
        if ($ua === '' || preg_match('/bot|crawl|spider|slurp|preview|facebookexternalhit|embedly|whatsapp|telegram|discord|curl|wget|python|httpclient|headless/i', $ua)) {
            return 'bot';
        }
        if (preg_match('/ipad|tablet|kindle|silk|playbook|(android(?!.*mobile))/i', $ua)) {
            return 'tablet';
        }
        if (preg_match('/mobi|iphone|ipod|android|blackberry|opera mini|iemobile/i', $ua)) {
            return 'mobile';
        }
        return 'desktop';
    }

    /**
     * The host of a referrer URL (lower-case, no `www.`), or null.
     *
     * @param  string $referrer
     * @return string|null
     */
    public static function referrerHost(string $referrer): ?string
    {
        $host = strtolower((string) parse_url($referrer, PHP_URL_HOST));
        $host = preg_replace('/^www\./', '', $host);
        return $host !== '' ? $host : null;
    }

    // ===================================================================================== internals

    /** A random slug not yet used by the org (a handful of tries; '' if every one collided). */
    protected function _freeSlug(Tinylink_Model_Link $model, string $org): string
    {
        $chars = self::AUTO_SLUG_CHARS;
        for ($try = 0; $try < 8; $try++) {
            $s = '';
            for ($i = 0; $i < self::AUTO_SLUG_LENGTH; $i++) { $s .= $chars[random_int(0, strlen($chars) - 1)]; }
            if (!$model->slugTaken($org, $s)) { return $s; }
        }
        return '';
    }

    /** A boolean from a form/JSON value; null/'' → $default. */
    protected static function _flag($v, bool $default): bool
    {
        if ($v === null || $v === '') { return $default; }
        return filter_var($v, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    /** The public shape of a link row. */
    protected function _present(array $r): array
    {
        return [
            'link_id'         => (string) $r['link_id'],
            'slug'            => (string) $r['slug'],
            'short_path'      => '/l/' . $r['slug'],
            'destination'     => (string) $r['destination'],
            'redirect_type'   => (int) $r['redirect_type'],
            'merge_params'    => (int) $r['merge_params'] === 1,
            'expires_at'      => $r['expires_at'] ?: null,
            'notes'           => (string) ($r['notes'] ?? ''),
            'click_count'     => (int) $r['click_count'],
            'last_clicked_at' => $r['last_clicked_at'] ?: null,
            'status'          => (string) $r['status'],
            'updated_at'      => (string) ($r['updated_at'] ?: $r['created_at']),
        ];
    }
}

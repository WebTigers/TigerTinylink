<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tinylink_Service_Block — the blocks on a links page over /api and MCP: add / edit, enable or disable,
 * delete, and reorder (so an agent can build and arrange a page). Scoped to the caller's org; every write
 * validates through a form.
 *
 * A `link` block is always backed by a short link, so every tap is counted and redirected through /l/: give
 * an existing `link_id`, or a `destination` and the service creates the short link for you.
 *
 * @api
 */
class Tinylink_Service_Block extends Tiger_Service_Service
{
    /**
     * Add a block (no `item_id`: needs `page_id` + `kind`) or edit one (only the fields sent change).
     *
     * Per kind: `link` needs a `label` and a `link_id` or `destination`; `header` needs a `label`; `product`
     * needs `ref` (a TigerShop product slug); `divider` and `signup` need nothing.
     *
     * @apiRequest Tinylink_Form_PageItem
     * @apiParam boolean featured Show a link as a larger card.
     * @param  array $params the form fields
     * @return void
     */
    public function save(array $params): void
    {
        if (!$this->_isAllowed('save')) { $this->_error('core.api.error.not_allowed'); return; }
        $org      = $this->_orgId();
        $items    = new Tinylink_Model_PageItem();
        $itemId   = (string) ($params['item_id'] ?? '');
        $existing = $itemId !== '' ? $items->findForOrg($org, $itemId) : null;
        if ($itemId !== '' && !$existing) { $this->_error('tinylink.error.item_not_found'); return; }

        $form = new Tinylink_Form_PageItem();
        if ($existing) {
            $params['_csrf'] = !empty($params['_csrf']) ? $params['_csrf'] : '';
            $valid = $form->isValidPartial($params);
        } else {
            $valid = $form->isValid($params);
        }
        if (!$valid) { $this->_formErrors($form); return; }

        $pageId = $existing ? (string) $existing->page_id : (string) $form->getValue('page_id');
        $page   = (new Tinylink_Model_Page())->findForOrg($org, $pageId);
        if (!$page) { $this->_error('tinylink.error.page_not_found'); return; }
        $kind = $existing ? (string) $existing->kind : ((string) $form->getValue('kind') ?: 'link');

        $data = [];
        foreach (['label', 'icon'] as $f) {
            if (array_key_exists($f, $params)) { $data[$f] = ((string) $form->getValue($f)) ?: null; }
        }
        if (array_key_exists('thumbnail_media_id', $params)) { $data['thumbnail_media_id'] = ((string) $form->getValue('thumbnail_media_id')) ?: null; }
        if (array_key_exists('ref', $params)) { $data['ref'] = ((string) $form->getValue('ref')) ?: null; }
        foreach (['show_from', 'show_until'] as $f) {
            if (array_key_exists($f, $params)) {
                $v = (string) $form->getValue($f);
                $data[$f] = $v === '' ? null : gmdate('Y-m-d H:i:s', (int) strtotime(str_replace('T', ' ', $v) . ' UTC'));
            }
        }
        if (array_key_exists('featured', $params)) {
            $data['featured'] = filter_var($params['featured'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ? 1 : 0;
        }
        if (array_key_exists('status', $params) && (string) $params['status'] !== '') { $data['status'] = (string) $form->getValue('status'); }

        // What each kind needs.
        $label = array_key_exists('label', $data) ? (string) $data['label'] : (string) ($existing->label ?? '');
        if (in_array($kind, ['link', 'header'], true) && $label === '') {
            $form->getElement('label')->addError('tinylink.error.label_required');
            $this->_formErrors($form);
            return;
        }
        if ($kind === 'product' && (string) ($data['ref'] ?? ($existing->ref ?? '')) === '') {
            $form->getElement('ref')->addError('tinylink.error.ref');
            $this->_formErrors($form);
            return;
        }

        $links    = new Tinylink_Model_Link();
        $linkId   = (string) $form->getValue('link_id');
        $dest     = (string) $form->getValue('destination');
        $makeLink = null;
        if ($kind === 'link') {
            if ($linkId !== '') {
                if (!$links->findForOrg($org, $linkId)) { $this->_error('tinylink.error.not_found'); return; }
                $data['link_id'] = $linkId;
            } elseif ($dest !== '') {
                if (!Tinylink_Service_Link::isSafeDestination($dest)) {
                    $form->getElement('destination')->addError('tinylink.error.destination');
                    $this->_formErrors($form);
                    return;
                }
                if ($existing && $existing->link_id && $links->findForOrg($org, (string) $existing->link_id)) {
                    $makeLink = ['update' => (string) $existing->link_id, 'destination' => $dest];
                } else {
                    $makeLink = ['destination' => $dest];
                }
            } elseif (!$existing || !$existing->link_id) {
                $form->getElement('destination')->addError('tinylink.error.destination');
                $this->_formErrors($form);
                return;
            }
        }

        try {
            $id = $this->_transaction(function ($db) use ($items, $links, $existing, $org, $page, $kind, $data, $makeLink) {
                if ($makeLink) {
                    if (isset($makeLink['update'])) {
                        $links->update(['destination' => $makeLink['destination']], $db->quoteInto('link_id = ?', $makeLink['update']));
                    } else {
                        $data['link_id'] = (string) $links->insert([
                            'org_id' => $org, 'slug' => self::_freeSlug($links, $org), 'destination' => $makeLink['destination'],
                            'redirect_type' => 302, 'merge_params' => 1, 'status' => Tinylink_Model_Link::STATUS_ACTIVE,
                            'notes' => substr('/links/' . $page->handle, 0, 500),
                        ]);
                    }
                }
                if ($existing) {
                    if ($data) { $items->update($data, $db->quoteInto('item_id = ?', $existing->item_id)); }
                    return (string) $existing->item_id;
                }
                return (string) $items->insert($data + ['org_id' => $org, 'page_id' => (string) $page->page_id, 'kind' => $kind,
                    'sort' => $items->nextSort((string) $page->page_id), 'status' => 'active']);
            });
            $row = null;
            foreach ($items->forPage($org, (string) $page->page_id) as $r) { if ($r['item_id'] === $id) { $row = $r; break; } }
            $this->_success(['item' => self::present($row)], 'tinylink.item.saved');
        } catch (Throwable $e) {
            $this->_error(APPLICATION_ENV !== 'production' ? $e->getMessage() : 'core.api.error.general');
        }
    }

    /**
     * Show or hide one block (a hidden block keeps its place).
     *
     * @apiRequest Tinylink_Form_PageItem
     * @param  array $params `item_id`, `status` (active|disabled)
     * @return void
     */
    public function setStatus(array $params): void
    {
        if (!$this->_isAllowed('setStatus')) { $this->_error('core.api.error.not_allowed'); return; }
        $form = new Tinylink_Form_PageItem();
        $params['_csrf'] = !empty($params['_csrf']) ? $params['_csrf'] : '';
        if (!$form->isValidPartial($params)) { $this->_formErrors($form); return; }
        $items = new Tinylink_Model_PageItem();
        $row   = $items->findForOrg($this->_orgId(), (string) $form->getValue('item_id'));
        $to    = (string) $form->getValue('status');
        if (!$row) { $this->_error('tinylink.error.item_not_found'); return; }
        if ($to === '') { $form->getElement('status')->addError('tinylink.error.status'); $this->_formErrors($form); return; }
        try {
            $this->_transaction(function ($db) use ($items, $row, $to) {
                $items->update(['status' => $to], $db->quoteInto('item_id = ?', $row->item_id));
            });
            $this->_success(['item_id' => (string) $row->item_id, 'status' => $to], 'tinylink.item.saved');
        } catch (Throwable $e) {
            $this->_error(APPLICATION_ENV !== 'production' ? $e->getMessage() : 'core.api.error.general');
        }
    }

    /**
     * Remove one block (soft). A link block's short link stays — it may be shared elsewhere.
     *
     * @apiRequest Tinylink_Form_PageId
     * @param  array $params `item_id`
     * @return void
     */
    public function delete(array $params): void
    {
        if (!$this->_isAllowed('delete')) { $this->_error('core.api.error.not_allowed'); return; }
        $form = new Tinylink_Form_PageId();
        if (!$form->isValid($params)) { $this->_formErrors($form); return; }
        $items = new Tinylink_Model_PageItem();
        $row   = $items->findForOrg($this->_orgId(), (string) $form->getValue('item_id'));
        if (!$row) { $this->_error('tinylink.error.item_not_found'); return; }
        try {
            $this->_transaction(function ($db) use ($items, $row) {
                $items->softDelete($db->quoteInto('item_id = ?', $row->item_id));
            });
            $this->_success(['item_id' => (string) $row->item_id], 'tinylink.item.deleted');
        } catch (Throwable $e) {
            $this->_error(APPLICATION_ENV !== 'production' ? $e->getMessage() : 'core.api.error.general');
        }
    }

    /**
     * Put a page's blocks in this order. Ids of another page or org are ignored; blocks not listed keep their
     * position after the listed ones.
     *
     * @apiRequest Tinylink_Form_Reorder
     * @apiParam array item_ids required The page's block ids, top to bottom (an array, or a comma-separated string).
     * @param  array $params `page_id`, `item_ids`
     * @return void
     */
    public function reorder(array $params): void
    {
        if (!$this->_isAllowed('reorder')) { $this->_error('core.api.error.not_allowed'); return; }
        $form = new Tinylink_Form_Reorder();
        if (!$form->isValid($params)) { $this->_formErrors($form); return; }
        $org  = $this->_orgId();
        $page = (new Tinylink_Model_Page())->findForOrg($org, (string) $form->getValue('page_id'));
        if (!$page) { $this->_error('tinylink.error.page_not_found'); return; }
        $raw = $params['item_ids'] ?? [];
        $ids = array_values(array_filter(array_map('trim', is_array($raw) ? array_map('strval', $raw) : explode(',', (string) $raw)),
            static fn($id) => (bool) preg_match('/^[0-9a-f-]{36}$/i', $id)));
        $items = new Tinylink_Model_PageItem();
        foreach ($items->forPage($org, (string) $page->page_id) as $r) {
            if (!in_array($r['item_id'], $ids, true)) { $ids[] = $r['item_id']; }   // unlisted ones keep their place, after
        }
        try {
            $n = $this->_transaction(fn() => $items->reorder($org, (string) $page->page_id, $ids));
            $this->_success(['moved' => $n], 'tinylink.item.reordered');
        } catch (Throwable $e) {
            $this->_error(APPLICATION_ENV !== 'production' ? $e->getMessage() : 'core.api.error.general');
        }
    }

    // ===================================================================== shared helpers (not operations)

    /**
     * The public shape of a block row (as Tinylink_Model_PageItem::forPage returns it).
     *
     * @param  array|null $r
     * @return array|null
     */
    public static function present(?array $r): ?array
    {
        if (!$r) { return null; }
        return [
            'item_id'            => (string) $r['item_id'],
            'kind'               => (string) $r['kind'],
            'label'              => (string) ($r['label'] ?? ''),
            'link_id'            => $r['link_id'] ?: null,
            'short_path'         => !empty($r['link_slug']) ? '/l/' . $r['link_slug'] : null,
            'destination'        => (string) ($r['link_destination'] ?? ''),
            'ref'                => (string) ($r['ref'] ?? ''),
            'icon'               => (string) ($r['icon'] ?? ''),
            'thumbnail_media_id' => $r['thumbnail_media_id'] ?: null,
            'thumbnail_url'      => Tinylink_Service_Page::mediaUrl($r['thumbnail_media_id'] ?? null),
            'featured'           => (int) $r['featured'] === 1,
            'show_from'          => $r['show_from'] ?: null,
            'show_until'         => $r['show_until'] ?: null,
            'status'             => (string) $r['status'],
            'sort'               => (int) $r['sort'],
        ];
    }

    /** A random slug not yet used by the org. */
    protected static function _freeSlug(Tinylink_Model_Link $model, string $org): string
    {
        $chars = Tinylink_Service_Link::AUTO_SLUG_CHARS;
        for ($try = 0; $try < 8; $try++) {
            $s = '';
            for ($i = 0; $i < Tinylink_Service_Link::AUTO_SLUG_LENGTH; $i++) { $s .= $chars[random_int(0, strlen($chars) - 1)]; }
            if (!$model->slugTaken($org, $s)) { return $s; }
        }
        throw new RuntimeException('no free slug');
    }
}

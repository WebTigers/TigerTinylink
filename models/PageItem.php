<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tinylink_Model_PageItem — the blocks on a links page (`tinylink_page_item`), in `sort` order. Org-scoped.
 *
 * @api
 */
class Tinylink_Model_PageItem extends Tiger_Model_Table
{
    const KINDS = ['link', 'header', 'divider', 'signup', 'product'];

    protected $_name    = 'tinylink_page_item';
    protected $_primary = 'item_id';

    /**
     * One live item of this org by id, or null.
     *
     * @param  string $orgId
     * @param  string $itemId
     * @return Zend_Db_Table_Row_Abstract|null
     */
    public function findForOrg(string $orgId, string $itemId)
    {
        if ($itemId === '') { return null; }
        return $this->fetchRow($this->activeSelect()->where('org_id = ?', $orgId)->where('item_id = ?', $itemId)) ?: null;
    }

    /**
     * A page's live items in display order, each joined to its short link (slug, destination, status).
     *
     * @param  string $orgId
     * @param  string $pageId
     * @return array
     */
    public function forPage(string $orgId, string $pageId): array
    {
        $db = $this->getAdapter();
        return $db->fetchAll($db->select()
            ->from(['i' => $this->_name])
            ->joinLeft(['l' => 'tinylink_link'], 'l.link_id = i.link_id AND l.deleted = 0',
                ['link_slug' => 'slug', 'link_destination' => 'destination', 'link_status' => 'status', 'link_expires_at' => 'expires_at'])
            ->where('i.org_id = ?', $orgId)->where('i.page_id = ?', $pageId)->where('i.deleted = 0')
            ->order(['i.sort ASC', 'i.created_at ASC']));
    }

    /**
     * The next sort position on a page (after the last item).
     *
     * @param  string $pageId
     * @return int
     */
    public function nextSort(string $pageId): int
    {
        $db = $this->getAdapter();
        return 1 + (int) $db->fetchOne($db->select()->from($this->_name, [new Zend_Db_Expr('COALESCE(MAX(sort), 0)')])
            ->where('page_id = ?', $pageId)->where('deleted = 0'));
    }

    /**
     * Apply an order: each listed item of this page gets its position. Items of another page/org are ignored.
     *
     * @param  string   $orgId
     * @param  string   $pageId
     * @param  string[] $itemIds in display order
     * @return int      items moved
     */
    public function reorder(string $orgId, string $pageId, array $itemIds): int
    {
        $db = $this->getAdapter();
        $n  = 0;
        foreach (array_values($itemIds) as $pos => $id) {
            $n += $this->update(['sort' => $pos + 1], $db->quoteInto('item_id = ?', (string) $id) . ' AND '
                . $db->quoteInto('page_id = ?', $pageId) . ' AND ' . $db->quoteInto('org_id = ?', $orgId) . ' AND deleted = 0');
        }
        return $n;
    }
}

<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tinylink_Model_Page — links pages (`tinylink_page`). Every finder is org-scoped; writes use update()/insert()
 * with explicit columns (never `$row->save()`, which would write the generated `handle_active`).
 *
 * @api
 */
class Tinylink_Model_Page extends Tiger_Model_Table
{
    const STATUS_ACTIVE   = 'active';
    const STATUS_DISABLED = 'disabled';

    /** The layouts a page can use (CSS classes `tl-page--<layout>`). */
    const LAYOUTS = ['stack', 'pill', 'outline'];

    /** The page backgrounds (CSS classes `tl-bg--<background>`). */
    const BACKGROUNDS = ['plain', 'brand', 'soft'];

    protected $_name    = 'tinylink_page';
    protected $_primary = 'page_id';

    /**
     * One live page of this org by id, or null.
     *
     * @param  string $orgId
     * @param  string $pageId
     * @return Zend_Db_Table_Row_Abstract|null
     */
    public function findForOrg(string $orgId, string $pageId)
    {
        if ($pageId === '') { return null; }
        return $this->fetchRow($this->activeSelect()->where('org_id = ?', $orgId)->where('page_id = ?', $pageId)) ?: null;
    }

    /**
     * One live page of this org by handle (any status), or null.
     *
     * @param  string $orgId
     * @param  string $handle case-insensitive
     * @return Zend_Db_Table_Row_Abstract|null
     */
    public function byHandle(string $orgId, string $handle)
    {
        if ($handle === '') { return null; }
        return $this->fetchRow($this->activeSelect()->where('org_id = ?', $orgId)->where('handle = ?', strtolower($handle))) ?: null;
    }

    /**
     * The org's default page (the one /links serves), else its oldest live page, or null.
     *
     * @param  string $orgId
     * @return Zend_Db_Table_Row_Abstract|null
     */
    public function defaultFor(string $orgId)
    {
        return $this->fetchRow($this->activeSelect()->where('org_id = ?', $orgId)->where('status = ?', self::STATUS_ACTIVE)
            ->order(['is_default DESC', 'created_at ASC'])) ?: null;
    }

    /**
     * Is this handle used by another live page of the org?
     *
     * @param  string      $orgId
     * @param  string      $handle
     * @param  string|null $exceptId the page being edited
     * @return bool
     */
    public function handleTaken(string $orgId, string $handle, ?string $exceptId = null): bool
    {
        $sel = $this->activeSelect()->where('org_id = ?', $orgId)->where('handle = ?', strtolower($handle));
        if ($exceptId) { $sel->where('page_id <> ?', $exceptId); }
        return (bool) $this->fetchRow($sel);
    }

    /**
     * Every live page of the org, newest first (the admin list — an org has a handful, not thousands).
     *
     * @param  string $orgId
     * @return array
     */
    public function forOrg(string $orgId): array
    {
        return $this->getAdapter()->fetchAll($this->getAdapter()->select()->from($this->_name)
            ->where('org_id = ?', $orgId)->where('deleted = 0')->order('created_at DESC'));
    }

    /**
     * Make one page the org's default and clear the flag on the rest.
     *
     * @param  string $orgId
     * @param  string $pageId
     * @return void
     */
    public function makeDefault(string $orgId, string $pageId): void
    {
        $db = $this->getAdapter();
        $this->update(['is_default' => 0], $db->quoteInto('org_id = ?', $orgId) . ' AND ' . $db->quoteInto('page_id <> ?', $pageId));
        $this->update(['is_default' => 1], $db->quoteInto('org_id = ?', $orgId) . ' AND ' . $db->quoteInto('page_id = ?', $pageId));
    }

    /**
     * Count one human view.
     *
     * @param  string $pageId
     * @return void
     */
    public function countView(string $pageId): void
    {
        $this->getAdapter()->update($this->_name, ['view_count' => new Zend_Db_Expr('view_count + 1')],
            $this->getAdapter()->quoteInto('page_id = ?', $pageId));
    }
}

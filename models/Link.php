<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tinylink_Model_Link — the short-link store (`tinylink_link`).
 *
 * Every finder is ORG-SCOPED: it takes the org and never returns another org's row, so the service can't
 * reach across orgs by passing a bare id. Writes go through update() with explicit columns — never
 * `$row->save()`, which would try to write the generated `slug_active` column.
 *
 * @api
 */
class Tinylink_Model_Link extends Tiger_Model_Table
{
    const STATUS_ACTIVE   = 'active';
    const STATUS_DISABLED = 'disabled';

    protected $_name    = 'tinylink_link';
    protected $_primary = 'link_id';

    /**
     * One live link of this org by id, or null.
     *
     * @param  string $orgId  the org
     * @param  string $linkId the link
     * @return Zend_Db_Table_Row_Abstract|null
     */
    public function findForOrg(string $orgId, string $linkId)
    {
        if ($linkId === '') { return null; }
        return $this->fetchRow($this->activeSelect()->where('org_id = ?', $orgId)->where('link_id = ?', $linkId)) ?: null;
    }

    /**
     * One live link of this org by slug (any status — the caller decides what a disabled one means), or null.
     *
     * @param  string $orgId the org
     * @param  string $slug  the slug (case-insensitive)
     * @return Zend_Db_Table_Row_Abstract|null
     */
    public function bySlug(string $orgId, string $slug)
    {
        if ($slug === '') { return null; }
        return $this->fetchRow($this->activeSelect()->where('org_id = ?', $orgId)->where('slug = ?', strtolower($slug))) ?: null;
    }

    /**
     * Is this slug already used by another live link of the org?
     *
     * @param  string      $orgId     the org
     * @param  string      $slug      the slug
     * @param  string|null $exceptId  a link to ignore (the one being edited)
     * @return bool
     */
    public function slugTaken(string $orgId, string $slug, ?string $exceptId = null): bool
    {
        $sel = $this->activeSelect()->where('org_id = ?', $orgId)->where('slug = ?', strtolower($slug));
        if ($exceptId) { $sel->where('link_id <> ?', $exceptId); }
        return (bool) $this->fetchRow($sel);
    }

    /**
     * Count one human click: bump the counter and the last-clicked stamp in one atomic update.
     *
     * @param  string $linkId the link
     * @return void
     */
    public function countClick(string $linkId): void
    {
        $this->getAdapter()->update($this->_name, [
            'click_count'     => new Zend_Db_Expr('click_count + 1'),
            'last_clicked_at' => gmdate('Y-m-d H:i:s'),
        ], $this->getAdapter()->quoteInto('link_id = ?', $linkId));
    }

    /**
     * The admin grid for one org: totals + a page of rows.
     *
     * @param  string $orgId the org
     * @param  array  $opts  search, status, orderCol, orderDir, offset, limit
     * @return array{total:int,filtered:int,rows:array}
     */
    public function datatable(string $orgId, array $opts): array
    {
        $db     = $this->getAdapter();
        $search = (string) ($opts['search'] ?? '');
        $status = (string) ($opts['status'] ?? '');
        $limit  = max(1, min(500, (int) ($opts['limit'] ?? 25)));
        $offset = max(0, (int) ($opts['offset'] ?? 0));

        $orderCols = [0 => 'slug', 1 => 'destination', 2 => 'click_count', 3 => 'status', 4 => 'updated_at'];
        $col = (int) ($opts['orderCol'] ?? -1);
        $dir = strtoupper((string) ($opts['orderDir'] ?? '')) === 'ASC' ? 'ASC' : 'DESC';
        $order = isset($orderCols[$col]) ? $orderCols[$col] . ' ' . $dir : 'created_at DESC';

        $scope = function ($sel) use ($orgId, $status) {
            $sel->where('deleted = 0')->where('org_id = ?', $orgId);
            if (in_array($status, [self::STATUS_ACTIVE, self::STATUS_DISABLED], true)) { $sel->where('status = ?', $status); }
        };
        $searchFn = function ($sel) use ($db, $search) {
            if ($search === '') { return; }
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $search) . '%';
            $sel->where('(' . $db->quoteInto('slug LIKE ?', $like) . ' OR ' . $db->quoteInto('destination LIKE ?', $like)
                . ' OR ' . $db->quoteInto('notes LIKE ?', $like) . ')');
        };

        $count = fn() => $db->select()->from($this->_name, ['c' => new Zend_Db_Expr('COUNT(*)')]);
        $t = $count(); $scope($t);
        $f = $count(); $scope($f); $searchFn($f);

        $rows = $db->select()
            ->from($this->_name, ['link_id', 'slug', 'destination', 'redirect_type', 'merge_params', 'expires_at', 'notes',
                                  'click_count', 'last_clicked_at', 'status', 'created_at', 'updated_at'])
            ->order(new Zend_Db_Expr($order))
            ->limit($limit, $offset);
        $scope($rows); $searchFn($rows);

        return ['total' => (int) $db->fetchOne($t), 'filtered' => (int) $db->fetchOne($f), 'rows' => $db->fetchAll($rows)];
    }
}

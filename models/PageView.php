<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tinylink_Model_PageView — the links-page view log (`tinylink_page_view`): referring host + device class.
 *
 * @api
 */
class Tinylink_Model_PageView extends Tiger_Model_Table
{
    protected $_name    = 'tinylink_page_view';
    protected $_primary = 'view_id';

    /**
     * Record one view.
     *
     * @param  string      $orgId
     * @param  string      $pageId
     * @param  string|null $referrerHost
     * @param  string      $uaClass
     * @return string the view id
     */
    public function log(string $orgId, string $pageId, ?string $referrerHost, string $uaClass): string
    {
        return (string) $this->insert([
            'org_id' => $orgId, 'page_id' => $pageId, 'viewed_at' => gmdate('Y-m-d H:i:s'),
            'referrer_host' => $referrerHost !== null ? substr($referrerHost, 0, 191) : null, 'ua_class' => $uaClass,
        ]);
    }

    /**
     * A page's views over the last $days days: total human views, a daily series, top referring hosts.
     *
     * @param  string $orgId
     * @param  string $pageId
     * @param  int    $days 1..365
     * @return array{views:int,series:array,referrers:array}
     */
    public function stats(string $orgId, string $pageId, int $days = 30): array
    {
        $db    = $this->getAdapter();
        $days  = max(1, min(365, $days));
        $since = gmdate('Y-m-d 00:00:00', time() - ($days - 1) * 86400);
        $base  = fn(array $cols) => $db->select()->from($this->_name, $cols)->where('org_id = ?', $orgId)
            ->where('page_id = ?', $pageId)->where('deleted = 0')->where('viewed_at >= ?', $since)->where('ua_class <> ?', 'bot');

        $series = [];
        for ($i = $days - 1; $i >= 0; $i--) { $series[gmdate('Y-m-d', time() - $i * 86400)] = 0; }
        $day = new Zend_Db_Expr('DATE(viewed_at)');
        foreach ($db->fetchAll($base(['d' => $day, 'n' => new Zend_Db_Expr('COUNT(*)')])->group($day)) as $r) {
            if (isset($series[(string) $r['d']])) { $series[(string) $r['d']] = (int) $r['n']; }
        }
        $refs = [];
        foreach ($db->fetchAll($base(['referrer_host', 'n' => new Zend_Db_Expr('COUNT(*)')])->group('referrer_host')
                     ->order(new Zend_Db_Expr('n DESC'))->limit(10)) as $r) {
            $refs[] = ['host' => $r['referrer_host'] !== null ? (string) $r['referrer_host'] : '', 'views' => (int) $r['n']];
        }
        return [
            'views'     => array_sum($series),
            'series'    => array_map(static fn($d, $n) => ['date' => $d, 'views' => $n], array_keys($series), $series),
            'referrers' => $refs,
        ];
    }
}

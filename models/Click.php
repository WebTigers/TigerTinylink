<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tinylink_Model_Click — the local click log (`tinylink_click`). Coarse on purpose: a referring host and a
 * device class, never an IP or a raw user agent.
 *
 * @api
 */
class Tinylink_Model_Click extends Tiger_Model_Table
{
    protected $_name    = 'tinylink_click';
    protected $_primary = 'click_id';

    /**
     * Record one click.
     *
     * @param  string      $orgId        the link's org
     * @param  string      $linkId       the link
     * @param  string|null $referrerHost the referring host, or null
     * @param  string      $uaClass      desktop|mobile|tablet|bot
     * @param  string|null $pageId       the links page the tap came from, or null
     * @return string                    the click id
     */
    public function log(string $orgId, string $linkId, ?string $referrerHost, string $uaClass, ?string $pageId = null): string
    {
        return (string) $this->insert([
            'org_id'        => $orgId,
            'link_id'       => $linkId,
            'page_id'       => $pageId,
            'clicked_at'    => gmdate('Y-m-d H:i:s'),
            'referrer_host' => $referrerHost !== null ? substr($referrerHost, 0, 191) : null,
            'ua_class'      => $uaClass,
        ]);
    }

    /**
     * Human taps per link that came FROM one links page, over the last $days days.
     *
     * @param  string $orgId
     * @param  string $pageId
     * @param  int    $days 1..365
     * @return array<string,int> link_id => taps
     */
    public function tapsForPage(string $orgId, string $pageId, int $days = 30): array
    {
        $db    = $this->getAdapter();
        $since = gmdate('Y-m-d 00:00:00', time() - (max(1, min(365, $days)) - 1) * 86400);
        $out   = [];
        foreach ($db->fetchAll($db->select()->from($this->_name, ['link_id', 'n' => new Zend_Db_Expr('COUNT(*)')])
                     ->where('org_id = ?', $orgId)->where('page_id = ?', $pageId)->where('deleted = 0')
                     ->where('ua_class <> ?', 'bot')->where('clicked_at >= ?', $since)->group('link_id')) as $r) {
            $out[(string) $r['link_id']] = (int) $r['n'];
        }
        return $out;
    }

    /**
     * Click statistics for one link of one org over the last $days days: totals by device class, a daily
     * series (human clicks), and the top referring hosts.
     *
     * @param  string $orgId  the org (a link of another org yields nothing)
     * @param  string $linkId the link
     * @param  int    $days   1..365
     * @return array{total:int,humans:int,by_class:array<string,int>,series:array,referrers:array}
     */
    public function stats(string $orgId, string $linkId, int $days = 30): array
    {
        $db    = $this->getAdapter();
        $days  = max(1, min(365, $days));
        $since = gmdate('Y-m-d 00:00:00', time() - ($days - 1) * 86400);
        $base  = fn(array $cols) => $db->select()->from($this->_name, $cols)
            ->where('org_id = ?', $orgId)->where('link_id = ?', $linkId)->where('deleted = 0')->where('clicked_at >= ?', $since);

        $byClass = [];
        foreach ($db->fetchAll($base(['ua_class', 'n' => new Zend_Db_Expr('COUNT(*)')])->group('ua_class')) as $r) {
            $byClass[(string) $r['ua_class']] = (int) $r['n'];
        }
        $series = [];
        for ($i = $days - 1; $i >= 0; $i--) { $series[gmdate('Y-m-d', time() - $i * 86400)] = 0; }
        $day = new Zend_Db_Expr('DATE(clicked_at)');
        foreach ($db->fetchAll($base(['d' => $day, 'n' => new Zend_Db_Expr('COUNT(*)')])->where('ua_class <> ?', 'bot')->group($day)) as $r) {
            if (isset($series[(string) $r['d']])) { $series[(string) $r['d']] = (int) $r['n']; }
        }
        $refs = [];
        $refSel = $base(['referrer_host', 'n' => new Zend_Db_Expr('COUNT(*)')])->where('ua_class <> ?', 'bot')
            ->group('referrer_host')->order(new Zend_Db_Expr('n DESC'))->limit(10);
        foreach ($db->fetchAll($refSel) as $r) {
            $refs[] = ['host' => $r['referrer_host'] !== null ? (string) $r['referrer_host'] : '', 'clicks' => (int) $r['n']];
        }
        $total = array_sum($byClass);
        return [
            'total'     => $total,
            'humans'    => $total - (int) ($byClass['bot'] ?? 0),
            'by_class'  => $byClass,
            'series'    => array_map(static fn($d, $n) => ['date' => $d, 'clicks' => $n], array_keys($series), $series),
            'referrers' => $refs,
        ];
    }
}

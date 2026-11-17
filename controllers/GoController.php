<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tinylink_GoController — the public redirect: /l/<slug> → the link's destination (configs/routes.ini).
 *
 * Guest-reachable by design (anyone who follows a short link). It resolves the slug within the SITE's org
 * (Tiger_Model_Org::siteOrgId — never a visitor's own org), so a multi-tenant install serves each site its own
 * links. It takes no destination from the request: the only URL it can send anyone to is one an admin saved
 * and that still passes Tinylink_Service_Link::isSafeDestination — no open redirect.
 *
 * Per hit: rate limit (Tiger_RateLimit, per hashed IP) → resolve (unknown / disabled / expired = 404) → log
 * the click locally (bots logged, not counted) → redirect. The analytics event (Tiger_Analytics::track) is
 * sent AFTER the response is flushed, so a slow analytics provider never delays the visitor.
 */
class Tinylink_GoController extends Zend_Controller_Action
{
    /** Default redirects allowed per visitor IP per minute (tiger.tinylink.rate_limit overrides). */
    const DEFAULT_RATE_LIMIT = 120;

    /** The query parameter a links-page button carries to attribute its tap (stripped before the redirect). */
    const PAGE_PARAM = '_tlp';

    /**
     * Redirect, or 404.
     *
     * @return void
     * @throws Zend_Controller_Action_Exception 404 for an unknown, disabled or expired slug
     */
    public function indexAction()
    {
        $this->_helper->viewRenderer->setNoRender(true);
        if ($this->_helper->hasHelper('layout')) { $this->_helper->layout()->disableLayout(); }
        $res = $this->getResponse();

        if (!Tiger_RateLimit::hit('tinylink.redirect', Tiger_RateLimit::ipKey(), $this->_rateLimit(), 60)) {
            $res->setHttpResponseCode(429)->setHeader('Retry-After', '60', true)->setBody('Too many requests.');
            return;
        }

        $org = (string) Tiger_Model_Org::siteOrgId();
        $hit = Tinylink_Service_Link::resolve($org, (string) $this->getRequest()->getParam('slug', ''), $this->_query());
        if (!$hit) {
            throw new Zend_Controller_Action_Exception('Short link not found', 404);
        }

        $ua      = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
        $class   = Tinylink_Service_Link::uaClass($ua);
        $refHost = Tinylink_Service_Link::referrerHost((string) ($_SERVER['HTTP_REFERER'] ?? ''));
        $link    = $hit['link'];
        try {
            (new Tinylink_Model_Click())->log($org, (string) $link['link_id'], $refHost, $class, $this->_pageId($org));
            if ($class !== 'bot') {
                (new Tinylink_Model_Link())->countClick((string) $link['link_id']);
            }
        } catch (Throwable $e) {
            // Counting is best-effort — the visitor still gets where they were going.
            if (class_exists('Tiger_Log')) { Tiger_Log::warn('tinylink.click_log_failed', ['error' => $e->getMessage()]); }
        }

        $res->setHeader('Cache-Control', 'no-store', true);
        $res->setRedirect($hit['url'], $hit['code']);

        if ($class !== 'bot' && class_exists('Tiger_Analytics') && method_exists('Tiger_Analytics', 'track')) {
            $slug = (string) $link['slug'];
            register_shutdown_function(static function () use ($org, $slug, $class) {
                if (function_exists('fastcgi_finish_request')) { fastcgi_finish_request(); }   // the visitor is already on their way
                try {
                    Tiger_Analytics::track($org, 'short_link_click', ['slug' => $slug, 'label' => $slug, 'device' => $class]);
                } catch (Throwable $e) {
                    // never surfaces
                }
            });
        }
    }

    /**
     * The short link's own query parameters (GET only). The route's `slug` and the links-page tag `_tlp` are
     * TigerTinylink's own and never reach the destination.
     */
    protected function _query(): array
    {
        $q = is_array($_GET) ? $_GET : [];
        unset($q['slug'], $q[self::PAGE_PARAM]);
        return $q;
    }

    /** The links page this tap came from (a live page of the SAME org), or null — a forged tag is ignored. */
    protected function _pageId(string $org): ?string
    {
        $id = (string) ($_GET[self::PAGE_PARAM] ?? '');
        if (!preg_match('/^[0-9a-f-]{36}$/i', $id)) { return null; }
        return (new Tinylink_Model_Page())->findForOrg($org, $id) ? $id : null;
    }

    /** Redirects per IP per minute. */
    protected function _rateLimit(): int
    {
        $cfg = Zend_Registry::isRegistered('Zend_Config') ? Zend_Registry::get('Zend_Config') : null;
        $t   = $cfg ? $cfg->get('tiger') : null;
        $m   = $t ? $t->get('tinylink') : null;
        $v   = $m ? $m->get('rate_limit') : null;
        return $v !== null && is_numeric((string) $v) ? (int) (string) $v : self::DEFAULT_RATE_LIMIT;
    }
}

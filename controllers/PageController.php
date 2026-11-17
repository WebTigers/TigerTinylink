<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tinylink_PageController — the public links page (link-in-bio): /links/<handle>, and /links for the org's
 * default page (configs/routes.ini). Rendered in the active theme's public layout, so it reskins with the site.
 *
 * Guest-reachable by design. It resolves the handle within the SITE's org (Tiger_Model_Org::siteOrgId). Only
 * live, active, in-schedule blocks are shown; a link button whose short link is disabled or expired is hidden;
 * a signup or product block hides when its module is absent. Every button goes through /l/<slug>, tagged with
 * the page (`_tlp`) so the tap is counted and attributed — Tinylink_GoController strips the tag before the
 * redirect. Views are logged (bots logged, not counted) and the page is rate-limited like the redirect.
 */
class Tinylink_PageController extends Tiger_Controller_Action
{
    /**
     * Render one links page, or 404.
     *
     * @return void
     * @throws Zend_Controller_Action_Exception 404 for an unknown or disabled page
     */
    public function viewAction()
    {
        $res = $this->getResponse();
        if (!Tiger_RateLimit::hit('tinylink.page', Tiger_RateLimit::ipKey(), Tinylink_GoController::DEFAULT_RATE_LIMIT, 60)) {
            $res->setHttpResponseCode(429)->setHeader('Retry-After', '60', true);
            $this->_helper->viewRenderer->setNoRender(true);
            $res->setBody('Too many requests.');
            return;
        }

        $org    = (string) Tiger_Model_Org::siteOrgId();
        $handle = (string) $this->getRequest()->getParam('handle', '');
        $pages  = new Tinylink_Model_Page();
        $page   = $handle === '' ? $pages->defaultFor($org) : $pages->byHandle($org, $handle);
        if (!$page || (string) $page->status !== Tinylink_Model_Page::STATUS_ACTIVE) {
            throw new Zend_Controller_Action_Exception('Links page not found', 404);
        }

        $class = Tinylink_Service_Link::uaClass((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
        try {
            (new Tinylink_Model_PageView())->log($org, (string) $page->page_id,
                Tinylink_Service_Link::referrerHost((string) ($_SERVER['HTTP_REFERER'] ?? '')), $class);
            if ($class !== 'bot') { $pages->countView((string) $page->page_id); }
        } catch (Throwable $e) {
            if (class_exists('Tiger_Log')) { Tiger_Log::warn('tinylink.page_view_log_failed', ['error' => $e->getMessage()]); }
        }

        $social = [];
        foreach (json_decode((string) $page->social, true) ?: [] as $net => $url) {
            if (!isset(Tinylink_Service_Page::SOCIAL[$net])) { continue; }
            [$icon, $label] = Tinylink_Service_Page::SOCIAL[$net];
            $social[] = ['icon' => $icon, 'label' => $label, 'href' => $net === 'email' ? 'mailto:' . $url : $url];
        }

        $this->view->page      = $page->toArray();
        $this->view->avatarUrl = Tinylink_Service_Page::mediaUrl($page->avatar_media_id);
        $this->view->social    = $social;
        $this->view->blocks    = $this->_blocks($org, $page);
        $this->view->title     = (string) $page->title;
        $res->setHeader('Cache-Control', 'no-store', true);

        if (class_exists('Seo_Service_Head')) {
            Seo_Service_Head::forValues([
                'title'       => (string) $page->title,
                'description' => (string) ($page->seo_description ?: $page->bio),
                'image'       => $page->og_image_media_id ?: ($page->avatar_media_id ?: ''),
            ], $this->getRequest());
        } else {
            $this->view->headTitle((string) $page->title);
        }
    }

    /** The blocks a visitor sees right now, ready to render. */
    protected function _blocks(string $org, $page): array
    {
        $now  = time();
        $caps = Tinylink_Service_Page::capabilities();
        $out  = [];
        foreach ((new Tinylink_Model_PageItem())->forPage($org, (string) $page->page_id) as $it) {
            if ((string) $it['status'] !== 'active') { continue; }
            if ($it['show_from'] && strtotime($it['show_from'] . ' UTC') > $now) { continue; }
            if ($it['show_until'] && strtotime($it['show_until'] . ' UTC') <= $now) { continue; }
            $b = ['kind' => $it['kind'], 'label' => (string) $it['label'], 'icon' => (string) $it['icon'],
                  'featured' => (int) $it['featured'] === 1, 'thumb' => Tinylink_Service_Page::mediaUrl($it['thumbnail_media_id'])];
            switch ($it['kind']) {
                case 'link':
                    if (empty($it['link_slug']) || (string) $it['link_status'] !== Tinylink_Model_Link::STATUS_ACTIVE) { continue 2; }
                    if ($it['link_expires_at'] && strtotime($it['link_expires_at'] . ' UTC') <= $now) { continue 2; }
                    $b['href'] = '/l/' . rawurlencode((string) $it['link_slug']) . '?_tlp=' . rawurlencode((string) $page->page_id);
                    break;
                case 'signup':
                    if (!$caps['signup']) { continue 2; }
                    $b['html'] = (new Newsletter_Service_Render())->form(['title' => $b['label'] !== '' ? $b['label'] : null, 'source' => 'tinylink']);
                    break;
                case 'product':
                    if (!$caps['product']) { continue 2; }
                    $p = (new Shop_Model_Product())->bySlug($org, (string) $it['ref']);
                    if (!$p) { continue 2; }
                    $b['label'] = $b['label'] !== '' ? $b['label'] : (string) $p['title'];
                    $b['price'] = class_exists('Pay_Service_Pricing')
                        ? Pay_Service_Pricing::amountDisplay((int) ($p['price'] ?? 0), (string) ($p['currency'] ?? 'usd'))
                        : number_format(((int) ($p['price'] ?? 0)) / 100, 2);
                    $b['href'] = '/shop/' . rawurlencode((string) $it['ref']);
                    break;
                case 'header':
                case 'divider':
                    break;
                default:
                    continue 2;
            }
            $out[] = $b;
        }
        return $out;
    }
}

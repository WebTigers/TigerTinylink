<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace TigerTinylink\Tests\Integration;

use ReflectionMethod;
use TigerTinylink\Tests\Support\IntegrationTestCase;
use Tinylink_GoController;
use Tinylink_Model_Click;
use Tinylink_Model_Link;
use Tinylink_Model_Page;
use Tinylink_Model_PageView;
use Tinylink_PageController;
use Tinylink_Service_Block;
use Tinylink_Service_Page;

/**
 * Links pages (1.1.0): pages + blocks over /api, the org lane, what the public page shows, and how a tap is
 * attributed to the page it came from.
 */
final class LinksPageTest extends IntegrationTestCase
{
    private function page(array $p = []): object
    {
        return $this->call('save', $p + ['title' => 'Jane Doe', 'handle' => 'jane'], Tinylink_Service_Page::class);
    }

    private function block(array $p): object
    {
        return $this->call('save', $p, Tinylink_Service_Block::class);
    }

    // ----- pages ------------------------------------------------------------------------------

    public function testTheFirstPageIsTheDefaultAndHandlesArePerOrg(): void
    {
        $this->login(self::ORG_A);
        $a = $this->page();
        $this->assertSame(1, (int) $a->result, $this->msgs($a));
        $this->assertTrue($a->data['page']['is_default'], 'an org\'s first page is the one /links serves');
        $this->assertSame('/links/jane', $a->data['page']['path']);

        $dup = $this->page(['handle' => 'JANE']);
        $this->assertStringContainsString('tinylink.error.handle_taken', $this->msgs($dup));

        $b = $this->page(['title' => 'Promo', 'handle' => 'promo', 'is_default' => true]);
        $this->assertTrue($b->data['page']['is_default']);
        $this->assertSame($b->data['page']['page_id'], (string) (new Tinylink_Model_Page())->defaultFor(self::ORG_A)->page_id, 'one default per org');

        $this->login(self::ORG_B);
        $this->assertSame(1, (int) $this->page()->result, 'another org may use the same handle');
    }

    public function testSocialLinksAreValidatedAndAPartialUpdateKeepsTheRest(): void
    {
        $this->login(self::ORG_A);
        $id = $this->page(['bio' => 'Author.'])->data['page']['page_id'];

        $bad = $this->call('save', ['page_id' => $id, 'social' => ['instagram' => 'javascript:alert(1)']], Tinylink_Service_Page::class);
        $this->assertStringContainsString('tinylink.error.social', $this->msgs($bad));
        $bad = $this->call('save', ['page_id' => $id, 'social' => json_encode(['email' => 'not-an-email'])], Tinylink_Service_Page::class);
        $this->assertStringContainsString('tinylink.error.social', $this->msgs($bad));

        $ok = $this->call('save', ['page_id' => $id, 'social' => ['instagram' => 'https://instagram.com/jane', 'email' => 'jane@example.com', 'myspace' => 'https://x', 'x' => '']], Tinylink_Service_Page::class);
        $this->assertSame(1, (int) $ok->result, $this->msgs($ok));
        $this->assertSame(['instagram' => 'https://instagram.com/jane', 'email' => 'jane@example.com'], $ok->data['page']['social'], 'unknown networks and blanks dropped');
        $this->assertSame(['Jane Doe', 'Author.'], [$ok->data['page']['title'], $ok->data['page']['bio']], 'fields not sent are kept');
    }

    // ----- blocks -----------------------------------------------------------------------------

    public function testALinkBlockCreatesItsShortLinkAndEachKindAsksForWhatItNeeds(): void
    {
        $this->login(self::ORG_A);
        $pid = $this->page()->data['page']['page_id'];

        $res = $this->block(['page_id' => $pid, 'kind' => 'link', 'label' => 'My book', 'destination' => 'https://shop.example/book?ref=bio']);
        $this->assertSame(1, (int) $res->result, $this->msgs($res));
        $link = (new Tinylink_Model_Link())->findForOrg(self::ORG_A, (string) $res->data['item']['link_id']);
        $this->assertSame(['https://shop.example/book?ref=bio', '/links/jane'], [$link->destination, $link->notes], 'a real short link, noted with its page');
        $this->assertSame('/l/' . $link->slug, $res->data['item']['short_path']);

        $this->assertStringContainsString('tinylink.error.label_required', $this->msgs($this->block(['page_id' => $pid, 'kind' => 'link', 'destination' => 'https://x.example/'])));
        $this->assertArrayHasKey('destination', (array) $this->block(['page_id' => $pid, 'kind' => 'link', 'label' => 'x', 'destination' => 'javascript:x'])->form);
        $this->assertStringContainsString('tinylink.error.label_required', $this->msgs($this->block(['page_id' => $pid, 'kind' => 'header'])));
        $this->assertStringContainsString('tinylink.error.ref', $this->msgs($this->block(['page_id' => $pid, 'kind' => 'product'])));
        $this->assertSame(1, (int) $this->block(['page_id' => $pid, 'kind' => 'divider'])->result, 'a divider needs nothing');
    }

    public function testBlocksReorderAndAnotherOrgsIdsAreIgnored(): void
    {
        $this->login(self::ORG_A);
        $pid = $this->page()->data['page']['page_id'];
        $a = $this->block(['page_id' => $pid, 'kind' => 'header', 'label' => 'A'])->data['item']['item_id'];
        $b = $this->block(['page_id' => $pid, 'kind' => 'header', 'label' => 'B'])->data['item']['item_id'];
        $c = $this->block(['page_id' => $pid, 'kind' => 'header', 'label' => 'C'])->data['item']['item_id'];

        $this->login(self::ORG_B);
        $foreign = $this->block(['page_id' => $this->page()->data['page']['page_id'], 'kind' => 'header', 'label' => 'X'])->data['item']['item_id'];

        $this->login(self::ORG_A);
        $res = $this->call('reorder', ['page_id' => $pid, 'item_ids' => [$c, $foreign, $a]], Tinylink_Service_Block::class);
        $this->assertSame(1, (int) $res->result, $this->msgs($res));
        $labels = array_column($this->call('get', ['page_id' => $pid], Tinylink_Service_Page::class)->data['items'], 'label');
        $this->assertSame(['C', 'A', 'B'], $labels, 'listed first in order; the unlisted one keeps its place after; the foreign id is ignored');
    }

    public function testAnotherOrgsPageAndBlocksAreOutOfReach(): void
    {
        $this->login(self::ORG_B);
        $theirPage = $this->page()->data['page']['page_id'];
        $theirItem = $this->block(['page_id' => $theirPage, 'kind' => 'header', 'label' => 'Theirs'])->data['item']['item_id'];

        $this->login(self::ORG_A);
        foreach (['get', 'stats'] as $m) {
            $this->assertStringContainsString('tinylink.error.page_not_found', $this->msgs($this->call($m, ['page_id' => $theirPage], Tinylink_Service_Page::class)), $m);
        }
        $this->assertStringContainsString('tinylink.error.page_not_found', $this->msgs($this->call('save', ['page_id' => $theirPage, 'title' => 'Hijack'], Tinylink_Service_Page::class)));
        $this->assertStringContainsString('tinylink.error.page_not_found', $this->msgs($this->call('delete', ['page_id' => $theirPage], Tinylink_Service_Page::class)));
        $this->assertStringContainsString('tinylink.error.page_not_found', $this->msgs($this->block(['page_id' => $theirPage, 'kind' => 'header', 'label' => 'Injected'])));
        $this->assertStringContainsString('tinylink.error.item_not_found', $this->msgs($this->block(['item_id' => $theirItem, 'label' => 'Hijack'])));
        $this->assertStringContainsString('tinylink.error.item_not_found', $this->msgs($this->call('delete', ['item_id' => $theirItem], Tinylink_Service_Block::class)));
        $this->assertSame('Jane Doe', (string) (new Tinylink_Model_Page())->findForOrg(self::ORG_B, $theirPage)->title);

        $foreignLink = $this->makeLink(self::ORG_B, 'their-link');
        $mine = $this->page(['handle' => 'mine'])->data['page']['page_id'];
        $this->assertStringContainsString('tinylink.error.not_found', $this->msgs($this->block(['page_id' => $mine, 'kind' => 'link', 'label' => 'x', 'link_id' => $foreignLink])),
            'a block cannot point at another org\'s short link');
    }

    // ----- the public page --------------------------------------------------------------------

    public function testThePublicPageShowsOnlyLiveBlocksAndTagsTapsWithThePage(): void
    {
        $this->login(self::ORG_A);
        $pid = $this->page()->data['page']['page_id'];
        $show = $this->block(['page_id' => $pid, 'kind' => 'link', 'label' => 'Live', 'destination' => 'https://a.example/'])->data['item'];
        $hidden = $this->block(['page_id' => $pid, 'kind' => 'link', 'label' => 'Hidden', 'destination' => 'https://b.example/'])->data['item'];
        $this->call('setStatus', ['item_id' => $hidden['item_id'], 'status' => 'disabled'], Tinylink_Service_Block::class);
        $this->block(['page_id' => $pid, 'kind' => 'link', 'label' => 'Later', 'destination' => 'https://c.example/', 'show_from' => gmdate('Y-m-d H:i', time() + 86400)]);
        $this->block(['page_id' => $pid, 'kind' => 'link', 'label' => 'Over', 'destination' => 'https://d.example/', 'show_until' => gmdate('Y-m-d H:i', time() - 3600)]);
        $off = $this->block(['page_id' => $pid, 'kind' => 'link', 'label' => 'LinkOff', 'destination' => 'https://e.example/'])->data['item'];
        $this->call('setStatus', ['link_id' => $off['link_id'], 'status' => 'disabled']);   // its short link disabled
        $this->block(['page_id' => $pid, 'kind' => 'header', 'label' => 'More']);

        $ctl = (new \ReflectionClass(Tinylink_PageController::class))->newInstanceWithoutConstructor();
        $m   = new ReflectionMethod(Tinylink_PageController::class, '_blocks');
        $blocks = $m->invoke($ctl, self::ORG_A, (new Tinylink_Model_Page())->findForOrg(self::ORG_A, $pid));
        $this->assertSame(['Live', 'More'], array_column($blocks, 'label'), 'hidden, not-yet, ended and disabled-link blocks are not shown');
        $this->assertSame($show['short_path'] . '?_tlp=' . $pid, $blocks[0]['href'], 'a button goes through /l/, tagged with the page');
    }

    public function testATapIsAttributedOnlyToALivePageOfTheSameOrgAndTheTagNeverReachesTheDestination(): void
    {
        $this->login(self::ORG_A);
        $pid = $this->page()->data['page']['page_id'];
        $this->login(self::ORG_B);
        $foreign = $this->page()->data['page']['page_id'];

        $ctl  = (new \ReflectionClass(Tinylink_GoController::class))->newInstanceWithoutConstructor();
        $page = new ReflectionMethod(Tinylink_GoController::class, '_pageId');
        $qry  = new ReflectionMethod(Tinylink_GoController::class, '_query');
        $was  = $_GET;
        try {
            $_GET = ['_tlp' => $pid, 'utm_source' => 'bio', 'slug' => 'x'];
            $this->assertSame($pid, $page->invoke($ctl, self::ORG_A));
            $this->assertSame(['utm_source' => 'bio'], $qry->invoke($ctl), 'the page tag and the route slug are not passed on');
            $_GET = ['_tlp' => $foreign];
            $this->assertNull($page->invoke($ctl, self::ORG_A), 'another org\'s page id is ignored');
            $_GET = ['_tlp' => 'not-a-uuid'];
            $this->assertNull($page->invoke($ctl, self::ORG_A));
        } finally {
            $_GET = $was;
        }
    }

    public function testPageStatsCountViewsAndTapsPerButton(): void
    {
        $this->login(self::ORG_A);
        $pid  = $this->page()->data['page']['page_id'];
        $item = $this->block(['page_id' => $pid, 'kind' => 'link', 'label' => 'Book', 'destination' => 'https://a.example/'])->data['item'];
        $views = new Tinylink_Model_PageView();
        $views->log(self::ORG_A, $pid, 'instagram.com', 'mobile');
        $views->log(self::ORG_A, $pid, 'instagram.com', 'mobile');
        $views->log(self::ORG_A, $pid, null, 'bot');
        $clicks = new Tinylink_Model_Click();
        $clicks->log(self::ORG_A, $item['link_id'], 'example.com', 'mobile', $pid);
        $clicks->log(self::ORG_A, $item['link_id'], null, 'desktop', null);   // a direct visit, not from the page

        $s = $this->call('stats', ['page_id' => $pid], Tinylink_Service_Page::class)->data['stats'];
        $this->assertSame([2, 1, 50.0], [$s['views'], $s['taps'], $s['tap_rate']], 'bots are not views; direct visits are not page taps');
        $this->assertSame([['item_id' => $item['item_id'], 'label' => 'Book', 'taps' => 1]], $s['items']);
        $this->assertSame(['host' => 'instagram.com', 'views' => 2], $s['referrers'][0]);
    }
}

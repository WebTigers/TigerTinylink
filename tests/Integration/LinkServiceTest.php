<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace TigerTinylink\Tests\Integration;

use TigerTinylink\Tests\Support\IntegrationTestCase;
use Tinylink_Model_Click;
use Tinylink_Model_Link;
use Tinylink_Service_Link;

/**
 * Tinylink_Service_Link against the real schema: create/update/disable/delete, the slug rules, the destination
 * guard, the org lane, and what the public redirect resolves.
 */
final class LinkServiceTest extends IntegrationTestCase
{
    // ----- create / update --------------------------------------------------------------------

    public function testCreateWithAGeneratedSlugAndWithAChosenOne(): void
    {
        $this->login(self::ORG_A);
        $res = $this->call('save', ['destination' => 'https://example.com/landing?utm_campaign=spring']);
        $this->assertSame(1, (int) $res->result, $this->msgs($res));
        $this->assertMatchesRegularExpression('/^[a-z2-9]{7}$/', $res->data['link']['slug']);
        $this->assertSame(302, $res->data['link']['redirect_type']);
        $this->assertTrue($res->data['link']['merge_params']);

        $res = $this->call('save', ['destination' => 'https://example.com/', 'slug' => 'Spring-Sale', 'redirect_type' => '301']);
        $this->assertSame(1, (int) $res->result, $this->msgs($res));
        $this->assertSame('spring-sale', $res->data['link']['slug'], 'slugs are stored lower-case');
        $this->assertSame(301, $res->data['link']['redirect_type']);
    }

    public function testATakenSlugIsRefusedUntilItsLinkIsDeleted(): void
    {
        $this->login(self::ORG_A);
        $first = $this->call('save', ['destination' => 'https://a.example/', 'slug' => 'promo'])->data['link']['link_id'];
        $res = $this->call('save', ['destination' => 'https://b.example/', 'slug' => 'PROMO']);
        $this->assertSame(0, (int) $res->result);
        $this->assertStringContainsString('tinylink.error.slug_taken', $this->msgs($res));

        $this->assertSame(1, (int) $this->call('delete', ['link_id' => $first])->result);
        $this->assertSame(1, (int) $this->call('save', ['destination' => 'https://b.example/', 'slug' => 'promo'])->result, 'a deleted link frees its slug');

        $this->login(self::ORG_B);
        $this->assertSame(1, (int) $this->call('save', ['destination' => 'https://c.example/', 'slug' => 'promo'])->result, 'slugs are per org');
    }

    public function testUnsafeDestinationsAndBadSlugsAreFieldErrors(): void
    {
        $this->login(self::ORG_A);
        foreach (['javascript:alert(1)', '//evil.example/', '/admin', 'https://user:pw@example.com/', 'ftp://x.example/'] as $bad) {
            $res = $this->call('save', ['destination' => $bad]);
            $this->assertSame(0, (int) $res->result, $bad);
            $this->assertArrayHasKey('destination', (array) $res->form, $bad . ' is refused on the destination field');
        }
        $res = $this->call('save', ['destination' => 'https://ok.example/', 'slug' => '../etc']);
        $this->assertArrayHasKey('slug', (array) $res->form);
        $this->assertSame(0, (int) $this->db->fetchOne('SELECT COUNT(*) FROM tinylink_link'), 'nothing was written');
    }

    public function testAPartialUpdateChangesOnlyWhatWasSent(): void
    {
        $this->login(self::ORG_A);
        $id = $this->makeLink(self::ORG_A, 'keep', 'https://old.example/', ['notes' => 'n1']);
        $res = $this->call('save', ['link_id' => $id, 'destination' => 'https://new.example/']);
        $this->assertSame(1, (int) $res->result, $this->msgs($res));
        $row = (new Tinylink_Model_Link())->findById($id);
        $this->assertSame(['https://new.example/', 'keep', 'n1', 1], [$row->destination, $row->slug, $row->notes, (int) $row->merge_params]);

        $this->call('save', ['link_id' => $id, 'merge_params' => false]);   // a JSON boolean, as an MCP client sends
        $this->assertSame(0, (int) (new Tinylink_Model_Link())->findById($id)->merge_params);
    }

    // ----- org lane + ACL ---------------------------------------------------------------------

    public function testAnotherOrgsLinkCannotBeReadChangedOrDeleted(): void
    {
        $theirs = $this->makeLink(self::ORG_B, 'theirs', 'https://b.example/');
        $this->login(self::ORG_A);
        foreach ([['get', []], ['save', ['destination' => 'https://hijack.example/']], ['setStatus', ['status' => 'disabled']],
                  ['delete', []], ['stats', []]] as [$m, $p]) {
            $res = $this->call($m, ['link_id' => $theirs] + $p);
            $this->assertSame(0, (int) $res->result, $m);
            $this->assertStringContainsString('tinylink.error.not_found', $this->msgs($res), $m);
        }
        $row = (new Tinylink_Model_Link())->findById($theirs);
        $this->assertSame(['https://b.example/', 'active', 0], [$row->destination, $row->status, (int) $row->deleted]);

        $grid = $this->call('datatable', ['draw' => 1]);
        $this->assertSame(0, (int) $grid->data['recordsTotal'], 'the grid shows only the caller\'s org');
    }

    public function testANonAdminIsRefused(): void
    {
        $this->login(self::ORG_A, 'user');
        $res = $this->call('save', ['destination' => 'https://example.com/']);
        $this->assertSame(0, (int) $res->result);
        $this->assertStringContainsString('not_allowed', $this->msgs($res));
    }

    // ----- what the redirect resolves ---------------------------------------------------------

    public function testResolveServesOnlyLiveActiveUnexpiredLinksOfThatOrg(): void
    {
        $this->makeLink(self::ORG_A, 'go', 'https://example.com/p?a=1#x');
        $this->makeLink(self::ORG_A, 'off', 'https://example.com/', ['status' => 'disabled']);
        $this->makeLink(self::ORG_A, 'old', 'https://example.com/', ['expires_at' => gmdate('Y-m-d H:i:s', time() - 60)]);
        $this->makeLink(self::ORG_A, 'fixed', 'https://example.com/?a=1', ['merge_params' => 0, 'redirect_type' => 301]);
        $this->makeLink(self::ORG_B, 'theirs', 'https://b.example/');

        $hit = Tinylink_Service_Link::resolve(self::ORG_A, 'GO', ['utm_source' => 'ig', 'a' => '2']);
        $this->assertSame('https://example.com/p?a=2&utm_source=ig#x', $hit['url']);
        $this->assertSame(302, $hit['code']);

        $fixed = Tinylink_Service_Link::resolve(self::ORG_A, 'fixed', ['utm_source' => 'ig']);
        $this->assertSame(['https://example.com/?a=1', 301], [$fixed['url'], $fixed['code']], 'merge off: the short link\'s params are ignored');

        foreach (['off', 'old', 'nope', 'theirs', '../x'] as $slug) {
            $this->assertNull(Tinylink_Service_Link::resolve(self::ORG_A, $slug), $slug . ' is a 404');
        }
        $this->db->update('tinylink_link', ['destination' => 'javascript:alert(1)'], "slug = 'go'");
        $this->assertNull(Tinylink_Service_Link::resolve(self::ORG_A, 'go'), 'a stored destination that is no longer safe is never served');
    }

    public function testClicksAreCountedAndBotsAreLoggedButNotCounted(): void
    {
        $id = $this->makeLink(self::ORG_A, 'count');
        $links = new Tinylink_Model_Link();
        $log   = new Tinylink_Model_Click();
        foreach ([['instagram.com', 'mobile'], ['instagram.com', 'desktop'], [null, 'bot']] as [$ref, $class]) {
            $log->log(self::ORG_A, $id, $ref, $class);
            if ($class !== 'bot') { $links->countClick($id); }
        }
        $this->assertSame(2, (int) $links->findById($id)->click_count);

        $this->login(self::ORG_A);
        $s = $this->call('stats', ['link_id' => $id])->data['stats'];
        $this->assertSame([3, 2], [$s['total'], $s['humans']]);
        $this->assertSame(['host' => 'instagram.com', 'clicks' => 2], $s['referrers'][0]);
        $this->assertSame(2, end($s['series'])['clicks'], 'today\'s human clicks');
        $this->assertCount(30, $s['series']);
    }
}

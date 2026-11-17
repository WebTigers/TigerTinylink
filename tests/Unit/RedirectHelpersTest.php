<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace TigerTinylink\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tinylink_Form_Link;
use Tinylink_Service_Link;

/**
 * The redirect's pure helpers: how the destination URL is built from the short link's parameters, which
 * destinations are allowed at all (no open redirect, no javascript:), and the coarse click classification.
 */
final class RedirectHelpersTest extends TestCase
{
    public function testParametersAreKeptMergedAndTheShortLinkWins(): void
    {
        $this->assertSame('https://ex.com/p?a=1', Tinylink_Service_Link::buildUrl('https://ex.com/p?a=1', []), 'no query: the destination untouched');
        $this->assertSame('https://ex.com/p?a=1&utm_source=x', Tinylink_Service_Link::buildUrl('https://ex.com/p?a=1', ['utm_source' => 'x']));
        $this->assertSame('https://ex.com/p?a=2', Tinylink_Service_Link::buildUrl('https://ex.com/p?a=1', ['a' => '2']), 'the short link replaces a same-named param');
        $this->assertSame('https://ex.com/p?b=1#top', Tinylink_Service_Link::buildUrl('https://ex.com/p#top', ['b' => '1']), 'the fragment stays last');
        $this->assertSame('https://ex.com/p?q=a%20b', Tinylink_Service_Link::buildUrl('https://ex.com/p', ['q' => 'a b']), 'values are encoded');
    }

    public static function destinations(): array
    {
        return [
            'https'          => ['https://example.com/a?b=c', true],
            'http'           => ['http://example.com', true],
            'javascript'     => ['javascript:alert(1)', false],
            'data'           => ['data:text/html,<b>x</b>', false],
            'protocol-rel'   => ['//evil.example/x', false],
            'bare path'      => ['/admin', false],
            'ftp'            => ['ftp://example.com/f', false],
            'credentials'    => ['https://user:pw@example.com/', false],
            'whitespace'     => ["https://example.com/\nLocation: x", false],
            'empty'          => ['', false],
            'too long'       => ['https://example.com/' . str_repeat('a', 2100), false],
        ];
    }

    #[DataProvider('destinations')]
    public function testOnlyAbsoluteHttpDestinationsAreAllowed(string $url, bool $ok): void
    {
        $this->assertSame($ok, Tinylink_Service_Link::isSafeDestination($url));
    }

    public function testClicksAreClassifiedCoarselyAndPreviewersAreBots(): void
    {
        $this->assertSame('bot', Tinylink_Service_Link::uaClass('Slackbot-LinkExpanding 1.0 (+https://api.slack.com/robots)'));
        $this->assertSame('bot', Tinylink_Service_Link::uaClass('facebookexternalhit/1.1'));
        $this->assertSame('bot', Tinylink_Service_Link::uaClass(''));
        $this->assertSame('mobile', Tinylink_Service_Link::uaClass('Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Mobile/15E148'));
        $this->assertSame('tablet', Tinylink_Service_Link::uaClass('Mozilla/5.0 (iPad; CPU OS 17_0 like Mac OS X)'));
        $this->assertSame('desktop', Tinylink_Service_Link::uaClass('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) Safari/605.1.15'));
        $this->assertSame('instagram.com', Tinylink_Service_Link::referrerHost('https://www.Instagram.com/p/abc?x=1'));
        $this->assertNull(Tinylink_Service_Link::referrerHost(''));
    }

    public function testTheSlugPatternMatchesTheRoute(): void
    {
        $route = parse_ini_file(dirname(__DIR__, 2) . '/configs/routes.ini', true)['production']['resources.router.routes.go.reqs.slug'];
        foreach (['spring', 'Spring-2026', 'a', 'x_y'] as $ok) {
            $this->assertMatchesRegularExpression(Tinylink_Form_Link::SLUG_PATTERN, $ok);
            $this->assertMatchesRegularExpression('#^' . $route . '$#', $ok);
        }
        foreach (['-lead', '', 'a b', str_repeat('a', 65), '../x'] as $bad) {
            $this->assertDoesNotMatchRegularExpression(Tinylink_Form_Link::SLUG_PATTERN, $bad);
        }
    }
}

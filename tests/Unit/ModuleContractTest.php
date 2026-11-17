<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace TigerTinylink\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Tiger_OpenApi_Generator;
use Zend_Config_Ini;

/**
 * The module's contract with the platform: the ACL loads in production with one rule per /api method, every
 * operation declares its input (so MCP tools are typed), the MCP scope offer ships, the manifest matches, and
 * every locale carries every key.
 */
final class ModuleContractTest extends TestCase
{
    private function root(): string { return dirname(__DIR__, 2); }

    public function testTheAclLoadsInProductionAndNamesEveryOperation(): void
    {
        $file = $this->root() . '/configs/acl.ini';
        $prod = (new Zend_Config_Ini($file, 'production'))->get('acl');
        $this->assertNotNull($prod);
        foreach (['staging', 'testing', 'development'] as $env) {
            $this->assertSame($prod->toArray(), (new Zend_Config_Ini($file, $env))->get('acl')->toArray());
        }
        $granted = [];
        foreach ($prod->rules as $rule) {
            $this->assertStringStartsWith('Tinylink_', (string) $rule->resource);
            if ((string) $rule->resource === 'Tinylink_Service_Link') {
                $this->assertNotEmpty((string) $rule->privilege, 'the service is granted per method, never blanket');
                $this->assertNotSame('guest', (string) $rule->role, 'no service method is public');
                $granted[] = (string) $rule->privilege;
            }
        }
        $gen = new Tiger_OpenApi_Generator();
        $ops = array_map(static fn($k) => explode('/', $k)[2], array_keys($gen->schemasByOp($gen->discover([$this->root() . '/services']))));
        sort($ops); sort($granted);
        $this->assertSame($ops, $granted, 'every operation has exactly one ACL rule');
    }

    public function testEveryOperationDeclaresItsInput(): void
    {
        $gen = new Tiger_OpenApi_Generator();
        $this->assertSame([], $gen->untyped($gen->discover([$this->root() . '/services'])));
    }

    public function testTheMcpScopeOfferAndManifestShip(): void
    {
        $offer = parse_ini_file($this->root() . '/configs/mcp-scope.ini', true)['tinylink'] ?? null;
        $this->assertSame('tinylink', $offer['module'] ?? null);
        $m = json_decode((string) file_get_contents($this->root() . '/module.json'), true);
        $this->assertSame(['tinylink', 'Tinylink', 'free', 'BSD-3-Clause'], [$m['slug'], $m['namespace'], $m['pricing']['model'], $m['license']]);
    }

    public function testEveryLocaleHasEveryKey(): void
    {
        $en = require $this->root() . '/languages/en/tinylink.php';
        foreach (['es', 'pt', 'hi', 'de', 'fr'] as $l) {
            $other = require $this->root() . "/languages/$l/tinylink.php";
            $this->assertSame([], array_values(array_diff(array_keys($en), array_keys($other))), "$l is missing keys");
            $this->assertSame([], array_values(array_diff(array_keys($other), array_keys($en))), "$l has extra keys");
        }
    }
}

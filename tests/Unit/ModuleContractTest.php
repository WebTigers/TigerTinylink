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
            if (strpos((string) $rule->resource, 'Tinylink_Service_') === 0) {
                $this->assertNotEmpty((string) $rule->privilege, 'a service is granted per method, never blanket');
                $this->assertNotSame('guest', (string) $rule->role, 'no service method is public');
                $granted[] = strtolower(substr((string) $rule->resource, strlen('Tinylink_Service_'))) . '/' . (string) $rule->privilege;
            }
        }
        $gen = new Tiger_OpenApi_Generator();
        $ops = array_map(static function ($k) { $p = explode('/', $k); return $p[1] . '/' . $p[2]; },
            array_keys($gen->schemasByOp($gen->discover([$this->root() . '/services']))));
        sort($ops); sort($granted);
        $this->assertSame($ops, $granted, 'every operation has exactly one ACL rule');
        foreach ($ops as $op) {
            $this->assertMatchesRegularExpression('#^[a-z]+/#', $op, 'a one-word service name: the /api + MCP name must round-trip to the class');
        }
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

    public function testRuntimeCodeNeverNamesAControllerClass(): void
    {
        // ZF1's module autoloader does not load controllers, so `Tinylink_FooController::X` from any other class
        // is a fatal at runtime — while this suite's bootstrap DOES load them, so tests would pass (it 500'd the
        // links page on a real install once). Shared constants live on a service.
        foreach (['controllers', 'services', 'models', 'forms', 'views'] as $dir) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root() . '/' . $dir, \FilesystemIterator::SKIP_DOTS)) as $f) {
                if (!$f->isFile()) { continue; }
                $this->assertDoesNotMatchRegularExpression('/Tinylink_\w+Controller::/', (string) file_get_contents($f->getPathname()), $f->getFilename());
            }
        }
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

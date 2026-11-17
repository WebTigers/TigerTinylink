<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace TigerTinylink\Tests\Support;

use PHPUnit\Framework\TestCase;
use Tiger_Model_Config;
use Tiger_Model_Org;
use Tiger_Model_Table;
use Zend_Auth;
use Zend_Auth_Storage_NonPersistent;
use Zend_Db;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Table_Abstract;
use Zend_Registry;

/**
 * Base for TigerTinylink integration tests. The schema is core's `org` + `config` tables (from the sibling
 * tiger-core's migrations) plus every TigerTinylink migration, applied once per process (drop-all-then-
 * migrate, filename order). The services run their own transactions, so each test starts and ends on emptied
 * tables. Self-skips when TIGER_TEST_DB_NAME is unset.
 *
 *   TIGER_TEST_DB_NAME (required)  TIGER_TEST_DB_SOCKET (overrides host/port)
 *   TIGER_TEST_DB_HOST/_PORT (default 127.0.0.1/3306)  TIGER_TEST_DB_USER/_PASS (default root/'')
 */
abstract class IntegrationTestCase extends TestCase
{
    const ORG_A = '00000000-0000-4000-8000-0000000000aa';
    const ORG_B = '00000000-0000-4000-8000-0000000000bb';

    const CORE_MIGRATIONS = ['0001_create_org.php', '0009_create_config.php'];
    const TABLES          = ['tinylink_click', 'tinylink_link', 'config', 'org'];

    private static ?Zend_Db_Adapter_Abstract $sharedDb = null;
    private static bool $schemaReady = false;

    protected Zend_Db_Adapter_Abstract $db;

    protected function setUp(): void
    {
        parent::setUp();
        $name = getenv('TIGER_TEST_DB_NAME');
        if ($name === false || $name === '') {
            $this->markTestSkipped('Integration DB not configured (set TIGER_TEST_DB_NAME to run).');
        }
        $this->db = self::adapter((string) $name);
        $this->ensureSchema();
        self::ensureAcl();

        Zend_Auth::getInstance()->setStorage(new Zend_Auth_Storage_NonPersistent());
        Zend_Registry::set('tiger.auth.stateless', true);   // an in-process call: the Bearer/MCP shape (no CSRF)
        Zend_Registry::set('Zend_Config', new \Zend_Config(['tiger' => ['log' => ['writer' => 'null'], 'site' => ['org_id' => self::ORG_A]]], true));
        Tiger_Model_Config::resetCache();
        Tiger_Model_Table::setActor(null);
        Tiger_Model_Org::setSiteOrgId(null);
        $this->truncateAll();
        foreach ([self::ORG_A => 'Org A', self::ORG_B => 'Org B'] as $id => $n) {
            $this->db->insert('org', ['org_id' => $id, 'name' => $n, 'slug' => strtolower(str_replace(' ', '-', $n)),
                'status' => 'active', 'deleted' => 0, 'created_at' => gmdate('Y-m-d H:i:s')]);
        }
    }

    protected function tearDown(): void
    {
        if (isset($this->db)) { $this->truncateAll(); }
        Zend_Auth::getInstance()->clearIdentity();
        $reg = Zend_Registry::getInstance();
        if ($reg->offsetExists('tiger.auth.stateless')) { $reg->offsetUnset('tiger.auth.stateless'); }
        Tiger_Model_Org::setSiteOrgId(null);
        parent::tearDown();
    }

    protected function truncateAll(): void
    {
        foreach (self::TABLES as $t) { $this->db->query("DELETE FROM `$t`"); }
        Tiger_Model_Config::resetCache();
    }

    /** Sign in (non-persistent storage) as $role of $orgId. */
    protected function login(string $orgId, string $role = 'admin', string $userId = '00000000-0000-4000-8000-0000000000ff'): void
    {
        Zend_Auth::getInstance()->getStorage()->write((object) ['org_id' => $orgId, 'user_id' => $userId, 'role' => $role]);
    }

    /** Run a service method in process, as the signed-in identity. */
    protected function call(string $method, array $params = []): object
    {
        return (new \Tinylink_Service_Link(['action' => $method] + $params))->getResponse();
    }

    /** The messages of a response, as one string. */
    protected function msgs(object $res): string
    {
        return json_encode($res->messages) . json_encode($res->form);
    }

    /** A link row straight through the model. */
    protected function makeLink(string $orgId, string $slug, string $dest = 'https://example.com/page?a=1', array $extra = []): string
    {
        return (string) (new \Tinylink_Model_Link())->insert($extra + ['org_id' => $orgId, 'slug' => $slug, 'destination' => $dest,
            'status' => 'active', 'redirect_type' => 302, 'merge_params' => 1]);
    }

    private static function ensureAcl(): void
    {
        if (Zend_Registry::isRegistered('Zend_Acl')) { return; }
        @mkdir(APPLICATION_PATH . '/modules', 0777, true);
        $link = APPLICATION_PATH . '/modules/tinylink';
        if (!is_link($link) && !is_dir($link)) { @symlink(dirname(__DIR__, 2), $link); }
        Zend_Registry::set('Zend_Acl', new \Tiger_Acl_Acl());
    }

    private static function adapter(string $name): Zend_Db_Adapter_Abstract
    {
        if (self::$sharedDb === null) {
            $cfg = ['dbname' => $name, 'username' => getenv('TIGER_TEST_DB_USER') ?: 'root',
                    'password' => getenv('TIGER_TEST_DB_PASS') ?: '', 'charset' => 'utf8mb4'];
            $socket = getenv('TIGER_TEST_DB_SOCKET');
            if ($socket) {
                $cfg['unix_socket'] = $socket;
            } else {
                $cfg['host'] = getenv('TIGER_TEST_DB_HOST') ?: '127.0.0.1';
                $cfg['port'] = (int) (getenv('TIGER_TEST_DB_PORT') ?: 3306);
            }
            self::$sharedDb = Zend_Db::factory('Pdo_Mysql', $cfg);
            Zend_Db_Table_Abstract::setDefaultAdapter(self::$sharedDb);
            Zend_Registry::set('Zend_Db', self::$sharedDb);
        }
        return self::$sharedDb;
    }

    private function ensureSchema(): void
    {
        if (self::$schemaReady) { return; }
        $files = [];
        foreach (self::CORE_MIGRATIONS as $f) { $files[] = TIGER_CORE_PATH . '/migrations/' . $f; }
        $mine = glob(dirname(__DIR__, 2) . '/migrations/*.php') ?: [];
        sort($mine);
        foreach (self::TABLES as $t) { $this->db->query("DROP TABLE IF EXISTS `$t`"); }
        foreach (array_merge($files, $mine) as $file) {
            foreach ((require $file)['up'] as $stmt) {
                if (!is_string($stmt) && is_callable($stmt)) { $stmt($this->db); continue; }
                $this->db->query($stmt);
            }
        }
        self::$schemaReady = true;
    }
}

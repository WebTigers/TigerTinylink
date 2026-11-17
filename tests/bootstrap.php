<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * PHPUnit bootstrap for TigerTinylink.
 *
 * The module's `Tinylink_*` classes extend `Tiger_*` bases. Load a tiger-core checkout's autoloader
 * (Tiger_*, Zend_* via tigerzf, PHPUnit) and register a small `Tinylink_*` loader for the ZF1 module layout.
 * Resolve tiger-core via: $TIGER_CORE_VENDOR → $TIGER_CORE_PATH/vendor → a sibling ../tiger-core.
 */

error_reporting(E_ALL);

if (!defined('APPLICATION_ENV')) {
    define('APPLICATION_ENV', getenv('APPLICATION_ENV') ?: 'testing');
}

$moduleRoot = dirname(__DIR__);
if (!defined('APPLICATION_ROOT')) {
    define('APPLICATION_ROOT', sys_get_temp_dir() . '/tinylink-test-' . getmypid());
}
if (!defined('APPLICATION_PATH')) {
    define('APPLICATION_PATH', APPLICATION_ROOT . '/application');
}

$candidates = array_filter([
    getenv('TIGER_CORE_VENDOR') ?: null,
    getenv('TIGER_CORE_PATH') ? rtrim(getenv('TIGER_CORE_PATH'), '/') . '/vendor/autoload.php' : null,
    $moduleRoot . '/../tiger-core/vendor/autoload.php',
]);
$coreVendor = '';
foreach ($candidates as $c) {
    if (is_file($c)) { $coreVendor = $c; break; }
}
if ($coreVendor === '') {
    fwrite(STDERR, "\nTigerTinylink tests need tiger-core's autoloader (Tiger_*/Zend_*/PHPUnit).\n"
        . "Set TIGER_CORE_VENDOR to a tiger-core checkout's vendor/autoload.php, or place tiger-core as a\n"
        . "sibling of TigerTinylink and run `composer install` in it.\n\n");
    exit(1);
}
require $coreVendor;

$coreRoot = dirname($coreVendor, 2);
if (!defined('TIGER_CORE_PATH')) {
    define('TIGER_CORE_PATH', $coreRoot);
}

set_include_path(implode(PATH_SEPARATOR, array_filter([
    $coreRoot . '/vendor/webtigers/tigerzf/library',
    $coreRoot . '/library',
    get_include_path(),
])));

spl_autoload_register(static function ($class) use ($moduleRoot) {
    if (strncmp($class, 'Tinylink_', 9) !== 0) { return; }
    if ($class === 'Tinylink_Bootstrap') {
        $file = $moduleRoot . '/Bootstrap.php';
    } elseif (preg_match('/^Tinylink_Service_(.+)$/', $class, $m)) {
        $file = $moduleRoot . '/services/' . str_replace('_', '/', $m[1]) . '.php';
    } elseif (preg_match('/^Tinylink_Model_(.+)$/', $class, $m)) {
        $file = $moduleRoot . '/models/' . str_replace('_', '/', $m[1]) . '.php';
    } elseif (preg_match('/^Tinylink_Form_(.+)$/', $class, $m)) {
        $file = $moduleRoot . '/forms/' . str_replace('_', '/', $m[1]) . '.php';
    } elseif (preg_match('/^Tinylink_(.+)Controller$/', $class, $m)) {
        $file = $moduleRoot . '/controllers/' . $m[1] . 'Controller.php';
    } else {
        $file = '';
    }
    if ($file !== '' && is_file($file)) { require $file; }
});

spl_autoload_register(static function ($class) {
    $prefix = 'TigerTinylink\\Tests\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) { return; }
    $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) { require $file; }
});

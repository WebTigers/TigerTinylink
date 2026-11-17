<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * TigerTinylink module bootstrap.
 *
 * Adds the "Short links" item to the admin sidebar (ACL-gated in the menu). The public redirect is a plain
 * route in configs/routes.ini (`/l/:slug` → Tinylink_GoController), so activation touches no web-server
 * config and registers no front-controller plugin (ARCHITECTURE §6).
 *
 * Extending Zend_Application_Module_Bootstrap gives the module its resource autoloader, so
 * Tinylink_Service_* (services/), Tinylink_Model_* (models/) and Tinylink_Form_* (forms/) load by convention.
 */
class Tinylink_Bootstrap extends Zend_Application_Module_Bootstrap
{
    /** The admin sidebar group: Short links › Links, Links pages. */
    protected function _initAdminNav()
    {
        if (!class_exists('Tiger_Admin_Nav')) { return; }
        Tiger_Admin_Nav::register([
            'key'      => 'tinylink',
            'label'    => 'tinylink.nav.label',
            'icon'     => 'fa-link',
            'order'    => 24,
            'children' => [
                ['key' => 'tinylink-links', 'label' => 'tinylink.nav.links', 'icon' => 'fa-link',
                 'href' => '/tinylink/admin', 'resource' => 'Tinylink_AdminController'],
                ['key' => 'tinylink-pages', 'label' => 'tinylink.nav.pages', 'icon' => 'fa-address-card',
                 'href' => '/tinylink/pages', 'resource' => 'Tinylink_PagesController'],
            ],
        ]);
    }
}

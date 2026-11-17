<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Create tinylink_page_item — one block on a links page, in `sort` order.
 *
 * `kind`: link (a button — `link_id` is a tinylink_link, so every tap is counted + redirected through /l/),
 * header, divider, signup (the newsletter form, when that module is active) or product (a TigerShop product
 * card, when TigerShop is active; `ref` = the product slug). `featured` makes a link a larger card.
 * `show_from` / `show_until` schedule an item (UTC).
 */
return [
    'up' => [
        "CREATE TABLE IF NOT EXISTS `tinylink_page_item` (
            `item_id`            CHAR(36)      NOT NULL,
            `org_id`             VARCHAR(191)  NOT NULL DEFAULT '',
            `page_id`            CHAR(36)      NOT NULL,
            `kind`               VARCHAR(16)   NOT NULL DEFAULT 'link',
            `label`              VARCHAR(191)  NULL,
            `link_id`            CHAR(36)      NULL,
            `ref`                VARCHAR(191)  NULL,
            `icon`               VARCHAR(32)   NULL,
            `thumbnail_media_id` CHAR(36)      NULL,
            `featured`           TINYINT(1)    NOT NULL DEFAULT 0,
            `show_from`          DATETIME      NULL,
            `show_until`         DATETIME      NULL,
            `sort`               INT           NOT NULL DEFAULT 0,
            `status`             VARCHAR(16)   NOT NULL DEFAULT 'active',
            `deleted`            TINYINT(1)    NOT NULL DEFAULT 0,
            `created_by`         CHAR(36)      NULL,
            `updated_by`         CHAR(36)      NULL,
            `created_at`         DATETIME      NULL,
            `updated_at`         DATETIME      NULL,
            PRIMARY KEY (`item_id`),
            KEY `ix_tinylink_page_item_page` (`page_id`, `deleted`, `sort`),
            KEY `ix_tinylink_page_item_org` (`org_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],
    'down' => [
        "DROP TABLE IF EXISTS `tinylink_page_item`",
    ],
];

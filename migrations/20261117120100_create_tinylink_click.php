<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Create tinylink_click — one row per redirect, the local click log.
 *
 * Deliberately coarse: the referring HOST (not the full URL) and a device class (desktop / mobile / tablet /
 * bot) — never the IP or the raw user agent. Enough for "where do my clicks come from", not a tracking file.
 */
return [
    'up' => [
        "CREATE TABLE IF NOT EXISTS `tinylink_click` (
            `click_id`      CHAR(36)      NOT NULL,
            `org_id`        VARCHAR(191)  NOT NULL DEFAULT '',
            `link_id`       CHAR(36)      NOT NULL,
            `clicked_at`    DATETIME      NOT NULL,
            `referrer_host` VARCHAR(191)  NULL,
            `ua_class`      VARCHAR(16)   NOT NULL DEFAULT 'desktop',
            `status`        VARCHAR(16)   NOT NULL DEFAULT 'recorded',
            `deleted`       TINYINT(1)    NOT NULL DEFAULT 0,
            `created_by`    CHAR(36)      NULL,
            `updated_by`    CHAR(36)      NULL,
            `created_at`    DATETIME      NULL,
            `updated_at`    DATETIME      NULL,
            PRIMARY KEY (`click_id`),
            KEY `ix_tinylink_click_link` (`link_id`, `clicked_at`),
            KEY `ix_tinylink_click_org` (`org_id`, `clicked_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],
    'down' => [
        "DROP TABLE IF EXISTS `tinylink_click`",
    ],
];

<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Create tinylink_page_view — one row per links-page view. As coarse as the click log: referring host +
 * device class, never an IP or a raw user agent.
 */
return [
    'up' => [
        "CREATE TABLE IF NOT EXISTS `tinylink_page_view` (
            `view_id`       CHAR(36)      NOT NULL,
            `org_id`        VARCHAR(191)  NOT NULL DEFAULT '',
            `page_id`       CHAR(36)      NOT NULL,
            `viewed_at`     DATETIME      NOT NULL,
            `referrer_host` VARCHAR(191)  NULL,
            `ua_class`      VARCHAR(16)   NOT NULL DEFAULT 'desktop',
            `status`        VARCHAR(16)   NOT NULL DEFAULT 'recorded',
            `deleted`       TINYINT(1)    NOT NULL DEFAULT 0,
            `created_by`    CHAR(36)      NULL,
            `updated_by`    CHAR(36)      NULL,
            `created_at`    DATETIME      NULL,
            `updated_at`    DATETIME      NULL,
            PRIMARY KEY (`view_id`),
            KEY `ix_tinylink_page_view_page` (`page_id`, `viewed_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],
    'down' => [
        "DROP TABLE IF EXISTS `tinylink_page_view`",
    ],
];

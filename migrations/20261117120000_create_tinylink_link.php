<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Create tinylink_link — one short link: slug → destination.
 *
 * The slug is unique per org among LIVE links (the TIGER-274 norm): `slug_active` is the slug while the row
 * is live and NULL once soft-deleted, so removing a link frees its slug. The standard columns ride along.
 * Timestamp version (YYYYMMDDHHMMSS): the migration ledger is one namespace across core and every module.
 */
return [
    'up' => [
        "CREATE TABLE IF NOT EXISTS `tinylink_link` (
            `link_id`         CHAR(36)      NOT NULL,
            `org_id`          VARCHAR(191)  NOT NULL DEFAULT '',
            `slug`            VARCHAR(64)   NOT NULL,
            `destination`     TEXT          NOT NULL,
            `redirect_type`   SMALLINT      NOT NULL DEFAULT 302,
            `merge_params`    TINYINT(1)    NOT NULL DEFAULT 1,
            `expires_at`      DATETIME      NULL,
            `notes`           VARCHAR(500)  NULL,
            `click_count`     INT UNSIGNED  NOT NULL DEFAULT 0,
            `last_clicked_at` DATETIME      NULL,
            `status`          VARCHAR(16)   NOT NULL DEFAULT 'active',
            `deleted`         TINYINT(1)    NOT NULL DEFAULT 0,
            `created_by`      CHAR(36)      NULL,
            `updated_by`      CHAR(36)      NULL,
            `created_at`      DATETIME      NULL,
            `updated_at`      DATETIME      NULL,
            `slug_active`     VARCHAR(64)   GENERATED ALWAYS AS (IF(`deleted` = 0, `slug`, NULL)) VIRTUAL,
            PRIMARY KEY (`link_id`),
            UNIQUE KEY `uq_tinylink_link_slug_active` (`org_id`, `slug_active`),
            KEY `ix_tinylink_link_org` (`org_id`, `deleted`, `status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],
    'down' => [
        "DROP TABLE IF EXISTS `tinylink_link`",
    ],
];

<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Create tinylink_page — a links page (link-in-bio): /links/<handle>.
 *
 * The handle is unique per org among LIVE pages (`handle_active`, the TIGER-274 norm). `social` is a JSON
 * object network => url (LONGTEXT, never JSON — core AGENTS.md). Media ids point at the core media library.
 */
return [
    'up' => [
        "CREATE TABLE IF NOT EXISTS `tinylink_page` (
            `page_id`              CHAR(36)      NOT NULL,
            `org_id`               VARCHAR(191)  NOT NULL DEFAULT '',
            `handle`               VARCHAR(64)   NOT NULL,
            `title`                VARCHAR(191)  NOT NULL,
            `bio`                  VARCHAR(500)  NULL,
            `avatar_media_id`      CHAR(36)      NULL,
            `layout`               VARCHAR(16)   NOT NULL DEFAULT 'stack',
            `background`           VARCHAR(16)   NOT NULL DEFAULT 'plain',
            `social`               LONGTEXT      NULL,
            `seo_description`      VARCHAR(300)  NULL,
            `og_image_media_id`    CHAR(36)      NULL,
            `is_default`           TINYINT(1)    NOT NULL DEFAULT 0,
            `view_count`           INT UNSIGNED  NOT NULL DEFAULT 0,
            `status`               VARCHAR(16)   NOT NULL DEFAULT 'active',
            `deleted`              TINYINT(1)    NOT NULL DEFAULT 0,
            `created_by`           CHAR(36)      NULL,
            `updated_by`           CHAR(36)      NULL,
            `created_at`           DATETIME      NULL,
            `updated_at`           DATETIME      NULL,
            `handle_active`        VARCHAR(64)   GENERATED ALWAYS AS (IF(`deleted` = 0, `handle`, NULL)) VIRTUAL,
            PRIMARY KEY (`page_id`),
            UNIQUE KEY `uq_tinylink_page_handle_active` (`org_id`, `handle_active`),
            KEY `ix_tinylink_page_org` (`org_id`, `deleted`, `status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],
    'down' => [
        "DROP TABLE IF EXISTS `tinylink_page`",
    ],
];

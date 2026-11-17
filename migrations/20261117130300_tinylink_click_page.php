<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * tinylink_click.page_id — the links page a tap came from (NULL for a direct short-link visit), so a page's
 * stats can show taps per button.
 */
return [
    'up' => [
        "ALTER TABLE `tinylink_click` ADD COLUMN `page_id` CHAR(36) NULL AFTER `link_id`",
        "ALTER TABLE `tinylink_click` ADD KEY `ix_tinylink_click_page` (`page_id`, `clicked_at`)",
    ],
    'down' => [
        "ALTER TABLE `tinylink_click` DROP KEY `ix_tinylink_click_page`",
        "ALTER TABLE `tinylink_click` DROP COLUMN `page_id`",
    ],
];

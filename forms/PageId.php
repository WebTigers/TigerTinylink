<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tinylink_Form_PageId — a write that names one page (Tinylink_Service_Page::delete) or one block
 * (Tinylink_Service_PageItem::delete, `item_id`).
 *
 * @api
 */
class Tinylink_Form_PageId extends Tinylink_Form_Write
{
    /** @return array<int,array> [type, name, options] triples */
    protected function elements(): array
    {
        return [$this->_uuid('page_id'), $this->_uuid('item_id')];
    }
}

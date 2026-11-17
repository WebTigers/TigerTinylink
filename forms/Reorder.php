<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tinylink_Form_Reorder — the new order of a page's blocks (Tinylink_Service_PageItem::reorder). `item_ids`
 * is a comma-separated list or an array; the service keeps only this page's items.
 *
 * @api
 */
class Tinylink_Form_Reorder extends Tinylink_Form_Write
{
    /** @return array<int,array> [type, name, options] triples */
    protected function elements(): array
    {
        return [$this->_uuid('page_id', true), $this->_open('item_ids')];
    }
}

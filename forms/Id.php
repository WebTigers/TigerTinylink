<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tinylink_Form_Id — a write that names one link and nothing else (Tinylink_Service_Link::delete). Also the
 * form the admin screen renders its one CSRF token from.
 *
 * @api
 */
class Tinylink_Form_Id extends Tinylink_Form_Write
{
    /** @return array<int,array> [type, name, options] triples */
    protected function elements(): array
    {
        $id = $this->_linkId();
        $id[2]['required'] = true;
        return [$id];
    }
}

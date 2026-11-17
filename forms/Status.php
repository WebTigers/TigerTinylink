<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tinylink_Form_Status — enable or disable one link (Tinylink_Service_Link::setStatus).
 *
 * @api
 */
class Tinylink_Form_Status extends Tinylink_Form_Write
{
    /** @return array<int,array> [type, name, options] triples */
    protected function elements(): array
    {
        $id = $this->_linkId();
        $id[2]['required'] = true;
        return [
            $id,
            ['hidden', 'status', [
                'required'   => true,
                'validators' => [['InArray', false, ['haystack' => ['active', 'disabled'], 'messages' => ['notInArray' => 'tinylink.error.status']]]],
            ]],
        ];
    }
}

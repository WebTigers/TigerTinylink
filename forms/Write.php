<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tinylink_Form_Write — the shared base of every TigerTinylink write form: one CSRF salt, so the admin screen
 * renders ONE token and every call it makes (save, status, delete) carries it.
 *
 * @api
 */
abstract class Tinylink_Form_Write extends Tiger_Form
{
    /**
     * One salt for the whole admin screen.
     *
     * @return string
     */
    protected function csrfSalt(): string
    {
        return 'Tinylink_Form_Write';
    }

    /**
     * The link id an update / status / delete names (blank = create, for save).
     *
     * @return array [type, name, options]
     */
    protected function _linkId(): array
    {
        return ['hidden', 'link_id', ['required' => false, 'filters' => ['StringTrim'],
            'validators' => [['Regex', false, ['pattern' => '/^[0-9a-f-]{36}$/i', 'messages' => ['regexNotMatch' => 'tinylink.error.not_found']]]]]];
    }
}

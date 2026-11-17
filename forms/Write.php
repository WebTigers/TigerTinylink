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
        return $this->_uuid('link_id');
    }

    /**
     * A UUID field (an id the service then looks up in the caller's org).
     *
     * @param  string $name     the element name
     * @param  bool   $required
     * @return array [type, name, options]
     */
    protected function _uuid(string $name, bool $required = false): array
    {
        return ['hidden', $name, ['required' => $required, 'filters' => ['StringTrim'],
            'validators' => [['Regex', false, ['pattern' => '/^[0-9a-f-]{36}$/i', 'messages' => ['regexNotMatch' => 'tinylink.error.not_found']]]]]];
    }

    /**
     * An optional field with no filter or validator — the service owns its shape (a flag, a JSON blob).
     *
     * @param  string $name
     * @return array [type, name, options]
     */
    protected function _open(string $name): array
    {
        return ['hidden', $name, ['required' => false, 'filters' => [], 'validators' => []]];
    }

    /**
     * An optional UTC date-time (`YYYY-MM-DD HH:MM`, or the `T` form a datetime-local input sends).
     *
     * @param  string $name
     * @return array [type, name, options]
     */
    protected function _datetime(string $name): array
    {
        return ['text', $name, ['required' => false, 'filters' => ['StringTrim'],
            'validators' => [['Regex', false, ['pattern' => '/^\d{4}-\d{2}-\d{2}([ T]\d{2}:\d{2}(:\d{2})?)?$/', 'messages' => ['regexNotMatch' => 'tinylink.error.expires_at']]]],
            'attribs' => ['class' => 'form-control', 'type' => 'datetime-local']]];
    }
}

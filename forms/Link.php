<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tinylink_Form_Link — create or update a short link (Tinylink_Service_Link::save).
 *
 * The form owns the field shape (slug pattern, http/https destination, 301/302, expiry format); the service
 * adds what needs the database or a parser — a slug already in use, a destination that isn't a real http(s)
 * URL with a host — and reports both back as per-field errors.
 *
 * @api
 */
class Tinylink_Form_Link extends Tinylink_Form_Write
{
    /** A slug: a letter or digit, then letters, digits, - or _, up to 64. Stored lower-case. */
    const SLUG_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9_-]{0,63}$/';

    /** @return array<int,array> [type, name, options] triples */
    protected function elements(): array
    {
        return [
            $this->_linkId(),
            ['text', 'slug', [
                'required'   => false,
                'filters'    => ['StringTrim'],
                'validators' => [['Regex', false, ['pattern' => self::SLUG_PATTERN, 'messages' => ['regexNotMatch' => 'tinylink.error.slug']]]],
                'attribs'    => ['class' => 'form-control', 'autocomplete' => 'off', 'maxlength' => 64],
            ]],
            ['text', 'destination', [
                'required'   => true,
                'filters'    => ['StringTrim'],
                'validators' => [
                    ['NotEmpty', true, ['messages' => ['isEmpty' => 'tinylink.error.destination']]],
                    ['StringLength', true, ['max' => 2048, 'messages' => ['stringLengthTooLong' => 'tinylink.error.destination_long']]],
                    ['Regex', false, ['pattern' => '#^https?://\S+$#i', 'messages' => ['regexNotMatch' => 'tinylink.error.destination']]],
                ],
                'attribs'    => ['class' => 'form-control', 'autocomplete' => 'off', 'placeholder' => 'https://'],
            ]],
            ['select', 'redirect_type', [
                'required'     => false,
                'multiOptions' => ['302' => '302', '301' => '301'],
                'validators'   => [['InArray', false, ['haystack' => ['301', '302'], 'messages' => ['notInArray' => 'tinylink.error.redirect_type']]]],
                'attribs'      => ['class' => 'form-select'],
            ]],
            ['hidden', 'merge_params', ['required' => false, 'filters' => [], 'validators' => []]],
            ['text', 'expires_at', [
                'required'   => false,
                'filters'    => ['StringTrim'],
                'validators' => [['Regex', false, ['pattern' => '/^\d{4}-\d{2}-\d{2}([ T]\d{2}:\d{2}(:\d{2})?)?$/', 'messages' => ['regexNotMatch' => 'tinylink.error.expires_at']]]],
                'attribs'    => ['class' => 'form-control', 'type' => 'datetime-local'],
            ]],
            ['text', 'notes', [
                'required'   => false,
                'filters'    => ['StringTrim'],
                'validators' => [['StringLength', false, ['max' => 500, 'messages' => ['stringLengthTooLong' => 'tinylink.error.notes']]]],
                'attribs'    => ['class' => 'form-control', 'maxlength' => 500],
            ]],
            ['select', 'status', [
                'required'     => false,
                'multiOptions' => ['active' => 'active', 'disabled' => 'disabled'],
                'validators'   => [['InArray', false, ['haystack' => ['active', 'disabled'], 'messages' => ['notInArray' => 'tinylink.error.status']]]],
                'attribs'      => ['class' => 'form-select'],
            ]],
        ];
    }
}

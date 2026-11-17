<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tinylink_Form_PageItem — create or update one block on a links page (Tinylink_Service_PageItem::save).
 *
 * A `link` block names an existing short link (`link_id`) or gives a `destination`, from which the service
 * creates one. The service checks what the form can't: the page and the link belong to the caller's org, a
 * destination is a real http(s) URL, the label a kind needs is present.
 *
 * @api
 */
class Tinylink_Form_PageItem extends Tinylink_Form_Write
{
    /** The icons a block may show (Font Awesome names, rendered as classes — never markup). */
    const ICONS = ['link', 'star', 'heart', 'cart-shopping', 'book', 'play', 'music', 'calendar', 'envelope', 'gift',
                   'download', 'microphone', 'video', 'newspaper', 'store', 'ticket', 'globe', 'camera', 'pen', 'bolt'];

    /** @return array<int,array> [type, name, options] triples */
    protected function elements(): array
    {
        return [
            $this->_uuid('item_id'),
            $this->_uuid('page_id'),
            ['select', 'kind', [
                'required'     => false,
                'multiOptions' => array_combine(Tinylink_Model_PageItem::KINDS, Tinylink_Model_PageItem::KINDS),
                'validators'   => [['InArray', false, ['haystack' => Tinylink_Model_PageItem::KINDS, 'messages' => ['notInArray' => 'tinylink.error.kind']]]],
                'attribs'      => ['class' => 'form-select'],
            ]],
            ['text', 'label', [
                'required'   => false,
                'filters'    => ['StringTrim'],
                'validators' => [['StringLength', false, ['max' => 191, 'messages' => ['stringLengthTooLong' => 'tinylink.error.label']]]],
                'attribs'    => ['class' => 'form-control', 'maxlength' => 191],
            ]],
            $this->_uuid('link_id'),
            ['text', 'destination', [
                'required'   => false,
                'filters'    => ['StringTrim'],
                'validators' => [
                    ['StringLength', true, ['max' => 2048, 'messages' => ['stringLengthTooLong' => 'tinylink.error.destination_long']]],
                    ['Regex', false, ['pattern' => '#^https?://\S+$#i', 'messages' => ['regexNotMatch' => 'tinylink.error.destination']]],
                ],
                'attribs'    => ['class' => 'form-control', 'placeholder' => 'https://'],
            ]],
            ['text', 'ref', [
                'required'   => false,
                'filters'    => ['StringTrim'],
                'validators' => [['Regex', false, ['pattern' => '/^[A-Za-z0-9][A-Za-z0-9_.-]{0,190}$/', 'messages' => ['regexNotMatch' => 'tinylink.error.ref']]]],
                'attribs'    => ['class' => 'form-control'],
            ]],
            ['select', 'icon', [
                'required'     => false,
                'multiOptions' => ['' => ''] + array_combine(self::ICONS, self::ICONS),
                'validators'   => [['InArray', false, ['haystack' => array_merge([''], self::ICONS), 'messages' => ['notInArray' => 'tinylink.error.icon']]]],
                'attribs'      => ['class' => 'form-select'],
            ]],
            $this->_uuid('thumbnail_media_id'),
            $this->_open('featured'),
            $this->_datetime('show_from'),
            $this->_datetime('show_until'),
            ['hidden', 'status', [
                'required'   => false,
                'validators' => [['InArray', false, ['haystack' => ['active', 'disabled'], 'messages' => ['notInArray' => 'tinylink.error.status']]]],
            ]],
        ];
    }
}

<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tinylink_Form_Page — create or update a links page (Tinylink_Service_Page::save). The service adds what
 * needs the database or a parser: a handle already in use, the social links (each an http(s) URL or an email
 * address), and that a media id exists.
 *
 * @api
 */
class Tinylink_Form_Page extends Tinylink_Form_Write
{
    /** @return array<int,array> [type, name, options] triples */
    protected function elements(): array
    {
        return [
            $this->_uuid('page_id'),
            ['text', 'handle', [
                'required'   => false,
                'filters'    => ['StringTrim'],
                'validators' => [['Regex', false, ['pattern' => Tinylink_Form_Link::SLUG_PATTERN, 'messages' => ['regexNotMatch' => 'tinylink.error.handle']]]],
                'attribs'    => ['class' => 'form-control', 'maxlength' => 64, 'autocomplete' => 'off'],
            ]],
            ['text', 'title', [
                'required'   => false,
                'filters'    => ['StringTrim'],
                'validators' => [['StringLength', false, ['min' => 1, 'max' => 191, 'messages' => ['stringLengthTooShort' => 'tinylink.error.title', 'stringLengthTooLong' => 'tinylink.error.title']]]],
                'attribs'    => ['class' => 'form-control', 'maxlength' => 191],
            ]],
            ['textarea', 'bio', [
                'required'   => false,
                'filters'    => ['StringTrim'],
                'validators' => [['StringLength', false, ['max' => 500, 'messages' => ['stringLengthTooLong' => 'tinylink.error.bio']]]],
                'attribs'    => ['class' => 'form-control', 'rows' => 3, 'maxlength' => 500],
            ]],
            $this->_uuid('avatar_media_id'),
            ['select', 'layout', [
                'required'     => false,
                'multiOptions' => array_combine(Tinylink_Model_Page::LAYOUTS, Tinylink_Model_Page::LAYOUTS),
                'validators'   => [['InArray', false, ['haystack' => Tinylink_Model_Page::LAYOUTS, 'messages' => ['notInArray' => 'tinylink.error.layout']]]],
                'attribs'      => ['class' => 'form-select'],
            ]],
            ['select', 'background', [
                'required'     => false,
                'multiOptions' => array_combine(Tinylink_Model_Page::BACKGROUNDS, Tinylink_Model_Page::BACKGROUNDS),
                'validators'   => [['InArray', false, ['haystack' => Tinylink_Model_Page::BACKGROUNDS, 'messages' => ['notInArray' => 'tinylink.error.layout']]]],
                'attribs'      => ['class' => 'form-select'],
            ]],
            $this->_open('social'),
            ['text', 'seo_description', [
                'required'   => false,
                'filters'    => ['StringTrim'],
                'validators' => [['StringLength', false, ['max' => 300, 'messages' => ['stringLengthTooLong' => 'tinylink.error.seo_description']]]],
                'attribs'    => ['class' => 'form-control', 'maxlength' => 300],
            ]],
            $this->_uuid('og_image_media_id'),
            $this->_open('is_default'),
            ['hidden', 'status', [
                'required'   => false,
                'validators' => [['InArray', false, ['haystack' => ['active', 'disabled'], 'messages' => ['notInArray' => 'tinylink.error.status']]]],
            ]],
        ];
    }
}

<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tinylink_AdminController — the "Short links" admin screen (/tinylink/admin). A thin shell: the grid loads
 * its rows from Tinylink_Service_Link::datatable and every write is an /api call (ADMIN.md).
 */
class Tinylink_AdminController extends Tiger_Controller_Admin_Action
{
    /** The admin shell comes from the base. */
    public function init()
    {
        parent::init();
    }

    /** The links grid + the create/edit and stats modals. */
    public function indexAction()
    {
        $this->view->title         = $this->view->t('tinylink.title') . ' — Tiger Admin';
        $this->view->useDataTables = true;
        $this->view->form          = new Tinylink_Form_Link();   // the modal's fields + the one CSRF token
        $req = $this->getRequest();
        $this->view->shortBase     = $req->getScheme() . '://' . $req->getHttpHost() . '/l/';
    }
}

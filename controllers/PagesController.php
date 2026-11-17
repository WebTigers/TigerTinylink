<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tinylink_PagesController — the links-page admin: the list (/tinylink/pages) and the editor
 * (/tinylink/pages/edit/id/<page_id>). Thin shells; data and every write go through Tinylink_Service_Page and
 * Tinylink_Service_Block over /api (ADMIN.md).
 */
class Tinylink_PagesController extends Tiger_Controller_Admin_Action
{
    /** The admin shell comes from the base. */
    public function init()
    {
        parent::init();
    }

    /** The org's pages + the "new page" modal. */
    public function indexAction()
    {
        $this->view->title = $this->view->t('tinylink.pages.title') . ' — Tiger Admin';
        $this->view->form  = new Tinylink_Form_Page();   // the modal fields + the screen's one CSRF token
    }

    /** One page's editor: profile, appearance, social, blocks (drag to reorder), stats. */
    public function editAction()
    {
        $id   = (string) $this->getRequest()->getParam('id', '');
        $page = (new Tinylink_Model_Page())->findForOrg((string) $this->_orgId(), $id);
        if (!$page) {
            throw new Zend_Controller_Action_Exception('Links page not found', 404);
        }
        $this->view->title   = (string) $page->title . ' — Tiger Admin';
        $this->view->pageId  = (string) $page->page_id;
        $this->view->form    = new Tinylink_Form_Page();
        $this->view->item    = new Tinylink_Form_PageItem();
        $this->view->social  = Tinylink_Service_Page::SOCIAL;
        $this->view->caps    = Tinylink_Service_Page::capabilities();
        $req = $this->getRequest();
        $this->view->siteBase = $req->getScheme() . '://' . $req->getHttpHost();
    }

    /** The signed-in caller's current org. */
    protected function _orgId(): string
    {
        $id = Zend_Auth::getInstance()->getIdentity();
        return ($id && !empty($id->org_id)) ? (string) $id->org_id : '';
    }
}

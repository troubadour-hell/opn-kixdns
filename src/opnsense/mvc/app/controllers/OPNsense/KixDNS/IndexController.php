<?php

namespace OPNsense\KixDNS;

class IndexController extends \OPNsense\Base\IndexController
{
    public function indexAction()
    {
        $this->view->title = gettext("KixDNS General Settings");
        // Pick the view forms
        $this->view->generalForm = $this->getForm("general");
        $this->view->formDialogPipeline = $this->getForm("dialog_pipeline");
        $this->view->formDialogSelector = $this->getForm("dialog_selector");
        $this->view->formDialogRule = $this->getForm("dialog_rule");

        $this->view->pick('OPNsense/KixDNS/index');
    }
}

<?php

namespace OPNsense\KixDNS;

class IndexController extends \OPNsense\Base\IndexController
{
    public function indexAction()
    {
        $this->view->title = gettext("KixDNS Configuration");
        // Only need general form for service settings
        $this->view->generalForm = $this->getForm("general");

        $this->view->pick('OPNsense/KixDNS/index');
    }
}

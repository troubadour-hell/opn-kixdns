<?php

namespace OPNsense\KixDNS\Api;

use OPNsense\Base\ApiMutableModelControllerBase;

class SettingsController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'KixDNS';
    protected static $internalModelClass = 'OPNsense\KixDNS\KixDNS';

    public function getAction()
    {
        return $this->getBase('general', 'pipelines', 'rules', 'selectors');
    }

    public function setAction()
    {
        return $this->setBase('general', 'pipelines', 'rules', 'selectors');
    }

    public function reconfigureAction()
    {
        $result = array("status" => "failed");
        if ($this->request->isPost()) {
            $backend = new \OPNsense\Core\Backend();
            $backend->configdRun('template reload OPNsense/KixDNS');
            $backend->configdRun('kixdns reload');
            $result["status"] = "ok";
        }
        return $result;
    }

    /* Pipelines CRUD */
    public function searchPipelineAction()
    {
        return $this->searchBase('pipelines.pipeline', array('id', 'description'));
    }
    public function getPipelineAction($uuid = null)
    {
        return $this->getBase('pipeline', 'pipelines.pipeline', $uuid);
    }
    public function addPipelineAction()
    {
        return $this->addBase('pipeline', 'pipelines.pipeline');
    }
    public function delPipelineAction($uuid)
    {
        return $this->delBase('pipelines.pipeline', $uuid);
    }
    public function setPipelineAction($uuid)
    {
        return $this->setBase('pipeline', 'pipelines.pipeline', $uuid);
    }

    /* Selectors CRUD */
    public function searchSelectorAction()
    {
        return $this->searchBase('selectors.selector', array('target_pipeline', 'description'));
    }
    public function getSelectorAction($uuid = null)
    {
        return $this->getBase('selector', 'selectors.selector', $uuid);
    }
    public function addSelectorAction()
    {
        return $this->addBase('selector', 'selectors.selector');
    }
    public function delSelectorAction($uuid)
    {
        return $this->delBase('selectors.selector', $uuid);
    }
    public function setSelectorAction($uuid)
    {
        return $this->setBase('selector', 'selectors.selector', $uuid);
    }

    /* Rules CRUD */
    public function searchRuleAction()
    {
        return $this->searchBase('rules.rule', array('name', 'pipeline_id', 'enabled'));
    }
    public function getRuleAction($uuid = null)
    {
        return $this->getBase('rule', 'rules.rule', $uuid);
    }
    public function addRuleAction()
    {
        return $this->addBase('rule', 'rules.rule');
    }
    public function delRuleAction($uuid)
    {
        return $this->delBase('rules.rule', $uuid);
    }
    public function setRuleAction($uuid)
    {
        return $this->setBase('rule', 'rules.rule', $uuid);
    }
    public function toggleRuleAction($uuid)
    {
        return $this->toggleBase('rules.rule', $uuid);
    }
}

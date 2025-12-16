<?php

namespace OPNsense\KixDNS\Api;

use OPNsense\Base\ApiMutableModelControllerBase;

class SettingsController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'KixDNS';
    protected static $internalModelClass = 'OPNsense\KixDNS\KixDNS';

    public function getAction()
    {
        // 调试：记录一次获取配置的行为（不打印完整内容，避免日志过大）
        $this->logDebug('getAction called');
        return $this->getBase('general', 'pipelines', 'rules', 'selectors');
    }

    public function setAction()
    {
        // 目前前端只提交 general 段配置，这里只保存 general，pipelines/rules/selectors 走各自 CRUD
        $post = $this->request->getPost();
        $this->logDebug('setAction payload', $post);

        $result = $this->setBase('general');

        // 方便调试：当保存失败时把详细结果打到系统日志
        if (!isset($result['result']) || $result['result'] !== 'saved') {
            $this->logError('setAction failed', $result);
        } else {
            $this->logDebug('setAction succeeded');
        }

        return $result;
    }

    public function reconfigureAction()
    {
        $result = array("status" => "failed");
        if ($this->request->isPost()) {
            $this->logInfo('reconfigureAction requested, triggering template reload and service reload');
            $backend = new \OPNsense\Core\Backend();
            $backend->configdRun('template reload OPNsense/KixDNS');
            $backend->configdRun('kixdns reload');
            $result["status"] = "ok";
            $this->logInfo('reconfigureAction completed');
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
        $post = $this->request->getPost();
        $this->logDebug('addPipelineAction payload', $post);
        $result = $this->addBase('pipeline', 'pipelines.pipeline');
        $this->logResult('addPipeline', $result);
        return $result;
    }
    public function delPipelineAction($uuid)
    {
        $this->logDebug('delPipelineAction', array('uuid' => $uuid));
        $result = $this->delBase('pipelines.pipeline', $uuid);
        $this->logResult('delPipeline', $result);
        return $result;
    }
    public function setPipelineAction($uuid)
    {
        $post = $this->request->getPost();
        $this->logDebug('setPipelineAction', array('uuid' => $uuid, 'payload' => $post));
        $result = $this->setBase('pipeline', 'pipelines.pipeline', $uuid);
        $this->logResult('setPipeline', $result);
        return $result;
    }
    public function togglePipelineAction($uuid)
    {
        $this->logDebug('togglePipelineAction', array('uuid' => $uuid));
        $result = $this->toggleBase('pipelines.pipeline', $uuid);
        $this->logResult('togglePipeline', $result);
        return $result;
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
        $post = $this->request->getPost();
        $this->logDebug('addSelectorAction payload', $post);
        $result = $this->addBase('selector', 'selectors.selector');
        $this->logResult('addSelector', $result);
        return $result;
    }
    public function delSelectorAction($uuid)
    {
        $this->logDebug('delSelectorAction', array('uuid' => $uuid));
        $result = $this->delBase('selectors.selector', $uuid);
        $this->logResult('delSelector', $result);
        return $result;
    }
    public function setSelectorAction($uuid)
    {
        $post = $this->request->getPost();
        $this->logDebug('setSelectorAction', array('uuid' => $uuid, 'payload' => $post));
        $result = $this->setBase('selector', 'selectors.selector', $uuid);
        $this->logResult('setSelector', $result);
        return $result;
    }
    public function toggleSelectorAction($uuid)
    {
        $this->logDebug('toggleSelectorAction', array('uuid' => $uuid));
        $result = $this->toggleBase('selectors.selector', $uuid);
        $this->logResult('toggleSelector', $result);
        return $result;
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
        $post = $this->request->getPost();
        $this->logDebug('addRuleAction payload', $post);
        $result = $this->addBase('rule', 'rules.rule');
        $this->logResult('addRule', $result);
        return $result;
    }
    public function delRuleAction($uuid)
    {
        $this->logDebug('delRuleAction', array('uuid' => $uuid));
        $result = $this->delBase('rules.rule', $uuid);
        $this->logResult('delRule', $result);
        return $result;
    }
    public function setRuleAction($uuid)
    {
        $post = $this->request->getPost();
        $this->logDebug('setRuleAction', array('uuid' => $uuid, 'payload' => $post));
        $result = $this->setBase('rule', 'rules.rule', $uuid);
        $this->logResult('setRule', $result);
        return $result;
    }
    public function toggleRuleAction($uuid)
    {
        $this->logDebug('toggleRuleAction', array('uuid' => $uuid));
        $result = $this->toggleBase('rules.rule', $uuid);
        $this->logResult('toggleRule', $result);
        return $result;
    }

    /**
     * 统一的结果日志辅助函数，便于在出现错误时快速定位问题
     */
    private function logResult(string $action, array $result): void
    {
        if (isset($result['result']) && in_array($result['result'], array('saved', 'deleted'), true)) {
            $this->logDebug($action . ' succeeded');
            return;
        }
        if (isset($result['result']) && $result['result'] === 'failed') {
            $this->logError($action . ' failed', $result);
        } else {
            // 其他返回结构也记录为 debug，方便分析
            $this->logDebug($action . ' result', $result);
        }
    }

    /**
     * 简单的文件日志封装，写入 /var/log/kixdns.log，避免挤占系统日志
     */
    private function logDebug(string $message, array $context = array()): void
    {
        $this->logToFile('DEBUG', $message, $context);
    }

    private function logInfo(string $message, array $context = array()): void
    {
        $this->logToFile('INFO', $message, $context);
    }

    private function logError(string $message, array $context = array()): void
    {
        $this->logToFile('ERROR', $message, $context);
    }

    private function logToFile(string $level, string $message, array $context = array()): void
    {
        $logFile = '/var/log/kixdns.log';
        $line = sprintf('[%s] [%s] %s', date('c'), $level, $message);
        if (!empty($context)) {
            $line .= ' ' . json_encode($context);
        }
        $line .= PHP_EOL;
        // 使用 @ 避免在日志目录不存在或权限问题时再抛出警告
        @file_put_contents($logFile, $line, FILE_APPEND);
    }
}

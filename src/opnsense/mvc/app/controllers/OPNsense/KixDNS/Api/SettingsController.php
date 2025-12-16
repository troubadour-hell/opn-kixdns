<?php

namespace OPNsense\KixDNS\Api;

use OPNsense\Base\ApiMutableModelControllerBase;

class SettingsController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'KixDNS';
    protected static $internalModelClass = 'OPNsense\KixDNS\KixDNS';

    public function getAction()
    {
        // 目前仅返回 general 段（服务开关、基础参数以及原始 JSON 等）
        $this->logDebug('getAction called');
        return $this->getBase('general');
    }

    public function setAction()
    {
        // 保存 general 段配置（包括服务开关、基础参数以及原始 JSON 等）
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
            $this->logInfo('reconfigureAction requested, triggering template reload and service restart');
            $backend = new \OPNsense\Core\Backend();
            $backend->configdRun('template reload OPNsense/KixDNS');
            // 使用 restart 动作，确保应用新配置（actions_kixdns.conf 中已定义）
            $backend->configdRun('kixdns restart');
            $result["status"] = "ok";
            $this->logInfo('reconfigureAction completed');
        }
        return $result;
    }

    /**
     * Get config JSON for editor
     */
    public function getConfigJsonAction()
    {
        $mdl = $this->getModel();
        $general = $mdl->general;
        $configJson = $general->config_json;
        return array(
            'config_json' => $configJson ? $configJson->__toString() : ''
        );
    }

    /**
     * Save config JSON from editor
     */
    public function saveConfigJsonAction()
    {
        $result = array("result" => "failed");
        if ($this->request->isPost()) {
            $post = $this->request->getPost();
            $this->logDebug('saveConfigJsonAction payload', $post);
            
            $mdl = $this->getModel();
            $general = $mdl->general;
            
            if (isset($post['config_json'])) {
                // Validate JSON
                $jsonStr = $post['config_json'];
                $decoded = json_decode($jsonStr, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $result['message'] = 'Invalid JSON: ' . json_last_error_msg();
                    return $result;
                }
                
                $general->config_json = $jsonStr;
                $mdl->serializeToConfig();
                $this->config->save();
                
                $result["result"] = "saved";
                $this->logInfo('saveConfigJsonAction succeeded');
            } else {
                $result['message'] = 'Missing config_json parameter';
            }
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

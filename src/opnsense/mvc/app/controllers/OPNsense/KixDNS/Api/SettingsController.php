<?php

namespace OPNsense\KixDNS\Api;

use OPNsense\Base\ApiMutableModelControllerBase;

class SettingsController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'KixDNS';
    protected static $internalModelClass = 'OPNsense\KixDNS\KixDNS';

    public function getAction()
    {
        // 使用 OPNsense 标准的 getBase 方法，它会自动处理默认值和选项
        try {
            $this->logDebug('getAction called');
            // getBase 会自动处理字段的默认值、选项列表等
            $result = $this->getBase('general');
            
            // 确保返回的数据有 general 键
            if (!isset($result['general'])) {
                $this->logDebug('getAction: result missing general key, wrapping', array('result_keys' => array_keys($result)));
                $result = array('general' => $result);
            }
            
            // 确保空字段使用默认值
            $defaults = array(
                'enabled' => '0',
                'config_path' => '/usr/local/etc/kixdns/pipeline.json',
                'bind_udp' => '0.0.0.0:5353',
                'bind_tcp' => '0.0.0.0:5353',
                'default_upstream' => '1.1.1.1:53',
                'upstream_timeout' => '2000',
                'response_jump_limit' => '10',
                'min_ttl' => '0',
                'udp_workers' => '0',
                'udp_pool_size' => '0',
                'listener_label' => 'default',
                'debug' => '0',
                'log_level' => 'info',
                'config_json' => ''
            );
            
            foreach ($defaults as $key => $defaultValue) {
                if (!isset($result['general'][$key]) || $result['general'][$key] === '') {
                    $result['general'][$key] = $defaultValue;
                    $this->logDebug("getAction: applied default for {$key}", array('value' => $defaultValue));
                }
            }
            
            $this->logDebug('getAction completed', array(
                'has_result' => !empty($result),
                'general_keys' => isset($result['general']) ? array_keys($result['general']) : array()
            ));
            return $result;
        } catch (\Exception $e) {
            $this->logError('getAction exception', array(
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ));
            // 如果 getBase 失败，返回带默认值的结构
            return array(
                'general' => array(
                    'enabled' => '0',
                    'config_path' => '/usr/local/etc/kixdns/pipeline.json',
                    'bind_udp' => '0.0.0.0:5353',
                    'bind_tcp' => '0.0.0.0:5353',
                    'default_upstream' => '1.1.1.1:53',
                    'upstream_timeout' => '2000',
                    'response_jump_limit' => '10',
                    'min_ttl' => '0',
                    'udp_workers' => '0',
                    'udp_pool_size' => '0',
                    'listener_label' => 'default',
                    'debug' => '0',
                    'log_level' => 'info',
                    'config_json' => ''
                )
            );
        }
    }

    public function setAction()
    {
        // 保存 general 段配置（包括服务开关、基础参数以及原始 JSON 等）
        try {
            $post = $this->request->getPost();
            $this->logDebug('setAction called', array('post_keys' => array_keys($post)));
            
            $mdl = $this->getModel();
            if (!$mdl) {
                throw new \Exception('Failed to get model instance');
            }
            
            $general = $mdl->general;
            if (!$general) {
                throw new \Exception('Failed to get general section');
            }
            
            // 更新字段值
            foreach ($post as $key => $value) {
                if (isset($general->$key)) {
                    $general->$key = $value;
                    $this->logDebug("setAction: set {$key}", array('value' => substr((string)$value, 0, 100)));
                }
            }
            
            // 保存配置
            $mdl->serializeToConfig();
            $this->config->save();
            
            $this->logInfo('setAction succeeded');
            return array('result' => 'saved');
        } catch (\Exception $e) {
            $this->logError('setAction exception', array(
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ));
            return array('result' => 'failed', 'message' => $e->getMessage());
        }
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
        try {
            $this->logDebug('getConfigJsonAction called');
            $mdl = $this->getModel();
            if (!$mdl) {
                throw new \Exception('Failed to get model');
            }
            $general = $mdl->general;
            if (!$general) {
                throw new \Exception('Failed to get general section');
            }
            
            $configJson = '';
            if (isset($general->config_json) && $general->config_json) {
                $configJson = $general->config_json->__toString();
            }
            
            $this->logDebug('getConfigJsonAction success', array('has_config' => !empty($configJson)));
            return array(
                'config_json' => $configJson
            );
        } catch (\Exception $e) {
            $this->logError('getConfigJsonAction exception', array(
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ));
            return array(
                'result' => 'failed',
                'message' => $e->getMessage(),
                'config_json' => ''
            );
        }
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
        $timestamp = date('Y-m-d H:i:s');
        $line = sprintf('[%s] [%s] %s', $timestamp, $level, $message);
        if (!empty($context)) {
            $line .= ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        $line .= PHP_EOL;
        
        // 确保日志目录存在
        $logDir = dirname($logFile);
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        
        // 写入日志文件
        $written = @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
        
        // 如果写入失败，尝试输出到 PHP 错误日志
        if ($written === false) {
            $errorMsg = "KixDNS [{$level}] {$message}";
            if (!empty($context)) {
                $errorMsg .= ' ' . json_encode($context, JSON_UNESCAPED_UNICODE);
            }
            error_log($errorMsg);
        }
    }
}

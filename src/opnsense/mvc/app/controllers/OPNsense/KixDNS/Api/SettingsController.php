<?php

namespace OPNsense\KixDNS\Api;

use OPNsense\Base\ApiMutableModelControllerBase;
use OPNsense\Core\Config;

class SettingsController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'kixdns';
    protected static $internalModelClass = 'OPNsense\KixDNS\KixDNS';

    /**
     * Get general settings
     */
    public function getAction()
    {
        $result = array('general' => array());
        
        try {
            $mdl = $this->getModel();
            
            if ($mdl && isset($mdl->general)) {
                $general = $mdl->general;
                
                // Convert model fields to array
                $result['general'] = array(
                    'enabled' => (string)$general->enabled,
                    'config_path' => (string)$general->config_path,
                    'bind_udp' => (string)$general->bind_udp,
                    'bind_tcp' => (string)$general->bind_tcp,
                    'default_upstream' => (string)$general->default_upstream,
                    'upstream_timeout' => (string)$general->upstream_timeout,
                    'response_jump_limit' => (string)$general->response_jump_limit,
                    'min_ttl' => (string)$general->min_ttl,
                    'udp_workers' => (string)$general->udp_workers,
                    'udp_pool_size' => (string)$general->udp_pool_size,
                    'listener_label' => (string)$general->listener_label,
                    'debug' => (string)$general->debug,
                    'log_level' => (string)$general->log_level,
                    'config_json' => (string)$general->config_json,
                );
            }
        } catch (\Throwable $e) {
            $this->logError('getAction exception', array(
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ));
        }
        
        // Fill in defaults for empty values
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
            'config_json' => '',
        );
        
        foreach ($defaults as $key => $default) {
            if (!isset($result['general'][$key]) || $result['general'][$key] === '') {
                $result['general'][$key] = $default;
            }
        }
        
        return $result;
    }

    /**
     * Save general settings
     */
    public function setAction()
    {
        $result = array('result' => 'failed');
        
        if (!$this->request->isPost()) {
            return $result;
        }
        
        try {
            $mdl = $this->getModel();
            $post = $this->request->getPost('general');
            
            if ($post && $mdl && isset($mdl->general)) {
                $general = $mdl->general;
                
                // Set each field from POST data
                if (isset($post['enabled'])) $general->enabled = $post['enabled'];
                if (isset($post['config_path'])) $general->config_path = $post['config_path'];
                if (isset($post['bind_udp'])) $general->bind_udp = $post['bind_udp'];
                if (isset($post['bind_tcp'])) $general->bind_tcp = $post['bind_tcp'];
                if (isset($post['default_upstream'])) $general->default_upstream = $post['default_upstream'];
                if (isset($post['upstream_timeout'])) $general->upstream_timeout = $post['upstream_timeout'];
                if (isset($post['response_jump_limit'])) $general->response_jump_limit = $post['response_jump_limit'];
                if (isset($post['min_ttl'])) $general->min_ttl = $post['min_ttl'];
                if (isset($post['udp_workers'])) $general->udp_workers = $post['udp_workers'];
                if (isset($post['udp_pool_size'])) $general->udp_pool_size = $post['udp_pool_size'];
                if (isset($post['listener_label'])) $general->listener_label = $post['listener_label'];
                if (isset($post['debug'])) $general->debug = $post['debug'];
                if (isset($post['log_level'])) $general->log_level = $post['log_level'];
                if (isset($post['config_json'])) $general->config_json = $post['config_json'];
                
                // Validate model
                $valMsgs = $mdl->performValidation();
                foreach ($valMsgs as $field => $msg) {
                    if (!isset($result['validations'])) {
                        $result['validations'] = array();
                    }
                    $result['validations']['general.' . $msg->getField()] = $msg->getMessage();
                }
                
                if (empty($result['validations'])) {
                    $mdl->serializeToConfig();
                    Config::getInstance()->save();
                    $result['result'] = 'saved';
                }
            }
        } catch (\Exception $e) {
            $this->logError('setAction exception', array(
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ));
            $result['message'] = $e->getMessage();
        }
        
        return $result;
    }

    /**
     * Reconfigure service - regenerate config and restart
     */
    public function reconfigureAction()
    {
        $result = array("status" => "failed");
        if ($this->request->isPost()) {
            $backend = new \OPNsense\Core\Backend();
            // Regenerate template
            $backend->configdRun('template reload OPNsense/KixDNS');
            // Restart service
            $backend->configdRun('kixdns restart');
            $result["status"] = "ok";
        }
        return $result;
    }

    /**
     * Get config JSON for editor
     */
    public function getConfigJsonAction()
    {
        try {
            $mdl = $this->getModel();
            $configJson = '';
            
            if (isset($mdl->general->config_json)) {
                $configJson = (string)$mdl->general->config_json;
            }
            
            return array('config_json' => $configJson);
        } catch (\Exception $e) {
            $this->logError('getConfigJsonAction exception', array(
                'message' => $e->getMessage()
            ));
            return array('result' => 'failed', 'message' => $e->getMessage(), 'config_json' => '');
        }
    }

    /**
     * Save config JSON from editor
     */
    public function saveConfigJsonAction()
    {
        $result = array("result" => "failed");
        
        if (!$this->request->isPost()) {
            $result['message'] = 'POST request required';
            return $result;
        }
        
        $post = $this->request->getPost();
        
        if (!isset($post['config_json'])) {
            $result['message'] = 'Missing config_json parameter';
            return $result;
        }
        
        $jsonStr = $post['config_json'];
        
        // Validate JSON
        $decoded = json_decode($jsonStr, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $result['message'] = 'Invalid JSON: ' . json_last_error_msg();
            return $result;
        }
        
        try {
            $mdl = $this->getModel();
            $mdl->general->config_json = $jsonStr;
            $mdl->serializeToConfig();
            Config::getInstance()->save();
            
            $result["result"] = "saved";
        } catch (\Exception $e) {
            $this->logError('saveConfigJsonAction exception', array(
                'message' => $e->getMessage()
            ));
            $result['message'] = $e->getMessage();
        }
        
        return $result;
    }

    /**
     * Simple file logging helper
     */
    private function logError(string $message, array $context = array()): void
    {
        $logFile = '/var/log/kixdns.log';
        $timestamp = date('Y-m-d H:i:s');
        $line = sprintf('[%s] [ERROR] %s', $timestamp, $message);
        if (!empty($context)) {
            $line .= ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        $line .= PHP_EOL;
        @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    }
}

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
                
                $result['general'] = array(
                    'enabled' => (string)$general->enabled,
                    'listener_label' => (string)$general->listener_label,
                    'debug' => (string)$general->debug,
                    'log_level' => (string)$general->log_level,
                );
            }
        } catch (\Throwable $e) {
            $this->logError('getAction exception', array('message' => $e->getMessage()));
        }
        
        // Fill defaults
        $defaults = array(
            'enabled' => '0',
            'listener_label' => 'default',
            'debug' => '0',
            'log_level' => 'info',
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
            $post = $this->request->getPost('general') ?: array();
            
            if ($mdl && isset($mdl->general)) {
                $general = $mdl->general;
                
                // Handle checkbox fields
                $general->enabled = isset($post['enabled']) && $post['enabled'] ? '1' : '0';
                $general->debug = isset($post['debug']) && $post['debug'] ? '1' : '0';
                
                // Text fields
                if (isset($post['listener_label'])) $general->listener_label = $post['listener_label'];
                if (isset($post['log_level'])) $general->log_level = $post['log_level'];
                
                // Validate and save
                $valMsgs = $mdl->performValidation();
                foreach ($valMsgs as $msg) {
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
            $this->logError('setAction exception', array('message' => $e->getMessage()));
            $result['message'] = $e->getMessage();
        }
        
        return $result;
    }

    /**
     * Reconfigure service
     */
    public function reconfigureAction()
    {
        $result = array("status" => "failed");
        if ($this->request->isPost()) {
            $backend = new \OPNsense\Core\Backend();
            $backend->configdRun('template reload OPNsense/KixDNS');
            $backend->configdRun('kixdns restart');
            $result["status"] = "ok";
        }
        return $result;
    }

    /**
     * Get config JSON for Pipeline Editor
     */
    public function getConfigJsonAction()
    {
        try {
            $mdl = $this->getModel();
            $configJson = '';
            
            if ($mdl && isset($mdl->general) && isset($mdl->general->config_json)) {
                $configJson = (string)$mdl->general->config_json;
            }
            
            $this->logDebug('getConfigJsonAction', array('length' => strlen($configJson)));
            return array('config_json' => $configJson);
        } catch (\Exception $e) {
            $this->logError('getConfigJsonAction exception', array('message' => $e->getMessage()));
            return array('result' => 'failed', 'message' => $e->getMessage(), 'config_json' => '');
        }
    }

    /**
     * Save config JSON from Pipeline Editor
     */
    public function saveConfigJsonAction()
    {
        $result = array("result" => "failed");
        
        if (!$this->request->isPost()) {
            $result['message'] = 'POST request required';
            return $result;
        }
        
        // Get POST data - try both methods
        $jsonStr = $this->request->getPost('config_json');
        if (empty($jsonStr)) {
            $post = $this->request->getPost();
            $jsonStr = isset($post['config_json']) ? $post['config_json'] : null;
        }
        
        $this->logDebug('saveConfigJsonAction received', array(
            'length' => $jsonStr ? strlen($jsonStr) : 0,
            'preview' => $jsonStr ? substr($jsonStr, 0, 100) : 'null'
        ));
        
        if (empty($jsonStr)) {
            $result['message'] = 'Missing or empty config_json parameter';
            return $result;
        }
        
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
            
            $this->logDebug('saveConfigJsonAction success', array('length' => strlen($jsonStr)));
            $result["result"] = "saved";
        } catch (\Exception $e) {
            $this->logError('saveConfigJsonAction exception', array('message' => $e->getMessage()));
            $result['message'] = $e->getMessage();
        }
        
        return $result;
    }
    
    private function logDebug(string $message, array $context = array()): void
    {
        $logFile = '/var/log/kixdns.log';
        $timestamp = date('Y-m-d H:i:s');
        $line = sprintf('[%s] [DEBUG] %s', $timestamp, $message);
        if (!empty($context)) {
            $line .= ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        @file_put_contents($logFile, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    private function logError(string $message, array $context = array()): void
    {
        $logFile = '/var/log/kixdns.log';
        $timestamp = date('Y-m-d H:i:s');
        $line = sprintf('[%s] [ERROR] %s', $timestamp, $message);
        if (!empty($context)) {
            $line .= ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        @file_put_contents($logFile, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}

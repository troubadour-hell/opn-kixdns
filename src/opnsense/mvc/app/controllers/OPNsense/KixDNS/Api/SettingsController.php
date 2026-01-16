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
                
                // Get current log_level value
                $logLevelValue = (string)$general->log_level ?: 'info';
                
                // Build log_level options with selected state
                $logLevelOptions = array(
                    'trace' => array('value' => 'Trace', 'selected' => ($logLevelValue === 'trace') ? 1 : 0),
                    'debug' => array('value' => 'Debug', 'selected' => ($logLevelValue === 'debug') ? 1 : 0),
                    'info' => array('value' => 'Info', 'selected' => ($logLevelValue === 'info') ? 1 : 0),
                    'warn' => array('value' => 'Warning', 'selected' => ($logLevelValue === 'warn') ? 1 : 0),
                    'error' => array('value' => 'Error', 'selected' => ($logLevelValue === 'error') ? 1 : 0),
                );
                
                $result['general'] = array(
                    'enabled' => (string)$general->enabled ?: '0',
                    'listener_label' => (string)$general->listener_label ?: 'default',
                    'debug' => (string)$general->debug ?: '0',
                    'log_level' => $logLevelOptions,
                );
            }
        } catch (\Throwable $e) {
            $this->logError('getAction exception', array('message' => $e->getMessage()));
            // Return defaults on error
            $result['general'] = array(
                'enabled' => '0',
                'listener_label' => 'default',
                'debug' => '0',
                'log_level' => array(
                    'trace' => array('value' => 'Trace', 'selected' => 0),
                    'debug' => array('value' => 'Debug', 'selected' => 0),
                    'info' => array('value' => 'Info', 'selected' => 1),
                    'warn' => array('value' => 'Warning', 'selected' => 0),
                    'error' => array('value' => 'Error', 'selected' => 0),
                ),
            );
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
            try {
                $backend = new \OPNsense\Core\Backend();
                
                // Reload templates first - this generates pipeline.json from config
                $templateResult = trim($backend->configdRun('template reload OPNsense/KixDNS'));
                $this->logDebug('reconfigureAction: template reload done', array('result' => $templateResult));
                
                // Use configd to run the configure action which handles rc.conf.d generation
                $configureResult = trim($backend->configdRun('kixdns configure'));
                $this->logDebug('reconfigureAction: configure done', array('result' => $configureResult));
                
                // Restart service
                $restartResult = trim($backend->configdRun('kixdns restart'));
                $this->logDebug('reconfigureAction: kixdns restart done', array('result' => $restartResult));
                
                $result["status"] = "ok";
            } catch (\Exception $e) {
                $this->logError('reconfigureAction exception', array('message' => $e->getMessage()));
                $result['message'] = $e->getMessage();
            }
        }
        return $result;
    }

    /**
     * Get config JSON for Pipeline Editor
     */
    public function getConfigJsonAction()
    {
        try {
            $configJson = '';
            
            // Read directly from config.xml to ensure we get latest data
            $cfgXml = Config::getInstance()->object();
            if (isset($cfgXml->OPNsense->kixdns->general->config_json)) {
                $configJson = (string)$cfgXml->OPNsense->kixdns->general->config_json;
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
            
            $cfgInstance = Config::getInstance();
            $cfgInstance->save();
            
            // Verify save by reading config.xml directly
            $cfgXml = $cfgInstance->object();
            $savedValue = '';
            if (isset($cfgXml->OPNsense->kixdns->general->config_json)) {
                $savedValue = (string)$cfgXml->OPNsense->kixdns->general->config_json;
            }
            
            $this->logDebug('saveConfigJsonAction success', array(
                'input_length' => strlen($jsonStr),
                'saved_length' => strlen($savedValue),
                'verified' => strlen($savedValue) > 0 ? 'yes' : 'no'
            ));
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

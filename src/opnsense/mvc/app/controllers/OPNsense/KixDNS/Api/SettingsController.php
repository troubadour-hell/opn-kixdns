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
     * Uses parent's getBase with node path 'general' and JSON key 'general'
     */
    public function getAction()
    {
        return $this->getBase('general', 'general');
    }

    /**
     * Save general settings
     * Uses parent's setBase with node path 'general' and JSON key 'general'  
     */
    public function setAction()
    {
        $result = $this->setBase('general', 'general');
        $this->logDebug('setAction result', array('result' => $result));
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

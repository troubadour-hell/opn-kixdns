<?php

namespace OPNsense\KixDNS\Api;

use OPNsense\Base\ApiMutableModelControllerBase;
use OPNsense\Core\Config;

class SettingsController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'KixDNS';
    protected static $internalModelClass = 'OPNsense\KixDNS\KixDNS';

    /**
     * Get general settings
     */
    public function getAction()
    {
        try {
            $result = $this->getBase('general');
            
            // Ensure returned data has 'general' key
            if (!isset($result['general'])) {
                $result = array('general' => $result);
            }
            
            return $result;
        } catch (\Throwable $e) {
            $this->logError('getAction exception', array(
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ));
            // Return default values on failure
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

    /**
     * Save general settings
     */
    public function setAction()
    {
        try {
            $result = $this->setBase('general');
            return $result;
        } catch (\Exception $e) {
            $this->logError('setAction exception', array(
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ));
            return array('result' => 'failed', 'message' => $e->getMessage());
        }
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

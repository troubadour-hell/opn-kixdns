<?php

namespace OPNsense\KixDNS\Api;

use OPNsense\Base\ApiMutableServiceControllerBase;
use OPNsense\Core\Backend;
use OPNsense\Core\Config;

/**
 * Service controller for KixDNS
 * Handles service start/stop/restart/status and reconfigure
 */
class ServiceController extends ApiMutableServiceControllerBase
{
    protected static $internalServiceClass = 'OPNsense\KixDNS\KixDNS';
    protected static $internalServiceTemplate = 'OPNsense/KixDNS';
    protected static $internalServiceEnabled = 'general.enabled';
    protected static $internalServiceName = 'kixdns';

    /**
     * Reconfigure service
     */
    public function reconfigureAction()
    {
        $status = "failed";
        if ($this->request->isPost()) {
            $this->sessionClose();
            
            $backend = new Backend();
            
            // Generate rc.conf.d
            $this->generateRcConf();
            
            // Reload templates
            $backend->configdRun('template reload OPNsense/KixDNS');
            
            // Restart service
            $backend->configdRun('kixdns restart');
            
            $status = "ok";
        }
        return array("status" => $status);
    }
    
    /**
     * Generate /etc/rc.conf.d/kixdns from current config
     */
    private function generateRcConf()
    {
        $config = Config::getInstance()->toArray();
        
        $kixdns_config = array();
        
        if (isset($config['OPNsense']['kixdns']['general'])) {
            $general = $config['OPNsense']['kixdns']['general'];
            
            $kixdns_config['kixdns_enable'] = ($general['enabled'] ?? '0') == '1' ? 'YES' : 'NO';
            $kixdns_config['kixdns_config'] = '/usr/local/etc/kixdns/pipeline.json';
            $kixdns_config['kixdns_listener_label'] = $general['listener_label'] ?? 'default';
            $kixdns_config['kixdns_debug'] = ($general['debug'] ?? '0') == '1' ? 'YES' : 'NO';
            $kixdns_config['kixdns_udp_workers'] = '0';
        } else {
            $kixdns_config['kixdns_enable'] = 'NO';
        }
        
        $rc_content = "";
        foreach ($kixdns_config as $key => $value) {
            $rc_content .= "{$key}=\"{$value}\"\n";
        }
        
        @file_put_contents('/etc/rc.conf.d/kixdns', $rc_content);
        @chmod('/etc/rc.conf.d/kixdns', 0644);
    }
}

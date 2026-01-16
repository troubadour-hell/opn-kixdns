<?php

/**
 * KixDNS plugin service registration
 */
function kixdns_services()
{
    global $config;
    $services = array();

    // Check if service is enabled based on model mount point //OPNsense/kixdns
    if (isset($config['OPNsense']['kixdns']['general']['enabled']) &&
        $config['OPNsense']['kixdns']['general']['enabled'] == 1) {
        $services[] = array(
            'description' => gettext('KixDNS'),
            'configd' => array(
                'restart' => array('kixdns restart'),
                'start'   => array('kixdns startup'),
                'stop'    => array('kixdns stop'),
            ),
            'name' => 'kixdns',
            'pidfile' => '/var/run/kixdns.pid',
        );
    }

    return $services;
}

/**
 * Generate rc.conf.d settings from OPNsense config
 */
function kixdns_configure()
{
    return array('bootup' => array('kixdns_configure_do'));
}

function kixdns_configure_do($verbose = false)
{
    global $config;
    
    $kixdns_config = array();
    
    if (isset($config['OPNsense']['kixdns']['general'])) {
        $general = $config['OPNsense']['kixdns']['general'];
        
        // Enable/disable
        $kixdns_config['kixdns_enable'] = ($general['enabled'] ?? '0') == '1' ? 'YES' : 'NO';
        
        // Config file path
        $kixdns_config['kixdns_config'] = $general['config_path'] ?? '/usr/local/etc/kixdns/pipeline.json';
        
        // Listener label
        $kixdns_config['kixdns_listener_label'] = $general['listener_label'] ?? 'default';
        
        // Debug mode
        $kixdns_config['kixdns_debug'] = ($general['debug'] ?? '0') == '1' ? 'YES' : 'NO';
        
        // UDP workers
        $kixdns_config['kixdns_udp_workers'] = $general['udp_workers'] ?? '0';
    } else {
        $kixdns_config['kixdns_enable'] = 'NO';
    }
    
    // Write rc.conf.d file
    $rc_content = "";
    foreach ($kixdns_config as $key => $value) {
        $rc_content .= "{$key}=\"{$value}\"\n";
    }
    
    @file_put_contents('/etc/rc.conf.d/kixdns', $rc_content);
    @chmod('/etc/rc.conf.d/kixdns', 0644);
    
    return 0;
}

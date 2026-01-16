<?php

/**
 * KixDNS plugin service registration
 */
function kixdns_services()
{
    global $config;
    $services = array();

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
    
    return 0;
}

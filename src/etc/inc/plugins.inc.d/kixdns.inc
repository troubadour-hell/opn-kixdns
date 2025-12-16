<?php

function kixdns_services()
{
    global $config;
    $services = array();

    // 根据模型挂载点 //OPNsense/kixdns 读取启用开关
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



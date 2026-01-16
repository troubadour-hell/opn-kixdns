#!/usr/local/bin/php
<?php

/**
 * KixDNS configuration script
 * Generates /etc/rc.conf.d/kixdns from OPNsense config
 */

require_once("config.inc");
require_once("util.inc");

use OPNsense\Core\Config;

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

echo "OK\n";

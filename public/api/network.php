<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Network.php';
require_once __DIR__ . '/../classes/VPN.php';

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

echo json_encode([
    'wan_ip' => Network::getInterfaceIp(WAN_INTERFACE),
    'lan_ip' => Network::getInterfaceIp(LAN_INTERFACE),
    'vpn_ip' => Network::getInterfaceIp(VPN_INTERFACE),

    'gateway' => Network::getGateway(),
    'dns' => Network::getDnsServers(),

    'exit_ip' => VPN::getExitIP(),
    'ping' => Network::getPing('194.93.11.96')
], JSON_PRETTY_PRINT);

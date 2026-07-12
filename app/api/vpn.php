<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/VPN.php';
require_once __DIR__ . '/../classes/Network.php';

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

echo json_encode([
    'status' => VPN::getStatus(),
    'uptime' => VPN::getUptime(),
    'exit_ip' => VPN::getExitIP(),
    'tunnel_ip' => Network::getInterfaceIp(VPN_INTERFACE),
    'server_ping' => Network::getPing('194.93.11.96')
], JSON_PRETTY_PRINT);

<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Network.php';

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

echo json_encode([
    'wan' => Network::getInterfaceBytes(WAN_INTERFACE),
    'vpn' => Network::getInterfaceBytes(VPN_INTERFACE)
], JSON_PRETTY_PRINT);

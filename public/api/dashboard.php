<?php

require_once __DIR__ . '/../config/config.php';

require_once __DIR__ . '/../classes/System.php';
require_once __DIR__ . '/../classes/Network.php';
require_once __DIR__ . '/../classes/VPN.php';

header('Content-Type: application/json');
header(
    'Cache-Control: no-store, no-cache, must-revalidate, max-age=0'
);

echo json_encode([
    'application' => APP_NAME,
    'version' => APP_VERSION,

    'vpn' => [

        'status'        => VPN::getStatus(),
        'exit_ip'       => VPN::getExitIP(),
        'uptime'        => VPN::getUptime(),

        'profile'       => VPN::getActiveProfile(),
        'provider'      => VPN::getProvider(),
        'type'          => VPN::getType(),
        'endpoint'      => VPN::getEndpoint(),

        'manual'        => VPN::isManual()
    ],

    'system' => [

        'hostname'      => SystemInfo::getHostname(),
        'os'            => SystemInfo::getOS(),
        'model'         => SystemInfo::getModel(),
        'cpu_load'      => SystemInfo::getCpuLoad(),
        'memory'        => SystemInfo::getMemory(),
        'temperature'   => SystemInfo::getTemperature(),
        'uptime'        => SystemInfo::getUptime()
    ],

    'network' => [

        'wan_ip'        => Network::getInterfaceIp(
                                WAN_INTERFACE
                           ),

        'lan_ip'        => Network::getInterfaceIp(
                                LAN_INTERFACE
                           ),

        'vpn_ip'        => Network::getInterfaceIp(
                                VPN_INTERFACE
                           ),

        'wan_traffic'   => Network::getTraffic(
                                WAN_INTERFACE
                           ),

        'vpn_traffic'   => Network::getTraffic(
                                VPN_INTERFACE
                           ),

        'server_ping'   => Network::getPing(
                                '194.93.11.96'
                           )
    ]

], JSON_PRETTY_PRINT);

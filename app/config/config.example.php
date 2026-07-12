<?php

return [
    'database' => [
        'path' => '/opt/tkgateway/database/gateway.db',
    ],

    'application' => [
        'name' => 'TK Gateway',
        'environment' => 'development',
        'debug' => false,
    ],

    'network' => [
        'wan_interface' => 'eth0',
        'lan_interface' => 'eth1',
        'lan_network' => '192.168.50.0/24',
    ],
];

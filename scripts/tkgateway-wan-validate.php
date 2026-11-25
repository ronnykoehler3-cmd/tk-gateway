<?php
$path = $argv[1] ?? '/etc/tkgateway/triplewan.conf';
$c = parse_ini_file($path, true, INI_SCANNER_RAW);

function fail($message) {
    fwrite(STDERR, "FEHLER: $message\n");
    exit(1);
}

if ($c === false) {
    fail("Konfiguration nicht lesbar");
}

$macs = [];

foreach (['PRIMARY', 'BACKUP', 'LTE'] as $role) {
    $mac = $c['wan'][$role . '_MAC'] ?? '';
    if (!preg_match('/^([0-9a-f]{2}:){5}[0-9a-f]{2}$/D', $mac)) {
        fail("Ungueltige MAC: $role");
    }
    $macs[] = $mac;
}

if (count(array_unique($macs)) !== 3) {
    fail("Doppelte MAC-Adressen");
}

$expected = [
    'wan' => [
        'PRIMARY_TABLE' => '201',
        'BACKUP_TABLE' => '202',
        'LTE_TABLE' => '203'
    ],
    'lan' => [
        'LAN_INTERFACE' => 'eth1',
        'LAN_NETWORK' => '192.168.50.0/24',
        'LAN_IP' => '192.168.50.1',
        'LAN_TABLE' => '100'
    ],
    'vpn' => [
        'XRAY_UID' => '65534',
        'TUN_INTERFACE' => 'xraytun',
        'TUN_ADDRESS' => '198.18.0.1/15',
        'SOCKS_ADDRESS' => '127.0.0.1:10808'
    ],
    'failover' => [
        'CHECK_INTERVAL' => '30',
        'FAIL_COUNT' => '3',
        'RECOVERY_COUNT' => '5'
    ]
];

foreach ($expected as $section => $values) {
    foreach ($values as $key => $value) {
        if (($c[$section][$key] ?? '') !== $value) {
            fail("Nicht unterstuetzter Wert: $section/$key");
        }
    }
}

echo "OK: Konfiguration vollstaendig und gueltig\n";

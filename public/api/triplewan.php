<?php

$cfg = @parse_ini_file('/etc/tkgateway/triplewan.conf', true, INI_SCANNER_RAW);
$wanConfig = $cfg['wan'] ?? [];

foreach (['PRIMARY_MAC', 'BACKUP_MAC', 'LTE_MAC'] as $key) {
    if (!isset($wanConfig[$key]) ||
        !preg_match('/^([0-9a-f]{2}:){5}[0-9a-f]{2}$/iD', $wanConfig[$key])) {
        http_response_code(503);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'WAN-Konfiguration fehlt oder ist ungueltig']);
        exit;
    }
    $wanConfig[$key] = strtolower($wanConfig[$key]);
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$roles = [
    'PRIMARY' => ['name' => 'WAN 1 – Hauptleitung', 'mac' => $wanConfig['PRIMARY_MAC']],
    'BACKUP'  => ['name' => 'WAN 2 – Backupleitung', 'mac' => $wanConfig['BACKUP_MAC']],
    'LTE'     => ['name' => 'WAN 3 – LTE', 'mac' => $wanConfig['LTE_MAC']],
];

$active = trim(@file_get_contents(
    '/var/lib/tkgateway-triplewan-live/active'
) ?: '');

$interfaces = glob('/sys/class/net/*') ?: [];
$result = [];

foreach ($roles as $role => $config) {
    $device = null;

    foreach ($interfaces as $path) {
        $mac = strtolower(trim(
            @file_get_contents("$path/address") ?: ''
        ));

        if ($mac === $config['mac']) {
            $device = basename($path);
            break;
        }
    }

    $ip = '-';
    $gateway = '-';
    $link = false;

    if ($device !== null) {
        $carrier = trim(
            @file_get_contents("/sys/class/net/$device/carrier") ?: ''
        );
        $link = $carrier === '1';

        $addressOutput = shell_exec(
            'ip -4 -o addr show dev ' .
            escapeshellarg($device) . ' 2>/dev/null'
        ) ?: '';

        if (preg_match('/\binet\s+([0-9.]+)\//', $addressOutput, $m)) {
            $ip = $m[1];
        }

        $routeOutput = shell_exec(
            'ip -4 route show default dev ' .
            escapeshellarg($device) . ' 2>/dev/null'
        ) ?: '';

        if (preg_match('/\bvia\s+([0-9.]+)/', $routeOutput, $m)) {
            $gateway = $m[1];
        }
    }

    $result[] = [
        'role' => $role,
        'name' => $config['name'],
        'interface' => $device ?? '-',
        'ip' => $ip,
        'gateway' => $gateway,
        'link' => $link,
        'active' => $role === $active,
    ];
}

echo json_encode([
    'active' => $active,
    'manual' => trim(file_get_contents('/var/lib/tkgateway-triplewan-live/manual')),
    'interfaces' => $result,
], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

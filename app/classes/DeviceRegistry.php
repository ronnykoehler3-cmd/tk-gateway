<?php

class DeviceRegistry
{
    private const REGISTRY_FILE = '/var/lib/tk-gateway/devices.json';
    private const APPLY_HELPER = '/usr/local/sbin/tk-gateway-apply-reservations';

    public static function normalizeMac(string $mac): string
    {
        $hex = strtoupper(preg_replace('/[^0-9A-Fa-f]/', '', $mac));
        if (strlen($hex) !== 12) {
            throw new InvalidArgumentException('Ungültige MAC-Adresse.');
        }
        return implode(':', str_split($hex, 2));
    }

    public static function load(): array
    {
        if (!is_file(self::REGISTRY_FILE)) {
            return [];
        }

        $raw = file_get_contents(self::REGISTRY_FILE);
        $data = json_decode($raw ?: '[]', true);
        if (!is_array($data)) {
            return [];
        }

        $result = [];
        foreach ($data as $mac => $device) {
            if (!is_array($device)) {
                continue;
            }
            try {
                $norm = self::normalizeMac($device['mac'] ?? $mac);
            } catch (Throwable $e) {
                continue;
            }
            $device['mac'] = $norm;
            $result[$norm] = $device;
        }
        return $result;
    }

    private static function saveAll(array $devices): void
    {
        $dir = dirname(self::REGISTRY_FILE);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('Geräteverzeichnis konnte nicht angelegt werden.');
        }

        ksort($devices);
        $tmp = self::REGISTRY_FILE . '.tmp';
        $json = json_encode($devices, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false || file_put_contents($tmp, $json . "\n", LOCK_EX) === false) {
            throw new RuntimeException('Gerätedaten konnten nicht gespeichert werden.');
        }
        chmod($tmp, 0664);
        if (!rename($tmp, self::REGISTRY_FILE)) {
            @unlink($tmp);
            throw new RuntimeException('Gerätedaten konnten nicht übernommen werden.');
        }
    }

    public static function syncDetected(array $clients): array
    {
        $devices = self::load();
        $changed = false;

        foreach ($clients as $client) {
            if (empty($client['mac'])) {
                continue;
            }
            try {
                $mac = self::normalizeMac($client['mac']);
            } catch (Throwable $e) {
                continue;
            }

            if (!isset($devices[$mac])) {
                $devices[$mac] = [
                    'mac' => $mac,
                    'ip' => (string)($client['ip'] ?? ''),
                    'hostname' => (string)($client['hostname'] ?? ''),
                    'display_name' => '',
                    'phone' => '',
                    'type' => '',
                    'note' => '',
                    'fixed_ip' => false,
                    'created_at' => date(DATE_ATOM),
                    'updated_at' => date(DATE_ATOM),
                ];
                $changed = true;
                continue;
            }

            if (empty($devices[$mac]['fixed_ip']) && !empty($client['ip']) && ($devices[$mac]['ip'] ?? '') !== $client['ip']) {
                $devices[$mac]['ip'] = (string)$client['ip'];
                $devices[$mac]['updated_at'] = date(DATE_ATOM);
                $changed = true;
            }
            if (!empty($client['hostname']) && $client['hostname'] !== 'Unbekannt' && ($devices[$mac]['hostname'] ?? '') !== $client['hostname']) {
                $devices[$mac]['hostname'] = (string)$client['hostname'];
                $devices[$mac]['updated_at'] = date(DATE_ATOM);
                $changed = true;
            }
        }

        if ($changed) {
            self::saveAll($devices);
        }
        return $devices;
    }

    public static function mergeClient(array $client, array $devices): array
    {
        $mac = self::normalizeMac($client['mac']);
        $device = $devices[$mac] ?? [];
        return array_merge($client, [
            'display_name' => (string)($device['display_name'] ?? ''),
            'phone' => (string)($device['phone'] ?? ''),
            'type' => (string)($device['type'] ?? ''),
            'note' => (string)($device['note'] ?? ''),
            'fixed_ip' => !empty($device['fixed_ip']),
            'managed_ip' => (string)($device['ip'] ?? ($client['ip'] ?? '')),
            'name' => trim((string)($device['display_name'] ?? '')) !== ''
                ? (string)$device['display_name']
                : (string)($client['hostname'] ?? 'Unbekannt'),
        ]);
    }

    public static function saveDevice(array $input): array
    {
        $mac = self::normalizeMac((string)($input['mac'] ?? ''));
        $ip = trim((string)($input['ip'] ?? ''));
        if ($ip === '' || filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            throw new InvalidArgumentException('Bitte eine gültige IPv4-Adresse angeben.');
        }

        $fixed = filter_var($input['fixed_ip'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $devices = self::load();

        if ($fixed) {
            foreach ($devices as $otherMac => $other) {
                if ($otherMac !== $mac && !empty($other['fixed_ip']) && ($other['ip'] ?? '') === $ip) {
                    throw new InvalidArgumentException('Diese feste IP-Adresse ist bereits einem anderen Gerät zugeordnet.');
                }
            }
        }

        $old = $devices[$mac] ?? [];
        $devices[$mac] = [
            'mac' => $mac,
            'ip' => $ip,
            'hostname' => trim((string)($input['hostname'] ?? ($old['hostname'] ?? ''))),
            'display_name' => trim((string)($input['display_name'] ?? '')),
            'phone' => trim((string)($input['phone'] ?? '')),
            'type' => trim((string)($input['type'] ?? '')),
            'note' => trim((string)($input['note'] ?? '')),
            'fixed_ip' => $fixed,
            'created_at' => $old['created_at'] ?? date(DATE_ATOM),
            'updated_at' => date(DATE_ATOM),
        ];

        self::saveAll($devices);

        $apply = self::applyReservations();
        if (!$apply['ok']) {
            throw new RuntimeException('Gerät gespeichert, DHCP-Reservierung konnte aber nicht angewendet werden: ' . $apply['message']);
        }

        return $devices[$mac];
    }

    public static function applyReservations(): array
    {
        $cmd = escapeshellarg(self::APPLY_HELPER);
        if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
            $command = $cmd . ' 2>&1';
        } else {
            $command = 'sudo -n ' . $cmd . ' 2>&1';
        }
        $out = [];
        $rc = 0;
        exec($command, $out, $rc);
        return [
            'ok' => $rc === 0,
            'message' => trim(implode("\n", $out)) ?: ($rc === 0 ? 'OK' : 'Helper nicht verfügbar oder nicht freigegeben'),
        ];
    }
}

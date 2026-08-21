<?php

require_once __DIR__ . '/DeviceRegistry.php';

class Clients
{
    private static function getArpTable(): array
    {
        $arp = [];
        $lines = explode("\n", trim((string)shell_exec("ip neigh show dev eth1")));

        foreach ($lines as $line) {
            if (empty($line)) {
                continue;
            }
            preg_match('/^([0-9\.]+).*lladdr ([0-9a-f:]+).*$/i', $line, $matches);
            if (isset($matches[1], $matches[2])) {
                $arp[strtoupper($matches[2])] = true;
            }
        }
        return $arp;
    }

    private static function getVendor(string $mac): string
    {
        $oui = strtoupper(substr(str_replace(':', '', $mac), 0, 6));
        $vendors = [
            '00E04C' => 'Realtek',
            '7C2F80' => 'Fanvil',
            'F4F5E8' => 'Apple',
            '3C5282' => 'Google',
            'DCA632' => 'Samsung / Android',
            'B827EB' => 'Raspberry Pi'
        ];
        return $vendors[$oui] ?? 'Unbekannt';
    }

    private static function detectedClients(): array
    {
        $clients = [];
        $arp = self::getArpTable();

        if (!file_exists('/var/lib/misc/dnsmasq.leases')) {
            return [];
        }

        foreach (file('/var/lib/misc/dnsmasq.leases') as $lease) {
            $parts = preg_split('/\s+/', trim($lease));
            if (count($parts) < 4) {
                continue;
            }
            $mac = strtoupper($parts[1]);
            $clients[$mac] = [
                'online' => isset($arp[$mac]),
                'expires' => date('Y-m-d H:i:s', (int)$parts[0]),
                'mac' => $mac,
                'vendor' => self::getVendor($mac),
                'ip' => $parts[2],
                'hostname' => $parts[3] === '*' ? 'Unbekannt' : $parts[3]
            ];
        }
        return $clients;
    }

    public static function getClients(): array
    {
        $detected = self::detectedClients();
        $devices = DeviceRegistry::syncDetected(array_values($detected));
        $clients = [];

        foreach ($detected as $mac => $client) {
            $clients[] = DeviceRegistry::mergeClient($client, $devices);
            unset($devices[$mac]);
        }

        // Bereits verwaltete, aktuell aber nicht geleaste Geräte bleiben sichtbar.
        foreach ($devices as $mac => $device) {
            $clients[] = [
                'online' => false,
                'expires' => '-',
                'mac' => $mac,
                'vendor' => self::getVendor($mac),
                'ip' => (string)($device['ip'] ?? ''),
                'hostname' => (string)($device['hostname'] ?? 'Unbekannt'),
                'display_name' => (string)($device['display_name'] ?? ''),
                'phone' => (string)($device['phone'] ?? ''),
                'type' => (string)($device['type'] ?? ''),
                'note' => (string)($device['note'] ?? ''),
                'fixed_ip' => !empty($device['fixed_ip']),
                'managed_ip' => (string)($device['ip'] ?? ''),
                'name' => trim((string)($device['display_name'] ?? '')) !== ''
                    ? (string)$device['display_name']
                    : (string)($device['hostname'] ?? 'Unbekannt'),
            ];
        }

        usort($clients, function ($a, $b) {
            $online = ($b['online'] <=> $a['online']);
            if ($online !== 0) {
                return $online;
            }
            return strnatcasecmp((string)$a['ip'], (string)$b['ip']);
        });

        return $clients;
    }

    public static function getCount(): int
    {
        return count(self::getClients());
    }

    public static function getOnlineCount(): int
    {
        $count = 0;
        foreach (self::getClients() as $client) {
            if ($client['online']) {
                $count++;
            }
        }
        return $count;
    }
}

<?php

class Clients
{
    private static function getArpTable(): array
    {
        $arp = [];

        $lines = explode("\n", trim(shell_exec("ip neigh show dev eth1")));

        foreach ($lines as $line)
        {
            if(empty($line))
                continue;

            preg_match('/^([0-9\.]+).*lladdr ([0-9a-f:]+).*$/i', $line, $matches);

            if(isset($matches[1]) && isset($matches[2]))
            {
                $arp[strtoupper($matches[2])] = true;
            }
        }

        return $arp;
    }

    private static function getVendor(string $mac): string
    {
        $oui = strtoupper(substr(str_replace(':','',$mac),0,6));

        $vendors = [
            '00E04C' => 'Realtek',
            '7C2F80' => 'Fanvil',
            'F4F5E8' => 'Apple',
            '3C5282' => 'Google',
            'DCA632' => 'Samsung',
            'B827EB' => 'Raspberry Pi',
            'DCA632' => 'Android'
        ];

        return $vendors[$oui] ?? 'Unbekannt';
    }

    public static function getClients(): array
    {
        $clients = [];
        $arp = self::getArpTable();

        if (!file_exists('/var/lib/misc/dnsmasq.leases'))
            return [];

        $leases = file('/var/lib/misc/dnsmasq.leases');

        foreach ($leases as $lease)
        {
            $parts = preg_split('/\s+/', trim($lease));

            if(count($parts) < 4)
                continue;

            $mac = strtoupper($parts[1]);

            $clients[] = [
                'online'    => isset($arp[$mac]),
                'expires'   => date('Y-m-d H:i:s', $parts[0]),
                'mac'       => $mac,
                'vendor'    => self::getVendor($mac),
                'ip'        => $parts[2],
                'hostname'  => $parts[3] === '*' ? 'Unbekannt' : $parts[3]
            ];
        }

        usort($clients, function($a,$b){
            return $b['online'] <=> $a['online'];
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

        foreach(self::getClients() as $client)
        {
            if($client['online'])
                $count++;
        }

        return $count;
    }
}

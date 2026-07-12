<?php

class Network
{
    public static function getInterfaceIp(string $interface): ?string
    {
        $output = shell_exec(
            "ip -4 addr show {$interface} | grep inet | awk '{print \$2}' | cut -d/ -f1"
        );

        $ip = trim($output);

        return $ip ?: null;
    }

    public static function getTraffic(string $interface): array
    {
        $rx = @file_get_contents("/sys/class/net/{$interface}/statistics/rx_bytes");
        $tx = @file_get_contents("/sys/class/net/{$interface}/statistics/tx_bytes");

        return [
            'rx_mb' => round($rx / 1024 / 1024,2),
            'tx_mb' => round($tx / 1024 / 1024,2)
        ];
    }

    public static function getInterfaceBytes(string $interface): array
    {
        return [
            'rx' => (int)trim(
                @file_get_contents(
                    "/sys/class/net/{$interface}/statistics/rx_bytes"
                )
            ),
            'tx' => (int)trim(
                @file_get_contents(
                    "/sys/class/net/{$interface}/statistics/tx_bytes"
                )
            )
        ];
    }

    public static function getPing(string $host): string
    {
        $ping = shell_exec(
            "ping -c 1 -W 1 {$host} | grep 'time=' | awk -F'time=' '{print \$2}' | cut -d' ' -f1"
        );

        return trim($ping) ?: "Timeout";
    }

    public static function getGateway(): string
    {
        return trim(
            shell_exec(
                "ip route | grep default | awk '{print \$3}'"
            )
        );
    }

    public static function getDnsServers(): array
    {
        $dns = [];

        $lines = file('/etc/resolv.conf');

        foreach($lines as $line)
        {
            if(str_starts_with($line,'nameserver'))
            {
                $parts = explode(' ',trim($line));
                $dns[] = $parts[1];
            }
        }

        return $dns;
    }
}

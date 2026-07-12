<?php

class VPN
{
    private static function getDB()
    {
        return new SQLite3(
            '/opt/tkgateway/database/gateway.db'
        );
    }

    public static function getStatus(): string
    {
        return trim(
            shell_exec(
                'systemctl is-active ' .
                VPN_SERVICE
            )
        );
    }

    public static function getExitIP(): string
    {
        $ip = trim(
            shell_exec(
                "curl -4 -s --max-time 5 https://api.ipify.org"
            )
        );

        return $ip ?: "nicht erreichbar";
    }

    public static function getUptime(): string
    {
        $timestamp = trim(
            shell_exec(
                "systemctl show sing-box --property=ActiveEnterTimestampMonotonic --value"
            )
        );

        if(
            empty($timestamp) ||
            $timestamp == "0"
        )
        {
            return "Unbekannt";
        }

        $seconds =
            floor(
                $timestamp / 1000000
            );

        $boot =
            explode(
                ' ',
                trim(
                    file_get_contents(
                        '/proc/uptime'
                    )
                )
            );

        $systemUptime =
            (int)$boot[0];

        $vpnSeconds =
            $systemUptime -
            $seconds;

        if($vpnSeconds < 0)
            $vpnSeconds = 0;

        $days =
            floor(
                $vpnSeconds / 86400
            );

        $vpnSeconds %= 86400;

        $hours =
            floor(
                $vpnSeconds / 3600
            );

        $vpnSeconds %= 3600;

        $minutes =
            floor(
                $vpnSeconds / 60
            );

        $parts=[];

        if($days > 0)
            $parts[] =
                $days .
                ' Tage';

        if($hours > 0)
            $parts[] =
                $hours .
                ' Stunden';

        if($minutes > 0)
            $parts[] =
                $minutes .
                ' Minuten';

        return implode(
            ', ',
            $parts
        );
    }

    public static function isConnected(): bool
    {
        return
            self::getStatus()
            ===
            'active';
    }

    public static function getActiveProfile()
    {
        $db=self::getDB();

        return $db->querySingle(
        "
        SELECT name
        FROM vpn_profiles
        WHERE active=1
        LIMIT 1
        "
        ) ?: 'Kein Profil';
    }

    public static function getProvider()
    {
        $db=self::getDB();

        return $db->querySingle(
        "
        SELECT provider
        FROM vpn_profiles
        WHERE active=1
        LIMIT 1
        "
        ) ?: 'Unbekannt';
    }

    public static function getType()
    {
        $db=self::getDB();

        return $db->querySingle(
        "
        SELECT type
        FROM vpn_profiles
        WHERE active=1
        LIMIT 1
        "
        ) ?: 'Unbekannt';
    }

    public static function getEndpoint()
    {
        $db=self::getDB();

        return $db->querySingle(
        "
        SELECT endpoint
        FROM vpn_profiles
        WHERE active=1
        LIMIT 1
        "
        ) ?: 'Unbekannt';
    }

    public static function isManual()
    {
        $db=self::getDB();

        return $db->querySingle(
        "
        SELECT manual_selected
        FROM vpn_profiles
        WHERE active=1
        LIMIT 1
        "
        ) == 1;
    }
}

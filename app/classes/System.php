<?php

class SystemInfo
{
    public static function getCpuLoad(): float
    {
        return round(sys_getloadavg()[0], 2);
    }

    public static function getUptime(): string
    {
        $uptimeData = explode(' ', trim(file_get_contents('/proc/uptime')));
        $seconds = (int)$uptimeData[0];

        $days = floor($seconds / 86400);
        $seconds %= 86400;

        $hours = floor($seconds / 3600);
        $seconds %= 3600;

        $minutes = floor($seconds / 60);

        $parts = [];

        if ($days > 0) {
            $parts[] = $days . ' Tag' . ($days != 1 ? 'e' : '');
        }

        if ($hours > 0) {
            $parts[] = $hours . ' Stunde' . ($hours != 1 ? 'n' : '');
        }

        if ($minutes > 0) {
            $parts[] = $minutes . ' Minute' . ($minutes != 1 ? 'n' : '');
        }

        if (empty($parts)) {
            return 'Weniger als eine Minute';
        }

        return implode(', ', $parts);
    }

    public static function getTemperature(): float
    {
        $temp = @file_get_contents('/sys/class/thermal/thermal_zone0/temp');

        if ($temp === false) {
            return 0;
        }

        return round($temp / 1000, 1);
    }

    public static function getMemory(): array
    {
        $meminfo = file('/proc/meminfo');

        $total = (int) filter_var($meminfo[0], FILTER_SANITIZE_NUMBER_INT);
        $available = (int) filter_var($meminfo[2], FILTER_SANITIZE_NUMBER_INT);

        $used = $total - $available;

        return [
            'total_mb' => round($total / 1024),
            'used_mb' => round($used / 1024),
            'percent' => round(($used / $total) * 100, 1)
        ];
    }

    public static function getHostname(): string
    {
        return gethostname();
    }

    public static function getOS(): string
    {
        return trim(shell_exec("grep PRETTY_NAME /etc/os-release | cut -d= -f2 | tr -d '\"'"));
    }

    public static function getModel(): string
    {
        return trim(shell_exec("cat /proc/device-tree/model 2>/dev/null"));
    }
}

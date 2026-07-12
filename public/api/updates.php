<?php

header('Content-Type: application/json');

exec("apt list --upgradable 2>/dev/null", $output);

$updates = [];

foreach($output as $line)
{
    if(str_contains($line,'upgradable from'))
    {
        $package = explode('/', $line)[0];
        $updates[] = $package;
    }
}

echo json_encode([
    'count' => count($updates),
    'checked' => date('d.m.Y H:i:s'),
    'packages' => $updates
], JSON_PRETTY_PRINT);

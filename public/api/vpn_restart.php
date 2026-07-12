<?php

require_once
'/opt/tkgateway/backend/classes/EventLogger.php';

header('Content-Type: application/json');

exec(
    'sudo systemctl restart sing-box 2>&1',
    $output,
    $returnCode
);

if($returnCode === 0)
{
    EventLogger::log(
        'WARNING',
        'VPN Dienst wurde neu gestartet'
    );
}

echo json_encode([
    'success'=>$returnCode === 0,
    'output'=>$output
]);

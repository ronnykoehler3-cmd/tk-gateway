<?php

require_once
'/opt/tkgateway/backend/classes/EventLogger.php';

header('Content-Type: application/json');

EventLogger::log(
    'WARNING',
    'Gateway Neustart wurde ausgelöst'
);

exec(
    'sudo systemctl reboot 2>&1',
    $output,
    $returnCode
);

echo json_encode([
    'success'=>$returnCode === 0
]);

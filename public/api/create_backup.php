<?php

require_once
'/opt/tkgateway/backend/classes/EventLogger.php';

header('Content-Type: application/json');

exec(
    'sudo /opt/tkgateway/scripts/create_backup.sh 2>&1',
    $output,
    $rc
);

if($rc === 0)
{
    EventLogger::log(
        'INFO',
        'Backup wurde erstellt'
    );
}

echo json_encode([
    'success' => $rc === 0,
    'output' => $output
]);

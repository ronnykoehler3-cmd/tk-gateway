<?php

require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Events.php';

header('Content-Type: application/json');

$output = trim(
    shell_exec(
        'sudo /opt/tkgateway/scripts/create_backup.sh'
    )
);

$success = file_exists($output);

if($success)
{
    Events::add(
        'INFO',
        'Backup erstellt: ' . basename($output)
    );
}

echo json_encode([
    'success' => $success,
    'file' => basename($output)
]);

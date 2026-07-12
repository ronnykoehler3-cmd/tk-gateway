<?php

header('Content-Type: application/json');

$backupScript='/opt/tkgateway/scripts/create_backup.sh';

exec(
    "sudo {$backupScript} 2>&1",
    $backupOutput,
    $backupResult
);

if($backupResult !== 0)
{
    echo json_encode([
        'success' => false,
        'message' => 'Backup konnte nicht erstellt werden.'
    ]);

    exit;
}

exec(
    "nohup bash -c 'apt update && apt -y upgrade' >/tmp/tkgateway-update.log 2>&1 &"
);

echo json_encode([
    'success' => true,
    'message' => 'Backup erstellt und Update gestartet.'
]);

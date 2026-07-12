<?php

header('Content-Type: application/json');

if(!isset($_GET['file']))
{
    echo json_encode([
        'success' => false,
        'message' => 'Datei fehlt'
    ]);
    exit;
}

$file = basename($_GET['file']);

$path = "/opt/tkgateway/uploads/" . $file;

exec(
    "sudo /opt/tkgateway/scripts/import_config.sh " .
    escapeshellarg($path),
    $output,
    $result
);

echo json_encode([
    'success' => $result === 0,
    'output' => $output
]);

<?php

header('Content-Type: application/json');

$output = shell_exec(
    'sudo /opt/tkgateway/scripts/export_config.sh'
);

$file = trim($output);

if(file_exists($file))
{
    echo json_encode([
        'success' => true,
        'file' => basename($file)
    ]);
}
else
{
    echo json_encode([
        'success' => false
    ]);
}

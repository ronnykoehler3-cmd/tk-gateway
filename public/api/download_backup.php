<?php

if (!isset($_GET['file']))
{
    http_response_code(400);
    exit('Dateiname fehlt');
}

$file = basename($_GET['file']);

$backupPath =
    '/opt/tkgateway/backups/' . $file;

$configPath =
    '/opt/tkgateway/config-export/' . $file;

if(file_exists($backupPath))
{
    $path = $backupPath;
}
elseif(file_exists($configPath))
{
    $path = $configPath;
}
else
{
    http_response_code(404);
    exit('Datei nicht gefunden');
}

header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header(
    'Content-Disposition: attachment; filename="' .
    basename($path) .
    '"'
);
header('Content-Length: ' . filesize($path));
header('Cache-Control: no-cache');
header('Pragma: public');

readfile($path);
exit;

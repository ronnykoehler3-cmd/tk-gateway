<?php

header('Content-Type: application/json');

$targetDir = "/opt/tkgateway/uploads/";

if(!isset($_FILES['config']))
{
    echo json_encode([
        'success' => false,
        'message' => 'Keine Datei hochgeladen'
    ]);
    exit;
}

$fileName = basename($_FILES["config"]["name"]);

$targetFile = $targetDir . $fileName;

if(move_uploaded_file(
    $_FILES["config"]["tmp_name"],
    $targetFile
))
{
    echo json_encode([
        'success' => true,
        'file' => $fileName
    ]);
}
else
{
    echo json_encode([
        'success' => false,
        'message' => 'Upload fehlgeschlagen'
    ]);
}

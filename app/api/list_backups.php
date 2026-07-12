<?php

header('Content-Type: application/json');

$backupDir='/opt/tkgateway/backups';

$files=[];

foreach(glob($backupDir.'/*.tar.gz') as $file)
{
    $files[]=[
        'name'=>basename($file),
        'size'=>round(filesize($file)/1024/1024,2),
        'date'=>date(
            'd.m.Y H:i:s',
            filemtime($file)
        )
    ];
}

usort($files,function($a,$b){
    return filemtime($backupDir.'/'.$b['name'])
         - filemtime($backupDir.'/'.$a['name']);
});

echo json_encode($files,JSON_PRETTY_PRINT);

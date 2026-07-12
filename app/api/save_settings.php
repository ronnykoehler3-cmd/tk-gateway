<?php

require_once
'/opt/tkgateway/backend/classes/EventLogger.php';

header('Content-Type: application/json');

$data=json_decode(
    file_get_contents(
        'php://input'
    ),
    true
);

$db=new SQLite3(
    '/opt/tkgateway/database/gateway.db'
);

foreach($data as $key=>$value)
{
    $stmt=$db->prepare(
    "
    UPDATE settings
    SET value=:value
    WHERE key=:key
    "
    );

    $stmt->bindValue(
        ':key',
        $key
    );

    $stmt->bindValue(
        ':value',
        $value
    );

    $stmt->execute();
}

EventLogger::log(
    'INFO',
    'VPN Einstellungen geändert'
);

echo json_encode([
    'success'=>true
]);

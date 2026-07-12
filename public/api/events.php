<?php

header('Content-Type: application/json');

$db = new SQLite3(
    '/opt/tkgateway/database/gateway.db'
);

$result = $db->query(
'
SELECT
    id,
    timestamp,
    level,
    message
FROM events
ORDER BY id DESC
LIMIT 50
'
);

$data=[];

while(
    $row =
    $result->fetchArray(
        SQLITE3_ASSOC
    )
)
{
    $data[]=$row;
}

echo json_encode(
    $data,
    JSON_PRETTY_PRINT
);

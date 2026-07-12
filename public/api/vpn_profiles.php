<?php

header('Content-Type: application/json');

$db = new SQLite3(
    '/opt/tkgateway/database/gateway.db'
);

$result = $db->query(
'
SELECT *
FROM vpn_profiles
ORDER BY priority ASC
'
);

$data=[];

while($row=$result->fetchArray(SQLITE3_ASSOC))
{
    $data[]=$row;
}

echo json_encode(
    $data,
    JSON_PRETTY_PRINT
);

<?php

$db = new SQLite3('/opt/tkgateway/database/gateway.db');

$result = $db->query('SELECT key,value FROM vpn_stats');

$data = [];

while($row = $result->fetchArray(SQLITE3_ASSOC))
{
    $data[$row['key']] = $row['value'];
}

echo json_encode($data, JSON_PRETTY_PRINT);

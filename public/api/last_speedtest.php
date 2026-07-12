<?php

header('Content-Type: application/json');

$db = new SQLite3('/opt/tkgateway/database/gateway.db');

$result = $db->query("
SELECT *
FROM speedtest_results
ORDER BY id DESC
LIMIT 1
");

$row = $result->fetchArray(SQLITE3_ASSOC);

echo json_encode(
    $row ?: [],
    JSON_PRETTY_PRINT
);

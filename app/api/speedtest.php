<?php

require_once
'/opt/tkgateway/backend/classes/EventLogger.php';

header('Content-Type: application/json');
header(
    'Cache-Control: no-store, no-cache, must-revalidate, max-age=0'
);

EventLogger::log(
    'INFO',
    'Speedtest gestartet'
);

$output = shell_exec(
    'speedtest --accept-license --accept-gdpr --format=json 2>/dev/null'
);

if(empty($output))
{
    EventLogger::log(
        'ERROR',
        'Speedtest fehlgeschlagen'
    );

    echo json_encode([
        'success' => false
    ]);

    exit;
}

$data = json_decode(
    $output,
    true
);

$result = [
    'download'   =>
        round(
            ($data['download']['bandwidth'] * 8)
            / 1000000,
            2
        ),

    'upload'     =>
        round(
            ($data['upload']['bandwidth'] * 8)
            / 1000000,
            2
        ),

    'latency'    =>
        round(
            $data['ping']['latency'],
            2
        ),

    'packetloss' =>
        $data['packetLoss'] ?? 0,

    'server'     =>
        $data['server']['name']
        ?? 'Unbekannt',

    'isp'        =>
        $data['isp']
        ?? 'Unbekannt'
];

$db = new SQLite3(
    '/opt/tkgateway/database/gateway.db'
);

$stmt = $db->prepare("
INSERT INTO speedtest_results
(
    download,
    upload,
    latency,
    packetloss,
    server,
    isp
)
VALUES
(
    :download,
    :upload,
    :latency,
    :packetloss,
    :server,
    :isp
)
");

$stmt->bindValue(
    ':download',
    $result['download']
);

$stmt->bindValue(
    ':upload',
    $result['upload']
);

$stmt->bindValue(
    ':latency',
    $result['latency']
);

$stmt->bindValue(
    ':packetloss',
    $result['packetloss']
);

$stmt->bindValue(
    ':server',
    $result['server']
);

$stmt->bindValue(
    ':isp',
    $result['isp']
);

$stmt->execute();

EventLogger::log(
    'INFO',
    'Speedtest abgeschlossen: '
    . $result['download']
    . ' Mbit Down / '
    . $result['upload']
    . ' Mbit Up / '
    . $result['latency']
    . ' ms'
);

echo json_encode(
[
    'success' => true
] + $result,
JSON_PRETTY_PRINT
);

<?php

require_once __DIR__ . '/../../app/classes/Clients.php';
require_once __DIR__ . '/../../app/classes/DeviceRegistry.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) {
            throw new InvalidArgumentException('Ungültige JSON-Daten.');
        }

        $device = DeviceRegistry::saveDevice($input);
        echo json_encode([
            'ok' => true,
            'message' => 'Gerät gespeichert und DHCP-Konfiguration aktualisiert.',
            'device' => $device,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    $clients = Clients::getClients();
    $online = 0;
    foreach ($clients as $client) {
        if (!empty($client['online'])) {
            $online++;
        }
    }

    echo json_encode([
        'count' => count($clients),
        'online' => $online,
        'clients' => $clients,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage(),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

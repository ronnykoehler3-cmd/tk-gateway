<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');
try {
    // In the repository the endpoint is public/api/syslog.php; on the
    // Raspberry it is copied to the webroot as api/syslog.php. Support both.
    $classFiles = [
        __DIR__ . '/../app/classes/SyslogDiagnose.php',
        __DIR__ . '/../../app/classes/SyslogDiagnose.php',
    ];
    $classFile = null;
    foreach ($classFiles as $candidate) {
        if (is_file($candidate)) { $classFile = $candidate; break; }
    }
    if ($classFile === null) throw new RuntimeException('Syslog-Diagnoseklasse fehlt.');
    require_once $classFile;

    $period = max(0, min(168, (int)($_GET['hours'] ?? 24)));
    $filters = [
        'search' => substr(trim((string)($_GET['q'] ?? '')), 0, 480),
        'source' => in_array($_GET['source'] ?? 'all', ['all', 'fritzbox', 'vpn', 'wan'], true) ? (string)($_GET['source'] ?? 'all') : 'all',
        'severity' => in_array($_GET['severity'] ?? 'all', ['all', 'error', 'warning', 'info'], true) ? (string)($_GET['severity'] ?? 'all') : 'all',
        'since' => $period === 0 ? 0 : time() - ($period * 3600),
    ];
    $expert = ($_GET['expert'] ?? '') === '1';
    $result = SyslogDiagnose::read($filters, $expert);
    if (($_GET['export'] ?? '') === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="tk-gateway-syslog-' . gmdate('Ymd-His') . '.csv"');
        $out = fopen('php://output', 'wb');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Zeit (UTC)', 'Quelle', 'Absender-IP', 'Gerät', 'Modell', 'MAC-Adresse', 'Schweregrad', 'Kategorie', 'Erklärung', 'Lösungsvorschlag', 'Meldung'], ';', '"', '');
        foreach ($result['entries'] as $entry) {
            $row = [$entry['timestamp'], $entry['sourceLabel'], $entry['senderIp'], $entry['device'], $entry['model'], $entry['mac'], $entry['severity'], $entry['category'], $entry['title'], $entry['advice'], $entry['message']];
            $row = array_map(static function ($value) { $value = (string)($value ?? ''); return preg_match('/^[\s]*[=+@-]/', $value) ? "'" . $value : $value; }, $row);
            fputcsv($out, $row, ';', '"', '');
        }
        fclose($out); exit;
    }
    echo json_encode(['ok' => true] + $result, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Das Protokoll konnte nicht gelesen werden. Bitte Zugriffsrechte und Logdateien prüfen.'], JSON_UNESCAPED_UNICODE);
}

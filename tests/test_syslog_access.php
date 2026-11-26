<?php
declare(strict_types=1);

function expect(bool $condition, string $message): void
{
    if (!$condition) { fwrite(STDERR, "FEHLER: $message\n"); exit(1); }
}

$api = realpath(__DIR__ . '/../public/api/syslog.php');
expect($api !== false, 'Syslog-API muss vorhanden sein');
$program = 'register_shutdown_function(static function () { fwrite(STDERR, "HTTP=" . http_response_code()); }); '
    . '$_GET = []; $_SERVER["REQUEST_METHOD"] = "GET"; require ' . var_export($api, true) . ';';
$pipes = [];
$process = proc_open([PHP_BINARY, '-r', $program], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
expect(is_resource($process), 'Unangemeldeter API-Aufruf muss startbar sein');
fclose($pipes[0]);
$body = stream_get_contents($pipes[1]);
$stderr = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
$exit = proc_close($process);
expect($exit === 0, 'API-Prüfprozess muss regulär enden');
expect(str_contains((string)$stderr, 'HTTP=401'), 'Syslog-API muss unangemeldete Zugriffe mit HTTP 401 abweisen');
$response = json_decode((string)$body, true);
expect(is_array($response) && ($response['ok'] ?? true) === false, 'API darf ohne Anmeldung keine Protokolldaten ausgeben');
expect(($response['error'] ?? '') === 'Bitte zuerst anmelden.', 'API muss eine verständliche Anmeldeantwort liefern');
echo "OK: Syslog-API verweigert unangemeldeten Zugriff mit HTTP 401 und ohne Logdaten.\n";

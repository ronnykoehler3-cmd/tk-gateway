<?php
declare(strict_types=1);

function expect(bool $condition, string $message): void
{
    if (!$condition) { fwrite(STDERR, "FEHLER: $message\n"); exit(1); }
}

$sourceApi = realpath(__DIR__ . '/../public/api/syslog.php');
$sourceClass = realpath(__DIR__ . '/../app/classes/SyslogDiagnose.php');
expect($sourceApi !== false && $sourceClass !== false, 'Syslog-API und Diagnoseklasse müssen vorhanden sein');
$fixture = sys_get_temp_dir() . '/tk-gateway-api-' . bin2hex(random_bytes(6));
mkdir($fixture . '/api', 0700, true);
mkdir($fixture . '/app/classes', 0700, true);
copy($sourceApi, $fixture . '/api/syslog.php');
copy($sourceClass, $fixture . '/app/classes/SyslogDiagnose.php');
$api = $fixture . '/api/syslog.php';
$program = '$_GET = []; $_SERVER["REQUEST_METHOD"] = "GET"; require ' . var_export($api, true) . ';';
$pipes = [];
$process = proc_open([PHP_BINARY, '-r', $program], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
expect(is_resource($process), 'Unangemeldeter API-Aufruf muss startbar sein');
fclose($pipes[0]);
$body = stream_get_contents($pipes[1]);
stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
$exit = proc_close($process);
expect($exit === 0, 'API-Prüfprozess muss regulär enden');
$response = json_decode((string)$body, true);
foreach ([$fixture . '/api/syslog.php', $fixture . '/app/classes/SyslogDiagnose.php', $fixture . '/app/classes', $fixture . '/app', $fixture . '/api', $fixture] as $item) {
    if (is_dir($item)) rmdir($item); else if (is_file($item)) unlink($item);
}
expect(is_array($response) && ($response['ok'] ?? false) === true, 'Installierte Syslog-API muss ohne Passwortanmeldung antworten');
echo "OK: Installierte Syslog-API findet ihre Klasse und ist ohne Passwort erreichbar.\n";

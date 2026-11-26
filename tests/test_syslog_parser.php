<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/classes/SyslogDiagnose.php';

function check(bool $ok, string $message): void
{
    if (!$ok) { fwrite(STDERR, "FEHLER: $message\n"); exit(1); }
}

$normalChallenge = SyslogDiagnose::parse('Nov 26 22:34:05 HT841 [ec:74:d7:d8:e2:16] SIP/2.0 401 Unauthorized', 'fritzbox');
check($normalChallenge['severity'] === 'info', 'SIP 401 ohne fehlgeschlagene Registrierung muss normal sein');
check($normalChallenge['model'] === 'HT841', 'HT841-Modell muss erkannt werden');
check($normalChallenge['mac'] === 'EC:74:D7:D8:E2:16', 'MAC-Adresse muss erkannt werden');
$proxyChallenge = SyslogDiagnose::parse('Nov 26 22:34:05 HT841 SIP/2.0 407 Proxy Authentication Required', 'fritzbox');
check($proxyChallenge['severity'] === 'info', 'SIP 407 ohne fehlgeschlagene Registrierung muss normal sein');

$ok = SyslogDiagnose::parse('Nov 26 22:34:06 192.168.50.200 SIP/2.0 200 OK', 'fritzbox');
check($ok['severity'] === 'info' && str_contains($ok['advice'], 'beweist nicht'), 'SIP 200 OK darf nicht als Registrierungsbeweis gelten');
check($ok['senderIp'] === '192.168.50.200', 'IP im Syslog-Hostname wird als Absender-IP erkannt');

$bad = SyslogDiagnose::parse('Nov 26 22:34:07 HT841 REGISTER failed 403 Authorization: Digest username="Nebenstelle" response=deadbeef', 'fritzbox');
check($bad['severity'] === 'error', 'SIP-Registrierungsfehler muss Fehlerstatus erhalten');
$masked = SyslogDiagnose::mask($bad['message']);
check(!str_contains($masked, 'Nebenstelle') && !str_contains($masked, 'deadbeef'), 'Authentifizierungsdaten müssen maskiert werden');

$timeout = SyslogDiagnose::parse('Nov 26 22:34:08 gateway DNS lookup timeout for pbx.example.invalid', 'vpn');
check($timeout['category'] === 'DNS / Namensauflösung' && $timeout['severity'] === 'error', 'DNS-Zeitüberschreitung muss erkannt werden');

$unknown = SyslogDiagnose::parse('Nov 26 22:34:09 HT841 private vendor event 123', 'fritzbox');
check($unknown['unknown'] === true, 'Unbekannte Meldung muss als unbekannt gekennzeichnet sein');

echo "OK: Parser-Regeln für 401/407, 200 OK, Registrierung, Geheimnis-Maskierung, DNS und unbekannte Meldungen.\n";

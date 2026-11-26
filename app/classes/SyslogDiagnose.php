<?php
declare(strict_types=1);

/** Read-only parser for gateway and device log files. */
final class SyslogDiagnose
{
    public const SOURCES = [
        'fritzbox' => ['label' => 'FRITZ!Box / Telefonie', 'path' => '/var/log/tkgateway/fritzbox.log'],
        'vpn' => ['label' => 'VPN / Watchdog', 'path' => '/var/log/tkgateway/vpn-watchdog.log'],
        'wan' => ['label' => 'WAN / Triple-WAN', 'path' => '/var/log/tkgateway/wan-failover.log'],
    ];

    public static function read(array $filters = [], bool $expert = false): array
    {
        $items = [];
        foreach (self::SOURCES as $source => $meta) {
            $path = $meta['path'];
            if (!is_readable($path) || !is_file($path)) {
                continue;
            }
            $lines = self::tailLines($path, 2_000);
            foreach ($lines as $line) {
                $item = self::parse($line, $source, $meta['label']);
                if (self::matches($item, $filters)) {
                    // Mask secrets in both lay and expert views, and in exports.
                    $item['message'] = self::mask($item['message']);
                    $items[] = $item;
                }
            }
        }
        foreach (self::readJournal() as $line) {
            $source = str_contains($line, 'tkgateway-wan-failover') ? 'wan' : 'vpn';
            if (!empty($filters['source']) && $filters['source'] !== 'all' && $filters['source'] !== $source) continue;
            $item = self::parse($line, $source, self::SOURCES[$source]['label']);
            if (self::matches($item, $filters)) {
                $item['message'] = self::mask($item['message']);
                $items[] = $item;
            }
        }

        usort($items, static fn(array $a, array $b): int => strcmp($b['timestamp'], $a['timestamp']));
        $items = array_slice($items, 0, 500);
        return ['entries' => $items, 'summary' => self::summarize($items), 'updatedAt' => gmdate('c')];
    }

    public static function parse(string $line, string $source = 'fritzbox', string $sourceLabel = 'FRITZ!Box / Telefonie'): array
    {
        $line = trim($line);
        $timestamp = self::timestamp($line);
        $hostname = null;
        $senderIp = null;
        $mac = null;
        $model = null;

        // RFC3164 header: "Nov 26 22:34:05 host message". This host is
        // the syslog sender; it may be a relay and is not assumed to be the device.
        if (preg_match('/^(?:<\d+>)?(?:[A-Z][a-z]{2}\s+\d{1,2}\s+\d{2}:\d{2}:\d{2}\s+)(\S+)\s+(.*)$/', $line, $m)) {
            $hostname = $m[1];
            $line = $m[2];
        } elseif (preg_match('/^(20\d{2}-\d{2}-\d{2}T\S+)\s+(\S+)\s+(tkgateway-[^:]+|TK-VPN-WATCHDOG(?:\[\d+\])?):\s*(.*)$/', $line, $m)) {
            $hostname = $m[2];
            $line = $m[4];
        } elseif (preg_match('/^<\d+>(\S+)\s+(.*)$/', $line, $m)) {
            $hostname = $m[1];
            $line = $m[2];
        }

        if (preg_match('/\b(?:fromhost-ip[=: ]+)?((?:\d{1,3}\.){3}\d{1,3})\b/', $hostname ?? '', $m)) {
            $senderIp = $m[1];
        }
        if (preg_match('/\b([0-9a-f]{2}(?::[0-9a-f]{2}){5})\b/i', $line, $m)) {
            $mac = strtoupper($m[1]);
        }
        if (preg_match('/\b(HT\s?-?841|HT\s?-?801|GRP\d{3,4}|GXP\d{3,4}|WP\d{3,4}|Fanvil\s+X\d+\w*|Yealink\s+T\d+\w*|FRITZ!?Box(?:\s+\d{3,4})?)\b/i', ($hostname ?? '') . ' ' . $line, $m)) {
            $model = preg_replace('/\s+/', ' ', strtoupper(str_replace('!', '', $m[1]))) ?: $m[1];
        }

        $device = $model ?: ($hostname ?: ($mac ? 'Gerät ' . $mac : 'Unbekanntes Gerät'));
        $severity = 'info';
        $category = 'Sonstige Meldung';
        $title = 'Meldung ohne bekannte Fehlerregel';
        $advice = 'Diese Meldung wurde noch keiner bekannten Regel zugeordnet. Prüfen Sie Zeitpunkt und Gerät; bei Wiederholung bitte den Expertenmodus nutzen.';
        $text = $line;
        $lower = strtolower($text);

        if (preg_match('/\b(?:authorization|proxy-authorization)\s*:/i', $text) || preg_match('/\b(?:secret|password|passwd|passwort|auth(?:entication)?_?key)\s*[=:]/i', $text)) {
            $category = 'Authentifizierung';
            $title = 'Anmeldeinformationen in Meldung erkannt';
            $advice = 'Die Zugangsdaten werden in der Anzeige verdeckt. Prüfen Sie, ob ein Gerät oder Dienst Zugangsdaten protokolliert.';
            $severity = 'warning';
        }

        if (preg_match('/\b(?:sip\s*)?(401|407)\b/', $text, $m)) {
            $category = 'SIP-Anmeldung';
            $title = 'SIP-Anmeldeabfrage (401/407)';
            $advice = 'Diese Antwort ist häufig eine normale Sicherheitsabfrage. Erst ein anschließender fehlgeschlagener REGISTER-Vorgang weist auf ein Anmeldeproblem hin.';
            $severity = preg_match('/register.*(?:failed|fail|rejected|timeout)|(?:failed|fail|rejected).*register/i', $text) ? 'error' : 'info';
            if ($severity === 'error') {
                $title = 'SIP-Registrierung nach Anmeldeabfrage fehlgeschlagen';
                $advice = 'Benutzername, Kennwort und die zugewiesene Nebenstelle im Telefon sowie in der Telefonanlage vergleichen. Zugangsdaten niemals im Export weitergeben.';
            }
        } elseif (preg_match('/\b200\s+OK\b/i', $text)) {
            $category = 'SIP-Antwort';
            $title = 'SIP-Antwort 200 OK empfangen';
            $advice = '200 OK bestätigt nur diese einzelne SIP-Anfrage. Es beweist nicht, dass das Telefon dauerhaft registriert oder ein Gespräch vollständig aufgebaut ist.';
            $severity = 'info';
        } elseif (preg_match('/\b(?:REGISTER|registration|registrierung)\b/i', $text) && preg_match('/\b(?:reject(?:ed)?|fail(?:ed|ure)?|timeout|unavailable|expired)\b/i', $text)) {
            $category = 'SIP-Registrierung';
            $title = 'Telefon konnte sich nicht registrieren';
            $advice = 'Prüfen Sie Nebenstelle, Kennwort, SIP-Server, Transport (UDP/TCP/TLS), Uhrzeit und Netzwerkweg.';
            $severity = 'error';
        } elseif (preg_match('/\bSIP\s*(403|404|408|480|486|500|502|503|504)\b|\b(?:response|status|code)[ =:]*(403|404|408|480|486|500|502|503|504)\b/i', $text, $m)) {
            $code = $m[1] ?: ($m[2] ?? '');
            [$title, $advice] = self::sipAdvice($code);
            $category = 'SIP-Gespräch';
            $severity = in_array($code, ['408', '500', '502', '503', '504'], true) ? 'error' : 'warning';
        } elseif (preg_match('/\b(?:DNS|resolve|resolver|name lookup|SERVFAIL|NXDOMAIN)\b/i', $text)) {
            $category = 'DNS / Namensauflösung';
            $title = 'Ein Servername konnte nicht aufgelöst werden';
            $advice = 'DNS-Server und Internetverbindung prüfen. Testen Sie die Auflösung des betroffenen Hostnamens und kontrollieren Sie, ob die DNS-Adresse erreichbar ist.';
            $severity = preg_match('/(?:fail|error|servfail|nxdomain|timeout)/i', $text) ? 'error' : 'warning';
        } elseif (preg_match('/\b(?:VPN|xray|vless|tun)\b/i', $text)) {
            $category = 'VPN';
            $title = preg_match('/\b(?:down|fail|error|disconnected|timeout|offline)\b/i', $text) ? 'VPN-Verbindung meldet ein Problem' : 'VPN-Statusmeldung';
            $advice = 'Prüfen Sie im Dashboard den VPN-Status und den letzten Watchdog-Eintrag. Ein einzelner Verbindungstest bestätigt noch nicht den gesamten VPN-Datenweg.';
            $severity = preg_match('/\b(?:down|fail|error|disconnected|timeout|offline)\b/i', $text) ? 'error' : 'info';
        } elseif (preg_match('/\b(?:WAN|triplewan|uplink|gateway|route)\b/i', $text)) {
            $category = 'WAN / Netzwerk';
            $title = 'Meldung zur Internetleitung oder Route';
            $advice = 'Prüfen Sie, ob Hauptleitung und Ersatzleitung online sind und welche Leitung aktiv genutzt wird. Die Statusanzeige im Dashboard zeigt den aktuellen Pfad.';
            $severity = preg_match('/\b(?:down|fail|error|offline|unreachable|timeout)\b/i', $text) ? 'error' : 'info';
        } elseif (preg_match('/\b(?:timeout|timed out|zeitüberschreitung|no response|unreachable)\b/i', $text)) {
            $category = 'Zeitüberschreitung / Netzwerk';
            $title = 'Ein Gerät oder Dienst antwortet nicht rechtzeitig';
            $advice = 'Verbindung, Zieladresse und Erreichbarkeit prüfen. Wiederholt sich der Fehler, Zeitpunkt mit den VPN- und WAN-Protokollen vergleichen.';
            $severity = 'error';
        } elseif (preg_match('/\b(?:error|failed|failure|fehler|kritisch|critical|emerg|alert)\b/i', $text)) {
            $category = 'Systemfehler';
            $title = 'Das System meldet einen Fehler';
            $advice = 'Prüfen Sie den vollständigen Eintrag im Expertenmodus und die passenden Statusseiten. Unbekannte Fehler nicht durch wiederholte Neustarts beheben.';
            $severity = 'error';
        } elseif (preg_match('/\b(?:warning|warn|notice|degraded)\b/i', $text)) {
            $category = 'Warnung';
            $title = 'Das System meldet eine Warnung';
            $advice = 'Beobachten Sie, ob die Meldung wiederkehrt oder ein Dienst dadurch ausfällt.';
            $severity = 'warning';
        }

        if ($source !== 'fritzbox' && $category === 'Sonstige Meldung') {
            $category = $source === 'vpn' ? 'VPN / Watchdog' : 'WAN / Triple-WAN';
            $title = 'Statusmeldung des Gateway-Dienstes';
            $advice = 'Lesen Sie die Meldung zusammen mit dem aktuellen Gateway-Status. Erfolgreiche Prüfläufe sind nicht automatisch Fehler.';
            $severity = preg_match('/\b(?:fail|error|down|offline|timeout|fehler)\b/i', $text) ? 'error' : 'info';
        }

        $id = hash('sha256', $source . "\0" . $line);
        return [
            'id' => $id,
            'timestamp' => $timestamp,
            'source' => $source,
            'sourceLabel' => $sourceLabel,
            'senderIp' => $senderIp,
            'syslogHost' => $hostname,
            'device' => $device,
            'model' => $model,
            'mac' => $mac,
            'category' => $category,
            'title' => $title,
            'advice' => $advice,
            'severity' => $severity,
            'message' => $text,
            'unknown' => $category === 'Sonstige Meldung',
            'fingerprint' => self::fingerprint($text),
        ];
    }

    public static function mask(string $text): string
    {
        $text = preg_replace('/(authorization|proxy-authorization|www-authenticate)\s*:\s*[^\r\n]*/i', '$1: [Authentifizierungsdaten verborgen]', $text) ?? $text;
        $text = preg_replace('/\b(password|passwd|passwort|secret|token|auth(?:entication)?_?key)\s*([=:])\s*([^\s,;]+)/i', '$1$2[verborgen]', $text) ?? $text;
        $text = preg_replace('/\b(username|user|authuser)\s*([=:])\s*([^\s,;]+)/i', '$1$2[verborgen]', $text) ?? $text;
        return $text;
    }

    private static function timestamp(string $line): string
    {
        if (preg_match('/\b(20\d{2}-\d{2}-\d{2}[T ][0-9:.+-]+(?:Z|\s*[A-Z]{2,5})?)\b/', $line, $m)) {
            $value = strtotime($m[1]);
            if ($value !== false) return gmdate('c', $value);
        }
        if (preg_match('/^([A-Z][a-z]{2}\s+\d{1,2}\s+\d{2}:\d{2}:\d{2})\s+/', $line, $m)) {
            $value = strtotime($m[1] . ' ' . date('Y'));
            if ($value !== false) return gmdate('c', $value);
        }
        return gmdate('c');
    }

    private static function tailLines(string $path, int $limit): array
    {
        $handle = @fopen($path, 'rb');
        if (!$handle) return [];
        $size = @filesize($path) ?: 0;
        if ($size > 4_194_304) fseek($handle, -4_194_304, SEEK_END);
        $lines = [];
        while (($line = fgets($handle)) !== false) {
            $line = trim($line);
            if ($line !== '') $lines[] = $line;
            if (count($lines) > $limit) array_shift($lines);
        }
        fclose($handle);
        return $lines;
    }

    private static function readJournal(): array
    {
        if (!function_exists('proc_open') || !is_executable('/usr/bin/sudo')) return [];
        $pipes = [];
        $process = @proc_open(['/usr/bin/sudo', '-n', '/usr/local/sbin/tkgateway-syslog-journal'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, ['PATH' => '/usr/bin:/bin']);
        if (!is_resource($process)) return [];
        fclose($pipes[0]);
        stream_set_timeout($pipes[1], 2);
        $output = stream_get_contents($pipes[1], 524288);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);
        return array_values(array_filter(array_map('trim', explode("\n", (string)$output)), static fn(string $line): bool => $line !== ''));
    }

    private static function matches(array $item, array $filters): bool
    {
        $search = trim((string)($filters['search'] ?? ''));
        if ($search !== '' && stripos(implode(' ', [$item['device'], $item['model'] ?? '', $item['mac'] ?? '', $item['category'], $item['title'], $item['message']]), $search) === false) return false;
        if (!empty($filters['source']) && $filters['source'] !== 'all' && $item['source'] !== $filters['source']) return false;
        if (!empty($filters['severity']) && $filters['severity'] !== 'all' && $item['severity'] !== $filters['severity']) return false;
        $since = (int)($filters['since'] ?? 0);
        if ($since > 0 && strtotime($item['timestamp']) < $since) return false;
        return true;
    }

    private static function summarize(array $items): array
    {
        $devices = [];
        $fingerprints = [];
        $counts = ['error' => 0, 'warning' => 0, 'info' => 0];
        foreach ($items as $item) {
            $key = $item['mac'] ?: $item['device'];
            $devices[$key] ??= ['device' => $item['device'], 'model' => $item['model'], 'mac' => $item['mac'], 'lastMessage' => $item['title'], 'lastSeen' => $item['timestamp'], 'errors' => 0, 'warnings' => 0, 'status' => 'ok'];
            if ($item['severity'] === 'error') { $devices[$key]['errors']++; $counts['error']++; }
            elseif ($item['severity'] === 'warning') { $devices[$key]['warnings']++; $counts['warning']++; }
            else $counts['info']++;
            if ($item['severity'] === 'error') $devices[$key]['status'] = 'error';
            elseif ($item['severity'] === 'warning' && $devices[$key]['status'] === 'ok') $devices[$key]['status'] = 'warning';
            $fingerprints[$item['fingerprint']] ??= ['message' => $item['title'], 'device' => $item['device'], 'severity' => $item['severity'], 'count' => 0, 'lastSeen' => $item['timestamp']];
            $fingerprints[$item['fingerprint']]['count']++;
        }
        usort($fingerprints, static fn(array $a, array $b): int => $b['count'] <=> $a['count']);
        return ['devices' => array_values($devices), 'repeated' => array_values(array_filter($fingerprints, static fn(array $v): bool => $v['count'] > 1)), 'counts' => $counts, 'total' => count($items)];
    }

    private static function fingerprint(string $text): string
    {
        $normalized = strtolower($text);
        $normalized = preg_replace('/\b(?:\d{1,3}\.){3}\d{1,3}\b/', '<ip>', $normalized) ?? $normalized;
        $normalized = preg_replace('/\b[0-9a-f]{2}(?::[0-9a-f]{2}){5}\b/i', '<mac>', $normalized) ?? $normalized;
        $normalized = preg_replace('/\b\d{2,}\b/', '<n>', $normalized) ?? $normalized;
        return hash('sha256', preg_replace('/\s+/', ' ', trim($normalized)) ?? $normalized);
    }

    private static function sipAdvice(string $code): array
    {
        return match ($code) {
            '403' => ['SIP 403: Anfrage nicht erlaubt', 'Der Telefonanbieter oder die Telefonanlage lehnt die Anfrage ab. Rufnummernformat, Berechtigung und Absendernummer prüfen.'],
            '404' => ['SIP 404: Ziel oder Nebenstelle unbekannt', 'Zielrufnummer und Nebenstelle prüfen. Bei externen Anrufen auch das vom Anbieter erwartete Rufnummernformat vergleichen.'],
            '408' => ['SIP 408: Keine rechtzeitige Antwort', 'Netzwerk, Firewall, NAT und Erreichbarkeit des SIP-Servers prüfen.'],
            '480' => ['SIP 480: Ziel momentan nicht erreichbar', 'Prüfen, ob das Zielgerät eingeschaltet und registriert ist und ob eine Rufumleitung aktiv ist.'],
            '486' => ['SIP 486: Ziel besetzt', 'Das Ziel meldet besetzt. Warteschleife, Anklopfen oder Rufumleitung prüfen.'],
            '500' => ['SIP 500: Fehler auf der Gegenseite', 'Zeitpunkt notieren und Telefonanlage oder Provider prüfen. Bei einzelnen Anrufen erneut testen.'],
            '502' => ['SIP 502: Gateway-Fehler', 'SIP-Trunk, Gegenstelle und Netzwerkverbindung zwischen den beteiligten Gateways prüfen.'],
            '503' => ['SIP 503: Dienst vorübergehend nicht verfügbar', 'Providerstatus und Trunk-Registrierung prüfen. Nach kurzer Zeit erneut testen.'],
            '504' => ['SIP 504: Gateway antwortet nicht rechtzeitig', 'Erreichbarkeit und Antwortzeit des nächsten SIP-Gateways sowie NAT und Firewall prüfen.'],
            default => ['SIP-Fehler', 'SIP-Antwortcode und vollständigen Gesprächsablauf im Expertenmodus prüfen.'],
        };
    }
}

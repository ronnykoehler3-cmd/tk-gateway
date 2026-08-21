#!/bin/bash
set -euo pipefail

REPO="${1:-/opt/tk-gateway}"
WEB_USER="${TK_GATEWAY_WEB_USER:-www-data}"
STATE_DIR="/var/lib/tk-gateway"
HELPER_SRC="$REPO/scripts/apply_device_reservations.sh"
HELPER_DST="/usr/local/sbin/tk-gateway-apply-reservations"
SUDOERS="/etc/sudoers.d/tk-gateway-device-reservations"
LIVE_WEBROOT="${TK_GATEWAY_WEBROOT:-/var/www/tkgateway}"
LEGACY_BACKEND="${TK_GATEWAY_BACKEND:-/opt/tkgateway/backend}"

if [[ ! -f "$HELPER_SRC" ]]; then
    echo "FEHLER: $HELPER_SRC fehlt." >&2
    exit 1
fi

install -d -o "$WEB_USER" -g "$WEB_USER" -m 0775 "$STATE_DIR"
if [[ ! -f "$STATE_DIR/devices.json" ]]; then
    printf '{}\n' > "$STATE_DIR/devices.json"
fi
chown "$WEB_USER:$WEB_USER" "$STATE_DIR/devices.json"
chmod 0664 "$STATE_DIR/devices.json"

install -o root -g root -m 0755 "$HELPER_SRC" "$HELPER_DST"

cat > "$SUDOERS" <<EOF
$WEB_USER ALL=(root) NOPASSWD: $HELPER_DST
EOF
chmod 0440 "$SUDOERS"
visudo -cf "$SUDOERS" >/dev/null

# Aktiven Webroot mit der Repository-Version synchronisieren.
if [[ -d "$LIVE_WEBROOT" ]]; then
    install -d -o root -g root -m 0755 "$LIVE_WEBROOT/app/classes" "$LIVE_WEBROOT/app/api" "$LIVE_WEBROOT/api" "$LIVE_WEBROOT/assets/js"
    install -o root -g root -m 0644 "$REPO/app/classes/Clients.php" "$LIVE_WEBROOT/app/classes/Clients.php"
    install -o root -g root -m 0644 "$REPO/app/classes/DeviceRegistry.php" "$LIVE_WEBROOT/app/classes/DeviceRegistry.php"
    install -o root -g root -m 0644 "$REPO/app/api/clients.php" "$LIVE_WEBROOT/app/api/clients.php"
    rm -f "$LIVE_WEBROOT/api/clients.php"
    install -o root -g root -m 0644 "$REPO/public/api/clients.php" "$LIVE_WEBROOT/api/clients.php"
    install -o root -g root -m 0644 "$REPO/public/clients.php" "$LIVE_WEBROOT/clients.php"
    install -o root -g root -m 0644 "$REPO/public/assets/js/clients.js" "$LIVE_WEBROOT/assets/js/clients.js"
fi

# Die bestehende Produktivinstallation nutzt zusätzlich /opt/tkgateway/backend.
# Auch dort dieselben Klassen und die funktionierende API installieren, damit
# alte interne Verknüpfungen nicht wieder auf einen veralteten Stand fallen.
if [[ -d "$LEGACY_BACKEND" ]]; then
    install -d -o root -g root -m 0755 "$LEGACY_BACKEND/classes" "$LEGACY_BACKEND/api"
    install -o root -g root -m 0644 "$REPO/app/classes/Clients.php" "$LEGACY_BACKEND/classes/Clients.php"
    install -o root -g root -m 0644 "$REPO/app/classes/DeviceRegistry.php" "$LEGACY_BACKEND/classes/DeviceRegistry.php"

    cat > "$LEGACY_BACKEND/api/clients.php" <<'PHP'
<?php

require_once __DIR__ . '/../classes/Clients.php';
require_once __DIR__ . '/../classes/DeviceRegistry.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if ($method === 'POST') {
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
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
PHP
    chmod 0644 "$LEGACY_BACKEND/api/clients.php"
fi

"$HELPER_DST"

echo "Zentrale Geräteverwaltung installiert."
echo "Registry: $STATE_DIR/devices.json"
echo "dnsmasq:  /etc/dnsmasq.d/tk-gateway-reservations.conf"
[[ -d "$LIVE_WEBROOT" ]] && echo "Webroot:  $LIVE_WEBROOT"
[[ -d "$LEGACY_BACKEND" ]] && echo "Backend:  $LEGACY_BACKEND"

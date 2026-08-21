#!/bin/bash
set -euo pipefail

REPO="${1:-/opt/tk-gateway}"
WEB_USER="${TK_GATEWAY_WEB_USER:-www-data}"
STATE_DIR="/var/lib/tk-gateway"
HELPER_SRC="$REPO/scripts/apply_device_reservations.sh"
HELPER_DST="/usr/local/sbin/tk-gateway-apply-reservations"
SUDOERS="/etc/sudoers.d/tk-gateway-device-reservations"

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

"$HELPER_DST"

echo "Zentrale Geräteverwaltung installiert."
echo "Registry: $STATE_DIR/devices.json"
echo "dnsmasq:  /etc/dnsmasq.d/tk-gateway-reservations.conf"

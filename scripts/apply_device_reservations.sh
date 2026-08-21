#!/bin/bash
set -euo pipefail

REGISTRY="/var/lib/tk-gateway/devices.json"
TARGET="/etc/dnsmasq.d/tk-gateway-reservations.conf"
TMP="$(mktemp)"
trap 'rm -f "$TMP"' EXIT

if [[ ! -f "$REGISTRY" ]]; then
    printf '# TK Gateway - keine festen DHCP-Reservierungen\n' > "$TMP"
else
    python3 - "$REGISTRY" > "$TMP" <<'PY'
import json, ipaddress, re, sys

path = sys.argv[1]
with open(path, encoding='utf-8') as f:
    data = json.load(f)

print('# Automatisch erzeugt durch TK Gateway - nicht manuell bearbeiten')
seen_ips = set()
seen_macs = set()
for key, d in sorted(data.items()):
    if not isinstance(d, dict) or not d.get('fixed_ip'):
        continue
    mac = str(d.get('mac', key)).upper()
    ip = str(d.get('ip', '')).strip()
    if not re.fullmatch(r'(?:[0-9A-F]{2}:){5}[0-9A-F]{2}', mac):
        raise SystemExit(f'Ungueltige MAC: {mac}')
    try:
        ipaddress.IPv4Address(ip)
    except Exception:
        raise SystemExit(f'Ungueltige IPv4: {ip}')
    if mac in seen_macs:
        raise SystemExit(f'Doppelte MAC: {mac}')
    if ip in seen_ips:
        raise SystemExit(f'Doppelte feste IP: {ip}')
    seen_macs.add(mac)
    seen_ips.add(ip)
    hostname = str(d.get('hostname', '')).strip()
    hostname = re.sub(r'[^A-Za-z0-9_.-]', '-', hostname).strip('-')
    if hostname:
        print(f'dhcp-host={mac},{ip},{hostname},infinite')
    else:
        print(f'dhcp-host={mac},{ip},infinite')
PY
fi

install -o root -g root -m 0644 "$TMP" "$TARGET"

dnsmasq --test >/dev/null
if systemctl is-active --quiet dnsmasq; then
    systemctl reload dnsmasq || systemctl restart dnsmasq
fi

echo "DHCP-Reservierungen aktualisiert."

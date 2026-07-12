#!/bin/bash

FILE="$1"

if [ ! -f "$FILE" ]; then
    echo "Datei nicht gefunden"
    exit 1
fi

TMPDIR="/tmp/tkgateway-restore"

rm -rf "$TMPDIR"
mkdir -p "$TMPDIR"

tar xzf "$FILE" -C "$TMPDIR"

cp -a "$TMPDIR/etc/dnsmasq.d/"* /etc/dnsmasq.d/ 2>/dev/null
cp -a "$TMPDIR/etc/NetworkManager/"* /etc/NetworkManager/ 2>/dev/null
cp -a "$TMPDIR/etc/nginx/"* /etc/nginx/ 2>/dev/null
cp -a "$TMPDIR/etc/systemd/system/"* /etc/systemd/system/ 2>/dev/null
cp -a "$TMPDIR/etc/sudoers.d/"* /etc/sudoers.d/ 2>/dev/null

systemctl daemon-reload
systemctl restart nginx
systemctl restart dnsmasq
systemctl restart NetworkManager

echo "Konfiguration erfolgreich wiederhergestellt"

exit 0

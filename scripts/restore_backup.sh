#!/bin/bash

BACKUP="$1"

if [ ! -f "$BACKUP" ]
then
    echo "Backup nicht gefunden"
    exit 1
fi

TMP_DIR="/tmp/tkgateway-restore"

rm -rf "${TMP_DIR}"
mkdir -p "${TMP_DIR}"

tar xzf "${BACKUP}" -C "${TMP_DIR}"

cp "${TMP_DIR}/gateway.db" \
   /opt/tkgateway/database/

rm -rf /opt/tkgateway/vpn-profiles
cp -r "${TMP_DIR}/vpn-profiles" \
      /opt/tkgateway/

cp -r "${TMP_DIR}/sing-box" \
      /etc/

systemctl restart sing-box

echo "Restore abgeschlossen."

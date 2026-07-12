#!/bin/bash

DATE=$(date +%F-%H%M)

BACKUP_DIR="/opt/tkgateway/backups"
TMP_DIR="/tmp/tkgateway-backup-${DATE}"

mkdir -p "${TMP_DIR}"

echo "Sichere Datenbank..."
cp /opt/tkgateway/database/gateway.db \
   "${TMP_DIR}/gateway.db"

echo "Sichere VPN Profile..."
cp -r /opt/tkgateway/vpn-profiles \
      "${TMP_DIR}/"

echo "Sichere Konfiguration..."
cp -r /etc/sing-box \
      "${TMP_DIR}/"

echo "Erstelle Archiv..."

tar czf \
"${BACKUP_DIR}/tkgateway-backup-${DATE}.tar.gz" \
-C "${TMP_DIR}" .

rm -rf "${TMP_DIR}"

echo "Backup erstellt:"
echo "${BACKUP_DIR}/tkgateway-backup-${DATE}.tar.gz"

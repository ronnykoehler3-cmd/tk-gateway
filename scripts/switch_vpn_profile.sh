#!/bin/bash

PROFILE="$1"
PROFILE_DIR="/opt/tkgateway/vpn-profiles/${PROFILE}"

echo "Aktiviere Profil ${PROFILE}"

# XRay / Reality
if [ -f "${PROFILE_DIR}/config.json" ]; then

    cp "${PROFILE_DIR}/config.json" \
       /etc/sing-box/config.json

    systemctl restart sing-box

    sleep 5

    echo "Sing-Box Profil aktiviert"

    exit 0
fi

# AWG / WireGuard
CONF_FILE=$(find "${PROFILE_DIR}" -name "*.conf" | head -1)

if [ -n "${CONF_FILE}" ]; then

    echo "AWG Profil erkannt:"
    echo "${CONF_FILE}"

    mkdir -p /etc/amnezia

    cp "${CONF_FILE}" \
       /etc/amnezia/amneziawg.conf

    echo "AWG Import erfolgreich"

    exit 0
fi

echo "Kein unterstütztes Profil gefunden"

exit 1

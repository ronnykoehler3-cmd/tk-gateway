#!/bin/bash

PROFILE_NAME="$1"
SOURCE_FILE="$2"

TARGET_DIR="/opt/tkgateway/vpn-profiles/${PROFILE_NAME}"

mkdir -p "${TARGET_DIR}"

cp "${SOURCE_FILE}" \
   "${TARGET_DIR}/amnezia.conf"

echo "Profil importiert:"
echo "${TARGET_DIR}"

exit 0

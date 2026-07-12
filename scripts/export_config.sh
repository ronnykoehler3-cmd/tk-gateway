#!/bin/bash

DATE=$(date +%F_%H-%M-%S)

EXPORT="/opt/tkgateway/config-export/tkgateway-config-${DATE}.tar.gz"

tar czf "${EXPORT}" \
    /etc/dnsmasq.d \
    /etc/NetworkManager \
    /etc/systemd/system \
    /etc/nginx \
    /opt/tkgateway \
    /etc/sudoers.d \
    /etc/hosts \
    /etc/resolv.conf \
    >/dev/null 2>&1

echo "${EXPORT}"

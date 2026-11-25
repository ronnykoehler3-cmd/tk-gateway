#!/bin/bash
set -e

# LAN-Killswitch: Kein Rueckfall auf die Standardroute
if ! ip -4 rule show | grep -q "^101:.*from 192.168.50.0/24 prohibit"; then
    ip -4 rule add priority 101 from 192.168.50.0/24 prohibit
fi

# TUN anlegen
ip link delete xraytun 2>/dev/null || true
ip tuntap add dev xraytun mode tun
ip addr add 198.18.0.1/15 dev xraytun
ip link set xraytun up

# Policy Routing
ip rule del priority 100 2>/dev/null || true
ip route flush table 100 2>/dev/null || true

ip route add 192.168.50.0/24 dev eth1 src 192.168.50.1 table 100
ip route add default dev xraytun table 100

ip rule add priority 100 from 192.168.50.0/24 lookup 100

#!/bin/bash

ip rule del priority 100 2>/dev/null || true
ip route flush table 100 2>/dev/null || true
ip link delete xraytun 2>/dev/null || true

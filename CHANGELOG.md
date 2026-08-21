# Changelog - tk-gateway

## 2026-08-21 – aktueller Projektstand dokumentiert

- Raspberry Pi 5 als Gateway für den Standort Ägypten dokumentiert
- Haupt-Internet über `eth0` / WE Ägypten dokumentiert
- Backup-Internet über `eth2` / LTE dokumentiert
- Xray/VLESS-REALITY mit `xraytun` dokumentiert
- zwei getrennte VPN-Profile mit jeweils eigenen Schlüsseln/REALITY-Daten dokumentiert
- VPN 1 `194.93.11.96:443`
- VPN 2 `194.93.8.35:443`
- Policy Routing für das Clientnetz `192.168.50.0/24` berücksichtigt
- lokaler SOCKS5-Zugang `127.0.0.1:10808` dokumentiert
- Heartbeat-/Monitoring-Anbindung an das zentrale Monitoring berücksichtigt
- getrennte Statusanzeige für Hauptleitung, Backup-Leitung, VPN 1 und VPN 2 vorgesehen
- Speedtest-/Leitungsdiagnose für Hauptleitung, Backup-Leitung und Xray/VPN berücksichtigt
- vorhandene Client-Erkennung über dnsmasq-Leases und ARP/Neighbor-Daten erfasst
- Vereinheitlichung von Clientliste, Geräteverwaltung und Fernwartungsansicht als Ziel festgelegt
- MAC-Adresse als zentrale Geräte-ID festgelegt
- automatische Übernahme neu erkannter Geräte in alle Ansichten vorgesehen
- gemeinsame Pflege von Anzeigename, Hostname, IP, MAC, Gerätetyp, Rufnummer/Nebenstelle und Bemerkung vorgesehen
- DHCP-Reservierungen über die Option `Feste IP` vorgesehen
- Konfliktprüfung für doppelte IP-/MAC-Zuordnungen vorgesehen
- GitHub als zentrale Entwicklungs- und Dokumentationsbasis festgelegt

## 0.0.1

- Repository vorbereitet
- Grundstruktur erstellt
- Dokumentationsdateien angelegt

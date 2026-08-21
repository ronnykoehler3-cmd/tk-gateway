# TK Gateway

Zentrales Raspberry-Pi-5-Gateway für den Standort Ägypten.

Das System stellt das lokale Netz bereit, verwaltet Clients und Geräte, routet Verkehr über Xray/VLESS nach Deutschland und hält eine zweite Internet-/VPN-Strecke als Backup bereit.

## Aktueller produktiver Aufbau

- Raspberry Pi 5 / Debian 13 ARM64
- LAN für Endgeräte: `192.168.50.0/24`
- Gateway im Clientnetz: `192.168.50.1`
- Haupt-Internet: `eth0` über WE Ägypten
- Backup-Internet: `eth2` über LTE
- Xray/TUN: `xraytun`
- lokaler SOCKS5-Port: `127.0.0.1:10808`
- DHCP/DNS: dnsmasq
- Weboberfläche zur Verwaltung von Gateway, Clients, Netzwerk, VPN, Ereignissen und Wartung

## VPN

Es existieren zwei unabhängig konfigurierte Xray/VLESS-REALITY-Verbindungen nach Deutschland:

- VPN 1: `194.93.11.96:443`
- VPN 2: `194.93.8.35:443`

Beide Profile besitzen ihre eigenen Schlüssel/REALITY-Daten. Ein Profilwechsel darf daher nie nur durch Ersetzen der Server-IP erfolgen.

Die Endgeräte im Netz `192.168.50.0/24` werden per Policy Routing über `xraytun` geführt.

## Internet-Uplinks

### Hauptleitung

- Interface: `eth0`
- lokales Netz: `192.168.1.0/24`
- Gateway: `192.168.1.1`
- aktuelle Raspberry-IP wird per DHCP bezogen

### Backup-Leitung

- Interface: `eth2`
- lokales Netz: `192.168.5.0/24`
- Gateway: `192.168.5.1`
- aktuelle Raspberry-IP wird per DHCP bezogen

Die Backup-Leitung kann online sein, ohne aktiv genutzt zu werden. In Statusanzeigen muss daher zwischen **online** und **aktiv genutzt** unterschieden werden.

## Monitoring

Der Raspberry sendet Heartbeats an das zentrale TK-Monitoring in Deutschland.

Überwacht werden mindestens:

- Raspberry erreichbar / letzter Heartbeat
- VPN 1
- VPN 2
- Haupt-Internet `eth0`
- Backup-Internet `eth2`
- jeweils online/offline
- aktive Leitung bzw. aktiver VPN-Pfad

Wichtig: Ein reiner TCP-Port-443-Test ist nur eine Erreichbarkeitsprüfung und kein vollständiger Nachweis einer funktionierenden REALITY-Verbindung.

## Speedtests / Leitungsdiagnose

Die Gateway-Oberfläche enthält Messwerte für:

- Hauptleitung `eth0`
- Backup-Leitung `eth2`
- Xray/VPN
- Download
- Upload
- Ping
- öffentliche IP
- Testserver
- Zeitpunkt der letzten Messung

Direkte Leitungsmessungen und Messungen durch Xray müssen getrennt betrachtet werden.

## Geräte und Clients

Die aktuelle Clientansicht liest DHCP-Leases und ARP-/Neighbor-Daten des Raspberry aus. Zusätzlich existiert eine Geräteverwaltung mit eigenen Bezeichnungen, Rufnummer/Nebenstelle, Gerätetyp und Bemerkung sowie eine Fernwartungsansicht.

Diese Datenquellen sollen zu **einem zentralen Gerätebestand** zusammengeführt werden.

Zielzustand:

- automatisch erkannte DHCP-Clients erscheinen automatisch in allen Geräteansichten
- MAC-Adresse ist die primäre Geräte-ID
- gemeinsame Felder: IP, MAC, Hostname, Anzeigename, Gerätetyp, Rufnummer/Nebenstelle, Bemerkung, Online-Status
- Änderungen in einer Ansicht erscheinen automatisch in allen Ansichten
- neue Geräte erscheinen ohne manuelle Doppelpflege überall
- Online/Offline wird aus dem tatsächlichen Gateway-Status ermittelt

### Feste IP

In der Geräteverwaltung soll direkt hinter der IP-Adresse die Option **Feste IP** verfügbar sein.

Bei aktiviertem Haken:

- wird die MAC-Adresse per DHCP-Reservierung an die gewählte IP gebunden
- die Reservierung wird zentral durch dnsmasq verwaltet
- doppelte IP- oder MAC-Zuordnungen werden verhindert
- die feste IP wird in allen Ansichten sichtbar markiert

Beim Entfernen des Hakens wird die Reservierung wieder entfernt und das Gerät kann wieder dynamisch per DHCP adressiert werden.

## Entwicklungsgrundsatz

GitHub ist die zentrale Entwicklungs- und Dokumentationsbasis. Produktive Änderungen sollen reproduzierbar über Dateien/Skripte erfolgen und nicht ausschließlich manuell auf dem Raspberry vorgenommen werden.

Zugangsdaten, private Schlüssel, Tokens und andere Secrets gehören nicht in dieses öffentliche Repository.

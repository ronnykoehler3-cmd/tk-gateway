# TK-Gateway – Triple-WAN

## Funktionen
- Manuelle Auswahl von WAN 1, WAN 2 und LTE.
- Automatische Leitungsumschaltung im AUTO-Modus.
- Persistente manuelle Leitungsauswahl.
- Kein automatischer Leitungswechsel im manuellen Modus.
- Anzeige und Steuerung im Dashboard und Netzwerkmenue.
- Automatische Aktualisierung der WAN-Anzeige.
- Zentrale MAC-Konfiguration.
- Xray-Watchdog mit WAN-Zuordnung.
- Zusaetzlicher LAN-Firewall-Killswitch.
- Routing-Killswitch mit Prioritaet 101.

## Lokale Konfiguration
/etc/tkgateway/triplewan.conf

Die lokale Konfiguration darf bei Updates nicht
ueberschrieben oder in GitHub gespeichert werden.

## Installation
scripts/install-triplewan.sh --check
scripts/install-triplewan.sh --backup

## Entwicklungsstand
Alle drei WAN-Leitungen mit Xray getestet.
AUTO-Modus getestet.
Syntaxpruefungen erfolgreich.
Installation und Rollback noch nicht freigegeben.
LAN-Killswitch noch nicht produktiv aktiviert.
Neustart- und Leitungsausfalltests stehen aus.

# TODO - tk-gateway

## Gerätebestand vereinheitlichen

- [ ] zentrale Gerätedatenquelle für Clientliste, Geräteverwaltung und Fernwartungsansicht erstellen
- [ ] bestehende manuell gepflegte Geräte in den zentralen Bestand übernehmen
- [ ] dnsmasq-Leases automatisch mit dem zentralen Gerätebestand abgleichen
- [ ] MAC-Adresse als primäre Geräte-ID verwenden
- [ ] neue DHCP-Geräte automatisch in allen Ansichten anzeigen
- [ ] gemeinsame Felder überall verwenden: IP, MAC, Hostname, Anzeigename, Typ, Rufnummer/Nebenstelle, Bemerkung, Online-Status
- [ ] Änderungen einer Ansicht sofort in allen anderen Ansichten sichtbar machen

## Feste IP / DHCP-Reservierung

- [ ] Checkbox `Feste IP` direkt hinter der IP-Adresse ergänzen
- [ ] bei aktiviertem Haken dnsmasq-DHCP-Reservierung aus MAC + IP erzeugen
- [ ] beim Entfernen des Hakens Reservierung entfernen
- [ ] doppelte IP-Zuordnungen verhindern
- [ ] doppelte MAC-Zuordnungen verhindern
- [ ] dnsmasq-Konfiguration vor Reload validieren
- [ ] bei Fehlern alten Stand beibehalten / Rollback durchführen
- [ ] feste IP in Client- und Fernwartungsansicht markieren

## Monitoring / Netzwerk

- [ ] Hauptleitung eth0 und Backup eth2 getrennt als online/offline und aktiv/inaktiv darstellen
- [ ] VPN 1 und VPN 2 getrennt als online/offline und aktiv/inaktiv darstellen
- [ ] Heartbeat-Status mit zentralem Monitoring weiter pflegen
- [ ] Speedtestwerte für eth0, eth2 und Xray/VPN sauber getrennt darstellen
- [ ] Qualitätswerte wie Paketverlust/Latenz bei Bedarf ergänzen

## Dokumentation / Betrieb

- [ ] Installations- und Updateweg des aktuellen produktiven Raspberry vollständig dokumentieren
- [ ] Backup-/Restore-Pfade auf aktuellen Stand bringen
- [ ] produktive Konfigurationsdateien mit Beispielvarianten im Repository abbilden
- [ ] sicherstellen, dass keine Secrets, Tokens oder privaten Schlüssel eingecheckt werden

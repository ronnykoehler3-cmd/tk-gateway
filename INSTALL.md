# Installation - tk-gateway

## Voraussetzungen

- Raspberry Pi mit Debian 13
- Repository unter `/opt/tk-gateway`
- Webroot `/var/www/tkgateway`, Nginx und PHP 8.4
- Rsyslog aktiv; vorhandene Telefonie-Syslog-Datei `/var/log/tkgateway/fritzbox.log`
- `logrotate`, `sudo`, `visudo` und `journalctl`

## Modul „Syslog & Fehlerdiagnose“ installieren

Zuerst den freigegebenen Feature-Branch im Repository auschecken und aktualisieren. Danach genügt für die Installation dieser einzelne Befehl:

```bash
cd /opt/tk-gateway && sudo bash scripts/install-syslog.sh --install
```

Beim ersten Lauf fragt das Skript interaktiv nach einem selbst gewählten Syslog-Passwort (mindestens 12 Zeichen). Es gibt kein Standardpasswort. Bei einer Aktualisierung bleibt das bereits eingerichtete Passwort erhalten.

Die Installation legt vor Änderungen eine Sicherung unter `/var/backups/tkgateway-syslog/` an. Sie ergänzt den Eintrag gezielt in der vorhandenen `includes/header.php`: existiert der Eintrag schon, bleibt die Menüdatei unangetastet. Die Repository-Version des Headers wird nicht blind über die Produktivdatei kopiert.

Das Skript prüft PHP- und Shell-Syntax, Rsyslog, sudoers und Logrotation. Die vorhandene `fritzbox.log` wird nicht geleert oder ersetzt. Ist sie vorhanden, bleibt die bestehende Rsyslog-Empfangs- und Routingkonfiguration unverändert. Nur wenn die Logdatei noch nicht existiert, kann das Installationsskript einen fehlenden UDP-514-Empfang für `192.168.50.0/24` ergänzen.

Die Telefonie- und vorhandene Watchdog-Logdatei erhalten ausschließlich für `www-data` Leserechte. Logrotation begrenzt beide aktiven Dateien auf jeweils 10 MB und hält je 30 Rotationen vor. Ein eng begrenzter root-Helfer liest Journalmeldungen der Gateway-Watchdog- und WAN-Failover-Dienste; `www-data` erhält keine allgemeinen Journal- oder Administrationsrechte.

## Nutzung

Aufrufen: `https://<Gateway-Adresse>/syslog-login.php`. Die Ansicht aktualisiert sich alle fünf Sekunden und startet im Laienmodus. Nach fünf fehlgeschlagenen Passworteingaben wird der Zugang der betreffenden Quell-IP für 15 Minuten begrenzt. Der Expertenmodus zeigt technische Meldungen; SIP-Passwörter, Authorization-Header, Tokens und ähnliche Zugangsdaten werden auch dort und im CSV-Export maskiert. Der Export ist wie die Ansicht durch die Anmeldung geschützt.

Enthält das vorhandene Fritzbox-Log keine Absender-IP im Syslog-Kopf, zeigt die Diagnose „nicht im Log enthalten“ an. Sie leitet die Adresse nicht aus einer anderen Meldung ab. Das erkannte Telefon bzw. Gerät wird separat anhand von Modell, Hostname und MAC-Adresse dargestellt.

401/407 sind häufig normale SIP-Anmeldeabfragen. `200 OK` belegt nur die jeweilige SIP-Anfrage und wird nicht als Beweis einer erfolgreichen Registrierung dargestellt. Nicht erkannte Meldungen werden ausdrücklich als unbekannt markiert.

## Prüfen, aktualisieren und zurückrollen

```bash
sudo bash /opt/tk-gateway/scripts/install-syslog.sh --check
sudo bash /opt/tk-gateway/scripts/install-syslog.sh --install
sudo bash /opt/tk-gateway/scripts/install-syslog.sh --rollback
```

`--rollback` verwendet die zuletzt angelegte Sicherung. Alternativ kann deren konkreter Pfad als zweites Argument übergeben werden. Die Logdatei selbst wird beim Rollback nicht ersetzt; ihre vorhandenen Meldungen bleiben bestehen.

Die Regressionstests liegen unter `tests/`. Lokal können sie mit PHP 8.4, Bash und Node.js gestartet werden:

```bash
php -l app/classes/SyslogAuth.php
php -l app/classes/SyslogDiagnose.php
php tests/test_syslog_parser.php
php tests/test_syslog_access.php
bash -n scripts/install-syslog.sh
bash tests/test_syslog_installer.sh
node --check public/assets/js/syslog.js
```

GitHub Actions führt diese Prüfungen bei Änderungen am Feature-Branch aus.


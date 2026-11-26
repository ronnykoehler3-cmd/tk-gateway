#!/usr/bin/env bash
set -Eeuo pipefail
umask 077

REPO="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TEST_ROOT="${TK_SYSLOG_TEST_ROOT:-}"
WEBROOT="${TK_GATEWAY_WEBROOT:-${TEST_ROOT:+$TEST_ROOT}/var/www/tkgateway}"
WEBROOT="${WEBROOT:-/var/www/tkgateway}"
WEB_USER="${TK_GATEWAY_WEB_USER:-www-data}"
BACKUP_ROOT="${TEST_ROOT:+$TEST_ROOT}/var/backups/tkgateway-syslog"
BACKUP_ROOT="${BACKUP_ROOT:-/var/backups/tkgateway-syslog}"
RSYSLOG_DIR="${TEST_ROOT:+$TEST_ROOT}/etc/rsyslog.d"
RSYSLOG_DIR="${RSYSLOG_DIR:-/etc/rsyslog.d}"
LOGROTATE_DIR="${TEST_ROOT:+$TEST_ROOT}/etc/logrotate.d"
LOGROTATE_DIR="${LOGROTATE_DIR:-/etc/logrotate.d}"
ETC_DIR="${TEST_ROOT:+$TEST_ROOT}/etc/tkgateway"
ETC_DIR="${ETC_DIR:-/etc/tkgateway}"
SUDOERS_DIR="${TEST_ROOT:+$TEST_ROOT}/etc/sudoers.d"
SUDOERS_DIR="${SUDOERS_DIR:-/etc/sudoers.d}"
LOCAL_SBIN="${TEST_ROOT:+$TEST_ROOT}/usr/local/sbin"
LOCAL_SBIN="${LOCAL_SBIN:-/usr/local/sbin}"
SYSLOG_AUTH="$ETC_DIR/syslog-auth.php"
ROTATION="$LOGROTATE_DIR/tk-gateway-syslog"
RSYSLOG_DROPIN="$RSYSLOG_DIR/60-tkgateway-syslog.conf"
SUDOERS="$SUDOERS_DIR/tkgateway-syslog"
JOURNAL_HELPER="$LOCAL_SBIN/tkgateway-syslog-journal"
LOGFILE="${TEST_ROOT:+$TEST_ROOT}/var/log/tkgateway/fritzbox.log"
LOGFILE="${LOGFILE:-/var/log/tkgateway/fritzbox.log}"
WATCHDOG_LOG="${TEST_ROOT:+$TEST_ROOT}/var/log/tkgateway/vpn-watchdog.log"
WATCHDOG_LOG="${WATCHDOG_LOG:-/var/log/tkgateway/vpn-watchdog.log}"
MANIFEST="$REPO/config/syslog-install.manifest"

path() { printf '%s%s' "${TEST_ROOT%/}" "$1"; }
need() { command -v "$1" >/dev/null 2>&1 || { echo "FEHLER: Benötigtes Programm fehlt: $1" >&2; return 1; }; }

preflight() {
    echo '===== VORPRÜFUNG ====='
    for cmd in bash php python3 install cp chmod chown chgrp stat rsyslogd systemctl logrotate visudo sudo journalctl; do need "$cmd"; done
    [[ $EUID -eq 0 || -n "$TEST_ROOT" ]] || { echo 'FEHLER: Installation und Rollback benötigen root.' >&2; return 1; }
    [[ -d "$WEBROOT" ]] || { echo "FEHLER: Webroot nicht gefunden: $WEBROOT" >&2; return 1; }
    [[ "$WEB_USER" =~ ^[a-z_][a-z0-9_-]*\$?$ ]] || { echo 'FEHLER: Ungültiger Webbenutzer.' >&2; return 1; }
    [[ -f "$WEBROOT/includes/header.php" ]] || { echo 'FEHLER: Produktive Menüdatei includes/header.php fehlt.' >&2; return 1; }
    [[ ! -L "$LOGFILE" && ! -L "$WATCHDOG_LOG" && ! -L "$SYSLOG_AUTH" ]] || { echo 'FEHLER: Log- oder Passwortdatei darf kein symbolischer Link sein.' >&2; return 1; }
    [[ -f "$LOGFILE" || -d "$(dirname "$LOGFILE")" ]] || { echo 'FEHLER: Logverzeichnis fehlt.' >&2; return 1; }
    [[ -f "$SYSLOG_AUTH" || -t 0 || -r /dev/tty ]] || { echo 'FEHLER: Für das erste Passwort ist eine interaktive Eingabe erforderlich.' >&2; return 1; }

    while read -r src; do
        [[ -f "$REPO/$src" ]] || { echo "FEHLER: Quelldatei fehlt: $src" >&2; return 1; }
        case "$src" in
            *.php) php -l "$REPO/$src" >/dev/null || return 1 ;;
            *.sh|scripts/tkgateway-syslog-journal) bash -n "$REPO/$src" || return 1 ;;
        esac
        echo "OK: $src"
    done < "$MANIFEST"
    visudo -cf "$REPO/config/sudoers/tkgateway-syslog" >/dev/null || return 1
    if [[ -f "$ROTATION" ]]; then logrotate -d "$ROTATION" >/dev/null 2>&1 || { echo 'FEHLER: vorhandene Logrotation ist ungültig.' >&2; return 1; }; fi
    if [[ -f "$RSYSLOG_DROPIN" ]]; then rsyslogd -N1 >/dev/null 2>&1 || { echo 'FEHLER: vorhandene Rsyslog-Konfiguration ist ungültig.' >&2; return 1; }
    elif [[ -z "$TEST_ROOT" ]]; then rsyslogd -N1 >/dev/null || { echo 'FEHLER: bestehende Rsyslog-Konfiguration ist ungültig.' >&2; return 1; }
    fi
    if [[ -n "$TEST_ROOT" ]]; then
        echo 'TESTROOT aktiv: produktive Dienste werden nicht angesprochen.'
    else
        systemctl is-active --quiet rsyslog.service || { echo 'FEHLER: rsyslog.service ist nicht aktiv.' >&2; return 1; }
    fi
    echo '===== VORPRÜFUNG BESTANDEN ====='
}

backup_path() {
    local target="$1" rel="${1#/}"
    if [[ -e "$target" || -L "$target" ]]; then
        install -d -m 0700 "$BACKUP/files/$(dirname "$rel")"
        cp -a -- "$target" "$BACKUP/files/$rel"
    else
        printf '%s\n' "$target" >> "$BACKUP/missing"
    fi
}

backup_all() {
    install -d -m 0700 "$BACKUP_ROOT"
    BACKUP="$BACKUP_ROOT/$(date +%Y%m%d-%H%M%S)-$$"
    install -d -m 0700 "$BACKUP"
    printf '%s\n' "$WEBROOT/includes/header.php" "$WEBROOT/syslog.php" "$WEBROOT/syslog-login.php" "$WEBROOT/syslog-logout.php" "$WEBROOT/api/syslog.php" "$WEBROOT/app/classes/SyslogAuth.php" "$WEBROOT/app/classes/SyslogDiagnose.php" "$WEBROOT/assets/js/syslog.js" "$WEBROOT/assets/css/syslog.css" "$SYSLOG_AUTH" "$ROTATION" "$RSYSLOG_DROPIN" "$SUDOERS" "$JOURNAL_HELPER" > "$BACKUP/targets"
    while IFS= read -r target; do backup_path "$target"; done < "$BACKUP/targets"
    : > "$BACKUP/log-metadata"
    for logfile in "$LOGFILE" "$WATCHDOG_LOG"; do
        if [[ -e "$logfile" ]]; then printf '%s %s %s %s\n' "$logfile" "$(stat -c '%a' "$logfile")" "$(stat -c '%u' "$logfile")" "$(stat -c '%g' "$logfile")" >> "$BACKUP/log-metadata"; fi
    done
    printf '%s\n' "$BACKUP" > "$BACKUP_ROOT/latest"
    echo "Sicherung erstellt: $BACKUP"
}

set_password_if_needed() {
    [[ -f "$SYSLOG_AUTH" ]] && { echo 'Vorhandenes Syslog-Passwort bleibt erhalten.'; return; }
    local first second hash
    if [[ -n "${TK_SYSLOG_PASSWORD:-}" ]]; then
        first="$TK_SYSLOG_PASSWORD"; second="$TK_SYSLOG_PASSWORD"
    else
        [[ -r /dev/tty ]] || { echo 'FEHLER: Passwort kann ohne Terminal nicht abgefragt werden.' >&2; return 1; }
        read -r -s -p 'Neues Syslog-Passwort (mindestens 12 Zeichen): ' first </dev/tty; echo
        read -r -s -p 'Passwort wiederholen: ' second </dev/tty; echo
    fi
    [[ "$first" == "$second" ]] || { echo 'FEHLER: Passwörter stimmen nicht überein.' >&2; return 1; }
    ((${#first} >= 12)) || { echo 'FEHLER: Bitte mindestens 12 Zeichen verwenden.' >&2; return 1; }
    hash="$(php -r 'echo password_hash($argv[1], PASSWORD_DEFAULT);' "$first")"
    [[ "$hash" == \$* ]] || { echo 'FEHLER: Passwort-Hash konnte nicht erstellt werden.' >&2; return 1; }
    install -d -o root -g "$WEB_USER" -m 0750 "$(dirname "$SYSLOG_AUTH")"
    printf "<?php\nreturn '%s';\n" "$hash" > "$SYSLOG_AUTH"
    chown root:"$WEB_USER" "$SYSLOG_AUTH"
    chmod 0640 "$SYSLOG_AUTH"
    unset first second hash TK_SYSLOG_PASSWORD
}

patch_menu() {
    python3 - "$1" <<'PY'
import pathlib, sys
p = pathlib.Path(sys.argv[1])
text = p.read_text(encoding='utf-8')
if 'href="/syslog.php"' in text:
    print('Menüeintrag ist bereits vorhanden.')
    raise SystemExit(0)
lines = text.splitlines(keepends=True)
index = next((i for i, line in enumerate(lines) if 'href="/maintenance.php"' in line), None)
if index is None:
    raise SystemExit('FEHLER: Menüanker /maintenance.php fehlt; Menüdatei wurde nicht geändert.')
start = max((i for i in range(index) if lines[i].lstrip().startswith('<li>')), default=None)
if start is None:
    raise SystemExit('FEHLER: Menüblock konnte nicht sicher erkannt werden.')
indent = lines[start][:len(lines[start]) - len(lines[start].lstrip())]
block = [
    f'{indent}<li>\n',
    f'{indent}    <a class="<?php echo $current==\'syslog.php\'?\'active\':\'\'; ?>"\n',
    f'{indent}       href="/syslog.php">\n',
    f'{indent}        🩺 Syslog &amp; Fehlerdiagnose\n',
    f'{indent}    </a>\n',
    f'{indent}</li>\n',
    '\n',
]
lines[start:start] = block
p.write_text(''.join(lines), encoding='utf-8')
print('Menüeintrag gezielt vor Wartung ergänzt.')
PY
}

install_files() {
    local dest owner
    install -d -o root -g root -m 0755 "$WEBROOT/api" "$WEBROOT/app/classes" "$WEBROOT/assets/js" "$WEBROOT/assets/css"
    install -d -o "$WEB_USER" -g "$WEB_USER" -m 0700 "$(path /var/lib/tkgateway/syslog-auth-failures)"
    install -o root -g root -m 0644 "$REPO/public/syslog.php" "$WEBROOT/syslog.php"
    install -o root -g root -m 0644 "$REPO/public/syslog-login.php" "$WEBROOT/syslog-login.php"
    install -o root -g root -m 0644 "$REPO/public/syslog-logout.php" "$WEBROOT/syslog-logout.php"
    install -o root -g root -m 0644 "$REPO/public/api/syslog.php" "$WEBROOT/api/syslog.php"
    install -o root -g root -m 0644 "$REPO/app/classes/SyslogAuth.php" "$WEBROOT/app/classes/SyslogAuth.php"
    install -o root -g root -m 0644 "$REPO/app/classes/SyslogDiagnose.php" "$WEBROOT/app/classes/SyslogDiagnose.php"
    install -o root -g root -m 0644 "$REPO/public/assets/js/syslog.js" "$WEBROOT/assets/js/syslog.js"
    install -o root -g root -m 0644 "$REPO/public/assets/css/syslog.css" "$WEBROOT/assets/css/syslog.css"
    patch_menu "$WEBROOT/includes/header.php"
    install -d -o root -g root -m 0755 "$SUDOERS_DIR"
    install -d -o root -g root -m 0755 "$LOCAL_SBIN"
    install -o root -g root -m 0755 "$REPO/scripts/tkgateway-syslog-journal" "$JOURNAL_HELPER"
    local sudoers_tmp
    sudoers_tmp="$(mktemp "$SUDOERS_DIR/.tkgateway-syslog.XXXXXX")"
    sed "s/^www-data /$WEB_USER /" "$REPO/config/sudoers/tkgateway-syslog" > "$sudoers_tmp"
    install -o root -g root -m 0440 "$sudoers_tmp" "$SUDOERS"
    rm -f "$sudoers_tmp"
    visudo -cf "$SUDOERS" >/dev/null

    install -d -o root -g root -m 0755 "$(dirname "$ROTATION")"
    owner=root
    local_log_missing=0
    if [[ -e "$LOGFILE" ]]; then
        owner="$(stat -c '%U' "$LOGFILE")"
        chgrp "$WEB_USER" "$LOGFILE"
        chmod 0640 "$LOGFILE"
    else
        local_log_missing=1
        install -d -o root -g root -m 0755 "$(dirname "$LOGFILE")"
        install -o "$owner" -g "$WEB_USER" -m 0640 /dev/null "$LOGFILE"
    fi
    watchdog_owner=root
    if [[ -e "$WATCHDOG_LOG" ]]; then
        watchdog_owner="$(stat -c '%U' "$WATCHDOG_LOG")"
        chgrp "$WEB_USER" "$WATCHDOG_LOG"
        chmod 0640 "$WATCHDOG_LOG"
    fi
    cat > "$ROTATION" <<EOF
$LOGFILE {
    size 10M
    rotate 30
    missingok
    notifempty
    compress
    delaycompress
    create 0640 $owner $WEB_USER
    su root root
    postrotate
        /bin/systemctl kill -s HUP rsyslog.service >/dev/null 2>&1 || true
    endscript
}

$WATCHDOG_LOG {
    size 10M
    rotate 30
    missingok
    notifempty
    compress
    delaycompress
    create 0640 $watchdog_owner $WEB_USER
    su root root
}
EOF
    chmod 0644 "$ROTATION"

    # Adopt an existing listener and logfile without duplicating it. Add a
    # dedicated UDP receiver only when the established log file does not exist.
    if (( local_log_missing )) && ! grep -RqsF -- "$LOGFILE" "$(path /etc/rsyslog.conf)" "$RSYSLOG_DIR" 2>/dev/null; then
        local_rsyslog_changed=0
        install -d -o root -g root -m 0755 "$RSYSLOG_DIR"
        if [[ -e "$RSYSLOG_DROPIN" ]]; then
            echo 'Vorhandene Rsyslog-Drop-in-Datei erkannt: sie bleibt unverändert.'
        elif grep -RqsE 'UDPServerRun[[:space:]]+514|input[[:space:]]*\(.*imudp|imudp.*port[[:space:]]*=[[:space:]]*"?514' "$(path /etc/rsyslog.conf)" "$RSYSLOG_DIR" 2>/dev/null; then
            cat > "$RSYSLOG_DROPIN" <<EOF
if (\$fromhost-ip startswith '192.168.50.') then {
    action(type="omfile" file="$LOGFILE")
}
EOF
            local_rsyslog_changed=1
        else
            cat > "$RSYSLOG_DROPIN" <<EOF
module(load="imudp")
input(type="imudp" port="514")
if (\$fromhost-ip startswith '192.168.50.') then {
    action(type="omfile" file="$LOGFILE")
}
EOF
            local_rsyslog_changed=1
        fi
        if (( local_rsyslog_changed )); then
            chmod 0644 "$RSYSLOG_DROPIN"
            rsyslogd -N1 >/dev/null
            [[ -n "$TEST_ROOT" ]] || systemctl reload rsyslog.service
        fi
    else
        echo 'Vorhandene fritzbox.log erkannt: bestehende Rsyslog-Empfangs- und Routingkonfiguration bleibt unverändert.'
    fi
    set_password_if_needed
}

rollback() {
    local selected="${1:-}"
    if [[ -z "$selected" ]]; then
        [[ -f "$BACKUP_ROOT/latest" ]] || { echo 'FEHLER: Keine Sicherung für Rollback gefunden.' >&2; return 1; }
        selected="$(cat "$BACKUP_ROOT/latest")"
    fi
    [[ -d "$selected" && -f "$selected/targets" ]] || { echo "FEHLER: Ungültige Sicherung: $selected" >&2; return 1; }
    while IFS= read -r target; do
        local rel="${target#/}" backup_file="$selected/files/${target#/}"
        if [[ -e "$backup_file" || -L "$backup_file" ]]; then
            install -d -m 0755 "$(dirname "$target")"
            rm -rf -- "$target"
            cp -a -- "$backup_file" "$target"
        fi
    done < "$selected/targets"
    if [[ -f "$selected/missing" ]]; then
        while IFS= read -r target; do rm -rf -- "$target"; done < "$selected/missing"
    fi
    if [[ -f "$selected/log-metadata" ]]; then
        while read -r logfile mode uid gid; do
            [[ -e "$logfile" ]] || continue
            chmod "$mode" "$logfile"
            chown "$uid:$gid" "$logfile"
        done < "$selected/log-metadata"
    fi
    if [[ -z "$TEST_ROOT" ]]; then
        rsyslogd -N1 && systemctl reload rsyslog.service
    fi
    echo "Rollback abgeschlossen aus $selected. Protokollinhalte wurden nicht verändert."
}

case "${1:---check}" in
    --check) preflight ;;
    --install)
        preflight
        backup_all
        handle_install_error() {
            local status=$?
            trap - ERR
            echo 'FEHLER bei Installation; stelle Sicherung wieder her.' >&2
            rollback "$BACKUP"
            exit "$status"
        }
        trap handle_install_error ERR
        install_files
        trap - ERR
        echo 'Syslog & Fehlerdiagnose installiert. Keine Änderungen an Xray, VLESS, VPN-Konfiguration, Triple-WAN, Routing, Firewall oder Telefonie wurden vorgenommen.'
        echo "Webseite: /syslog-login.php  |  Sicherung: $BACKUP"
        ;;
    --rollback)
        [[ $EUID -eq 0 || -n "$TEST_ROOT" ]] || { echo 'FEHLER: Rollback benötigt root.' >&2; exit 1; }
        rollback "${2:-}"
        ;;
    *) echo "Verwendung: $0 --check|--install|--rollback [Sicherungspfad]" >&2; exit 2 ;;
esac

#!/bin/bash
source "$(dirname "${BASH_SOURCE[0]}")/tkgateway-wan-config.sh"

set -u

STATE_DIR="/var/lib/tkgateway-watchdog"
LOG="/var/log/tkgateway/vpn-watchdog.log"

VPN_FAIL_FILE="$STATE_DIR/vpn_failures"
RECOVERY_FILE="$STATE_DIR/recovery_failures"

WAN_STATE_FILE="$STATE_DIR/wan_state"
WAN_FAIL_FILE="$STATE_DIR/wan_failures"
WAN_OK_FILE="$STATE_DIR/wan_successes"

LOCK_FILE="/run/tkgateway-vpn-watchdog.lock"

SOCKS="127.0.0.1:10808"

# VPN:
# Erst nach 3 echten VPN-Fehlern reparieren.
MAX_VPN_FAILURES=3

# Nach 3 komplett erfolglosen Reparaturzyklen
# ist ein Reboot die letzte Eskalationsstufe.
MAX_RECOVERY_FAILURES=3

# WAN:
# Einzelne Aussetzer NICHT mehr als Internet-Ausfall behandeln.
MAX_WAN_FAILURES=3

# Nach einem echten WAN-Ausfall müssen zwei Prüfungen
# hintereinander erfolgreich sein.
MAX_WAN_SUCCESSES=2

mkdir -p "$STATE_DIR"
mkdir -p "$(dirname "$LOG")"

exec 9>"$LOCK_FILE"

if ! flock -n 9; then
    exit 0
fi

# ------------------------------------------------------------
# HILFSFUNKTIONEN
# ------------------------------------------------------------

now() {
    TZ=Europe/Berlin date '+%Y-%m-%d %H:%M:%S %Z'
}

log() {
    MSG="$(now) $*"
    echo "$MSG" >> "$LOG"
    logger -t TK-VPN-WATCHDOG "$*"
}

read_number() {

    VALUE="$(cat "$1" 2>/dev/null || echo 0)"

    case "$VALUE" in
        ''|*[!0-9]*)
            VALUE=0
            ;;
    esac

    echo "$VALUE"
}

valid_ip() {
    [[ "$1" =~ ^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$ ]]
}

# ------------------------------------------------------------
# DIREKTES INTERNET
#
# Mehrere unabhängige Dienste.
# Sobald EINER sauber eine IPv4 liefert, gilt WAN als erreichbar.
# Der Verkehr wird ausdrücklich über eth0 geschickt.
# ------------------------------------------------------------

direct_ip() {
    local role mac path wan_dev=""
    role=$(cat /var/lib/tkgateway-triplewan-live/manual 2>/dev/null || echo AUTO)
    [[ "$role" != AUTO ]] || role=$(cat /var/lib/tkgateway-triplewan-live/active)
    case "$role" in PRIMARY) mac="$(tk_config_get wan PRIMARY_MAC)";; BACKUP) mac="$(tk_config_get wan BACKUP_MAC)";; LTE) mac="$(tk_config_get wan LTE_MAC)";; *) return 1;; esac
    for path in /sys/class/net/*; do
      if [[ $(cat "$path/address" 2>/dev/null) == "$mac" ]]; then wan_dev=${path##*/}; break; fi
    done
    [[ -n "$wan_dev" ]] || return 1

    RESULT=""

    for URL in \
        "https://api.ipify.org" \
        "https://icanhazip.com" \
        "https://ifconfig.me/ip"
    do

        RESULT="$(
            curl -4 -s \
              --interface "$wan_dev" \
              --connect-timeout 4 \
              --max-time 7 \
              "$URL" 2>/dev/null |
            tr -d '\r\n ' || true
        )"

        if valid_ip "$RESULT"; then
            echo "$RESULT"
            return 0
        fi
    done

    return 1
}

# ------------------------------------------------------------
# VPN-AUSGANG
# ------------------------------------------------------------

vpn_ip() {

    curl -4 -s \
      --socks5-hostname "$SOCKS" \
      --connect-timeout 5 \
      --max-time 10 \
      https://api.ipify.org 2>/dev/null |
    tr -d '\r\n ' || true
}

vpn_healthy() {

    TEST_IP="$(vpn_ip)"

    if valid_ip "$TEST_IP" &&
       [ "$TEST_IP" != "$WAN_IP" ]
    then
        VPN_TEST_IP="$TEST_IP"
        return 0
    fi

    VPN_TEST_IP="${TEST_IP:-KEINE_VERBINDUNG}"

    return 1
}

# ------------------------------------------------------------
# VPN KONTROLLIERT NEU AUFBAUEN
# ------------------------------------------------------------

restart_vpn() {

    log "REPARATUR: Stoppe XRay-TUN."

    systemctl stop tkgateway-xraytun.service || true

    sleep 2

    log "REPARATUR: Starte XRay neu."

    systemctl restart xray.service || true

    sleep 4

    log "REPARATUR: Starte XRay-TUN neu."

    systemctl start tkgateway-xraytun.service || true

    sleep 8
}

# ============================================================
# 1. WAN TESTEN
# ============================================================

WAN_IP="$(direct_ip || true)"

WAN_STATE="$(cat "$WAN_STATE_FILE" 2>/dev/null || echo "UP")"
WAN_FAILS="$(read_number "$WAN_FAIL_FILE")"
WAN_OKS="$(read_number "$WAN_OK_FILE")"

# ------------------------------------------------------------
# WAN TEST FEHLGESCHLAGEN
# ------------------------------------------------------------

if ! valid_ip "$WAN_IP"; then

    WAN_OKS=0
    echo 0 > "$WAN_OK_FILE"

    WAN_FAILS=$((WAN_FAILS + 1))
    echo "$WAN_FAILS" > "$WAN_FAIL_FILE"

    # Nur den ersten und den letzten Vorwarnungswert loggen,
    # damit das Log nicht zugespammt wird.

    if [ "$WAN_FAILS" -eq 1 ]; then
        log "WAN TEST FEHLER 1/$MAX_WAN_FAILURES: Noch KEIN bestätigter WAN-Ausfall."
    fi

    if [ "$WAN_FAILS" -lt "$MAX_WAN_FAILURES" ]; then
        exit 0
    fi

    # Erst JETZT gilt WAN wirklich als DOWN.

    if [ "$WAN_STATE" != "DOWN" ]; then

        log "WAN DOWN BESTAETIGT: $MAX_WAN_FAILURES aufeinanderfolgende Internettests fehlgeschlagen."

        echo "DOWN" > "$WAN_STATE_FILE"
    fi

    # Während WAN wirklich DOWN ist:
    # KEIN XRay-Neustart
    # KEIN Failover erzwingen
    # KEIN Raspberry-Reboot

    exit 0
fi

# ============================================================
# 2. WAN TEST ERFOLGREICH
# ============================================================

echo 0 > "$WAN_FAIL_FILE"

# ------------------------------------------------------------
# WAN WAR VORHER DOWN
# ------------------------------------------------------------

if [ "$WAN_STATE" = "DOWN" ]; then

    WAN_OKS=$((WAN_OKS + 1))
    echo "$WAN_OKS" > "$WAN_OK_FILE"

    if [ "$WAN_OKS" -eq 1 ]; then

        log "WAN RUECKKEHR TEST 1/$MAX_WAN_SUCCESSES: IP=$WAN_IP - warte auf zweite Bestaetigung."

        exit 0
    fi

    if [ "$WAN_OKS" -lt "$MAX_WAN_SUCCESSES" ]; then
        exit 0
    fi

    # Zwei Prüfungen hintereinander waren erfolgreich.

    echo "UP" > "$WAN_STATE_FILE"
    echo 0 > "$WAN_OK_FILE"

    log "WAN WIEDER STABIL ONLINE: IP=$WAN_IP"

    log "Erzwinge EINMALIG nach bestaetigter WAN-Rueckkehr Neuaufbau des VPN."

    restart_vpn

    echo 0 > "$VPN_FAIL_FILE"
    echo 0 > "$RECOVERY_FILE"

    exit 0
fi

# ------------------------------------------------------------
# WAN IST NORMAL UP
# ------------------------------------------------------------

echo "UP" > "$WAN_STATE_FILE"
echo 0 > "$WAN_OK_FILE"

# ============================================================
# 3. XRAY SERVICE
# ============================================================

if ! systemctl is-active --quiet xray.service; then

    log "XRAY SERVICE DOWN."

    restart_vpn

    exit 0
fi

# ============================================================
# 4. XRAY-TUN SERVICE
# ============================================================

if ! systemctl is-active --quiet tkgateway-xraytun.service; then

    log "XRAY-TUN SERVICE DOWN."

    restart_vpn

    exit 0
fi

# ============================================================
# 5. ECHTEN VPN-DATENWEG TESTEN
# ============================================================

VPN_TEST_IP=""

if vpn_healthy; then

    VPN_FAILS="$(read_number "$VPN_FAIL_FILE")"
    RECOVERIES="$(read_number "$RECOVERY_FILE")"

    if [ "$VPN_FAILS" -gt 0 ] ||
       [ "$RECOVERIES" -gt 0 ]
    then

        log "VPN WIEDER GESUND: WAN=$WAN_IP VPN=$VPN_TEST_IP"
    fi

    echo 0 > "$VPN_FAIL_FILE"
    echo 0 > "$RECOVERY_FILE"

    exit 0
fi

# ============================================================
# 6. VPN FEHLER ZÄHLEN
# ============================================================

VPN_FAILS="$(read_number "$VPN_FAIL_FILE")"

VPN_FAILS=$((VPN_FAILS + 1))

echo "$VPN_FAILS" > "$VPN_FAIL_FILE"

log "VPN TEST FEHLER $VPN_FAILS/$MAX_VPN_FAILURES: WAN=$WAN_IP VPN=$VPN_TEST_IP"

if [ "$VPN_FAILS" -lt "$MAX_VPN_FAILURES" ]; then
    exit 0
fi

echo 0 > "$VPN_FAIL_FILE"

# ============================================================
# 7. VPN REPARIEREN
# ============================================================

log "VPN mehrfach ausgefallen. Starte XRay und TUN kontrolliert neu."

restart_vpn

# ============================================================
# 8. NACH REPARATUR WAN ERNEUT PRÜFEN
# ============================================================

WAN_IP="$(direct_ip || true)"

if ! valid_ip "$WAN_IP"; then

    log "Nach VPN-Reparatur kein stabiler WAN-Test möglich. KEIN Reboot."

    # Nicht sofort DOWN markieren.
    # Der normale WAN-Zähler entscheidet das in den nächsten Läufen.

    exit 0
fi

# ============================================================
# 9. VPN NACH REPARATUR TESTEN
# ============================================================

VPN_TEST_IP=""

if vpn_healthy; then

    log "REPARATUR ERFOLGREICH: WAN=$WAN_IP VPN=$VPN_TEST_IP"

    echo 0 > "$RECOVERY_FILE"

    exit 0
fi

# ============================================================
# 10. REPARATUR FEHLGESCHLAGEN
# ============================================================

RECOVERIES="$(read_number "$RECOVERY_FILE")"

RECOVERIES=$((RECOVERIES + 1))

echo "$RECOVERIES" > "$RECOVERY_FILE"

log "REPARATUR FEHLGESCHLAGEN $RECOVERIES/$MAX_RECOVERY_FAILURES: WAN=$WAN_IP VPN=$VPN_TEST_IP"

# Der separate Failover-Timer bekommt dadurch Zeit,
# auf das andere VLESS-Profil umzuschalten.

if [ "$RECOVERIES" -lt "$MAX_RECOVERY_FAILURES" ]; then
    exit 0
fi

# ============================================================
# 11. LETZTE SICHERHEITSPRÜFUNG VOR REBOOT
# ============================================================

sleep 5

# WAN zweimal prüfen, bevor ein Reboot überhaupt erlaubt wird.

WAN_CHECK1="$(direct_ip || true)"

sleep 5

WAN_CHECK2="$(direct_ip || true)"

if ! valid_ip "$WAN_CHECK1" ||
   ! valid_ip "$WAN_CHECK2"
then

    log "REBOOT ABGEBROCHEN: WAN nicht zweimal stabil bestaetigt."

    echo 0 > "$RECOVERY_FILE"

    exit 0
fi

WAN_IP="$WAN_CHECK2"

VPN_TEST_IP=""

if vpn_healthy; then

    log "REBOOT NICHT NOETIG: VPN hat sich noch erholt. VPN=$VPN_TEST_IP"

    echo 0 > "$RECOVERY_FILE"

    exit 0
fi

# ============================================================
# 12. LETZTE ESKALATIONSSTUFE
# ============================================================

log "KRITISCH: WAN mehrfach stabil bestaetigt, VPN trotz mehrerer Reparaturen weiterhin tot. Raspberry wird neu gestartet."

sync

sleep 3

if [[ $(cat /var/lib/tkgateway-triplewan-live/manual) == AUTO ]]; then systemctl reboot; else log "Manueller WAN-Modus: Reboot unterdrueckt"; fi

exit 0

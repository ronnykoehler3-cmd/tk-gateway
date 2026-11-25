#!/bin/bash
source "$(dirname "${BASH_SOURCE[0]}")/tkgateway-wan-config.sh"
set -euo pipefail

STATE="/var/lib/tkgateway-triplewan-live"
mkdir -p "$STATE"

exec 9>"$STATE/lock"
flock -n 9 || exit 0

declare -A MAC=(
  [PRIMARY]="$(tk_config_get wan PRIMARY_MAC)"
  [BACKUP]="$(tk_config_get wan BACKUP_MAC)"
  [LTE]="$(tk_config_get wan LTE_MAC)"
)

declare -A TABLE=(
  [PRIMARY]=201
  [BACKUP]=202
  [LTE]=203
)

ROLES=(PRIMARY BACKUP LTE)

declare -A DEV IP GW HEALTH GOOD BAD

log() {
  echo "TK-TRIPLEWAN: $*"
}

find_dev() {
  local path mac
  for path in /sys/class/net/*; do
    [[ -f "$path/address" ]] || continue
    mac="$(<"$path/address")"
    if [[ "${mac,,}" == "${MAC[$1]}" ]]; then
      echo "${path##*/}"
      return 0
    fi
  done
  return 1
}

counter() {
  local value=0
  if [[ -f "$1" ]]; then
    read -r value < "$1" || true
  fi
  [[ "$value" =~ ^[0-9]+$ ]] || value=0
  echo "$value"
}

# DHCP-Adressen und Routingtabellen aktualisieren
for role in "${ROLES[@]}"; do
  dev="$(find_dev "$role" || true)"
  DEV[$role]="$dev"
  HEALTH[$role]=0

  [[ -n "$dev" ]] || continue

  cidr="$(ip -4 -o addr show dev "$dev" scope global |
    awk 'NR==1 {print $4}')"

  [[ -n "$cidr" ]] || continue

  ip="${cidr%%/*}"

  gw="$(ip -4 route show default dev "$dev" |
    awk '$1=="default" && $2=="via" {print $3; exit}')"

  if [[ -z "$gw" ]]; then
    gw="$(nmcli -g IP4.GATEWAY device show "$dev" 2>/dev/null |
      head -n 1)"
  fi

  [[ -n "$gw" ]] || continue

  IP[$role]="$ip"
  GW[$role]="$gw"

  table="${TABLE[$role]}"

  # Nur eigene Routingtabellen 201-203 verwalten
  network="$(ip -4 route show dev "$dev" scope link |
    awk '$1!="default" && $1 ~ /\// {print $1; exit}')"

  if [[ -n "$network" ]]; then
    ip -4 route replace "$network" dev "$dev" \
      scope link src "$ip" table "$table" || true
  fi

  ip -4 route replace default via "$gw" dev "$dev" \
    src "$ip" table "$table" || continue

  # Regeln für diese Priorität nur ändern, wenn sie
  # zu unserer eigenen Routingtabelle gehören.
  existing="$(ip -4 rule show |
    awk -v p="${table}:" '$1==p {print}')"

  if [[ -n "$existing" ]]; then
    if ! grep -Eq "lookup (${table}|tk_wan_eth0|tk_wan_eth2|tk_wan_eth3)( |$)" \
      <<< "$existing"; then
      log "Fremde Regel auf Prioritaet $table - Abbruch"
      exit 1
    fi
  fi

  if ! grep -Eq "from ${ip}(/32)? lookup " <<< "$existing"; then
    while ip -4 rule show |
      awk -v p="${table}:" '$1==p {found=1} END {exit !found}'; do
      ip -4 rule del pref "$table"
    done

    ip -4 rule add pref "$table" from "$ip/32" table "$table"
  fi

  # Unabhängiger Internettest über die Quell-IP
  result="$(curl -4 --interface "$ip" --noproxy '*' \
    --connect-timeout 3 --max-time 8 \
    -fsS https://api.ipify.org 2>/dev/null || true)"

  if [[ "$result" =~ ^([0-9]{1,3}\.){3}[0-9]{1,3}$ ]]; then
    HEALTH[$role]=1
  fi
done

# Statuszähler aktualisieren
for role in "${ROLES[@]}"; do
  good="$(counter "$STATE/$role.good")"
  bad="$(counter "$STATE/$role.bad")"

  if [[ "${HEALTH[$role]}" == 1 ]]; then
    good=$((good+1))
    bad=0
  else
    bad=$((bad+1))
    good=0
  fi

  ((good > 5)) && good=5
  ((bad > 3)) && bad=3

  GOOD[$role]="$good"
  BAD[$role]="$bad"

  echo "$good" > "$STATE/$role.good"
  echo "$bad" > "$STATE/$role.bad"
done

active="PRIMARY"
if [[ -f "$STATE/active" ]]; then
  read -r active < "$STATE/active" || true
fi

case "$active" in
  PRIMARY|BACKUP|LTE) ;;
  *) active="PRIMARY" ;;
esac

mode=$(cat "$STATE/manual")
if [[ "$mode" != AUTO ]]; then
  /usr/local/sbin/tkgateway-wan-guard
  echo "$mode" > "$STATE/active"
  log "MANUELL=$mode HEALTH=${HEALTH[$mode]}"
  exit 0
fi
# Reconcile actual main-route role after leaving manual mode.
actual=$(ip -4 route get 1.1.1.1 | awk '{for(i=1;i<NF;i++) if($i=="dev") {print $(i+1); exit}}')
for role in "${ROLES[@]}"; do
  if [[ -n "${DEV[$role]}" && "${DEV[$role]}" == "$actual" ]]; then active="$role"; break; fi
done
echo "$active" > "$STATE/active"
selected="$active"

# Rückschaltung: fünf erfolgreiche Prüfungen
case "$active" in
  BACKUP)
    if (( GOOD[PRIMARY] >= 5 )); then
      selected=PRIMARY
    fi
    ;;
  LTE)
    if (( GOOD[PRIMARY] >= 5 )); then
      selected=PRIMARY
    elif (( GOOD[BACKUP] >= 5 )); then
      selected=BACKUP
    fi
    ;;
esac

# Ausfall: drei fehlgeschlagene Prüfungen
if [[ "$selected" == "$active" ]] &&
   (( BAD[$active] >= 3 )); then

  for role in "${ROLES[@]}"; do
    if [[ "${HEALTH[$role]}" == 1 ]]; then
      selected="$role"
      break
    fi
  done
fi

# Bereits aktive, selbst verwaltete Route ermitteln
old=""
if [[ -f "$STATE/managed-route" ]]; then
  old="$(cat "$STATE/managed-route")"
fi

# Nur bei tatsächlichem Wechsel die Standardroute ändern
if [[ "$selected" != "$active" ]]; then

  if [[ "$selected" == PRIMARY ]]; then
    # PRIMARY besitzt bereits die NetworkManager-Route metric 100.
    # Zuerst sicherstellen, dass diese vorhanden ist.
    if ! ip -4 route show default |
      grep -q "dev ${DEV[PRIMARY]} .*metric 100"; then
      log "PRIMARY-Standardroute fehlt - Wechsel abgebrochen"
      exit 1
    fi

    if [[ -n "$old" ]]; then
      read -r oldgw olddev oldip <<< "$old"
      ip -4 route del default via "$oldgw" \
        dev "$olddev" metric 40 || true
      rm -f "$STATE/managed-route"
    fi
  else
    newgw="${GW[$selected]:-}"
    newdev="${DEV[$selected]:-}"
    newip="${IP[$selected]:-}"

    [[ -n "$newgw" && -n "$newdev" && -n "$newip" ]] ||
      exit 1

    # Neue Route setzen, NetworkManager-Routen behalten
    ip -4 route replace default via "$newgw" \
      dev "$newdev" src "$newip" metric 40

    if [[ -n "$old" ]]; then
      read -r oldgw olddev oldip <<< "$old"
      if [[ "$olddev" != "$newdev" ||
            "$oldgw" != "$newgw" ]]; then
        ip -4 route del default via "$oldgw" \
          dev "$olddev" metric 40 2>/dev/null || true
      fi
    fi

    echo "$newgw $newdev $newip" > "$STATE/managed-route"
  fi

  echo "$selected" > "$STATE/active"
  log "WAN-WECHSEL: $active -> $selected"

  # SSH-Verbindung darf ihren automatischen Wiederaufbau
  # selbst durchführen. Kein erzwungener Dienstneustart.
fi

log "Aktiv=$selected PRIMARY=${HEALTH[PRIMARY]} BACKUP=${HEALTH[BACKUP]} LTE=${HEALTH[LTE]}"

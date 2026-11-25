#!/bin/bash
set -Eeuo pipefail
umask 077

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
MANIFEST="$ROOT/config/triplewan-install.manifest"
CONFIG="/etc/tkgateway/triplewan.conf"
BACKUP_ROOT="/var/backups/tkgateway-triplewan"

check() {
    echo "===== VORPRUEFUNG ====="

    for cmd in php bash nft visudo install systemctl; do
        command -v "$cmd" >/dev/null
    done

    php "$ROOT/scripts/tkgateway-wan-validate.php" "$CONFIG"

    while read -r mode src dst; do
        test -f "$ROOT/$src"
        case "$src" in
            *.php)
                php -l "$ROOT/$src"
                ;;
            scripts/*)
                bash -n "$ROOT/$src"
                ;;
            config/sudoers/*)
                visudo -cf "$ROOT/$src"
                ;;
            *.nft)
                nft -c -f "$ROOT/$src"
                ;;
        esac
        echo "OK: $src"
    done < "$MANIFEST"

    echo "===== VORPRUEFUNG BESTANDEN ====="
}

backup() {
    B="$BACKUP_ROOT/$(date +%Y%m%d-%H%M%S)-$$"
    install -d -m 700 "$B/files"
    cp "$MANIFEST" "$B/manifest"

    while read -r mode src dst; do
        if [[ -e "$dst" || -L "$dst" ]]; then
            cp -a --parents "$dst" "$B/files/"
        else
            printf '%s\n' "$dst" >> "$B/missing"
        fi
    done < "$MANIFEST"

    cp -a "$CONFIG" "$B/triplewan.conf"
    ip -4 rule show > "$B/ip-rules.txt"
    ip -4 route show table all > "$B/ip-routes.txt"
    nft list ruleset > "$B/nftables.txt"

    echo "Sicherung: $B"
}

case "${1:---check}" in
    --check)
        check
        ;;
    --backup)
        check
        backup
        ;;
    --install|--rollback)
        echo "Noch nicht freigegeben."
        echo "Produktive Dienste bleiben unveraendert."
        exit 1
        ;;
    *)
        echo "Verwendung: $0 --check|--backup"
        exit 1
        ;;
esac

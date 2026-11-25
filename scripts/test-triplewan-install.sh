#!/bin/bash
set -Eeuo pipefail
umask 077

ROOT=/opt/tk-gateway
TARGET=/var/lib/tkgateway-install-test
BACKUP=$(mktemp -d /var/lib/tkgateway-auto-rollback.XXXXXX)
MANIFEST="$ROOT/config/triplewan-install.manifest"

source "$ROOT/scripts/tkgateway-install-rollback.sh"

echo "===== TESTSICHERUNG ====="

mkdir -p "$BACKUP/files"
cp "$MANIFEST" "$BACKUP/manifest"

while read -r mode src dst; do
    if [[ -f "$TARGET$dst" && ! -L "$TARGET$dst" ]]; then
        mkdir -p "$BACKUP/files$(dirname "$dst")"
        cp -a "$TARGET$dst" "$BACKUP/files$dst"
    elif [[ ! -e "$TARGET$dst" && ! -L "$TARGET$dst" ]]; then
        echo "$dst" >> "$BACKUP/missing"
    else
        echo "Ungueltige Testdatei: $dst"
        exit 1
    fi
done < "$MANIFEST"

echo "Sicherung: $BACKUP"

rollback_on_error() {
    local rc=$?
    trap - ERR

    echo "===== INSTALLATIONSFEHLER ====="
    echo "Fehlercode: $rc"
    echo "===== AUTOMATISCHER ROLLBACK ====="

    if (set -e; restore_files "$BACKUP" "$TARGET"); then
        echo "OK: Automatischer Rollback abgeschlossen"
        exit "$rc"
    else
        echo "FEHLER: Rollback fehlgeschlagen"
        exit 99
    fi
}

trap rollback_on_error ERR

echo "===== TESTINSTALLATION ====="

count=0

while read -r mode src dst; do
    install -D -o root -g root -m "$mode" \
        "$ROOT/$src" "$TARGET$dst"

    count=$((count + 1))
    echo "INSTALLIERT: $dst"

    if [[ "$count" -eq 5 ]]; then
        echo "Simulierter Installationsfehler"
        echo "DEFEKT" > "$TARGET$dst"
        false
    fi
done < "$MANIFEST"

trap - ERR

echo "===== INSTALLATION ABGESCHLOSSEN ====="

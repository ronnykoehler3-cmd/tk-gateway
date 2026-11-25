#!/bin/bash
set -Eeuo pipefail

restore_files() {
    local backup="$1"
    local target="$2"
    local mode src dst file saved

    [[ -f "$backup/manifest" ]] || return 1
    [[ -d "$backup/files" ]] || return 1

    # Vorerst ausschliesslich Testinstallation erlauben.
    [[ "$target" == "/var/lib/tkgateway-install-test" ]] || return 1

    while read -r mode src dst; do
        case "$dst" in
            /var/www/tkgateway/*|/usr/local/sbin/tkgateway-*|/etc/systemd/system/*|/etc/sudoers.d/tkgateway-*|/etc/tkgateway/*)
                ;;
            *)
                echo "Ungueltiger Zielpfad: $dst"
                return 1
                ;;
        esac

        [[ "$dst" != *"/../"* && "$dst" != */.. ]] || return 1

        file="$target$dst"
        saved="$backup/files$dst"

        if [[ -f "$saved" && ! -L "$saved" ]]; then
            install -d "$(dirname "$file")"
            cp -a --remove-destination "$saved" "$file"
            echo "WIEDERHERGESTELLT: $dst"
        elif [[ -f "$backup/missing" ]] && grep -Fxq -- "$dst" "$backup/missing"; then
            rm -f -- "$file"
            echo "ENTFERNT: $dst"
        else
            echo "Sicherung unvollstaendig: $dst"
            return 1
        fi
    done < "$backup/manifest"
}

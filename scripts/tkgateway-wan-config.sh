#!/bin/bash

TK_CONFIG="${TK_CONFIG:-/etc/tkgateway/triplewan.conf}"

tk_config_get() {
    local section="$1"
    local key="$2"

    awk -v section="$section" -v key="$key" '
        /^[[:space:]]*[#;]/ {next}
        /^\[/ {
            current=$0
            sub(/^\[/,"",current)
            sub(/\][[:space:]]*$/,"",current)
            next
        }
        current==section {
            pos=index($0,"=")
            if (!pos) next
            name=substr($0,1,pos-1)
            gsub(/^[[:space:]]+|[[:space:]]+$/,"",name)
            if (name==key) {
                value=substr($0,pos+1)
                gsub(/^[[:space:]]+|[[:space:]]+$/,"",value)
                print value
                found=1
                exit
            }
        }
        END {
            if (!found) exit 1
        }
    ' "$TK_CONFIG"
}

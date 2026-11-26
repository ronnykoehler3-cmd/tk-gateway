#!/usr/bin/env bash
set -Eeuo pipefail
REPO="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TEST_ROOT="$(mktemp -d)"
trap 'rm -rf "$TEST_ROOT"' EXIT
BIN="$TEST_ROOT/bin"
mkdir -p "$BIN" "$TEST_ROOT/var/www/tkgateway/includes" "$TEST_ROOT/var/www/tkgateway/api" \
  "$TEST_ROOT/var/www/tkgateway/app/classes" "$TEST_ROOT/var/www/tkgateway/assets/js" \
  "$TEST_ROOT/var/www/tkgateway/assets/css" "$TEST_ROOT/var/log/tkgateway" "$TEST_ROOT/etc/tkgateway"

cat > "$TEST_ROOT/var/www/tkgateway/includes/header.php" <<'PHP'
<?php /* lokaler produktiver Menüinhalt muss erhalten bleiben */ ?>
<ul>
  <li><a href="/events.php">Ereignisse</a></li>
  <li><a href="/maintenance.php">Wartung</a></li>
</ul>
PHP
printf 'bestehende Meldung 1\nbestehende Meldung 2\n' > "$TEST_ROOT/var/log/tkgateway/fritzbox.log"
printf "<?php\nreturn 'existing-password-hash';\n" > "$TEST_ROOT/etc/tkgateway/syslog-auth.php"
printf '<?php // alte Anmeldung\n' > "$TEST_ROOT/var/www/tkgateway/syslog-login.php"
printf '<?php // alter Logout\n' > "$TEST_ROOT/var/www/tkgateway/syslog-logout.php"
printf '<?php // alter Authenticator\n' > "$TEST_ROOT/var/www/tkgateway/app/classes/SyslogAuth.php"

cat > "$BIN/install" <<'SH'
#!/usr/bin/env bash
args=()
while (($#)); do
  case "$1" in
    -o|-g) shift 2 ;;
    *) args+=("$1"); shift ;;
  esac
done
exec /usr/bin/install "${args[@]}"
SH
chmod +x "$BIN/install"
for tool in chown chgrp visudo rsyslogd systemctl logrotate sudo journalctl; do
  cat > "$BIN/$tool" <<'SH'
#!/usr/bin/env bash
exit 0
SH
  chmod +x "$BIN/$tool"
done
cat > "$BIN/php" <<'SH'
#!/usr/bin/env bash
if [[ "${1:-}" == -l ]]; then exit 0; fi
exit 0
SH
chmod +x "$BIN/php"

export PATH="$BIN:$PATH"
export TK_SYSLOG_TEST_ROOT="$TEST_ROOT"
"$REPO/scripts/install-syslog.sh" --install > "$TEST_ROOT/install1.out"
BACKUP="$(sed -n 's/^Sicherung erstellt: //p' "$TEST_ROOT/install1.out")"
[[ -d "$BACKUP" ]]
[[ -f "$TEST_ROOT/var/www/tkgateway/syslog.php" ]]
[[ ! -e "$TEST_ROOT/var/www/tkgateway/syslog-login.php" ]]
[[ ! -e "$TEST_ROOT/var/www/tkgateway/syslog-logout.php" ]]
[[ ! -e "$TEST_ROOT/var/www/tkgateway/app/classes/SyslogAuth.php" ]]
[[ ! -e "$TEST_ROOT/etc/tkgateway/syslog-auth.php" ]]
[[ "$(grep -c 'href="/syslog.php"' "$TEST_ROOT/var/www/tkgateway/includes/header.php")" -eq 1 ]]

"$REPO/scripts/install-syslog.sh" --install > "$TEST_ROOT/install2.out"
[[ "$(grep -c 'href="/syslog.php"' "$TEST_ROOT/var/www/tkgateway/includes/header.php")" -eq 1 ]]
grep -q 'lokaler produktiver Menüinhalt' "$TEST_ROOT/var/www/tkgateway/includes/header.php"
grep -q 'bestehende Meldung 2' "$TEST_ROOT/var/log/tkgateway/fritzbox.log"
grep -q 'rotate 30' "$TEST_ROOT/etc/logrotate.d/tk-gateway-syslog"
grep -q 'size 10M' "$TEST_ROOT/etc/logrotate.d/tk-gateway-syslog"
[[ ! -e "$TEST_ROOT/etc/rsyslog.d/60-tkgateway-syslog.conf" ]]

"$REPO/scripts/install-syslog.sh" --rollback "$BACKUP" > "$TEST_ROOT/rollback.out"
[[ ! -e "$TEST_ROOT/var/www/tkgateway/syslog.php" ]]
[[ "$(grep -c 'href="/syslog.php"' "$TEST_ROOT/var/www/tkgateway/includes/header.php" || true)" -eq 0 ]]
grep -q 'bestehende Meldung 2' "$TEST_ROOT/var/log/tkgateway/fritzbox.log"
[[ -f "$TEST_ROOT/var/www/tkgateway/syslog-login.php" ]]
[[ -f "$TEST_ROOT/var/www/tkgateway/app/classes/SyslogAuth.php" ]]
[[ -f "$TEST_ROOT/etc/tkgateway/syslog-auth.php" ]]
echo 'OK: Installation wiederholbar, Menü gezielt ergänzt, vorhandene Syslog-Meldungen erhalten, Rollback stellt Stand wieder her.'

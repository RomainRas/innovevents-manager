#!/bin/sh
set -eu
umask 077
config=$(mktemp /tmp/innovevents-dump.XXXXXX)
trap 'rm -f "$config"' EXIT HUP INT TERM
cat /run/secrets/mysql_client > "$config"
mysqldump --defaults-extra-file="$config" --single-transaction --skip-comments --skip-dump-date --set-gtid-purged=OFF --no-tablespaces --skip-add-drop-table --hex-blob --order-by-primary "$@"

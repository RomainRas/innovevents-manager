#!/bin/sh
# Les secrets montés depuis Windows peuvent apparaître en mode 0777.
# MySQL refuse alors le fichier : copie privée et suppression à la sortie.
set -eu
umask 077
config=$(mktemp /tmp/innovevents-client.XXXXXX)
trap 'rm -f "$config"' EXIT HUP INT TERM
cat /run/secrets/mysql_client > "$config"
mysql --defaults-extra-file="$config" "$@"

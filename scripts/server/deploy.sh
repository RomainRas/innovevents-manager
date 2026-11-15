#!/bin/sh
# Déploiement sur le VPS, depuis le dossier de l'application (/opt/innovevents/app).
# Idempotent : premier lancement (secrets, bases, démonstration) puis mises à jour.
# Aucun secret n'est affiché ; .env et .secrets restent sur le serveur, hors Git.
set -eu
cd "$(dirname "$0")/../.."
[ -f .env ] || { echo "Fichier .env absent : copier scripts/server/env.example vers .env et le compléter." >&2; exit 1; }
set -a; . ./.env; set +a
export COMPOSE_FILE=compose.yaml:compose.prod.yaml
export PHP_BIN="$PWD/scripts/server/php"

# Secrets générés sur le serveur (jamais transmis) : bases racines et client MySQL.
mkdir -p .secrets && chmod 700 .secrets
umask 022
# Tous les secrets aléatoires (64 caractères hexadécimaux) : les scripts de provisionnement les réutilisent.
for name in mysql-root mongo-root mysql-api mongo-api mongo-auth mysql-accounts mysql-mail mail-key mysql-quotes mysql-settings \
  mysql-prospects-admin mysql-conversion mysql-journal mysql-events mysql-event-images mysql-quote-drafts mysql-quote-pdf \
  mysql-quote-send mysql-client-quotes mysql-quote-decision mysql-client-space mysql-reviews mysql-admin mysql-team mysql-records; do
  [ -s ".secrets/$name.txt" ] || openssl rand -hex 32 > ".secrets/$name.txt"
done
[ -f .secrets/mysql-client.cnf ] || printf '[client]\nuser=root\npassword=%s\nhost=127.0.0.1\nprotocol=TCP\n' "$(cat .secrets/mysql-root.txt)" > .secrets/mysql-client.cnf
[ -f .secrets/smtp-password.txt ] || : > .secrets/smtp-password.txt

echo "1/5 Construction des images"
docker compose build --pull api mobile caddy

echo "2/5 Démarrage des bases"
docker compose up -d --wait --wait-timeout 300 mysql mongodb

echo "3/5 Démonstration initiale (une seule fois)"
if [ "$(docker compose exec -T mysql sh /opt/innovevents/client.sh --batch --skip-column-names -e "SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name='innovevents_demo'")" = "0" ]; then
  python3 -X utf8 scripts/prepare_demo_backup.py
fi

echo "4/5 Migrations et comptes limités"
for script in provision-api-accounts provision-mail provision-quotes provision-prospects provision-conversion provision-events provision-event-images provision-quote-drafts provision-quote-pdf provision-quote-send provision-quote-decisions provision-public-pages provision-client-space provision-admin-accounts provision-team-space provision-records; do
  python3 -X utf8 "scripts/$script.py"
done

echo "5/5 Démarrage de l'application"
docker compose up -d --wait --wait-timeout 180 --remove-orphans api mail_worker journal_worker mailpit mobile caddy
docker image prune -f >/dev/null
echo "Application démarrée : https://$SITE_DOMAIN"

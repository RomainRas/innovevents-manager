"""Compte limité pour la lecture et la qualification négative des prospects."""

from pathlib import Path
import os, secrets, subprocess

# Racine du dépôt (les commandes docker compose s'y exécutent).
ROOT = Path(__file__).resolve().parents[1]
# Exécutable Docker (modifiable par la variable DOCKER_BIN).
DOCKER = os.environ.get('DOCKER_BIN', 'docker')


# Exécute une requête SQL dans le conteneur MySQL et renvoie le texte du résultat.
def sql(query):
    p = subprocess.run(
        [
            DOCKER,
            'compose',
            'exec',
            '-T',
            'mysql',
            'sh',
            '/opt/innovevents/client.sh',
            '--batch',
            '--skip-column-names',
            'innovevents_demo',
        ],
        input=query,
        encoding='utf-8',
        capture_output=True,
        cwd=ROOT,
    )
    if p.returncode:
        raise SystemExit('Provisionnement prospects interrompu ; aucune suppression automatique.')
    return p.stdout.strip()


clause = sql(
    "SELECT CHECK_CLAUSE FROM information_schema.check_constraints WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME='ck_mail_type';"
)
if 'demande_devis' not in clause:
    raise SystemExit('Appliquer la migration des demandes avant celle des prospects.')
if 'qualification_echec' not in clause:
    sql((ROOT / 'database/mysql/005_prospect_qualification.sql').read_text(encoding='utf-8'))
path = ROOT / '.secrets/mysql-prospects-admin.txt'
if not path.exists():
    with path.open('x', encoding='utf-8') as f:
        f.write(secrets.token_hex(32))
password = path.read_text().strip()
if len(password) != 64 or any(c not in '0123456789abcdef' for c in password):
    raise SystemExit('Secret invalide')
sql(
    "CREATE USER IF NOT EXISTS 'ecf_prospects_admin'@'%' IDENTIFIED BY '"
    + password
    + "'; GRANT SELECT ON innovevents_demo.prospects TO 'ecf_prospects_admin'@'%'; GRANT UPDATE (statut,message_echec,qualifie_le) ON innovevents_demo.prospects TO 'ecf_prospects_admin'@'%'; GRANT INSERT ON innovevents_demo.courriels_a_envoyer TO 'ecf_prospects_admin'@'%';"
)
print('Lecture et qualification des prospects prêtes. Aucun secret affiché.')

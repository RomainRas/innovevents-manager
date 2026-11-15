"""Conversion et livraison du journal, comptes limités et migration additive."""

from pathlib import Path
import os, secrets, subprocess

# Racine du dépôt (les commandes docker compose s'y exécutent).
ROOT = Path(__file__).resolve().parents[1]
# Exécutable Docker (modifiable par la variable DOCKER_BIN).
DOCKER = os.environ.get('DOCKER_BIN', 'docker')


# Exécute une requête SQL dans le conteneur MySQL et renvoie le texte du résultat.
def sql(query):
    r = subprocess.run(
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
    if r.returncode:
        raise SystemExit('Provisionnement conversion interrompu ; examiner sans supprimer de données.')
    return r.stdout.strip()


# Lit un secret local (.secrets/), jamais affiché.
def secret(name):
    p = ROOT / '.secrets' / name
    if not p.exists():
        with p.open('x', encoding='utf-8') as f:
            f.write(secrets.token_hex(32))
    value = p.read_text().strip()
    if len(value) != 64 or any(c not in '0123456789abcdef' for c in value):
        raise SystemExit('Secret invalide')
    return value


if (
    sql(
        "SELECT COUNT(*) FROM information_schema.columns WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='prospects' AND COLUMN_NAME='conversion_empreinte';"
    )
    == '0'
):
    sql((ROOT / 'database/mysql/006_prospect_conversion.sql').read_text(encoding='utf-8'))
c = secret('mysql-conversion.txt')
j = secret('mysql-journal.txt')
sql(
    "CREATE USER IF NOT EXISTS 'ecf_conversion'@'%' IDENTIFIED BY '"
    + c
    + "'; GRANT SELECT ON innovevents_demo.prospects TO 'ecf_conversion'@'%'; GRANT UPDATE (statut,client_id,qualifie_le,conversion_empreinte) ON innovevents_demo.prospects TO 'ecf_conversion'@'%'; GRANT SELECT ON innovevents_demo.clients TO 'ecf_conversion'@'%'; GRANT INSERT (entreprise,contact_nom,contact_prenom,email,telephone) ON innovevents_demo.clients TO 'ecf_conversion'@'%'; GRANT INSERT (id_trace,horodatage,type_action,id_utilisateur,details) ON innovevents_demo.journal_a_emettre TO 'ecf_conversion'@'%';"
)
sql(
    "CREATE USER IF NOT EXISTS 'ecf_journal'@'%' IDENTIFIED BY '"
    + j
    + "'; GRANT SELECT ON innovevents_demo.journal_a_emettre TO 'ecf_journal'@'%'; GRANT UPDATE (etat,tentatives,prochain_essai_le,livre_le,derniere_erreur) ON innovevents_demo.journal_a_emettre TO 'ecf_journal'@'%';"
)
print('Conversion et livraison du journal préparées ; aucun secret affiché.')

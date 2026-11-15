"""Migration 010 et compte limité : réponse du client au devis et suivi administrateur."""

from pathlib import Path
import os, secrets, subprocess

# Racine du dépôt (les commandes docker compose s'y exécutent).
ROOT = Path(__file__).resolve().parents[1]
# Exécutable Docker (modifiable par la variable DOCKER_BIN).
DOCKER = os.environ.get('DOCKER_BIN', 'docker')


# Exécute une requête SQL dans le conteneur MySQL et renvoie le texte du résultat.
def sql(q):
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
        input=q,
        encoding='utf-8',
        capture_output=True,
        cwd=ROOT,
    )
    if r.returncode:
        raise SystemExit('Provisionnement décisions interrompu. Aucune suppression automatique ; examiner la base.')
    return r.stdout.strip()


p = ROOT / '.secrets/mysql-quote-decision.txt'
if not p.exists():
    with p.open('x', encoding='utf-8') as f:
        f.write(secrets.token_hex(32))
password = p.read_text(encoding='utf-8').strip()
if len(password) != 64 or any(c not in '0123456789abcdef' for c in password):
    raise SystemExit('Secret invalide')
if (
    sql(
        "SELECT COUNT(*) FROM information_schema.columns WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='decisions_devis' AND COLUMN_NAME='cle_idempotence';"
    )
    == '0'
):
    sql((ROOT / 'database/mysql/010_quote_decisions.sql').read_text(encoding='utf-8'))
q = "CREATE USER IF NOT EXISTS 'ecf_quote_decision'@'%' IDENTIFIED BY '" + password + "';"
for t in ['clients', 'evenements', 'devis', 'versions_devis', 'decisions_devis']:
    q += f" GRANT SELECT ON innovevents_demo.{t} TO 'ecf_quote_decision'@'%';"
q += (
    " GRANT INSERT (version_id,auteur_id,choix,motif,cle_idempotence) ON innovevents_demo.decisions_devis TO 'ecf_quote_decision'@'%';"
    " GRANT UPDATE (statut) ON innovevents_demo.versions_devis TO 'ecf_quote_decision'@'%';"
    " GRANT UPDATE (statut,revision) ON innovevents_demo.evenements TO 'ecf_quote_decision'@'%';"
    " GRANT INSERT (id_trace,horodatage,type_action,id_utilisateur,details) ON innovevents_demo.journal_a_emettre TO 'ecf_quote_decision'@'%';"
    " GRANT INSERT ON innovevents_demo.courriels_a_envoyer TO 'ecf_quote_decision'@'%';"
    # Suivi administrateur et historique des réponses depuis la transmission ; lecture seule des décisions.
    " GRANT SELECT ON innovevents_demo.decisions_devis TO 'ecf_quote_send'@'%';"
    " GRANT SELECT ON innovevents_demo.decisions_devis TO 'ecf_client_quotes'@'%';"
    " GRANT SELECT ON innovevents_demo.decisions_devis TO 'ecf_quote_drafts'@'%';"
)
sql(q)
print('Décisions : migration et compte limité prêts. Aucun secret affiché.')

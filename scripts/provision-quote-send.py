"""Migration 009 et comptes limités : transmission du devis et consultation client."""

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
        raise SystemExit('Provisionnement transmission interrompu. Aucune suppression automatique ; examiner la base.')
    return r.stdout.strip()


# Lit un secret local (.secrets/), jamais affiché.
def secret(name):
    p = ROOT / '.secrets' / name
    if not p.exists():
        with p.open('x', encoding='utf-8') as f:
            f.write(secrets.token_hex(32))
    value = p.read_text(encoding='utf-8').strip()
    if len(value) != 64 or any(c not in '0123456789abcdef' for c in value):
        raise SystemExit('Secret invalide')
    return value


if (
    sql(
        "SELECT COUNT(*) FROM information_schema.columns WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transmissions_devis' AND COLUMN_NAME='courriel_id';"
    )
    == '0'
):
    sql((ROOT / 'database/mysql/009_quote_transmission.sql').read_text(encoding='utf-8'))
send, client = secret('mysql-quote-send.txt'), secret('mysql-client-quotes.txt')
q = "CREATE USER IF NOT EXISTS 'ecf_quote_send'@'%' IDENTIFIED BY '" + send + "';"
for t in [
    'evenements',
    'clients',
    'devis',
    'versions_devis',
    'prestations_devis',
    'documents_devis',
    'transmissions_devis',
]:
    q += f" GRANT SELECT ON innovevents_demo.{t} TO 'ecf_quote_send'@'%';"
q += (
    " GRANT SELECT (id,email_normalise,role) ON innovevents_demo.utilisateurs TO 'ecf_quote_send'@'%';"
    " GRANT INSERT (email,email_normalise,prenom,nom,nom_utilisateur,mot_de_passe_hash,role,changement_mdp_requis) ON innovevents_demo.utilisateurs TO 'ecf_quote_send'@'%';"
    " GRANT UPDATE (utilisateur_id) ON innovevents_demo.clients TO 'ecf_quote_send'@'%';"
    " GRANT UPDATE (statut,figee_le,transmise_le) ON innovevents_demo.versions_devis TO 'ecf_quote_send'@'%';"
    " GRANT INSERT (document_id,acteur_id,mode,destinataire,etat,cle_idempotence,confirmee_le,courriel_id) ON innovevents_demo.transmissions_devis TO 'ecf_quote_send'@'%';"
    " GRANT INSERT ON innovevents_demo.courriels_a_envoyer TO 'ecf_quote_send'@'%';"
)
q += " CREATE USER IF NOT EXISTS 'ecf_client_quotes'@'%' IDENTIFIED BY '" + client + "';"
for t in [
    'evenements',
    'clients',
    'devis',
    'versions_devis',
    'prestations_devis',
    'documents_devis',
    'transmissions_devis',
]:
    q += f" GRANT SELECT ON innovevents_demo.{t} TO 'ecf_client_quotes'@'%';"
q += " GRANT SELECT (id,etat),UPDATE (etat,confirmee_le) ON innovevents_demo.transmissions_devis TO 'ecf_mail'@'%'; GRANT SELECT (courriel_id) ON innovevents_demo.transmissions_devis TO 'ecf_mail'@'%';"
sql(q)
print('Transmission : migration et comptes limités prêts. Aucun secret affiché.')

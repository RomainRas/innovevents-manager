"""Migration 008 et compte MySQL limité à la génération des PDF de devis.
Le mot de passe est généré une fois dans .secrets/ et n'est jamais affiché."""

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
        raise SystemExit('Provisionnement PDF interrompu.')
    return r.stdout.strip()


if (
    sql(
        "SELECT COUNT(*) FROM information_schema.columns WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='documents_devis' AND COLUMN_NAME='source_empreinte';"
    )
    == '0'
):
    sql((ROOT / 'database/mysql/008_quote_pdf.sql').read_text())
p = ROOT / '.secrets/mysql-quote-pdf.txt'
if not p.exists():
    with p.open('x', encoding='utf-8') as f:
        f.write(secrets.token_hex(32))
password = p.read_text().strip()
if len(password) != 64 or any(c not in '0123456789abcdef' for c in password):
    raise SystemExit('Secret invalide')
q = "CREATE USER IF NOT EXISTS 'ecf_quote_pdf'@'%' IDENTIFIED BY '" + password + "';"
for t in ['evenements', 'clients', 'devis', 'versions_devis', 'prestations_devis', 'documents_devis']:
    q += f" GRANT SELECT ON innovevents_demo.{t} TO 'ecf_quote_pdf'@'%';"
q += " GRANT INSERT (version_id,revision_contenu,etat,fichier_reference,empreinte_sha256,source_empreinte,genere_le),UPDATE (etat) ON innovevents_demo.documents_devis TO 'ecf_quote_pdf'@'%'; GRANT UPDATE (client_snapshot,evenement_snapshot) ON innovevents_demo.versions_devis TO 'ecf_quote_pdf'@'%'; GRANT INSERT (id_trace,horodatage,type_action,id_utilisateur,details) ON innovevents_demo.journal_a_emettre TO 'ecf_quote_pdf'@'%';"
sql(q)
print('PDF : migration et compte limité prêts.')

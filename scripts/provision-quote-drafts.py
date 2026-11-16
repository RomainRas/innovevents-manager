"""Compte MySQL limité à la préparation des devis (événements, devis, versions, prestations, lecture du client).
Le mot de passe est généré une fois dans .secrets/ et n'est jamais affiché."""

from pathlib import Path
import os, secrets, subprocess

# Racine du dépôt (les commandes docker compose s'y exécutent).
ROOT = Path(__file__).resolve().parents[1]
p = ROOT / '.secrets/mysql-quote-drafts.txt'
if not p.exists():
    with p.open('x', encoding='utf-8') as f:
        f.write(secrets.token_hex(32))
password = p.read_text().strip()
if len(password) != 64 or any(c not in '0123456789abcdef' for c in password):
    raise SystemExit('Secret invalide')
q = "CREATE USER IF NOT EXISTS 'ecf_quote_drafts'@'%' IDENTIFIED BY '" + password + "';"
for table in ['evenements', 'devis', 'versions_devis', 'prestations_devis']:
    q += f" GRANT SELECT ON innovevents_demo.{table} TO 'ecf_quote_drafts'@'%';"
# Lecture de la fiche client (identité et coordonnées) pour afficher le client et ce qui manque avant le PDF.
q += " GRANT SELECT (id,entreprise,contact_nom,contact_prenom,email,telephone) ON innovevents_demo.clients TO 'ecf_quote_drafts'@'%';"
q += " GRANT INSERT (evenement_id,reference),UPDATE (version_courante_id) ON innovevents_demo.devis TO 'ecf_quote_drafts'@'%'; GRANT INSERT (devis_id,numero),UPDATE (revision_contenu,devise,taux_tva,regle_calcul,total_ht,total_tva,total_ttc) ON innovevents_demo.versions_devis TO 'ecf_quote_drafts'@'%'; GRANT INSERT (version_id,ordre,libelle,montant_ht),DELETE ON innovevents_demo.prestations_devis TO 'ecf_quote_drafts'@'%'; GRANT SELECT (version_id,etat),UPDATE (etat) ON innovevents_demo.documents_devis TO 'ecf_quote_drafts'@'%';"
r = subprocess.run(
    [
        os.environ.get('DOCKER_BIN', 'docker'),
        'compose',
        'exec',
        '-T',
        'mysql',
        'sh',
        '/opt/innovevents/client.sh',
        'innovevents_demo',
    ],
    input=q,
    encoding='utf-8',
    capture_output=True,
    cwd=ROOT,
)
if r.returncode:
    raise SystemExit('Provisionnement devis interrompu.')
print('Compte brouillons de devis prêt.')

"""Compte limité : gestion des fiches clients, des événements, de leur statut et des tâches par l'administratrice.
Aucune migration : les tables existent ; seuls les droits nécessaires sont accordés."""
from pathlib import Path
import os,secrets,subprocess
ROOT=Path(__file__).resolve().parents[1];DOCKER=os.environ.get('DOCKER_BIN','docker')
def sql(q):
 r=subprocess.run([DOCKER,'compose','exec','-T','mysql','sh','/opt/innovevents/client.sh','--batch','--skip-column-names','innovevents_demo'],input=q,encoding='utf-8',capture_output=True,cwd=ROOT)
 if r.returncode:raise SystemExit('Provisionnement gestion des fiches interrompu. Aucune suppression automatique ; examiner la base.')
 return r.stdout.strip()
p=ROOT/'.secrets/mysql-records.txt'
if not p.exists():
 with p.open('x',encoding='utf-8') as f:f.write(secrets.token_hex(32))
password=p.read_text(encoding='utf-8').strip()
if len(password)!=64 or any(c not in '0123456789abcdef' for c in password):raise SystemExit('Secret invalide')
D='innovevents_demo';U="'ecf_records'@'%'"
CLIENT_COLS='entreprise,contact_prenom,contact_nom,email,telephone,adresse_ligne1,adresse_ligne2,code_postal,ville,pays_code'
q=f"CREATE USER IF NOT EXISTS {U} IDENTIFIED BY '{password}';"
q+=f" GRANT SELECT, DELETE ON {D}.clients TO {U};"
q+=f" GRANT INSERT ({CLIENT_COLS}), UPDATE ({CLIENT_COLS}) ON {D}.clients TO {U};"
q+=f" GRANT SELECT, DELETE ON {D}.evenements TO {U};"
q+=f" GRANT INSERT (client_id,nom,debut,fin,fuseau,lieu,type_evenement,theme,participants_estimes) ON {D}.evenements TO {U};"
q+=f" GRANT UPDATE (nom,debut,fin,lieu,type_evenement,theme,participants_estimes,statut,revision) ON {D}.evenements TO {U};"
# Contrôles avant suppression : existence de demandes, de devis et de comptes.
q+=f" GRANT SELECT (id,client_id) ON {D}.prospects TO {U};"
q+=f" GRANT SELECT (id,evenement_id) ON {D}.devis TO {U};"
q+=f" GRANT SELECT (id,prenom,nom,role,statut,supprime_le) ON {D}.utilisateurs TO {U};"
# Tâches créées et supprimées ; notes, tâches et avis retirés avec un événement sans devis.
q+=f" GRANT SELECT, DELETE ON {D}.taches TO {U};"
q+=f" GRANT INSERT (evenement_id,assigne_a,titre,description) ON {D}.taches TO {U};"
q+=f" GRANT SELECT, DELETE ON {D}.notes_evenement TO {U};"
q+=f" GRANT SELECT, DELETE ON {D}.avis TO {U};"
q+=f" GRANT INSERT (id_trace,horodatage,type_action,id_utilisateur,details) ON {D}.journal_a_emettre TO {U};"
sql(q);print('Gestion des fiches : compte limité prêt. Aucun secret affiché.')

"""Migration 014 et compte limité : consultation des clients et événements, notes collaboratives et tâches de l'équipe."""
from pathlib import Path
import os,secrets,subprocess
ROOT=Path(__file__).resolve().parents[1];DOCKER=os.environ.get('DOCKER_BIN','docker')
def sql(q):
 r=subprocess.run([DOCKER,'compose','exec','-T','mysql','sh','/opt/innovevents/client.sh','--batch','--skip-column-names','innovevents_demo'],input=q,encoding='utf-8',capture_output=True,cwd=ROOT)
 if r.returncode:raise SystemExit('Provisionnement espace employé interrompu. Aucune suppression automatique ; examiner la base.')
 return r.stdout.strip()
p=ROOT/'.secrets/mysql-team.txt'
if not p.exists():
 with p.open('x',encoding='utf-8') as f:f.write(secrets.token_hex(32))
password=p.read_text(encoding='utf-8').strip()
if len(password)!=64 or any(c not in '0123456789abcdef' for c in password):raise SystemExit('Secret invalide')
# Démonstration idempotente : chaque tâche n'est insérée que si elle n'existe pas encore.
sql((ROOT/'database/mysql/014_team_space.sql').read_text(encoding='utf-8'))
# Adresse fictive du client de démonstration (action « Itinéraire » de l'application mobile), sans écraser une adresse existante.
sql((ROOT/'database/mysql/015_mobile_demo.sql').read_text(encoding='utf-8'))
D='innovevents_demo';U="'ecf_team'@'%'"
q=f"CREATE USER IF NOT EXISTS {U} IDENTIFIED BY '{password}';"
# Consultation : aucune écriture sur les clients ni les événements (« sans modifier les éléments structurels »).
q+=f" GRANT SELECT (id,utilisateur_id,entreprise,contact_nom,contact_prenom,email,telephone,adresse_ligne1,adresse_ligne2,code_postal,ville,pays_code,cree_le) ON {D}.clients TO {U};"
q+=f" GRANT SELECT (id,client_id,nom,debut,fin,fuseau,lieu,type_evenement,theme,participants_estimes,statut,cree_le) ON {D}.evenements TO {U};"
q+=f" GRANT SELECT (id,prenom,nom,role) ON {D}.utilisateurs TO {U};"
q+=f" GRANT SELECT, DELETE ON {D}.notes_evenement TO {U};"
q+=f" GRANT INSERT (evenement_id,auteur_id,contenu) ON {D}.notes_evenement TO {U};"
q+=f" GRANT UPDATE (contenu) ON {D}.notes_evenement TO {U};"
q+=f" GRANT SELECT ON {D}.taches TO {U};"
q+=f" GRANT UPDATE (statut) ON {D}.taches TO {U};"
sql(q);print('Espace employé : démonstration et compte limité prêts. Aucun secret affiché.')

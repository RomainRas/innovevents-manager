"""Brouillons d’événements : migration additive, permissions limitées."""
from pathlib import Path
import os,secrets,subprocess
ROOT=Path(__file__).resolve().parents[1];DOCKER=os.environ.get('DOCKER_BIN','docker')
def sql(q):
 r=subprocess.run([DOCKER,'compose','exec','-T','mysql','sh','/opt/innovevents/client.sh','--batch','--skip-column-names','innovevents_demo'],input=q,encoding='utf-8',capture_output=True,cwd=ROOT)
 if r.returncode:raise SystemExit('Provisionnement événements interrompu ; aucune suppression automatique.')
 return r.stdout.strip()
count=sql("SELECT COUNT(*) FROM information_schema.columns WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='evenements' AND COLUMN_NAME IN ('creation_empreinte','revision');")
if count=='0':sql((ROOT/'database/mysql/007_event_drafts.sql').read_text(encoding='utf-8'))
elif count!='2':raise SystemExit('Migration partielle : examen nécessaire.')
p=ROOT/'.secrets/mysql-events.txt'
if not p.exists():
 with p.open('x',encoding='utf-8') as f:f.write(secrets.token_hex(32))
password=p.read_text().strip()
if len(password)!=64 or any(c not in '0123456789abcdef' for c in password):raise SystemExit('Secret invalide')
sql("CREATE USER IF NOT EXISTS 'ecf_events'@'%' IDENTIFIED BY '"+password+"'; GRANT SELECT ON innovevents_demo.prospects TO 'ecf_events'@'%'; GRANT SELECT ON innovevents_demo.clients TO 'ecf_events'@'%'; GRANT SELECT ON innovevents_demo.evenements TO 'ecf_events'@'%'; GRANT INSERT (client_id,prospect_id,nom,debut,fin,fuseau,lieu,type_evenement,participants_estimes,creation_empreinte) ON innovevents_demo.evenements TO 'ecf_events'@'%'; GRANT UPDATE (nom,debut,fin,lieu,type_evenement,participants_estimes,revision) ON innovevents_demo.evenements TO 'ecf_events'@'%'; GRANT INSERT (id_trace,horodatage,type_action,id_utilisateur,details) ON innovevents_demo.journal_a_emettre TO 'ecf_events'@'%';")
print('Brouillons d’événements prêts. Aucun secret affiché.')

"""Migration additive et comptes limités pour les demandes publiques."""
from pathlib import Path
import os, secrets, subprocess
ROOT=Path(__file__).resolve().parents[1]
DOCKER=os.environ.get('DOCKER_BIN','docker')
def sql(query):
    p=subprocess.run([DOCKER,'compose','exec','-T','mysql','sh','/opt/innovevents/client.sh','--batch','--skip-column-names','innovevents_demo'],input=query,encoding='utf-8',capture_output=True,cwd=ROOT)
    if p.returncode:raise SystemExit('Provisionnement demandes interrompu ; examiner la migration sans supprimer de données.')
    return p.stdout.strip()
def secret(name):
    p=ROOT/'.secrets'/name
    if not p.exists():
        with p.open('x',encoding='utf-8') as f:f.write(secrets.token_hex(32))
    value=p.read_text().strip()
    if len(value)!=64 or any(c not in '0123456789abcdef' for c in value):raise SystemExit('Secret invalide')
    return value
sql((ROOT/'database/mysql/004_quote_requests.sql').read_text(encoding='utf-8'))
clause=sql("SELECT CHECK_CLAUSE FROM information_schema.check_constraints WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME='ck_mail_type';")
if not clause:raise SystemExit('Contrainte de courriel absente ; examen manuel requis.')
if 'demande_devis' not in clause:
    sql("ALTER TABLE courriels_a_envoyer DROP CHECK ck_mail_type, ADD CONSTRAINT ck_mail_type CHECK (type IN ('confirmation','recuperation','demande_devis'));")
password=secret('mysql-quotes.txt');settings=secret('mysql-settings.txt')
sql("CREATE USER IF NOT EXISTS 'ecf_quotes'@'%' IDENTIFIED BY '"+password+"'; GRANT INSERT (entreprise,nom,prenom,email,telephone,lieu,type_evenement,date_souhaitee,participants_estimes,besoin) ON innovevents_demo.prospects TO 'ecf_quotes'@'%'; GRANT SELECT,INSERT ON innovevents_demo.soumissions_devis TO 'ecf_quotes'@'%'; GRANT INSERT ON innovevents_demo.courriels_a_envoyer TO 'ecf_quotes'@'%';")
sql("CREATE USER IF NOT EXISTS 'ecf_settings'@'%' IDENTIFIED BY '"+settings+"'; GRANT SELECT ON innovevents_demo.parametres_application TO 'ecf_settings'@'%'; GRANT UPDATE (valeur,modifie_par) ON innovevents_demo.parametres_application TO 'ecf_settings'@'%';")
print('Demandes : migration et droits limités prêts. Aucun secret affiché.')

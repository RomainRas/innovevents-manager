"""Migration et comptes locaux pour inscription/courriels, sans changer les secrets existants."""
from pathlib import Path
import os,secrets,subprocess
ROOT=Path(__file__).resolve().parents[1]
DOCKER=os.environ.get('DOCKER_BIN','docker')
def sql(query):
    p=subprocess.run([DOCKER,'compose','exec','-T','mysql','sh','/opt/innovevents/client.sh','--batch','--skip-column-names','innovevents_demo'],input=query,encoding='utf-8',capture_output=True,cwd=ROOT)
    if p.returncode:raise SystemExit('Migration courriels interrompue. Aucune suppression automatique ; examiner la base.')
    return p.stdout.strip()
def secret(name):
    path=ROOT/'.secrets'/name
    if not path.exists():
        with path.open('x',encoding='utf-8') as f:f.write(secrets.token_hex(32))
    value=path.read_text().strip()
    if len(value)!=64 or any(c not in '0123456789abcdef' for c in value):raise SystemExit('Secret invalide')
    return value
count=int(sql("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='utilisateurs' AND column_name IN ('mdp_temporaire_hash','mdp_temporaire_expire_le');"))
ddl=(ROOT/'database/mysql/003_account_mail.sql').read_text(encoding='utf-8')
if count==0:sql(ddl)
elif count==2:sql(ddl.split('ALTER TABLE')[0])
else:raise SystemExit('Migration partielle : arrêt pour examen sans suppression.')
accounts=secret('mysql-accounts.txt');mail=secret('mysql-mail.txt');secret('mail-key.txt')
sql("CREATE USER IF NOT EXISTS 'ecf_accounts'@'%' IDENTIFIED BY '"+accounts+"'; GRANT SELECT ON innovevents_demo.utilisateurs TO 'ecf_accounts'@'%'; GRANT INSERT (email,email_normalise,prenom,nom,nom_utilisateur,mot_de_passe_hash) ON innovevents_demo.utilisateurs TO 'ecf_accounts'@'%'; GRANT UPDATE (mot_de_passe_hash,changement_mdp_requis,mdp_temporaire_hash,mdp_temporaire_expire_le) ON innovevents_demo.utilisateurs TO 'ecf_accounts'@'%'; GRANT INSERT ON innovevents_demo.courriels_a_envoyer TO 'ecf_accounts'@'%';")
sql("CREATE USER IF NOT EXISTS 'ecf_mail'@'%' IDENTIFIED BY '"+mail+"'; GRANT SELECT,UPDATE ON innovevents_demo.courriels_a_envoyer TO 'ecf_mail'@'%';")
print('Migration comptes/courriels et droits limités prêts. Aucun secret affiché.')

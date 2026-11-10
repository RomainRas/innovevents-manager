"""Démo locale et preuve de restauration ; aucune base existante écrasée.
Python 3 standard, PHP CLI pour password_hash, Docker Compose.
"""
from pathlib import Path
import datetime, hashlib, json, os, re, secrets, subprocess, sys

ROOT = Path(__file__).resolve().parents[1]
DOCKER = os.environ.get('DOCKER_BIN', 'docker')
PHP = os.environ.get('PHP_BIN', 'php')
if sys.argv[1:] not in ([], ['--backup-only']):
    raise SystemExit('Option admise : --backup-only pour une démo déjà créée')
BACKUP_ONLY = '--backup-only' in sys.argv
SOURCE = 'innovevents_demo'
TARGET = 'innovevents_restore_' + secrets.token_hex(6)

def run(args, data=None):
    result = subprocess.run(args, input=data, capture_output=True, cwd=ROOT, timeout=180)
    if result.returncode:
        # Diagnostic privé : ne pas imprimer les données SQL ou mots de passe.
        diagnostic = ROOT/'tmp/demo-backup-error.log'
        diagnostic.parent.mkdir(exist_ok=True)
        diagnostic.write_bytes(result.stderr)
        raise RuntimeError('Commande en échec : ' + ' '.join(args[:7]) + '; diagnostic conservé hors affichage')
    return result.stdout

def docker(*args, data=None):
    return run([DOCKER, 'compose', *args], data)

def sql(statement, database=None):
    args = ['exec','-T','mysql','sh','/opt/innovevents/client.sh','--batch','--skip-column-names','--default-character-set=utf8mb4']
    if database: args.append(database)
    return docker(*args, data=statement.encode('utf-8'))

def mongo(action, target=SOURCE):
    return docker('exec','-T','-e','DEMO_ACTION='+action,'-e','DEMO_TARGET='+target,
                  'mongodb','mongosh','--quiet','--file','/tmp/innovevents-demo-tools.js')

def dump(database):
    return docker('exec','-T','mysql','sh','/tmp/innovevents-dump.sh',database)

def sha(data): return hashlib.sha256(data).hexdigest()

docker('cp','scripts/mongo-demo-backup.js','mongodb:/tmp/innovevents-demo-tools.js')
docker('cp','database/mongodb/001_logs.js','mongodb:/tmp/innovevents-logs-schema.js')
docker('cp','docker/mysql/dump.sh','mysql:/tmp/innovevents-dump.sh')

# Preflight : ne modifier aucune base si l'une des sources existe déjà.
exists = sql("SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name='innovevents_demo';").strip()
mongo_exists = json.loads(mongo('exists'))['exists']
if BACKUP_ONLY:
    if exists != b'1' or not mongo_exists:
        raise SystemExit('Les deux bases démo doivent exister pour --backup-only.')
elif exists != b'0' or mongo_exists:
    raise SystemExit('Une base démo existe déjà : arrêt sans remplacement. Utiliser --backup-only pour sauvegarder et restaurer sans réinitialiser.')

if not BACKUP_ONLY:
    secret_path = ROOT/'.secrets/demo-password.txt'
    secret_path.parent.mkdir(exist_ok=True)
    if secret_path.exists():
        password = secret_path.read_text(encoding='utf-8').strip()
    else:
        password = secrets.token_urlsafe(24)
        with secret_path.open('x',encoding='utf-8') as f: f.write(password)
    hashed = run([PHP,'-r','echo password_hash(trim(stream_get_contents(STDIN)), PASSWORD_BCRYPT);'],password.encode()).decode()
    if not re.fullmatch(r'\$2y\$\d\d\$[./A-Za-z0-9]{53}',hashed): raise SystemExit('Empreinte PHP inattendue')
    verified = run([PHP,'-r','$x=json_decode(stream_get_contents(STDIN),true); echo password_verify($x[0],$x[1]) ? "OK" : "NON";'],json.dumps([password,hashed]).encode())
    if verified != b'OK': raise SystemExit('Vérification du mot de passe échouée')
    
    sql('CREATE DATABASE innovevents_demo CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;')
    sql((ROOT/'database/mysql/001_schema.sql').read_text(encoding='utf-8'), SOURCE)
    sql("SET @demo_hash='"+hashed+"';\n"+(ROOT/'database/mysql/002_demo.sql').read_text(encoding='utf-8'),SOURCE)
    if sql('SELECT GROUP_CONCAT(id ORDER BY id) FROM utilisateurs;',SOURCE).strip() != b'1,2,3':
        raise SystemExit('Identifiants inattendus ; journaux non créés')
    mongo('seed')
    print('Jeu fictif créé : trois comptes, trois événements et leurs devis brouillons ; quatre journaux.')

# Vérifier les empreintes effectivement relues dans la base, pas seulement celle calculée.
password = (ROOT/'.secrets/demo-password.txt').read_text(encoding='utf-8').strip()
account_hashes = sql('SELECT mot_de_passe_hash FROM utilisateurs ORDER BY id;', SOURCE).decode().splitlines()
if len(account_hashes) != 3: raise SystemExit('Trois comptes démo attendus')
verified = b'OK'
for stored_hash in account_hashes:
    answer = run([PHP,'-r','$x=json_decode(stream_get_contents(STDIN),true); echo password_verify($x[0],$x[1]) ? "OK" : "NON";'], json.dumps([password,stored_hash]).encode())
    if answer != b'OK': raise SystemExit('Mot de passe local non conforme au compte stocké')

folder = ROOT/'backups'/TARGET
folder.mkdir(parents=True,exist_ok=False)
original_sql = dump(SOURCE)
(folder/'mysql.sql').write_bytes(original_sql)
mongo('dump')
docker('cp','mongodb:/tmp/innovevents-demo.archive.gz',str(folder/'mongodb.archive.gz'))
original_mongo = json.loads(mongo('snapshot'))

# Restauration dans un espace nouveau, sans DROP ni remplacement de la source.
sql('CREATE DATABASE '+TARGET+' CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;')
sql(original_sql.decode('utf-8'),TARGET)
# Recharger le fichier sauvegardé sur le poste, pas seulement celui resté dans le conteneur.
docker('cp',str(folder/'mongodb.archive.gz'),'mongodb:/tmp/innovevents-demo.archive.gz')
mongo('restore',TARGET)
restored_sql = dump(TARGET)
restored_mongo = json.loads(mongo('snapshot',TARGET))
checks = {'mysql_dump_identique':original_sql == restored_sql,
          'mongo_documents_validateur_index_identiques':original_mongo == restored_mongo,
          'source_mysql_inchangee':original_sql == dump(SOURCE),
          'source_mongo_inchangee':original_mongo == json.loads(mongo('snapshot')),
          'mot_de_passe_verifie_par_php':verified == b'OK'}
report = {'date_utc':datetime.datetime.now(datetime.timezone.utc).isoformat(),
          'source':SOURCE,'restauration':TARGET,'controles':checks,
          'mysql_sha256':sha(original_sql),'mongodb_archive_sha256':sha((folder/'mongodb.archive.gz').read_bytes()),
          'mysql_tables':int(sql('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE();',TARGET)),
          'mongo_logs':len(restored_mongo['documents'])}
(folder/'verification.json').write_text(json.dumps(report,ensure_ascii=False,indent=2),encoding='utf-8')
print(json.dumps(report,ensure_ascii=False,indent=2))
if not all(checks.values()): raise SystemExit('Comparaison non conforme ; conserver les bases pour diagnostic.')
print('Archives locales et preuve conservées ; aucune base supprimée. Secrets et archives exclus de Git.')

"""Comptes locaux de lecture uniquement ; secrets hors Git, aucune rotation implicite."""
from pathlib import Path
import json, os, secrets, subprocess
ROOT=Path(__file__).resolve().parents[1]
DOCKER=os.environ.get('DOCKER_BIN','docker')
def invoke(args, data=None):
    r=subprocess.run([DOCKER,'compose',*args],input=data,capture_output=True,cwd=ROOT,timeout=60)
    if r.returncode:
        (ROOT/'tmp').mkdir(exist_ok=True)
        (ROOT/'tmp/provision-api-error.log').write_bytes(r.stderr)
        raise SystemExit('Provisionnement interrompu ; diagnostic privé dans tmp/provision-api-error.log.')
    return r.stdout
def secret(name):
    p=ROOT/'.secrets'/name
    if not p.exists():
        with p.open('x',encoding='utf-8') as f: f.write(secrets.token_hex(32))
    value=p.read_text(encoding='utf-8').strip()
    if len(value)!=64 or any(c not in '0123456789abcdef' for c in value): raise SystemExit('Format de secret incorrect')
    return value
mysql,mongo=secret('mysql-api.txt'),secret('mongo-api.txt')
statement="CREATE USER IF NOT EXISTS 'ecf_api_read'@'%' IDENTIFIED BY '"+mysql+"';\nGRANT SELECT ON innovevents_demo.* TO 'ecf_api_read'@'%';"
invoke(['exec','-T','mysql','sh','/opt/innovevents/client.sh'],statement.encode())
invoke(['cp','scripts/provision-api-mongo.js','mongodb:/tmp/provision-api-mongo.js'])
invoke(['exec','-T','mongodb','mongosh','--quiet','--file','/tmp/provision-api-mongo.js'],json.dumps({'password':mongo,'audit_password':secret('mongo-auth.txt')}).encode())
print('Comptes de lecture préparés. Les comptes existants ne changent pas de mot de passe ; vérifier avec les tests API.')

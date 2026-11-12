"""Contrôles HTTP et panne temporaire MongoDB avec redémarrage dans finally."""
import json, os, subprocess, time, urllib.request, urllib.error
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
CMD=[os.environ.get('DOCKER_BIN','docker'),'compose','-f','compose.yaml','-f','compose.app.yaml']
def docker(*args):
    return subprocess.run([*CMD,*args],cwd=ROOT,capture_output=True,text=True,check=True,timeout=120).stdout
def request(path,method='GET'):
    try:
        with urllib.request.urlopen(urllib.request.Request('http://127.0.0.1:5173'+path,method=method),timeout=30) as r:
            return r.status,r.read().decode()
    except urllib.error.HTTPError as e: return e.code,e.read().decode()
results=json.loads(docker('exec','-T','api','php','tests/connections.php'))
results['http_ready']=request('/api/health')==(200,'{"status":"ready"}')
results['http_method']=request('/api/health','POST')==(405,'{"error":"method_not_allowed"}')
results['http_unknown']=request('/api/inconnue')==(404,'{"error":"not_found"}')
status,body=request('/')
results['vue_document_served']=status==200 and 'id="app"' in body
try:
    docker('stop','mongodb')
    results['http_unavailable_without_details']=request('/api/health')==(503,'{"status":"unavailable"}')
finally:
    docker('up','-d','--wait','--wait-timeout','90','mongodb')
for _ in range(10):
    if request('/api/health')==(200,'{"status":"ready"}'): break
    time.sleep(1)
results['http_recovered']=request('/api/health')==(200,'{"status":"ready"}')
out=ROOT/'tmp/app-tests.json'
out.parent.mkdir(exist_ok=True)
out.write_text(json.dumps(results,indent=2),encoding='utf-8')
print(json.dumps(results,indent=2))
raise SystemExit(0 if all(results.values()) else 1)

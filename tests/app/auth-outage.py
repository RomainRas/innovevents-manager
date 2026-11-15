"""Panne du journal avant connexion : aucun accès créé, MongoDB relancé en finally."""

from pathlib import Path
import http.cookiejar, json, os, subprocess, urllib.request, urllib.error

# Racine du dépôt (les commandes docker compose s'y exécutent).
ROOT = Path(__file__).resolve().parents[2]
cmd = [os.environ.get('DOCKER_BIN', 'docker'), 'compose', '-f', 'compose.yaml', '-f', 'compose.app.yaml']
client = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))


def request(path, data=None, csrf=''):
    req = urllib.request.Request(
        'http://127.0.0.1:5173' + path,
        data=json.dumps(data).encode() if data else None,
        headers={'Content-Type': 'application/json', 'X-CSRF-Token': csrf, 'Origin': 'http://127.0.0.1:5173'},
    )
    try:
        with client.open(req, timeout=40) as r:
            return r.status, json.loads(r.read())
    except urllib.error.HTTPError as e:
        return e.code, json.loads(e.read())


token = request('/api/auth/session')[1]['csrf']
# Scénario : préparation des données fictives, puis contrôles enregistrés dans results.
try:
    subprocess.run([*cmd, 'stop', 'mongodb'], cwd=ROOT, capture_output=True, check=True)
    status, data = request(
        '/api/auth/login',
        {'email': 'alice@example.invalid', 'password': (ROOT / '.secrets/demo-password.txt').read_text().strip()},
        token,
    )
    results = {
        'audit_failure_denies_login': status == 503 and data == {'status': 'unavailable'},
        'audit_failure_no_authenticated_session': request('/api/auth/session')[1]['user'] is None,
    }
# Remise en état, même si un contrôle échoue : données fictives supprimées, droits et services rétablis.
finally:
    subprocess.run(
        [*cmd, 'up', '-d', '--wait', '--wait-timeout', '90', 'mongodb'], cwd=ROOT, capture_output=True, check=True
    )
(ROOT / 'tmp/auth-outage.json').write_text(json.dumps(results, indent=2), encoding='utf-8')
print(json.dumps(results, indent=2))
raise SystemExit(0 if all(results.values()) else 1)

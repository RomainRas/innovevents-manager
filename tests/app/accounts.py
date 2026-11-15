"""Parcours de compte avec données fictives ; aucun mot de passe dans les sorties."""

from pathlib import Path
import http.cookiejar, json, os, re, secrets, subprocess, time, urllib.request, urllib.error

# Racine du dépôt (les commandes docker compose s'y exécutent).
ROOT = Path(__file__).resolve().parents[2]
# Adresse du site en local (serveur Vite, qui relaie /api vers PHP).
BASE = 'http://127.0.0.1:5173'
# Exécutable Docker (modifiable par la variable DOCKER_BIN).
DOCKER = os.environ.get('DOCKER_BIN', 'docker')
email = 'account-' + secrets.token_hex(8) + '@example.invalid'
password = 'Initial!8' + secrets.token_hex(8)
new_password = 'Changed!9' + secrets.token_hex(8)
results = {}


# Exécute une requête SQL dans le conteneur MySQL et renvoie le texte du résultat.
def sql(query):
    return subprocess.run(
        [
            DOCKER,
            'compose',
            'exec',
            '-T',
            'mysql',
            'sh',
            '/opt/innovevents/client.sh',
            '--batch',
            '--skip-column-names',
            'innovevents_demo',
        ],
        input=query,
        encoding='utf-8',
        capture_output=True,
        cwd=ROOT,
        check=True,
    ).stdout.strip()


# Client HTTP qui se comporte comme un navigateur : cookies de session et jeton CSRF conservés.
class Browser:
    def __init__(self):
        self.client = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
        self.csrf = self.call('/api/auth/session')[1]['csrf']

    # Appel JSON à l'API ; renvoie le statut HTTP et la réponse, et mémorise le nouveau jeton CSRF.
    def call(self, path, data=None):
        req = urllib.request.Request(
            BASE + path,
            data=json.dumps(data).encode() if data is not None else None,
            headers={'Origin': BASE, 'Content-Type': 'application/json', 'X-CSRF-Token': getattr(self, 'csrf', '')},
        )
        try:
            with self.client.open(req, timeout=30) as r:
                status, body = r.status, json.loads(r.read())
        except urllib.error.HTTPError as e:
            status, body = e.code, json.loads(e.read())
        if 'csrf' in body:
            self.csrf = body['csrf']
        return status, body


def inbox():
    with urllib.request.urlopen('http://127.0.0.1:8025/api/v1/messages') as r:
        return json.loads(r.read())['messages']


def mail(subject):
    for _ in range(25):
        for m in inbox():
            if m['Subject'] == subject and any(t['Address'] == email for t in m['To']):
                with urllib.request.urlopen('http://127.0.0.1:8025/api/v1/message/' + m['ID']) as r:
                    return json.loads(r.read())['Text']
        time.sleep(1)
    raise RuntimeError('Courriel de test non reçu')


# Scénario : préparation des données fictives, puis contrôles enregistrés dans results.
try:
    b = Browser()
    payload = {
        'email': email,
        'prenom': 'Test',
        'nom': 'Compte',
        'nom_utilisateur': 'Test',
        'password': password,
        'confirmation': password,
        'role': 'administrateur',
    }
    results['weak_password_rejected'] = (
        b.call('/api/auth/register', {**payload, 'password': 'weak', 'confirmation': 'weak'})[0] == 422
    )
    results['confirmation_required'] = b.call('/api/auth/register', {**payload, 'confirmation': 'wrong'})[0] == 422
    results['registration_accepted'] = b.call('/api/auth/register', payload)[0] == 202
    uid = sql(f"SELECT id FROM utilisateurs WHERE email_normalise='{email}';")
    assert uid.isdigit(), 'Compte de test absent'
    results['server_forces_client'] = sql(f'SELECT role FROM utilisateurs WHERE id={uid};') == 'client'
    results['confirmation_mail'] = password not in mail('Bienvenue chez Innov’Events')
    results['duplicate_generic'] = b.call('/api/auth/register', payload) == (202, {'status': 'accepted'})
    existing = Browser()
    results['permanent_login'] = existing.call('/api/auth/login', {'email': email, 'password': password})[0] == 200
    results['forgot_unknown_generic'] = b.call('/api/auth/forgot', {'email': 'unknown-' + email}) == (
        202,
        {'status': 'accepted'},
    )
    results['forgot_known_generic'] = b.call('/api/auth/forgot', {'email': email}) == (202, {'status': 'accepted'})
    body = mail('Votre mot de passe temporaire Innov’Events')
    temporary = re.search(r'Tmp!7[a-f0-9]{32}', body).group()
    before = sql(f"SELECT mdp_temporaire_hash FROM utilisateurs WHERE id={uid};")
    results['repeat_request_preserves_pending'] = (
        b.call('/api/auth/forgot', {'email': email})[0] == 202
        and sql(f"SELECT mdp_temporaire_hash FROM utilisateurs WHERE id={uid};") == before
    )
    sql(f"UPDATE utilisateurs SET mdp_temporaire_expire_le=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE id={uid};")
    results['expired_temporary_rejected'] = b.call('/api/auth/login', {'email': email, 'password': temporary})[0] == 401
    sql(
        f"UPDATE utilisateurs SET mdp_temporaire_expire_le=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 30 MINUTE) WHERE id={uid};"
    )
    results['permanent_survives_request'] = existing.call('/api/me/events')[0] == 200
    t = Browser()
    status, data = t.call('/api/auth/login', {'email': email, 'password': temporary})
    results['temporary_login_forces_change'] = status == 200 and data['user']['password_change_required']
    results['temporary_private_blocked'] = t.call('/api/me/events')[0] == 403
    change = {'current_password': temporary, 'password': new_password, 'confirmation': new_password}
    results['wrong_current_rejected'] = t.call('/api/auth/password', {**change, 'current_password': 'wrong'})[0] == 422
    results['new_password_confirmation'] = t.call('/api/auth/password', {**change, 'confirmation': 'wrong'})[0] == 422
    results['password_changed'] = t.call('/api/auth/password', change)[0] == 200
    results['current_session_revoked'] = t.call('/api/me/events')[0] == 401
    results['other_session_revoked'] = existing.call('/api/me/events')[0] == 401
    results['old_password_rejected'] = b.call('/api/auth/login', {'email': email, 'password': password})[0] == 401
    results['temporary_password_rejected'] = (
        b.call('/api/auth/login', {'email': email, 'password': temporary})[0] == 401
    )
    results['new_password_works'] = b.call('/api/auth/login', {'email': email, 'password': new_password})[0] == 200
    results['temporary_erased'] = (
        sql(
            f'SELECT mdp_temporaire_hash IS NULL AND mdp_temporaire_expire_le IS NULL FROM utilisateurs WHERE id={uid};'
        )
        == '1'
    )
    sql(f"UPDATE utilisateurs SET statut='suspendu' WHERE id={uid};")
    results['suspended_forgot_generic'] = b.call('/api/auth/forgot', {'email': email}) == (202, {'status': 'accepted'})
    results['suspended_no_temporary'] = (
        sql(f'SELECT mdp_temporaire_hash IS NULL FROM utilisateurs WHERE id={uid};') == '1'
    )
    results['mail_payload_erased'] = (
        sql("SELECT COUNT(*) FROM courriels_a_envoyer WHERE etat='envoye' AND contenu_chiffre IS NOT NULL;") == '0'
    )
# Remise en état, même si un contrôle échoue : données fictives supprimées, droits et services rétablis.
finally:
    sql(f"DELETE FROM utilisateurs WHERE email_normalise='{email}';")
    (ROOT / 'tmp/accounts-tests.json').write_text(json.dumps(results, indent=2), encoding='utf-8')
print(json.dumps(results, indent=2))
raise SystemExit(0 if len(results) >= 20 and all(results.values()) else 1)

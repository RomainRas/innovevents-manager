"""Contrôles HTTP de l'espace administrateur : tableau de bord, création, suspension et suppression des comptes.
Les comptes et la fiche client créés pour l'essai sont retirés en fin d'exécution."""

from pathlib import Path
import http.cookiejar, json, os, re, secrets, subprocess, time, urllib.parse, urllib.request, urllib.error

# Racine du dépôt (les commandes docker compose s'y exécutent).
ROOT = Path(__file__).resolve().parents[2]
# Adresse du site en local (serveur Vite, qui relaie /api vers PHP).
BASE = 'http://127.0.0.1:5173'
# Interface de Mailpit, qui retient les courriels envoyés en local.
MAILPIT = 'http://127.0.0.1:8025'
# Exécutable Docker (modifiable par la variable DOCKER_BIN).
DOCKER = os.environ.get('DOCKER_BIN', 'docker')
results = {}
# Commande docker compose de l'environnement local (bases + application).
COMPOSE = [DOCKER, 'compose', '-f', 'compose.yaml', '-f', 'compose.app.yaml']


# Exécute une requête SQL dans le conteneur MySQL et renvoie le texte du résultat.
def sql(query):
    p = subprocess.run(
        COMPOSE
        + [
            'exec',
            '-T',
            'mysql',
            'sh',
            '/opt/innovevents/client.sh',
            '--batch',
            '--skip-column-names',
            'innovevents_demo',
        ],
        input='SET NAMES utf8mb4;' + query,
        encoding='utf-8',
        capture_output=True,
        cwd=ROOT,
        check=True,
    )
    return p.stdout.strip()


# Lit les courriels reçus par Mailpit.
def mailpit(path):
    with urllib.request.urlopen(MAILPIT + path, timeout=10) as r:
        return r.read()


def password_for(email, subject):
    for _ in range(40):
        found = [
            m
            for m in json.loads(mailpit('/api/v1/search?' + urllib.parse.urlencode({'query': f'to:"{email}"'})))[
                'messages'
            ]
            if m['Subject'] == subject
        ]
        if found:
            m = re.search(
                r'Mot de passe par défaut : (\S+)', json.loads(mailpit('/api/v1/message/' + found[0]['ID']))['Text']
            )
            return m and m.group(1)
        time.sleep(1)


def mail_received(email, subject):
    for _ in range(40):
        if any(
            m['Subject'] == subject
            for m in json.loads(mailpit('/api/v1/search?' + urllib.parse.urlencode({'query': f'to:"{email}"'})))[
                'messages'
            ]
        ):
            return True
        time.sleep(1)
    return False


# Client HTTP qui se comporte comme un navigateur : cookies de session et jeton CSRF conservés.
class Browser:
    def __init__(self):
        self.client = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
        self.csrf = ''
        self.csrf = self.call('/api/auth/session')[1]['csrf']

    # Appel JSON à l'API ; renvoie le statut HTTP et la réponse, et mémorise le nouveau jeton CSRF.
    def call(self, path, data=None, csrf=True):
        req = urllib.request.Request(
            BASE + path,
            data=json.dumps(data).encode() if data is not None else None,
            headers={'Origin': BASE, 'Content-Type': 'application/json', 'X-CSRF-Token': self.csrf if csrf else ''},
        )
        try:
            with self.client.open(req, timeout=30) as r:
                s, d = r.status, json.loads(r.read())
        except urllib.error.HTTPError as e:
            s, d = e.code, json.loads(e.read())
        if 'csrf' in d:
            self.csrf = d['csrf']
        return s, d

    # Connexion avec le mot de passe de démonstration (lu dans .secrets/).
    def login(self, address, password=None):
        return self.call(
            '/api/auth/login',
            {'email': address, 'password': password or (ROOT / '.secrets/demo-password.txt').read_text().strip()},
        )


subprocess.run(
    COMPOSE + ['exec', '-T', 'api', 'rm', '-f', '/var/lib/innovevents/login-rate.json'], cwd=ROOT, check=True
)
tag = secrets.token_hex(4)
employee_email = f'employe-{tag}@example.invalid'
client_email = f'client-{tag}@example.invalid'
created = []
fiche = None
# Scénario : préparation des données fictives, puis contrôles enregistrés dans results.
try:
    anonymous = Browser()
    admin = Browser()
    alice = Browser()
    jose = Browser()
    assert (
        admin.login('chloe@example.invalid')[0] == 200
        and alice.login('alice@example.invalid')[0] == 200
        and jose.login('jose@example.invalid')[0] == 200
    )
    chloe_id = sql("SELECT id FROM utilisateurs WHERE email_normalise='chloe@example.invalid';")

    results['admin_only'] = (
        anonymous.call('/api/admin/dashboard')[0] == 401
        and alice.call('/api/admin/dashboard')[0] == 403
        and jose.call('/api/admin/dashboard')[0] == 403
        and jose.call('/api/admin/accounts')[0] == 403
        and jose.call('/api/admin/account-create', {'role': 'employe'})[0] == 403
    )
    results['csrf_required'] = (
        admin.call('/api/admin/account-status', {'id': '1', 'statut': 'actif'}, csrf=False)[0] == 403
    )

    # Tableau de bord : trois prochains événements avec leur client, cinq notes au plus, indicateurs exacts.
    code, data = admin.call('/api/admin/dashboard')
    upcoming = data.get('prochains_evenements', [])
    results['dashboard_upcoming'] = (
        code == 200
        and 0 < len(upcoming) <= 3
        and all(e['client'] for e in upcoming)
        and [e['debut'] for e in upcoming] == sorted(e['debut'] for e in upcoming)
        and all(e['statut'] != 'annule' for e in upcoming)
    )
    expected = sql(
        "SELECT GROUP_CONCAT(id ORDER BY debut,id) FROM (SELECT id,debut FROM evenements WHERE debut>=UTC_TIMESTAMP() AND statut<>'annule' ORDER BY debut,id LIMIT 3) x;"
    )
    results['dashboard_upcoming_exact'] = ','.join(e['id'] for e in upcoming) == expected
    results['dashboard_notes'] = len(data.get('notes_recentes', [])) == min(
        5, int(sql('SELECT COUNT(*) FROM notes_evenement;'))
    )
    k = data.get('indicateurs', {})
    results['dashboard_indicators'] = k.get('clients_actifs') == int(
        sql("SELECT COUNT(DISTINCT client_id) FROM evenements WHERE statut='en_cours';")
    ) and k.get('evenements_brouillon') == int(sql("SELECT COUNT(*) FROM evenements WHERE statut='brouillon';"))

    # Création d'un compte employé : validation, mot de passe par défaut envoyé, changement obligatoire.
    code, data = admin.call(
        '/api/admin/account-create', {'role': 'employe', 'prenom': '', 'nom': 'Test', 'email': 'pas-une-adresse'}
    )
    results['create_validation'] = code == 422 and set(data.get('fields', {})) == {'prenom', 'email'}
    results['create_admin_refused'] = (
        admin.call(
            '/api/admin/account-create',
            {'role': 'administrateur', 'prenom': 'A', 'nom': 'B', 'email': f'admin-{tag}@example.invalid'},
        )[0]
        == 422
    )
    code, data = admin.call(
        '/api/admin/account-create',
        {'role': 'employe', 'prenom': 'Inès', 'nom': 'Essai', 'email': employee_email, 'acces_mobile': True},
    )
    results['employee_created'] = code == 201 and data['role'] == 'employe'
    employee_id = data.get('id')
    created.append(employee_id)
    results['employee_duplicate_email'] = (
        admin.call(
            '/api/admin/account-create', {'role': 'employe', 'prenom': 'X', 'nom': 'Y', 'email': employee_email.upper()}
        )[0]
        == 409
    )
    password = password_for(employee_email, 'Votre compte Innov’Events')
    results['employee_mail_sent'] = bool(password)
    staff = Browser()
    code, data = staff.login(employee_email, password)
    results['employee_must_change_password'] = (
        code == 200 and data['user']['role'] == 'employe' and data['user']['password_change_required'] is True
    )
    new_password = 'Essai!' + secrets.token_hex(6) + 'A1'
    changed = staff.call(
        '/api/auth/password', {'current_password': password, 'password': new_password, 'confirmation': new_password}
    )
    # Le changement de mot de passe ferme la session : nouvelle connexion avec le mot de passe choisi.
    staff = Browser()
    staff.login(employee_email, new_password)
    work = staff.call('/api/team/reviews')
    results['employee_can_work'] = changed[0] == 200 and work[0] == 200

    # Liste et recherche.
    code, data = admin.call('/api/admin/accounts?q=' + urllib.parse.quote('Essai'))
    results['listing_search'] = (
        code == 200
        and [a['email'] for a in data['comptes']] == [employee_email]
        and data['comptes'][0]['acces_mobile'] is True
    )
    code, data = admin.call('/api/admin/accounts?role=client')
    results['listing_filter_role'] = (
        code == 200 and data['comptes'] and all(a['role'] == 'client' for a in data['comptes'])
    )
    results['listing_no_admin'] = all(
        a['role'] != 'administrateur' for a in admin.call('/api/admin/accounts')[1]['comptes']
    )
    results['listing_invalid_filter'] = admin.call('/api/admin/accounts?role=administrateur')[0] == 422

    # Suspension : session coupée immédiatement, connexion refusée, accès mobile retiré ; réactivation.
    code, _ = admin.call('/api/admin/account-status', {'id': employee_id, 'statut': 'suspendu'})
    results['suspend_cuts_session'] = code == 200 and staff.call('/api/team/reviews')[0] == 401
    results['suspend_blocks_login'] = Browser().login(employee_email, new_password)[0] == 401
    results['suspend_removes_mobile'] = sql(f"SELECT acces_mobile FROM utilisateurs WHERE id={employee_id};") == '0'
    admin.call('/api/admin/account-status', {'id': employee_id, 'statut': 'actif'})
    results['reactivate_allows_login'] = Browser().login(employee_email, new_password)[0] == 200
    results['admin_not_targetable'] = (
        admin.call('/api/admin/account-status', {'id': chloe_id, 'statut': 'suspendu'})[0] == 404
        and admin.call('/api/admin/account-delete', {'id': chloe_id, 'confirmation': True})[0] == 404
        and sql(f"SELECT statut FROM utilisateurs WHERE id={chloe_id};") == 'actif'
    )
    results['invalid_status'] = (
        admin.call('/api/admin/account-status', {'id': employee_id, 'statut': 'bloque'})[0] == 422
    )

    # Compte client : rattaché à une fiche client sans compte, identité reprise de la fiche.
    sql(
        f"INSERT INTO clients(entreprise,contact_nom,contact_prenom,email,telephone,ville) VALUES('Essai {tag}','Martin','Lou','{client_email}','0102030405','Lille');"
    )
    fiche = sql(f"SELECT id FROM clients WHERE email='{client_email}';")
    code, data = admin.call('/api/admin/accounts')
    results['free_client_records_listed'] = fiche in [f['id'] for f in data['fiches_sans_compte']]
    code, data = admin.call('/api/admin/account-create', {'role': 'client', 'client_id': fiche})
    client_id = data.get('id')
    created.append(client_id)
    results['client_created_linked'] = (
        code == 201 and sql(f"SELECT utilisateur_id FROM clients WHERE id={fiche};") == client_id
    )
    results['client_single_account'] = (
        admin.call('/api/admin/account-create', {'role': 'client', 'client_id': fiche})[0] == 409
    )
    client_password = password_for(client_email, 'Votre compte Innov’Events')
    results['client_mail_sent'] = bool(client_password)

    # Suppression : confirmation, anonymisation, demande traitée, fiche détachée, journal et courriel.
    sql(f"INSERT INTO demandes_suppression(utilisateur_id,motif) VALUES({client_id},'Essai');")
    code, data = admin.call('/api/admin/accounts?role=client')
    results['pending_deletion_first'] = (
        code == 200 and data['comptes'][0]['id'] == client_id and bool(data['comptes'][0]['suppression_demandee_le'])
    )
    results['delete_needs_confirmation'] = admin.call('/api/admin/account-delete', {'id': client_id})[0] == 422
    journal = int(
        sql(
            f"SELECT COUNT(*) FROM journal_a_emettre WHERE type_action='MODIFICATION_CLIENT' AND JSON_UNQUOTE(JSON_EXTRACT(details,'$.id_client'))='{fiche}';"
        )
    )
    code, _ = admin.call('/api/admin/account-delete', {'id': client_id, 'confirmation': True})
    row = sql(
        f"SELECT CONCAT_WS('|',email,prenom,statut,supprime_le IS NOT NULL) FROM utilisateurs WHERE id={client_id};"
    )
    results['delete_anonymizes_user'] = (
        code == 200 and row == f'supprime-{client_id}@supprime.invalid|Compte|suspendu|1'
    )
    results['delete_clears_client_record'] = (
        sql(
            f"SELECT CONCAT_WS('|',IFNULL(utilisateur_id,'-'),contact_prenom,email,IFNULL(ville,'-')) FROM clients WHERE id={fiche};"
        )
        == f'-|Contact|supprime-{client_id}@supprime.invalid|-'
    )
    results['delete_request_processed'] = (
        sql(f"SELECT statut FROM demandes_suppression WHERE utilisateur_id={client_id};") == 'traitee'
    )
    results['delete_logged'] = (
        int(
            sql(
                f"SELECT COUNT(*) FROM journal_a_emettre WHERE type_action='MODIFICATION_CLIENT' AND JSON_UNQUOTE(JSON_EXTRACT(details,'$.id_client'))='{fiche}';"
            )
        )
        == journal + 1
    )
    results['delete_blocks_login'] = Browser().login(client_email, client_password)[0] == 401
    results['delete_not_listed'] = client_id not in [a['id'] for a in admin.call('/api/admin/accounts')[1]['comptes']]
    results['delete_once'] = admin.call('/api/admin/account-delete', {'id': client_id, 'confirmation': True})[0] == 404
    results['delete_mail_sent'] = mail_received(client_email, 'Suppression de votre compte Innov’Events')
    # L'adresse libérée peut servir à un nouveau compte.
    code, data = admin.call(
        '/api/admin/account-create', {'role': 'employe', 'prenom': 'Lou', 'nom': 'Martin', 'email': client_email}
    )
    results['email_reusable'] = code == 201
    created.append(data.get('id'))
    results['sql_no_password_read'] = 'mot_de_passe_hash' not in sql(
        "SELECT GROUP_CONCAT(COLUMN_NAME) FROM information_schema.COLUMN_PRIVILEGES WHERE GRANTEE=\"'ecf_admin'@'%'\" AND TABLE_NAME='utilisateurs' AND PRIVILEGE_TYPE='SELECT';"
    )
# Remise en état, même si un contrôle échoue : données fictives supprimées, droits et services rétablis.
finally:
    ids = ','.join(i for i in created if i)
    if fiche:
        sql(f"UPDATE clients SET utilisateur_id=NULL WHERE id={fiche};")
    if ids:
        sql(
            f"DELETE FROM demandes_suppression WHERE utilisateur_id IN ({ids}); DELETE FROM utilisateurs WHERE id IN ({ids});"
        )
    if fiche:
        sql(f"DELETE FROM clients WHERE id={fiche};")
    subprocess.run(COMPOSE + ['exec', '-T', 'api', 'rm', '-f', '/var/lib/innovevents/login-rate.json'], cwd=ROOT)
for k, v in results.items():
    print(('OK  ' if v else 'ÉCHEC ') + k)
print(f"{sum(results.values())}/{len(results)} contrôles réussis")
raise SystemExit(0 if all(results.values()) and len(results) >= 38 else 1)

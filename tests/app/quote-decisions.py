"""Réponse du client au devis : acceptation, refus, modification motivée, contre-proposition et droits."""

from pathlib import Path
import http.cookiejar, io, json, os, re, secrets, subprocess, time, urllib.error, urllib.parse, urllib.request, uuid
from PIL import Image

# Racine du dépôt (les commandes docker compose s'y exécutent).
ROOT = Path(__file__).resolve().parents[2]
# Adresse du site en local (serveur Vite, qui relaie /api vers PHP).
BASE = 'http://127.0.0.1:5173'
# Interface de Mailpit, qui retient les courriels envoyés en local.
MAILPIT = 'http://127.0.0.1:8025'
# Commande docker compose de l'environnement local (bases + application).
COMPOSE = [os.environ.get('DOCKER_BIN', 'docker'), 'compose', '-f', 'compose.yaml', '-f', 'compose.app.yaml']
results = {}
tag = secrets.token_hex(8)
email = f'decision-test-{tag}@example.invalid'


# Exécute une requête SQL dans le conteneur MySQL et renvoie le texte du résultat.
def sql(q):
    r = subprocess.run(
        COMPOSE
        + [
            'exec',
            '-T',
            'mysql',
            'sh',
            '/opt/innovevents/client.sh',
            '--default-character-set=utf8mb4',
            '--batch',
            '--skip-column-names',
            'innovevents_demo',
        ],
        input=q,
        encoding='utf-8',
        capture_output=True,
        cwd=ROOT,
    )
    if r.returncode:
        raise RuntimeError('SQL de test refusé')
    return r.stdout.strip()


# Client HTTP qui se comporte comme un navigateur : cookies de session et jeton CSRF conservés.
class Browser:
    def __init__(self):
        self.client = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
        self.csrf = ''
        self.call('/api/auth/session')

    # Appel JSON à l'API ; renvoie le statut HTTP et la réponse, et mémorise le nouveau jeton CSRF.
    def call(self, path, data=None, mime='application/json', csrf=True):
        raw = json.dumps(data).encode() if data is not None and not isinstance(data, bytes) else data
        req = urllib.request.Request(
            BASE + path,
            data=raw,
            headers={'Origin': BASE, 'Content-Type': mime, 'X-CSRF-Token': self.csrf if csrf else ''},
        )
        try:
            r = self.client.open(req, timeout=30)
        except urllib.error.HTTPError as e:
            r = e
        with r:
            body = r.read()
            headers = r.headers
            s = r.code
        d = json.loads(body) if headers.get('Content-Type', '').startswith('application/json') else body
        if isinstance(d, dict) and 'csrf' in d:
            self.csrf = d['csrf']
        return s, d, headers

    # Connexion avec le mot de passe de démonstration (lu dans .secrets/).
    def login(self, address, password=None):
        return self.call(
            '/api/auth/login',
            {'email': address, 'password': password or (ROOT / '.secrets/demo-password.txt').read_text().strip()},
        )

    def upload(self, eid):
        b = io.BytesIO()
        Image.new('RGB', (120, 80), (12, 127, 140)).save(b, format='PNG')
        boundary = 'test-' + secrets.token_hex(16)
        parts = []
        for k, v in {'id': eid, 'revision': 1, 'alternative': 'Salle de réception'}.items():
            parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="{k}"\r\n\r\n{v}\r\n'.encode())
        parts.append(
            f'--{boundary}\r\nContent-Disposition: form-data; name="image"; filename="salle.png"\r\nContent-Type: image/png\r\n\r\n'.encode()
            + b.getvalue()
            + b'\r\n'
        )
        parts.append(f'--{boundary}--\r\n'.encode())
        return self.call('/api/admin/event-image-upload', b''.join(parts), 'multipart/form-data; boundary=' + boundary)


# Lit les courriels reçus par Mailpit.
def mailpit(path):
    with urllib.request.urlopen(MAILPIT + path, timeout=10) as r:
        return r.read()


def messages(query):
    return json.loads(mailpit('/api/v1/search?' + urllib.parse.urlencode({'query': query})))['messages']


def key():
    return str(uuid.uuid4())


def version(eid):
    return sql(
        f"SELECT v.id FROM devis d JOIN versions_devis v ON v.id=d.version_courante_id WHERE d.evenement_id={eid};"
    )


def row(eid, cols):
    return sql(
        f"SELECT CONCAT_WS('|',{cols}) FROM evenements e JOIN devis d ON d.evenement_id=e.id JOIN versions_devis v ON v.id=d.version_courante_id WHERE e.id={eid};"
    )


lines = [
    {'libelle': 'Location de la salle', 'montant_ht': '1000.00'},
    {'libelle': 'Cocktail dînatoire', 'montant_ht': '500.00'},
]


def prepare(admin, eid, revision=0):
    assert (
        admin.call(
            '/api/admin/quote-draft-save',
            {'event_id': eid, 'revision': revision, 'taux_tva': '20', 'prestations': lines},
        )[0]
        == 200
    )
    if revision == 0:
        assert admin.upload(eid)[0] == 200
    code, d, _ = admin.call('/api/admin/quote-pdf-generate', {'event_id': eid, 'revision': revision + 1})
    assert code == 200, d
    code, d, _ = admin.call(
        '/api/admin/quote-send',
        {'event_id': eid, 'document_id': d['document_id'], 'mode': 'manuel', 'cle': key(), 'confirmation': True},
    )
    assert code == 200, d


# Scénario : préparation des données fictives, puis contrôles enregistrés dans results.
try:
    sql(
        f"INSERT INTO clients(entreprise,contact_nom,contact_prenom,email,telephone) VALUES('Atelier Décision','Bernard','Inès','{email}','0600000002');"
    )
    cid = sql(f"SELECT id FROM clients WHERE email='{email}';")
    names = ['Acceptation', 'Refus', 'Modification', 'Réponse ordinateur', 'Réponse téléphone']
    sql(
        "INSERT INTO evenements(client_id,nom,debut,fin,lieu) VALUES"
        + ",".join(
            f"({cid},'{n}','2031-0{i+3}-10 08:00:00','2031-0{i+3}-10 16:00:00','Lyon')" for i, n in enumerate(names)
        )
        + ";"
    )
    ids = sql(f"SELECT id FROM evenements WHERE client_id={cid} ORDER BY id;").splitlines()
    e_acc, e_ref, e_mod = ids[:3]
    admin = Browser()
    anon = Browser()
    alice = Browser()
    employee = Browser()
    for b, address in [
        (admin, 'chloe@example.invalid'),
        (alice, 'alice@example.invalid'),
        (employee, 'jose@example.invalid'),
    ]:
        assert b.login(address)[0] == 200
    for i in ids:
        prepare(admin, i)
    password = None
    for _ in range(30):
        found = [m for m in messages(f'to:"{email}"') if m['Subject'] == 'Votre espace client Innov’Events']
        if found:
            match = re.search(
                r'Mot de passe par défaut : (\S+)', json.loads(mailpit('/api/v1/message/' + found[0]['ID']))['Text']
            )
            password = match and match.group(1)
            break
        time.sleep(1)
    assert password, 'Identifiants non reçus'
    customer = Browser()
    assert customer.login(email, password)[0] == 200
    new_password = 'Decision!' + secrets.token_hex(6) + 'A1'
    assert (
        customer.call(
            '/api/auth/password', {'current_password': password, 'password': new_password, 'confirmation': new_password}
        )[0]
        == 200
        and customer.login(email, new_password)[0] == 200
    )
    endpoint = '/api/me/quote-decision'
    v_acc = version(e_acc)
    accept = {'version_id': v_acc, 'choix': 'accepte', 'cle': key()}
    results['anonymous_denied'] = anon.call(endpoint, accept)[0] == 401
    results['staff_denied'] = admin.call(endpoint, accept)[0] == 403 and employee.call(endpoint, accept)[0] == 403
    results['csrf_required'] = customer.call(endpoint, accept, csrf=False)[0] == 403
    code, d, _ = customer.call(
        endpoint, {'version_id': version(e_mod), 'choix': 'modification', 'motif': '   ', 'cle': key()}
    )
    results['invalid_input_refused'] = (
        all(
            customer.call(endpoint, {**accept, **x})[0] == 422
            for x in [{'choix': 'peut-etre'}, {'cle': 'x'}, {'version_id': '0'}]
        )
        and code == 422
        and 'motif' in d.get('fields', {})
    )
    results['other_client_cannot_decide'] = (
        alice.call(endpoint, {**accept, 'cle': key()})[0] == 404 and row(e_acc, 'v.statut') == 'etude_client'
    )
    before = int(row(e_acc, 'e.revision'))
    code, d, _ = customer.call(endpoint, accept)
    results['acceptance_recorded'] = (
        code == 200
        and row(e_acc, "v.statut,e.statut,e.revision") == f'accepte|accepte|{before+1}'
        and sql(f"SELECT COUNT(*) FROM decisions_devis WHERE version_id={v_acc} AND choix='accepte' AND motif IS NULL;")
        == '1'
    )
    code, d, _ = customer.call(endpoint, accept)
    results['lost_response_replay'] = (
        code == 200
        and d['repetition'] is True
        and sql(f"SELECT COUNT(*) FROM decisions_devis WHERE version_id={v_acc};") == '1'
    )
    results['second_answer_refused'] = (
        customer.call(endpoint, {**accept, 'choix': 'refuse', 'cle': key()})[0] == 409
        and customer.call(endpoint, {**accept, 'choix': 'refuse'})[0] == 409
    )
    trace = sql(
        f"SELECT id_trace FROM journal_a_emettre WHERE type_action='CHANGEMENT_STATUT_EVENEMENT' AND JSON_UNQUOTE(JSON_EXTRACT(details,'$.id_evenement'))='{e_acc}';"
    )
    for _ in range(20):
        if sql(f"SELECT etat FROM journal_a_emettre WHERE id_trace='{trace}';") == 'livre':
            break
        time.sleep(1)
    r = subprocess.run(
        COMPOSE + ['exec', '-T', 'api', 'php', 'tests/trace-inspect.php'],
        input=trace,
        encoding='utf-8',
        capture_output=True,
        cwd=ROOT,
    )
    log = json.loads(r.stdout or '{}')
    results['event_status_logged'] = (
        log.get('count') == 1
        and log.get('type') == 'CHANGEMENT_STATUT_EVENEMENT'
        and log.get('details') == {'id_evenement': e_acc, 'ancien_statut': 'brouillon', 'nouveau_statut': 'accepte'}
    )
    reference = sql(f"SELECT reference FROM devis WHERE evenement_id={e_acc};")
    agency = []
    for _ in range(30):
        agency = messages(f'subject:"Devis accepté : {reference}"')
        if agency:
            break
        time.sleep(1)
    results['agency_notified_once'] = len(agency) == 1 and agency[0]['To'][0]['Address'] == 'agence@example.invalid'
    results['frozen_after_acceptance'] = (
        admin.call(
            '/api/admin/quote-draft-save', {'event_id': e_acc, 'revision': 1, 'taux_tva': '10', 'prestations': lines}
        )[0]
        == 409
    )
    code, _, _ = customer.call(endpoint, {'version_id': version(e_ref), 'choix': 'refuse', 'cle': key()})
    results['refusal_recorded'] = (
        code == 200
        and row(e_ref, 'v.statut,e.statut') == 'refuse|brouillon'
        and sql(
            f"SELECT COUNT(*) FROM journal_a_emettre WHERE type_action='CHANGEMENT_STATUT_EVENEMENT' AND JSON_UNQUOTE(JSON_EXTRACT(details,'$.id_evenement'))='{e_ref}';"
        )
        == '0'
    )
    v1 = version(e_mod)
    reason = 'Pourriez-vous retirer le cocktail et ajouter une captation vidéo ?'
    code, _, _ = customer.call(
        endpoint, {'version_id': v1, 'choix': 'modification', 'motif': '  ' + reason + '  ', 'cle': key()}
    )
    status = admin.call('/api/admin/quote-transmission?event_id=' + e_mod)[1]
    results['modification_recorded'] = (
        code == 200
        and row(e_mod, 'v.statut,e.statut') == 'modification|brouillon'
        and status['decisions']
        == [
            {'version': 1, 'choix': 'modification', 'motif': reason, 'decidee_le': status['decisions'][0]['decidee_le']}
        ]
    )
    overview = admin.call('/api/admin/quotes?statut=modification')
    results['overview_tracks_status'] = (
        overview[0] == 200
        and [x['evenement_id'] for x in overview[1]['devis']].count(e_mod) == 1
        and all(x['statut'] == 'modification' for x in overview[1]['devis'])
        and e_acc in [x['evenement_id'] for x in admin.call('/api/admin/quotes?statut=accepte')[1]['devis']]
    )
    results['overview_admin_only'] = (
        customer.call('/api/admin/quotes')[0] == 403
        and employee.call('/api/admin/quotes')[0] == 403
        and admin.call('/api/admin/quotes?statut=x')[0] == 422
    )
    counter = '/api/admin/quote-counter-proposal'
    results['counter_proposal_guarded'] = (
        customer.call(counter, {'event_id': e_mod, 'version': 1})[0] == 403
        and admin.call(counter, {'event_id': e_mod, 'version': 2})[0] == 409
        and admin.call(counter, {'event_id': e_acc, 'version': 1})[0] == 409
    )
    code, d, _ = admin.call(counter, {'event_id': e_mod, 'version': 1})
    code2, d2, _ = admin.call(counter, {'event_id': e_mod, 'version': 1})
    v2 = version(e_mod)
    results['counter_proposal_created'] = (
        code == 200
        and d['version'] == 2
        and code2 == 200
        and d2['repetition'] is True
        and v2 != v1
        and sql(
            f"SELECT CONCAT_WS('|',numero,statut,total_ttc,(SELECT COUNT(*) FROM prestations_devis WHERE version_id={v2})) FROM versions_devis WHERE id={v2};"
        )
        == '2|brouillon|1800.00|2'
        and sql(f"SELECT statut FROM versions_devis WHERE id={v1};") == 'modification'
    )
    mine = customer.call('/api/me/quotes')[1]['devis']
    old = [x for x in mine if x['version_id'] == v1]
    results['client_sees_history_not_draft'] = (
        len(old) == 1
        and old[0]['remplace'] is True
        and old[0]['decidable'] is False
        and old[0]['decision']['motif'] == reason
        and all(x['version_id'] != v2 for x in mine)
    )
    results['old_version_not_decidable'] = (
        customer.call(endpoint, {'version_id': v1, 'choix': 'accepte', 'cle': key()})[1].get('error')
        == 'quote_version_replaced'
    )
    lines = [
        {'libelle': 'Location de la salle', 'montant_ht': '1000.00'},
        {'libelle': 'Captation vidéo', 'montant_ht': '800.00'},
    ]
    prepare(admin, e_mod, revision=1)
    current = [x for x in customer.call('/api/me/quotes')[1]['devis'] if x['version_id'] == v2]
    code, _, _ = customer.call(endpoint, {'version_id': v2, 'choix': 'accepte', 'cle': key()})
    results['counter_proposal_accepted'] = (
        len(current) == 1
        and current[0]['decidable'] is True
        and current[0]['version'] == 2
        and current[0]['total_ttc'] == '2160.00'
        and code == 200
        and row(e_mod, 'v.numero,v.statut,e.statut') == '2|accepte|accepte'
    )
    r = subprocess.run(
        COMPOSE + ['exec', '-T', 'api', 'php', 'tests/quote-decision-permissions.php'],
        encoding='utf-8',
        capture_output=True,
        cwd=ROOT,
    )
    print(r.stdout)
    results['sql_permissions'] = r.returncode == 0
    env = {
        **os.environ,
        'DECISION_TEST_IDS': ','.join(ids[3:]),
        'DECISION_TEST_EMAIL': email,
        'DECISION_TEST_PASSWORD': new_password,
    }
    b = subprocess.run(
        [os.environ.get('NODE_BIN', 'node'), 'tests/app/quote-decisions-browser.cjs'],
        cwd=ROOT,
        env=env,
        encoding='utf-8',
        capture_output=True,
    )
    print(b.stdout)
    print(b.stderr if b.returncode else '')
    results['browser'] = b.returncode == 0
# Remise en état, même si un contrôle échoue : données fictives supprimées, droits et services rétablis.
finally:
    if 'cid' in locals():
        sel = f"SELECT v.id FROM versions_devis v JOIN devis d ON d.id=v.devis_id JOIN evenements e ON e.id=d.evenement_id WHERE e.client_id={cid}"
        sql(
            f"DELETE FROM decisions_devis WHERE version_id IN ({sel}); DELETE t FROM transmissions_devis t JOIN documents_devis p ON p.id=t.document_id WHERE p.version_id IN ({sel});"
        )
        for table in ['documents_devis', 'prestations_devis']:
            sql(f"DELETE FROM {table} WHERE version_id IN ({sel});")
        sql(
            f"UPDATE devis d JOIN evenements e ON e.id=d.evenement_id SET d.version_courante_id=NULL WHERE e.client_id={cid}; DELETE v FROM versions_devis v JOIN devis d ON d.id=v.devis_id JOIN evenements e ON e.id=d.evenement_id WHERE e.client_id={cid}; DELETE d FROM devis d JOIN evenements e ON e.id=d.evenement_id WHERE e.client_id={cid}; DELETE FROM evenements WHERE client_id={cid}; UPDATE clients SET utilisateur_id=NULL WHERE id={cid}; DELETE FROM clients WHERE id={cid}; DELETE FROM utilisateurs WHERE email_normalise='{email}';"
        )
    (ROOT / 'tmp').mkdir(exist_ok=True)
    (ROOT / 'tmp/quote-decisions-tests.json').write_text(json.dumps(results, indent=2), encoding='utf-8')
print(json.dumps(results, indent=2))
raise SystemExit(0 if len(results) == 22 and all(results.values()) else 1)

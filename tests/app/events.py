"""Brouillons : droits, dates, reprise, conflit, atomicité et navigateur local."""

from pathlib import Path
import http.cookiejar, json, os, secrets, subprocess, time, urllib.request, urllib.error

# Racine du dépôt (les commandes docker compose s'y exécutent).
ROOT = Path(__file__).resolve().parents[2]
# Adresse du site en local (serveur Vite, qui relaie /api vers PHP).
BASE = 'http://127.0.0.1:5173'
# Commande docker compose de l'environnement local (bases + application).
COMPOSE = [os.environ.get('DOCKER_BIN', 'docker'), 'compose', '-f', 'compose.yaml', '-f', 'compose.app.yaml']
results = {}
revoked = False
email = 'events-' + secrets.token_hex(8) + '@example.invalid'


# Lance une commande docker compose et renvoie son résultat.
def run(args, input=None):
    return subprocess.run(COMPOSE + args, input=input, encoding='utf-8', capture_output=True, cwd=ROOT, timeout=90)


# Exécute une requête SQL dans le conteneur MySQL et renvoie le texte du résultat.
def sql(q):
    r = run(
        [
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
        q,
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
    def login(self, address):
        return self.call(
            '/api/auth/login', {'email': address, 'password': (ROOT / '.secrets/demo-password.txt').read_text().strip()}
        )


# Scénario : préparation des données fictives, puis contrôles enregistrés dans results.
try:
    sql(
        f"INSERT INTO clients(entreprise,contact_nom,contact_prenom,email,telephone) VALUES('Client test événements','Nom','Prénom','{email}','0600000000');"
    )
    cid = sql(f"SELECT id FROM clients WHERE email='{email}';")
    values = ','.join(
        f"('Entreprise','Nom','Prénom','{email}','0600000000','Lyon','Séminaire','2031-07-01',20,'Test', 'converti',{cid},UTC_TIMESTAMP())"
        for _ in range(5)
    )
    sql(
        'INSERT INTO prospects(entreprise,nom,prenom,email,telephone,lieu,type_evenement,date_souhaitee,participants_estimes,besoin,statut,client_id,qualifie_le) VALUES '
        + values
        + ';'
    )
    ids = sql(f"SELECT id FROM prospects WHERE email='{email}' ORDER BY id;").splitlines()
    admin = Browser()
    anon = Browser()
    client = Browser()
    employee = Browser()
    for b, address in [
        (admin, 'chloe@example.invalid'),
        (client, 'alice@example.invalid'),
        (employee, 'jose@example.invalid'),
    ]:
        assert b.login(address)[0] == 200
    create = '/api/admin/event-create'
    save = '/api/admin/event-save'
    p = {'prospect_id': ids[0], 'nom': 'Séminaire test', 'lieu': 'Lyon', 'participants_estimes': '20'}
    results['anonymous_denied'] = anon.call(create, p)[0] == 401
    results['client_denied'] = client.call(create, p)[0] == 403
    results['employee_denied'] = employee.call(create, p)[0] == 403
    results['csrf_required'] = admin.call(create, p, False)[0] == 403
    ctx = admin.call('/api/admin/event-context?prospect_id=' + ids[0])[1]
    results['prefill'] = (
        ctx['client_id'] == cid
        and ctx['suggested']['nom'] == 'Séminaire'
        and ctx['suggested']['date_souhaitee'] == '2031-07-01'
    )
    results['name_required'] = admin.call(create, {**p, 'nom': ''})[0] == 422
    results['participants_positive'] = admin.call(create, {**p, 'participants_estimes': 0})[0] == 422
    results['dates_rejected'] = all(
        admin.call(create, {**p, 'debut': v})[0] == 422
        for v in ['2031-02-30T10:00', '2026-03-29T02:30', '2026-10-25T02:30']
    )
    results['date_order'] = admin.call(create, {**p, 'debut': '2031-07-01T10:00', 'fin': '2031-07-01T09:00'})[0] == 422
    code, d = admin.call(
        create, {**p, 'statut': 'accepte', 'visible_demande': 1, 'accord_publication': 1, 'client_id': '1'}
    )
    assert code == 200
    eid = d['event_id']
    results['partial_private_draft'] = (
        sql(
            f"SELECT client_id,statut,visible_demande,accord_publication,debut IS NULL,fin IS NULL FROM evenements WHERE id={eid};"
        )
        == cid + '\tbrouillon\t0\t0\t1\t1'
    )
    results['retry_same_event'] = admin.call(create, p) == (200, d)
    results['changed_creation_conflict'] = admin.call(create, {**p, 'nom': 'Autre'})[0] == 409
    results['single_event'] = sql(f"SELECT COUNT(*) FROM evenements WHERE prospect_id={ids[0]};") == '1'
    results['context_existing_event'] = (
        admin.call('/api/admin/event-context?prospect_id=' + ids[0])[1]['event_id'] == eid
    )
    results['read_denied'] = (
        client.call('/api/admin/event?id=' + eid)[0] == 403
        and employee.call('/api/admin/event-context?prospect_id=' + ids[0])[0] == 403
    )
    edit = {
        'id': eid,
        'revision': 1,
        'nom': 'Événement corrigé',
        'lieu': 'Paris',
        'type_evenement': 'Conférence',
        'participants_estimes': 30,
        'debut': '2031-07-01T10:00',
        'fin': '2031-07-01T12:00',
    }
    code, updated = admin.call(save, edit)
    results['edit_success'] = code == 200 and updated['revision'] == 2
    results['summer_utc'] = (
        sql(f"SELECT DATE_FORMAT(debut,'%Y-%m-%d %H:%i') FROM evenements WHERE id={eid};") == '2031-07-01 08:00'
    )
    results['local_round_trip'] = admin.call('/api/admin/event?id=' + eid)[1]['event']['debut'] == edit['debut']
    results['edit_retry'] = admin.call(save, edit) == (200, updated)
    results['stale_edit_conflict'] = admin.call(save, {**edit, 'nom': 'Écrasement'})[0] == 409
    results['edit_denied'] = client.call(save, edit)[0] == 403 and employee.call(save, edit)[0] == 403
    winter = {**edit, 'revision': 2, 'debut': '2031-01-01T10:00', 'fin': '2031-01-01T12:00'}
    results['winter_utc'] = (
        admin.call(save, winter)[0] == 200 and sql(f"SELECT HOUR(debut) FROM evenements WHERE id={eid};") == '9'
    )
    sql(f"UPDATE evenements SET statut='accepte' WHERE id={eid};")
    results['non_draft_refused'] = admin.call(save, {**winter, 'revision': 3})[0] == 409
    sql(f"UPDATE prospects SET statut='a_contacter',client_id=NULL,qualifie_le=NULL WHERE id={ids[1]};")
    results['unconverted_refused'] = admin.call(create, {**p, 'prospect_id': ids[1]})[0] == 409
    trace = sql(
        f"SELECT id_trace FROM journal_a_emettre WHERE type_action='CREATION_EVENEMENT' AND JSON_UNQUOTE(JSON_EXTRACT(details,'$.id_evenement'))='{eid}';"
    )
    for _ in range(20):
        if sql(f"SELECT etat FROM journal_a_emettre WHERE id_trace='{trace}';") == 'livre':
            break
        time.sleep(1)
    log = json.loads(run(['exec', '-T', 'api', 'php', 'tests/trace-inspect.php'], trace).stdout)
    results['single_mongo_creation'] = (
        log['count'] == 1
        and log['type'] == 'CREATION_EVENEMENT'
        and log['details'] == {'id_evenement': eid, 'nom_evenement': p['nom']}
    )
    revoked = True
    sql(
        "REVOKE INSERT (id_trace,horodatage,type_action,id_utilisateur,details) ON innovevents_demo.journal_a_emettre FROM 'ecf_events'@'%';"
    )
    results['journal_failure_reported'] = admin.call(create, {**p, 'prospect_id': ids[2]})[0] == 503
    results['journal_failure_rolls_back'] = sql(f"SELECT COUNT(*) FROM evenements WHERE prospect_id={ids[2]};") == '0'
    sql(
        "GRANT INSERT (id_trace,horodatage,type_action,id_utilisateur,details) ON innovevents_demo.journal_a_emettre TO 'ecf_events'@'%';"
    )
    revoked = False
    env = {**os.environ, 'EVENT_TEST_IDS': ','.join(ids[-2:])}
    b = subprocess.run(
        [os.environ.get('NODE_BIN', 'node'), 'tests/app/events-browser.cjs'],
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
    if revoked:
        sql(
            "GRANT INSERT (id_trace,horodatage,type_action,id_utilisateur,details) ON innovevents_demo.journal_a_emettre TO 'ecf_events'@'%';"
        )
    sql(
        f"DELETE FROM evenements WHERE client_id IN(SELECT id FROM clients WHERE email='{email}'); DELETE FROM prospects WHERE email='{email}'; DELETE FROM clients WHERE email='{email}';"
    )
    (ROOT / 'tmp/events-tests.json').write_text(json.dumps(results, indent=2), encoding='utf-8')
print(json.dumps(results, indent=2))
raise SystemExit(0 if len(results) == 28 and all(results.values()) else 1)

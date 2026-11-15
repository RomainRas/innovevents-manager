"""PDF : source courante, journalisation, téléchargement et rendu."""

from pathlib import Path
import http.cookiejar, json, os, secrets, subprocess, urllib.request, urllib.error, io
from PIL import Image

# Racine du dépôt (les commandes docker compose s'y exécutent).
ROOT = Path(__file__).resolve().parents[2]
# Adresse du site en local (serveur Vite, qui relaie /api vers PHP).
BASE = 'http://127.0.0.1:5173'
# Commande docker compose de l'environnement local (bases + application).
COMPOSE = [os.environ.get('DOCKER_BIN', 'docker'), 'compose', '-f', 'compose.yaml', '-f', 'compose.app.yaml']
results = {}
email = 'pdf-test-' + secrets.token_hex(8) + '@example.invalid'


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
    def login(self, address):
        return self.call(
            '/api/auth/login', {'email': address, 'password': (ROOT / '.secrets/demo-password.txt').read_text().strip()}
        )

    def upload(self, eid, revision, image, alt='Salle de séminaire', mime='image/png', csrf=True):
        boundary = 'test-' + secrets.token_hex(16)
        parts = []
        for k, v in {'id': eid, 'revision': revision, 'alternative': alt}.items():
            parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="{k}"\r\n\r\n{v}\r\n'.encode())
        parts.append(
            f'--{boundary}\r\nContent-Disposition: form-data; name="image"; filename="../../lieu.php"\r\nContent-Type: {mime}\r\n\r\n'.encode()
            + image
            + b'\r\n'
        )
        parts.append(f'--{boundary}--\r\n'.encode())
        return self.call(
            '/api/admin/event-image-upload', b''.join(parts), 'multipart/form-data; boundary=' + boundary, csrf
        )


def picture(fmt='PNG', size=(120, 80)):
    b = io.BytesIO()
    Image.new('RGB', size, (12, 127, 140)).save(b, format=fmt)
    return b.getvalue()


revoked = False
# Scénario : préparation des données fictives, puis contrôles enregistrés dans results.
try:
    from pypdf import PdfReader
    import time

    sql(
        f"INSERT INTO clients(entreprise,contact_nom,contact_prenom,email,telephone) VALUES('Ateliers Rhône & Lumière','Rivière','Élodie','{email}','0600000000');"
    )
    cid = sql(f"SELECT id FROM clients WHERE email='{email}';")
    sql(
        f"INSERT INTO evenements(client_id,nom,debut,fin,lieu) VALUES({cid},'Séminaire des équipes','2031-07-01 08:00:00','2031-07-01 16:00:00','Lyon — salle des conférences'),({cid},'Devis ordinateur','2031-07-01 08:00:00','2031-07-01 16:00:00','Lyon'),({cid},'Devis téléphone','2031-07-01 08:00:00','2031-07-01 16:00:00','Lyon');"
    )
    ids = sql(f"SELECT id FROM evenements WHERE client_id={cid} ORDER BY id;").splitlines()
    eid = ids[0]
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
    endpoint = '/api/admin/quote-pdf-generate'
    p = {'event_id': eid, 'revision': 1}
    results['anonymous_denied'] = anon.call(endpoint, p)[0] == 401
    results['client_denied'] = client.call(endpoint, p)[0] == 403
    results['employee_denied'] = employee.call(endpoint, p)[0] == 403
    results['csrf_required'] = admin.call(endpoint, p, csrf=False)[0] == 403
    results['missing_quote_refused'] = admin.call(endpoint, p)[0] == 422
    lines = [
        {'libelle': 'Location de la salle', 'montant_ht': '1000.00'},
        {'libelle': 'Traiteur — déjeuner végétarien', 'montant_ht': '250.50'},
    ]
    for id in ids:
        assert (
            admin.call(
                '/api/admin/quote-draft-save', {'event_id': id, 'revision': 0, 'taux_tva': '20', 'prestations': lines}
            )[0]
            == 200
        )
    results['missing_image_refused'] = admin.call(endpoint, p)[0] == 422
    for id in ids:
        assert admin.upload(id, 1, picture(), alt='Salle de conférence')[0] == 200
    code, d, _ = admin.call(endpoint, p)
    assert code == 200, d
    doc = d['document_id']
    url = '/api/admin/quote-pdf?id=' + doc
    results['generation_success'] = True
    results['idempotent_generation'] = admin.call(endpoint, p)[1] == d
    results['status_ready'] = admin.call('/api/admin/quote-pdf-status?event_id=' + eid)[1] == {
        'ready': True,
        'document_id': doc,
    }
    code, body, headers = admin.call(url)
    results['private_download'] = (
        code == 200
        and headers['Content-Type'] == 'application/pdf'
        and headers['Cache-Control'] == 'no-store'
        and body.startswith(b'%PDF-')
    )
    out = ROOT / 'tmp/quote-pdf'
    out.mkdir(exist_ok=True)
    (out / 'devis-court.pdf').write_bytes(body)
    reader = PdfReader(io.BytesIO(body))
    text = '\n'.join(page.extract_text() for page in reader.pages)
    results['pdf_content'] = (
        all(x in text for x in ['Rhône', 'Élodie', 'Séminaire', '1 500,60', '250,10', '10:00', 'Location de la salle'])
        and len(reader.pages) == 1
    )
    results['download_roles'] = anon.call(url)[0] == 401 and client.call(url)[0] == 403 and employee.call(url)[0] == 403
    results['one_document'] = (
        sql(
            f"SELECT COUNT(*) FROM documents_devis p JOIN versions_devis v ON v.id=p.version_id JOIN devis d ON d.id=v.devis_id WHERE d.evenement_id={eid};"
        )
        == '1'
    )
    trace = sql(
        f"SELECT id_trace FROM journal_a_emettre WHERE type_action='GENERATION_PDF_DEVIS' AND JSON_UNQUOTE(JSON_EXTRACT(details,'$.id_evenement'))='{eid}';"
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
    log = json.loads(r.stdout)
    results['mongo_generation_trace'] = (
        log['count'] == 1 and log['type'] == 'GENERATION_PDF_DEVIS' and log['details'] == {'id_evenement': eid}
    )
    sql(f"UPDATE clients SET entreprise='Ateliers Rhône — nouveau nom' WHERE id={cid};")
    results['changed_client_invalidates'] = (
        admin.call(url)[0] == 409 and not admin.call('/api/admin/quote-pdf-status?event_id=' + eid)[1]['ready']
    )
    code, d, _ = admin.call(endpoint, p)
    assert code == 200, d
    doc2 = d['document_id']
    results['regenerated_after_client_change'] = doc2 != doc
    sql(f"UPDATE evenements SET lieu='Paris' WHERE id={eid};")
    results['changed_event_invalidates'] = admin.call('/api/admin/quote-pdf?id=' + doc2)[0] == 409
    revoked = True
    sql(
        "REVOKE INSERT (id_trace,horodatage,type_action,id_utilisateur,details) ON innovevents_demo.journal_a_emettre FROM 'ecf_quote_pdf'@'%';"
    )
    results['journal_failure_reported'] = admin.call(endpoint, p)[0] == 503
    results['journal_failure_no_document'] = (
        sql(
            f"SELECT COUNT(*) FROM documents_devis p JOIN versions_devis v ON v.id=p.version_id JOIN devis d ON d.id=v.devis_id WHERE d.evenement_id={eid};"
        )
        == '2'
    )
    sql(
        "GRANT INSERT (id_trace,horodatage,type_action,id_utilisateur,details) ON innovevents_demo.journal_a_emettre TO 'ecf_quote_pdf'@'%';"
    )
    revoked = False
    many = [
        {
            'libelle': f'Prestation {i:03d} — organisation et accueil des participants, coordination des équipes et préparation des espaces',
            'montant_ht': '10.05',
        }
        for i in range(1, 101)
    ]
    assert (
        admin.call(
            '/api/admin/quote-draft-save', {'event_id': eid, 'revision': 1, 'taux_tva': '20', 'prestations': many}
        )[0]
        == 200
    )
    results['commercial_edit_obsoletes'] = sql(f"SELECT etat FROM documents_devis WHERE id={doc2};") == 'obsolete'
    results['stale_revision_refused'] = admin.call(endpoint, p)[0] == 409
    code, d, _ = admin.call(endpoint, {**p, 'revision': 2})
    assert code == 200, d
    code, body, _ = admin.call('/api/admin/quote-pdf?id=' + d['document_id'])
    assert code == 200
    (out / 'devis-long.pdf').write_bytes(body)
    reader = PdfReader(io.BytesIO(body))
    text = '\n'.join(page.extract_text() for page in reader.pages)
    results['long_pdf_complete'] = (
        len(reader.pages) > 1 and all(f'Prestation {i:03d}' in text for i in range(1, 101)) and '1 206,00' in text
    )
    env = {**os.environ, 'PDF_TEST_IDS': ','.join(ids[1:])}
    b = subprocess.run(
        [os.environ.get('NODE_BIN', 'node'), 'tests/app/quote-pdf-browser.cjs'],
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
            "GRANT INSERT (id_trace,horodatage,type_action,id_utilisateur,details) ON innovevents_demo.journal_a_emettre TO 'ecf_quote_pdf'@'%';"
        )
    if 'cid' in locals():
        for table in ['documents_devis', 'prestations_devis']:
            sql(
                f"DELETE p FROM {table} p JOIN versions_devis v ON v.id=p.version_id JOIN devis d ON d.id=v.devis_id JOIN evenements e ON e.id=d.evenement_id WHERE e.client_id={cid};"
            )
        sql(
            f"UPDATE devis d JOIN evenements e ON e.id=d.evenement_id SET d.version_courante_id=NULL WHERE e.client_id={cid}; DELETE v FROM versions_devis v JOIN devis d ON d.id=v.devis_id JOIN evenements e ON e.id=d.evenement_id WHERE e.client_id={cid}; DELETE d FROM devis d JOIN evenements e ON e.id=d.evenement_id WHERE e.client_id={cid}; DELETE FROM evenements WHERE client_id={cid}; DELETE FROM clients WHERE id={cid};"
        )
    (ROOT / 'tmp/quote-pdf-tests.json').write_text(json.dumps(results, indent=2), encoding='utf-8')
print(json.dumps(results, indent=2))
raise SystemExit(0 if len(results) == 23 and all(results.values()) else 1)

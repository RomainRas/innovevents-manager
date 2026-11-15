"""Contrôles HTTP des pages publiques : événements publiés, image, avis validés et contact.
Les modifications temporaires (accord retiré, brouillon, avis en attente) sont annulées en fin d'essai."""

from pathlib import Path
import http.cookiejar, json, os, secrets, subprocess, urllib.request, urllib.error

# Racine du dépôt (les commandes docker compose s'y exécutent).
ROOT = Path(__file__).resolve().parents[2]
# Adresse du site en local (serveur Vite, qui relaie /api vers PHP).
BASE = 'http://127.0.0.1:5173'
# Exécutable Docker (modifiable par la variable DOCKER_BIN).
DOCKER = os.environ.get('DOCKER_BIN', 'docker')
results = {}


# Exécute une requête SQL dans le conteneur MySQL et renvoie le texte du résultat.
def sql(query):
    p = subprocess.run(
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
    )
    return p.stdout.strip()


# Client HTTP qui se comporte comme un navigateur : cookies de session et jeton CSRF conservés.
class Browser:
    def __init__(self):
        self.client = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
        self.csrf = ''
        self.csrf = self.call('/api/auth/session')[1]['csrf']

    def raw(self, path):
        try:
            with self.client.open(urllib.request.Request(BASE + path), timeout=30) as r:
                return r.status, r.headers, r.read()
        except urllib.error.HTTPError as e:
            return e.code, e.headers, e.read()

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


published = sql("SELECT id FROM v_evenements_publics ORDER BY id;").split()
target = published[0]
pending_review = None
# Scénario : préparation des données fictives, puis contrôles enregistrés dans results.
try:
    b = Browser()
    code, data = b.call('/api/public/events')
    ids = sorted(str(e['id']) for e in data['events'])
    results['lists_only_published'] = code == 200 and ids == sorted(published) and len(ids) >= 6
    results['drafts_never_listed'] = not any('démonstration' in e['nom'] for e in data['events'])
    results['no_private_fields'] = all(
        set(e)
        == {
            'id',
            'nom',
            'debut',
            'fin',
            'lieu',
            'type_evenement',
            'theme',
            'participants',
            'statut',
            'image',
            'image_alternative',
        }
        for e in data['events']
    )
    results['filter_options'] = 'Conférence' in data['types'] and 'Innovation' in data['themes']
    code, data = b.call('/api/public/events?type=Atelier')
    results['filter_type'] = (
        code == 200 and data['events'] and all(e['type_evenement'] == 'Atelier' for e in data['events'])
    )
    code, data = b.call('/api/public/events?theme=Innovation')
    results['filter_theme'] = (
        code == 200 and len(data['events']) >= 2 and all(e['theme'] == 'Innovation' for e in data['events'])
    )
    code, data = b.call('/api/public/events?du=2026-11-01')
    results['filter_from_date'] = (
        code == 200 and data['events'] and all(e['fin'] >= '2026-11-01' for e in data['events'])
    )
    code, data = b.call('/api/public/events?du=2026-03-01&au=2026-03-31')
    results['filter_date_range'] = code == 200 and [e['nom'] for e in data['events']] == ['Convention annuelle Horizon']
    results['invalid_date'] = b.call('/api/public/events?du=2026-02-30')[0] == 422
    results['reversed_range'] = b.call('/api/public/events?du=2026-12-01&au=2026-01-01')[0] == 422
    results['injection_is_plain_value'] = (
        b.call("/api/public/events?type=%27%20OR%201%3D1%20--%20")[1].get('events') == []
    )
    code, data = b.call('/api/public/event?id=' + target)
    results['detail_published'] = (
        code == 200 and data['event']['id'] == int(target) and 'client' not in json.dumps(data)
    )
    draft = sql("SELECT id FROM evenements WHERE statut='brouillon' LIMIT 1;")
    results['detail_draft_hidden'] = (
        b.call('/api/public/event?id=' + draft)[0] == 404 and b.raw('/api/public/event-image?id=' + draft)[0] == 404
    )
    results['detail_invalid_id'] = b.call('/api/public/event?id=abc')[0] == 404
    code, headers, body = b.raw('/api/public/event-image?id=' + target)
    results['public_read_without_cookie'] = headers.get('Set-Cookie') is None and 'max-age=3600' in headers.get(
        'Cache-Control', ''
    )
    results['image_served'] = (
        code == 200
        and headers.get('Content-Type') == 'image/jpeg'
        and body[:3] == b'\xff\xd8\xff'
        and 'sandbox' in headers.get('Content-Security-Policy', '')
    )
    # Retrait de l'accord du client : l'événement disparaît immédiatement de toutes les routes publiques.
    sql(f"UPDATE evenements SET accord_publication=0 WHERE id={target};")
    results['consent_withdrawn_hidden'] = (
        b.call('/api/public/event?id=' + target)[0] == 404
        and b.raw('/api/public/event-image?id=' + target)[0] == 404
        and target not in [str(e['id']) for e in b.call('/api/public/events')[1]['events']]
    )
    sql(f"UPDATE evenements SET accord_publication=1 WHERE id={target};")
    sql(f"UPDATE evenements SET visible_demande=0 WHERE id={target};")
    results['visibility_off_hidden'] = b.call('/api/public/event?id=' + target)[0] == 404
    sql(f"UPDATE evenements SET visible_demande=1 WHERE id={target};")
    code, data = b.call('/api/public/reviews')
    results['reviews_validated'] = (
        code == 200
        and len(data['reviews']) >= 4
        and all(
            set(r) == {'id', 'contenu', 'publie_le', 'evenement', 'type_evenement', 'auteur'} for r in data['reviews']
        )
    )
    results['review_author_reduced'] = all(
        r['auteur'].endswith('.') and '@' not in r['auteur'] for r in data['reviews']
    )
    pending_review = sql(
        f"INSERT INTO avis(evenement_id,auteur_id,contenu) SELECT {target},id,'Avis test en attente' FROM utilisateurs WHERE email_normalise='jose@example.invalid'; SELECT LAST_INSERT_ID();"
    )
    results['pending_review_hidden'] = not any(
        r['contenu'] == 'Avis test en attente' for r in b.call('/api/public/reviews')[1]['reviews']
    )
    # Contact
    title = 'Contact test ' + secrets.token_hex(6)
    before = int(sql("SELECT COUNT(*) FROM courriels_a_envoyer WHERE type='contact';"))
    msg = {
        'nom_utilisateur': '',
        'email': 'visiteur@example.invalid',
        'titre': title,
        'description': 'Message fictif de validation.',
    }
    results['contact_csrf_required'] = b.call('/api/contact', msg, csrf=False)[0] == 403
    code, data = b.call('/api/contact', {**msg, 'titre': '  '})
    results['contact_title_required'] = code == 422 and 'titre' in data['fields']
    code, data = b.call('/api/contact', {**msg, 'description': ''})
    results['contact_description_required'] = code == 422 and 'description' in data['fields']
    code, data = b.call('/api/contact', {**msg, 'email': ''})
    results['contact_email_or_username'] = code == 422 and 'email' in data['fields']
    results['contact_invalid_email'] = b.call('/api/contact', {**msg, 'email': 'pas-une-adresse'})[0] == 422
    results['contact_length'] = b.call('/api/contact', {**msg, 'titre': 'a' * 151})[0] == 422
    results['contact_no_insert_on_error'] = (
        int(sql("SELECT COUNT(*) FROM courriels_a_envoyer WHERE type='contact';")) == before
    )
    results['contact_visitor_email'] = b.call('/api/contact', msg) == (202, {'status': 'accepted'})
    results['contact_username_only'] = (
        b.call('/api/contact', {**msg, 'email': '', 'nom_utilisateur': 'alice-demo'})[0] == 202
    )
    rows = sql(f"SELECT contenu_chiffre FROM courriels_a_envoyer WHERE type='contact' ORDER BY cree_le DESC LIMIT 2;")
    results['contact_queued_encrypted'] = (
        int(sql("SELECT COUNT(*) FROM courriels_a_envoyer WHERE type='contact';")) == before + 2
        and title not in rows
        and 'visiteur@' not in rows
    )
# Remise en état, même si un contrôle échoue : données fictives supprimées, droits et services rétablis.
finally:
    sql(f"UPDATE evenements SET accord_publication=1,visible_demande=1 WHERE id={target};")
    if pending_review:
        sql(f"DELETE FROM avis WHERE id={pending_review};")
for name, ok in results.items():
    print(('OK  ' if ok else 'ÉCHEC ') + name)
print(f"{sum(map(bool,results.values()))}/{len(results)} contrôles réussis")
raise SystemExit(0 if all(results.values()) else 1)

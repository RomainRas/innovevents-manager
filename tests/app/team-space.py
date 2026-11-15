"""Contrôles HTTP de l'espace employé : consultation des clients et événements, notes collaboratives, statut des tâches.
Les notes créées, le compte employé temporaire et les statuts de tâches modifiés sont restaurés en fin d'essai."""

from pathlib import Path
import http.cookiejar, json, os, secrets, subprocess, urllib.parse, urllib.request, urllib.error

# Racine du dépôt (les commandes docker compose s'y exécutent).
ROOT = Path(__file__).resolve().parents[2]
# Adresse du site en local (serveur Vite, qui relaie /api vers PHP).
BASE = 'http://127.0.0.1:5173'
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
event = sql("SELECT id FROM evenements WHERE nom='Lancement produit Horizon';")
task = lambda title: sql(f"SELECT id FROM taches WHERE evenement_id={event} AND titre='{title}';")
sound, caterer, program = (
    task('Réserver la sonorisation'),
    task('Confirmer le traiteur'),
    task('Envoyer le programme au client'),
)
unassigned = sql("SELECT id FROM taches WHERE assigne_a IS NULL LIMIT 1;")
before = sql(f"SELECT GROUP_CONCAT(CONCAT(id,':',statut)) FROM taches WHERE id IN ({sound},{caterer},{program});")
other_email = f'collegue-{secrets.token_hex(4)}@example.invalid'
other_password = 'Collegue!' + secrets.token_hex(6) + 'A1'
other_id = None
# Scénario : préparation des données fictives, puis contrôles enregistrés dans results.
try:
    assert (
        event and sound and caterer and program and unassigned
    ), 'Démonstration incomplète : relancer provision-team-space.py'
    anonymous = Browser()
    alice = Browser()
    jose = Browser()
    chloe = Browser()
    colleague = Browser()
    assert (
        alice.login('alice@example.invalid')[0] == 200
        and jose.login('jose@example.invalid')[0] == 200
        and chloe.login('chloe@example.invalid')[0] == 200
    )
    hashed = subprocess.run(
        COMPOSE
        + ['exec', '-T', 'api', 'php', '-r', 'echo password_hash(stream_get_contents(STDIN),PASSWORD_DEFAULT);'],
        input=other_password,
        encoding='utf-8',
        capture_output=True,
        cwd=ROOT,
        check=True,
    ).stdout.strip()
    sql(
        f"INSERT INTO utilisateurs(email,email_normalise,prenom,nom,nom_utilisateur,mot_de_passe_hash,role) VALUES('{other_email}','{other_email}','Camille','Collègue','camille','{hashed}','employe');"
    )
    other_id = sql(f"SELECT id FROM utilisateurs WHERE email_normalise='{other_email}';")
    assert colleague.login(other_email, other_password)[0] == 200

    results['team_only'] = (
        anonymous.call('/api/team/clients')[0] == 401
        and alice.call('/api/team/clients')[0] == 403
        and alice.call('/api/team/event?id=' + event)[0] == 403
        and alice.call('/api/team/note-save', {'evenement_id': event, 'contenu': 'Intrusion'})[0] == 403
    )
    results['csrf_required'] = (
        jose.call('/api/team/note-save', {'evenement_id': event, 'contenu': 'Sans jeton'}, csrf=False)[0] == 403
    )

    # Clients : liste complète, recherche, fiche avec historique.
    code, data = jose.call('/api/team/clients')
    total = int(sql('SELECT COUNT(*) FROM clients;'))
    results['clients_all'] = code == 200 and len(data['clients']) == min(total, 200)
    code, data = jose.call('/api/team/clients?q=Horizon')
    results['clients_search'] = (
        code == 200
        and data['clients']
        and all('Horizon' in c['entreprise'] or 'Horizon' in (c['contact'] or '') for c in data['clients'])
    )
    results['clients_search_none'] = (
        jose.call('/api/team/clients?q=' + urllib.parse.quote("zzz' OR 1=1 --"))[1].get('clients') == []
    )
    client = sql(f"SELECT client_id FROM evenements WHERE id={event};")
    code, data = jose.call('/api/team/client?id=' + client)
    results['client_detail_history'] = (
        code == 200
        and event in [e['id'] for e in data['evenements']]
        and len(data['evenements']) == int(sql(f'SELECT COUNT(*) FROM evenements WHERE client_id={client};'))
    )
    results['client_unknown'] = (
        jose.call('/api/team/client?id=999999999')[0] == 404 and jose.call('/api/team/client?id=abc')[0] == 422
    )

    # Événements : tous, y compris les brouillons, avec filtres.
    code, data = jose.call('/api/team/events')
    results['events_all'] = code == 200 and len(data['evenements']) == min(
        int(sql('SELECT COUNT(*) FROM evenements;')), 200
    )
    code, data = jose.call('/api/team/events?statut=brouillon')
    results['events_filter_status'] = (
        code == 200 and data['evenements'] and all(e['statut'] == 'brouillon' for e in data['evenements'])
    )
    results['events_invalid_filter'] = jose.call('/api/team/events?statut=publie')[0] == 422
    code, data = jose.call('/api/team/event?id=' + event)
    results['event_detail'] = (
        code == 200
        and data['evenement']['nom'] == 'Lancement produit Horizon'
        and data['evenement']['client']
        and data['evenement']['participants'] == 90
    )
    tasks = {t['id']: t for t in data['taches']}
    results['tasks_listed'] = all(i in tasks for i in (sound, caterer, program))
    results['tasks_next_only_mine'] = (
        tasks[sound]['suivant'] == 'en_cours'
        and tasks[caterer]['suivant'] == 'termine'
        and tasks[program]['suivant'] is None
        and tasks[program]['a_moi'] is False
    )

    # Notes : création par José, modification et suppression par l'auteur ou l'administratrice uniquement.
    results['note_empty_refused'] = (
        jose.call('/api/team/note-save', {'evenement_id': event, 'contenu': '   '})[0] == 422
    )
    results['note_unknown_event'] = (
        jose.call('/api/team/note-save', {'evenement_id': '999999999', 'contenu': 'Note perdue'})[0] == 404
    )
    long_note = 'é' * 5000
    code, data = jose.call('/api/team/note-save', {'evenement_id': event, 'contenu': long_note})
    results['note_long_accented'] = code == 200
    note_long = next(n['id'] for n in data['notes'] if n['contenu'] == long_note)
    code, data = jose.call(
        '/api/team/note-save', {'evenement_id': event, 'contenu': 'Le traiteur confirme 90 couverts.'}
    )
    mine = next(n for n in data['notes'] if n['contenu'] == 'Le traiteur confirme 90 couverts.')
    results['note_created_mine'] = code == 200 and mine['modifiable'] is True and mine['auteur'].startswith('José ')
    note = mine['id']
    other_view = {n['id']: n for n in colleague.call('/api/team/event?id=' + event)[1]['notes']}
    results['note_visible_to_team'] = other_view[note]['modifiable'] is False
    results['note_other_cannot_edit'] = (
        colleague.call('/api/team/note-save', {'id': note, 'contenu': 'Réécrite'})[0] == 403
    )
    results['note_other_cannot_delete'] = colleague.call('/api/team/note-delete', {'id': note})[0] == 403
    code, data = jose.call('/api/team/note-save', {'id': note, 'contenu': 'Le traiteur confirme 92 couverts.'})
    results['note_author_edits'] = (
        code == 200
        and sql(f"SELECT contenu FROM notes_evenement WHERE id={note};") == 'Le traiteur confirme 92 couverts.'
    )
    results['note_admin_edits'] = (
        chloe.call('/api/team/note-save', {'id': note, 'contenu': 'Traiteur : 92 couverts (vérifié par Chloé).'})[0]
        == 200
    )
    results['note_in_dashboard'] = any(n['id'] == note for n in chloe.call('/api/admin/dashboard')[1]['notes_recentes'])
    code, data = jose.call('/api/team/note-delete', {'id': note})
    results['note_author_deletes'] = code == 200 and note not in [n['id'] for n in data['notes']]
    results['note_deleted_once'] = jose.call('/api/team/note-delete', {'id': note})[0] == 404
    results['note_admin_deletes'] = chloe.call('/api/team/note-delete', {'id': note_long})[0] == 200

    # Tâches : seule la personne assignée avance, une étape à la fois.
    results['task_invalid_status'] = jose.call('/api/team/task-status', {'id': sound, 'statut': 'a_faire'})[0] == 422
    results['task_no_skip'] = jose.call('/api/team/task-status', {'id': sound, 'statut': 'termine'})[0] == 409
    code, data = jose.call('/api/team/task-status', {'id': sound, 'statut': 'en_cours'})
    results['task_started'] = code == 200 and {t['id']: t for t in data['taches']}[sound]['statut'] == 'en_cours'
    results['task_repeat_conflict'] = jose.call('/api/team/task-status', {'id': sound, 'statut': 'en_cours'})[0] == 409
    code, data = jose.call('/api/team/task-status', {'id': sound, 'statut': 'termine'})
    results['task_finished'] = code == 200 and {t['id']: t for t in data['taches']}[sound]['suivant'] is None
    results['task_admin_not_assignee'] = (
        chloe.call('/api/team/task-status', {'id': caterer, 'statut': 'termine'})[0] == 403
    )
    results['task_other_assignee'] = jose.call('/api/team/task-status', {'id': program, 'statut': 'en_cours'})[0] == 403
    results['task_unassigned'] = jose.call('/api/team/task-status', {'id': unassigned, 'statut': 'en_cours'})[0] == 403
    results['task_unknown'] = jose.call('/api/team/task-status', {'id': '999999999', 'statut': 'en_cours'})[0] == 404

    # Le compte MySQL de l'équipe ne peut modifier ni les clients ni les événements.
    grants = sql(
        "SELECT GROUP_CONCAT(DISTINCT CONCAT(TABLE_NAME,':',PRIVILEGE_TYPE)) FROM (SELECT TABLE_NAME,PRIVILEGE_TYPE FROM information_schema.TABLE_PRIVILEGES WHERE GRANTEE=\"'ecf_team'@'%'\" UNION SELECT TABLE_NAME,PRIVILEGE_TYPE FROM information_schema.COLUMN_PRIVILEGES WHERE GRANTEE=\"'ecf_team'@'%'\") x;"
    )
    results['sql_read_only_structure'] = not any(
        f'{t}:{p}' in grants for t in ('clients', 'evenements') for p in ('INSERT', 'UPDATE', 'DELETE')
    )
# Remise en état, même si un contrôle échoue : données fictives supprimées, droits et services rétablis.
finally:
    sql(
        f"DELETE FROM notes_evenement WHERE evenement_id={event} AND (contenu LIKE 'Le traiteur%' OR contenu LIKE 'Traiteur%' OR contenu LIKE 'éééé%');"
    )
    for pair in before.split(','):
        i, s = pair.split(':')
        sql(f"UPDATE taches SET statut='{s}' WHERE id={i};")
    if other_id:
        sql(f"DELETE FROM utilisateurs WHERE id={other_id};")
    subprocess.run(COMPOSE + ['exec', '-T', 'api', 'rm', '-f', '/var/lib/innovevents/login-rate.json'], cwd=ROOT)
for k, v in results.items():
    print(('OK  ' if v else 'ÉCHEC ') + k)
print(f"{sum(map(bool,results.values()))}/{len(results)} contrôles réussis")
raise SystemExit(0 if all(results.values()) and len(results) >= 36 else 1)

"""Contrôles HTTP de l'espace client (profil, demande de suppression, avis) et de la modération des avis.
Les données de démonstration modifiées sont restaurées en fin d'essai ; le compte client temporaire est supprimé."""

from pathlib import Path
import http.cookiejar, json, os, secrets, subprocess, time, urllib.request, urllib.error

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


alice_id = sql("SELECT id FROM utilisateurs WHERE email_normalise='alice@example.invalid';")
client_id = sql(f"SELECT id FROM clients WHERE utilisateur_id={alice_id};")
original = sql(
    f"SELECT u.prenom,u.nom,c.entreprise,c.telephone,IFNULL(c.ville,''),IFNULL(c.adresse_ligne1,''),IFNULL(c.adresse_ligne2,''),IFNULL(c.code_postal,''),'fin' FROM utilisateurs u JOIN clients c ON c.utilisateur_id=u.id WHERE u.id={alice_id};"
).split('\t')
target = sql(f"SELECT id FROM evenements WHERE nom='Afterwork de rentrée Horizon' AND client_id={client_id};")
future = sql(
    f"SELECT id FROM evenements WHERE client_id={client_id} AND statut<>'termine' AND fin>UTC_TIMESTAMP() LIMIT 1;"
)
other_email = f'autre-{secrets.token_hex(4)}@example.invalid'
other_password = 'Autre!' + secrets.token_hex(6) + 'A1'
other_id = None
# Scénario : préparation des données fictives, puis contrôles enregistrés dans results.
try:
    assert target and future, 'Démonstration incomplète : relancer provision-client-space.py'
    anonymous = Browser()
    alice = Browser()
    employee = Browser()
    admin = Browser()
    other = Browser()
    assert (
        alice.login('alice@example.invalid')[0] == 200
        and employee.login('jose@example.invalid')[0] == 200
        and admin.login('chloe@example.invalid')[0] == 200
    )
    # Compte client sans fiche, créé directement en base pour vérifier le cloisonnement entre clients.
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
        f"INSERT INTO utilisateurs(email,email_normalise,prenom,nom,nom_utilisateur,mot_de_passe_hash) VALUES('{other_email}','{other_email}','Autre','Client','autre','{hashed}');"
    )
    other_id = sql(f"SELECT id FROM utilisateurs WHERE email_normalise='{other_email}';")
    assert other.login(other_email, other_password)[0] == 200

    # Accès : uniquement le client connecté pour son espace, uniquement l'équipe pour la modération.
    results['profile_requires_login'] = anonymous.call('/api/me/profile')[0] == 401
    results['profile_clients_only'] = employee.call('/api/me/profile')[0] == 403
    results['moderation_team_only'] = (
        alice.call('/api/team/reviews')[0] == 403 and anonymous.call('/api/team/reviews')[0] == 401
    )
    results['moderation_post_team_only'] = (
        alice.call('/api/team/review-moderation', {'id': '1', 'decision': 'valide'})[0] == 403
    )
    results['csrf_required'] = alice.call('/api/me/profile-save', {'prenom': 'X', 'nom': 'Y'}, csrf=False)[0] == 403

    code, data = alice.call('/api/me/profile')
    results['profile_read'] = (
        code == 200
        and data['profil']['email'] == 'alice@example.invalid'
        and data['profil']['fiche_client'] is True
        and data['suppression'] is None
    )
    code, data = other.call('/api/me/profile')
    results['profile_without_client_record'] = (
        code == 200 and data['profil']['fiche_client'] is False and 'entreprise' not in data['profil']
    )

    base = {
        'prenom': original[0],
        'nom': original[1],
        'entreprise': original[2],
        'telephone': original[3],
        'adresse_ligne1': original[5],
        'adresse_ligne2': original[6],
        'code_postal': '69002',
        'ville': 'Lyon',
    }
    code, data = alice.call('/api/me/profile-save', {**base, 'prenom': ' ', 'telephone': 'abc', 'code_postal': '<b>'})
    results['profile_validation'] = code == 422 and set(data['fields']) == {'prenom', 'telephone', 'code_postal'}
    results['profile_control_chars'] = alice.call('/api/me/profile-save', {**base, 'nom': 'A\u0000B'})[0] == 422
    before = int(
        sql(
            f"SELECT COUNT(*) FROM journal_a_emettre WHERE type_action='MODIFICATION_CLIENT' AND JSON_UNQUOTE(JSON_EXTRACT(details,'$.id_client'))='{client_id}';"
        )
    )
    code, data = alice.call('/api/me/profile-save', base)
    results['profile_saved'] = (
        code == 200
        and data['profil']['ville'] == 'Lyon'
        and sql(f"SELECT CONCAT(contact_prenom,'|',code_postal) FROM clients WHERE id={client_id};")
        == original[0] + '|69002'
    )
    after = int(
        sql(
            f"SELECT COUNT(*) FROM journal_a_emettre WHERE type_action='MODIFICATION_CLIENT' AND JSON_UNQUOTE(JSON_EXTRACT(details,'$.id_client'))='{client_id}';"
        )
    )
    results['profile_change_logged'] = after == before + 1
    # Le compte limité ne permet pas de changer l'adresse électronique de connexion, même si le champ est envoyé.
    alice.call('/api/me/profile-save', {**base, 'email': 'pirate@example.invalid'})
    results['email_not_editable'] = (
        sql(f"SELECT email FROM utilisateurs WHERE id={alice_id};") == 'alice@example.invalid'
    )

    # Avis : événement terminé du client uniquement, un seul avis, en attente jusqu'à la modération.
    code, data = alice.call('/api/me/reviews')
    listed = {e['id']: e for e in data.get('evenements', [])}
    results['reviews_list_finished_only'] = (
        code == 200 and target in listed and future not in listed and listed[target]['avis'] is None
    )
    results['review_too_short'] = alice.call('/api/me/review', {'evenement_id': target, 'contenu': 'Bien'})[0] == 422
    results['review_future_event_refused'] = (
        alice.call('/api/me/review', {'evenement_id': future, 'contenu': 'Un avis donné trop tôt pour cet événement.'})[
            0
        ]
        == 404
    )
    results['review_other_client_refused'] = (
        other.call(
            '/api/me/review', {'evenement_id': target, 'contenu': 'Avis sur un événement qui ne m’appartient pas.'}
        )[0]
        == 404
    )
    text = 'Ambiance détendue sur la péniche, traiteur excellent et horaires respectés. Merci à toute l’équipe !'
    code, data = alice.call('/api/me/review', {'evenement_id': target, 'contenu': text})
    results['review_submitted_pending'] = (
        code == 200 and {e['id']: e for e in data['evenements']}[target]['avis']['statut'] == 'en_attente'
    )
    results['review_single'] = alice.call('/api/me/review', {'evenement_id': target, 'contenu': text})[0] == 409
    review_id = sql(f"SELECT id FROM avis WHERE evenement_id={target} AND auteur_id={alice_id};")
    results['pending_not_public'] = not any(
        r['contenu'] == text for r in anonymous.call('/api/public/reviews')[1]['reviews']
    )

    # Modération par un employé : la file d'attente contient l'avis, la validation le publie.
    code, data = employee.call('/api/team/reviews')
    pending = {r['id']: r for r in data.get('avis', [])}
    results['moderation_queue'] = (
        code == 200
        and review_id in pending
        and pending[review_id]['evenement_public'] is True
        and pending[review_id]['auteur'].endswith('.')
    )
    results['moderation_invalid_decision'] = (
        employee.call('/api/team/review-moderation', {'id': review_id, 'decision': 'publie'})[0] == 422
    )
    results['moderation_unknown'] = (
        employee.call('/api/team/review-moderation', {'id': '999999999', 'decision': 'valide'})[0] == 404
    )
    code, data = employee.call('/api/team/review-moderation', {'id': review_id, 'decision': 'valide'})
    results['moderation_validated'] = code == 200 and sql(f"SELECT statut FROM avis WHERE id={review_id};") == 'valide'
    results['moderation_once'] = (
        admin.call('/api/team/review-moderation', {'id': review_id, 'decision': 'refuse'})[0] == 409
    )
    results['validated_is_public'] = any(
        r['contenu'] == text for r in anonymous.call('/api/public/reviews')[1]['reviews']
    )
    results['validated_listed'] = review_id in [
        r['id'] for r in admin.call('/api/team/reviews?statut=valide')[1]['avis']
    ]
    # Un avis refusé n'est jamais publié.
    sql(f"UPDATE avis SET statut='en_attente',moderateur_id=NULL,modere_le=NULL WHERE id={review_id};")
    admin.call('/api/team/review-moderation', {'id': review_id, 'decision': 'refuse'})
    results['refused_not_public'] = not any(
        r['contenu'] == text for r in anonymous.call('/api/public/reviews')[1]['reviews']
    )

    # Demande de suppression : confirmation explicite, une seule demande en attente, agence prévenue.
    results['deletion_needs_confirmation'] = alice.call('/api/me/deletion-request', {'motif': ''})[0] == 422
    mails = int(sql("SELECT COUNT(*) FROM courriels_a_envoyer WHERE type='suppression';"))
    code, data = alice.call('/api/me/deletion-request', {'confirmation': True, 'motif': 'Fin de collaboration.'})
    results['deletion_requested'] = code == 202 and data['suppression']['statut'] == 'en_attente'
    results['deletion_agency_notified'] = (
        int(sql("SELECT COUNT(*) FROM courriels_a_envoyer WHERE type='suppression';")) == mails + 1
    )
    results['deletion_single_pending'] = alice.call('/api/me/deletion-request', {'confirmation': True})[0] == 409
    results['deletion_status_visible'] = alice.call('/api/me/profile')[1]['suppression']['statut'] == 'en_attente'
# Remise en état, même si un contrôle échoue : données fictives supprimées, droits et services rétablis.
finally:
    sql(f"DELETE FROM avis WHERE evenement_id={target} AND auteur_id={alice_id};" if target else 'SELECT 1;')
    sql(f"DELETE FROM demandes_suppression WHERE utilisateur_id={alice_id};")
    # Adresse d'origine restaurée à l'identique (valeur vide = NULL).
    q = lambda v: 'NULL' if v == '' else "'" + v.replace("'", "''") + "'"
    sql(
        f"UPDATE clients SET ville={q(original[4])},adresse_ligne1={q(original[5])},adresse_ligne2={q(original[6])},code_postal={q(original[7])} WHERE id={client_id};"
    )
    if other_id:
        sql(f"DELETE FROM utilisateurs WHERE id={other_id};")
for k, v in results.items():
    print(('OK  ' if v else 'ÉCHEC ') + k)
print(f"{sum(results.values())}/{len(results)} contrôles réussis")
raise SystemExit(0 if all(results.values()) else 1)

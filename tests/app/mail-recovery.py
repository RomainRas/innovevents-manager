"""Panne SMTP réelle et reprise automatique. Ne traite que des courriels fictifs.
Le worker et Mailpit doivent tourner au départ ; restauration garantie en finally.
"""

from pathlib import Path
import json, os, re, secrets, subprocess, time, urllib.request

# Racine du dépôt (les commandes docker compose s'y exécutent).
ROOT = Path(__file__).resolve().parents[2]
# Exécutable Docker (modifiable par la variable DOCKER_BIN).
DOCKER = os.environ.get('DOCKER_BIN', 'docker')
# Commande docker compose de l'environnement local (bases + application).
COMPOSE = [DOCKER, 'compose', '-f', 'compose.yaml', '-f', 'compose.app.yaml']
results = {}
ids = []
services_changed = False
email = 'mail-test-' + secrets.token_hex(8) + '@example.invalid'


# Lance une commande docker compose et renvoie son résultat.
def run(args, input=None):
    p = subprocess.run(COMPOSE + args, input=input, encoding='utf-8', capture_output=True, cwd=ROOT, timeout=90)
    if p.returncode:
        raise RuntimeError('Échec commande de test : ' + str(args[:3]))
    return p.stdout.strip()


# Exécute une requête SQL dans le conteneur MySQL et renvoie le texte du résultat.
def sql(query):
    return run(
        [
            'exec',
            '-T',
            'mysql',
            'sh',
            '/opt/innovevents/client.sh',
            '--batch',
            '--skip-column-names',
            'innovevents_demo',
        ],
        query,
    )


def fixture():
    uid = run(['exec', '-T', 'api', 'php', 'tests/mail-fixture.php'], json.dumps({'email': email}))
    assert re.fullmatch(r'[a-f0-9-]{36}', uid)
    ids.append(uid)
    return uid


def row(uid):
    return sql(
        f"SELECT etat,tentatives,contenu_chiffre IS NOT NULL,prochain_essai_le>UTC_TIMESTAMP() FROM courriels_a_envoyer WHERE id='{uid}';"
    ).split('\t')


def wait_for(fn, seconds=85):
    end = time.monotonic() + seconds
    while time.monotonic() < end:
        if fn():
            return True
        time.sleep(1)
    return False


def received():
    with urllib.request.urlopen('http://127.0.0.1:8025/api/v1/messages', timeout=5) as r:
        return [m for m in json.loads(r.read())['messages'] if any(t['Address'] == email for t in m['To'])]


# Scénario : préparation des données fictives, puis contrôles enregistrés dans results.
try:
    services = run(['ps', '--services', '--status', 'running']).splitlines()
    assert 'mailpit' in services and 'mail_worker' in services, 'Services requis non démarrés'
    # Ne pas interrompre une file qui contient des envois réels ou d’autres essais.
    assert (
        sql("SELECT COUNT(*) FROM courriels_a_envoyer WHERE etat='attente';") == '0'
    ), 'File non vide : reporter le test'
    services_changed = True
    run(['stop', 'mail_worker'])
    run(['stop', 'mailpit'])
    uid = fixture()
    results['queued_during_smtp_outage'] = row(uid)[:3] == ['attente', '0', '1']
    cipher = sql(f"SELECT contenu_chiffre FROM courriels_a_envoyer WHERE id='{uid}';")
    results['payload_not_plaintext'] = email not in cipher and 'Message fictif' not in cipher
    run(['run', '--rm', '--no-deps', 'mail_worker', 'php', 'bin/mail-worker.php', '--once'])
    results['failed_send_kept_for_retry'] = row(uid) == ['attente', '1', '1', '1']
    # Relancer les services sans modifier l’échéance : attendre le vrai délai de reprise.
    run(['start', 'mailpit'])
    run(['start', 'mail_worker'])
    results['automatic_retry_sent'] = wait_for(lambda: row(uid)[:3] == ['envoye', '2', '0'])
    results['single_message_received'] = wait_for(lambda: len(received()) == 1, 15)
    run(['stop', 'mail_worker'])
    expired = fixture()
    sql(f"UPDATE courriels_a_envoyer SET expire_le=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE id='{expired}';")
    run(['run', '--rm', '--no-deps', 'mail_worker', 'php', 'bin/mail-worker.php', '--once'])
    results['expired_erased_without_send'] = row(expired)[:3] == ['expire', '0', '0'] and len(received()) == 1
    # Dernière tentative simulée pour ne pas multiplier les minutes d’attente.
    terminal = fixture()
    sql(f"UPDATE courriels_a_envoyer SET tentatives=4 WHERE id='{terminal}';")
    run(['stop', 'mailpit'])
    run(['run', '--rm', '--no-deps', 'mail_worker', 'php', 'bin/mail-worker.php', '--once'])
    results['fifth_failure_erases_payload'] = row(terminal)[:3] == ['echec', '5', '0']
# Remise en état, même si un contrôle échoue : données fictives supprimées, droits et services rétablis.
finally:
    # Effacer seulement les lignes créées par cet essai ; conserver les autres données.
    try:
        if ids:
            sql('DELETE FROM courriels_a_envoyer WHERE id IN (' + ','.join("'" + x + "'" for x in ids) + ');')
    finally:
        if services_changed:
            run(['start', 'mailpit'])
            run(['start', 'mail_worker'])
    (ROOT / 'tmp').mkdir(exist_ok=True)
    (ROOT / 'tmp/mail-recovery.json').write_text(json.dumps(results, indent=2), encoding='utf-8')
print(json.dumps(results, indent=2))
raise SystemExit(0 if len(results) == 7 and all(results.values()) else 1)

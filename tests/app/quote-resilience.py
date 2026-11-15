"""Deux processus PHP/MySQL indépendants, échec de file et panne SMTP locale."""

from pathlib import Path
import concurrent.futures, json, os, secrets, subprocess, time

# Racine du dépôt (les commandes docker compose s'y exécutent).
ROOT = Path(__file__).resolve().parents[2]
# Exécutable Docker (modifiable par la variable DOCKER_BIN).
DOCKER = os.environ.get('DOCKER_BIN', 'docker')
# Commande docker compose de l'environnement local (bases + application).
COMPOSE = [DOCKER, 'compose', '-f', 'compose.yaml', '-f', 'compose.app.yaml']
email = 'quote-resilience-' + secrets.token_hex(8) + '@example.invalid'
key = secrets.token_hex(32)
keys = [key]
results = {}
revoked = False
stopped = False
data = dict(
    submission_key=key,
    entreprise='Test résilience',
    nom='Test',
    prenom='Test',
    email=email,
    telephone='0600000000',
    lieu='Lyon',
    type_evenement='Test',
    date_souhaitee='2031-07-01',
    participants_estimes=12,
    besoin='Demande fictive.',
)


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
            '--batch',
            '--skip-column-names',
            'innovevents_demo',
        ],
        q,
    )
    if r.returncode:
        raise RuntimeError('Commande SQL de test échouée')
    return r.stdout.strip()


def submit(p):
    return run(['exec', '-T', 'api', 'php', 'tests/quote-fixture.php'], json.dumps(p))


# Scénario : préparation des données fictives, puis contrôles enregistrés dans results.
try:
    with concurrent.futures.ThreadPoolExecutor(max_workers=2) as pool:
        responses = list(pool.map(submit, [data, data]))
    results['two_php_processes_succeed'] = all(
        r.returncode == 0 and json.loads(r.stdout)['status'] == 'accepted' for r in responses
    )
    results['one_prospect_after_concurrency'] = sql(f"SELECT COUNT(*) FROM prospects WHERE email='{email}';") == '1'
    results['one_submission_after_concurrency'] = (
        sql(f"SELECT COUNT(*) FROM soumissions_devis WHERE cle='{key}';") == '1'
    )
    before = sql(f"SELECT COUNT(*) FROM prospects WHERE email='{email}';")
    newkey = secrets.token_hex(32)
    keys.append(newkey)
    # Injection d’un échec à l’écriture de la file, après l’insertion du prospect.
    revoked = True
    sql("REVOKE INSERT ON innovevents_demo.courriels_a_envoyer FROM 'ecf_quotes'@'%';")
    failed = submit({**data, 'submission_key': newkey})
    results['queue_failure_reported'] = failed.returncode != 0
    results['queue_failure_rolls_back_prospect'] = (
        sql(f"SELECT COUNT(*) FROM prospects WHERE email='{email}';") == before
    )
    results['queue_failure_rolls_back_key'] = (
        sql(f"SELECT COUNT(*) FROM soumissions_devis WHERE cle='{newkey}';") == '0'
    )
    sql("GRANT INSERT ON innovevents_demo.courriels_a_envoyer TO 'ecf_quotes'@'%';")
    revoked = False
    assert (
        sql("SELECT COUNT(*) FROM courriels_a_envoyer WHERE etat='attente';") == '0'
    ), 'Attendre la fin des autres envois'
    stopped = True
    assert run(['stop', 'mail_worker']).returncode == 0
    assert run(['stop', 'mailpit']).returncode == 0
    before_ids = set(sql('SELECT id FROM courriels_a_envoyer;').splitlines())
    response = submit({**data, 'submission_key': newkey})
    results['smtp_outage_keeps_submission'] = (
        response.returncode == 0 and sql(f"SELECT COUNT(*) FROM prospects WHERE email='{email}';") == '2'
    )
    new_ids = set(sql('SELECT id FROM courriels_a_envoyer;').splitlines()) - before_ids
    assert len(new_ids) == 1, 'Courriel de test non isolé'
    mail_id = new_ids.pop()
    assert run(['start', 'mailpit']).returncode == 0
    assert run(['start', 'mail_worker']).returncode == 0
    for _ in range(25):
        if sql(f"SELECT etat FROM courriels_a_envoyer WHERE id='{mail_id}';") == 'envoye':
            break
        time.sleep(1)
    results['queued_notification_sent_after_restart'] = (
        sql(f"SELECT etat,contenu_chiffre IS NULL FROM courriels_a_envoyer WHERE id='{mail_id}';") == 'envoye\t1'
    )
# Remise en état, même si un contrôle échoue : données fictives supprimées, droits et services rétablis.
finally:
    if revoked:
        sql("GRANT INSERT ON innovevents_demo.courriels_a_envoyer TO 'ecf_quotes'@'%';")
    if stopped:
        if run(['start', 'mailpit']).returncode or run(['start', 'mail_worker']).returncode:
            raise RuntimeError('Remise en service à vérifier')
    sql('DELETE FROM soumissions_devis WHERE cle IN (' + ','.join("'" + x + "'" for x in keys) + ');')
    sql(f"DELETE FROM prospects WHERE email='{email}';")
    (ROOT / 'tmp/quote-resilience.json').write_text(json.dumps(results, indent=2), encoding='utf-8')
print(json.dumps(results, indent=2))
raise SystemExit(0 if len(results) == 8 and all(results.values()) else 1)

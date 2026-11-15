"""Exécute tous les tests de l'application et enregistre le résultat de chacun.

  py -3 -X utf8 scripts/run-tests.py              tests unitaires, fonctionnels (HTTP) et de bout en bout (navigateur)
  py -3 -X utf8 scripts/run-tests.py --coverage   idem, avec mesure de la couverture du code PHP (PCOV + phpcov)

Prérequis : application locale démarrée (scripts/Start-App.ps1), Node.js et Playwright (PLAYWRIGHT_MODULE si besoin).
Résultats : tmp/tests/resultats.json ; couverture : tmp/coverage/html/index.html et tmp/coverage/resume.txt.
Les essais restaurent eux-mêmes leurs données ; le limiteur de connexion local est remis à zéro entre deux suites.
"""
from pathlib import Path
import json, os, shutil, subprocess, sys, time
ROOT = Path(__file__).resolve().parents[1]
DOCKER = os.environ.get('DOCKER_BIN', 'docker'); PYTHON = sys.executable; NODE = os.environ.get('NODE_BIN', 'node')
APP = [DOCKER, 'compose', '-f', 'compose.yaml', '-f', 'compose.app.yaml']
COVERAGE = '--coverage' in sys.argv

# Suites par fonctionnalité : fichier, type, fonctionnalité. Les suites HTTP marquées « +navigateur » lancent aussi
# leur parcours Playwright avec des données préparées pour l'occasion.
SUITES = [
    ('tests/app/authentication.py', 'fonctionnel', 'Connexion, sessions et journal des connexions'),
    ('tests/app/accounts.py', 'fonctionnel', 'Création de compte, mot de passe oublié et changement'),
    ('tests/app/mail-recovery.py', 'fonctionnel', 'File des courriels et reprise après incident'),
    ('tests/app/auth-outage.py', 'fonctionnel', 'Connexion pendant une panne MongoDB'),
    ('tests/app/quote-requests.py', 'fonctionnel', 'Demande de devis publique'),
    ('tests/app/prospects.py', 'fonctionnel+navigateur', 'Qualification des prospects'),
    ('tests/app/conversion.py', 'fonctionnel+navigateur', 'Conversion d’un prospect en client'),
    ('tests/app/events.py', 'fonctionnel+navigateur', 'Événement créé depuis une demande'),
    ('tests/app/event-images.py', 'fonctionnel+navigateur', 'Images des événements'),
    ('tests/app/quote-drafts.py', 'fonctionnel+navigateur', 'Préparation du devis et calculs'),
    ('tests/app/quote-pdf.py', 'fonctionnel+navigateur', 'Devis PDF'),
    ('tests/app/quote-transmission.py', 'fonctionnel+navigateur', 'Transmission du devis et espace client'),
    ('tests/app/quote-decisions.py', 'fonctionnel+navigateur', 'Réponse du client et contre-proposition'),
    ('tests/app/quote-resilience.py', 'fonctionnel', 'Devis : concurrence et reprises'),
    ('tests/app/public-pages.py', 'fonctionnel', 'Pages publiques, avis validés et contact'),
    ('tests/app/client-space.py', 'fonctionnel', 'Espace client : informations, suppression, avis'),
    ('tests/app/admin-accounts.py', 'fonctionnel', 'Tableau de bord et comptes (administratrice)'),
    ('tests/app/team-space.py', 'fonctionnel', 'Espace employé : clients, événements, notes, tâches'),
    ('tests/app/admin-records.py', 'fonctionnel', 'Gestion des fiches et statuts (administratrice)'),
    ('tests/app/mobile.py', 'fonctionnel', 'API de l’application mobile'),
    ('tests/app/auth-browser.cjs', 'bout en bout', 'Connexion et retour à la page demandée'),
    ('tests/app/accounts-browser.cjs', 'bout en bout', 'Création de compte et mot de passe'),
    ('tests/app/quote-browser.cjs', 'bout en bout', 'Demande de devis publique'),
    ('tests/app/public-pages-browser.cjs', 'bout en bout', 'Pages publiques'),
    ('tests/app/client-space-browser.cjs', 'bout en bout', 'Espace client : informations, suppression, avis'),
    ('tests/app/admin-accounts-browser.cjs', 'bout en bout', 'Tableau de bord et comptes (administratrice)'),
    ('tests/app/team-space-browser.cjs', 'bout en bout', 'Espace employé : clients, événements, notes, tâches'),
    ('tests/app/admin-records-browser.cjs', 'bout en bout', 'Gestion des fiches et statuts (administratrice)'),
    ('tests/app/mobile-browser.cjs', 'bout en bout', 'Application mobile sur téléphone émulé'),
]

def compose(*args, extra=(), check=True, capture=True):
    return subprocess.run(APP + list(extra) + list(args), cwd=ROOT, encoding='utf-8', capture_output=capture, check=check)
def reset_limiter():
    compose('exec', '-T', 'api', 'rm', '-f', '/var/lib/innovevents/login-rate.json', check=False)
def wait_api():
    for _ in range(60):
        r = compose('exec', '-T', 'api', 'php', '-r', "exit(@file_get_contents('http://127.0.0.1:8080/api/health')==='{\"status\":\"ready\"}'?0:1);", check=False)
        if r.returncode == 0: return
        time.sleep(2)
    raise SystemExit('API indisponible.')
def summary(output):
    for line in reversed(output.splitlines()):
        if 'contrôles réussis' in line or line.startswith('OK (') or line.startswith('Tests:'): return line.strip()
    return ''

results = []; started = time.time()
out = ROOT / 'tmp/tests'; out.mkdir(parents=True, exist_ok=True)
if COVERAGE:
    shutil.rmtree(ROOT / 'tmp/coverage', ignore_errors=True)
    (ROOT / 'backend/tmp/coverage/requetes').mkdir(parents=True, exist_ok=True)
    for f in (ROOT / 'backend/tmp/coverage/requetes').glob('*.cov'): f.unlink()
    compose('up', '-d', '--force-recreate', '--no-deps', 'api', extra=('-f', 'compose.coverage.yaml')); wait_api()
try:
    # Tests unitaires (PHPUnit) : couverture propre ajoutée à celle des tests HTTP.
    args = ['exec', '-T', 'api', 'php'] + (['-d', 'pcov.enabled=1'] if COVERAGE else []) + ['vendor/bin/phpunit', '--no-progress']
    if COVERAGE: args += ['--coverage-php', 'tmp/coverage/requetes/unitaires.cov']
    r = compose(*args, check=False)
    results.append({'fichier': 'backend/tests/Unit (PHPUnit)', 'type': 'unitaire', 'fonctionnalite': 'Règles métier : événements, clients, dates, montants', 'reussi': r.returncode == 0, 'resume': summary(r.stdout)})
    print(('OK  ' if r.returncode == 0 else 'ÉCHEC ') + 'PHPUnit', summary(r.stdout), flush=True)
    for path, kind, feature in SUITES:
        reset_limiter()
        command = [PYTHON, '-X', 'utf8', path] if path.endswith('.py') else [NODE, path]
        t = time.time(); r = subprocess.run(command, cwd=ROOT, encoding='utf-8', capture_output=True, env=os.environ)
        ok = r.returncode == 0
        results.append({'fichier': path, 'type': kind, 'fonctionnalite': feature, 'reussi': ok, 'resume': summary(r.stdout), 'duree_s': round(time.time() - t, 1)})
        print(('OK  ' if ok else 'ÉCHEC ') + path, summary(r.stdout), flush=True)
        if not ok: (out / (Path(path).stem + '.echec.txt')).write_text(r.stdout + '\n' + r.stderr, encoding='utf-8')
finally:
    reset_limiter()
    if COVERAGE:
        compose('up', '-d', '--force-recreate', '--no-deps', 'api'); wait_api()
coverage = None
if COVERAGE:
    r = compose('exec', '-T', 'api', 'php', 'vendor/bin/phpcov', 'merge', '--text', 'tmp/coverage/resume.txt', '--html', 'tmp/coverage/html', '--clover', 'tmp/coverage/clover.xml', 'tmp/coverage/requetes', check=False)
    report = ROOT / 'backend/tmp/coverage'
    if (report / 'resume.txt').exists():
        (ROOT / 'tmp/coverage').mkdir(parents=True, exist_ok=True)
        shutil.copy(report / 'resume.txt', ROOT / 'tmp/coverage/resume.txt'); shutil.copytree(report / 'html', ROOT / 'tmp/coverage/html', dirs_exist_ok=True)
        text = (report / 'resume.txt').read_text(encoding='utf-8')
        coverage = [l.strip() for l in text.splitlines() if l.strip().startswith(('Classes:', 'Methods:', 'Lines:'))][:3]
        print('\n'.join(coverage))
    else:
        print('Fusion de la couverture impossible :', r.stdout, r.stderr)
(out / 'resultats.json').write_text(json.dumps({'date': time.strftime('%Y-%m-%d %H:%M'), 'duree_min': round((time.time() - started) / 60, 1),
    'reussis': sum(r['reussi'] for r in results), 'total': len(results), 'couverture': coverage, 'suites': results}, ensure_ascii=False, indent=1), encoding='utf-8')
print(f"{sum(r['reussi'] for r in results)}/{len(results)} suites réussies.")
raise SystemExit(0 if all(r['reussi'] for r in results) else 1)

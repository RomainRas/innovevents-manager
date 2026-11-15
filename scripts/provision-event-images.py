"""Compte limité à l'image et à la révision, sans migration de schéma."""

from pathlib import Path
import os, secrets, subprocess

# Racine du dépôt (les commandes docker compose s'y exécutent).
ROOT = Path(__file__).resolve().parents[1]
p = ROOT / '.secrets/mysql-event-images.txt'
if not p.exists():
    with p.open('x', encoding='utf-8') as f:
        f.write(secrets.token_hex(32))
password = p.read_text().strip()
if len(password) != 64 or any(c not in '0123456789abcdef' for c in password):
    raise SystemExit('Secret invalide')
q = (
    "CREATE USER IF NOT EXISTS 'ecf_event_images'@'%' IDENTIFIED BY '"
    + password
    + "'; GRANT SELECT (id,statut,revision,image_reference,image_alternative) ON innovevents_demo.evenements TO 'ecf_event_images'@'%'; GRANT UPDATE (image_reference,image_alternative,revision) ON innovevents_demo.evenements TO 'ecf_event_images'@'%';"
)
r = subprocess.run(
    [
        os.environ.get('DOCKER_BIN', 'docker'),
        'compose',
        'exec',
        '-T',
        'mysql',
        'sh',
        '/opt/innovevents/client.sh',
        'innovevents_demo',
    ],
    input=q,
    encoding='utf-8',
    capture_output=True,
    cwd=ROOT,
)
if r.returncode:
    raise SystemExit('Provisionnement images interrompu.')
print('Compte images prêt. Aucun secret affiché.')

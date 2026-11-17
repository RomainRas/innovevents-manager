"""Migration MongoDB 002 : suppression automatique des journaux de connexion (adresses IP) au bout d'un an."""

from pathlib import Path
import os, subprocess

# Racine du dépôt (les commandes docker compose s'y exécutent).
ROOT = Path(__file__).resolve().parents[1]
# Exécutable Docker (modifiable par la variable DOCKER_BIN).
DOCKER = os.environ.get('DOCKER_BIN', 'docker')


def invoke(args):
    r = subprocess.run([DOCKER, 'compose', *args], capture_output=True, encoding='utf-8', cwd=ROOT, timeout=60)
    if r.returncode:
        raise SystemExit('Migration de conservation des journaux interrompue. Aucune suppression manuelle effectuée.')
    return r.stdout.strip()


invoke(['cp', 'database/mongodb/002_logs_retention.js', 'mongodb:/tmp/innovevents-logs-retention.js'])
print(invoke(['exec', '-T', 'mongodb', 'mongosh', '--quiet', '--file', '/tmp/innovevents-logs-retention.js']))

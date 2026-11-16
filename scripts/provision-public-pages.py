"""Migrations 011, 016 et 017 : vues publiques (événements publiés, avis validés), courriel de contact et démonstration publiée.
Aucun nouveau compte : la lecture publique passe par le compte en lecture seule existant, limitée aux vues dans le code."""

from pathlib import Path
import os, subprocess

# Racine du dépôt (les commandes docker compose s'y exécutent).
ROOT = Path(__file__).resolve().parents[1]
# Exécutable Docker (modifiable par la variable DOCKER_BIN).
DOCKER = os.environ.get('DOCKER_BIN', 'docker')


# Exécute une requête SQL dans le conteneur MySQL et renvoie le texte du résultat.
def sql(q):
    r = subprocess.run(
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
        input=q,
        encoding='utf-8',
        capture_output=True,
        cwd=ROOT,
    )
    if r.returncode:
        raise SystemExit(
            'Provisionnement pages publiques interrompu. Aucune suppression automatique ; examiner la base.'
        )
    return r.stdout.strip()


if (
    sql("SELECT COUNT(*) FROM information_schema.views WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='v_avis_publics';")
    == '0'
):
    sql((ROOT / 'database/mysql/011_public_pages.sql').read_text(encoding='utf-8'))
# Photos d'ambiance de la démonstration (016) : rejouable, n'écrase jamais une image envoyée par Chloé.
sql((ROOT / 'database/mysql/016_demo_photos.sql').read_text(encoding='utf-8'))
# Avis illustrés (017) : la vue des avis publics expose l'événement et la présence de sa photo.
sql((ROOT / 'database/mysql/017_review_photos.sql').read_text(encoding='utf-8'))
print(
    'Pages publiques : vues et démonstration prêtes ('
    + sql('SELECT COUNT(*) FROM v_evenements_publics;')
    + ' événements publiés).'
)

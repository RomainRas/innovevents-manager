"""Tests des contraintes MySQL via Compose, sans dépendance Python externe.
Les données fictives sont annulées ; aucun DROP ni accès à XAMPP.
"""

from pathlib import Path
import json
import os
import re
import subprocess

# Racine du dépôt (les commandes docker compose s'y exécutent).
ROOT = Path(__file__).resolve().parents[2]
# Exécutable Docker (modifiable par la variable DOCKER_BIN).
DOCKER = os.environ.get('DOCKER_BIN', 'docker')
COMMAND = [
    DOCKER,
    'compose',
    'exec',
    '-T',
    'mysql',
    'sh',
    '/opt/innovevents/client.sh',
    '--batch',
    '--skip-column-names',
    '--default-character-set=utf8mb4',
    'innovevents_test',
]


def query(sql):
    return subprocess.run(COMMAND, input=sql, encoding='utf-8', capture_output=True, cwd=ROOT, timeout=60)


pre = query('SELECT VERSION(), DATABASE();')
if pre.returncode or not pre.stdout.startswith('8.4.') or 'innovevents_test' not in pre.stdout:
    raise SystemExit('Moteur MySQL 8.4 et base de test attendus ; arrêt sans insertion.')

fixture = (ROOT / 'tests/database/mysql-fixture.sql').read_text(encoding='utf-8')
cases = [
    ('jeu_fictif_complet', 'SELECT 1;', None),
    (
        'email_unique',
        'UPDATE utilisateurs SET email_normalise=(SELECT x.email_normalise FROM (SELECT email_normalise FROM utilisateurs WHERE id=@admin) x) WHERE id=@user_a;',
        1062,
    ),
    ('compte_client_unique', 'UPDATE clients SET utilisateur_id=@user_a WHERE id=@client_b;', 1062),
    ('origine_meme_client', 'UPDATE evenements SET client_id=@client_b WHERE id=@event_a;', 1452),
    ('version_meme_devis', 'UPDATE devis SET version_courante_id=@version_b WHERE id=@devis_a;', 1452),
    (
        'ordre_prestation_unique',
        "INSERT INTO prestations_devis(version_id,ordre,libelle,montant_ht) VALUES(@version_a,1,'Doublon',1);",
        1062,
    ),
    ('montant_non_negatif', 'UPDATE prestations_devis SET montant_ht=-1 WHERE version_id=@version_a;', 3819),
    ('dates_coherentes', 'UPDATE evenements SET fin=debut WHERE id=@event_a;', 3819),
    (
        'decision_unique',
        "INSERT INTO decisions_devis(version_id,auteur_id,choix) VALUES(@version_a,@user_a,'refuse');",
        1062,
    ),
    (
        'modification_motivee',
        "INSERT INTO decisions_devis(version_id,auteur_id,choix) VALUES(@version_b,@user_a,'modification');",
        3819,
    ),
    ('avis_modere_identifie', "UPDATE avis SET statut='valide' WHERE evenement_id=@event_a;", 3819),
    ('suppression_parent_bloquee', 'DELETE FROM clients WHERE id=@client_a;', 1451),
    ('total_ttc_coherent', 'UPDATE versions_devis SET total_ttc=1 WHERE id=@version_a;', 3819),
    ('brouillon_vide_permis', 'SELECT COUNT(*) FROM prestations_devis WHERE version_id=@version_b;', None),
]
results = []
for name, statement, expected in cases:
    # En cas d'erreur, mysql ferme la connexion et annule la transaction ouverte.
    run = query('START TRANSACTION;\n' + fixture + "\nSELECT 'FIXTURE_OK';\n" + statement + '\nROLLBACK;')
    error = re.search(r'ERROR (\d+)', run.stderr)
    actual = int(error.group(1)) if error else None
    ok = (run.returncode == 0 and expected is None) or (run.returncode != 0 and actual == expected)
    ok = ok and 'FIXTURE_OK' in run.stdout.splitlines()
    if name == 'brouillon_vide_permis':
        ok = ok and run.stdout.replace('FIXTURE_OK', '').strip() == '0'
    results.append({'cas': name, 'reussi': ok, 'code_attendu': expected, 'code_recu': actual})
    if not ok:
        print(run.stderr[:1500])
    print(('OK ' if ok else 'ECHEC ') + name)

out = ROOT / 'tmp/database-tests'
out.mkdir(parents=True, exist_ok=True)
(out / 'mysql.json').write_text(
    json.dumps({'moteur': pre.stdout.strip(), 'tests': results}, ensure_ascii=False, indent=2), encoding='utf-8'
)
raise SystemExit(0 if all(r['reussi'] for r in results) else 1)

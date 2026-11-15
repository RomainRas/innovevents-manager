"""Migration 013 et compte limité : tableau de bord administrateur et gestion des comptes employés et clients."""

from pathlib import Path
import os, secrets, subprocess

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
            'Provisionnement administration interrompu. Aucune suppression automatique ; examiner la base.'
        )
    return r.stdout.strip()


p = ROOT / '.secrets/mysql-admin.txt'
if not p.exists():
    with p.open('x', encoding='utf-8') as f:
        f.write(secrets.token_hex(32))
password = p.read_text(encoding='utf-8').strip()
if len(password) != 64 or any(c not in '0123456789abcdef' for c in password):
    raise SystemExit('Secret invalide')
if (
    sql(
        "SELECT COUNT(*) FROM information_schema.columns WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='utilisateurs' AND COLUMN_NAME='supprime_le';"
    )
    == '0'
):
    sql((ROOT / 'database/mysql/013_admin_accounts.sql').read_text(encoding='utf-8'))
D = 'innovevents_demo'
U = "'ecf_admin'@'%'"
q = f"CREATE USER IF NOT EXISTS {U} IDENTIFIED BY '{password}';"
# Comptes : jamais de lecture des empreintes de mot de passe ; écriture limitée aux colonnes gérées.
q += f" GRANT SELECT (id,email,email_normalise,prenom,nom,nom_utilisateur,role,statut,acces_mobile,changement_mdp_requis,cree_le,supprime_le) ON {D}.utilisateurs TO {U};"
q += f" GRANT INSERT (email,email_normalise,prenom,nom,nom_utilisateur,mot_de_passe_hash,role,changement_mdp_requis,acces_mobile) ON {D}.utilisateurs TO {U};"
q += f" GRANT UPDATE (email,email_normalise,prenom,nom,nom_utilisateur,mot_de_passe_hash,statut,acces_mobile,changement_mdp_requis,mdp_temporaire_hash,mdp_temporaire_expire_le,supprime_le) ON {D}.utilisateurs TO {U};"
q += f" GRANT SELECT ON {D}.clients TO {U};"
q += f" GRANT UPDATE (utilisateur_id,contact_nom,contact_prenom,email,telephone,adresse_ligne1,adresse_ligne2,code_postal,ville) ON {D}.clients TO {U};"
q += f" GRANT SELECT, DELETE ON {D}.avis TO {U};"
q += f" GRANT SELECT ON {D}.demandes_suppression TO {U};"
q += f" GRANT UPDATE (statut,traitee_le) ON {D}.demandes_suppression TO {U};"
# Tableau de bord : lecture seule des événements et des notes.
q += f" GRANT SELECT (id,client_id,nom,debut,fin,statut) ON {D}.evenements TO {U};"
q += f" GRANT SELECT ON {D}.notes_evenement TO {U};"
q += f" GRANT INSERT ON {D}.courriels_a_envoyer TO {U};"
q += f" GRANT INSERT (id_trace,horodatage,type_action,id_utilisateur,details) ON {D}.journal_a_emettre TO {U};"
sql(q)
print('Administration : migration et compte limité prêts. Aucun secret affiché.')

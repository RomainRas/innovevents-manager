"""Migration 012 et comptes limités : profil, demande de suppression et avis du client ; modération des avis par l'équipe."""

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
        raise SystemExit('Provisionnement espace client interrompu. Aucune suppression automatique ; examiner la base.')
    return r.stdout.strip()


# Lit un secret local (.secrets/), jamais affiché.
def secret(name):
    p = ROOT / '.secrets' / name
    if not p.exists():
        with p.open('x', encoding='utf-8') as f:
            f.write(secrets.token_hex(32))
    value = p.read_text(encoding='utf-8').strip()
    if len(value) != 64 or any(c not in '0123456789abcdef' for c in value):
        raise SystemExit('Secret invalide')
    return value


client_pw = secret('mysql-client-space.txt')
review_pw = secret('mysql-reviews.txt')
if (
    sql(
        "SELECT COUNT(*) FROM information_schema.tables WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='demandes_suppression';"
    )
    == '0'
):
    sql((ROOT / 'database/mysql/012_client_space.sql').read_text(encoding='utf-8'))
D = 'innovevents_demo'
# Client connecté : ses informations, ses événements, ses avis et sa demande de suppression ; rien d'autre n'est modifiable.
q = f"CREATE USER IF NOT EXISTS 'ecf_client_space'@'%' IDENTIFIED BY '{client_pw}';"
q += f" GRANT SELECT (id,email,prenom,nom,role,statut) ON {D}.utilisateurs TO 'ecf_client_space'@'%';"
q += f" GRANT UPDATE (prenom,nom) ON {D}.utilisateurs TO 'ecf_client_space'@'%';"
q += f" GRANT SELECT ON {D}.clients TO 'ecf_client_space'@'%';"
q += f" GRANT UPDATE (entreprise,contact_nom,contact_prenom,telephone,adresse_ligne1,adresse_ligne2,code_postal,ville) ON {D}.clients TO 'ecf_client_space'@'%';"
q += f" GRANT SELECT (id,client_id,nom,debut,fin,lieu,statut) ON {D}.evenements TO 'ecf_client_space'@'%';"
q += f" GRANT SELECT ON {D}.avis TO 'ecf_client_space'@'%';"
q += f" GRANT INSERT (evenement_id,auteur_id,contenu) ON {D}.avis TO 'ecf_client_space'@'%';"
q += f" GRANT SELECT ON {D}.demandes_suppression TO 'ecf_client_space'@'%';"
q += f" GRANT INSERT (utilisateur_id,motif) ON {D}.demandes_suppression TO 'ecf_client_space'@'%';"
q += f" GRANT INSERT ON {D}.courriels_a_envoyer TO 'ecf_client_space'@'%';"
q += f" GRANT INSERT (id_trace,horodatage,type_action,id_utilisateur,details) ON {D}.journal_a_emettre TO 'ecf_client_space'@'%';"
# Employés et administrateur : lecture des avis et décision de modération uniquement.
q += f" CREATE USER IF NOT EXISTS 'ecf_reviews'@'%' IDENTIFIED BY '{review_pw}';"
q += f" GRANT SELECT ON {D}.avis TO 'ecf_reviews'@'%';"
q += f" GRANT UPDATE (statut,moderateur_id,modere_le) ON {D}.avis TO 'ecf_reviews'@'%';"
q += f" GRANT SELECT (id,nom,debut,fin,statut,visible_demande,accord_publication) ON {D}.evenements TO 'ecf_reviews'@'%';"
q += f" GRANT SELECT (id,prenom,nom) ON {D}.utilisateurs TO 'ecf_reviews'@'%';"
sql(q)
print('Espace client : migration et comptes limités prêts. Aucun secret affiché.')

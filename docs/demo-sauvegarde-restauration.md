# Démonstration et restauration — 10 novembre 2026

## Résultat obtenu

Une base **innovevents_demo** existe dans chacun des deux moteurs locaux. Elle est distincte de **innovevents_test**, qui reste réservée aux tests de contraintes. Le jeu permanent est fictif :

- Chloé administratrice, José employé avec accès mobile, Alice cliente ;
- une fiche cliente et une demande prospect à contacter ;
- trois événements non publics, trois devis avec leur version brouillon et une prestation indicative chacun ;
- trois tâches, trois notes d’événement et une note globale ;
- quatre journaux MongoDB de création, insérés par le script de démonstration.

Les événements sont datés de 2030, sans publication, sans devis transmis et sans PDF fictivement déclaré disponible. Les montants de prestations sont illustratifs ; aucun taux de TVA par défaut n’a été ajouté. Ce jeu n’est pas encore la couverture exhaustive des scénarios métier (acceptation, refus, avis, échecs de livraison, etc.).

## Comptes locaux

Identifiants : `chloe@example.invalid`, `jose@example.invalid`, `alice@example.invalid`. Un mot de passe aléatoire commun **réservé à cette démonstration locale** est conservé dans `.secrets/demo-password.txt`. Les empreintes bcrypt sont générées par PHP et vérifiées avec `password_verify` sur les trois valeurs relues en base. Le mot de passe n’est pas affiché ni commité ; ne pas joindre `.secrets` au rendu. Les comptes ne prouvent pas une connexion dans l’application : l’écran et le service d’authentification restent à développer.

## Procédure reproductible

Docker Desktop démarré, Docker, Python 3 et PHP CLI accessibles depuis la racine du dépôt :

```powershell
./scripts/Start-Databases.ps1
# Une fois seulement, si aucune base innovevents_demo n’existe :
python scripts/prepare_demo_backup.py
# Pour refaire une sauvegarde et sa restauration après création :
python scripts/prepare_demo_backup.py --backup-only
```

`DOCKER_BIN` et `PHP_BIN` permettent de fournir les chemins des exécutables. PHP 8.2.12 du poste a seulement servi à générer/vérifier bcrypt ; cela ne valide pas encore l’environnement applicatif PHP 8.4 prévu.

La première commande Python refuse de remplacer une démo existante, même partiellement créée. En cas d’échec partiel, conserver les bases et examiner le diagnostic local `tmp/demo-backup-error.log` avant une reprise. L’option `--backup-only` exige les deux bases existantes et les trois comptes de la démo initiale ; ce n’est pas encore un outil générique de sauvegarde de production.

Exécuter sans écritures concurrentes et sans lancer deux essais en parallèle. Il n’existe pas de transaction atomique entre MySQL et MongoDB : cet essai local au repos ne garantit pas une sauvegarde cohérente d’un service en activité.

## Sauvegarde et contrôles

Le lanceur crée un dossier local `backups/innovevents_restore_<identifiant>/` contenant :

- `mysql.sql` : schéma et données, export MySQL avec transaction cohérente ;
- `mongodb.archive.gz` : archive BSON et métadonnées produites par mongodump ;
- `verification.json` : empreintes SHA-256 et résultats des comparaisons.

Chaque restauration utilise une **nouvelle base** dans chaque moteur. Aucun remplacement de la source, aucune suppression automatique de base. Le SQL de l’archive peut temporairement désactiver ses contrôles de références pour importer les dépendances cycliques : cela reste limité à la nouvelle base de restauration. Ce n’est pas une modification du script SQL manuscrit.

Contrôles réussis le 10 novembre :

- export SQL après restauration strictement identique à l’export de départ : schéma et données des seize tables ;
- quatre documents MongoDB identiques, avec leurs types BSON, ainsi que validateur et index ;
- exports/snapshots des sources inchangés à la fin ;
- mot de passe local compatible avec les trois empreintes réellement stockées.

Un premier essai a créé `innovevents_restore_e6372452deb5`. Un deuxième essai avec `--backup-only` vérifie que l’opération peut être répétée sans réinitialiser la démo. Les bases restaurées et archives restent disponibles localement pour inspection ; leur nettoyage devra être explicite. Les archives contiennent notamment des empreintes de mots de passe : elles restent exclues de Git, avec les secrets.

## Fichiers à conserver pour le dossier

- [Jeu SQL écrit à la main](../database/mysql/002_demo.sql).
- [Lanceur Python](../scripts/prepare_demo_backup.py), [outil MongoDB](../scripts/mongo-demo-backup.js), [export MySQL](../docker/mysql/dump.sh).
- [Preuve sans mots de passe](preuves/restauration-2026-11-10.json) et ce compte rendu.

Cette preuve complète les [tests de contraintes](verification-bases.md). Restent les droits applicatifs restreints, les règles PHP, la concurrence, la reprise inter-bases, les fichiers PDF et une vraie sauvegarde hors poste. Une copie située sur le même ordinateur ne protège pas d’une perte du poste. Les tâches #6 et #7 restent ouvertes pour leurs autres livrables.

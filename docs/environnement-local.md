# Environnement local des bases — préparation du 9 novembre 2026

> **Note du 15 novembre 2026 :** ce document décrit l’état du projet à sa date de rédaction. Les éléments qu’il signale comme « à développer » ont pour la plupart été réalisés depuis ; l’état final, avec les limites encore valables, est dans le [bilan des fonctionnalités](bilan-fonctionnalites.md).

Cette étape prépare la tâche #7. Elle installe les bases de test, pas encore le serveur PHP, Vue ou la construction mobile. Le [guide des scripts](../database/README.md) décrit les contraintes et les limites des vérifications.

## Prérequis Windows

Docker Desktop avec le moteur de conteneurs Linux et WSL 2 est nécessaire pour cette configuration. Contrôler `wsl --version`, `docker version` et `docker compose version` après installation ; une commande Docker présente sans moteur démarré ne suffit pas. Les prérequis, dont virtualisation, mémoire et versions Windows/WSL, sont détaillés dans la [documentation Docker officielle](https://docs.docker.com/desktop/setup/install/windows-install/).

Diagnostic initial : Windows 11 Professionnel, build 26300 ; Docker absent des emplacements vérifiés et de winget ; WSL signale ne pas être installé. La virtualisation firmware est signalée active ; le champ CIM SLAT est signalé faux, mais le démarrage effectif du moteur Linux a ensuite confirmé son fonctionnement. Un client MariaDB/XAMPP existe mais ne valide pas le schéma MySQL 8.4. Aucune modification de XAMPP n'est nécessaire.

L'installation WSL intégrée a échoué. Le paquet officiel Microsoft.WSL 2.7.14 a été vérifié par winget ; sa première installation a échoué avec le code `0x80073d28` demandant les droits administrateur. La tentative avec élévation Windows a réussi (code 0) ; `wsl --version` confirme 2.7.14.0 et `wsl --status` indique WSL 2 par défaut. Docker Desktop 4.99.0 est installé et démarré : moteur 29.9.3, Compose 5.7.0. MySQL 8.4.13 et MongoDB 8.0.34 sont opérationnels, contrôles de santé réussis. Aucun redémarrage automatique n’a été demandé.

## Démarrage depuis la racine du dépôt

Une fois Docker Desktop opérationnel :

```powershell
./scripts/Start-Databases.ps1
```

Le script vérifie le moteur, crée les secrets manquants sans écraser ceux qui existent, valide Compose puis attend la santé des deux services. Il ne supprime aucun volume. Si la politique PowerShell bloque le fichier, examiner et autoriser le script selon la politique du poste ; aucune modification globale de la politique d'exécution n'est prévue ici.

La [configuration Compose](../compose.yaml) utilise les images officielles `mysql:8.4` et `mongo:8.0`, trois volumes nommés du projet `innovevents-ecf-db`, et la base `innovevents_test` dans chacun des deux moteurs. Aucun port n'est publié sur Windows, ce qui évite les conflits avec XAMPP ; les commandes de contrôle passent par `docker compose exec`. Les scripts du dépôt sont montés en lecture seule pour la première initialisation.

Les empreintes SHA-256 des images réellement testées sont figées dans Compose. Les mises à jour devront être explicites et vérifiées.

## Secrets locaux

`scripts/Initialize-LocalSecrets.ps1` crée des valeurs aléatoires dans `.secrets/`, exclu de Git. Les services lisent les mots de passe via des fichiers montés ; les valeurs ne sont pas affichées. Ces fichiers restent des secrets locaux en clair sur disque, à protéger par les droits du poste, et ne doivent pas être joints au rendu ni partagés.

Le compte MySQL root et le compte MongoDB `ecf_admin` servent uniquement à l'initialisation et aux contrôles de cet environnement local. L'application future devra utiliser des comptes restreints distincts. Il ne s'agit pas d'une configuration de production. Les services ne redémarrent pas automatiquement après arrêt du poste.

Ne pas régénérer les secrets sur des volumes déjà initialisés : les images ne réinitialisent pas les mots de passe existants au redémarrage. Conserver les fichiers locaux ou prévoir une rotation explicite.

## Contrôle après démarrage

```powershell
docker compose ps
docker compose exec mysql sh /opt/innovevents/client.sh -e 'SELECT VERSION(); SHOW TABLES FROM innovevents_test;'
docker compose exec mongodb mongosh --quiet --file /opt/innovevents/healthcheck.js
```

Le contrôle MySQL attend seize tables. Celui de MongoDB s'authentifie et vérifie la collection, son validateur et les quatre index attendus. Cela prouve au mieux une initialisation structurelle, pas la justesse des règles métier, des droits ou des scénarios de panne.

## Arrêter et diagnostiquer

```powershell
docker compose stop
docker compose ps
docker compose logs --tail 80 mysql mongodb
```

Les volumes sont conservés. Ne pas ajouter `--volumes` à une commande d'arrêt/nettoyage pour corriger une erreur sans examiner les données. Les scripts d'initialisation ne sont exécutés qu'à la création des volumes de données ; une modification ultérieure du SQL demande une migration ou une nouvelle base de test explicitement choisie. Un démarrage partiel doit être examiné, pas déclaré réussi parce qu'un conteneur tourne.

## Vérifications et suite

Secrets générés localement et exclusion Git vérifiée ; aucune valeur affichée. Syntaxe JavaScript du contrôle MongoDB vérifiée. La configuration Compose, les imports réels et les tests de contraintes ont été validés sur les moteurs. La restauration reste à tester. Le rendu visuel du MCD et les diagrammes de cas d'utilisation/séquence restent également à traiter dans #6.

Sources d'initialisation et de secrets : [image officielle MySQL](https://hub.docker.com/_/mysql), [image officielle MongoDB](https://hub.docker.com/_/mongo), [commandes WSL Microsoft](https://learn.microsoft.com/en-us/windows/wsl/basic-commands).

## État de Git local

Le connecteur GitHub permet les sauvegardes sur dev. La commande Git locale échoue sans interaction car aucun identifiant utilisable n’est disponible (`could not read Username`). La branche locale contient une sauvegarde antérieure équivalente en partie aux commits distants créés par le connecteur. Avant toute poussée locale, authentifier Git, récupérer dev, comparer les fichiers et réconcilier les historiques sans forcer la branche distante. Les modifications locales et les secrets doivent être préservés. Aucune fusion sur main effectuée.

## Tests exécutables

Voir [le bilan détaillé](verification-bases.md). Depuis la racine : `./scripts/Test-Databases.ps1` (Python 3 nécessaire, chemin personnalisable via `-Python`). Les comptes et journaux du jeu de test sont temporaires ; aucun compte de démonstration utilisable n’est créé.

Sous Windows, MySQL ignore les fichiers de configuration montés avec des droits trop ouverts. Le lanceur `docker/mysql/client.sh` copie le secret dans un fichier temporaire privé, puis le supprime à la sortie. Aucune valeur n’est affichée.

## Mise à jour du 10 novembre 2026

Le jeu permanent existe désormais dans innovevents_demo, distinct de la base de tests. Les sauvegardes et restaurations MySQL/MongoDB ont réussi dans de nouvelles bases, y compris un deuxième essai sans réinitialisation. Voir [le guide et les preuves](demo-sauvegarde-restauration.md). Les mentions précédentes de restauration ou de comptes de démonstration « à préparer » décrivent l’état du 9 novembre ; les scénarios métier complets, les droits applicatifs et la reprise inter-bases restent à développer.

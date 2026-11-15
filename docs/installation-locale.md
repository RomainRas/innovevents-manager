# Installation et exécution en local

Vérifiée sur Windows 11 avec Docker Desktop. L’historique détaillé de la mise en place est dans [environnement-local.md](environnement-local.md) et [environnement-application.md](environnement-application.md).

## Prérequis

| Outil | Version vérifiée | Utilité |
| --- | --- | --- |
| Docker Desktop (moteur Linux, WSL 2) | moteur 29, Compose 5 | Toute l’application : bases, API PHP, serveurs Vite, Mailpit |
| Python | 3.12 ou plus (vérifié 3.14) | Scripts de démonstration, migrations, tests fonctionnels |
| PowerShell | Windows PowerShell 5.1 | Scripts de démarrage |
| Node.js + Playwright (Chromium) | Node 22, Playwright 1.5x | Tests de bout en bout uniquement : `npx playwright install chromium` |

| PHP CLI (8.x, par exemple celui de XAMPP) | 8.2 | **Uniquement** pour la création initiale de la démonstration (empreintes bcrypt des mots de passe) ; chemin indiqué par `PHP_BIN` s’il n’est pas dans le `PATH` |

Aucun PHP, MySQL, MongoDB ni Node n’est nécessaire sur le poste pour faire tourner l’application : tout s’exécute dans les conteneurs.

## Première installation

Depuis la racine du dépôt cloné :

```powershell
./scripts/Initialize-LocalSecrets.ps1
./scripts/Start-Databases.ps1
py -3 -X utf8 scripts/prepare_demo_backup.py
./scripts/Start-App.ps1 -Python (Get-Command py).Source
```

1. `Initialize-LocalSecrets.ps1` crée des mots de passe aléatoires dans `.secrets/` (exclu de Git).
2. `Start-Databases.ps1` démarre MySQL 8.4 et MongoDB 8.0 et attend qu’ils soient sains.
3. `prepare_demo_backup.py` crée **une seule fois** le jeu de démonstration (`database/mysql/001_schema.sql`, `002_demo.sql`), puis le sauvegarde et le restaure dans une nouvelle base pour le vérifier.
4. `Start-App.ps1` applique les migrations et comptes limités (`scripts/provision-*.py`), installe les dépendances PHP et JavaScript dans les conteneurs et démarre l’API, les traitements de fond, Mailpit, le site et l’application mobile.

Si `python` ouvre le Microsoft Store au lieu de Python, passer le chemin réel avec `-Python` (exemple ci-dessus avec le lanceur `py`, ou `-Python "$env:LOCALAPPDATA\Programs\Python\Python314\python.exe"`).

## Adresses locales

| Adresse | Contenu |
| --- | --- |
| http://127.0.0.1:5173 | Site (public, espaces client, équipe, administration) |
| http://127.0.0.1:5173/mobile/ | Application mobile (ouvrir avec l’émulation téléphone du navigateur) |
| http://127.0.0.1:8025 | Mailpit : tous les courriels envoyés en local (aucun ne sort) |

Comptes : `chloe@example.invalid` (administratrice), `jose@example.invalid` (employé, accès mobile), `alice@example.invalid` (cliente). Mot de passe commun : `.secrets/demo-password.txt`.

## Démarrages suivants

```powershell
./scripts/Start-App.ps1 -Python (Get-Command py).Source
```

Le script est idempotent : il ne recrée ni n’efface aucune donnée.

## Tests

```powershell
py -3 -X utf8 scripts/run-tests.py
py -3 -X utf8 scripts/run-tests.py --coverage
```

Tous les tests (unitaires PHPUnit, fonctionnels HTTP, de bout en bout Playwright) s’exécutent contre l’application locale ; chacun restaure ses données. Résultats : `tmp/tests/resultats.json` ; couverture : `tmp/coverage/html/index.html`. Si Playwright n’est pas installé globalement, indiquer son dossier dans `PLAYWRIGHT_MODULE`. Détail : [plan de tests](plan-de-tests.md).

## Arrêter

```powershell
docker compose -f compose.yaml -f compose.app.yaml stop
```

`stop` conserve les données. Ne pas utiliser `down -v`, qui supprime les volumes (bases, sessions, PDF).

## Problèmes rencontrés et solutions

| Symptôme | Cause | Solution |
| --- | --- | --- |
| Docker Desktop : « initializing Secrets Engine … engine.sock … cannot be accessed » | Fichier de connexion laissé par un arrêt brutal (disque plein, coupure) que Windows ne sait pas supprimer | Quitter Docker Desktop (**ne pas** choisir « Reset to factory defaults », qui efface les volumes), puis `wsl -d docker-desktop -u root sh -c "rm -f /mnt/host/c/Users/<utilisateur>/AppData/Local/docker-secrets-engine/engine.sock"` et relancer Docker Desktop. |
| Une modification d’un fichier Vue n’apparaît pas | Sous Windows, le dossier monté ne transmet pas toujours les notifications de modification au conteneur | `docker compose -f compose.yaml -f compose.app.yaml restart web` (l’application mobile scrute ses fichiers et n’est pas concernée). |
| Page blanche, erreurs « 504 Outdated Optimize Dep » | Cache de dépendances Vite périmé après un redémarrage | Même commande : redémarrer `web`. |
| Connexion refusée (« Trop de tentatives ») après de nombreux essais | Limiteur : 10 connexions par compte en 15 minutes | Attendre, ou en local uniquement : `docker compose -f compose.yaml -f compose.app.yaml exec api rm -f /var/lib/innovevents/login-rate.json` |
| Disque presque plein | Les images Docker et les volumes occupent plusieurs Go | `docker image prune` ; ne jamais supprimer les volumes du projet. |

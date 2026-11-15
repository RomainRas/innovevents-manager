# Environnement PHP et Vue

> **Note :** ce document retrace l’étape où cette fonctionnalité a été développée. Les éléments qu’il signale comme « à développer » ont pour la plupart été réalisés depuis ; l’état final, avec les limites encore valables, est dans le [bilan des fonctionnalités](bilan-fonctionnalites.md).

## Périmètre

Liaison Vue → API PHP native → MySQL/MongoDB, connexion avec contrôle des rôles, inscription et récupération de compte. L’écran d’accueil reste provisoire. Les comptes techniques de base sont distincts des comptes Chloé, José et Alice.

La route `GET /api/health` renvoie un état sans donnée métier ni détail de configuration. Les routes d’authentification et les premières lectures privées sont décrites dans [Authentification](authentification-sessions.md) et [Parcours de compte](parcours-comptes.md). Les chemins inconnus renvoient 404, les méthodes non autorisées 405. Une panne interne renvoie 503 sans détail technique.

## Démarrage local

Prérequis : Docker Desktop opérationnel, Python 3 accessible, jeu `innovevents_demo` créé selon [la procédure de démonstration](demo-sauvegarde-restauration.md). Depuis la racine :

```powershell
./scripts/Start-App.ps1
```

`-Python` permet de choisir le chemin Python. Docker doit être dans PATH ; rouvrir le terminal après son installation. Le lanceur prépare les comptes, construit PHP, installe les dépendances verrouillées et démarre les services. Adresse locale : **http://127.0.0.1:5173/**. Les bases et PHP n’ont pas de port publié ; Vite relaie `/api` vers PHP sur le réseau Docker. L’adresse est réservée au poste, pas un hébergement à remettre au jury.

```powershell
docker compose -f compose.yaml -f compose.app.yaml ps
docker compose -f compose.yaml -f compose.app.yaml logs --tail 50 api web
docker compose -f compose.yaml -f compose.app.yaml stop api web
```

Les bases et leurs volumes ne sont pas supprimés. L’ensemble utilise le même projet Compose que les bases ; fournir les deux fichiers pour gérer l’application. Le serveur PHP intégré et Vite sont ici des serveurs de développement, pas la configuration de production.

## Accès aux données

- MySQL : `ecf_api_read`, droit SELECT uniquement sur `innovevents_demo.*`, sans droits d’écriture ni accès aux comptes du serveur.
- MongoDB : `ecf_api_read` dans `innovevents_demo`, rôle personnalisé autorisant seulement `find` sur `logs` (dont les opérations de lecture associées).
- L’API reçoit uniquement `.secrets/mysql-api.txt` et `.secrets/mongo-api.txt`. Aucun secret administrateur n’est monté dans le service PHP, aucun secret n’est envoyé au navigateur.

Ces comptes de lecture suffisent pour cette étape ; les permissions d’écriture seront ajoutées par fonctionnalité et testées. Les autorisations métier par utilisateur restent à développer en PHP. Le provisionnement ne change pas implicitement les mots de passe existants ; une divergence entre compte et fichier secret provoquera un échec de connexion à traiter explicitement.

## Organisation et dépendances

- `backend/public/index.php` : entrée HTTP et réponses JSON ;
- `backend/src/Service/Readiness.php` : vérification des deux connexions ;
- `backend/src/Infrastructure/Connections.php` : PDO et bibliothèque officielle MongoDB ;
- `frontend/src/` : Vue et Vue Router ; `vite.config.js` définit le relais local ;
- `composer.lock` et `package-lock.json` : versions réellement installées ;
- images PHP, Composer et Node fixées par SHA-256 ; extension MongoDB 2.5.7 explicitement fixée.

Les paquets système installés lors de la construction viennent des dépôts Debian : leurs versions peuvent évoluer malgré les images de base figées. La construction n’est donc pas déclarée reproductible octet pour octet. PECL est utilisé ici pour la version figée du pilote ; la documentation PHP recommande désormais PIE pour les nouvelles installations.

## Contrôles reproductibles

```powershell
python scripts/check-app.py
docker compose -f compose.yaml -f compose.app.yaml exec -T web npm run build
```

Le premier script vérifie les accès autorisés et refusés, les réponses HTTP, puis arrête temporairement MongoDB pour tester le 503 et le redémarre dans un bloc `finally`. Ne pas l’exécuter pendant d’autres travaux sur cette base. Résultat local : `tmp/app-tests.json`.

`tests/app/browser.cjs` contrôle l’affichage et le bouton dans Chromium en 1280 et 390 pixels, avec absence de débordement horizontal et d’erreur JavaScript. Il nécessite Playwright installé sur le poste ; `PLAYWRIGHT_MODULE` et `CHROMIUM_PATH` permettent de fournir les chemins. Captures et résultat dans `tmp/app-browser/`. Il ne valide pas l’application mobile Android.

## Résultats

PHP 8.4.27, Composer 2.11.1, extension MongoDB 2.5.7 et bibliothèque 2.5.1 ; Node 24.22.2, Vue 3.5.45, Vue Router 4.6.4, Vite 8.3.7 et plugin Vue 6.0.10 réellement installés. La compilation Vue réussit. Composer valide le manifeste avec des avertissements sur l’absence de licence déclarée et le choix volontaire d’une version exacte du pilote.

Les douze contrôles du lanceur passent : deux lectures, quatre refus d’accès/écriture, disponibilité HTTP, 405, 404, document Vue, 503 pendant la panne et retour à 200. Chromium valide les deux largeurs, le bouton de relance et l’absence d’erreur JavaScript ; la capture mobile a été relue visuellement. [Preuve conservée](preuves/environnement.json).

Le premier essai a corrigé le nom de la table de paramètres dans le contrôle de disponibilité et l’option de lecture des privilèges MongoDB lors d’un reprovisionnement. Pendant l’arrêt d’un conteneur, la résolution réseau peut prendre plus de temps que le délai de sélection du pilote : le contrôle HTTP attend jusqu’à 30 secondes ; l’interface affiche son échec au bout de 5 secondes et permet de réessayer. Aucun objectif de performance n’est déclaré atteint.

## Suite du développement

Authentification et sessions, autorisations par rôle, pages et règles métier, écritures avec droits minimaux, tests PHPUnit/Vitest, construction Android et hébergement restent à traiter. La tâche #7 reste ouverte notamment pour Git local et la vérification mobile. Le suivi distingue les résultats réellement obtenus de ces étapes futures.

Sources consultées : [Vite](https://vite.dev/guide/), [image PHP officielle](https://hub.docker.com/_/php), [pilote MongoDB PHP](https://www.php.net/manual/en/mongodb.installation.php), [version du pilote](https://pecl.php.net/package/mongodb), [actions de privilèges MongoDB](https://www.mongodb.com/docs/manual/reference/privilege-actions/).

## Mise à jour — Authentification

La connexion web, les sessions, la déconnexion et les premières lectures protégées sont désormais réalisées : [guide et limites](authentification-sessions.md). Un compte MongoDB distinct possède uniquement insert sur logs ; le service PHP reçoit un troisième secret local et utilise le volume auth_state pour les sessions et quotas. Relancer Start-App.ps1 pour provisionner ce compte avant le démarrage. Les résultats ci-dessus datés restent l’historique du socle initial ; la preuve d’authentification est conservée séparément. Inscription, récupération/changement effectif du mot de passe et mobile restent à réaliser.
## Courriels locaux

Start-App applique aussi la migration comptes/courriels, démarre Mailpit (127.0.0.1:8025) et le traitement de la file. Aucun envoi externe. Voir [le guide](parcours-comptes.md).

## Demandes de devis

Start-App exécute désormais aussi `provision-quotes.py` : suivi des soumissions, type de courriel et comptes SQL limités. La base de démonstration contient 18 tables après cette migration. Formulaire public `/demande-devis`, paramètre administrateur `/parametres-demandes`. [Bilan et tests](implementation-demande-devis.md).

## Qualification des prospects

Start-App exécute `provision-prospects.py` après le provisionnement des demandes. La migration 005 étend les types de courriels ; un compte SQL séparé limite la lecture et les modifications des prospects. Le nombre de tables reste inchangé. [Utilisation et vérifications](qualification-prospects.md).

## Conversion et journalisation

Start-App exécute `provision-conversion.py` : ajout de l’empreinte de conversion et comptes MySQL limités. `journal_worker` livre les intentions de la table existante vers MongoDB avec reprise et déduplication. Le nombre de tables reste 18. [Fonctionnement, permissions, essais et limites](conversion-clients.md).

## Brouillons d’événements

Start-App exécute `provision-events.py` : migration 007 (empreinte et révision), compte SQL limité et secret API. Le nombre de tables reste 18. [Parcours et vérifications](evenements-brouillons.md). La restauration complète après les migrations récentes reste à vérifier.

## Images des brouillons

Start-App exécute `provision-event-images.py`, puis reconstruit PHP avec GD (JPEG/PNG). Aucun changement de schéma. Les images privées sont stockées dans le volume `auth_state`, hors du dossier web ; ce volume n’est pas inclus dans les sauvegardes de bases existantes. [Fonctionnement et conservation](images-evenements.md).


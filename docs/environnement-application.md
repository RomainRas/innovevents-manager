# Environnement PHP et Vue — 10 novembre 2026

## Périmètre

Première liaison Vue → API PHP native → MySQL/MongoDB. L’écran de préparation ne remplace pas les maquettes validées ; il permet de contrôler la connexion avant d’implémenter les pages métier. Aucun écran de connexion ni droit utilisateur n’est encore développé. Les comptes techniques de base sont distincts des comptes Chloé, José et Alice.

L’API propose uniquement `GET /api/health`, qui renvoie un état sans donnée métier ni détail de configuration. Les chemins inconnus renvoient 404, les méthodes autres que GET sur cette route renvoient 405. Si une base est indisponible, la réponse est 503 sans message interne.

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

## Résultats du 10 novembre

PHP 8.4.27, Composer 2.11.1, extension MongoDB 2.5.7 et bibliothèque 2.5.1 ; Node 24.22.2, Vue 3.5.45, Vue Router 4.6.4, Vite 8.3.7 et plugin Vue 6.0.10 réellement installés. La compilation Vue réussit. Composer valide le manifeste avec des avertissements sur l’absence de licence déclarée et le choix volontaire d’une version exacte du pilote.

Les douze contrôles du lanceur passent : deux lectures, quatre refus d’accès/écriture, disponibilité HTTP, 405, 404, document Vue, 503 pendant la panne et retour à 200. Chromium valide les deux largeurs, le bouton de relance et l’absence d’erreur JavaScript ; la capture mobile a été relue visuellement. [Preuve conservée](preuves/environnement-2026-11-10.json).

Le premier essai a corrigé le nom de la table de paramètres dans le contrôle de disponibilité et l’option de lecture des privilèges MongoDB lors d’un reprovisionnement. Pendant l’arrêt d’un conteneur, la résolution réseau peut prendre plus de temps que le délai de sélection du pilote : le contrôle HTTP attend jusqu’à 30 secondes ; l’interface affiche son échec au bout de 5 secondes et permet de réessayer. Aucun objectif de performance n’est déclaré atteint.

## Suite du développement

Authentification et sessions, autorisations par rôle, pages et règles métier, écritures avec droits minimaux, tests PHPUnit/Vitest, construction Android et hébergement restent à traiter. La tâche #7 reste ouverte notamment pour Git local et la vérification mobile. Le suivi distingue les résultats réellement obtenus de ces étapes futures.

Sources consultées le 10 novembre : [Vite](https://vite.dev/guide/), [image PHP officielle](https://hub.docker.com/_/php), [pilote MongoDB PHP](https://www.php.net/manual/en/mongodb.installation.php), [version du pilote](https://pecl.php.net/package/mongodb), [actions de privilèges MongoDB](https://www.mongodb.com/docs/manual/reference/privilege-actions/).

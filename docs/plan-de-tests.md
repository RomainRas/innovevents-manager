# Plan de tests et résultats

Exigence : environnement de test complet, une fonctionnalité vérifiée en intégralité par des tests **unitaires, fonctionnels et de bout en bout**, couverture du code et tableau de tous les tests par fonctionnalité.

## Environnement de test

| Élément | Choix |
| --- | --- |
| Application testée | L’application complète en local (`compose.yaml` + `compose.app.yaml`) : MySQL, MongoDB, API PHP, traitements de fond, Mailpit, site et application mobile. Bases de démonstration distinctes de la production. |
| Tests unitaires | PHPUnit 12 (`backend/tests/Unit`, configuration `backend/phpunit.xml.dist`), sans base de données. |
| Tests fonctionnels | Scripts Python (`tests/app/*.py`) qui appellent l’API HTTP comme le navigateur (cookies de session, jeton CSRF), vérifient les réponses, la base (MySQL) et le journal (MongoDB), puis **restaurent les données**. |
| Tests de bout en bout | Playwright / Chromium (`tests/app/*-browser.cjs`) : parcours réels dans le navigateur, à 1280 px et 390 px, téléphone émulé pour l’application mobile ; vérifient aussi le focus clavier, les messages d’erreur et l’absence de débordement horizontal. |
| Courriels | Mailpit capte tous les envois ; les tests lisent les courriels reçus (identifiants, notifications). |
| Couverture | PCOV + php-code-coverage : chaque requête traitée par l’API pendant les tests fonctionnels et de bout en bout enregistre les lignes exécutées, fusionnées avec celles des tests unitaires par phpcov. |
| Lanceur unique | `py -3 -X utf8 scripts/run-tests.py [--coverage]` : exécute toutes les suites dans l’ordre, remet à zéro le limiteur de connexion local entre deux suites, écrit `tmp/tests/resultats.json` et le rapport `tmp/coverage/html/index.html`. |
| Intégration continue | À chaque push sur `dev` et `main` (GitHub Actions) : syntaxe PHP, tests unitaires PHPUnit avec couverture, calculs de devis, audit de sécurité des dépendances, compilations du site et de l’application mobile, configuration Compose, scripts shell. Le déploiement n’a lieu que si tout réussit. |

## Fonctionnalité vérifiée en intégralité : gestion des événements par l’administratrice

| Niveau | Fichier | Ce qui est vérifié |
| --- | --- | --- |
| Unitaire | `backend/tests/Unit/EventValidationTest.php`, `EventScheduleTest.php` | Règles de saisie (nom obligatoire, fin après le début, participants entiers positifs, longueurs, caractères de contrôle, erreurs groupées) ; conversion heure de Paris ⇄ UTC, dates invalides, heure inexistante ou ambiguë aux changements d’heure |
| Fonctionnel | `tests/app/admin-records.py` | Droits (employé refusé, CSRF), création sans demande préalable, contrôle de révision, statuts (transitions, annulation définitive, dates requises), tâches, suppressions protégées (devis), journal `CREATION_EVENEMENT` et `CHANGEMENT_STATUT_EVENEMENT` livré dans MongoDB, droits SQL du compte limité |
| Bout en bout | `tests/app/admin-records-browser.cjs` | Parcours de Chloé : création de la fiche client puis de l’événement, changement de statut, tâche ajoutée puis supprimée, modification, événement avec devis protégé, suppressions ; focus, messages et affichage à 1280 et 390 px |

## Tableau des tests par fonctionnalité

| Fonctionnalité | Unitaire | Fonctionnel | Bout en bout |
| --- | --- | --- | --- |
| Connexion, sessions, journal des connexions | — | `authentication.py`, `auth-outage.py` (panne MongoDB) | `auth-browser.cjs` |
| Création de compte, mot de passe oublié et changement | — | `accounts.py` | `accounts-browser.cjs` |
| File des courriels, reprise après incident | — | `mail-recovery.py` | — |
| Demande de devis publique | — | `quote-requests.py` | `quote-browser.cjs` |
| Qualification des prospects | — | `prospects.py` | `prospects-browser.cjs` (lancé par la suite fonctionnelle) |
| Conversion en client (journal `CREATION_CLIENT`) | — | `conversion.py` | `conversion-browser.cjs` (idem) |
| Événement créé depuis une demande | `EventScheduleTest` | `events.py` | `events-browser.cjs` (idem) |
| Images des événements | — | `event-images.py` | `event-images-browser.cjs` (idem) |
| Prestations et calcul du devis | `QuoteAmountsTest`, `backend/tests/quote-amounts.php` | `quote-drafts.py` | `quote-drafts-browser.cjs` (idem) |
| Devis PDF (journal `GENERATION_PDF_DEVIS`) | — | `quote-pdf.py` | `quote-pdf-browser.cjs` (idem) |
| Transmission du devis, espace client | — | `quote-transmission.py` | `quote-transmission-browser.cjs` (idem) |
| Réponse du client, contre-proposition | — | `quote-decisions.py`, `quote-resilience.py` | `quote-decisions-browser.cjs` (idem) |
| Pages publiques, avis validés, contact | — | `public-pages.py` | `public-pages-browser.cjs` |
| Espace client : informations, suppression, avis, modération | — | `client-space.py` | `client-space-browser.cjs` |
| Tableau de bord et comptes (administratrice) | — | `admin-accounts.py` | `admin-accounts-browser.cjs` |
| Espace employé : clients, événements, notes, tâches | — | `team-space.py` | `team-space-browser.cjs` |
| Gestion des fiches clients et des événements | `EventValidationTest`, `ClientValidationTest` | `admin-records.py` | `admin-records-browser.cjs` |
| Application mobile | — | `mobile.py` | `mobile-browser.cjs` (téléphone émulé, hors ligne) |
| Base de données (contraintes, droits) | — | `tests/database/*`, `backend/tests/*-permissions.php` | — |

Chaque suite fonctionnelle contrôle aussi la sécurité de sa fonctionnalité : accès selon le rôle (401/403), jeton CSRF, cloisonnement entre clients (même réponse pour « inexistant » et « appartenant à un autre »), validation des entrées (injection traitée comme texte, caractères de contrôle, longueurs), droits du compte MySQL limité.

## Résultats

Exécution complète (`scripts/run-tests.py --coverage`, environ 60 minutes) : **30 suites sur 30 réussies**.

| Niveau | Suites | Résultat |
| --- | --- | --- |
| Unitaire (PHPUnit) | 1 suite, 48 tests, 81 assertions | Réussi |
| Fonctionnel (HTTP) | 20 suites (dont 8 qui lancent aussi leur parcours navigateur) | Toutes réussies ; détail des contrôles par suite dans les documents de chaque fonctionnalité (ex. 39/39 comptes, 36/36 gestion des fiches, 36/36 espace employé, 32/32 espace client, 31/31 pages publiques, 22/22 mobile) |
| Bout en bout (Playwright) | 9 suites autonomes + 8 lancées par les suites fonctionnelles | Toutes réussies |

**Couverture du code PHP (`backend/src`)** :

| Mesure | Résultat |
| --- | --- |
| Lignes | **98,31 %** (1 341 / 1 364) |
| Méthodes | **92,07 %** (151 / 164) |
| Classes entièrement couvertes | 70,97 % (22 / 31) |

Preuve : `docs/preuves/tests.json` ; rapport détaillé régénérable par `scripts/run-tests.py --coverage`.

## Lecture de la couverture

- **Le pourcentage de lignes est optimiste** : le code PHP est écrit de façon compacte (plusieurs instructions par ligne) et une ligne compte comme couverte dès qu’une partie s’exécute. Le **taux de méthodes (92 %)** est l’indicateur le plus représentatif.
- **Ce qui n’est pas couvert** (13 méthodes) : surtout des branches défensives difficiles à provoquer volontairement — configuration manquante (`Connections`, adresse de l’agence invalide), course entre deux requêtes simultanées, échecs d’écriture de fichiers d’image — et `MailQueue::decode`, exécutée par le traitement de fond `mail_worker`, un processus séparé que la mesure ne suit pas (son comportement est néanmoins vérifié par `mail-recovery.py` et `quote-resilience.py`, qui contrôlent les courriels réellement reçus).
- **Hors mesure** : le code JavaScript (site et application mobile), vérifié par les tests de bout en bout mais sans pourcentage ; les traitements de fond (`backend/bin/`).
- Les tests ne s’exécutent jamais contre la production : la base de démonstration locale est restaurée par chaque suite.

## Problèmes trouvés grâce aux tests (exemples)

- Mot de passe resté affiché quelques instants après un échec de connexion pendant la vérification de session : effacé immédiatement depuis.
- Messages de confirmation affichés avant le rechargement des listes (tâches, comptes, avis) : l’ordre a été corrigé.
- Adresse du client de démonstration effacée par la restauration d’un ancien test : restauration rendue exacte.
- Note de 5 000 caractères accentués refusée (requête de plus de 8 Ko) : limite relevée pour les notes.
- Application mobile hors ligne renvoyée vers la connexion : reprise du dernier utilisateur pour la consultation seule.

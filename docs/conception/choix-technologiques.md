# Choix technologiques Innov’Events Manager

J’ai choisi Vue.js pour les interfaces et PHP natif pour le serveur. J’ai déjà pratiqué HTML, CSS, JavaScript, Vue.js, PHP et Symfony : je préfère m’appuyer sur ces acquis pour me concentrer sur la qualité, et j’ai fait le choix d’un serveur PHP sans framework pour maîtriser moi-même chaque couche, les contrôles d’accès et les requêtes SQL.

## Choix retenus

| Partie | Technologie | Utilisation et justification |
|---|---|---|
| Application web | Vue 3, JavaScript, HTML et CSS ; construction avec Vite et navigation avec Vue Router | Réutiliser mes acquis, organiser les écrans en composants, gérer les formulaires et les espaces par rôle |
| Application mobile | Application web installable (PWA) en Vue 3 et Vite, servie par son propre conteneur Docker | Même langage et même API que le site, installable sur Android et iPhone sans magasin d’applications (voir plus bas) |
| Serveur commun | PHP 8.4 natif, programmation orientée objet, API HTTP avec échanges JSON | Rendre visibles les couches, les règles métier et les contrôles d’accès demandés par l’ECF |
| Base relationnelle | MySQL 8.4, accès via PDO | Stocker les comptes, prospects, clients, devis, prestations, événements, tâches, notes et avis avec relations et contraintes |
| Base non relationnelle | MongoDB 8.0, extension PHP et bibliothèque officielle `mongodb/mongodb` | Conserver le journal des actions, dont les détails varient selon l’action, comme le recommande le sujet |
| Dépendances PHP | Composer et chargement automatique des classes | Installation reproductible, versions verrouillées |
| Courriels | PHPMailer avec SMTP, file d’envoi en base | Confirmations, notifications et envoi des devis en pièce jointe, sans perte en cas de panne du serveur de courriel |
| PDF | Dompdf avec un modèle HTML/CSS dédié | Générer les devis à partir des données validées côté serveur |
| Environnement | Docker et Docker Compose | Décrire tous les services, isoler les bases, installer le projet de la même façon en local et en production |
| Serveur web | Caddy | HTTPS automatique (Let’s Encrypt) et en-têtes de sécurité |
| Tests | PHPUnit, PCOV et phpcov ; Python ; Playwright | Tests unitaires et couverture, tests fonctionnels contre l’API et les bases, tests de bout en bout dans un navigateur |
| Suivi et automatisation | GitHub, GitHub Projects et GitHub Actions | Historique, suivi des tâches en Kanban, contrôles automatiques et déploiement à partir de `main` |
| Hébergement | VPS Ubuntu 24.04 chez Hostinger, en France | Un serveur que je maîtrise, nécessaire pour faire tourner MySQL, MongoDB et les traitements de fond |

Les versions des bibliothèques et des images sont fixées par les fichiers de verrouillage Composer et npm et par les images Docker. Sources : [Vue](https://vuejs.org/guide/introduction), [démarrage Vue avec Vite](https://vuejs.org/guide/quick-start.html), [versions PHP](https://www.php.net/supported-versions.php), [MySQL 8.4](https://dev.mysql.com/doc/refman/8.4/en/), [bibliothèque PHP MongoDB](https://www.mongodb.com/docs/php-library/current/).

## Organisation du PHP natif

J’ai organisé le serveur en couches, avec des responsabilités séparées :

1. Entrée HTTP et routage (`Http/Kernel.php`) : une table des routes associe chaque adresse à sa méthode et à son traitement, et produit des réponses JSON cohérentes.
2. Services métier (`Service/`) : ils appliquent les règles de qualification, de devis, de statuts, de notes et de droits.
3. Accès aux données : PDO pour MySQL, avec un compte limité par fonctionnalité, et la bibliothèque MongoDB pour le journal.
4. Services techniques : file de courriels, génération des PDF, sessions, limitation des tentatives et journalisation.

Les scripts de création et les jeux d’essai SQL sont écrits à la main, comme le demande l’énoncé, et versionnés dans `database/mysql/`. Toutes les valeurs passent par des requêtes préparées. Les rares éléments SQL non paramétrables, comme une colonne de tri, viennent d’une liste fixe. Sources : [PDO](https://www.php.net/manual/en/book.pdo.php), [requêtes préparées](https://www.php.net/manual/en/pdo.prepared-statements.php).

PHP natif m’oblige à concevoir moi-même le routage, la validation, les erreurs, l’authentification et les autorisations : c’est ce que les tests vérifient. Pour le PDF et les courriels, j’utilise des bibliothèques reconnues plutôt que de les réécrire. Sources : [PHPMailer](https://github.com/PHPMailer/PHPMailer), [Dompdf](https://github.com/dompdf/dompdf).

## Application mobile : pourquoi une application web installable

Au départ, j’avais prévu une application native Android construite avec Capacitor. Je l’ai remplacée par une **application web installable (PWA)**, pour quatre raisons.

**1. Une application native est très difficile à distribuer, surtout sur iPhone.**

- **iPhone** : on ne peut pas installer une application qui ne vient pas de l’App Store ou de TestFlight. Il faut un compte Apple Developer payant (99 $ par an), un Mac avec Xcode pour compiler, puis la validation d’Apple. Sans tout ça, ni Chloé, ni José, ni le correcteur ne pourraient installer l’application sur un iPhone.
- **Android** : publier sur Google Play demande un compte développeur payant, une période de test fermé avec des testeurs, puis la validation de Google. L’autre solution, envoyer un fichier APK, oblige chaque utilisateur à autoriser l’installation d’applications de source inconnue, ce qu’on ne peut pas demander raisonnablement aux employés d’une agence ni à un correcteur.

Une PWA ne passe par aucun magasin : il suffit d’ouvrir un lien et de l’ajouter à l’écran d’accueil, sur Android comme sur iPhone.

**2. Elle respecte directement la demande du sujet.** L’énoncé demande une application « packagée avec docker ». La PWA est compilée puis servie par son propre conteneur Docker, alors qu’un APK n’aurait été qu’un fichier construit dans Docker puis installé ailleurs.

**3. Les fonctions demandées n’ont pas besoin d’une application native.** Appeler, écrire un courriel et lancer un itinéraire passent par des liens (`tel:`, `mailto:`, Google Maps) que le téléphone ouvre avec ses propres applications. La note rapide passe par la même API que le site.

**4. Une seule application fonctionne partout.** La même base de code tourne sur Android et sur iPhone, reste consultable hors ligne grâce à son service worker, et pourra être emballée plus tard avec Capacitor sans réécriture si l’agence veut une version dans les magasins.

Détails, écrans et tests : [application mobile](../application-mobile.md).

## Sécurité

- Validation des données et autorisations côté PHP pour chaque action ; contrôle de l’appartenance des dossiers et du statut du compte.
- Requêtes préparées PDO, filtres et tris contrôlés, un compte MySQL limité par fonctionnalité.
- Mots de passe hachés avec bcrypt (`password_hash`), jamais stockés en clair. Source : [password_hash](https://www.php.net/manual/en/function.password-hash.php).
- Cookies de session protégés, session renouvelée à la connexion, jeton CSRF sur chaque écriture. Le mobile utilise la même session que le site. Source : [sécurité des sessions PHP](https://www.php.net/manual/en/features.session.security.management.php).
- Textes affichés sans insertion de HTML brut ; images vérifiées et ré-encodées ; PDF sans exécution de code ni ressource externe.
- Secrets générés sur le serveur et exclus de Git.

Le détail des mesures et leur vérification sont dans le [plan de tests](../plan-de-tests.md).

## Tests et déploiement

PHPUnit teste les règles métier ; des suites Python appellent chaque fonctionnalité comme le navigateur et vérifient MySQL et MongoDB ; Playwright rejoue les parcours réels dans un navigateur, y compris l’application mobile sur un téléphone émulé. La couverture du code PHP est mesurée avec PCOV et phpcov. Sources : [PHPUnit](https://phpunit.de/documentation.html), [Playwright](https://playwright.dev/docs/intro).

Docker Compose organise en production Caddy, l’API PHP, l’application mobile, MySQL, MongoDB et les traitements de fond ; seuls les ports 80 et 443 sont publics. GitHub Actions exécute les contrôles à chaque envoi et déploie automatiquement à partir de `main`. Source : [Docker Compose en production](https://docs.docker.com/compose/how-tos/production/). Procédure complète : [déploiement](../deploiement.md).

## Correspondance avec les exigences ECF

| Exigence | Réponse retenue |
|---|---|
| Interfaces web et mobile | Vue pour les deux interfaces ; application mobile installable (PWA) |
| Programmation orientée objet et architecture en couches | PHP natif structuré en routage, services et accès aux données |
| SQL, NoSQL et Docker obligatoires | MySQL avec PDO, MongoDB pour le journal, Docker Compose pour tous les services, y compris le mobile |
| Création SQL écrite à la main | Scripts SQL versionnés, sans génération automatique du schéma |
| PDF et courriels | Dompdf et PHPMailer, appelés depuis les services PHP |
| Tests et couverture | PHPUnit, tests fonctionnels Python et Playwright, avec rapport de couverture |
| Déploiement en ligne et CI/CD | VPS, HTTPS, adresses du site et du mobile, GitHub Actions |

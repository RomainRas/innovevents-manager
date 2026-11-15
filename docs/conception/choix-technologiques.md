# Choix technologiques Innov’Events Manager

Décision du 18 octobre 2026 : utiliser Vue.js pour les interfaces et PHP natif pour le serveur, conformément au choix de Romain. Il a pratiqué HTML, CSS, JavaScript, Vue.js, PHP et Symfony, et souhaite réaliser ce projet avec un serveur PHP sans framework applicatif.

Ce document justifie les technologies retenues. Leur installation, leur compatibilité effective et leur exécution seront vérifiées dans la tâche de mise en place de l’environnement. Aucune application n’est déclarée opérationnelle à ce stade.

> **Révision du 15 novembre 2026 — application mobile.** Le choix initial (Vue + Capacitor, Android) a été remplacé par une **application web installable (PWA)** en Vue, servie par son propre conteneur Docker. Raisons : le sujet impose seulement que l’application soit « packagée avec docker » (ce qu’une PWA respecte directement, alors qu’un APK n’est qu’un fichier construit dans Docker) ; les fonctions demandées (appel, courriel, itinéraire, note rapide) passent par des liens `tel:`, `mailto:` et Google Maps ouverts par les applications du téléphone ; la même application fonctionne sur Android et iOS ; le délai restant ne permettait pas de construire et tester en plus une chaîne Android (SDK, JDK, Gradle). La PWA reste emballable plus tard avec Capacitor sans réécriture. Détails et tests : [application mobile](../application-mobile.md). Les mentions de Capacitor ci-dessous décrivent le choix d’origine.

## Choix retenus

| Partie | Technologie | Utilisation et justification |
|---|---|---|
| Application web | Vue 3, JavaScript, HTML et CSS ; construction avec Vite et navigation avec Vue Router | Réutiliser les acquis de Romain, organiser les écrans en composants et gérer les formulaires et espaces par rôle |
| Serveur commun | PHP 8.4 natif, programmation orientée objet, API HTTP avec échanges JSON | Réutiliser PHP tout en rendant visibles les couches, les règles métier et les contrôles d’accès demandés par l’ECF |
| Base relationnelle | MySQL 8.4, accès via PDO et son pilote MySQL | Stocker les comptes, prospects, clients, devis, prestations, événements, tâches, notes et avis avec relations et contraintes |
| Base non relationnelle | MongoDB, extension PHP et bibliothèque officielle `mongodb/mongodb` | Conserver la collection de journaux d’actions avec détails variables, selon la recommandation du sujet |
| Application mobile | Interface Vue dédiée et Capacitor ; première cible Android | Réutiliser JavaScript/Vue et la même API PHP pour les usages mobiles de Chloé et José ; produire une application installable |
| Dépendances PHP | Composer et chargement automatique des classes | Déclarer les bibliothèques ciblées et rendre l’installation reproductible |
| Courriels | PHPMailer avec SMTP | Gérer les confirmations, notifications et envois de PDF avec pièces jointes |
| PDF | Dompdf avec un modèle HTML/CSS dédié | Générer les devis à partir des données validées côté serveur |
| Environnement | Docker et Docker Compose | Décrire les services nécessaires, isoler les bases et permettre une installation documentée |
| Tests serveur | PHPUnit | Tester les règles métier et l’accès aux données dans un environnement de test isolé |
| Tests interface | Vitest pour les composants et fonctions JavaScript | Vérifier les comportements des formulaires et composants concernés |
| Parcours navigateur | Playwright | Tester les parcours fonctionnels et de bout en bout sur l’application web |
| Suivi et automatisation | GitHub, GitHub Projects et GitHub Actions | Conserver l’historique, suivre les tâches, exécuter les contrôles avant intégration et automatiser le déploiement de production |

Les versions exactes des bibliothèques et images seront fixées et enregistrées à l’installation, avec les fichiers de verrouillage Composer/npm et une matrice de versions dans la documentation. PHP 8.4 est une branche encore supportée à la date de la décision. La version de Node devra satisfaire simultanément Vite, Vitest et Capacitor ; elle sera vérifiée avant de figer l’image de construction. Sources : [Vue](https://vuejs.org/guide/introduction), [démarrage Vue avec Vite](https://vuejs.org/guide/quick-start.html), [versions PHP](https://www.php.net/supported-versions.php), [MySQL 8.4](https://dev.mysql.com/doc/refman/8.4/en/), [bibliothèque PHP MongoDB](https://www.mongodb.com/docs/php-library/current/).

## Organisation du PHP natif

Le serveur sera une application organisée en couches, avec des responsabilités séparées :

1. Entrée HTTP et routage : identifier l’action demandée et produire une réponse JSON cohérente.
2. Contrôleurs : lire la requête, lancer les validations et appeler les services.
3. Services métier : appliquer les règles de qualification, devis, statuts, notes et droits.
4. Accès aux données : classes dédiées utilisant PDO pour MySQL et la bibliothèque MongoDB pour les journaux.
5. Services techniques : envoi des courriels, génération des PDF, authentification et journalisation.

Les requêtes SQL restent dans les classes d’accès aux données. Les scripts de création et de jeux d’essai seront écrits à la main, conformément à l’énoncé, puis versionnés. PDO fournit une interface d’accès aux bases ; les valeurs des requêtes seront passées par paramètres. Les éléments SQL non paramétrables, comme un nom de colonne de tri, seront limités à une liste autorisée. Sources : [PDO](https://www.php.net/manual/en/book.pdo.php), [requêtes préparées](https://www.php.net/manual/en/pdo.prepared-statements.php).

Le choix de PHP natif implique de concevoir explicitement le routage, la validation, les erreurs, l’authentification et les autorisations. Les tests devront couvrir ces éléments. Composer et des bibliothèques spécialisées restent compatibles avec ce choix : le projet ne nécessite pas de réécrire un moteur de PDF ou un client SMTP. Sources : [PHPMailer](https://github.com/PHPMailer/PHPMailer), [Dompdf](https://github.com/dompdf/dompdf).

## Application mobile et Docker

Le mobile aura ses propres écrans pour les événements à venir, les fiches événement/client, les actions de contact et les notes rapides. Il utilisera l’API commune et les droits décrits dans la matrice des utilisateurs. Le site web adapté à un petit écran ne sera pas, à lui seul, présenté comme l’application mobile livrée.

Capacitor permet d’intégrer des interfaces Vue dans une application Android. Android est la première cible retenue pour ce poste Windows ; le test pourra se faire sur appareil ou émulateur. Les écrans mobiles pourront aussi être publiés sous une adresse de démonstration pour faciliter la revue en ligne. Sources : [Vue et Capacitor](https://capacitorjs.com/solution/vue), [prérequis Capacitor](https://capacitorjs.com/docs/getting-started/environment-setup).

Pour traiter la demande de packaging Docker de la page 14, la conception prévoit une construction reproductible : image de construction Node pour les ressources Vue, puis image de construction Android avec SDK/JDK et Gradle pour produire l’APK. C’est une stratégie de projet à vérifier, déduite de la possibilité de construire Android en ligne de commande ; elle n’a pas encore été exécutée. L’application produite s’exécutera sur Android, tandis que Docker servira à sa construction et aux services serveur. Source : [construction Android en ligne de commande](https://developer.android.com/build/building-cmdline).

La tâche d’environnement devra vérifier tôt qu’un écran Vue minimal peut appeler l’API PHP, être construit avec Capacitor et être installé sur la cible Android. La recette mobile devra aussi vérifier réellement les actions téléphone, courriel et itinéraire ; les tests navigateur seuls ne suffiront pas pour ces interactions.

## Sécurité à concevoir et à vérifier

- Validation des données et autorisations côté PHP pour chaque action ; contrôle de l’appartenance des dossiers et du statut du compte.
- Requêtes préparées PDO, contrôle des filtres et tris, accès limité aux bases.
- Mots de passe hachés avec les fonctions PHP prévues à cet effet, sans stockage en clair. Source : [password_hash](https://www.php.net/manual/en/function.password-hash.php).
- Authentification web avec cookies de session protégés, renouvellement de session et protection contre les requêtes forgées. Le mécanisme mobile et le stockage des informations d’authentification devront être arrêtés lors de l’architecture, avec expiration et révocation, notamment après suspension. Source : [sécurité des sessions PHP](https://www.php.net/manual/en/features.session.security.management.php).
- Affichage des textes utilisateurs sans insertion HTML non contrôlée ; vérification des fichiers envoyés et des ressources utilisées pour produire les PDF.
- Secrets de bases, SMTP et déploiement fournis par configuration d’environnement ; fichiers contenant les secrets exclus de Git.

Ces mesures constituent des engagements de conception, pas une preuve de sécurité acquise. Les scénarios déjà préparés dans la matrice des droits et le parcours métier serviront à leur vérification.

## Tests et déploiement

PHPUnit couvrira les services PHP et les cas d’accès aux données ; Vitest couvrira les composants et fonctions de l’interface. Playwright exercera le parcours web de bout en bout. Les rapports de couverture et le tableau des tests seront conservés pour le dossier. Sources : [PHPUnit](https://phpunit.de/documentation.html), [Vitest](https://vitest.dev/guide/), [Playwright](https://playwright.dev/docs/intro).

Docker Compose organisera le service HTTP/PHP, MySQL et MongoDB, avec une configuration adaptée à la production. Les données persistantes, sauvegardes, secrets, contrôles de disponibilité et procédure de restauration seront détaillés dans les tâches correspondantes. Source : [Docker Compose en production](https://docs.docker.com/compose/how-tos/production/).

La cible d’hébergement est un environnement Linux compatible avec les conteneurs retenus. Le fournisseur, le dimensionnement, le domaine et le coût restent à choisir selon le budget avant le déploiement ; aucune offre n’a été souscrite. Les URL des applications web et de démonstration mobile ainsi que l’APK seront préparés pour la remise. L’API sera accessible en HTTPS et les bases ne seront pas exposées publiquement.

GitHub Actions exécutera les contrôles avant intégration et automatisera le déploiement à partir de `main`, comme demandé dans le sujet. La procédure précise et les preuves de fonctionnement seront produites lors de la mise en place du pipeline.

## Correspondance avec les exigences ECF

| Exigence | Réponse retenue |
|---|---|
| Interfaces web et mobile | Vue pour les deux interfaces, Capacitor pour l’application Android |
| Programmation orientée objet et architecture en couches | PHP natif structuré en contrôleurs, services et accès aux données |
| SQL, NoSQL et Docker obligatoires | MySQL avec PDO, MongoDB pour les journaux, Docker Compose et construction mobile conteneurisée à vérifier |
| Création SQL écrite à la main | Scripts SQL versionnés, sans génération automatique du schéma |
| PDF et courriels | Dompdf et PHPMailer, appelés depuis les services PHP |
| Tests et couverture | PHPUnit, Vitest et Playwright, avec rapports à produire |
| Déploiement en ligne et CI/CD | Hébergement de conteneurs, URL de démonstration, APK et GitHub Actions |

## Bilan et utilisation pour le rendu

La décision principale est arrêtée : Vue.js et PHP natif. Les technologies complémentaires sont choisies pour couvrir les exigences du sujet et rester cohérentes avec les acquis de Romain. La compatibilité d’ensemble sera démontrée par les premiers essais de l’environnement ; les paramètres d’hébergement et les détails d’authentification mobile restent à traiter dans leurs tâches respectives.

Ce fichier sera conservé dans `docs/conception/choix-technologiques.md` sur `dev`. Il alimentera la partie « Technologies utilisées » de la copie officielle, avec des explications actualisées selon ce qui aura réellement été installé et réalisé. Les sources techniques officielles ont été consultées les 17 et 18 octobre 2026 ; leurs liens sont placés près des choix concernés.

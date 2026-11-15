# Connexion web, sessions et premiers droits

> **Note :** ce document retrace l’étape où cette fonctionnalité a été développée. Les éléments qu’il signale comme « à développer » ont pour la plupart été réalisés depuis ; l’état final, avec les limites encore valables, est dans le [bilan des fonctionnalités](bilan-fonctionnalites.md).

## Ce qui fonctionne

La page `/connexion` accepte l’adresse électronique et le mot de passe. Une session authentifiée permet de rejoindre `/espace` ; la déconnexion retire l’accès. Le retour après connexion est limité aux chemins internes connus `/espace` et `/journaux`, sans redirection externe. Les rechargements conservent la session.

Trois lectures protégées matérialisent les premiers contrôles : les trois prochains événements du client connecté, les notes globales pour l’équipe interne, les vingt dernières traces pour l’administrateur. Le serveur vérifie le rôle et l’appartenance ; aucune valeur de rôle ou d’identifiant envoyée lors de la connexion n’accorde de droit. Chloé hérite de la lecture employé, pas des décisions client.

Références : [analyse de l’énoncé](conception/analyse-exigences.md), p. 5 (connexion et retour à l’action), p. 12–13 (journaux) ; [matrice des droits](conception/utilisateurs-et-droits.md), notamment D05 suspension et D08 rattachement des dossiers. Ces premiers écrans ne constituent pas l’ensemble de l’espace utilisateur/employé/administrateur demandé.

## Choix de sécurité de cette version

- Cookie `innovevents_session`, `HttpOnly`, `SameSite=Lax`, chemin `/api`, sans conservation durable par le navigateur. PHP utilise le mode strict et refuse les identifiants transmis par URL. Rotation de l’identifiant et du jeton CSRF lors de la connexion réussie et de la déconnexion.
- `Secure` activé par défaut hors `APP_ENV=local`. Le Compose local utilise HTTP sur 127.0.0.1 ; HTTPS et une configuration de production restent à mettre en place avant hébergement.
- Jeton CSRF de session requis pour connexion et déconnexion ; refus d’une origine différente et des requêtes déclarées cross-site. Seule l’origine locale configurée est admise lorsqu’un en-tête Origin est fourni.
- Vérification bcrypt via `password_verify`, requête préparée et erreur identique pour compte inconnu, mauvais mot de passe et compte suspendu. Un hash factice valide est vérifié pour un compte inconnu ; ce choix ne constitue pas une preuve d’égalité exacte des temps de réponse.
- Statut, rôle et empreinte du mot de passe relus en MySQL à chaque accès protégé. Une suspension, suppression ou modification d’empreinte retire l’accès à la session existante.
- Choix de durée : 30 minutes d’inactivité et 8 heures au maximum. Ce sont des décisions du projet, pas des valeurs imposées par le sujet.
- Limitation partagée, conservée sur volume local et protégée par verrou : 10 tentatives par adresse et 60 par pair réseau en 15 minutes, y compris en changeant de cookie. Le compteur inclut les réussites ; une limite atteinte retourne 429. Les entrées expirées sont purgées lors de la prochaine tentative.
- Taille JSON limitée à 8 Kio et mot de passe limité à 72 octets pour les empreintes bcrypt actuelles. Inscription et politique de nouveaux mots de passe restent distinctes.

La limitation locale n’est pas une protection distribuée prête pour plusieurs serveurs. Dans cette architecture, PHP voit l’IP du relais Vite ; les en-têtes X-Forwarded-For fournis par le client ne sont pas utilisés. La journalisation de la véritable IP publique exigera la configuration d’un proxy de confiance lors du déploiement. Les durées, limites et expiration sont documentées pour être adaptées et retestées.

## Journaux MongoDB

Un compte supplémentaire `ecf_auth_log` possède seulement l’action `insert` sur `innovevents_demo.logs`. Il ne peut ni lire ni modifier les traces ; le compte de lecture existant reste distinct. Secret local : `.secrets/mongo-auth.txt`, monté uniquement dans PHP. Aucun mot de passe ou jeton n’est enregistré dans les journaux.

Une tentative de connexion avec une entrée valide produit `CONNEXION_REUSSIE` ou `CONNEXION_ECHOUEE`, un horodatage BSON, l’acteur connu ou nul, l’IP du pair réseau, et le courriel utilisé en cas d’échec. Une tentative limitée produit aussi une trace d’échec. Les requêtes JSON invalides ou refusées avant authentification (CSRF, mauvais format) ne sont pas comptabilisées comme vérifications d’identifiants.

L’insertion est acquittée avant d’accorder la session. Si MongoDB est indisponible, réponse 503 et aucune nouvelle session authentifiée : le test de panne le confirme. Cette version ne dispose pas encore de la file durable de reprise proposée pour les opérations métier. Elle ne promet pas de conserver la trace d’une tentative lorsque MongoDB ne peut pas l’écrire. Un journal de réussite atteste la validation des identifiants ; un échec technique ultérieur de création de session reste possible. La livraison inter-bases fiable et la supervision restent à développer.

Les traces issues des tests sont conservées, y compris les identifiants de comptes fictifs ensuite supprimés. Leur absence en MySQL ne doit pas être présentée comme un compte encore actif. Aucun bouton de suppression de journal n’est proposé.

## Vérifications exécutées

[Preuve détaillée sans secret](preuves/authentification.json).

- 26 contrôles HTTP : anonymat, cookie, CSRF, origine, erreur générique, rôle imposé côté serveur, rotation, absence de hash, séparation des événements de deux clients, accès interdits, suspension, changement de mot de passe requis, déconnexion, héritage administrateur, journaux et limite entre sessions.
- 2 contrôles de panne MongoDB : connexion refusée et absence de session authentifiée ; MongoDB relancé dans un bloc de nettoyage garanti.
- 3 contrôles d’état de session côté PHP : inactivité expirée, durée absolue expirée et empreinte différente. L’état est simulé dans une session de test ; il ne s’agit pas d’une attente réelle de huit heures.
- 8 contrôles de permissions des bases, dont les refus de lecture et modification avec le compte d’insertion.
- Chromium à 1280 et 390 px : retour à l’espace demandé, message d’erreur accessible, mot de passe effacé après essai, connexion, rechargement, déconnexion et absence de débordement/erreur JavaScript. Capture mobile relue visuellement.
- Compilation Vue réussie. Aucun taux de couverture de test revendiqué.

Les tests HTTP créent deux comptes, clients et événements fictifs propres à l’essai, puis suppriment ces données dans `finally`. Ils ne suspendent pas les comptes de démonstration existants. Les tests répétés consomment le quota de tentatives : attendre son expiration en cas de 429, sans désactiver la protection pour obtenir un succès.

## Rejouer et utiliser

```powershell
./scripts/Start-App.ps1
python tests/app/authentication.py
python tests/app/auth-outage.py
docker compose -f compose.yaml -f compose.app.yaml exec -T api php tests/session-expiry.php
docker compose -f compose.yaml -f compose.app.yaml exec -T api php tests/connections.php
```

Le contrôle de panne arrête temporairement MongoDB : l’exécuter hors autres travaux. Le navigateur de test s’exécute avec `node tests/app/auth-browser.cjs`, Playwright disponible (chemins personnalisables comme dans le guide d’environnement). Rapports et captures locaux dans `tmp/`, exclus de Git ; synthèse sans secrets dans `docs/preuves/`.

Comptes locaux : `alice@example.invalid`, `jose@example.invalid`, `chloe@example.invalid`. Leur mot de passe de démonstration commun reste dans `.secrets/demo-password.txt`, à ouvrir localement sans le copier dans GitHub ni dans les rapports publics. Aucune valeur n’est affichée dans les résultats des outils.

## Restant à développer

L’inscription, la confirmation locale et la récupération/changement sont maintenant disponibles : voir [Parcours de compte](parcours-comptes.md). Restent les autorisations d’écriture et contrôles de propriété sur chaque future action, le mobile Android, HTTPS/proxy de confiance, la reprise durable des journaux et les tests de concurrence.

Sources techniques consultées : [sessions PHP](https://www.php.net/manual/en/session.security.ini.php), [authentification OWASP](https://cheatsheetseries.owasp.org/cheatsheets/Authentication_Cheat_Sheet.html), [protection CSRF OWASP](https://cheatsheetseries.owasp.org/cheatsheets/Cross-Site_Request_Forgery_Prevention_Cheat_Sheet.html).

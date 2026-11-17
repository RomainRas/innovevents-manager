# Déploiement en production

## Architecture en ligne

- **Adresse :** https://innovevents.rascleromain.fr (sous-domaine du domaine personnel, enregistrement DNS `A` vers le VPS).
- **Hébergement :** VPS Hostinger KVM 1 (1 vCPU, 4 Go de RAM, 50 Go NVMe), Ubuntu 24.04 LTS, centre de données en France.
- **Conteneurs Docker Compose** (`compose.yaml` + `compose.prod.yaml`) :

| Service | Rôle | Exposition |
| --- | --- | --- |
| `caddy` | Serveur web : HTTPS automatique Let's Encrypt, fichiers Vue compilés, en-têtes de sécurité, transmission de `/api` à PHP-FPM et de `/mobile/` au conteneur `mobile` | Ports 80 et 443, seuls ports publics |
| `mobile` | Application mobile (PWA) compilée, servie par un Caddy interne (`docker/mobile/Dockerfile`), ajoutée | Réseau interne (port 8080) |
| `api` | PHP 8.4-FPM, code et dépendances inclus dans l'image (`docker/php/Dockerfile.prod`) | Réseau interne |
| `mail_worker`, `journal_worker` | Envoi des courriels et livraison des journaux vers MongoDB | Réseau interne |
| `mysql`, `mongodb` | Bases MySQL 8.4 et MongoDB 8.0, volumes persistants | Réseau interne, aucun port publié |
| `mailpit` | Boîte de secours si le SMTP réel est désactivé (paramètres `SMTP_*` vides) : aucun courriel ne sort | Interface sur `127.0.0.1` du serveur uniquement (tunnel SSH) |

**Courriels réels :** `mail_worker` envoie par le serveur SMTP de Hostinger (`smtp.hostinger.com`, port 465, SSL implicite, authentification) depuis `contact@rascleromain.fr`, qui reçoit aussi les notifications de l'agence (`AGENCY_EMAIL`).

Le serveur intégré de PHP reste réservé au développement local ; la production utilise PHP-FPM avec OPcache, erreurs non affichées et variables d'environnement transmises (`clear_env = no`).

## Sécurité du serveur

Script `scripts/server/bootstrap.sh`, exécuté une fois en root :

- Docker Engine depuis le dépôt officiel, journaux limités en taille ;
- pare-feu UFW : seuls SSH, HTTP et HTTPS sont ouverts ;
- fail2ban contre les tentatives de connexion répétées et mises à jour de sécurité automatiques ;
- connexion SSH **par clé uniquement** (mots de passe désactivés) ;
- utilisateur `deploy` sans mot de passe, membre du groupe `docker`, propriétaire de `/opt/innovevents`.

Côté application : HTTPS forcé avec HSTS, cookie de session `Secure`/`HttpOnly`/`SameSite=Lax`, en-têtes CSP, `X-Frame-Options`, `nosniff` et `Referrer-Policy`, comptes SQL à droits limités par fonctionnalité (déjà testés), secrets uniquement sur le serveur.

## Secrets et configuration

- `.env` (non versionné, modèle `scripts/server/env.example`) : domaine, adresse de l'agence, expéditeur, paramètres SMTP.
- `.secrets/` (non versionné, dossier `700`) : tous les mots de passe des bases et la clé de chiffrement de la file de courriels sont **générés sur le serveur** par `deploy.sh` (`openssl rand -hex 32`). Aucun n'est transmis ni affiché.
- Le mot de passe de la boîte d'envoi est saisi directement sur le serveur, en saisie masquée par SSH, dans `.secrets/smtp-password.txt` ; il ne transite ni par Git ni par GitHub. La connexion SMTP est testée (authentification seule) avant d'activer l'envoi.
- Clé SSH de déploiement dédiée à GitHub Actions (ed25519, sans autre usage) : autorisée sur le compte `deploy` avec l'option `restrict` (ni terminal interactif, ni redirection de ports). Empreinte du serveur fixée dans `DEPLOY_KNOWN_HOSTS` pour éviter une interception.

## Déploiement

`scripts/server/deploy.sh`, idempotent, lancé dans `/opt/innovevents/app` :

1. génération des secrets manquants ;
2. construction des images `api` (PHP-FPM), `mobile` (compilation de la PWA) et `caddy` (compilation Vue) ;
3. démarrage et contrôle de santé de MySQL et MongoDB ;
4. création unique du jeu de démonstration (`prepare_demo_backup.py`, avec sauvegarde et restauration vérifiées) ;
5. migrations et comptes limités (`provision-*.py`, déjà utilisés en local), dont la conservation d’un an des journaux de connexion dans MongoDB (`provision-log-retention.py`) ;
6. démarrage de l'application, contrôle de santé et nettoyage des anciennes images.

**Déploiement automatique :** `.github/workflows/ci-deploy.yml` exécute les contrôles à chaque push sur `dev` et `main` : syntaxe PHP dans l'image de production, tests de calcul, **tests unitaires PHPUnit avec couverture**, **tests unitaires du front (Vitest)**, **audit de sécurité des dépendances** (Composer et npm, voir [veille](veille.md)), compilation Vue, compilation de l'application mobile, configuration Compose et analyse des scripts shell. Un seul contrôle en échec bloque le déploiement. Sur `main`, après succès, il envoie le code au serveur par SSH avec une clé dédiée et lance `deploy.sh`, puis vérifie `/api/health`. Secrets GitHub requis : `DEPLOY_SSH_KEY`, `DEPLOY_HOST`, `DEPLOY_KNOWN_HOSTS` ; variable `SITE_URL`.

## Reproduire le déploiement sur un nouveau serveur

Prérequis : un VPS Ubuntu 24.04 joignable en SSH par clé (root), un nom de domaine dont l'enregistrement `A` pointe vers le VPS, un dépôt GitHub contenant ce code.

1. **Préparer le serveur** (une fois, en root) : copier `scripts/server/bootstrap.sh` sur le VPS puis `sh bootstrap.sh`. Le script installe Docker, le pare-feu (SSH, HTTP, HTTPS), fail2ban et les mises à jour automatiques, crée l'utilisateur `deploy` avec les clés SSH autorisées de root et interdit la connexion par mot de passe.
2. **Créer la clé de déploiement** sur son poste : `ssh-keygen -t ed25519 -f deploy_key -C github-actions`. Ajouter la clé publique dans `/home/deploy/.ssh/authorized_keys`, précédée de `restrict `.
3. **Configurer l'application** sur le serveur, en tant que `deploy` : `mkdir -p /opt/innovevents/app`, y copier `scripts/server/env.example` en `.env` et renseigner `SITE_DOMAIN`, `AGENCY_EMAIL`, `MAIL_FROM` (et les `SMTP_*` pour des courriels réels, sinon Mailpit les retient). Mot de passe SMTP éventuel : `mkdir -p .secrets && chmod 700 .secrets && read -rs p && printf '%s' "$p" > .secrets/smtp-password.txt` (saisie masquée).
4. **Configurer GitHub** (Settings → Secrets and variables → Actions) : secrets `DEPLOY_SSH_KEY` (clé privée), `DEPLOY_HOST` (adresse du VPS), `DEPLOY_KNOWN_HOSTS` (sortie de `ssh-keyscan -t ed25519 <adresse>`), variable `SITE_URL` (`https://<domaine>`) ; environnement `production`.
5. **Déployer** : pousser sur `main` (ou fusionner `dev` dans `main`). GitHub Actions exécute les contrôles, envoie le code et lance `deploy.sh`, qui génère les secrets, construit les images, crée la démonstration la première fois, applique les migrations et démarre les services. Caddy obtient le certificat HTTPS au premier accès.
6. **Vérifier** : l'étape « Vérification du site » du pipeline doit afficher « Site en ligne » ; ouvrir `https://<domaine>`, `https://<domaine>/mobile/` et `https://<domaine>/api/health` (`{"status":"ready"}`). Le mot de passe des comptes de démonstration est dans `.secrets/demo-password.txt` sur le serveur.

Redéploiement manuel (sans GitHub) : depuis le dossier du dépôt, `git archive --format=tar HEAD | gzip | ssh deploy@<adresse> 'mkdir -p /opt/innovevents/app && tar -xzf - -C /opt/innovevents/app && cd /opt/innovevents/app && sh scripts/server/deploy.sh'`.

## Limites connues

- Les courriels partent d'une boîte personnelle (`contact@rascleromain.fr`) et non d'un domaine propre à l'agence ; adresses de démonstration en `example.invalid`, qui ne reçoivent rien.
- Un seul serveur, sans redondance ; la sauvegarde automatique des bases et des fichiers (PDF, images) vers un stockage externe reste à planifier.
- L'image PHP de base n'est pas épinglée par empreinte (`php:8.4-fpm-bookworm`) ; les mises à jour de sécurité de l'image sont prises à chaque construction (`--pull`).

## Vérifications

- Premier déploiement complet par `deploy.sh` : images construites sur le serveur, jeu de démonstration créé avec sauvegarde et restauration vérifiées, onze scripts de migration et de droits exécutés, tous les conteneurs déclarés sains.
- HTTP redirigé vers HTTPS (308) ; certificat Let's Encrypt valide, renouvelé automatiquement par Caddy.
- En-têtes présents : HSTS, CSP, `X-Frame-Options: DENY`, `nosniff`, `Referrer-Policy`, `Permissions-Policy`.
- API en ligne : `/api/health` prêt (MySQL et MongoDB joignables), jeton CSRF, cookie de session `Secure; HttpOnly; SameSite=Lax`.
- Essais en ligne : connexions de Chloé, José et Alice ; suivi des devis, prospects et journaux MongoDB pour l'administratrice ; espace client ; refus d'accès administrateur pour la cliente ; notes pour l'employé ; mauvais mot de passe refusé.
- Correction faite pendant la mise en ligne : la directive `php_fastcgi` de Caddy cherchait `index.php` dans son propre conteneur ; remplacée par une réécriture explicite vers PHP-FPM.
- Courriels réels : authentification SMTP Hostinger réussie, courriel de test accepté par le serveur SMTP pour `contact@rascleromain.fr`. Un premier mot de passe erroné a été détecté par le test d'authentification : retour immédiat sur Mailpit, aucun courriel de la file perdu.
- Intégration continue : contrôles réussis sur `dev` ; `dev` fusionnée dans `main` (avance rapide), ce qui a déclenché le premier déploiement automatique par GitHub Actions.

Comptes de démonstration en ligne : `chloe@example.invalid` (administratrice), `jose@example.invalid` (employé), `alice@example.invalid` (cliente). Le mot de passe commun est généré sur le serveur (`.secrets/demo-password.txt`) et n'est pas versionné ; il sera communiqué au correcteur dans la copie à rendre.

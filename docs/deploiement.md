# Déploiement en production — 14 novembre 2026

## Architecture en ligne

- **Adresse :** https://innovevents.rascleromain.fr (sous-domaine du domaine personnel, enregistrement DNS `A` vers le VPS).
- **Hébergement :** VPS Hostinger KVM 1 (1 vCPU, 4 Go de RAM, 50 Go NVMe), Ubuntu 24.04 LTS, centre de données en France.
- **Conteneurs Docker Compose** (`compose.yaml` + `compose.prod.yaml`) :

| Service | Rôle | Exposition |
| --- | --- | --- |
| `caddy` | Serveur web : HTTPS automatique Let's Encrypt, fichiers Vue compilés, en-têtes de sécurité, transmission de `/api` à PHP-FPM | Ports 80 et 443, seuls ports publics |
| `api` | PHP 8.4-FPM, code et dépendances inclus dans l'image (`docker/php/Dockerfile.prod`) | Réseau interne |
| `mail_worker`, `journal_worker` | Envoi des courriels et livraison des journaux vers MongoDB | Réseau interne |
| `mysql`, `mongodb` | Bases MySQL 8.4 et MongoDB 8.0, volumes persistants | Réseau interne, aucun port publié |
| `mailpit` | Boîte de secours tant que le SMTP réel n'est pas configuré : aucun courriel ne sort | Interface sur `127.0.0.1` du serveur uniquement (tunnel SSH) |

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
- Le mot de passe SMTP éventuel se place uniquement dans `.secrets/smtp-password.txt` sur le serveur.

## Déploiement

`scripts/server/deploy.sh`, idempotent, lancé dans `/opt/innovevents/app` :

1. génération des secrets manquants ;
2. construction des images `api` (PHP-FPM) et `caddy` (compilation Vue) ;
3. démarrage et contrôle de santé de MySQL et MongoDB ;
4. création unique du jeu de démonstration (`prepare_demo_backup.py`, avec sauvegarde et restauration vérifiées) ;
5. migrations et comptes limités (`provision-*.py`, déjà utilisés en local) ;
6. démarrage de l'application, contrôle de santé et nettoyage des anciennes images.

**Déploiement automatique :** `.github/workflows/ci-deploy.yml` exécute les contrôles à chaque push sur `dev` et `main` (syntaxe PHP dans l'image de production, tests de calcul, compilation Vue, configuration Compose, analyse des scripts shell). Sur `main`, après succès, il envoie le code au serveur par SSH avec une clé dédiée et lance `deploy.sh`, puis vérifie `/api/health`. Secrets GitHub requis : `DEPLOY_SSH_KEY`, `DEPLOY_HOST`, `DEPLOY_KNOWN_HOSTS` ; variable `SITE_URL`.

## Limites connues

- Les courriels restent dans la boîte de secours Mailpit tant que le SMTP réel n'est pas configuré.
- Un seul serveur, sans redondance ; la sauvegarde automatique des bases et des fichiers (PDF, images) vers un stockage externe reste à planifier.
- L'image PHP de base n'est pas épinglée par empreinte (`php:8.4-fpm-bookworm`) ; les mises à jour de sécurité de l'image sont prises à chaque construction (`--pull`).

## Vérifications du 14 novembre 2026

- Premier déploiement complet par `deploy.sh` : images construites sur le serveur, jeu de démonstration créé avec sauvegarde et restauration vérifiées, onze scripts de migration et de droits exécutés, tous les conteneurs déclarés sains.
- HTTP redirigé vers HTTPS (308) ; certificat Let's Encrypt valide jusqu'au 12 février 2027, renouvelé automatiquement par Caddy.
- En-têtes présents : HSTS, CSP, `X-Frame-Options: DENY`, `nosniff`, `Referrer-Policy`, `Permissions-Policy`.
- API en ligne : `/api/health` prêt (MySQL et MongoDB joignables), jeton CSRF, cookie de session `Secure; HttpOnly; SameSite=Lax`.
- Essais en ligne : connexions de Chloé, José et Alice ; suivi des devis, prospects et journaux MongoDB pour l'administratrice ; espace client ; refus d'accès administrateur pour la cliente ; notes pour l'employé ; mauvais mot de passe refusé.
- Correction faite pendant la mise en ligne : la directive `php_fastcgi` de Caddy cherchait `index.php` dans son propre conteneur ; remplacée par une réécriture explicite vers PHP-FPM.

Comptes de démonstration en ligne : `chloe@example.invalid` (administratrice), `jose@example.invalid` (employé), `alice@example.invalid` (cliente). Le mot de passe commun est généré sur le serveur (`.secrets/demo-password.txt`) et n'est pas versionné ; il sera communiqué au correcteur dans la copie à rendre.

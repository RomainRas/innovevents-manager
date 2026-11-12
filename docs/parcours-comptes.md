# Inscription, récupération et changement de mot de passe

État au 12 novembre 2026 : parcours web locaux fonctionnels. Branche `dev`, sans fusion sur `main`.

## Consignes et choix

L’énoncé p. 5 demande une inscription avec adresse électronique, prénom, nom, nom d’utilisateur, mot de passe et courriel de confirmation. Le minimum est huit caractères avec majuscule, minuscule, chiffre et caractère spécial. La p. 6 demande de générer et envoyer un mot de passe lors de la récupération, puis d’imposer son changement à la connexion suivante. Aucun compte administrateur ne peut être créé par ce formulaire.

Choix de conception : mot de passe temporaire aléatoire valable 30 minutes, stocké sous forme de hash bcrypt, distinct du mot de passe habituel. La demande seule ne révoque pas le mot de passe habituel : cela évite qu’un tiers puisse bloquer un compte en demandant une récupération. Toute session ouverte avec le mot de passe temporaire est limitée au changement du mot de passe. Une nouvelle demande pendant sa validité ne produit pas un nouveau courriel. Ces modalités et la limite bcrypt de 72 octets sont des décisions techniques, pas des consignes textuelles de l’énoncé.

Le changement vérifie le mot de passe actuel, la politique et la confirmation, efface le secret temporaire et invalide les sessions précédentes. Une nouvelle connexion est nécessaire. L’inscription n’exige pas de lien d’activation : la confirmation demandée est informative. Les réponses publiques d’inscription en doublon et de récupération restent génériques.

## Utilisation locale

- Application : http://127.0.0.1:5173/connexion ; liens vers inscription et récupération.
- Changement volontaire depuis « Mon espace » ; changement imposé après connexion temporaire.
- Boîte de courriels de démonstration : http://127.0.0.1:8025.
- Démarrage : `scripts/Start-App.ps1`, qui applique la migration et prépare les comptes techniques.

Mailpit reçoit uniquement les courriels locaux. Aucun courriel n’est envoyé à une adresse réelle sur Internet. Sa boîte contient les messages de démonstration, y compris les mots de passe temporaires : ne pas publier de capture contenant ces valeurs.

## Stockage et permissions

`database/mysql/003_account_mail.sql` ajoute deux colonnes temporaires à `utilisateurs` et une table technique `courriels_a_envoyer`. La base de démonstration comporte désormais 17 tables ; la base de vérification initiale conserve ses 16 tables. Le script de provisionnement vérifie les colonnes avant application ; une migration partielle est signalée sans suppression automatique.

L’inscription et la mise en file du courriel sont une transaction. Le contenu en attente est chiffré avec Sodium secretbox, clé distincte dans `.secrets/mail-key.txt`. Le traitement SMTP efface le contenu chiffré après envoi, expiration ou échec définitif. Cinq tentatives au maximum, espacées d’une minute ; expiration à 30 minutes pour une récupération et 24 heures pour une confirmation. Les métadonnées de traitement restent conservées pour ce prototype ; leur purge reste à définir.

`ecf_accounts` peut lire les utilisateurs et écrire seulement les colonnes nécessaires ; aucun droit d’écriture sur rôle/statut, aucun droit de suppression. Le processus d’envoi utilise un compte séparé qui ne peut lire et modifier que la file. Les secrets ne sont ni versionnés ni inclus dans les preuves. Conserver la clé séparément et de façon protégée si une sauvegarde contenant des courriels en attente doit être restaurée.

Un verrou de ligne évite de prendre simultanément le même message. SMTP n’est pas transactionnel avec MySQL : un arrêt après l’envoi et avant la validation SQL peut entraîner un doublon. Aucune garantie d’envoi « exactement une fois » n’est annoncée.

## Vérifications et limites

- `tests/app/accounts.py` : 26 contrôles HTTP et données, dont politique, rôle client imposé, confirmation, doublon, récupération générique, expiration, accès temporaire limité, invalidation des sessions et effacement des secrets. Comptes fictifs supprimés après essai ; traces de connexion conservées.
- `backend/tests/account-permissions.php` : quatre refus vérifiés (écriture du rôle, insertion du rôle, suppression, lecture de la file par le compte applicatif).
- `tests/app/accounts-browser.cjs` : formulaires et navigation à 1280/390 px, réponse générique de récupération, absence de débordement et d’erreur JavaScript.
- Compilation Vue réussie. Synthèse dans `docs/preuves/comptes-2026-11-12.json`.

La panne SMTP a désormais été injectée avec succès : voir le bilan ci-dessous. La concurrence de plusieurs processus reste à vérifier. Restent le fournisseur de courriels de production, HTTPS, les tests mobiles et la journalisation durable. Ces écrans fonctionnels ne constituent pas une validation visuelle des maquettes Figma ni une clôture de l’ensemble de l’authentification.

## Panne et reprise vérifiées — 12 novembre 2026

`tests/app/mail-recovery.py` arrête Mailpit avec des messages fictifs propres à l’essai, puis remet les services en marche. Sept contrôles réussis : mise en file pendant la panne, absence de contenu en clair dans la file, conservation après échec, reprise automatique après le délai réel d’une minute, réception d’un seul message dans cet essai, expiration sans envoi et effacement après le cinquième échec. Pour ce dernier cas, le compteur initial est placé à quatre : cinq échecs espacés d’une minute n’ont pas été attendus.

Le test refuse de commencer si la file contient des envois en attente. Les lignes créées sont supprimées par identifiants précis ; les autres données sont conservées. Le courriel fictif reçu reste dans Mailpit. Le nettoyage et le redémarrage sont prévus dans `finally`. L’arrêt brutal du processus de test ou de Docker lui-même n’est pas couvert par cette garantie.

Le service de mise en file retourne maintenant l’identifiant du message, permettant de suivre exactement celui-ci dans les tests et les futurs parcours. Les appels d’inscription et de récupération restent compatibles. Preuve : [résultats de reprise](preuves/reprise-courriels-2026-11-12.json). La réception unique observée ne prouve pas l’absence de doublons dans tous les scénarios de panne SMTP/MySQL.

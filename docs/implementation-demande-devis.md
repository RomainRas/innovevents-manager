# Développement de la demande de devis — bilan du 12 novembre 2026

État : formulaire, enregistrement, notification locale, reprise sans doublon et remerciement configurable développés et testés. Le plan initial ci-dessous est conservé pour expliquer l’ordre suivi ; le bilan final précise les contrôles effectivement exécutés.

## Sources vérifiées

La page 8 du [sujet](enonce/enonce-ecf-cda.pdf) a été relue directement. Elle impose entreprise, nom, prénom, adresse électronique, téléphone, lieu, type d’événement, date souhaitée, nombre de participants estimé et besoin. Tous sont obligatoires. La demande doit être enregistrée dans `prospects`, avec le statut initial « à contacter », un courriel à l’agence et un remerciement modifiable. L’adresse donnée dans le sujet est un exemple : ne pas en faire une destination réelle par défaut.

Les [règles du formulaire](conception/etats-demande-devis.md), le [wireframe](conception/wireframe-web-01-demande-devis.md), la maquette WEB01 et le schéma SQL constituent les supports existants. Le type d’événement reste libre ; les catégories citées par le sujet sont des exemples. Le formulaire public ne doit pas imposer de compte.

## Ordre d’implémentation

1. Ajouter un accès SQL dédié à la demande : insertion des dix colonnes seulement, lecture du remerciement et insertion du courriel. Ne pas réutiliser un compte administrateur ni autoriser la modification du statut depuis le formulaire public.
2. Préparer une migration écrite à la main pour la protection contre les doublons et le type de courriel `demande_devis`. L’extension doit conserver les données existantes. Les types actuellement autorisés dans la file sont uniquement `confirmation` et `recuperation`.
3. Valider côté PHP les dix champs, les longueurs prévues par le schéma, une adresse électronique valide, une vraie date et un entier strictement positif pour les participants. Documenter toute borne supplémentaire comme choix de conception. Ne pas inventer une obligation de date future absente de la page 8.
4. Enregistrer la demande et sa notification dans une transaction MySQL. Le statut initial vient du serveur. Si la file ne peut pas être écrite, annuler l’ensemble ; si seul SMTP est indisponible après validation, conserver la demande et reprendre l’envoi.
5. Réutiliser un identifiant aléatoire de soumission lors d’une nouvelle tentative après perte de réponse. Associer cet identifiant au contenu normalisé : même identifiant et même contenu donnent le résultat précédent ; contenu différent produit un conflit. Ne pas exposer de données personnelles avec un identifiant séquentiel public.
6. Construire la page Vue avec les dix libellés, deux groupes de champs, erreurs liées aux champs, résumé et focus sur la première erreur, état d’attente et confirmation. Conserver la saisie lors d’un incident. Une réponse perdue ne doit pas être présentée comme une absence certaine d’enregistrement.
7. Lire le remerciement depuis les paramètres et l’afficher comme du texte. Ajouter une modification réservée à l’administratrice, avec protection CSRF et vérification des droits ; afficher une confirmation après sauvegarde.
8. Tester en local la chaîne complète, puis consigner les preuves, les limites et les fichiers dans la tâche GitHub et le reporting.

## Critères de validation

- Visiteur et utilisateur connecté peuvent transmettre une demande complète.
- Chaque champ absent ou invalide bloque l’écriture et signale la bonne erreur ; les valeurs saisies restent visibles.
- Le navigateur ne peut imposer ni statut, ni identifiant client, ni rôle.
- Une soumission produit un prospect « à contacter » et une notification à l’agence de test.
- Une panne SMTP ne perd pas la demande ; la notification est reprise après redémarrage.
- Deux requêtes avec le même identifiant ne créent pas deux prospects, y compris en concurrence ; changement de contenu avec la même clé refusé.
- Une réponse réseau perdue permet une nouvelle tentative sans doublon.
- Remerciement modifiable par l’administratrice uniquement ; texte contenant du HTML affiché sans exécution.
- Navigation clavier, focus d’erreur, affichage à 390 et 1280 px, absence de données personnelles dans les erreurs techniques.

## Limites de cette étape

La qualification, la conversion en client, la création et les calculs du devis, son PDF et sa transmission constituent les étapes suivantes. Le formulaire de demande ne calcule aucun montant : il n’a donc besoin ni d’un taux de TVA inventé ni d’une règle d’arrondi à ce stade. La messagerie restera locale tant que le déploiement et sa destination réelle ne sont pas configurés.

## Utiliser la version développée

- Formulaire public : http://127.0.0.1:5173/demande-devis ; accessible aussi depuis le menu.
- Remerciement : se connecter comme administratrice, puis « Modifier le remerciement des demandes » dans l’espace personnel. La route serveur contrôle le rôle, même en accès direct.
- Courriels de démonstration : http://127.0.0.1:8025, destinataire `agence@example.invalid`. La variable serveur `AGENCY_EMAIL` peut configurer la destination ; le transport reste Mailpit local.
- Démarrage avec `scripts/Start-App.ps1`. La migration `004_quote_requests.sql` et `scripts/provision-quotes.py` ajoutent le suivi des soumissions, le type de notification et les comptes techniques. Les données existantes ne sont pas remplacées. La base de démonstration passe à 18 tables ; la base initiale de vérification reste à 16.

## Choix mis en œuvre

La clé aléatoire de 256 bits est générée dans le navigateur et réutilisée après un incident. Une contrainte unique garantit une seule soumission enregistrée pour cette clé. Le serveur conserve l’empreinte du contenu normalisé et le remerciement original ; la même clé avec un contenu différent est refusée. Les insertions concurrentes en doublon sont annulées avant lecture du résultat existant. Aucune donnée personnelle ni identifiant séquentiel de prospect n’est retourné publiquement.

La clé reste dans la mémoire du formulaire : un rechargement complet ou une nouvelle page génère une nouvelle clé. La reprise après fermeture n’est pas promise. Les clés sont conservées en base, sans purge automatique à ce stade. Définir la rétention avant production, en tenant compte de la clé étrangère vers le prospect.

Après un résultat réseau incertain, les champs restent visibles mais sont bloqués pour garder exactement la soumission d’origine. Le bouton permet de vérifier et réessayer. Les erreurs de validation permettent la correction, conservent les valeurs et déplacent le focus vers le premier champ. Le texte de confirmation est interpolé comme texte, jamais comme HTML.

Bornes de conception : longueurs du schéma, besoin limité à 5 000 caractères, remerciement à 2 000 caractères ; participants entre 1 et 4 294 967 295, date civile valide à partir de l’année 1000 sans obligation de date future. Téléphone limité à 32 caractères, chiffres et séparateurs usuels, au moins six chiffres. Ces limites ne sont pas des prescriptions du sujet.

Protection : CSRF et origine, JSON limité à 32 Kio pour la soumission, requêtes préparées, compte `ecf_quotes` sans lecture ni modification des prospects et sans choix du statut ; compte `ecf_settings` séparé pour les paramètres. La route publique n’exige pas de session authentifiée. Dix nouvelles demandes par pair réseau en quinze minutes ; le quota réseau général de 60 est partagé avec l’authentification. En local le pair est Vite : IP réelle et quotas de production restent à configurer.

## Vérifications exécutées

- 21 contrôles HTTP et données dans `tests/app/quote-requests.py` : dix champs requis, formats, longueur, anonymat, statut imposé, reprise, conflit de contenu, demandes parallèles, utilisateur connecté, droits de configuration, nouveau remerciement et réception par l’agence locale.
- Six refus SQL dans `backend/tests/quote-permissions.php` : choix du statut, modification, lecture et suppression des prospects, modification des paramètres et lecture des utilisateurs depuis le compte public.
- Huit contrôles dans `tests/app/quote-resilience.py` : deux processus PHP indépendants, un seul prospect/une seule clé, retrait temporaire du droit d’écriture de la file provoquant l’annulation du prospect et de la clé, puis notification conservée et envoyée après redémarrage de Mailpit. Droits et services rétablis dans le nettoyage.
- Chromium à 1280 et 390 pixels : dix erreurs, focus sur la première erreur, Tab vers le champ suivant, saisie conservée, réponse réellement perdue après l’écriture puis reprise réussie, confirmation affichée comme texte, focus de succès, absence de débordement et d’erreur JavaScript. L’essai HTML injecte une réponse de test dans le navigateur ; ce n’est pas un audit XSS exhaustif.
- Compilation Vue réussie. Capture mobile relue. Pas de conformité globale d’accessibilité ni de test de charge revendiqué.

Les prospects et clés fictifs sont nettoyés précisément ; le remerciement original est rétabli. Les courriels de test et métadonnées de file restent dans l’environnement local. Rapports et captures bruts dans `tmp/`, preuve synthétique dans [demandes-devis-2026-11-12.json](preuves/demandes-devis-2026-11-12.json). Les tests de panne modifient temporairement le service de courriels et un droit SQL : les lancer hors autres opérations locales. La garantie de nettoyage ne couvre pas un arrêt brutal du processus ou de Docker.

Le service PHP local traite les requêtes HTTP séquentiellement ; la concurrence des transactions a donc également été vérifiée par deux processus PHP séparés. Le test ne garantit pas tous les entrelacements possibles. La notification conserve les limites SMTP déjà documentées : doublon encore possible après envoi réussi puis échec de validation SQL.

## Conservation pour le rendu

Conserver ce bilan, la migration manuelle, les fichiers de code et les preuves sur `dev`. Ils alimentent le dossier technique et les explications de tests. La page fonctionnelle réutilise la palette du projet ; elle ne vaut pas validation des maquettes Figma de la tâche #5. Aucun hébergement public, dépôt d’évaluation ou fusion sur `main` n’est réalisé par cette étape.

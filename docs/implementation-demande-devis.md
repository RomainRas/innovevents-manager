# Développement de la demande de devis — préparation du 12 novembre 2026

État : consignes relues et plan d’implémentation préparé. Le formulaire métier n’est pas encore développé. Ce document organise la prochaine étape ; il ne constitue pas une preuve de fonctionnement.

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

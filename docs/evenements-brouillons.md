# Brouillon d’événement associé au client

> **Note :** ce document retrace l’étape où cette fonctionnalité a été développée. Les éléments qu’il signale comme « à développer » ont pour la plupart été réalisés depuis ; l’état final, avec les limites encore valables, est dans le [bilan des fonctionnalités](bilan-fonctionnalites.md).

Complément ultérieur : [l’ajout et le remplacement d’image privée](images-evenements.md) sont désormais disponibles. Le bilan ci-dessous conserve le périmètre de la livraison initiale ; consentement, publication, statuts et devis restent à développer.

## Source et périmètre

L’énoncé p. 9 prévoit d’associer un événement au client après conversion du prospect et d’en modifier les informations. Les pages 12–13 prévoient une trace de création d’événement. Cette étape livre la création et la modification du brouillon : nom, lieu, type, participants et dates/heures. Elle ne termine pas toute la gestion des événements.

L’image, le consentement à publication, la visibilité publique, les changements de statut, les prestations et le devis restent à développer. Le statut initial est imposé à brouillon et les indicateurs de publication restent à zéro. Aucune publication publique ne découle de cet écran.

## Parcours

1. Ouvrir une demande convertie depuis l’espace administratrice, puis sa fiche client.
2. Dans les demandes associées, choisir « Préparer ou consulter l’événement ».
3. Le type, le lieu et le nombre de participants sont repris de la demande. Le type sert de proposition de nom, modifiable.
4. La date souhaitée est rappelée ; les horaires ne sont pas inventés. Seul le nom est obligatoire à ce stade de brouillon, par décision de conception.
5. Créer le brouillon, puis compléter et enregistrer ses informations. Un événement déjà créé est retrouvé depuis la même demande.

Les dates sont saisies en heure de Paris et stockées en UTC. Une fin renseignée avec un début doit être postérieure. Les heures inexistantes ou ambiguës lors d’un changement d’heure sont refusées avec une explication : le choix entre les deux occurrences d’une heure ambiguë n’est pas proposé. Ces règles sont des choix techniques, pas des citations de l’énoncé.

## Protections

Les quatre routes de lecture/écriture vérifient le rôle administrateur ; les écritures vérifient également la session et CSRF. Le client associé vient du prospect converti, jamais d’un identifiant imposé par le formulaire.

La création prend un verrou nommé MySQL propre au prospect, puis un verrou de lecture sur celui-ci. Cela permet de protéger l’association sans accorder de droit de modification des prospects au compte événement. Le verrou nommé est libéré en sortie ; la contrainte unique sur le prospect protège également le lien. La création de l’événement et l’intention de journal `CREATION_EVENEMENT` sont enregistrées dans la même transaction MySQL. Le traitement existant livre cette trace vers MongoDB.

Une empreinte du contenu initial permet de retrouver le même événement après perte de réponse ; un contenu différent est refusé. Une révision protège les modifications contre l’écrasement d’une fiche changée depuis son ouverture. Une répétition identique de la dernière modification est reconnue. Le formulaire garde les saisies et bloque leur modification après une réponse incertaine pour permettre une reprise identique.

Le compte `ecf_events` ne peut pas changer le statut, publier, renseigner le consentement, réaffecter le client, supprimer un événement ou modifier une trace de journal. La modification des informations du brouillon ne génère pas un type de journal inventé en dehors du contrat actuel.

## Installation

`Start-App.ps1` appelle `provision-events.py`. La migration additive `007_event_drafts.sql` ajoute l’empreinte de création et la révision ; aucune nouvelle table. La démonstration garde 18 tables. Secret local dans `.secrets/mysql-events.txt`, ignoré par Git, monté uniquement dans l’API. Une restauration complète après les migrations récentes reste à rejouer.

## Vérifications exécutées

- 27 contrôles serveur/données : droits, CSRF, préremplissage, champs, dates invalides et changements d’heure, enregistrement UTC été/hiver, brouillon incomplet privé, association au bon client, reprises, unicité, conflit de révision, refus hors brouillon et prospect non converti, trace MongoDB unique, annulation de la création si l’écriture du journal échoue.
- Sept refus de permissions SQL vérifiés avec le compte limité.
- Chromium à 1280 et 390 px : retour après connexion, préremplissage, erreur et focus, perte de réponse après création puis reprise, modification des horaires, persistance après rechargement, absence de débordement et d’erreur JavaScript. Capture mobile relue.
- Compilation Vue réussie. Le droit temporairement retiré pour simuler la panne a été rétabli ; les fiches fictives ont été nettoyées. Leurs traces restent conservées comme historique de test.

Tests : `tests/app/events.py`, `events-browser.cjs` et `backend/tests/events-permissions.php`. Ils doivent être exécutés sur la démonstration locale hors autre travail concurrent : ils retirent brièvement un droit SQL. `finally` restaure les droits et nettoie les fixtures, sauf arrêt brutal du processus. Résultats et captures locaux dans `tmp/`, synthèse versionnée dans [la preuve](preuves/evenements-brouillons.json).

Le conflit a été simulé par une révision périmée ; aucune épreuve de charge ou création simultanée entre plusieurs processus n’est revendiquée. Aucun audit complet d’accessibilité, déploiement public ou validation Figma supplémentaire n’est revendiqué.

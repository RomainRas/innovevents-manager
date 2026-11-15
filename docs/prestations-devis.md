# Prestations et calculs du devis — 14 novembre 2026

> **Note du 15 novembre 2026 :** ce document décrit l’état du projet à sa date de rédaction. Les éléments qu’il signale comme « à développer » ont pour la plupart été réalisés depuis ; l’état final, avec les limites encore valables, est dans le [bilan des fonctionnalités](bilan-fonctionnalites.md).

Depuis la fiche événement, « Préparer les prestations et le devis » ouvre le formulaire administrateur : ajout/suppression de lignes (libellé et montant HT), taux de TVA, calcul et enregistrement. Les totaux deviennent visibles après validation serveur ; une modification de la saisie masque les anciens totaux jusqu’au prochain enregistrement.

L’énoncé p. 9 exige les prestations HT et les calculs automatiques TVA/TTC. Il ne fournit ni taux, ni devise, ni arrondi. Les choix déjà consignés dans `conception/mld-mysql.md` sont appliqués : EUR, taux explicite par version sans valeur par défaut, TVA calculée sur la somme HT, arrondie au centime demi au supérieur, puis TTC = HT + TVA. Les calculs utilisent des centimes entiers et des centièmes de pourcentage, sans calcul flottant. Virgule ou point admis en saisie ; valeurs normalisées à deux décimales en base.

Limites techniques de cette version : 1 à 100 lignes, 255 caractères par libellé, HT positif ou nul jusqu’à 9 999 999,99 € par ligne, taux de 0 à 100 avec deux décimales maximum. Elles permettent un calcul exact en entiers 64 bits ; elles ne sont pas présentées comme des consignes d’examen.

## Cohérence et droits

Routes réservées à l’administrateur, session et CSRF sur l’écriture. Les totaux fournis éventuellement par le navigateur sont ignorés et recalculés. Le devis doit être un brouillon non figé, lié à un événement brouillon. La référence initiale est `IE-<identifiant événement>` (choix interne, pas une numérotation de facture).

La sauvegarde utilise les tables existantes `devis`, `versions_devis` et `prestations_devis`, sans migration. Un verrou par événement sérialise les écritures de ce service ; un verrou de lecture protège l’état de l’événement. Le remplacement des lignes, les totaux et la révision sont atomiques. Une requête répétée après réponse perdue retrouve la même révision si son contenu normalisé est identique ; une modification périmée différente reçoit un conflit. Les futurs traitements PDF/transmission doivent se coordonner sur le même verrou de devis avant de figer ou modifier sa version.

Les documents déjà disponibles de la version sont marqués obsolètes à la modification du contenu. Cette anticipation est codée ; la génération PDF et son invalidation de bout en bout restent à tester avec le prochain bloc. Aucun devis figé ou transmis n’est modifié par ce formulaire.

Le compte SQL dédié lit les données nécessaires, crée le dossier/version, remplace les prestations et met à jour les colonnes de calcul. Il ne peut pas changer les statuts commerciaux, figer les coordonnées, modifier un client ou lire les utilisateurs. Les droits restent un complément aux règles applicatives : ce compte peut supprimer des lignes de prestations, le contrôle de la version modifiable reste donc indispensable dans le service.

## Résultats exécutés

- 16 contrôles unitaires du calcul : demi-centime, juste en dessous, taux zéro et cent pour cent, virgule, maximum, arrondi sur total plutôt que sur chaque ligne, formats invalides et décimales excessives.
- 21 contrôles HTTP/données : droits, CSRF, champs, totaux serveur, unicité, reprise création/modification, conflit, remplacement, refus d’une version figée et événement annulé. Retrait réel du droit d’insertion pour vérifier que l’échec après suppression des lignes annule toute la transaction et préserve les anciennes données.
- Six refus SQL vérifiés.
- Chromium 1280/390 px : retour après connexion, erreurs et focus, deux lignes, reprise après perte de confirmation simulée côté navigateur après écriture réelle, suppression d’une ligne, disparition des totaux périmés, persistance et absence de débordement/erreur JavaScript. Capture mobile relue.
- Compilation Vue réussie ; droit SQL rétabli et fiches fictives nettoyées.

Tests dans `backend/tests/quote-amounts.php`, `quote-drafts-permissions.php`, `tests/app/quote-drafts.py` et `quote-drafts-browser.cjs`. L’essai HTTP retire brièvement un droit SQL : exécution locale hors autre travail concurrent. Le nettoyage/restauration est dans `finally`, sauf arrêt brutal. [Preuve](preuves/prestations-devis-2026-11-14.json).

La génération PDF, son journal MongoDB, le téléchargement, l’envoi, le compte client, les décisions et les nouvelles versions après demande de modification restent à réaliser. Pas de couverture de code, de test de charge ou de déploiement revendiqué pour cette étape.

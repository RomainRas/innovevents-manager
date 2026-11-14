# Réponse du client au devis et contre-proposition — 14 novembre 2026

## Périmètre et utilisation

L’énoncé, page 10, prévoit trois réponses du client : **acceptation** (statut « accepté » et courriel à Innov’Events), **demande de modification** (motif exigé, statut « modification », contre-proposition par Chloé) et **refus** (statut « refusé »). Cette livraison les réalise, ainsi que la contre-proposition, en suivant les [états WEB03](conception/etats-decisions-devis.md) et le [parcours commercial](conception/parcours-prospect-devis-evenement.md).

**Client (« Mon espace », section « Vos devis »).** Sur la version courante en étude, trois boutons : « Accepter le devis… », « Demander une modification… », « Refuser le devis… ». Chaque réponse passe par une confirmation qui rappelle la référence, la version, l’événement et le total TTC. Le motif de modification est obligatoire (2 000 caractères maximum, espaces seuls refusés). Annuler ne vaut ni acceptation ni refus et rend le focus au bouton d’origine. Après réponse, les boutons sont remplacés par le statut, la date et le motif éventuel.

**Chloé (page du devis).** La section « Transmission au client » affiche les réponses du client par version, avec le motif. Sur une demande de modification, « Préparer une contre-proposition (version n+1) » crée une nouvelle version brouillon qui reprend les prestations et le taux de la version transmise. Chloé la modifie, l’enregistre, génère le PDF et la transmet avec les écrans existants. La page « Suivi des devis » (`/devis`, lien depuis « Mon espace ») liste la version courante de chaque devis, filtrable par statut ; les modifications demandées et les devis acceptés apparaissent en premier.

## Règles

- Le client ne répond qu’à un devis de son compte, sur la **version courante** au statut « étude côté client ». Propriétaire, statut et version sont revérifiés au moment de la confirmation, sous le verrou partagé du devis.
- Une seule réponse par version (contrainte unique). Un identifiant d’opération rend la répétition sans effet : après une réponse perdue, « Vérifier ma réponse » renvoie le résultat enregistré, sans doublon.
- Un devis d’un autre client répond comme un devis inexistant (404). Une ancienne version, remplacée par une contre-proposition, n’est plus décidable (`quote_version_replaced`) mais reste consultable avec sa réponse.
- **Acceptation :** version « acceptée » ; l’événement brouillon passe à « accepté » (synchronisation retenue en conception, page 12) avec journalisation `CHANGEMENT_STATUT_EVENEMENT` (identifiant, ancien et nouveau statut, page 13) ; courriel à l’adresse de l’agence (`AGENCY_EMAIL`, adresse de démonstration par défaut). Le courriel passe par la file existante : un incident de livraison n’annule pas l’acceptation.
- **Refus :** version « refusée » ; l’événement n’est pas modifié automatiquement et l’historique est conservé.
- **Modification :** version « modification » avec motif ; aucun courriel supplémentaire, non exigé par le sujet.
- Le client ne modifie jamais les prestations ni les montants. La version transmise reste conservée et figée ; un devis accepté ou refusé n’est plus modifiable.

## Droits et installation

- `ecf_quote_decision` : lecture des données du devis ; insertion des décisions ; mise à jour du seul statut de la version et du statut/révision de l’événement ; insertion des traces et des courriels. Ni montants, ni dates de transmission, ni décisions existantes, ni comptes.
- La contre-proposition utilise le compte existant `ecf_quote_drafts`. Les comptes de transmission, de consultation client et de brouillons lisent les décisions.
- Migration additive `010` (type de courriel `devis_accepte`, identifiant d’opération des décisions), appliquée par `scripts/provision-quote-decisions.py`, intégré à `Start-App.ps1`.

## Tests exécutés le 14 novembre

`tests/app/quote-decisions.py` (avec `quote-decisions-browser.cjs` et `backend/tests/quote-decision-permissions.php`) : **22 contrôles réussis.**

- Droits : visiteur, administratrice et employé refusés ; CSRF exigé ; entrées invalides et motif vide refusés ; un autre client ne peut pas répondre.
- Acceptation : statuts du devis et de l’événement, décision unique, réponse rejouée sans doublon, deuxième réponse refusée, trace MongoDB livrée et vérifiée, un seul courriel à l’agence dans Mailpit, devis non modifiable ensuite.
- Refus : statut refusé, événement inchangé, aucune trace de changement de statut.
- Modification : motif nettoyé et visible par Chloé ; suivi filtré par statut, réservé à l’administratrice.
- Contre-proposition : réservée à l’administratrice et à une version en modification ; version 2 créée une seule fois avec les prestations reprises ; version 1 conservée, visible par le client comme remplacée et non décidable ; version 2 modifiée, transmise puis acceptée.
- Huit refus SQL du compte des réponses.
- Chromium 1280 px : motif obligatoire avec focus, réponse perdue simulée puis vérification, contre-proposition et suivi par Chloé. 390 px : annulation avec retour du focus, acceptation et focus sur le résultat. Pas de débordement horizontal.

Non-régression : `quote-transmission.py` 29/29, `quote-drafts.py` 22/22, `quote-pdf.py` 23/23 ; compilation Vue réussie. [Preuve sans secrets](preuves/decisions-devis-2026-11-14.json).

## Limites

- L’organisation après acceptation (passage en cours, tâches, notes, fin d’événement) reste à développer.
- Le client ne peut pas annuler une réponse en ligne ; un changement d’avis passe par Innov’Events.
- Aucun courriel n’est envoyé pour un refus ou une demande de modification : la page « Suivi des devis » sert à les repérer.
- Les traces MongoDB et les courriels de test restent conservés ; les fiches de test sont supprimées.

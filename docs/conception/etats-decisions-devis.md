# États complémentaires - Décisions du client sur un devis

Version du 3 novembre 2026. Tâche [#5](https://github.com/RomainRas/innovevents-manager/issues/5), conservée ouverte.

## Travail réalisé et fichiers

Deux planches complètent la [maquette WEB03](maquette-web-03-consultation-devis.md) :

- [Confirmations et résultats](https://www.figma.com/design/eAJJZyMSjoOff7mA1pNpCU?node-id=69-599), 1440 × 1092 pixels ; [SVG](../maquettes/web-03-confirmations-resultats.svg).
- [Modification et incidents](https://www.figma.com/design/eAJJZyMSjoOff7mA1pNpCU?node-id=69-600), 1440 × 1544 pixels ; [SVG](../maquettes/web-03-modification-incidents.svg).

Dix extraits représentent des dialogues et états de la même page. Ils ne comptent pas comme dix nouvelles maquettes principales. Les repères entre crochets seront remplacés par les données du devis ; aucun montant ni client réel n’est inventé.

## Sources et distinction des choix

La page 10 de l’[énoncé](../enonce/enonce-ecf-cda.pdf), déjà rapprochée du [wireframe](wireframe-web-03-consultation-devis.md), exige :
- acceptation avec statut accepté et courriel à Innov’Events ;
- demande de modification avec motif obligatoire et contre-proposition possible ;
- refus avec statut refusé.

Les confirmations avant acceptation ou refus, leurs textes, la conservation du motif, l’affichage après réponse et les protections de version sont des choix de conception décrits dans le wireframe et le [parcours commercial](parcours-prospect-devis-evenement.md). Aucun motif de refus ni courriel supplémentaire de refus ou de modification n’est ajouté comme obligation.

## États représentés

| Repère | Situation | Résultat ou prochaine action |
| --- | --- | --- |
| WEB03-CA | Confirmation avant acceptation | Référence, version, événement et total TTC rappelés ; Confirmer l’acceptation ou Annuler |
| WEB03-CR | Confirmation avant refus | Même contexte ; Confirmer le refus ou Annuler, sans demander de motif |
| WEB03-RA | Acceptation enregistrée | Statut accepté et résultat explicite ; retour à la liste des devis |
| WEB03-RR | Refus enregistré | Statut refusé et résultat explicite ; historique conservé |
| WEB03-MV | Motif de modification vide | Erreur près du champ, résumé explicite et conservation des valeurs |
| WEB03-MR | Modification enregistrée | Statut modification, motif affiché et possibilité pour Chloé de préparer une contre-proposition |
| WEB03-A | Enregistrement en cours | Attente explicite ; actions de décision inactives jusqu’au résultat |
| WEB03-E | Échec d’enregistrement confirmé | Aucun succès annoncé ; motif conservé et possibilité de réessayer |
| WEB03-O | Version remplacée, tentative rejetée | Consultation de la version actuelle avant toute nouvelle décision |
| WEB03-I | Réponse réseau interrompue, résultat inconnu | Vérifier le statut avant de répondre à nouveau ; aucune resoumission automatique |

Annuler ferme uniquement le dialogue et ne vaut ni refus ni acceptation. Après une réponse enregistrée, les actions de réponse sont remplacées par le résultat et le statut.

## Règles de traitement à développer

- Le client répond uniquement à un devis affecté à son compte, dans un état permettant cette action.
- Au moment de confirmer, vérifier de nouveau le propriétaire, l’état et la version ; une vérification uniquement lors de l’ouverture du dialogue est insuffisante.
- Le serveur doit empêcher deux réponses concurrentes sur la même version, même si le client utilise plusieurs onglets.
- Conserver la version transmise. Une demande de modification conduit à une nouvelle proposition, puis à une nouvelle décision ; elle ne permet pas au client de modifier les prestations ou les montants.
- WEB03-O s’applique à une tentative rejetée parce que la version a changé ; ne pas effacer une décision historique ni annoncer son absence sans résultat serveur.
- L’échec du seul courriel à l’agence ne doit pas annuler une acceptation enregistrée. Prévoir sa reprise interne sans promettre au client que le courriel a déjà été reçu.
- WEB03-I distingue l’incertitude réseau d’un échec confirmé. Vérifier le statut courant et réconcilier le résultat avant de proposer une nouvelle tentative.
- En cas de reprise, réutiliser un identifiant d’opération et contrôler la version pour éviter les doublons. Cette solution technique reste à implémenter et tester.
- Conserver le motif dans le formulaire courant après échec ; ne pas promettre une conservation après fermeture de la page.
- Bloquer les motifs vides ou composés uniquement d’espaces. Le sujet ne fixe pas de limite maximale de caractères.

## Accessibilité et autres variantes

Prévoir un titre de dialogue accessible, un focus initial prudent, une navigation clavier contenue dans le dialogue, puis le retour du focus au bouton d’origine après annulation. Associer le message d’erreur au champ de motif et annoncer les états de traitement.

Le focus visible, le responsive, la session expirée et l’accès refusé restent à compléter dans les états transversaux. Ne pas afficher le contenu d’un devis à un utilisateur non autorisé. Aucun audit d’accessibilité ni test sur appareil n’est revendiqué.

## Vérifications réalisées

- Concordance avec les trois réponses et le motif requis pour la modification, à partir des documents relus.
- Contextes référence/version présents ; aucun montant, taux ou délai ajouté.
- Retour à la liste après résultat ; absence de nouvelles actions de décision dans ces extraits après réponse.
- Deux planches complètes relues visuellement : pas de texte coupé ni de chevauchement constaté.
- Première planche : 27 textes, 8 cadres internes et 10 instances.
- Seconde planche : 37 textes, 10 cadres internes, 13 instances et un vecteur.
- Police Inter contrôlée ; aucune image d’interface aplatie.
- Réutilisation des messages, champs et boutons existants ; exports SVG préparés.

Les boutons, confirmations et résultats sont représentés dans des maquettes statiques. Aucun devis réel n’a été accepté ou refusé, aucun courriel envoyé, et aucun contrôle serveur exécuté.

## Sauvegarde, rendu et suite

Figma conserve les sources modifiables. Les deux SVG, ce bilan et le lien depuis la documentation principale sont sauvegardés sur dev pour alimenter la conception du dossier. Les planches pourront être jointes en complément des six maquettes principales.

La tâche #5 reste ouverte. Prochaine étape : confirmation, validation et échecs de la note mobile. Les autres états, la validation d’ensemble, le responsive et les exports finaux restent à terminer.

# Wireframe mobile 03 - Fiche client

Version du 18 octobre 2026. Tâche [#5](https://github.com/RomainRas/innovevents-manager/issues/5).

## Travail réalisé

Troisième wireframe mobile : fiche contact simplifiée pour Chloé et José, accessible depuis le nom du client dans la fiche événement.

- [Fiche client dans Figma](https://www.figma.com/design/eAJJZyMSjoOff7mA1pNpCU?node-id=31-331).
- [Notes et états](https://www.figma.com/design/eAJJZyMSjoOff7mA1pNpCU?node-id=31-332).
- [Export SVG de la fiche](../maquettes/wireframes/mobile-03-fiche-client.svg).
- [Export SVG des notes](../maquettes/wireframes/mobile-03-notes.svg).

Le cadre mesure 390 × 1225 pixels de contenu défilant. Les textes et composants restent éditables. La police Inter et les niveaux de gris sont provisoires, avant définition de la charte.

## Consignes utilisées

Source : [énoncé ECF, page 14](../enonce/enonce-ecf-cda.pdf), relue lors de la préparation des wireframes mobile.

| Exigence | Représentation |
| --- | --- |
| Fiche client accessible depuis la fiche événement | Retour à l’événement et rappel de l’événement d’origine |
| Numéro cliquable ouvrant le téléphone | Carte Appeler, avec numéro présenté comme bouton |
| Adresse e-mail cliquable ouvrant la messagerie par défaut | Carte Envoyer un courriel |
| Adresse postale cliquable ouvrant la cartographie pour un itinéraire | Carte Itinéraire |
| CGU et CGV à fournir | Accès en pied de page, contenus à réaliser |

L’action ouvre l’application appropriée avec les coordonnées du client ; elle n’appelle pas et n’envoie pas de courriel automatiquement. Le comportement réel dépendra de l’appareil et sera testé lors du développement.

## Données et choix de conception

- Afficher l’entreprise, le prénom et le nom du contact.
- Conserver le contexte de l’événement d’origine.
- Regrouper chaque coordonnée et son action dans une carte.
- Prévoir des zones tactiles d’au moins 48 pixels de haut.
- Proposer le retour à la fiche événement.
- Limiter cet écran à la consultation et au contact, sans ajouter un formulaire d’édition mobile.

Les valeurs entre crochets sont des repères sans données réelles.

L’itinéraire utilise l’adresse postale du client, pas le lieu de l’événement. La collecte et la conservation de cette adresse doivent être prévues dans la fiche client et le modèle de données. La demande publique de devis comporte un lieu d’événement ; celui-ci ne peut pas être considéré automatiquement comme l’adresse du client.

## États et tests à prévoir

Ces règles complètent les consignes par des choix de conception :

- Coordonnée absente : afficher Téléphone non renseigné, E-mail non renseigné ou Adresse non renseignée ; ne pas proposer de lien actif.
- Coordonnée inutilisable ou application indisponible : expliquer l’échec ; permettre une nouvelle tentative après résolution du problème.
- Contrôler côté serveur les droits et l’accès au bon client.
- Tester les numéros, e-mails et adresses longues sans coupure gênante.
- Vérifier sur appareil l’ouverture du téléphone, de la messagerie et de la cartographie, puis le retour à l’application.

Aucune capacité hors connexion n’est promise. Aucun appel, courriel ou itinéraire réel n’a été lancé.

## Vérifications réalisées

- Les trois coordonnées actionnables demandées page 14 sont représentées.
- L’adresse du client est distinguée du lieu de l’événement.
- L’écran et les annotations ont été relus visuellement : pas de chevauchement ni de texte coupé constaté.
- La police a été vérifiée : Inter.
- Les deux exports SVG sont préparés pour conservation.

Ces vérifications portent sur la conception statique. Les liens natifs, les droits et l’accessibilité sur appareil ne sont pas encore implémentés ni testés.

## Sauvegarde, rendu et avancement

Figma conserve la source modifiable. Les deux SVG et ce document sont sauvegardés sur dev pour alimenter le dossier de conception. Les exports PDF/PNG adaptés au rendu seront préparés après validation.

- Wireframes web : 3 premières versions sur 3.
- Wireframes mobile : 3 premières versions sur 3.
- État complémentaire : saisie rapide d’une note, non comptée comme écran principal supplémentaire.
- Maquettes : 0 sur 6.
- Validation avec Romain, charte graphique et enchaînement : à réaliser.

La tâche #5 reste ouverte : les six wireframes ne constituent pas l’ensemble de ses livrables. Prochaine étape proposée : relecture d’ensemble, puis charte graphique et maquettes.

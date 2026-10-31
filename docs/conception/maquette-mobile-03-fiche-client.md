# Maquette mobile 03 - Fiche client

Version du 31 octobre 2026. Tâche [#5](https://github.com/RomainRas/innovevents-manager/issues/5).

## Travail réalisé et fichiers

Troisième maquette détaillée mobile pour Chloé et José : consultation des coordonnées et accès aux applications de contact depuis la fiche événement.

- [Maquette Figma](https://www.figma.com/design/eAJJZyMSjoOff7mA1pNpCU?node-id=57-394) : 390 × 1244 pixels de contenu défilant.
- [Export SVG](../maquettes/mobile-03-fiche-client.svg).
- [Wireframe, sources et règles détaillées](wireframe-mobile-03-fiche-client.md).
- [Charte graphique](charte-graphique.md).

## Consignes et choix de conception

La maquette reprend les exigences de la page 14 de l’[énoncé](../enonce/enonce-ecf-cda.pdf), relue lors de l’étape précédente, et le contenu du wireframe.

| Exigence | Représentation |
| --- | --- |
| Accès depuis le nom du client sur la fiche événement | Fiche client avec contexte de l’événement et retour |
| Numéro cliquable ouvrant le téléphone | Carte Appeler et numéro dans un bouton |
| Adresse e-mail cliquable ouvrant la messagerie | Carte Envoyer un courriel et adresse dans un bouton |
| Adresse postale cliquable ouvrant un itinéraire | Carte Itinéraire et adresse du client dans un bouton |

L’adresse postale appartient au client ; elle ne doit pas être déduite automatiquement du lieu de l’événement. Sa collecte devra être prévue dans la gestion des clients et le modèle de données.

La présentation reprend l’en-tête bleu nuit, les cartes blanches sur fond clair, les boutons turquoise et Inter. Les actions font au moins 48 pixels de haut ; le bouton d’adresse mesure 64 pixels. Les valeurs entre crochets sont des repères, sans données réelles. Les liens CGU et CGV restent accessibles.

## Vérifications et limites

- Écran complet relu visuellement après correction d’un libellé d’adresse trop étroit ; aucune coupure ni aucun chevauchement constaté dans la version finale.
- 6 cadres internes, 17 textes et 6 instances éditables ; aucune image d’interface aplatie.
- Police contrôlée : Inter ; couleurs et styles repris des fondations existantes.
- Les trois coordonnées demandées sont représentées comme actions.
- Export SVG préparé pour conservation.

La maquette est statique : elle ne lance ni appel, ni courriel, ni itinéraire. Les futures actions ouvriront l’application appropriée, sans appeler ou envoyer automatiquement. Leur comportement, les droits et le retour à l’application devront être vérifiés sur appareil.

Les coordonnées manquantes ou invalides, les applications indisponibles et les erreurs réseau restent décrites dans le wireframe ; leurs variantes visuelles restent à compléter. Les noms, e-mails et adresses longs devront pouvoir revenir à la ligne dans l’interface développée, sans couper les informations. Aucun fonctionnement hors connexion n’est promis.

## Sauvegarde et utilisation pour le rendu

Figma conserve la source modifiable. Le SVG et cette explication sont sauvegardés sur dev pour alimenter la partie conception du dossier. Les exports PDF/PNG adaptés au dépôt final restent à préparer après validation.

Les six maquettes principales existent désormais en première version : trois web et trois mobile. L’écran de saisie de note reste un état complémentaire, non compté parmi les six. Les six wireframes principaux sont également disponibles.

La tâche #5 reste ouverte : enchaînement des écrans, états complémentaires, relecture et validation d’ensemble, puis exports de rendu et bilan de clôture restent à terminer. La prochaine étape est le schéma d’enchaînement, avec les changements de rôle clairement indiqués.

# Maquette mobile 03 - Fiche client

Tâche [#5](https://github.com/RomainRas/innovevents-manager/issues/5).

## Version actuelle (style affiche)

Cette maquette est la première version, dans la charte bleu nuit et turquoise. L’identité a ensuite évolué vers le style affiche (papier crème, rouge, bleu, jaune, titres en Anton) ; l’image ci-dessous montre l’écran tel qu’il est réalisé aujourd’hui. Les contenus, règles et états décrits plus bas restent valables. Tous les écrans actuels : [maquettes-affiche.md](maquettes-affiche.md).

<img src="../maquettes/affiche/mobile-04-fiche-client.webp" alt="Version actuelle" width="300">


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

Les [six états de contact mobile](etats-contact-mobile.md) représentent désormais les coordonnées absentes et les ouvertures impossibles. Les valeurs invalides, les retours de copie et les limites de détection sont documentés ; ils restent à développer et tester. Les noms, e-mails et adresses longs devront pouvoir revenir à la ligne dans l’interface développée, sans couper les informations. Aucun fonctionnement hors connexion n’est promis.

## Sauvegarde et utilisation pour le rendu

Figma conserve la source modifiable. Le SVG et cette explication sont sauvegardés sur dev pour alimenter la partie conception du dossier. Les exports PDF/PNG adaptés au dépôt final restent à préparer après validation.

Les six maquettes principales existent désormais en première version : trois web et trois mobile. L’écran de saisie de note reste un état complémentaire, non compté parmi les six. Les six wireframes principaux sont également disponibles.

Les enchaînements, états communs et états de contact sont maintenant réalisés en première version. Restent notamment les derniers états des formulaires, l’adaptation web, le focus, la validation d’ensemble et les exports ; voir le [bilan de couverture](bilan-couverture-ecrans.md).

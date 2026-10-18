# Wireframe mobile 01 - Événements à venir

Version du 18 octobre 2026. Tâche GitHub : [#5](https://github.com/RomainRas/innovevents-manager/issues/5).

## Travail réalisé

Premier wireframe mobile : tableau de bord simplifié pour Chloé et José, après connexion.

- [Écran modifiable dans Figma](https://www.figma.com/design/eAJJZyMSjoOff7mA1pNpCU?node-id=25-286).
- [Notes et états](https://www.figma.com/design/eAJJZyMSjoOff7mA1pNpCU?node-id=25-287).
- [Export SVG de l’écran](../maquettes/wireframes/mobile-01-evenements-a-venir.svg).
- [Export SVG des notes](../maquettes/wireframes/mobile-01-evenements-a-venir-notes.svg).

Le cadre mesure 390 × 1269 pixels. Sa hauteur représente le contenu défilant, pas une taille physique de téléphone. Wireframe en niveaux de gris, Inter et composants provisoires Simple Design System.

## Source et périmètre

La page 14 de l’[énoncé ECF](../enonce/enonce-ecf-cda.pdf) a été relue directement. L’application mobile doit afficher une liste claire des événements à venir à l’ouverture. Un événement sélectionné donne accès à sa fiche ; celle-ci mène au contact client et permet l’ajout rapide d’une note.

Ce premier écran couvre la liste. La fiche événement et la fiche client sont les deux wireframes suivants à réaliser.

La même page demande des CGU et des CGV : deux accès sont représentés dans le pied de page. Leur contenu et leurs pages restent à réaliser. Le packaging Docker est une exigence technique distincte, à traiter dans la tâche d’environnement et de déploiement.

## Choix de conception

- Afficher le prénom de la personne connectée et une action de déconnexion.
- Proposer une même structure à Chloé et José, en respectant leurs droits.
- Trier les événements à venir du plus proche au plus lointain.
- Présenter une carte avec date de début, heure, nom, lieu, client et bouton Voir l’événement.
- Utiliser des boutons de 48 pixels de haut et une liste défilante.
- Afficher trois cartes pour illustrer la répétition ; ne pas limiter la liste à trois résultats.

La limite des trois prochains événements appartient au widget de l’espace utilisateur web, page 10. La page 14 n’impose pas cette limite au tableau de bord mobile.

Les valeurs entre crochets sont des repères. Elles ne représentent aucun client ou événement réel. Le tri, la présentation des cartes et les dimensions tactiles sont des choix de conception.

## États à développer

| Situation | Présentation proposée |
| --- | --- |
| Chargement | Chargement des événements… |
| Aucun événement à venir | Aucun événement à venir. |
| Échec de récupération | Impossible de charger les événements. + Réessayer |
| Session expirée | Retour à la connexion |
| Liste disponible | Cartes ouvrant la fiche de l’événement sélectionné |

Le sujet ne promet pas de fonctionnement hors connexion. Il faudra signaler une indisponibilité du réseau sans prétendre que les données sont actualisées.

Les contrôles côté serveur devront vérifier les accès autorisés. Les dates, le tri et le comportement au changement de jour seront à préciser et tester lors du développement.

## Vérifications réalisées

- Rapprochement avec la liste, la navigation vers le détail et les CGU/CGV de la page 14.
- Relecture visuelle de l’écran et de ses notes : aucun chevauchement ni texte coupé constaté.
- Inspection : 7 cadres, 20 textes et 6 instances ; interface éditable.
- Police vérifiée : Inter.
- Exports SVG de l’écran et des notes préparés.

Aucun test sur appareil, d’authentification, de récupération des événements, de navigation ou d’accessibilité n’a été exécuté. Les états sont documentés, pas fonctionnels dans ce wireframe statique.

## Sauvegarde et rendu

Figma reste la source modifiable. Les deux SVG et cette explication sont sauvegardés sur dev. Ils alimentent le dossier de conception ; des exports PDF/PNG pour le rendu final seront préparés après validation.

Les notes ne comptent pas comme un second écran. La tâche #5 reste ouverte.

## Avancement

- Wireframes web : 3 premières versions sur 3.
- Wireframes mobile : 1 première version sur 3.
- Maquettes web et mobile : 0 sur 6.
- Charte graphique et enchaînement des écrans : à réaliser.

Prochaine étape : fiche événement mobile avec ses informations clés, l’accès au client et le bouton Ajouter une note.

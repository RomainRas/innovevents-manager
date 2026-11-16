# Maquette mobile 02 - Fiche événement et ajout de note

Tâche [#5](https://github.com/RomainRas/innovevents-manager/issues/5).

## Version actuelle (style affiche)

Cette maquette est la première version, dans la charte bleu nuit et turquoise. L’identité a ensuite évolué vers le style affiche (papier crème, rouge, bleu, jaune, titres en Anton) ; l’image ci-dessous montre l’écran tel qu’il est réalisé aujourd’hui. Les contenus, règles et états décrits plus bas restent valables. Tous les écrans actuels : [maquettes-affiche.md](maquettes-affiche.md).

<img src="../maquettes/affiche/mobile-03-fiche-evenement.webp" alt="Version actuelle" width="300">


## Travail réalisé et fichiers

Deuxième maquette détaillée mobile pour Chloé et José, accompagnée d’un état complémentaire de saisie de note.

- [Fiche événement dans Figma](https://www.figma.com/design/eAJJZyMSjoOff7mA1pNpCU?node-id=55-367) : 390 × 1100 pixels.
- [Ajouter une note dans Figma](https://www.figma.com/design/eAJJZyMSjoOff7mA1pNpCU?node-id=55-392) : 390 × 600 pixels.
- [SVG de la fiche](../maquettes/mobile-02-fiche-evenement.svg).
- [SVG de la saisie de note](../maquettes/mobile-02-saisie-note.svg).
- [Wireframe et règles détaillées](wireframe-mobile-02-fiche-evenement.md).
- [Charte graphique](charte-graphique.md).

Les dimensions représentent le contenu ; le défilement et l’adaptation au clavier restent à développer. L’état de saisie ne compte pas comme une troisième maquette mobile : celle-ci est la fiche client.

## Correspondance avec le sujet

La page 14 de l’[énoncé ECF](../enonce/enonce-ecf-cda.pdf) a été relue directement avant réalisation.

| Consigne | Représentation |
| --- | --- |
| Nom, dates, lieu et client associé | Titre, bloc Informations clés et bloc Client associé |
| Nom du client cliquable vers sa fiche contact | Bouton portant le nom du client |
| Bouton Ajouter une note bien visible | Action principale turquoise sous le titre |
| Interface simple de saisie et enregistrement | Vue dédiée, champ Votre note et bouton Enregistrer la note |

Les liens CGU et CGV sont conservés sur la fiche ; leur contenu reste à rédiger. Les crochets désignent les données futures, sans inventer de client ni d’événement.

## Choix de conception

En-tête bleu nuit, actions turquoise, cartes blanches sur fond clair et police Inter reprennent la première maquette mobile. Les boutons font 48 pixels de haut. Le nom de l’événement reste rappelé dans l’écran de note pour éviter une saisie sur la mauvaise fiche.

Le retour à la liste, l’annulation et la présentation des informations en blocs sont des choix de conception. Après un enregistrement réussi, le retour à la fiche et le message « Note enregistrée. » sont prévus. Une note vide doit être refusée ; un échec doit conserver le texte et permettre de réessayer. Ces comportements sont décrits dans le document du wireframe.

## Vérifications et limites

- Les deux vues complètes ont été relues visuellement : aucun chevauchement ni texte coupé constaté.
- Fiche : 8 cadres internes, 16 textes et 5 instances.
- Saisie : 3 cadres internes, 9 textes, 3 instances et 1 vecteur.
- Police contrôlée : Inter ; aucune image d’interface aplatie.
- Couleurs et styles liés aux fondations existantes ; instances de boutons et de champ conservées.
- Exports SVG avec texte préparés pour sauvegarde.

Il s’agit de maquettes statiques : aucune navigation ni sauvegarde réelle de note n’est implémentée. Les [six états complémentaires de note](etats-note-mobile.md) sont maintenant dessinés et sauvegardés : vide, attente, succès, échec confirmé, résultat incertain et abandon. Droits, rattachement de la note à l’événement, doubles soumissions, retour système, clavier, notes longues et erreurs réseau devront être testés dans l’application. Aucun fonctionnement hors connexion n’est promis.

## Sauvegarde, rendu et clôture

Figma reste la source modifiable ; les deux SVG et cette explication sont enregistrés sur dev. Ils serviront à préparer la partie conception du dossier. Les exports PDF/PNG adaptés au dépôt final restent à préparer après validation des écrans.

Avancement actualisé : six maquettes principales et deux schémas d’enchaînement réalisés en première version, plus la saisie et les états complémentaires. Voir le [bilan de couverture](bilan-couverture-ecrans.md) pour les compléments prioritaires, les autres pages et les conditions de clôture.

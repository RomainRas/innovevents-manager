# Adaptation des écrans web aux petits écrans
Version du 3 novembre 2026 — tâche [#5](https://github.com/RomainRas/innovevents-manager/issues/5), ouverte.

## Travail réalisé
Trois variantes à 390 pixels de large adaptent les maquettes web existantes. Elles représentent le site web consulté sur téléphone ; elles sont distinctes des trois écrans de l’application mobile destinée à Chloé et José.

Les menus communs et les rubriques propres aux rôles disposent de trois états dépliés. Les maquettes restent statiques : les boutons Menu et Rubriques ne sont pas câblés dans un prototype interactif.

| Variante | Dimensions du contenu | Source et sauvegarde |
| --- | --- | --- |
| Demande de devis | 390 × 2214 | [Figma](https://www.figma.com/design/eAJJZyMSjoOff7mA1pNpCU?node-id=84-1101) · [SVG](../maquettes/web-01-demande-devis-petit-ecran.svg) |
| Préparation du devis | 390 × 3880 | [Figma](https://www.figma.com/design/eAJJZyMSjoOff7mA1pNpCU?node-id=84-1230) · [SVG](../maquettes/web-02-preparation-devis-petit-ecran.svg) |
| Consultation du devis | 390 × 2817 | [Figma](https://www.figma.com/design/eAJJZyMSjoOff7mA1pNpCU?node-id=84-1465) · [SVG](../maquettes/web-03-consultation-devis-petit-ecran.svg) |
| Menu visiteur déplié | 390 × 441 | [Figma](https://www.figma.com/design/eAJJZyMSjoOff7mA1pNpCU?node-id=85-1091) · [SVG](../maquettes/web-menu-visiteur-petit-ecran.svg) |
| Menu administrateur déplié | 390 × 821 | [Figma](https://www.figma.com/design/eAJJZyMSjoOff7mA1pNpCU?node-id=85-1132) · [SVG](../maquettes/web-menu-administration-petit-ecran.svg) |
| Menu client déplié | 390 × 693 | [Figma](https://www.figma.com/design/eAJJZyMSjoOff7mA1pNpCU?node-id=85-1199) · [SVG](../maquettes/web-menu-client-petit-ecran.svg) |

Les hauteurs sont celles du contenu défilant, pas des dimensions physiques d’un téléphone.

## Sources et choix
La page 4 de l’[énoncé](../enonce/enonce-ecf-cda.pdf) demande un menu accessible sur les pages et une interface adaptée au support ; la page 6 précise les exigences d’accessibilité. Les pages 8–10 définissent les formulaires et actions concernés. La largeur de 390 pixels et l’organisation ci-dessous sont des choix de conception, pas des dimensions imposées par le sujet.

Les six représentations web et mobile attendues de chaque type restent les écrans principaux. Ces variantes complémentaires ne remplacent pas les wireframes ni les maquettes de l’application mobile.

## Réorganisation
### Navigation
- En-tête compact : nom Innov’Events et bouton Menu.
- Le menu déplié conserve Accueil, Événements, Avis, Contact, Demande de devis et Connexion ou Déconnexion selon le contexte.
- Les espaces connectés affichent un accès aux rubriques ; les menus dépliés montrent les rubriques correspondant au rôle.
- Menu et Rubriques ouvrent la navigation du contexte courant. Prévoir la fermeture explicite et le retour du focus au déclencheur.
- Le menu déplié doit pouvoir défiler si sa hauteur dépasse celle du téléphone. Les coordonnées et données du formulaire ne doivent pas être perdues à son ouverture.
- Les maquettes ne justifient pas de masquer les autres fonctionnalités du projet : les rubriques représentées sont celles déjà présentes dans les écrans sélectionnés.

### WEB01 — Demande
- Champs empilés sur une colonne, avec leurs libellés et indications obligatoires.
- Dix champs conservés : entreprise, nom, prénom, email, téléphone, lieu, type, date, participants et besoin.
- Bouton d’envoi sur la largeur disponible.
- Bloc d’accompagnement placé après le formulaire.
- Typographie de titre reprise du style mobile de la charte ; marges et espacements adaptés avec les variables existantes.

### WEB02 — Préparation
- Client, événement et prestations, puis synthèse et transmission dans l’ordre de lecture.
- Dates et heures restent des champs distincts.
- Chaque prestation conserve libellé, montant HT et retrait ; l’ajout de prestation demeure disponible.
- Sélection d’image, condition de visibilité, totaux et modes de transmission sont conservés.
- La variante illustre le même brouillon incomplet que la version large ; les actions PDF/transmission restent désactivées.
- Les paramètres de TVA et devise demeurent des propositions de conception ; aucun taux, montant ou arrondi n’est ajouté comme exigence.

### WEB03 — Consultation
- Informations de l’événement, prestations, totaux, modification motivée, décisions puis trois prochains événements.
- Le tableau de prestations devient une succession de blocs : chaque montant est explicitement précédé de « Montant HT ».
- Les actions de téléchargement, acceptation, refus et demande de modification restent présentes.
- La mention « formulaire ci-contre » devient « formulaire ci-dessus » pour correspondre à l’ordre vertical.
- Les trois événements à venir restent un widget de l’espace client ; cette limite ne s’applique pas à la liste de l’application mobile.

## Vérifications réalisées
- Relecture visuelle des trois pages et des trois menus ; boutons centrés et indication de position du formulaire corrigée, puis pages contrôlées à nouveau.
- Aucun débordement horizontal des éléments visibles constaté dans les trois cadres à 390 pixels.
- Présence des dix champs de WEB01 contrôlée.
- Champs, prestations et actions de WEB02/WEB03 contrôlés par inventaire.
- Boutons visibles des trois pages : hauteur de 48 pixels.
- Police Inter sur les trois pages et les menus ; aucune image d’interface aplatie.
- Instances existantes, styles et variables conservés. Les menus repliés restent masqués dans les cadres principaux, avec des variantes dépliées séparées.
- Exports SVG avec texte sauvegardés sur dev.

Inventaire des descendants, y compris les éléments masqués : WEB01 comprend 41 textes et 20 instances ; WEB02, 71 textes et 41 instances ; WEB03, 53 textes et 21 instances. Les trois menus ont respectivement 8/14/12 textes et 7/12/10 instances.

## Limites et contrôles à réaliser dans l’application
Ces variantes ne constituent pas un test responsive du code. Seules les compositions large et 390 pixels sont représentées. Définir et vérifier les changements de disposition selon le contenu, notamment sur écrans plus étroits, tablettes, paysage, zoom et avec clavier affiché.

Vérifier les textes longs, la sélection de fichier, les dates natives, la visibilité des erreurs et du focus, les annonces accessibles, l’ouverture/fermeture du menu et la conservation de la saisie. Le contour de focus est illustré dans les [compléments de formulaires](formulaires-reprise-erreurs-focus.md) ; son fonctionnement reste à développer.

L’application n’est pas encore implémentée. Aucun envoi, calcul, contrôle serveur ou navigation réelle n’a été exécuté.

## Rendu et suivi
Figma reste la source modifiable ; les six SVG et ce document sont sauvegardés sur dev pour préparer la partie conception du dossier. Les vues très longues devront être présentées en pages lisibles ou extraits ordonnés dans les exports finaux, sans réduction rendant les textes illisibles.

La tâche #5 reste ouverte. Suite : relecture finale de cohérence entre les écrans, les droits et les parcours, sélection des visuels à remettre, validation avec Romain et préparation des exports de rendu. Le [bilan de couverture](bilan-couverture-ecrans.md) continue de distinguer ces écrans représentatifs des autres pages à concevoir pour l’application.

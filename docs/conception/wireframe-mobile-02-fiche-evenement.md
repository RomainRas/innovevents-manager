# Wireframe mobile 02 - Fiche événement et ajout de note

Tâche [#5](https://github.com/RomainRas/innovevents-manager/issues/5).

## Travail réalisé et fichiers

Deuxième wireframe mobile pour Chloé et José : fiche événement concise, accompagnée de son état de saisie rapide d’une note.

- [Fiche événement dans Figma](https://www.figma.com/design/eAJJZyMSjoOff7mA1pNpCU?node-id=29-304) — 390 × 1081 pixels.
- [État Ajouter une note](https://www.figma.com/design/eAJJZyMSjoOff7mA1pNpCU?node-id=29-305) — 390 × 595 pixels.
- [Annotations de conception](https://www.figma.com/design/eAJJZyMSjoOff7mA1pNpCU?node-id=29-306).
- [Export SVG de la fiche](../maquettes/wireframes/mobile-02-fiche-evenement.svg).
- [Export SVG de la saisie](../maquettes/wireframes/mobile-02-saisie-note.svg).
- [Export SVG des annotations](../maquettes/wireframes/mobile-02-notes.svg).

Les hauteurs correspondent au contenu ; le défilement et l’adaptation au clavier seront à gérer dans l’application. L’état Ajouter une note complète ce wireframe et ne remplace pas le troisième écran mobile attendu : la fiche client.

## Consignes utilisées

Source : [énoncé ECF, page 14](../enonce/enonce-ecf-cda.pdf), relue lors de la préparation des wireframes mobile.

| Exigence | Représentation |
| --- | --- |
| Nom de l’événement | Titre de la fiche |
| Dates | Date et heure de début puis de fin |
| Lieu | Nom du lieu et adresse de l’événement |
| Client associé | Bloc Client associé |
| Nom du client cliquable vers la fiche contact | Bouton portant le nom du client |
| Bouton Ajouter une note bien visible | Action principale juste sous le titre |
| Interface simple de saisie textuelle et enregistrement | État dédié avec zone de texte et bouton Enregistrer la note |

Les liens CGU et CGV restent présents dans la fiche. La page 14 demande ces documents ; leur contenu reste à réaliser.

## Parcours et choix de conception

Depuis la liste, sélectionner un événement ouvre sa fiche. Le nom du client ouvre la fiche contact qui sera conçue ensuite.

Ajouter une note ouvre une vue dédiée, rattachée à l’événement consulté. Enregistrer revient à la fiche après succès, avec le message proposé « Note enregistrée. » Annuler revient sans enregistrer.

Ces choix complètent les exigences :
- Séparer les informations clés et le client pour faciliter la lecture.
- Placer l’ajout de note avant les informations détaillées.
- Prévoir des boutons de 48 pixels de haut.
- Afficher l’événement concerné dans la vue de saisie.
- Demander confirmation avant l’abandon d’un texte déjà saisi.
- Reprendre les composants provisoires et la police Inter des autres wireframes.

Les crochets sont des repères de données, sans coordonnées ni événement réels. Les confirmations, les erreurs et la navigation sont décrites ; les interactions ne sont pas encore câblées dans Figma.

## Validation et erreurs proposées

- Refuser une note vide ou composée uniquement d’espaces : « Saisissez une note avant d’enregistrer. »
- Conserver le texte après un échec : « Impossible d’enregistrer la note. Réessayez. »
- Vérifier côté serveur les droits et le rattachement à l’événement consulté.
- Associer la note à la personne connectée et empêcher les doubles soumissions.
- Ne confirmer qu’après un enregistrement réussi.
- En cas de réseau indisponible, afficher un échec explicite ; aucun fonctionnement hors connexion n’est promis.

Aucune limite maximale de caractères n’est inventée dans cette version. La gestion du clavier, du retour système, des notes longues et des contraintes techniques devra être précisée et testée.

## Vérifications réalisées

- Présence du nom, des dates, du lieu et du client associé.
- Présence des actions Ajouter une note, Enregistrer la note et Annuler.
- Relecture visuelle des trois cadres : aucune coupure ni aucun chevauchement constaté.
- Police contrôlée : Inter.
- Textes et composants éditables conservés ; exports SVG préparés.

Aucun test fonctionnel d’enregistrement, de droits, de réseau, de clavier ou de navigation sur appareil n’a été exécuté. Il s’agit de conception statique.

## Sauvegarde et rendu

Figma reste la source modifiable. Les trois exports SVG et ce document sont sauvegardés sur dev pour conserver les choix et préparer la partie conception du dossier.

Les notes et l’état complémentaire ne sont pas comptés comme des wireframes principaux supplémentaires. Les exports PDF/PNG adaptés au rendu seront préparés après validation.

## Avancement

- Wireframes web : 3 premières versions sur 3.
- Wireframes mobile : 2 premières versions sur 3, avec un état complémentaire d’ajout de note.
- Maquettes : 0 sur 6.
- Charte graphique et enchaînement : à réaliser.


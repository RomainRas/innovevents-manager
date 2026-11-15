# États de la note mobile
Tâche #5.

[Planche Figma](https://www.figma.com/design/eAJJZyMSjoOff7mA1pNpCU?node-id=72-349) — 1362 × 1100 pixels.
[Export SVG](../maquettes/mobile-02-etats-note.svg).
[Source et règles du wireframe](wireframe-mobile-02-fiche-evenement.md).
[Maquette principale](maquette-mobile-02-fiche-evenement.md).

## Travail réalisé
Six extraits à largeur mobile complètent MOB02-N, sans augmenter le nombre de maquettes principales :
- Note vide : message explicite, retour à la saisie sans perte.
- Enregistrement en cours : attente et action inactive.
- Succès confirmé : retour à la fiche événement, message « Note enregistrée. ».
- Échec confirmé : texte conservé dans la saisie et possibilité de réessayer.
- Résultat réseau incertain : vérifier la sauvegarde avant toute nouvelle tentative.
- Abandon d’un texte : continuer la saisie ou abandonner explicitement sans enregistrer.

La page 14 de l’énoncé exige la saisie et l’enregistrement rapide de notes. Les messages, confirmations et règles de reprise sont des choix de conception déjà décrits dans le wireframe, complétés ici pour le résultat réseau incertain.

## Comportements à développer
Vérifier les droits, le rattachement à l’événement et l’auteur côté serveur. Refuser une note vide ou composée d’espaces. Empêcher les doubles soumissions, notamment en réutilisant un identifiant d’opération lors d’une reprise après réponse incertaine. Ne confirmer qu’après sauvegarde effective ; ne pas confondre absence de réponse et absence d’enregistrement.

Le retour système doit suivre la même règle d’abandon que le bouton Annuler. Pendant une requête, éviter une seconde soumission et réconcilier le résultat avant une nouvelle action. Aucun stockage hors connexion ou après fermeture n’est promis. Aucune limite maximale de caractères n’est inventée.

Ces extraits ne montrent pas le clavier ni le champ dans chaque état : la saisie complète demeure dans la maquette principale. Le focus, les annonces accessibles, les notes longues, la session expirée et les tests sur appareil restent à traiter.

## Vérifications
Planche complète relue après correction des retours à la ligne : aucun chevauchement ni texte coupé constaté. Police Inter ; 34 textes, 8 cadres internes et 13 instances. Messages et boutons existants réutilisés ; SVG avec textes exporté.

Aucune note réelle enregistrée et aucun test fonctionnel exécuté : il s’agit d’une conception statique.

## Sauvegarde et rendu
Figma conserve la source modifiable ; SVG et bilan sauvegardés sur dev pour compléter le dossier de conception.

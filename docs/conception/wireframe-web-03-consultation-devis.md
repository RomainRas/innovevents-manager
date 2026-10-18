# Wireframe web 03 - Consultation du devis

Version du 18 octobre 2026. Tâche GitHub : [#5](https://github.com/RomainRas/innovevents-manager/issues/5).

## Travail réalisé

Troisième wireframe web : le client connecté consulte une proposition en statut « étude côté client » et choisit sa réponse.

- [Écran modifiable dans Figma](https://www.figma.com/design/eAJJZyMSjoOff7mA1pNpCU?node-id=23-226).
- [Notes et états](https://www.figma.com/design/eAJJZyMSjoOff7mA1pNpCU?node-id=23-227).
- [Export SVG de l’écran](../maquettes/wireframes/web-03-consultation-devis.svg).
- [Export SVG des notes](../maquettes/wireframes/web-03-consultation-devis-notes.svg).

Écran de 1440 × 1798 pixels à faire défiler, en niveaux de gris. Inter et les composants Simple Design System restent provisoires ; la charte graphique sera définie ensuite.

## Source et couverture

La page 10 de l’[énoncé ECF](../enonce/enonce-ecf-cda.pdf) a été relue directement. Les détails du devis reprennent les données préparées à la page 9 et le [parcours commercial](parcours-prospect-devis-evenement.md).

| Exigence | Présence dans le wireframe |
| --- | --- |
| Consulter les devis affectés à son compte | Détail du devis et action Retour à mes devis |
| Accepter | Bouton Accepter le devis |
| Demander une modification avec motif | Formulaire Motif de modification et bouton d’envoi |
| Refuser | Bouton Refuser le devis |
| Suivre sa demande | Statut visible Étude côté client |
| Widget des trois événements à venir les plus proches | Bloc Prochains événements contenant trois emplacements |
| Accéder aux autres fonctions de son espace | Navigation Mes devis, Mes événements et Mon profil |

Les informations personnelles modifiables, la demande de suppression totale des données et du compte, ainsi que l’évaluation d’un événement terminé restent à développer dans les écrans associés. Elles ne sont pas supprimées du périmètre parce que cet écran représentatif porte sur le devis.

Le sujet prévoit un compte créé automatiquement s’il est absent, avec mot de passe par défaut envoyé par courriel et modifiable à la première connexion. Cette étape se situe en amont ; le wireframe représente un client déjà connecté.

## Organisation de l’écran

À gauche : événement, prestations, synthèse HT/TVA/TTC, téléchargement du PDF et formulaire de modification. À droite : acceptation/refus et prochains événements.

Les valeurs entre crochets sont des emplacements destinés aux données du devis transmis. Aucun taux, montant, nom de client ou calendrier réel n’est inventé. Le client consulte les montants sans pouvoir les modifier.

Les liens de navigation sont représentés, mais ne constituent pas un prototype fonctionnel. Le retour à la liste permettra de retrouver tous les devis affectés au compte ; la liste complète n’est pas dessinée ici.

## Réponses et états attendus

| Réponse | Validation et résultat prévus par le sujet |
| --- | --- |
| Acceptation | Statut accepté et courriel à Innov’Events |
| Modification | Motif obligatoire, statut modification ; Chloé peut préparer une contre-proposition |
| Refus | Statut refusé |

Le sujet n’impose pas de motif pour le refus, ni de courriel supplémentaire pour un refus ou une demande de modification. L’astérisque est donc limité au motif de modification et une phrase précise quand ce champ est obligatoire.

Choix de conception à développer :

- Demander confirmation avant une acceptation ou un refus ; permettre d’annuler cette confirmation.
- Après une réponse enregistrée, remplacer les actions par le statut et un message de résultat.
- Afficher une erreur près du motif s’il est vide ; conserver la saisie après un échec.
- Vérifier côté serveur le propriétaire du devis, son état et sa version courante.
- Empêcher les doubles réponses et les décisions sur une ancienne version.
- Conserver une acceptation enregistrée même si le courriel de notification échoue ; prévoir une reprise de la notification.

Les dialogues de confirmation et les états après réponse sont décrits dans les notes, mais ne sont pas encore dessinés en variantes interactives.

Le widget affichera jusqu’à trois événements à venir du client, du plus proche au plus lointain. Avec moins de trois événements, seuls ceux disponibles seront affichés. Sans événement : « Aucun événement à venir. » Ce comportement de liste vide est un choix de conception.

## Vérifications réalisées

- Rapprochement avec les trois réponses, le motif obligatoire et le widget de la page 10.
- Contrôle visuel de l’écran complet et de ses annotations : aucun chevauchement ni texte coupé constaté.
- Inspection du cadre : 25 cadres, 53 textes, 19 instances et un vecteur.
- Police vérifiée : Inter.
- Exports SVG de l’écran et des notes préparés pour conservation.

Le wireframe reste statique. Aucun test de décision, de droits d’accès, de notification, de téléchargement ou d’accessibilité clavier n’a été exécuté.

## Sauvegarde et rendu

Figma conserve la source modifiable. Les deux SVG et ce document sont enregistrés sur dev pour garder une trace et alimenter le dossier de conception.

Les exports PDF/PNG adaptés au rendu seront préparés après validation des écrans. Un lien Figma seul ne remplace pas les livrables à remettre. Les notes expliquent les choix ; elles ne comptent pas comme un écran supplémentaire.

## Avancement

- Wireframes web : 3 premières versions sur 3, relues visuellement et à valider avec Romain.
- Wireframes mobile : 0 sur 3.
- Maquettes web et mobile : 0 sur 6.
- Charte graphique et enchaînement des écrans : à réaliser.

La tâche #5 reste ouverte. Prochaine étape : premier wireframe mobile, consacré aux événements à venir pour Chloé et José.

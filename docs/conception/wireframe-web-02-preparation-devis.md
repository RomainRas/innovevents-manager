# Wireframe web 02 - Préparation du devis

Tâche GitHub : [#5](https://github.com/RomainRas/innovevents-manager/issues/5).

## Ce qui est réalisé

Le deuxième wireframe web représente la préparation d’un devis par Chloé, après qualification et conversion du prospect en client.

- [Écran modifiable dans Figma](https://www.figma.com/design/eAJJZyMSjoOff7mA1pNpCU?node-id=15-80).
- [Notes et états associés](https://www.figma.com/design/eAJJZyMSjoOff7mA1pNpCU?node-id=19-235).
- [Export SVG de l’écran](../maquettes/wireframes/web-02-preparation-devis.svg).
- [Export SVG des notes](../maquettes/wireframes/web-02-preparation-devis-notes.svg).

L’écran mesure 1440 × 2054 pixels et se consulte par défilement. Il reste en niveaux de gris, avec Inter et des composants provisoires de la bibliothèque Figma Simple Design System. La charte définitive reste à réaliser.

## Sources et couverture

La page 9 de l’[énoncé ECF](../enonce/enonce-ecf-cda.pdf) a été relue directement. Elle décrit la conversion du prospect en client, la reprise de ses informations, l’événement associé, les prestations et la transmission du devis. La page 7 encadre la visibilité publique ; la page 10 traite de la décision ultérieure du client.

| Exigence du sujet | Représentation dans le wireframe |
| --- | --- |
| Reprendre les informations après conversion du prospect | Bloc Client associé et liens vers la fiche et la demande d’origine |
| Nom de l’événement | Champ Nom de l’événement |
| Dates et heures de début et de fin | Quatre champs distincts |
| Lieu | Champ Lieu |
| Statut modifiable | Sélecteur Statut de l’événement |
| Visibilité sur la page Événements | Case Visible sur la page Événements |
| Ajouter une image | Bloc Image de l’événement |
| Prestations avec libellé et montant HT | Lignes répétables avec ajout et retrait |
| Calcul automatique de la TVA et du TTC | Synthèse Total HT, TVA et Total TTC |
| Générer le devis PDF | Bouton Générer le devis en PDF |
| Télécharger pour envoi manuel ou envoyer automatiquement | Boutons Télécharger le PDF et Envoyer au client |
| Passer à l’étude côté client après transmission | Indication de l’état suivant |

Le bouton de conversion du prospect appartient à l’écran prospect, situé en amont. Son absence sur cet écran représentatif ne supprime pas cette fonctionnalité de l’application à développer. Le traitement d’un prospect impossible à satisfaire reste également décrit dans le [parcours commercial](parcours-prospect-devis-evenement.md).

## Choix de conception

Ces décisions organisent le travail de Chloé ; elles ne sont pas présentées comme des consignes supplémentaires du sujet.

- Regrouper à gauche les informations du client, l’événement et les prestations ; réserver la colonne de droite aux totaux et à la transmission.
- Afficher les coordonnées héritées sous forme de repères entre crochets, sans inventer de client réel.
- Montrer un brouillon incomplet. Les boutons PDF et d’envoi sont désactivés dans cet état.
- Proposer une sauvegarde du brouillon distincte de la génération du PDF.
- Prévoir une confirmation explicite de l’envoi manuel pour suivre la transmission. Le téléchargement seul ne prouve pas que le courriel a été envoyé.
- Conserver la navigation commune et ajouter une navigation d’administration. Chloé est connectée : le bouton de session affiche Déconnexion.
- Ne pas ajouter de quantité, prix unitaire ou remise : la page 9 demande ici un libellé et un montant HT par prestation.

Le statut de l’événement et celui du devis sont distingués. Les transitions autorisées devront respecter les droits et le parcours commercial ; le sélecteur ne permet pas à l’administratrice d’accepter un devis à la place du client.

## TVA, devise et arrondis

Le sujet demande un calcul automatique de la TVA et du TTC, mais ne fixe pas de taux de TVA, de devise ni de règle d’arrondi.

Les champs Devise du devis et Taux de TVA sont une proposition de conception à confirmer avant le développement. Ils ne signifient pas que le sujet impose une gestion multidevise ou un taux librement modifiable pour chaque utilisateur.

Aucun taux de 20 %, symbole monétaire ou total chiffré n’a été inventé. Les textes Calculé / Calculée indiquent l’emplacement des résultats. La règle d’arrondi et le périmètre du taux, notamment par devis ou par prestation, restent à préciser avant d’implémenter les calculs et le PDF.

## États, publication et erreurs

La publication d’un événement nécessite l’accord du client et un événement hors brouillon, conformément à la page 7. La case est donc désactivée dans l’état illustré.

La séquence proposée est : enregistrer le brouillon, compléter et vérifier les données, générer le PDF, puis choisir un mode de transmission. Le devis passe à l’étude côté client après transmission. La génération du PDF ne constitue ni un envoi ni une acceptation du client.

Comportements à développer et tester :

- Date/heure de fin antérieure au début : message près des champs concernés.
- Prestation sans libellé ou montant HT invalide : erreur sur la ligne.
- Paramètres de calcul nécessaires manquants : explication et blocage de la finalisation.
- Échec de génération du PDF : conservation du brouillon et possibilité de réessayer.
- Échec du courriel : conservation des données et du PDF, suivi de l’échec technique et reprise sans doublon.

Le panneau de notes explique ces états. Il ne fait pas partie de l’interface et ne compte pas comme un troisième écran.

## Vérifications réalisées

- Rapprochement des champs et actions avec la page 9.
- Contrôle de la condition de visibilité publique de la page 7.
- Relecture visuelle de l’écran complet après ajustement des hauteurs automatiques : aucun texte coupé ou chevauchement constaté.
- Relecture visuelle du panneau de notes.
- Inspection du cadre : 40 cadres, 80 textes, 39 instances et 2 vecteurs ; l’interface reste éditable.
- Contrôle de la police : Inter.
- Export SVG de l’écran et de ses notes pour conservation dans le dépôt.

Ces vérifications portent sur la conception. Aucun calcul, contrôle serveur, génération PDF, envoi de courriel ou test d’accessibilité clavier n’a été exécuté. Le wireframe est statique ; l’enchaînement des écrans reste à réaliser.

## Sauvegarde et utilisation pour le rendu

Figma reste la source modifiable. Les deux SVG et ce document sont sauvegardés sur dev. Ils conservent le travail et ses justifications pour la partie conception du dossier.

Ces wireframes ont servi de base aux maquettes ; leurs exports PDF sont dans `output/pdf/`.

## Avancement de la tâche

- Wireframes web : 2 versions réalisées sur 3.
- Wireframes mobile : 0 sur 3.
- Maquettes web et mobile : 0 sur 6.
- Charte graphique et enchaînement des écrans : à réaliser.


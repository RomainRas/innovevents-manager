# États de préparation et de transmission du devis
Version du 3 novembre 2026 — complément de WEB02, tâche [#5](https://github.com/RomainRas/innovevents-manager/issues/5).

## Travail réalisé
Deux planches de six extraits complètent la [maquette principale](maquette-web-02-preparation-devis.md). Elles réutilisent les messages et boutons existants, les styles Inter et les fondations de la charte. Elles ne remplacent pas le formulaire et ne comptent pas comme de nouvelles maquettes principales.

- [Préparation et génération dans Figma](https://www.figma.com/design/eAJJZyMSjoOff7mA1pNpCU?node-id=75-661), 1440 × 1544 : [SVG](../maquettes/web-02-etats-generation-pdf.svg).
- [Transmission dans Figma](https://www.figma.com/design/eAJJZyMSjoOff7mA1pNpCU?node-id=75-823), 1440 × 1664 : [SVG](../maquettes/web-02-etats-transmission.svg).

## Exigences et choix
L’[énoncé](../enonce/enonce-ecf-cda.pdf), pages 9–10, exige les prestations avec libellé et montant HT, le calcul automatique TVA/TTC, la génération du PDF, le téléchargement pour envoi manuel ou l’envoi par la plateforme, puis le passage à « étude côté client ». La création du compte client s’il n’existe pas reste également à développer ; ces extraits ne représentent pas ce traitement.

Les confirmations, le suivi de version, le blocage des doubles actions et la reprise après incident sont des choix de conception. Le taux de TVA, la devise et les arrondis ne sont pas fixés par le sujet. Les repères entre crochets représentent les données futures ; aucun calcul réel n’est exécuté.

## États représentés
| N° | État | Action ou règle proposée |
| --- | --- | --- |
| 01 | Devis complet | Synthèse client, événement et montants ; génération disponible après validation |
| 02 | Données incohérentes | Résumé d’erreurs sur les dates et une prestation ; retour aux champs |
| 03 | Génération en cours | Bouton désactivé, conserver les données et attendre |
| 04 | Génération échouée | Réessayer sans perdre le devis |
| 05 | PDF disponible | Télécharger ou ouvrir la confirmation d’envoi depuis la plateforme |
| 06 | PDF devenu ancien | Générer une version à jour après modification des données |
| 07 | Confirmation d’envoi | Vérifier email, référence et version ; confirmer ou annuler |
| 08 | Envoi en cours | Bouton désactivé ; attendre le résultat |
| 09 | Envoi confirmé | Courriel accepté par le service d’envoi ; statut étude côté client |
| 10 | Échec d’envoi confirmé | Conserver le PDF, vérifier l’adresse et réessayer |
| 11 | Résultat incertain | Vérifier le résultat avant de relancer ; aucune affirmation d’échec |
| 12 | Envoi manuel | Déclarer explicitement l’envoi réalisé dans sa messagerie, ou revenir sans le confirmer |

## Transitions et protections à développer
- Enregistrer et contrôler côté serveur les informations, les droits et les paramètres de calcul avant génération.
- Le résumé d’erreurs n’exonère pas des messages au niveau des champs : dates et prestation concernée doivent être identifiables. Les champs restent visibles dans la maquette principale ; la variante complète avec erreurs intégrées reste à vérifier lors de la relecture.
- L’état 01 est un extrait de synthèse du devis complet, pas une nouvelle vue complète avec toutes les valeurs saisies.
- Générer ou télécharger le PDF ne constitue pas une transmission. Après modification du devis, empêcher l’envoi d’un document devenu ancien.
- La confirmation d’envoi manuel est une déclaration de Chloé, pas une preuve fournie par le service de messagerie. Après enregistrement réussi, afficher le mode manuel et le statut étude côté client ; ce résultat est décrit ici, sans extrait supplémentaire.
- Le succès d’envoi automatique signifie que le service a accepté le courriel, sans garantir sa réception ni sa lecture. Un incident ultérieur de livraison doit être suivi séparément.
- En cas d’échec d’une nouvelle tentative, conserver toute transmission antérieure dans l’historique : le texte de l’état 10 illustre une première transmission qui a échoué.
- Si le résultat est incertain, vérifier l’opération et conserver sa référence pour éviter les doublons. Prévoir la reprise si l’envoi réussit mais que la mise à jour de son statut échoue.
- Distinguer le statut du devis de celui de l’événement ; Chloé ne peut pas accepter le devis à la place du client.
- Annuler la confirmation ne transmet rien. Les erreurs et attentes conservent le devis et le PDF existant.

## Vérifications et limites
Les deux planches ont été relues visuellement : textes lisibles, sans chevauchement constaté. La génération comprend 34 textes, 9 cadres internes et 13 instances ; la transmission 36 textes, 9 cadres internes et 15 instances. Police Inter vérifiée ; aucune image d’interface aplatie. Les boutons d’attente utilisent la variante désactivée. Les messages et boutons restent des instances ; les styles et variables de la composition existante sont conservés.

Les SVG conservent le texte éditable. Aucun devis, PDF métier ou courriel réel n’a été généré ou envoyé. Les boutons sont statiques ; la navigation, le clavier, les validations, les calculs et les protections serveur restent à développer et tester.

## Sauvegarde, rendu et clôture
Les deux SVG et cette documentation sont sauvegardés sur dev. Figma reste la source modifiable. Ces compléments justifient les décisions de conception et pourront accompagner les six maquettes principales dans le dossier ; ils ne sont pas douze livrables supplémentaires imposés par le sujet.

La tâche #5 reste ouverte : états communs, adaptation du site aux petits écrans, focus, validation d’ensemble et exports finaux restent à préparer. Voir le [bilan de couverture](bilan-couverture-ecrans.md).

# Formulaires : reprise, erreurs et focus
Version du 3 novembre 2026 — tâche [#5](https://github.com/RomainRas/innovevents-manager/issues/5), ouverte.

## Livrable et périmètre
- [Planche Figma](https://www.figma.com/design/eAJJZyMSjoOff7mA1pNpCU?node-id=80-763), 1440 × 1532.
- [SVG avec texte](../maquettes/web-formulaires-reprise-erreurs-focus.svg).
- Complète [WEB01](maquette-web-01-demande-devis.md), [WEB02](maquette-web-02-preparation-devis.md) et les [états de préparation/transmission](etats-preparation-transmission-devis.md).

Quatre extraits présentent un résultat incertain après demande, une date de fin incohérente, des erreurs de prestation et un exemple de focus visible. Ils ne sont pas de nouvelles maquettes principales et ne remplacent pas les formulaires complets.

## Sources et choix
L’[énoncé](../enonce/enonce-ecf-cda.pdf) demande la navigation clavier (page 6), les champs obligatoires de demande (page 8), les dates/heures d’événement et prestations HT (page 9). Les contrôles détaillés, messages, reprise d’opération et contour de focus sont des choix de conception destinés à rendre ces parcours utilisables.

Les dates du 11 et du 12 novembre 2026 servent seulement à illustrer une erreur ; elles ne constituent ni événement réel ni échéance de projet. « abc » est une saisie volontairement invalide. Aucun montant, taux de TVA, devise ou arrondi n’est déduit de ces exemples.

## Comportements prévus
### Résultat incertain de la demande
La réponse réseau peut être interrompue après un enregistrement effectif. Le message ne doit alors affirmer ni succès ni échec. « Vérifier ma demande » contrôlera le résultat de la même opération, sans créer une nouvelle demande.

La réalisation devra prévoir une référence d’opération non devinable et une protection serveur contre les doublons. Pour un visiteur, la vérification doit rester limitée à sa propre opération : aucune recherche publique par email ni exposition de prospects. Tant que ce résultat n’est pas établi, conserver la saisie et éviter la relance automatique. Le mécanisme technique exact reste à développer.

Si seule la notification par courriel échoue après enregistrement confirmé, conserver la confirmation d’enregistrement côté visiteur ; traiter l’échec de notification côté équipe. Ne pas provoquer une seconde demande pour réessayer le courriel.

### Dates et heures
L’extrait montre la date de début valide et la date de fin antérieure signalée en erreur. Le message précise le problème près de la date de fin ; le résumé reste visible au-dessus.

Les heures demeurent des champs distincts dans le formulaire complet, même si elles ne sont pas reproduites dans cet extrait. Comparer les dates et heures ensemble : un événement qui se termine le lendemain peut avoir une heure de fin plus petite que l’heure de début. La règle proposée exige une fin postérieure au début. Le format de saisie réel et les fuseaux devront être précisés avant développement.

### Prestations
Chaque ligne requiert son libellé et son montant HT. Montrer l’erreur sur le champ concerné, conserver ce qui a été saisi, et empêcher la finalisation tant que les données sont invalides. L’exemple ne définit pas la politique des montants nuls/négatifs, les arrondis ou le format décimal ; ces choix restent à arrêter.

Le bouton « Enregistrer les corrections » représente une tentative après correction. Le serveur doit refuser la finalisation si des erreurs subsistent. La sauvegarde éventuelle d’un brouillon incomplet reste distincte de la génération du PDF.

### Focus et accessibilité
L’exemple montre un contour bleu nuit de 2 pixels autour du champ actif, en réutilisant une couleur de la charte. La tabulation suit l’ordre logique des éléments interactifs ; Maj + Tabulation permet de revenir en arrière.

À développer et vérifier :
- associer chaque libellé et message d’erreur à son champ ;
- indiquer les champs invalides autrement que par la seule couleur ;
- conserver le focus visible sur les champs, boutons et liens, y compris dans les menus et dialogues ;
- permettre d’atteindre les erreurs sans perdre la saisie ;
- annoncer les résultats utiles sans déplacer le focus à chaque mise à jour ;
- tester l’absence de piège au clavier et le retour après fermeture d’un dialogue.

Le contour illustré est un exemple de conception : il ne constitue pas à lui seul une validation d’accessibilité du site.

## Vérifications et limites
La planche complète a été relue visuellement. Le contour de la date de début a été remis à l’état normal pour réserver le signalement d’erreur à la date de fin ; la formulation du parcours clavier a été précisée, puis la planche a été contrôlée à nouveau.

36 textes, 11 cadres internes et 13 instances ; police Inter contrôlée et aucune image d’interface aplatie. Les composants existants restent réutilisés et les textes sont conservés dans le SVG. Aucune validation serveur, demande réelle, navigation clavier ou génération de devis n’a été exécutée.

## Sauvegarde, rendu et suivi
Source modifiable dans Figma, SVG et explication sur dev. Cette planche justifie la conception des formulaires et pourra être intégrée aux annexes retenues ; elle n’est pas un livrable supplémentaire imposé par le sujet.

La tâche #5 reste ouverte. Suite prioritaire : adaptation des trois écrans web aux petits écrans, cohérence des interactions/focus, relecture d’ensemble et exports finaux. Les montants et paramètres de calcul réels restent à définir avant développement ; les tests fonctionnels appartiendront à cette phase.

# États complémentaires - Demande de devis

Tâche [#5](https://github.com/RomainRas/innovevents-manager/issues/5).

## Travail réalisé et fichiers

- [Planche Figma](https://www.figma.com/design/eAJJZyMSjoOff7mA1pNpCU?node-id=67-569), 1440 × 1172 pixels.
- [Export SVG](../maquettes/etats/web-01-etats-formulaire.svg).
- [Maquette principale](maquette-web-01-demande-devis.md) et [wireframe avec règles et sources](wireframe-web-01-demande-devis.md).
- [Composant Message de formulaire](https://www.figma.com/design/eAJJZyMSjoOff7mA1pNpCU?node-id=66-295), dans la page Charte graphique.

Quatre extraits d’interface complètent WEB01 : confirmation, validation des champs, envoi en cours et échec d’enregistrement confirmé. Ils ne remplacent pas le formulaire complet, ne suppriment aucun des dix champs obligatoires et ne constituent pas des maquettes principales supplémentaires.

## Sources et choix

La page 8 de l’[énoncé](../enonce/enonce-ecf-cda.pdf), transcrite dans le document du wireframe, impose les dix champs, l’enregistrement du prospect, la notification de l’agence et un remerciement modifiable. La planche reprend son exemple : « Merci pour votre demande. Chloé vous recontactera dans les plus brefs délais pour discuter de votre projet. »

Les textes d’erreur, le bouton Retour à l’accueil, les repères des états et les règles de reprise sont des choix de conception. Ils ne sont pas présentés comme des formulations imposées par le sujet.

## États représentés

| Repère | Déclencheur | Présentation et comportement prévu |
| --- | --- | --- |
| WEB01-C | Enregistrement confirmé | Message de succès et texte de remerciement configurable ; retour à l’accueil possible |
| WEB01-V | Validation refusée | Résumé explicite, bordures et messages près des champs ; conservation de la saisie |
| WEB01-A | Requête en cours | Message d’attente et action d’envoi visuellement désactivée ; empêcher les soumissions répétées |
| WEB01-E | Non-enregistrement confirmé | Échec explicite, saisie conservée dans le formulaire et bouton Réessayer |

Les deux champs montrés en erreur sont des exemples : entreprise vide et adresse « contact@ » invalide. Ils n’épuisent pas les règles de validation du formulaire. Les autres champs restent dans la maquette principale.

Le message configurable doit être affiché comme du texte maîtrisé, sans permettre d’injecter du contenu exécutable. La mise en œuvre de cette protection reste à réaliser.

## Cas à distinguer avant développement

- Échec confirmé de l’écriture : utiliser WEB01-E ; conserver les valeurs dans le formulaire courant.
- Réponse réseau perdue ou délai dépassé : l’enregistrement peut avoir réussi. Ne pas afficher une affirmation de non-enregistrement sans vérification. Message proposé : « Nous n’avons pas pu confirmer l’enregistrement de votre demande. Vos informations sont conservées. » Cette variante reste à dessiner.
- Nouvelle tentative après résultat incertain : prévoir un identifiant de soumission réutilisé et un contrôle serveur pour retrouver le résultat ou éviter un doublon. Ce mécanisme est un choix technique à développer et tester.
- Seul le courriel à l’agence échoue après enregistrement : conserver la demande, confirmer son enregistrement au visiteur et permettre une reprise interne de la notification ; ne pas lui demander de recréer sa demande.
- Un double clic bloqué dans l’interface ne remplace pas la protection serveur contre les doublons.
- La conservation annoncée concerne le formulaire courant ; aucun stockage persistant après fermeture ou rechargement de la page n’est promis.

## Accessibilité à implémenter

Les erreurs associent couleur et texte. Prévoir des libellés reliés aux champs, des messages d’erreur associés, un résumé accessible et un déplacement de focus cohérent vers la première erreur. Annoncer attente et résultat aux technologies d’assistance ; rendre le bouton réellement inactif pendant l’envoi.

La variante de focus visible, la navigation clavier, les annonces et le responsive restent à compléter et tester. La planche seule n’atteste pas de conformité d’accessibilité.

## Composants et vérifications

Les champs et boutons réutilisent les instances Simple Design System déjà présentes. Un composant local de message a été ajouté avec trois tons : succès, erreur et information. Les titres et messages sont exposés comme propriétés de texte ; couleurs, marges, espacements et rayons réutilisent les variables existantes, et la typographie les styles Inter.

Contrôles réalisés :
- Relecture visuelle de la planche complète après réglage des hauteurs automatiques ; aucun texte coupé ni chevauchement constaté.
- 25 textes, 8 cadres internes et 10 instances ; aucune image d’interface aplatie.
- Police Inter vérifiée.
- Quatre messages rattachés aux trois variantes du composant ; styles et liaisons de variables contrôlés.
- Export SVG préparé pour conservation.

Il s’agit de conception statique : aucune soumission, validation serveur, notification, conservation réelle de données ni fonction de reprise n’a été testée.

## Sauvegarde, rendu et suite

Figma reste la source modifiable. SVG et documentation sont enregistrés sur dev pour la partie conception du dossier. La planche pourra être jointe en complément des six maquettes principales lors de la préparation des exports adaptés au dépôt.

Les états réseau incertain, focus, autres erreurs, validation d’ensemble et exports finaux restent à compléter.

# Maquette web 03 - Consultation du devis

Version du 18 octobre 2026. Tâche [#5](https://github.com/RomainRas/innovevents-manager/issues/5).

## Travail réalisé

Troisième maquette web détaillée : le client connecté consulte le devis qui lui est affecté et choisit sa réponse.

- [Maquette Figma](https://www.figma.com/design/eAJJZyMSjoOff7mA1pNpCU?node-id=48-509).
- [Export SVG](../maquettes/web-03-consultation-devis.svg).
- [Wireframe, sources et règles](wireframe-web-03-consultation-devis.md).
- [Charte graphique](charte-graphique.md).

Le cadre mesure 1440 × 1885 pixels de contenu défilant. Le wireframe en niveaux de gris reste conservé séparément.

## Exigences conservées

L’écran reprend les exigences de l’énoncé page 10 :
- Consultation d’un devis affecté au client, avec retour vers la liste.
- Acceptation : statut accepté et courriel à Innov’Events.
- Demande de modification : motif obligatoire, statut modification et possibilité de contre-proposition par Chloé.
- Refus : statut refusé, sans motif supplémentaire imposé.
- Suivi du statut et widget des trois prochains événements les plus proches.

Les détails événement et prestations reprennent la proposition préparée page 9. Les montants, la devise, les dates et le taux sont des repères entre crochets, pas des valeurs inventées.

## Choix graphiques

- Navigation de l’espace client et panneau de réponse en bleu nuit.
- Bouton d’acceptation en turquoise ; refus identifié par son libellé et son texte rouge sur blanc.
- Demande de modification séparée, avec action corail.
- Cartes blanches, totaux sur fond clair et Total TTC mis en évidence.
- Police Inter et composants éditables conservés.

La couleur complète les libellés, elle ne remplace pas l’explication des actions. Le client ne peut pas modifier directement les prestations ou les montants.

## États et règles à développer

Les règles documentées dans le wireframe restent applicables : confirmation avant acceptation/refus, motif requis pour la modification, protection contre les doubles réponses, vérification du propriétaire, du statut et de la version courante côté serveur.

Après succès, le statut et un message doivent remplacer les actions. En cas d’échec d’enregistrement, ne pas annoncer un succès. L’échec du seul courriel ne doit pas effacer une acceptation enregistrée.

Cette maquette montre le devis en étude côté client. Les confirmations, erreurs, états après réponse et interactions ne sont pas encore dessinés comme variantes fonctionnelles. Aucun courriel ni aucune réponse réelle n’a été envoyé.

## Vérifications

- Présence des trois réponses, du motif obligatoire et du widget de trois événements.
- Menu commun, déconnexion et mentions légales conservés.
- Contrôle visuel complet : textes et boutons lisibles, sans chevauchement constaté.
- Export SVG préparé, éléments éditables maintenus.

Aucun test fonctionnel, d’autorisation, d’accessibilité clavier ou de responsive n’a été exécuté.

## Sauvegarde et suite

Figma reste la source modifiable. SVG et documentation sont sauvegardés sur dev. Les exports pour le dossier final seront préparés selon le format accepté au dépôt.

Avancement : trois maquettes web sur trois en première version ; zéro maquette mobile sur trois. Les six wireframes principaux sont réalisés en première version. La tâche #5 reste ouverte pour les maquettes mobile, les états complémentaires, l’enchaînement et les exports.

Prochaine étape : maquette mobile des événements à venir.

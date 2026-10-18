# Maquette web 02 - Préparation du devis

Version du 18 octobre 2026. Tâche [#5](https://github.com/RomainRas/innovevents-manager/issues/5).

## Travail réalisé

Deuxième maquette détaillée web, destinée à Chloé dans son espace d’administration.

- [Maquette Figma](https://www.figma.com/design/eAJJZyMSjoOff7mA1pNpCU?node-id=47-364).
- [Export SVG](../maquettes/web-02-preparation-devis.svg).
- [Wireframe, exigences et choix détaillés](wireframe-web-02-preparation-devis.md).
- [Charte graphique](charte-graphique.md).

Le cadre mesure 1440 × 2188 pixels de contenu défilant. Il reprend le wireframe sans remplacer sa version en niveaux de gris.

## Périmètre et contenu

L’écran représente un brouillon incomplet après conversion du prospect en client :
- Coordonnées du client associé et accès à sa fiche ou à la demande d’origine.
- Nom, début et fin avec heures, lieu, statut, visibilité publique et image de l’événement.
- Prestations avec libellé et montant HT, ajout et retrait de lignes.
- Synthèse HT, TVA et TTC ; sauvegarde du brouillon.
- Génération du PDF, téléchargement, envoi automatique ou confirmation d’un envoi manuel.

Les sources restent l’énoncé page 9 et la condition de visibilité publique page 7. La préparation, la génération du PDF, la transmission et la décision du client sont des étapes distinctes.

La devise, le taux de TVA et les règles d’arrondi ne sont pas fixés par le sujet. Les contrôles de paramétrage sont des propositions de conception ; aucun chiffre de calcul n’a été inventé.

## Direction graphique

La charte bleu nuit / turquoise est appliquée avec sobriété pour la gestion :
- Navigation administrative bleu nuit, rubrique Devis active en turquoise.
- Cartes blanches sur fond clair ; titres et libellés structurés.
- Sauvegarde et total TTC mis en évidence.
- Champs, boutons et textes liés aux fondations de la charte.
- Police Inter et composants éditables conservés.

Le corail, proposé pour dynamiser les pages publiques, n’est pas ajouté systématiquement aux écrans de travail.

## États représentés et limites

La maquette montre un brouillon incomplet. Les quatre actions PDF et transmission restent désactivées. Le texte explique qu’il faut compléter le devis puis générer le PDF avant l’envoi. La visibilité publique reste indisponible tant que les conditions prévues ne sont pas réunies.

Les erreurs, les reprises après échec et les transitions sont détaillées dans le document du wireframe. Leurs variantes visuelles, ainsi que les états de survol et de focus, restent à compléter. La maquette n’exécute ni calcul, ni téléversement, ni génération de PDF, ni courriel.

## Vérifications réalisées

- Conservation des champs événement, des prestations et de la synthèse.
- Conservation des deux modes de transmission.
- Contrôle visuel complet après correction du contraste des boutons secondaires : menu, actions client, image et prestations lisibles.
- Dimensions des boutons contrôlées dans la mise en page ; textes éditables et instances conservés.
- Export SVG préparé pour conservation.

Ces contrôles ne constituent pas un test fonctionnel, un audit d’accessibilité ou une validation sur mobile. Le comportement responsive du site web reste à préparer.

## Sauvegarde et rendu

Source modifiable Figma, SVG et explication sur dev. Les exports destinés au dossier seront préparés dans le format accepté au dépôt ; le lien Figma reste un complément.

Avancement : maquettes web 2/3, maquettes mobile 0/3. Les six wireframes principaux sont réalisés en première version. La tâche #5 reste ouverte.

Prochaine étape : maquette de consultation du devis par le client.

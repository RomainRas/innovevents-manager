# États communs de consultation et d’accès
Tâche [#5](https://github.com/RomainRas/innovevents-manager/issues/5).

## Travail réalisé et sauvegardes
Une planche de six extraits réutilisables complète les écrans principaux :
- [Planche Figma](https://www.figma.com/design/eAJJZyMSjoOff7mA1pNpCU?node-id=77-733), 1440 × 1400.
- [Export SVG](../maquettes/etats/etats-communs-consultation-acces.svg).
- [Bilan de couverture](bilan-couverture-ecrans.md).

Ces états sont des choix de conception, pas six maquettes supplémentaires imposées par le sujet. Ils réutilisent les messages, boutons, variables et styles déjà présents. Les exemples portent sur les événements ; leurs libellés doivent être adaptés aux devis, clients, notes et autres listes.

## Sources et portée
L’[énoncé](../enonce/enonce-ecf-cda.pdf) prévoit le retour à l’action demandée après connexion (page 5), l’accessibilité (page 6), les filtres des événements publics (page 7), les droits des espaces (pages 10–12) et la consultation mobile (page 14). Il n’impose pas les textes ni la présentation de ces six états. Les [droits analysés](utilisateurs-et-droits.md) restent la référence pour décider quelles données et actions sont accessibles.

La planche décrit des modèles communs ; elle ne signifie pas qu’ils sont déjà intégrés visuellement à chaque écran ni adaptés au format mobile.

## Règles par état
| État | Déclencheur | Comportement proposé |
| --- | --- | --- |
| Chargement | Lecture en cours | Message explicite et action d’actualisation indisponible ; pas de fausse liste vide |
| Liste vide | Lecture réussie sans événement à venir | Message neutre, possibilité d’actualiser ; aucun bouton de création ajouté à un rôle sans droit de création |
| Aucun résultat | Filtres actifs sans correspondance | Conserver les critères visibles et proposer leur réinitialisation |
| Erreur de lecture | Échec réseau ou serveur | Expliquer l’indisponibilité, permettre de réessayer ; ne pas affirmer que les données n’existent pas |
| Session expirée | Authentification devenue invalide | Reconnexion, puis retour vers une destination interne autorisée ; revérifier les droits |
| Accès refusé | Session valide mais droit insuffisant | Message générique et retour vers l’espace autorisé, sans révéler de données protégées |

L’exemple « aucun événement à venir » concerne les listes à venir : pour une liste de tous les événements, utiliser « Aucun événement disponible ». Le modèle de filtres ne crée pas une obligation d’ajouter des filtres à l’application mobile : les filtres date/type/thème sont demandés sur le site public.

Pour une page publique, adapter le chargement sans mention du compte. Ne pas afficher une demande de connexion à la place d’une erreur de lecture publique. Ne pas confondre accès interdit et session expirée.

## Application aux parcours
- MOB01 et widgets des prochains événements : chargement, vide et échec de lecture.
- Liste publique des événements : chargement, vide, aucun résultat et échec de lecture.
- Listes des devis/clients et consultation des fiches : messages adaptés au contenu ; accès contrôlé côté serveur.
- Espaces connectés : session expirée et accès refusé. Après reconnexion, reprendre la consultation, sans rejouer automatiquement une acceptation, suppression, sauvegarde ou transmission.
- Une nouvelle tentative de lecture ne doit pas déclencher de mutation ni d’envoi de courriel.
- Un brouillon non enregistré demande un traitement spécifique de conservation et de confidentialité ; cette planche ne promet pas sa récupération automatique.
- Si des données anciennes restent affichées pendant une actualisation échouée, les signaler comme non actualisées. Ce cas est documenté ici, sans variante supplémentaire.

## Accessibilité à développer et tester
Prévoir l’annonce des changements d’état, un ordre de focus cohérent et des boutons nommés clairement. Ne pas déplacer le focus à chaque actualisation automatique. Après reconnexion, rétablir un point de reprise compréhensible. Ces règles ne sont pas démontrées par une maquette statique.

## Vérifications
- Contrôle visuel de la planche complète ; bouton de réinitialisation harmonisé, puis second contrôle visuel.
- 33 textes, 9 cadres internes et 12 instances ; police Inter contrôlée.
- Aucune image d’interface aplatie ; messages et boutons conservés en instances.
- SVG avec texte, sauvegardé sur dev.
- Aucune requête applicative, authentification ou vérification fonctionnelle exécutée.

## Usage pour le rendu et clôture
Figma reste la source modifiable. Le SVG et cette explication sont conservés sur dev pour justifier la conception et préparer les exports retenus pour le dossier. La planche complète les six maquettes principales ; elle n’en augmente pas le nombre.

Cette étape achève la préparation des modèles communs, pas toute la tâche #5. Restent les cas de contact mobile indisponible, les derniers états des formulaires, l’adaptation du site aux petits écrans, le focus, la validation d’ensemble et les exports finaux.

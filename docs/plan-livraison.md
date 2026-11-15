# Plan de livraison

Une fois la conception terminée et les premiers blocs développés, j’ai fixé l’ordre de développement du reste du projet jusqu’au rendu. L’état final des fonctionnalités, avec les limites encore valables, est dans le [bilan des fonctionnalités](bilan-fonctionnalites.md).

## Ordre de développement

1. **Parcours commercial complet**, de la demande de devis à la réponse du client : prestations et calculs, PDF, transmission et décision du client, avec l’historique des versions. C’est le cœur du besoin de l’agence.
2. **Mise en ligne dès que ce parcours fonctionne** : choix d’un hébergement compatible avec tous les services, secrets de production, HTTPS et vrais courriels. L’objectif est de régler les contraintes d’hébergement au plus tôt, et pas la veille du rendu.
3. **Statuts, accord de publication et espaces** public, client, employé et administrateur, selon les exigences de l’énoncé.
4. **Application mobile** : consultation des événements et des clients, contact en un geste et note rapide, testées sur téléphone.
5. **Stabilisation et rendu** : tests et couverture, diagrammes, sauvegarde et restauration, guides, copie à rendre, liens et accès du correcteur.

## Règles que je me suis fixées

- Avancer par blocs utilisables et testés, un seul à la fois, plutôt que d’ajouter des améliorations visuelles tant qu’un parcours attendu manque.
- Ne considérer un bloc comme terminé qu’une fois développé, testé et documenté : une fonctionnalité seulement conçue, présente dans le schéma de base ou simulée par des données n’est pas terminée.
- Tenir le kanban à jour à chaque étape, avec les réalisations, les preuves de tests et les limites.

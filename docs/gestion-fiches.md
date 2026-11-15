# Gestion des fiches clients et des événements (administratrice)

Version du 15 novembre 2026. Exigences couvertes : Chloé gère tous les événements (création, modification, suppression, statut « accepté », « en cours », « terminé » ou « annulé ») et les clients (création, modification, suppression), avec la journalisation demandée.

## Où

Les commandes apparaissent pour l’administratrice dans les écrans de l’espace équipe ([espace-employe.md](espace-employe.md)) ; les employés gardent la consultation seule.

| Écran | Commandes de Chloé |
| --- | --- |
| `/equipe/clients` | « Nouveau client » |
| `/equipe/clients/:id` | « Modifier la fiche », « Nouvel événement pour ce client », « Supprimer la fiche… » |
| `/equipe/evenements` | « Nouvel événement » |
| `/equipe/evenements/nouveau`, `/equipe/evenements/:id/modifier` | Formulaire de l’événement (client, nom, lieu, type, thème, dates et heures de Paris, participants) |
| `/equipe/evenements/:id` | Panneau « Gestion de l’événement » : changement de statut, modification, ajout de tâche (assignée ou non), suppression ; « Supprimer la tâche » dans la liste |

## Règles

- **Statut** : vers « accepté », « en cours », « terminé » ou « annulé ». Le nom et les dates doivent être renseignés (sauf pour annuler). « Annulé » est définitif : plus de modification, de changement de statut ni de nouvelle tâche.
- **Modification d’un événement** : contrôle de révision ; si quelqu’un a modifié l’événement entre-temps, l’enregistrement est refusé (409) au lieu d’écraser.
- **Suppressions protégées** : un événement **avec devis** ne peut pas être supprimé (historique commercial : on l’annule) ; sinon il est supprimé avec ses notes, tâches et avis. Une fiche client n’est supprimable que **sans compte, sans événement et sans demande de devis**. Confirmation explicite obligatoire.
- **Tâches** : assignées à une personne active de l’équipe (jamais un client), ou non assignées. Seule la personne assignée les fait avancer (voir l’espace employé).

## Journal (MongoDB, collection `logs`)

| Action | Détail enregistré |
| --- | --- |
| Création d’un client | `CREATION_CLIENT` : id et nom du client |
| Modification d’un client | `MODIFICATION_CLIENT` : id du client |
| Suppression d’un client | `SUPPRESSION_CLIENT` : id et nom du client |
| Création d’un événement | `CREATION_EVENEMENT` : id et nom de l’événement |
| Changement de statut | `CHANGEMENT_STATUT_EVENEMENT` : id, ancien et nouveau statut |

Les entrées sont écrites dans la même transaction que la modification (table `journal_a_emettre`), puis livrées dans MongoDB par le traitement du journal, qui valide leur format. Le test vérifie la livraison effective.

## Sécurité

Compte MySQL dédié `ecf_records` (`scripts/provision-records.py`, aucune migration) : écriture limitée aux colonnes des fiches clients, des événements et des tâches ; lecture seule des devis, demandes et comptes pour les contrôles avant suppression ; aucun droit sur les devis eux-mêmes.

## Tests

| Fichier | Contrôles | Résultat du 15 novembre 2026 |
| --- | --- | --- |
| `tests/app/admin-records.py` | Réservé à l’administratrice, CSRF, création / modification / suppression de client et journal, création d’événement sans demande, dates vérifiées, révision, statuts (invalide, inchangé, dates requises, annulation définitive) et journal, tâches (responsable vérifié, non assignée, suppression), suppressions protégées (devis, compte, événements), suppression en cascade des notes, **livraison du journal dans MongoDB**, aucun droit d’écriture sur les devis | 36/36 |
| `tests/app/admin-records-browser.cjs` | Parcours de Chloé à 1280 px puis 390 px : fiche client (erreur, création, modification), événement créé pour ce client, statut, tâche ajoutée puis supprimée, modification, événement avec devis protégé, suppressions ; focus et absence de débordement | 20/20, trois exécutions successives |
| Non-régression | Toutes les suites HTTP et navigateur, compilation Vue | réussies |

## Limites

- Une image d’événement déjà envoyée n’est pas effacée du disque quand l’événement (sans devis) est supprimé.
- La modification d’une tâche existante (titre, responsable) n’est pas proposée : la supprimer et la recréer.

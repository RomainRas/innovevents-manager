# Espace employé : clients, événements, notes et tâches

Exigence couverte : EMP-01 (consultation des clients et événements, notes collaboratives, statut des tâches assignées ; la modération des avis est décrite dans [espace-client-avis.md](espace-client-avis.md)). L’administratrice hérite de toutes ces fonctions.

## Écrans

| Écran | Contenu |
| --- | --- |
| `/equipe/clients` | Tous les clients, recherche par entreprise, contact, adresse électronique ou ville ; téléphone et adresse cliquables. |
| `/equipe/clients/:id` | Fiche client (contact, coordonnées, adresse, compte ouvert ou non) et historique de tous ses événements. |
| `/equipe/evenements` | Tous les événements, y compris les brouillons, recherche (nom, lieu, client) et filtre par statut. |
| `/equipe/evenements/:id` | Fiche projet (statut, dates, lieu, type, thème, participants, client), tâches et notes de l’équipe. |

Les liens se trouvent dans « Mon espace » pour les employés et l’administratrice.

## Règles

- **Consultation seulement** pour les clients et les événements : le compte MySQL `ecf_team` n’a aucun droit d’écriture sur ces tables (« sans pouvoir modifier les éléments structurels »).
- **Notes** (5 000 caractères au maximum) : tout membre de l’équipe en ajoute sur n’importe quel événement. L’auteur modifie ou supprime ses propres notes ; l’administratrice peut modifier ou supprimer toutes les notes. Le sujet ne précise pas ce périmètre : ce choix évite qu’un employé réécrive le message d’un collègue tout en laissant Chloé gérer les communications.
- **Tâches** : chacun voit toutes les tâches d’un événement. Seule la personne assignée fait avancer sa tâche, une étape à la fois : « à faire » → « en cours » → « terminé ». Une tâche non assignée ou assignée à un collègue n’affiche aucun bouton (refus 403 côté serveur), y compris pour l’administratrice. Un changement concurrent est détecté (409).
- Les notes ajoutées alimentent le widget « Notes récentes » du tableau de bord administrateur.
- Démonstration : cinq tâches fictives sur deux événements à venir (migration `014_team_space.sql`, rejouable sans doublon).

## Tests

| Fichier | Contrôles | Résultat |
| --- | --- | --- |
| `tests/app/team-space.py` | Droits (client refusé, CSRF), liste et recherche des clients (injection inoffensive), fiche et historique, liste et filtre des événements, fiche projet, notes (vide, longue accentuée, auteur, collègue refusé, administratrice, tableau de bord), tâches (étapes, conflit, non assignée, autre personne), absence d’écriture SQL sur clients et événements | 36/36 |
| `tests/app/team-space-browser.cjs` | José à 390 px : recherche, fiche client, fiche événement, tâche, note avec erreur, ajout et modification ; Chloé à 1280 px : filtre, note d’un collègue, suppression annulée puis confirmée ; focus et absence de débordement | 18/18, trois exécutions successives |
| Non-régression | Espace client, administration, pages publiques, authentification, comptes, décisions de devis, conversion, événements, compilation Vue | réussis |

Les essais restaurent les notes, statuts de tâches et comptes temporaires ; la base de démonstration a été vérifiée sans reste.

## Limites

- La création et l’assignation de tâches ne sont pas proposées dans l’interface (non demandées pour l’employé ; à prévoir avec la gestion des événements par l’administratrice).
- Les notes globales (communications générales de Chloé) restent en lecture seule dans « Mon espace ».

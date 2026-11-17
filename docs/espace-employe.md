# Espace employé : clients, événements, notes et tâches

Exigences couvertes : EMP-01 (consultation des clients et événements, notes collaboratives, statut des tâches assignées ; la modération des avis est décrite dans [espace-client-avis.md](espace-client-avis.md)) et les notes globales de l’administratrice (énoncé, page 11). L’administratrice hérite de toutes ces fonctions.

## Écrans

| Écran | Contenu |
| --- | --- |
| `/equipe/clients` | Tous les clients, recherche par entreprise, contact, adresse électronique ou ville ; téléphone et adresse cliquables. |
| `/equipe/clients/:id` | Fiche client (contact, coordonnées, adresse, compte ouvert ou non) et historique de tous ses événements. |
| `/equipe/evenements` | Tous les événements, y compris les brouillons, recherche (nom, lieu, client) et filtre par statut. |
| `/equipe/evenements/:id` | Fiche projet (statut, dates, lieu, type, thème, participants, client), tâches et notes de l’équipe. |
| `/espace` (« Notes de l’équipe ») | Notes globales, sans rattachement à un événement, avec auteur et date. L’administratrice y publie, modifie et supprime les notes ; les employés les lisent. |

Les liens se trouvent dans « Mon espace » pour les employés et l’administratrice.

## Règles

- **Consultation seulement** pour les clients et les événements : le compte MySQL `ecf_team` n’a aucun droit d’écriture sur ces tables (« sans pouvoir modifier les éléments structurels »).
- **Notes** (5 000 caractères au maximum) : tout membre de l’équipe en ajoute sur n’importe quel événement. L’auteur modifie ou supprime ses propres notes ; l’administratrice peut modifier ou supprimer toutes les notes. Le sujet ne précise pas ce périmètre : ce choix évite qu’un employé réécrive le message d’un collègue tout en laissant Chloé gérer les communications.
- **Notes globales** (5 000 caractères au maximum) : communications générales de Chloé à toute l’équipe, comme le demande l’énoncé (« des notes globales au cas où des communications importantes sont à effectuer »). Seule l’administratrice les crée, les modifie et les supprime (contrôle côté serveur, 403 pour un employé) ; la suppression demande une confirmation. Le compte `ecf_team` a les droits de lecture, d’ajout (auteur et contenu), de modification du seul contenu et de suppression sur `notes_globales`.
- **Tâches** : chacun voit toutes les tâches d’un événement. Seule la personne assignée fait avancer sa tâche, une étape à la fois : « à faire » → « en cours » → « terminé ». Une tâche non assignée ou assignée à un collègue n’affiche aucun bouton (refus 403 côté serveur), y compris pour l’administratrice. Un changement concurrent est détecté (409).
- Les notes ajoutées alimentent le widget « Notes récentes » du tableau de bord administrateur.
- Démonstration : cinq tâches fictives sur deux événements à venir (migration `014_team_space.sql`, rejouable sans doublon).

## Tests

| Fichier | Contrôles | Résultat |
| --- | --- | --- |
| `tests/app/team-space.py` | Droits (client refusé, CSRF), liste et recherche des clients (injection inoffensive), fiche et historique, liste et filtre des événements, fiche projet, notes (vide, longue accentuée, auteur, collègue refusé, administratrice, tableau de bord), tâches (étapes, conflit, non assignée, autre personne), notes globales (création, lecture par l’équipe, refus pour l’employé et le client, CSRF, validation, injection inoffensive, note inconnue, suppression), absence d’écriture SQL sur clients et événements | 44/44 |
| `tests/app/team-space-browser.cjs` | José à 390 px : recherche, fiche client, fiche événement, tâche, note avec erreur, ajout et modification ; Chloé à 1280 px : filtre, note d’un collègue, suppression annulée puis confirmée ; notes globales : erreur sur note vide, publication, modification, lecture seule pour José, suppression, à 1280 et 390 px ; focus et absence de débordement | 27/27 |
| `frontend/tests/GlobalNotes.test.js` (Vitest) | Composant des notes globales : affichage selon le rôle, texte jamais interprété comme du HTML, note vide refusée sans appel à l’API, envoi avec jeton CSRF, texte conservé après un refus | 6/6 |
| Non-régression | Espace client, administration, pages publiques, authentification, comptes, décisions de devis, conversion, événements, compilation Vue | réussis |

Les essais restaurent les notes (y compris les notes globales), statuts de tâches et comptes temporaires ; la base de démonstration a été vérifiée sans reste.

## Limites

- La création et l’assignation de tâches ne sont pas proposées dans l’interface (non demandées pour l’employé ; à prévoir avec la gestion des événements par l’administratrice).

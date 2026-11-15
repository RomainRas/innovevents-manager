# Espace client : informations, suppression du compte et avis

Exigences couvertes : CLI-02 (profil modifiable, demande de suppression totale, suivi, évaluation après événement) et la modération des avis d’EMP-01.

## Parcours

| Qui | Écran | Ce qui est possible |
| --- | --- | --- |
| Client | `/espace/informations` | Modifier prénom, nom, entreprise, téléphone et adresse. L’adresse de connexion n’est pas modifiable en ligne (lien vers le contact). |
| Client | `/espace/informations` | Demander la suppression totale de ses informations et de son compte (case de confirmation obligatoire, motif facultatif), puis suivre l’état de la demande. |
| Client | `/espace`, « Évaluer mes événements terminés » | Donner un avis (10 à 2 000 caractères) sur chacun de ses événements terminés, une seule fois, puis voir s’il est en attente, publié ou non publié. |
| Employé, administratrice | `/moderation-avis` | Valider ou refuser les avis en attente ; consulter les avis publiés et refusés. |
| Visiteur | `/avis` | Voir uniquement les avis validés, sur des événements dont le client a accepté la publication. |

Un événement est « terminé » s’il a le statut `termine`, ou si sa date de fin est passée et qu’il n’est ni brouillon ni annulé.

## Règles et sécurité

- Deux comptes MySQL dédiés, créés par `scripts/provision-client-space.py` (migration `012_client_space.sql`) :
  - `ecf_client_space` : lecture de son profil et de ses événements, mise à jour des seules colonnes du profil, insertion d’un avis ou d’une demande de suppression. Il ne peut pas modifier l’adresse électronique, le rôle, le mot de passe ni le statut d’un avis.
  - `ecf_reviews` : lecture des avis et mise à jour des seules colonnes de modération (`statut`, `moderateur_id`, `modere_le`).
- Chaque requête est limitée au compte de la session. Un événement d’un autre client, inexistant ou non terminé reçoit la même réponse (404), sans rien révéler.
- Un seul avis par événement et par client (contrainte unique existante). Une seule demande de suppression en attente par compte (colonne calculée unique).
- La modération est atomique : un avis déjà modéré ne peut pas l’être une seconde fois (409), même par deux personnes en même temps.
- La modification du profil d’un client crée l’entrée de journal exigée `MODIFICATION_CLIENT` (identifiant du client uniquement).
- La demande de suppression envoie un courriel chiffré dans la file à l’adresse de l’agence (`AGENCY_EMAIL`).
- Le public ne voit que le prénom et l’initiale du nom de l’auteur.
- Accessibilité : focus placé sur le premier champ en erreur ou sur le message de résultat, erreurs reliées aux champs (`aria-describedby`), boutons de filtre avec `aria-pressed`, pages vérifiées à 390 et 1280 pixels sans défilement horizontal.

## Tests

| Fichier | Contrôles | Résultat |
| --- | --- | --- |
| `tests/app/client-space.py` | Droits d’accès, jeton CSRF, validation, journal, cloisonnement entre clients, avis unique, modération, publication, refus, demande de suppression et courriel | 32/32 |
| `tests/app/client-space-browser.cjs` | Profil et avis du client à 390 px, modération par José à 1280 px, publication sur `/avis`, demande de suppression à 390 px, focus et absence de débordement | 15/15 |
| Non-régression | `public-pages.py`, `authentication.py`, `quote-decisions.py` | réussis |

Les deux essais restaurent la démonstration (avis, demande, ville) en fin d’exécution. Le test navigateur remet à zéro le limiteur de connexion local (10 connexions par compte en 15 minutes), que des essais rapprochés épuisent ; la limite elle-même reste vérifiée par `tests/app/authentication.py`.

```bash
py -3 -X utf8 tests/app/client-space.py
```

```bash
node tests/app/client-space-browser.cjs
```

## Limites

- Le **traitement** de la demande de suppression (anonymisation du compte, confirmation par courriel au client) est fait par l’administratrice depuis la gestion des comptes : voir [administration-comptes.md](administration-comptes.md). Il n’est pas automatique.
- Le changement d’adresse électronique de connexion passe par le formulaire de contact.
- Le client ne peut pas modifier ni retirer un avis déjà envoyé.

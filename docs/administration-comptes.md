# Espace administrateur : tableau de bord et comptes

Version du 15 novembre 2026. Exigences couvertes : tableau de bord de Chloé (prochains événements, notes récentes, indicateurs clés) et « créer / suspendre / supprimer des comptes employés et clients ». Elle traite aussi les demandes de suppression envoyées par les clients (voir [espace-client-avis.md](espace-client-avis.md)).

## Tableau de bord (`/espace`, administratrice uniquement)

| Widget | Contenu |
| --- | --- |
| Prochains événements | Les trois événements non annulés dont le début est le plus proche, avec le client associé, la date (heure de Paris) et le statut ; lien vers la fiche. |
| Notes récentes | Les cinq dernières notes ajoutées sur l’ensemble des projets, avec auteur, événement et date. |
| Indicateurs clés | Clients actifs (au moins un événement « en cours »), événements en brouillon à traiter, avis à modérer, demandes de suppression en attente (avec lien vers l’action). |

## Comptes (`/comptes`, administratrice uniquement)

- **Créer un compte employé** : prénom, nom, adresse électronique, accès mobile facultatif.
- **Créer un compte client** : rattaché à une fiche client existante qui n’a pas encore de compte ; le nom et l’adresse viennent de la fiche.
- Dans les deux cas, un mot de passe par défaut aléatoire est envoyé par courriel et un nouveau mot de passe est exigé à la première connexion (mécanisme existant).
- **Suspendre / réactiver** : la connexion est refusée immédiatement, y compris pour une session déjà ouverte ; la suspension retire l’accès mobile.
- **Supprimer** (avec confirmation) :
  - le compte est anonymisé : identité, adresse électronique et mot de passe remplacés, statut suspendu, date de suppression enregistrée ;
  - pour un client : ses avis sont effacés, la fiche client est détachée et ses coordonnées de contact effacées (le nom de l’entreprise et les devis restent, sans coordonnées, pour les obligations comptables) ; entrée de journal `MODIFICATION_CLIENT` ;
  - la demande de suppression éventuelle passe à « traitée » ;
  - un courriel de confirmation est envoyé à l’ancienne adresse ;
  - l’adresse électronique est libérée et peut servir à un nouveau compte.
- Recherche par nom, adresse ou entreprise, filtre par type. Les comptes ayant demandé leur suppression apparaissent en premier, signalés en rouge.
- Aucun compte administrateur ne peut être créé, suspendu ou supprimé depuis l’application : un administrateur est traité comme inexistant (404).

Choix de l’anonymisation plutôt que l’effacement de la ligne : les devis, décisions et transmissions référencent l’auteur de l’action. Les effacer casserait l’historique commercial que la loi impose de conserver ; anonymiser supprime toute donnée personnelle tout en gardant cet historique cohérent.

## Sécurité

- Compte MySQL dédié `ecf_admin` (migration `013_admin_accounts.sql`, script `scripts/provision-admin-accounts.py`) : il ne peut **pas lire** les empreintes de mot de passe, n’écrit que les colonnes gérées et ne lit les événements et notes qu’en lecture seule.
- Jeton CSRF sur toutes les actions ; réservé au rôle administrateur (403 pour les employés et clients, 401 sans session).
- Transactions avec verrou sur le compte ciblé : une suppression ne peut pas se produire deux fois.

## Tests

| Fichier | Contrôles | Résultat du 15 novembre 2026 |
| --- | --- | --- |
| `tests/app/admin-accounts.py` | Droits, CSRF, tableau de bord exact, création (validation, doublon, courriel, changement de mot de passe), recherche et filtres, suspension (session coupée, connexion refusée, accès mobile retiré), réactivation, administrateur intouchable, compte client rattaché, suppression complète, adresse réutilisable, absence de lecture des mots de passe | 39/39 |
| `tests/app/admin-accounts-browser.cjs` | Tableau de bord à 1280 px, création avec erreur puis succès, suspension, réactivation, suppression avec confirmation et annulation à 390 px, focus et absence de débordement | 13/13, trois exécutions successives |
| Non-régression | `client-space.py`, `client-space-browser.cjs`, `public-pages.py`, `authentication.py`, `accounts.py`, `quote-decisions.py`, `conversion.py`, compilation Vue | réussis |

Les essais retirent les comptes et fiches créés ; la base de démonstration a été vérifiée sans reste après exécution.

## Limites

- La gestion complète des fiches clients (création, modification, suppression avec journal `SUPPRESSION_CLIENT`) et des événements (suppression, changement de statut) n’est pas encore développée.
- Les notes collaboratives (création, modification, suppression) relèvent de l’espace employé, à venir : le widget « Notes récentes » affiche les notes déjà présentes.
- La modification de l’identité d’un employé après création n’est pas proposée (le supprimer puis le recréer).

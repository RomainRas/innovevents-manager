# Bilan des fonctionnalités — rendu du 19 novembre 2026

État au 15 novembre 2026. « Réalisé » signifie développé, testé automatiquement et déployé en ligne. Les limites sont celles constatées, pas des intentions.

## 1. Application web

| Exigence du sujet | État | Où | Limites |
| --- | --- | --- | --- |
| Menu sur toutes les pages, connexion/déconnexion selon la session | Réalisé | En-tête du site | — |
| Accueil : présentation, images, bouton de demande de devis | Réalisé | `/` | Textes et images fictifs |
| Création de compte, règles de mot de passe, courriel de confirmation | Réalisé | `/inscription` | — |
| Connexion, mot de passe oublié, changement obligatoire | Réalisé | `/connexion`, `/mot-de-passe-oublie` | — |
| Accessibilité (RGAA : titres, focus, erreurs, alternatives) | Réalisé, vérifié par les tests de bout en bout (focus, débordement à 390 px) | Toutes les pages | Pas d’audit RGAA complet par un outil ou un expert |
| Mentions légales, CGU, CGV, confidentialité | Réalisé | Pied de page | Éditeur et hébergeur à compléter avec des données réelles si l’agence existait |
| Contact (nom d’utilisateur facultatif, courriel sinon obligatoire, titre, description) | Réalisé | `/contact` | — |
| Événements publics : accord du client, sans brouillon ni prix, filtres date/type/thème | Réalisé | `/evenements-publics` | — |
| Avis : seuls les avis validés sont publiés | Réalisé | `/avis` | Un avis d’un événement non public n’apparaît pas, même validé (choix de confidentialité) |
| Demande de devis, message de remerciement modifiable | Réalisé | `/demande-devis`, `/parametres-demandes` | — |
| Prospects : qualification, échec avec message, conversion en client | Réalisé | `/prospects` | — |
| Devis : prestations, calcul HT/TVA/TTC, PDF, transmission, compte client créé | Réalisé | Fiche événement, `/devis` | — |
| Réponse du client : accepter, refuser, demander une modification ; contre-proposition | Réalisé | « Mon espace », `/devis` | — |
| Espace client : devis, trois prochains événements, informations, demande de suppression, avis après l’événement | Réalisé | `/espace`, `/espace/informations` | Changement d’adresse de connexion par le formulaire de contact |
| Employé : clients (recherche, fiche, historique), événements, notes, tâches assignées, modération des avis | Réalisé | `/equipe/*`, `/moderation-avis` | Notes : chacun modifie les siennes, l’administratrice toutes (règle choisie, le sujet ne précise pas) |
| Administratrice : tableau de bord (3 événements, 5 notes, indicateurs) | Réalisé | `/espace` | — |
| Administratrice : gestion des clients et des événements, statuts | Réalisé | `/equipe/*` | Suppression refusée si elle effacerait un devis, un compte ou un historique (on annule l’événement) |
| Administratrice : comptes employés et clients (créer, suspendre, supprimer) | Réalisé | `/comptes` | Suppression = anonymisation (historique commercial conservé) |
| Journalisation MongoDB des actions exigées | Réalisé, livraison vérifiée par les tests | Collection `logs`, `/journaux` | Consultation limitée aux 20 dernières entrées |

## 2. Application mobile

| Exigence | État | Limites |
| --- | --- | --- |
| Tableau de bord des événements à venir | Réalisé (PWA, `/mobile/`) | — |
| Fiche événement concise | Réalisé | — |
| Fiche contact : appeler, courriel, itinéraire en un geste | Réalisé | Vérifié sur téléphone émulé, pas sur appareil physique |
| Note rapide | Réalisé | Une note saisie hors ligne n’est pas envoyée automatiquement |
| Packagée avec Docker, CGU et CGV | Réalisé (conteneur `mobile`) | PWA et non application native : [justification](conception/choix-technologiques.md) |

## 3. Conception, données et déploiement

| Exigence | État | Où |
| --- | --- | --- |
| 3 maquettes et 3 wireframes web et mobile, charte graphique, enchaînement | Réalisé | `docs/maquettes/`, `docs/conception/` |
| Diagrammes de cas d’utilisation et de séquence, architecture | Réalisé | [diagrammes.md](conception/diagrammes.md) |
| Script SQL de création écrit à la main, jeu de données, MCD | Réalisé | `database/mysql/001_schema.sql`, `002_demo.sql`, [MCD](conception/mcd-relationnel.md) |
| Base restaurable en cas d’incident | Réalisé (sauvegarde et restauration vérifiées) | [sauvegarde-restauration](demo-sauvegarde-restauration.md) |
| Tests unitaires, fonctionnels, de bout en bout, couverture, tableau par fonctionnalité | Réalisé | [plan de tests](plan-de-tests.md) |
| Déploiement en ligne documenté et reproductible | Réalisé | [deploiement.md](deploiement.md) |
| CI/CD : tests continus, déploiement automatique sur `main` | Réalisé | `.github/workflows/ci-deploy.yml` |
| Documentation utilisateur | Réalisé | [guide-utilisateur.md](guide-utilisateur.md) |
| Veille technologique et sécurité | Réalisé | [veille.md](veille.md) |
| Kanban partagé (colonnes : à faire, prêt à développer, en cours, terminé sur dev, fusionné sur main) | Réalisé et à jour | GitHub Projects « Innov’Events - Suivi ECF » (n° 3) |

## Limites générales connues

- Un seul serveur, sans redondance ; pas encore de sauvegarde automatique planifiée vers un stockage externe en production.
- Les courriels partent d’une boîte personnelle ; les comptes de démonstration utilisent des adresses `example.invalid` qui ne reçoivent rien.
- Les tests fonctionnels et de bout en bout s’exécutent en local contre l’application complète ; l’intégration continue exécute les tests unitaires, les audits et les compilations, pas encore la suite complète.

# Innov’Events Manager

Projet ECF « Concepteur Développeur d’Applications » : application de gestion de l’agence événementielle (fictive) Innov’Events — site public, espaces client, employé et administratrice, application mobile pour l’équipe.

- **Site en ligne :** https://innovevents.rascleromain.fr
- **Application mobile (PWA) :** https://innovevents.rascleromain.fr/mobile/
- **Dépôt :** branche `dev` (développement), `main` (production, déployée automatiquement)

Les identifiants des comptes de démonstration sont transmis avec le rendu ; ils ne sont pas dans le dépôt.

## Technologies

Vue 3 + Vite (site et application mobile), PHP 8.4 natif avec PDO (API), MySQL 8.4 (données métier), MongoDB 8.0 (journal des actions), Caddy (HTTPS), Docker Compose, GitHub Actions (intégration et déploiement continus), VPS Ubuntu. [Choix et justification](docs/conception/choix-technologiques.md).

## Démarrage local

```powershell
./scripts/Start-App.ps1 -Python (Get-Command py).Source
```

Puis http://127.0.0.1:5173 (site), http://127.0.0.1:5173/mobile/ (mobile), http://127.0.0.1:8025 (courriels de test). Première installation, prérequis et dépannage : [guide d’installation locale](docs/installation-locale.md).

## Tests

```powershell
py -3 -X utf8 scripts/run-tests.py --coverage
```

Tests unitaires (PHPUnit), fonctionnels (HTTP) et de bout en bout (Playwright), couverture du code : [plan de tests et résultats](docs/plan-de-tests.md).

## Documentation

**Pour le correcteur : [rendu — liens, comptes et emplacement de chaque livrable](docs/rendu.md)**

**Utilisation**
- [Guide d’utilisation par rôle](docs/guide-utilisateur.md)

**Conception**
- [Analyse des exigences](docs/conception/analyse-exigences.md), [utilisateurs et droits](docs/conception/utilisateurs-et-droits.md)
- [Diagrammes : cas d’utilisation, séquence, architecture](docs/conception/diagrammes.md)
- [MCD](docs/conception/mcd-relationnel.md), [MLD MySQL](docs/conception/mld-mysql.md), [dictionnaire des données](docs/conception/dictionnaire-donnees.md), [journaux MongoDB](docs/conception/modele-journaux-mongodb.md)
- [Maquettes de tous les écrans](docs/conception/maquettes-affiche.md) (site et application mobile, `docs/maquettes/affiche/`) ; l’identité visuelle a évolué en cours de projet, voir [pourquoi](docs/conception/charte-graphique.md#évolution-de-lidentité-visuelle)
- [Charte graphique](docs/conception/charte-graphique.md), maquettes principales et wireframes (`docs/maquettes/`, `docs/conception/maquette-*`, `wireframe-*`), [enchaînement des écrans](docs/conception/enchainement-ecrans.md)
- Scripts SQL écrits à la main : [création des tables](database/mysql/001_schema.sql), [jeu de données](database/mysql/002_demo.sql), migrations `database/mysql/003` à `015`

**Fonctionnalités (règles, tests, limites)**
- Comptes : [authentification et sessions](docs/authentification-sessions.md), [création de compte et mot de passe](docs/parcours-comptes.md)
- Parcours commercial : [demande de devis](docs/implementation-demande-devis.md), [prospects](docs/qualification-prospects.md), [conversion en client](docs/conversion-clients.md), [événement](docs/evenements-brouillons.md), [images](docs/images-evenements.md), [prestations](docs/prestations-devis.md), [PDF](docs/devis-pdf.md), [transmission](docs/transmission-devis.md), [réponse du client](docs/decisions-devis.md)
- [Pages publiques](docs/pages-publiques.md), [espace client et avis](docs/espace-client-avis.md), [espace employé](docs/espace-employe.md), [tableau de bord et comptes](docs/administration-comptes.md), [gestion des fiches et statuts](docs/gestion-fiches.md), [application mobile](docs/application-mobile.md)
- [Bilan des fonctionnalités et limites](docs/bilan-fonctionnalites.md)

**Déploiement et exploitation**
- [Déploiement en production (procédure reproductible)](docs/deploiement.md)
- [Sauvegarde et restauration](docs/demo-sauvegarde-restauration.md)
- [Veille technologique et sécurité](docs/veille.md)
- Preuves datées des tests : `docs/preuves/`

Les secrets (`.secrets/`, `.env`), dépendances installées, sauvegardes et fichiers temporaires sont exclus de Git.

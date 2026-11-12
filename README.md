# innovevents-manager
Projet ECF CDA - Application de gestion événementielle Innov’Events

## Démarrage local

Lire le [guide PHP/Vue](docs/environnement-application.md). Prérequis : Docker Desktop, Python 3 et [bases de démonstration initialisées](docs/demo-sauvegarde-restauration.md).

```powershell
./scripts/Start-App.ps1
```

Ouvrir http://127.0.0.1:5173/connexion. La connexion, les sessions et les premières lectures par rôle sont disponibles ; les autres fonctionnalités métier restent à développer. Voir [les comptes locaux, protections et limites](docs/authentification-sessions.md).

## Documentation

- [Énoncé et notice](docs/enonce/)
- [Choix techniques](docs/conception/choix-technologiques.md)
- [Environnement des bases](docs/environnement-local.md)
- [Tests de contraintes](docs/verification-bases.md)
- [Démonstration, sauvegarde et restauration](docs/demo-sauvegarde-restauration.md)
- [Authentification, sessions et tests](docs/authentification-sessions.md)

Les secrets, dépendances installées et archives locales sont exclus de Git. Le suivi de développement est conservé sur `dev` ; aucune publication de production n’est réalisée à ce stade.
# Parcours de compte — complément du 12 novembre

Inscription et récupération web : [guide et limites](docs/parcours-comptes.md). Courriels de test locaux dans Mailpit sur http://127.0.0.1:8025.

# Demande de devis

Formulaire local : http://127.0.0.1:5173/demande-devis. [Fonctionnement, essais et limites](docs/implementation-demande-devis.md).


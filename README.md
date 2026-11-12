# innovevents-manager
Projet ECF CDA - Application de gestion événementielle Innov’Events

## Démarrage local

Lire le [guide PHP/Vue](docs/environnement-application.md). Prérequis : Docker Desktop, Python 3 et [bases de démonstration initialisées](docs/demo-sauvegarde-restauration.md).

```powershell
./scripts/Start-App.ps1
```

Ouvrir http://127.0.0.1:5173/. L’écran provisoire vérifie la connexion Vue → PHP → MySQL/MongoDB ; les fonctionnalités métier et l’authentification restent à développer.

## Documentation

- [Énoncé et notice](docs/enonce/)
- [Choix techniques](docs/conception/choix-technologiques.md)
- [Environnement des bases](docs/environnement-local.md)
- [Tests de contraintes](docs/verification-bases.md)
- [Démonstration, sauvegarde et restauration](docs/demo-sauvegarde-restauration.md)

Les secrets, dépendances installées et archives locales sont exclus de Git. Le suivi de développement est conservé sur `dev` ; aucune publication de production n’est réalisée à ce stade.

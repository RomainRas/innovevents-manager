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

## Espace prospects

Depuis le compte administratrice : consultation, recherche et qualification négative sur `/prospects`. [Bilan et preuves](docs/qualification-prospects.md). Conversion en client disponible depuis une demande à contacter.

## Conversion en client

Depuis une fiche prospect à contacter, « Convertir en client » reprend les coordonnées et permet de créer la fiche. Journalisation différée vers MongoDB avec reprise : [bilan et preuves](docs/conversion-clients.md).

## Brouillon d’événement

Depuis la fiche client, préparer l’événement d’une demande convertie, puis compléter son nom, lieu, type, participants et horaires. Création privée et journalisée, reprise sans doublon et protection contre les modifications périmées : [utilisation, tests et limites](docs/evenements-brouillons.md).


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

L’administratrice peut ajouter ou remplacer une image JPEG/PNG dans le brouillon, avec aperçu privé et description accessible : [guide des images](docs/images-evenements.md).

## Prestations du devis

Depuis l’événement, ouvrir « Préparer les prestations et le devis » : lignes HT, taux explicite, calcul exact TVA/TTC et sauvegarde du brouillon. [Résultats et limites](docs/prestations-devis.md). La transmission reste à développer. [Priorités pour le rendu du 19 novembre](docs/plan-livraison-19-novembre.md).

Complément : génération et téléchargement administrateur du PDF désormais disponibles, avec contrôle de validité et journalisation MongoDB. [Guide PDF](docs/devis-pdf.md). La transmission et la réponse du client sont livrées (voir ci-dessous).

## Transmission du devis et espace client

Depuis le devis, « Envoyer par la plateforme » (courriel avec PDF joint) ou « Déclarer un envoi manuel ». Le devis passe en étude côté client et devient non modifiable ; le compte client est créé s’il n’existe pas, avec un mot de passe à changer à la première connexion. Le client consulte et télécharge ses devis dans « Mon espace ». [Fonctionnement, tests et limites](docs/transmission-devis.md).

## Réponse du client et contre-proposition

Le client accepte, refuse ou demande une modification motivée depuis « Mon espace ». L’acceptation fait passer l’événement à « accepté », est journalisée et notifiée à l’agence. Chloé consulte le motif, prépare une contre-proposition (nouvelle version) et suit les devis sur `/devis`. [Règles, tests et limites](docs/decisions-devis.md).

## Mise en ligne

Application en ligne : https://innovevents.rascleromain.fr (VPS Ubuntu, Docker Compose, Caddy en HTTPS, PHP-FPM). [Architecture, sécurité, procédure et vérifications](docs/deploiement.md).

Pages publiques : accueil, événements publiés avec filtres, avis validés, contact, mentions légales, CGU, CGV et confidentialité. [Sécurité, accessibilité, tests et limites](docs/pages-publiques.md). Après une mise à jour : `py -3 -X utf8 scripts/provision-public-pages.py`.

Espace client et avis : modification des informations, demande de suppression du compte, avis après un événement terminé et modération par l’équipe. [Règles, tests et limites](docs/espace-client-avis.md).

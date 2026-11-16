# Rendu ECF — Innov’Events Manager

Romain Rascle — titre professionnel Concepteur Développeur d’Applications.

## Liens

| Élément | Adresse |
| --- | --- |
| Application web (production) | https://innovevents.rascleromain.fr |
| Application mobile (PWA, production) | https://innovevents.rascleromain.fr/mobile/ |
| Dépôt GitHub (branches `dev` et `main`) | https://github.com/RomainRas/innovevents-manager |
| Kanban (GitHub Projects) | https://github.com/users/RomainRas/projects/3 |
| Maquettes (site et mobile) | [maquettes-affiche.md](conception/maquettes-affiche.md), images dans `docs/maquettes/affiche/` ; l’identité visuelle a évolué en cours de projet, voir [pourquoi](conception/charte-graphique.md#évolution-de-lidentité-visuelle) |
| Wireframes Figma (exports SVG dans `docs/maquettes/wireframes/`) | https://www.figma.com/design/eAJJZyMSjoOff7mA1pNpCU |

## Comptes de démonstration

Données entièrement fictives. Le mot de passe, commun aux trois comptes, est communiqué dans le champ prévu du rendu (il n’est pas dans le dépôt).

| Rôle | Identifiant | Pour tester |
| --- | --- | --- |
| Administratrice (Chloé) | `chloe@example.invalid` | Tableau de bord, prospects, devis, gestion des clients, événements et comptes, journaux, application mobile |
| Employé (José) | `jose@example.invalid` | Clients, événements, notes, tâches assignées, modération des avis, application mobile |
| Cliente (Alice) | `alice@example.invalid` | Devis, prochains événements, informations, avis sur un événement terminé |

Les adresses `example.invalid` ne reçoivent pas de courriel : la création d’un compte avec une vraie adresse permet de vérifier les envois (confirmation, mot de passe temporaire).

Pour l’application mobile : ouvrir le lien sur un téléphone (ou avec l’émulation mobile du navigateur), se connecter avec Chloé ou José, puis l’installer sur l’écran d’accueil (menu ⋮ « Installer l’application » sur Android, Partager → « Sur l’écran d’accueil » sur iPhone).

## Où trouver chaque livrable demandé

| Exigence de l’énoncé | Document |
| --- | --- |
| Gestion de projet : kanban partagé | Lien ci-dessus ; historique des branches et commits sur GitHub |
| Maquettes et wireframes (3 web, 3 mobile), charte graphique, enchaînement | [maquettes](conception/maquettes-affiche.md), `docs/maquettes/`, [charte](conception/charte-graphique.md), [enchaînement](conception/enchainement-ecrans.md), `docs/conception/maquette-*` et `wireframe-*` |
| Diagrammes de cas d’utilisation et de séquence | [diagrammes.md](conception/diagrammes.md) |
| Architecture logicielle, rôle sécurité des couches, éco-conception | [diagrammes.md § 3](conception/diagrammes.md), [choix technologiques](conception/choix-technologiques.md) |
| Script SQL écrit à la main, jeu de données, MCD | `database/mysql/001_schema.sql`, `002_demo.sql` (migrations `003` à `015`), [MCD](conception/mcd-relationnel.md), [MLD](conception/mld-mysql.md), [journaux MongoDB](conception/modele-journaux-mongodb.md) |
| Base restaurable en cas d’incident | [sauvegarde et restauration](demo-sauvegarde-restauration.md) |
| Tests unitaires, fonctionnels, de bout en bout, couverture, tableau par fonctionnalité | [plan de tests](plan-de-tests.md) |
| Déploiement documenté et reproductible | [déploiement](deploiement.md) |
| CI/CD (tests continus, déploiement automatique sur `main`) | `.github/workflows/ci-deploy.yml`, [déploiement](deploiement.md) |
| Documentation utilisateur | [guide d’utilisation](guide-utilisateur.md) |
| Veille technologique et sécurité | [veille](veille.md) |
| CGU et CGV | Pied de page du site et écran de connexion de l’application mobile |
| Bilan des fonctionnalités et limites | [bilan](bilan-fonctionnalites.md) |

## Choix à signaler

- **Application mobile en PWA** (application web installable, conteneur Docker dédié) plutôt qu’en application native : l’énoncé impose seulement qu’elle soit « packagée avec docker », et ses fonctions (appel, courriel, itinéraire, note rapide) s’appuient sur les applications du téléphone. Justification : [choix technologiques](conception/choix-technologiques.md), [application mobile](application-mobile.md).
- **Sécurité** : un compte MySQL limité par fonctionnalité, jeton CSRF et contrôle d’origine, CSP stricte, mots de passe bcrypt, courriels en attente chiffrés, journal écrit dans la même transaction que l’action puis livré dans MongoDB.
- **Suppressions** : un compte supprimé est anonymisé et un événement avec devis s’annule au lieu d’être effacé, pour conserver l’historique commercial sans donnée personnelle.

## Résultats de tests

30 suites sur 30 réussies (48 tests unitaires, 20 suites fonctionnelles, tests de bout en bout dont application mobile sur téléphone émulé). Couverture du code PHP : 98,3 % des lignes, 92,1 % des méthodes. Détail et interprétation : [plan de tests](plan-de-tests.md).

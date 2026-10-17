# Analyse des exigences Innov’Events Manager

Document de préparation établi à partir de l’énoncé ECF fourni (20 pages). Les éléments ci-dessous sont attendus ; leur présence dans cette liste ne signifie pas qu’ils sont réalisés.

## Contexte et objectif

Innov’Events est une agence événementielle de huit personnes. Ses informations sont réparties entre un fichier Excel partagé, des documents Word et un calendrier. Cette organisation entraîne des risques de perte de modifications, de ressaisie et d’utilisation de coordonnées obsolètes.

L’application doit centraliser les informations des prospects, clients, événements et devis, faciliter la collaboration et automatiser la production des devis PDF. Une application mobile doit permettre la consultation des informations et l’ajout de notes en déplacement.

## Matrice des exigences

Tous les éléments ont initialement le statut « À réaliser ». L’ordre du tableau suit l’énoncé et ne constitue pas un classement par priorité.

| ID | Exigence | Pages | Preuve ou contrôle à prévoir |
|---|---|---|---|
| ENV-01 | Git avec main et dev, commits fréquents et explicites, intégration après tests | 3 | Historique Git et documentation du fonctionnement |
| ENV-02 | Docker et README racine permettant l’exécution locale précise | 3 | Configuration, procédure et essai depuis un environnement vierge |
| WEB-01 | Menu commun, accueil de présentation, images et accès à la demande de devis | 4 | Écrans et vérification des liens |
| AUTH-01 | Inscription, politique de mot de passe et courriel de confirmation | 5 | Tests de validation et preuve de courriel |
| AUTH-02 | Connexion et retour à l’action nécessitant l’authentification | 5–6 | Test du parcours avec accès protégé |
| AUTH-03 | Mot de passe oublié avec mot de passe généré envoyé par courriel et changement obligatoire | 6 | Test du parcours ; conception de sécurité à préciser |
| UI-01 | Navigation clavier, sémantique et alternatives des images informatives | 6 | Contrôles d’accessibilité documentés |
| WEB-02 | Mentions légales et formulaire de contact avec envoi au courriel de la société | 6–7 | Pages, validations et test d’envoi |
| EVT-01 | Publication avec accord du client, hors brouillon, sans prix ; filtres dates, type et thème | 7 | Tests de visibilité, de filtrage et d’absence du prix |
| PRO-01 | Formulaire de devis entièrement obligatoire, table prospects, statut à contacter, notification et remerciement modifiable | 8 | Tests interface, base, notification et paramétrage du message |
| PRO-02 | Qualification, échec avec notification ou conversion en client avec reprise des informations | 9 | Tests des deux issues et de la conversion |
| DEV-01 | Événement associé, image, prestations HT, calcul TVA et TTC, génération et envoi du devis PDF | 9–10 | Tests de calcul, PDF produit et preuve d’envoi |
| CLI-01 | Décision sur devis, demande motivée de modification et contre-proposition | 10 | Tests des statuts et des notifications prévues |
| CLI-02 | Profil modifiable, demande de suppression complète, suivi, évaluation après événement et trois prochains événements | 10 | Tests métier et contrôle des accès entre clients |
| EMP-01 | Consultation clients et événements, notes, statuts des tâches assignées et modération des avis | 11 | Matrice de droits et tests d’autorisation |
| ADM-01 | Tableaux de bord, gestion clients/événements/devis/comptes, notes globales et accès aux logs | 11–12 | Tests des fonctions et des restrictions ; compte Chloé initialisé |
| LOG-01 | Journaliser les actions sensibles avec horodatage, type, utilisateur et détails | 12–13 | Journaux des connexions, opérations clients, création/statut événement et génération PDF |
| MOB-01 | Liste des événements à venir, fiches événement/client et actions téléphone, courriel, itinéraire | 14 | Démonstration mobile et tests des liens |
| MOB-02 | Ajout rapide de notes, packaging Docker et pages CGU/CGV | 14 | Test de note, documentation de packaging et pages |
| PROJ-01 | Kanban partagé, tâches priorisées, planifiées et suivies | 15 | Lien du tableau, priorités, estimations et suivi des écarts |
| CON-01 | Trois wireframes et trois mockups pour chacun des supports web et mobile | 16 | Douze représentations et schéma d’enchaînement |
| CON-02 | Charte avec palette et police, cas d’utilisation et séquence d’une fonctionnalité | 16 | Charte et diagrammes |
| ARC-01 | Architecture complète en couches, stratégie de sécurité et besoins d’écoconception | 16–17 | Schémas et justification des responsabilités |
| DATA-01 | MCD, script SQL de création écrit à la main, script de données d’essai et restauration | 17 | Modèle, scripts exécutables et contrôle de restauration |
| TEST-01 | Environnement complet pour au moins une application ; une fonctionnalité testée intégralement en unitaire, fonctionnel et bout en bout | 18 | Plan, tests exécutés, attendus, résultats et anomalies |
| TEST-02 | Couverture du code et tableau des tests par fonctionnalité ; sécurité | 5, 18 | Rapport de couverture et tableau de tests |
| DEP-01 | Applications déployées en ligne, procédure reproductible, scripts et guide utilisateur | 18–19 | Liens, procédures et vérification du déploiement |
| CI-01 | Tests avant intégration, qualité du code et déploiement automatique sur push/fusion de production | 19 | Configuration du pipeline et rapports d’exécution |
| VEILLE-01 | Suivi des évolutions technologiques et de sécurité liées au déploiement et au DevOps | 19 | Méthode de veille et exemples documentés |
| STACK-01 | Base relationnelle, base non relationnelle et Docker obligatoires | 20 | Architecture et services effectivement utilisés |

## Points à préciser

- Date limite, taille maximale et formats acceptés par la plateforme.
- Accès du correcteur au dépôt et au tableau privés.
- Technologie mobile et sens du packaging Docker attendu pour cette application.
- Taux de TVA et règles d’arrondi.
- Transitions distinctes des statuts prospect, devis et événement.
- Portée des droits de modification et suppression des notes collaboratives.
- Attribution du compte mobile de José et droits associés.
- Informations réelles de l’entreprise et de l’hébergeur nécessaires aux pages légales.
- Modalités de conservation des journaux et de traitement des demandes de suppression à concevoir.

## État des éléments déjà préparés

- Dépôt : https://github.com/RomainRas/innovevents-manager
- Branche dev créée à partir de main.
- Tableau : https://github.com/users/RomainRas/projects/3/views/1
- Cinq colonnes et sept premières tâches visibles sur la capture fournie.
- Analyse des exigences en cours. Le tableau doit encore être détaillé pour couvrir le développement, les tests, la documentation et le déploiement.

## Prochaine étape

Définir une matrice de droits pour visiteur, client, employé et administrateur avant de dessiner les écrans ou de choisir l’architecture détaillée.

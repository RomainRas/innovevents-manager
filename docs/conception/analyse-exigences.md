# Analyse des exigences Innov’Events Manager

Document de préparation établi à partir de l’énoncé ECF fourni (20 pages), de la notice des évaluations (3 pages) et de la copie à rendre. Relecture de contrôle des deux PDF effectuée. Les éléments ci-dessous sont attendus ; leur présence dans cette liste ne signifie pas qu’ils sont réalisés. La matrice donne une vue synthétique ; les précisions qui suivent en font partie.

## Contexte et objectif

Innov’Events est une agence événementielle de huit personnes. Ses informations sont réparties entre un fichier Excel partagé, des documents Word et un calendrier. Cette organisation entraîne des risques de perte de modifications, de ressaisie et d’utilisation de coordonnées obsolètes.

L’application doit centraliser les informations des prospects, clients, événements et devis, faciliter la collaboration et automatiser la production des devis PDF. Une application mobile doit permettre la consultation des informations et l’ajout de notes en déplacement.

## Matrice des exigences

La matrice décrit le périmètre attendu, pas l’avancement de chaque réalisation. Le suivi d’avancement est tenu dans les tâches GitHub. L’ordre du tableau suit l’énoncé et ne constitue pas un classement par priorité.

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

## Détails obligatoires vérifiés dans l’énoncé

### Interfaces et comptes pages 4 à 7

- Menu sur toutes les pages : accueil, connexion ou déconnexion selon la session, événements, avis, contact, demande de devis. Accueil : présentation, images et bouton visible de demande de devis.
- Inscription : adresse électronique comme login, prénom, nom et nom d’utilisateur ; mot de passe de huit caractères minimum comprenant au moins une majuscule, une minuscule, un caractère spécial et un chiffre. Un courriel de confirmation est envoyé. Le sujet ne décrit pas de lien d’activation obligatoire.
- Connexion avec login et mot de passe ; retour à l’action initialement demandée si celle-ci nécessitait une authentification.
- Mot de passe oublié : génération automatique et envoi par courriel, puis modification obligatoire à la prochaine connexion. Le mécanisme est prescrit ; sa sécurisation reste à concevoir.
- Interfaces adaptées au support, conformes aux maquettes et à la charte ; navigation clavier, structure sémantique, alternatives aux images informatives.
- Mentions légales accessibles dans le pied de page : éditeur (raison sociale, forme juridique, siège et capital), contact (courriel et téléphone), responsable de publication et hébergeur (nom, raison sociale, adresse et téléphone).
- Contact : nom d’utilisateur facultatif, titre, adresse électronique obligatoire si le nom d’utilisateur n’est pas fourni, description ; envoi à une adresse générique de la société. La condition sur le courriel est explicite dans le sujet.

### Événements publics et demande de devis pages 7 et 8

- Événements commandés publiés seulement avec accord du client et hors statut brouillon ; image et informations de l’événement, sans affichage du prix.
- Tri/filtrage par plage de dates, type et thème. Le détail comporte une section distincte « Prestations et Devis » qui renvoie à la demande de devis.
- Champs obligatoires de demande : entreprise, nom et prénom, courriel, téléphone, lieu, type d’événement, date souhaitée, nombre estimé de participants et description du besoin.
- Enregistrement dans une table nommée `prospects`, statut initial « à contacter », courriel à l’agence et message de remerciement modifiable. L’adresse contact@innovevents.com est un exemple, pas une adresse opérationnelle imposée.

### Qualification et devis pages 9 et 10

- Qualification par Chloé : si le projet n’est pas possible, demande au statut « échoué » et message envoyé par courriel ; sinon poursuite du parcours commercial.
- Compte administrateur Chloé créé par défaut ; création d’un compte administrateur depuis l’application non permise.
- Conversion du prospect en client avec reprise automatique des informations dans le formulaire.
- Événement associé avec données préremplies et modifiables : nom, début et fin (dates et heures), lieu, statut et visibilité publique ; ajout d’une image.
- Chaque prestation comporte un libellé et un montant HT. TVA et TTC calculés automatiquement. Aucun taux chiffré, devise ou règle d’arrondi n’est fourni dans les deux PDF.
- Génération du PDF, téléchargement pour envoi manuel ou bouton d’envoi par la plateforme au courriel du client ; statut « étude côté client ».
- Le devis apparaît dans l’espace utilisateur. Si le client n’a pas de compte, création automatique avec mot de passe par défaut envoyé par courriel, modifiable à la première connexion. Contrairement au cas du mot de passe oublié, ce passage ne dit pas explicitement que le changement est obligatoire.
- Acceptation : statut « accepté » et courriel à Innov’Events. Modification : motif exigé, statut « modification », contre-proposition par Chloé. Refus : statut « refusé ».
- Espace client : devis affectés, modification des informations personnelles, demande de suppression totale des informations et du compte, suivi de la demande, avis après événement terminé et trois événements à venir les plus proches.

### Employés et administration pages 11 et 12

- Employé : tous les clients consultables avec recherche, fiche et historique événementiel ; tous les événements consultables ; notes sur toute fiche événement (création, modification et suppression).
- L’employé consulte les tâches d’un événement et modifie seulement le statut des tâches qui lui sont assignées : « à faire », « en cours », « terminé ».
- Il valide ou refuse les avis ; seuls les avis validés sont publiés sur la page avis.
- Chloé hérite des fonctions employé. Elle peut créer, modifier et supprimer les clients et événements, gérer les devis et les notes globales, créer/suspendre/supprimer les comptes employés et clients, et accéder aux journaux.
- Tableau de bord administrateur : trois prochains événements avec nom et client ; cinq dernières notes tous projets confondus ; indicateurs tels que nombre de clients actifs (avec projet en cours) et nombre d’événements brouillons.
- Statuts d’événement cités : « accepté », « en cours », « terminé », « annuler » (orthographe du sujet), ainsi que « brouillon » ailleurs dans le sujet. Les devis acceptés doivent être repérables ; après acceptation, Chloé peut passer la demande en cours, contacter les prestataires et ajouter des notes.

### Journalisation pages 12 et 13

- Collection `logs` : horodatage au format ISODate, `type_action` standardisé, `id_utilisateur` et objet JSON `details`.
- Connexion réussie : IP. Échec de connexion : courriel utilisé et IP.
- Création client : identifiant et nom ; modification client : identifiant ; suppression client : identifiant et nom.
- Création événement : identifiant et nom ; changement de statut : identifiant, ancien et nouveau statut.
- Génération du PDF de devis : identifiant de l’événement concerné.
- Le sujet impose la conformité RGPD et attire l’attention sur les IP comme données personnelles. Il recommande NoSQL pour les logs ; l’obligation globale d’une base NoSQL est, elle, explicite page 20.

### Mobile page 14

- Usage annoncé pour Chloé et José : événements à venir, détail (nom, dates, lieu, client), accès à la fiche client, actions en un clic pour téléphone, messagerie et itinéraire.
- Chaque fiche événement comporte un bouton visible d’ajout de note et une interface simple de saisie/enregistrement.
- Docker, page CGU et page CGV sont explicitement demandés dans cette section. Aucun framework mobile particulier n’est imposé.

### Projet conception données tests et déploiement pages 15 à 20

- Kanban partagé : fonctionnalités priorisées, prévues, en cours et terminées sur dev ; colonne fusionnée dans main facultative. Planification selon le délai, suivi des écarts et procédures qualité à documenter.
- Trois wireframes et trois mockups par support web/mobile, soit douze représentations ; charte incluant maquettes, palette et police ; schéma d’enchaînement ; diagrammes de cas d’utilisation et de séquence (fonctionnalité au choix).
- Architecture complète sous forme de schémas, couches et sécurité expliquées, besoins d’écoconception identifiés.
- MCD, création SQL écrite à la main (pas de génération par un outil), insertions d’un jeu d’essai complet et base de test restaurable ; nommage, intégrité, confidentialité et documentation.
- POO, composants serveurs sécurisés, code documenté, nommage cohérent, traitement des exceptions, validation de toutes les entrées côté serveur, tests unitaires et de sécurité associés aux composants concernés : critères pages 4 et 5 à conserver au-delà du seul parcours testé de bout en bout.
- Au moins une application avec environnement de test complet ; au moins une fonctionnalité vérifiée intégralement en unitaire, fonctionnel et bout en bout ; plan couvrant les fonctionnalités retenues, tableau de tests par fonctionnalité et résultats comparés aux attendus. Couverture du code à fournir ; aucun pourcentage minimal n’est indiqué.
- Applications déployées obligatoirement en ligne ; documentation détaillée et reproductible, scripts documentés, guide utilisateur ; procédures d’exécution des tests d’intégration, système et d’acceptation client.
- CI/CD : tests avant intégration sur main ou dev ; chaque push/fusion sur la branche de production déclenche un déploiement automatique sans manipulation humaine. Qualité du code, rapports CI interprétés, résolution des dysfonctionnements et veille technique/sécurité documentés.
- SQL, NoSQL et Docker obligatoires. HTML/CSS/JS, PHP/PDO, MySQL/MariaDB/PostgreSQL, MongoDB et fly.io ne sont que des exemples de technologies possibles.

## Consignes de remise vérifiées

- Énoncé page 1 : 80 h ; aucune réponse sur le sujet ; copie numérique Word/Excel fournie à utiliser ; nom `ECF_TPConcepteurDeveloppeurApplications_copiearendre_NOM_Prenom` ; réponses et calculs justifiés ; nombres alignés avec séparation des milliers par espace ; pas de copie manuscrite.
- Les consignes de déroulement de l’épreuve figurent également page 1 : téléphone éteint, échanges entre apprenants interdits et navigation limitée aux sites métier.
- Notice page 1 : utiliser le modèle lorsqu’il est fourni, vérifier format et taille maximale, lisibilité et ordre des pages. Si plusieurs fichiers doivent être rendus dans un seul espace, la notice demande une fusion en PDF. L’application de cette consigne générale au présent rendu Word/Excel est à vérifier sur la plateforme.
- Notice pages 2 et 3 : attendre la fin du chargement, retélécharger pour contrôler fichier/version/format, puis soumettre l’évaluation ; le dépôt seul ne suffit pas. Conserver une copie locale. La notice précise que la copie d’un examen final ne peut plus être modifiée après soumission ; elle distingue ce cas de l’évaluation d’entraînement.
- Copie à rendre fournie : liens du Kanban, Git et applications en ligne, login/mot de passe administrateur ; justification de la planification et de la méthode ; technologies, sécurité, tests et déploiement ; informations complémentaires. Ces rubriques doivent être renseignées à partir du travail réellement réalisé.

## Informations absentes et décisions restant à prendre

### Informations réellement absentes des deux PDF

- Échéance calendaire et taille maximale du dépôt : non indiquées. Word/Excel est bien indiqué pour la copie ; seuls les paramètres exacts de la plateforme et la remise des annexes restent à vérifier.
- Taux de TVA, devise et règles d’arrondi : non indiqués. Le calcul automatique TVA/TTC est bien imposé page 9. Aucun taux ne doit être présenté comme une consigne fournie ; une hypothèse de projet sera à expliciter et tester.
- Coordonnées juridiques complètes de l’entreprise : catégories imposées, valeurs non fournies. Les coordonnées de l’hébergeur dépendront du choix effectué.

### Choix de conception à documenter sans les présenter comme des oublis du sujet

- Framework mobile et architecture de packaging Docker : technologies libres, obligation Docker connue ; faire préciser l’interprétation attendue si nécessaire avant de figer la solution mobile.
- Transitions complètes entre statuts : plusieurs valeurs et déclencheurs sont déjà imposés ci-dessus ; il reste à formaliser leur articulation sans les remplacer arbitrairement.
- Notes : création/modification/suppression par les employés déjà explicites ; seul le périmètre auteur/autres employés n’est pas défini précisément.
- Accès mobile de José : son usage est explicite ; son rôle technique et son compte initial restent à définir.
- Conservation des journaux, traitement d’une suppression de compte et sécurisation des courriels/mots de passe : mécanismes à concevoir dans le respect des exigences, pas fonctionnalités à inventer comme déjà réalisées.
- Accès du correcteur au dépôt/tableau privés et modalités de transmission des identifiants de démonstration : organisation pratique de la remise à vérifier.

## Bilan de la relecture

Les vingt pages de l’énoncé et les trois pages de la notice ont été relues. La page 9 a également été vérifiée visuellement pour le passage TVA/TTC. La première matrice couvrait les grands ensembles mais omettait des précisions utiles : champs, statuts, compte client automatique, widgets, détails des logs et consignes de remise. Ces éléments ont été ajoutés. La liste des questions a été corrigée pour séparer informations absentes, règles déjà données et décisions de conception. Aucune application ni aucun test n’est déclaré réalisé par cette relecture.

## État des éléments déjà préparés

- Dépôt : https://github.com/RomainRas/innovevents-manager
- Branche dev créée à partir de main.
- Tableau : https://github.com/users/RomainRas/projects/3/views/1
- Cinq colonnes et sept premières tâches visibles sur la capture fournie.
- Analyse des exigences rédigée et contrôlée contre les deux PDF sources. Les questions ouvertes sont consignées ci-dessus pour les étapes concernées. Le tableau doit encore être détaillé pour couvrir le développement, les tests, la documentation et le déploiement.

## Prochaine étape

Définir une matrice de droits pour visiteur, client, employé et administrateur avant de dessiner les écrans ou de choisir l’architecture détaillée.

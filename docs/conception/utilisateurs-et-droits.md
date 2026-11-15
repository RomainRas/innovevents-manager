# Utilisateurs et droits Innov’Events Manager

Cette matrice définit qui peut consulter ou modifier les informations de l’application. Elle sert de référence pour les écrans, les contrôles côté serveur et les futurs tests d’autorisation.

Source : [énoncé ECF](../enonce/enonce-ecf-cda.pdf), principalement pages 5 à 14. Les exigences explicites sont référencées par page ; les décisions prises pour les points non détaillés sont identifiées dans une section distincte. Ce document décrit la conception, pas des fonctions déjà développées.

## Acteurs et comptes

| Acteur | Situation et besoin | Référence |
|---|---|---|
| Visiteur | Personne non connectée : découvre l’agence, consulte les événements publics et avis validés, contacte l’agence, demande un devis ou crée un compte | p. 4–8 |
| Client ou utilisateur inscrit | Personne connectée : accède aux devis qui lui sont affectés et au suivi de ses projets, gère ses informations et évalue un événement terminé | p. 5, 9–10 |
| Employé | Membre de l’équipe : consulte les clients et projets, contribue aux notes, met à jour ses tâches assignées et modère les avis | p. 11 |
| Administrateur | Chloé : dispose des fonctions employé et gère les prospects, clients, événements, devis, comptes et journaux | p. 9, 11–12 |

Le visiteur n’est pas un rôle enregistré en base : il correspond à une absence de session. Les rôles de compte retenus sont client, employé et administrateur. Le rôle client donne accès à l’espace utilisateur même avant qu’un premier devis soit affecté ; cet espace peut alors être vide. Ce rattachement de l’utilisateur inscrit au rôle client est un choix de modélisation.

Un prospect est un contact commercial enregistré après une demande de devis, pas un rôle de connexion. Il peut ne pas avoir de compte. La fiche client, le prospect et le compte utilisateur doivent être distingués : créer un compte n’accorde pas l’accès aux dossiers d’une autre personne.

Chloé dispose du compte administrateur initial exigé. José est explicitement utilisateur du mobile, mais son rôle technique n’est pas précisé par l’énoncé ; la décision correspondante figure plus bas.

## Matrice des fonctions web

« Personnel » limite l’action aux données rattachées au compte connecté. « Non » indique que l’application ne doit pas accorder cette permission dans le modèle retenu. Lorsque le sujet n’attribue pas explicitement une action, le choix de conception est de ne pas l’accorder automatiquement.

| Action | Visiteur | Client | Employé | Administrateur | Source |
|---|---|---|---|---|---|
| Consulter accueil, mentions légales, événements publics et avis validés | Oui | Oui | Oui | Oui | p. 4, 6–7, 11 |
| Envoyer une demande de contact ou de devis | Oui | Oui | Oui, formulaire public | Oui, formulaire public | p. 7–8 ; accès public conservé pour les comptes connectés |
| S’inscrire publiquement | Oui, compte client uniquement | Sans objet | Sans objet | Sans objet | p. 5, 9 ; attribution du rôle client choisie |
| Consulter les devis affectés à son compte | Non | Personnel | Non | Via la gestion commerciale | p. 10, 12 |
| Accepter, refuser ou demander la modification de son devis | Non | Personnel | Non | Non au nom du client | p. 10 ; choix de séparation des décisions client/agence |
| Suivre ses demandes et ses trois prochains événements | Non | Personnel | Via consultation des projets | Via consultation et tableau de bord | p. 10–11 |
| Modifier ses informations personnelles et demander leur suppression avec le compte | Non | Personnel | Non prévu dans ce parcours client | Non prévu dans ce parcours client | p. 10 ; comptes internes gérés par l’administrateur |
| Évaluer un événement terminé | Non | Événement personnel terminé | Non au nom du client | Non au nom du client | p. 10 |
| Consulter/rechercher tous les clients et leur historique d’événements | Non | Non | Oui | Oui | p. 11 |
| Consulter tous les événements internes et leurs fiches | Non | Non | Oui | Oui | p. 11 |
| Ajouter une note sur une fiche événement | Non | Non | Oui, tout événement | Oui, tout événement | p. 11 |
| Modifier/supprimer une note événementielle | Non | Non | Ses propres notes | Toutes les notes | p. 11 ; périmètre auteur choisi ci-dessous |
| Consulter les tâches d’un événement | Non | Non | Oui | Oui | p. 11 |
| Changer le statut d’une tâche assignée | Non | Non | Uniquement ses tâches | Ses tâches par héritage ; toutes par décision de gestion | p. 11 ; extension administrateur explicitée ci-dessous |
| Créer et assigner les tâches | Non | Non | Non | Oui, décision de conception | Le sujet décrit les tâches assignées sans désigner leur créateur |
| Valider/refuser les avis | Non | Non | Oui | Oui, par héritage | p. 11 |
| Qualifier les prospects, déclarer un échec ou convertir en client | Non | Non | Non | Oui | p. 9 |
| Créer/modifier/supprimer les fiches clients | Non | Non | Non | Oui | p. 12 |
| Créer/modifier/supprimer les événements, modifier leur statut et visibilité | Non | Non | Non | Oui | p. 9, 12 |
| Préparer/modifier un devis et ses prestations, générer/télécharger/envoyer le PDF | Non | Non | Non | Oui | p. 9, 12 |
| Faire une contre-proposition après demande de modification du client | Non | Non | Non | Oui | p. 10, 12 |
| Créer/modifier/supprimer les notes globales | Non | Non | Non | Oui | p. 11 |
| Lire les notes globales destinées à l’équipe | Non | Non | Oui, décision de conception | Oui | p. 11 ; destinataires non détaillés |
| Consulter le tableau de bord administrateur | Non | Non | Non | Oui | p. 11 |
| Créer/suspendre/supprimer des comptes clients et employés | Non | Non | Non | Oui | p. 12 |
| Créer un compte administrateur depuis l’application | Non | Non | Non | Non | Interdiction explicite p. 9 |
| Consulter les journaux d’actions | Non | Non | Non | Oui | p. 12 |
| Modifier/supprimer les journaux depuis l’interface | Non | Non | Non | Non, décision de conception | Seul l’accès en consultation est retenu |
| Modifier le texte de remerciement après demande de devis | Non | Non | Non | Oui, décision de conception | Texte modifiable exigé p. 8, acteur non précisé |

L’administrateur hérite du rôle employé, pas des droits de décision du client sur ses devis. L’employé peut consulter les fiches clients et événements ; le sujet ne lui donne pas un accès général aux devis commerciaux ni aux décisions du client.

## Mobile et acteurs externes

L’application mobile est destinée à Chloé et José (p. 14). Ils doivent voir les événements à venir, ouvrir une fiche événement puis la fiche contact du client, lancer un appel, un courriel ou un itinéraire, et ajouter une note.

Décision retenue : utiliser les mêmes comptes que sur le web, avec une autorisation mobile réservée aux comptes internes désignés. Chloé conserve son rôle administrateur. José reçoit un rôle employé avec accès mobile ; le sujet ne justifie pas de lui attribuer tous les droits administrateur. Les autres employés n’obtiennent pas cet accès mobile par défaut. Cela évite de créer un rôle métier supplémentaire uniquement pour le support utilisé.

Les comptes clients et les visiteurs n’accèdent pas aux données de l’application mobile interne. L’accès est contrôlé par le serveur, y compris pour les appels directs à l’API.

Le service d’envoi de courriels, l’application téléphone, la messagerie et la cartographie sont des acteurs techniques externes. Ils servent les actions du système ou de l’utilisateur ; ils ne reçoivent pas de compte administrateur. La qualification d’un prospect reste une action de Chloé, même si le système envoie ensuite automatiquement une notification.

## Décisions de conception pour les points non détaillés

Ces choix complètent le sujet. Ils sont retenus pour la première version et pourront être ajustés si une précision pédagogique les contredit.

| Décision | Choix et justification | Conséquence à vérifier |
|---|---|---|
| D01 Notes collaboratives | Employé : création sur tout événement, modification/suppression de ses propres notes. Administrateur : toutes les notes. Le sujet prévoit les trois opérations sans préciser la propriété | Empêcher un employé de modifier une note d’un collègue ; autoriser l’administrateur |
| D02 Compte mobile de José | Rôle employé avec autorisation mobile, sans privilège administrateur implicite | José consulte les contacts et ajoute des notes ; il ne gère pas les comptes ou les journaux |
| D03 Gestion des tâches | L’administrateur crée et assigne les tâches et peut corriger leurs statuts ; l’employé change seulement le statut des siennes | Refuser toute réaffectation par un employé et tout changement d’une tâche non assignée à celui-ci |
| D04 Paramétrage du message | L’administrateur modifie le remerciement de demande de devis | Les autres rôles ne modifient pas ce paramètre |
| D05 Comptes suspendus | La suspension retire l’accès authentifié, y compris mobile et sessions existantes | Un compte suspendu ne conserve pas ses droits avec une ancienne session |
| D06 Notes globales | Visibles par l’équipe interne, modifiables uniquement par l’administrateur | Aucun accès pour le client ; lecture seule pour l’employé |
| D07 Journaux | Consultables par l’administrateur, sans modification manuelle dans l’application | Aucun rôle ne peut effacer une trace par une action d’interface ; la politique de conservation reste un sujet séparé |
| D08 Affectation des dossiers | Un compte client accède uniquement aux dossiers qui lui sont explicitement rattachés ; une simple adresse saisie dans un formulaire public ne suffit pas à accorder l’accès | Le rattachement doit être contrôlé lors de la conversion/création de compte ; aucun accès à un dossier voisin par changement d’identifiant |

La suppression d’une fiche client et la suppression d’un compte sont deux opérations distinctes. La demande de suppression totale du client est prévue ; les modalités de traitement des données liées et des journaux seront définies lors de la conception des données.

L’accord de publication d’un événement doit être établi avant sa publication. Le sujet impose cet accord mais ne décrit pas de bouton client pour le donner : aucune interface de consentement spécifique n’est supposée déjà requise. L’administrateur règle la visibilité, et la condition d’accord reste contrôlée.

## Contrôles transversaux à prévoir

- Vérifier côté serveur le rôle, le statut du compte et le périmètre des données pour chaque action protégée. Masquer un bouton seul ne suffit pas.
- Refuser les permissions non attribuées. Le client ne peut pas transformer son compte en compte employé ou administrateur en modifiant les données envoyées.
- Vérifier l’appartenance du devis, de l’événement ou de la tâche ciblée, y compris quand l’identifiant est fourni directement dans une requête.
- Maintenir les conditions métier : avis après événement terminé, événement public uniquement avec accord et hors brouillon, affichage public sans prix.
- Prévoir le retour à l’action demandée après connexion (p. 5), la modification obligatoire après mot de passe oublié (p. 6) et les journaux requis (p. 12–13).

## Scénarios de vérification à implémenter

Ces scénarios sont préparés pour les étapes de développement et de tests ; ils n’ont pas encore été exécutés.

| ID | Situation | Résultat attendu |
|---|---|---|
| DROIT-01 | Visiteur ouvrant une page privée | Authentification demandée, aucune donnée privée exposée |
| DROIT-02 | Client A demandant le devis ou le suivi de projet du client B | Accès refusé, y compris avec identifiant modifié manuellement |
| DROIT-03 | Client demandant une action de gestion de comptes ou de devis administrateur | Accès refusé |
| DROIT-04 | Employé consultant un client et son historique | Consultation autorisée ; modification/suppression de la fiche refusée |
| DROIT-05 | Employé changeant sa tâche puis celle d’un collègue | Première action autorisée, seconde refusée |
| DROIT-06 | Employé modifiant sa note puis celle d’un collègue | Première action autorisée, seconde refusée selon D01 |
| DROIT-07 | Employé validant un avis | Validation autorisée ; avis visible publiquement après validation |
| DROIT-08 | Client donnant un avis avant la fin de son événement | Action refusée ; autorisée après fin, sous réserve d’appartenance |
| DROIT-09 | Administrateur gérant un événement ou un compte employé | Action autorisée et journalisée lorsqu’elle appartient à la liste du sujet |
| DROIT-10 | Tentative de création d’un administrateur par l’application ou l’inscription | Action refusée ; seul le compte initial provient de l’initialisation prévue |
| DROIT-11 | Compte suspendu réutilisant une session web/mobile | Accès authentifié refusé selon D05 |
| DROIT-12 | José sur mobile, puis client sur la même API | Consultation et note autorisées à José ; données internes refusées au client |
| DROIT-13 | Affichage d’un événement sans accord ou brouillon | Événement absent du site public, même par accès direct |
| DROIT-14 | Employé ouvrant les journaux ; administrateur les consultant | Refus pour l’employé, lecture autorisée à l’administrateur |

## Conservation et utilisation pour le rendu

Fichier de référence : `docs/conception/utilisateurs-et-droits.md`, suivi sur `dev` dans Git. Il alimente le dossier de conception, le futur diagramme de cas d’utilisation et la justification des mesures de sécurité de la copie à rendre. Aucun format de matrice particulier n’est imposé par l’énoncé.

Travail réalisé : identification des acteurs, séparation des profils métier et des comptes, matrice des permissions, décisions explicites sur les points non détaillés et préparation de scénarios de vérification. La tâche de conception est terminée lorsque ce document est relu contre l’énoncé et enregistré sur `dev` ; cela ne signifie pas que les autorisations sont déjà implémentées.

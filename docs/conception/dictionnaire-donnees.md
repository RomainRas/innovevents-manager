# Dictionnaire des données — première version

Travail de conception pour la tâche #6, sans création ni installation de base. Ce document prépare le MCD ; les noms de tables, cardinalités et règles supplémentaires sont des choix de projet, sauf mention explicite du sujet.

## Sources et périmètre

- [Énoncé](../enonce/enonce-ecf-cda.pdf) : comptes p. 5–6, publication p. 7, prospects p. 8, devis p. 9–10, suivi p. 11–12, journaux p. 12–13, mobile p. 14, diagrammes p. 16, données p. 17, SQL/NoSQL p. 20.
- [Analyse des exigences](analyse-exigences.md), [droits](utilisateurs-et-droits.md), [parcours commercial](parcours-prospect-devis-evenement.md) et [technologies](choix-technologiques.md).
- Passages p. 8–9, 12–13 et 16–17 relus directement dans le PDF pour cette étape. Les autres références sont rapprochées de l'analyse existante.

MySQL contient les données métier liées entre elles. MongoDB contient la collection `logs`. Le sujet impose une base relationnelle et une base non relationnelle ; leur répartition est le choix documenté du projet. Les journaux ne remplacent pas l'historique nécessaire au traitement des devis.

## Identités : trois objets différents

Un **compte** permet de se connecter. Un **prospect** représente une demande reçue, même sans compte. Une **fiche client** porte les coordonnées commerciales et les événements. Une adresse e-mail fournie dans un formulaire public ne suffit jamais à attribuer des dossiers à un compte.

Pour cette première proposition, une fiche client peut être sans compte puis rattachée à un seul compte client ; un compte client est rattaché à zéro ou une fiche. Ce choix limite volontairement la première version à un interlocuteur connecté par fiche. Le partage entre plusieurs contacts d'une entreprise n'est pas ajouté. Si ce besoin est confirmé, il faudra une association explicite de droits avant le schéma physique.

## Informations à conserver dans MySQL

Chaque objet possède un identifiant technique et des dates de création/mise à jour lorsque pertinentes. Les champs « lien » désignent une association à traduire dans le futur modèle logique. Longueurs, types SQL exacts, index et actions de suppression seront fixés lors de cette traduction.

| Objet proposé | Informations principales | Règles et justification |
| --- | --- | --- |
| `utilisateurs` | E-mail de connexion, prénom, nom, nom d'utilisateur, empreinte du mot de passe, rôle, statut du compte, changement de mot de passe requis, autorisation mobile | E-mail de connexion unique après normalisation définie ; rôles client/employé/administrateur. Suspension contrôlée à chaque accès. Chloé initialisée hors interface ; José employé autorisé sur mobile selon la matrice. Aucun mot de passe en clair dans cette table. |
| `prospects` | Entreprise, nom, prénom, e-mail, téléphone, lieu demandé, type envisagé, date souhaitée, participants estimés, besoin ; statut, message d'échec, lien client après conversion, date de qualification | Les dix informations de demande sont obligatoires p. 8. Le nom `prospects` est imposé. Statuts à contacter/échoué/converti ; « converti » est un choix existant. Conserver la demande initiale distincte des coordonnées client actualisées. |
| `clients` | Entreprise, nom/prénom du contact, e-mail, téléphone, adresse postale structurée, lien facultatif au compte | L'adresse postale sert à l'itinéraire mobile ; elle n'est pas le lieu demandé dans le formulaire. Elle peut manquer avant enrichissement : aucune adresse fictive n'est produite. Un e-mail commercial n'est pas une preuve d'appartenance du compte. |
| `evenements` | Client, demande d'origine facultative, nom, début, fin, fuseau d'affichage, lieu/adresse, type, thème, participants, statut, visibilité demandée, accord de publication, image et texte alternatif | Début/fin et autres informations modifiables p. 9 ; type/thème nécessaires aux filtres p. 7. Type, thème, fuseau et preuve de l'accord seront précisés dans le MCD/dictionnaire physique. État initial brouillon ; publication seulement avec accord et hors brouillon. |
| `devis` | Événement, référence commerciale, version courante | Un dossier de devis par événement dans cette première version ; les contre-propositions deviennent des versions. Le sujet ne prescrit pas cette cardinalité. La référence doit rester stable. |
| `versions_devis` | Devis, numéro de version, statut commercial, copie des coordonnées et informations événementielles présentées, devise, taux et règle de calcul appliqués, totaux HT/TVA/TTC, dates de préparation/transmission | Chaque version transmise est conservée sans réécrire son contenu commercial. Une modification crée une nouvelle version, pas une modification silencieuse du PDF déjà remis. Numéro unique à l'intérieur d'un devis ; une seule version courante. |
| `prestations_devis` | Version du devis, ordre, libellé, montant HT | Une ligne appartient à une seule version. Le brouillon peut ne comporter aucune ligne ; une proposition transmise doit en comporter au moins une valide. Quantités, remises et facturation ne sont pas ajoutées comme obligations. |
| `decisions_devis` | Version, auteur client, décision, motif de modification, date | Au plus une décision finale par version proposée : accepter, refuser ou demander une modification. Motif obligatoire uniquement pour modification. Auteur rattaché au client de l'événement ; ancienne version non décidable. |
| `documents_devis` | Version, référence de fichier PDF privé, empreinte du fichier, date de génération, état de génération | Permet d'identifier le document exact. Le fichier n'est pas exposé via une URL publique devinable. Une version peut avoir plusieurs tentatives ; seul un document généré avec succès est transmissible. |
| `transmissions_devis` | Version et document, mode automatique/manuel, destinataire, acteur, dates, état technique, identifiant de tentative | Sépare transmission et décision commerciale. Téléchargement seul différent d'envoi ; envoi manuel déclaré par Chloé. Un résultat incertain ne devient pas automatiquement un succès. Mécanisme de reprise à définir dans l'architecture. |
| `taches` | Événement, titre, description, personne assignée, statut | À faire/en cours/terminé. Une personne assignée au plus par tâche pour cette version ; affectation facultative avant distribution. Modification par l'employé limité à ses tâches, selon la matrice. |
| `notes_evenement` | Événement, auteur, contenu, dates | Notes accessibles aux personnes internes autorisées, jamais au client par défaut. Un employé modifie/supprime ses notes ; l'administrateur toutes, selon le choix D01. |
| `notes_globales` | Auteur administrateur, contenu, dates | Notes d'équipe sans rattachement à un événement ; accès distinct des notes événementielles. |
| `avis` | Événement, auteur client, contenu de l'évaluation, statut de modération, modérateur, date de modération | Après événement terminé et vérification d'appartenance. En attente/validé/refusé ; seul validé est public. Proposition : un avis par compte et événement. Échelle de notation non inventée à ce stade. |
| `parametres_application` | Clé, valeur, auteur/date de modification | Stocker notamment le remerciement modifiable p. 8. Clés autorisées, pas un éditeur libre de toute la configuration. Secrets de bases/SMTP exclus de cette table. |

Les objets PDF, transmission et versionnement sont des choix pour couvrir les reprises et contre-propositions déjà décrites. Leur regroupement physique éventuel sera évalué au MLD, sans perdre la traçabilité.

## Relations à formaliser dans le MCD

Les cardinalités sont présentées du point de vue de chaque objet ; elles décrivent la première proposition, pas des contraintes SQL déjà installées.

| Association | Cardinalités proposées |
| --- | --- |
| Compte client / fiche client | Un compte : 0..1 fiche ; une fiche : 0..1 compte |
| Demande prospect / client issu de qualification | Une demande : 0..1 client ; un client : 0..N demandes, dont de nouveaux besoins |
| Client / événement | Un client : 0..N événements ; un événement : exactement 1 client |
| Demande d'origine / événement | Une demande : 0..1 événement pour cette version ; un événement : 0..1 demande d'origine |
| Événement / dossier de devis | Un événement : 0..1 devis ; un devis : exactement 1 événement |
| Devis / version | Un devis : 1..N versions ; une version : exactement 1 devis |
| Version / prestation | Une version : 0..N lignes en brouillon, au moins 1 à transmettre ; une ligne : exactement 1 version |
| Version / décision | Une version : 0..1 décision ; une décision : exactement 1 version et 1 auteur |
| Version / document ou transmission | Une version : 0..N documents et tentatives ; chaque document/tentative : exactement 1 version |
| Événement / note, tâche ou avis | Un événement : 0..N de chaque ; chaque note/tâche/avis : exactement 1 événement |
| Utilisateur / contributions | Un auteur : 0..N notes ou avis ; une note/un avis : 1 auteur à la création ; suppression à traiter explicitement |

Un événement provenant d'une demande convertie doit appartenir au même client que cette demande. Une transmission ne peut référencer un PDF d'une autre version. La version courante doit appartenir au devis concerné. Ces règles devront être garanties par les contraintes appropriées et les transactions métier, puis testées.

## Journaux MongoDB

Collection `logs`, structure demandée p. 12–13 : horodatage de type date (présentation ISODate), `type_action`, `id_utilisateur`, `details`. Ajout choisi : identifiant de trace permettant de dédupliquer les reprises techniques.

| Action normalisée proposée | Détails à conserver selon le sujet |
| --- | --- |
| `CONNEXION_REUSSIE` | IP |
| `CONNEXION_ECHOUEE` | E-mail utilisé, IP |
| `CREATION_CLIENT` | Identifiant et nom du client |
| `MODIFICATION_CLIENT` | Identifiant du client |
| `SUPPRESSION_CLIENT` | Identifiant et nom du client |
| `CREATION_EVENEMENT` | Identifiant et nom de l'événement |
| `CHANGEMENT_STATUT_EVENEMENT` | Identifiant, ancien et nouveau statut |
| `GENERATION_PDF_DEVIS` | Identifiant de l'événement concerné |

Lors d'un échec de connexion sans utilisateur identifié, `id_utilisateur` reste nul : ne pas fabriquer un compte. C'est une adaptation explicite au cas décrit. Les références MySQL stockées dans MongoDB ne constituent pas des clés étrangères vérifiées automatiquement. Une suppression ne doit pas laisser croire qu'une trace décrit un utilisateur encore actif.

Aucun mot de passe, secret de session, PDF complet ou formulaire entier dans les détails. Les choix de durée de conservation, purge et traitement des demandes de suppression restent à documenter ; aucune durée prétendument légale n'est fixée ici. La coordination entre opération MySQL et journal MongoDB, notamment en cas de panne, reste à concevoir avant implémentation.

## Règles transversales et décisions restantes

1. **Conversions** : transaction évitant deux conversions d'une même demande ; rapprochement avec un client existant contrôlé par Chloé, pas par égalité d'e-mail seule.
2. **Montants** : représentation décimale exacte à retenir dans le schéma physique ; devise, taux et méthode d'arrondi à formaliser et tester. Aucun taux chiffré imposé par l'énoncé. Les totaux transmis doivent correspondre à la version figée et au PDF.
3. **Dates** : fin postérieure au début ; date souhaitée distincte des dates de l'événement ; convention de stockage des instants et fuseau à définir.
4. **États** : respecter les transitions du parcours commercial. Accepter une version doit mettre à jour de façon cohérente la décision et l'événement ; une seule réponse gagne en cas d'actions concurrentes.
5. **Suppression** : distinguer suppression d'un compte, d'une fiche client et demande de suppression totale p. 10. Définir le traitement des versions, PDF, notes, avis, demandes, traces et sauvegardes ; une simple suspension ou un indicateur « supprimé » ne suffit pas à décrire ce parcours. Aucune cascade globale n'est décidée ici.
6. **Données calculées** : les trois prochains événements, les cinq dernières notes et les clients actifs se déduisent des données ; pas de compteurs dupliqués à maintenir dans cette première version.
7. **Contact et contenu** : le sujet demande l'envoi du formulaire de contact, pas son archivage en base. Accueil et pages légales restent des contenus à préparer ; aucun CMS ajouté par défaut. Les sessions, files de courriels et mécanismes de reprise seront précisés avec l'architecture.

## Vérification, livrable et suite

Contrôle documentaire effectué contre les sources citées : dix champs de demande recensés ; compte/prospect/client séparés ; droits de consultation conservés ; versions et réponses rattachées ; publication et modération prises en compte ; huit actions de journalisation couvertes. Les cardinalités facultatives permettent de créer un compte sans dossier, une fiche sans compte, un événement sans devis et un devis encore incomplet.

Ce dictionnaire est une première base de conception. Il alimentera le MCD et les explications du dossier ; il ne remplace pas le MCD, le script SQL écrit à la main, le jeu d'essai ou la preuve de restauration exigés p. 17.

Suite dans #6 : dessiner et relire le MCD, traduire en MLD avec types/contraintes, formaliser cas d'utilisation et séquence, puis écrire et exécuter les scripts et vérifier la restauration après installation de l'environnement (#7). Aucune donnée réelle ni base exécutée pour cette étape.


# Modèle logique et préparation physique MySQL — v1

9 novembre 2026. Proposition de conception, sans script exécuté ni base installée. Ce document traduit le [MCD](mcd-relationnel.md) et le [dictionnaire](dictionnaire-donnees.md). Le sujet demande un MCD, un script SQL écrit à la main, un jeu d'essai et une restauration (énoncé p. 17) : ce document prépare ces travaux, il ne les remplace pas.

## Conventions retenues

Les quinze tables métier correspondent aux quinze entités du MCD. Une seizième table technique, `journal_a_emettre`, prépare la livraison des traces vers MongoDB ; elle ne remplace pas la collection `logs`.

- Cible : MySQL 8.4, moteur InnoDB, textes UTF-8 (`utf8mb4`). Noms en minuscules et `snake_case`.
- `ID` désigne `BIGINT UNSIGNED`. Toutes les tables métier sauf les paramètres ont `id ID PRIMARY KEY AUTO_INCREMENT` ; toutes les références utilisent exactement le même type. Ne pas exposer ces grands entiers comme nombres JavaScript : les transmettre sous forme de chaînes décimales.
- `INSTANT` désigne `DATETIME(6)` stocké en UTC. Le serveur convertit selon le fuseau de l'événement. Une date souhaitée est un `DATE`, sans heure inventée.
- `O` = obligatoire (`NOT NULL`) ; `F` = facultatif (`NULL`). Une chaîne vide n'est pas équivalente à une donnée valide : validation de contenu requise en plus de la nullabilité.
- Les valeurs d'état sont des `VARCHAR` bornés par des contraintes `CHECK`, plutôt que des valeurs libres. Les booléens sont `TINYINT` avec contrôle dans `{0,1}`.
- Les identifiants restent immuables. Les suppressions de parents sont bloquées (`RESTRICT`) tant que des enfants existent ; aucun effacement global en cascade n'est activé implicitement.
- Les longueurs et précisions ci-dessous sont des choix du projet, pas des valeurs imposées par l'énoncé. Ils devront être vérifiés lors de l'écriture et de l'exécution du SQL.

Sources techniques consultées pour cette préparation : [clés étrangères MySQL 8.4](https://dev.mysql.com/doc/refman/8.4/en/create-table-foreign-keys.html) et [types numériques exacts](https://dev.mysql.com/doc/refman/8.4/en/precision-math-numbers.html). Une clé étrangère InnoDB est contrôlée immédiatement ; les règles portant sur plusieurs lignes ne sont pas assimilées à une simple contrainte de champ.

## 1. Comptes, demandes, clients et événements

### `utilisateurs`

| Champs | Type | Présence / règle |
| --- | --- | --- |
| `email`, `email_normalise` | VARCHAR(254) | O ; unicité de la valeur normalisée |
| `prenom`, `nom` | VARCHAR(100) | O |
| `nom_utilisateur` | VARCHAR(100) | O ; unicité non imposée à ce stade |
| `mot_de_passe_hash` | VARCHAR(255) | O ; jamais le mot de passe en clair |
| `role` | VARCHAR(20) | O : client, employe, administrateur |
| `statut` | VARCHAR(20) | O : actif, suspendu |
| `changement_mdp_requis`, `acces_mobile` | TINYINT | O : 0 ou 1 |
| `cree_le`, `modifie_le` | INSTANT | O |

Choix de première version : enlever les espaces périphériques et traiter l'e-mail de connexion sans distinction de casse ; appliquer la même normalisation à l'inscription, connexion et modification. L'unicité porte sur cette représentation comparée de façon déterministe. Cette politique doit être expliquée aux utilisateurs et testée. Les contrôles de rôles, de suspension et d'accès mobile restent côté serveur ; aucune inscription ne peut choisir un rôle interne.

### `clients`

`utilisateur_id ID F UNIQUE → utilisateurs.id` ; `entreprise VARCHAR(200) O` ; `contact_nom/contact_prenom VARCHAR(100) O` ; `email VARCHAR(254) O` ; `telephone VARCHAR(32) O` ; `adresse_ligne1/adresse_ligne2 VARCHAR(255) F` ; `code_postal VARCHAR(20) F` ; `ville VARCHAR(120) F` ; `pays_code CHAR(2) F` ; `cree_le/modifie_le INSTANT O`.

Le téléphone et le code postal restent des textes. Une adresse partielle n'active pas automatiquement l'itinéraire. La relation facultative unique traduit un compte client au plus par fiche et réciproquement. Le rôle client du compte rattaché et l'autorisation de rattachement sont vérifiés par PHP. Pas d'unicité de l'e-mail commercial : ce n'est pas une identité de connexion.

### `prospects`

`entreprise VARCHAR(200) O` ; `nom/prenom VARCHAR(100) O` ; `email VARCHAR(254) O` ; `telephone VARCHAR(32) O` ; `lieu VARCHAR(255) O` ; `type_evenement VARCHAR(100) O` ; `date_souhaitee DATE O` ; `participants_estimes INT UNSIGNED O` ; `besoin TEXT O` ; `statut VARCHAR(20) O` ; `message_echec TEXT F` ; `client_id ID F → clients.id` ; `qualifie_le INSTANT F` ; `cree_le/modifie_le INSTANT O`.

Statut initial `a_contacter`, puis `echoue` ou `converti`. Contrôle choisi : participants strictement positifs. En conversion, client et date de qualification sont requis ; en échec, message non vide et date requis ; à contacter, aucun client rattaché. Formaliser ces combinaisons avec contrôles de ligne explicites, y compris `IS NULL` / `IS NOT NULL`, puis validation métier. Index unique additionnel `(id, client_id)` pour le lien composé ci-dessous.

### `evenements`

`client_id ID O → clients.id` ; `prospect_id ID F UNIQUE` ; `nom VARCHAR(200) F` ; `debut/fin INSTANT F` ; `fuseau VARCHAR(64) O` ; `lieu VARCHAR(255) F` ; `type_evenement/theme VARCHAR(100) F` ; `participants_estimes INT UNSIGNED F` ; `statut VARCHAR(20) O` ; `visible_demande TINYINT O` ; `accord_publication TINYINT O` ; `accord_recueilli_le INSTANT F` ; `preuve_accord TEXT F` ; `image_reference VARCHAR(512) F` ; `image_alternative VARCHAR(255) F` ; `cree_le/modifie_le INSTANT O`.

Les valeurs `F` permettent le brouillon incomplet montré dans les maquettes ; elles ne dispensent pas de compléter nom, période et lieu avant émission du devis. Statuts : brouillon, accepte, en_cours, termine, annule. Si les deux dates sont renseignées, fin strictement postérieure au début. Les transitions suivent le parcours commercial. Fuseau choisi par défaut pour la démonstration : Europe/Paris, sans convertir une date métier en date fictive.

Lien composé `(prospect_id, client_id) → prospects(id, client_id)` : si une demande d'origine est fournie, elle doit être convertie vers le même client. Le serveur vérifie aussi le statut converti ; une clé étrangère seule ne vérifie pas ce statut. Type/thème sont des libellés normalisés en première version, pas des référentiels déjà administrables. La publication nécessite accord, visibilité, statut hors brouillon et contenu public complet ; l'API publique n'expose aucun montant.

## 2. Devis, versions, lignes et réponses

### `devis`

`evenement_id ID O UNIQUE → evenements.id` ; `reference VARCHAR(50) O UNIQUE` ; `version_courante_id ID F` ; `cree_le/modifie_le INSTANT O`.

### `versions_devis`

`devis_id ID O → devis.id` ; `numero INT UNSIGNED O` ; `revision_contenu INT UNSIGNED O` ; `statut VARCHAR(30) O` ; `client_snapshot JSON F` ; `evenement_snapshot JSON F` ; `devise CHAR(3) F` ; `taux_tva DECIMAL(5,2) F` ; `regle_calcul VARCHAR(50) F` ; `total_ht/total_tva/total_ttc DECIMAL(15,2) F` ; `figee_le/transmise_le INSTANT F` ; `cree_le/modifie_le INSTANT O`.

Unicités : `(devis_id, numero)` et `(devis_id, id)`. Numéro et révision positifs. Statuts : brouillon, etude_client, modification, accepte, refuse. Les trois derniers résultats sont cohérents avec l'unique décision associée ; cela se vérifie dans la transaction métier.

La clé composée `devis(id, version_courante_id) → versions_devis(devis_id, id)` empêchera de choisir une version d'un autre dossier. Pour résoudre le cycle d'insertion : créer le dossier avec version courante nulle, créer la version, puis renseigner la version courante, dans une seule transaction. Après validation de la transaction métier, aucun dossier utilisable ne doit rester sans version courante. `F` est ici une nécessité d'initialisation, pas une dérogation métier permanente. Ajouter cette clé après la création des deux tables.

Les snapshots sont des copies historiques volontaires, pas des identifiants de rattachement : entreprise/contact/coordonnées présentées pour le client ; nom, début, fin, fuseau, lieu et caractéristiques présentées pour l'événement. Leur structure sera validée côté serveur. Ils sont complets à la génération du PDF puis figés avant une tentative de transmission ; une contre-proposition crée une nouvelle version. Ni un changement ultérieur de fiche client ni de lieu ne réécrit un PDF transmis.

### `prestations_devis`

`version_id ID O → versions_devis.id` ; `ordre INT UNSIGNED O` ; `libelle VARCHAR(255) O` ; `montant_ht DECIMAL(15,2) O` ; `cree_le/modifie_le INSTANT O`.

Unicité `(version_id, ordre)` ; ordre positif ; montant non négatif. Le choix de permettre une ligne gratuite ne rend pas négatives les autres lignes. Le brouillon peut avoir zéro ligne ; la génération exige au moins une ligne valide. Ne pas sauvegarder de ligne vide uniquement parce que l'interface affiche un champ libre.

### `decisions_devis`

`version_id ID O UNIQUE → versions_devis.id` ; `auteur_id ID O → utilisateurs.id` ; `choix VARCHAR(20) O` ; `motif TEXT F` ; `decidee_le INSTANT O`.

Choix : accepte, refuse, modification. Motif non vide pour modification ; nul pour les autres choix dans cette version. La contrainte unique empêche deux décisions sur la même version ; PHP vérifie avant insertion l'appartenance du compte, le statut et la version courante. Verrouiller le dossier/version pendant la transaction de réponse et refuser un second traitement concurrent. L'acceptation et le passage de l'événement à accepté suivent la même transaction MySQL ; la notification est déclenchée après succès.

## 3. PDF et transmission

### `documents_devis`

`version_id ID O → versions_devis.id` ; `revision_contenu INT UNSIGNED O` ; `etat VARCHAR(20) O` ; `fichier_reference VARCHAR(512) F` ; `empreinte_sha256 CHAR(64) F` ; `genere_le INSTANT F` ; `cree_le/modifie_le INSTANT O`.

États : en_cours, disponible, echec, obsolete. Disponible exige référence, empreinte et date. Les fichiers restent privés. La révision de contenu change lors de toute modification commerciale du brouillon ; une transmission n'accepte qu'un PDF disponible dont la révision correspond encore à celle de la version. Pas de clé étrangère sur la révision mutable : contrôle transactionnel côté service. Un PDF disponible après changement de contenu doit être marqué obsolète ou rejeté à l'envoi.

### `transmissions_devis`

`document_id ID O → documents_devis.id` ; `acteur_id ID O → utilisateurs.id` ; `mode VARCHAR(20) O` ; `destinataire VARCHAR(254) O` ; `etat VARCHAR(20) O` ; `cle_idempotence CHAR(36) O UNIQUE` ; `confirmee_le INSTANT F` ; `cree_le/modifie_le INSTANT O`.

Mode automatique/manuel ; états en_attente, en_cours, confirme, echec, incertain. La version découle du document, sans second champ contradictoire. Confirmé exige une date ; en mode manuel, il s'agit d'une déclaration de Chloé, pas d'un reçu du fournisseur de courriel. Un téléchargement ne crée pas une transmission confirmée. Un résultat SMTP incertain n'autorise pas un renvoi aveugle : vérifier et suivre la tentative ; l'identifiant d'idempotence local ne prouve pas la déduplication par un serveur de courriel.

## 4. Suivi, avis et configuration

| Table | Champs hors identifiant | Contraintes |
| --- | --- | --- |
| `taches` | `evenement_id ID O`, `assigne_a ID F`, `titre VARCHAR(200) O`, `description TEXT F`, `statut VARCHAR(20) O`, dates création/modification | Références événement/utilisateur ; statuts a_faire/en_cours/termine ; utilisateur assigné interne vérifié par PHP |
| `notes_evenement` | `evenement_id ID O`, `auteur_id ID O`, `contenu TEXT O`, dates création/modification | Références événement/utilisateur ; contenu non vide ; règles d'auteur selon matrice des droits |
| `notes_globales` | `auteur_id ID O`, `contenu TEXT O`, dates création/modification | Référence utilisateur ; auteur administrateur contrôlé par PHP |
| `avis` | `evenement_id ID O`, `auteur_id ID O`, `contenu TEXT O`, `statut VARCHAR(20) O`, `moderateur_id ID F`, `modere_le INSTANT F`, dates création/modification | Références événement/utilisateurs ; unique (evenement_id, auteur_id) ; en_attente/valide/refuse ; modérateur et date requis après décision, absents avant |
| `parametres_application` | `cle VARCHAR(100)` comme clé primaire, `valeur TEXT O`, `modifie_par ID F`, `modifie_le INSTANT O` | Référence utilisateur ; auteur absent possible pour valeur initiale ; liste de clés autorisées côté serveur ; pas de secret |

Dans ce tableau, les dates sont `INSTANT O`. L'avis concerne un événement terminé du client connecté. Aucun barème de notation supplémentaire n'est ajouté sans choix explicite ; la conception actuelle conserve un avis textuel. Le remerciement modifiable est initialisé à partir du texte du sujet.

## 5. Table technique de livraison vers MongoDB

`journal_a_emettre` : `id_trace CHAR(36) PRIMARY KEY`, `horodatage INSTANT O`, `type_action VARCHAR(40) O`, `id_utilisateur ID F` sans clé étrangère bloquante, `details JSON O`, `version_schema INT UNSIGNED O`, `etat VARCHAR(20) O`, `tentatives INT UNSIGNED O`, `prochain_essai_le/verrou_jusqua/livre_le INSTANT F`, `jeton_verrou CHAR(36) F`, `derniere_erreur VARCHAR(500) F`, `cree_le INSTANT O`.

Cette boîte de livraison est un choix technique cohérent avec le [modèle MongoDB](modele-journaux-mongodb.md). La trace d'une modification métier est inscrite dans la même transaction MySQL que cette modification. Le traitement asynchrone réclame une ligne avec un verrou temporaire, écrit dans `logs` avec le même `id_trace`, puis marque la livraison. Si l'arrêt intervient entre ces deux dernières étapes, le rejeu est dédupliqué dans MongoDB. Un traitement doit vérifier son jeton de verrou avant de marquer la ligne livrée.

États envisagés : en_attente, traitement, livre, echec. La dernière erreur est expurgée des secrets et du contenu brut des requêtes. La rétention des lignes livrées doit être bornée par une politique à définir, pas en faire un second historique permanent. La référence d'acteur reste une trace même après disparition du compte ; elle n'accorde aucun droit. Les identifiants sont sérialisés conformément au contrat MongoDB.

Les connexions et PDF nécessitent un traitement adapté : constater le résultat réel, puis enregistrer durablement l'intention. Si MySQL est indisponible, ne pas promettre une journalisation fiable ; le comportement dégradé reste à concevoir et tester. Cette table ne signifie pas que l'acheminement fonctionne déjà.

## 6. Calculs retenus pour la première version

Choix de projet, **non imposés par le sujet** : devis en EUR pour la première démonstration, montants à deux décimales, un taux de TVA saisi par version, taux entre 0 et 100. Aucun taux par défaut n'est inventé ; impossible de générer le PDF si ce paramètre manque.

Règle proposée `TVA_TOTAL_2D_HALF_UP_V1` : additionner les HT des lignes, calculer la TVA sur ce total avec le taux de la version, arrondir la TVA à deux décimales (demi au supérieur pour ces montants non négatifs), puis TTC = HT + TVA. Les calculs PHP devront utiliser une représentation décimale exacte ou des entiers mis à l'échelle, pas une succession de flottants binaires. Une précision SQL n'est pas, à elle seule, une définition de l'arrondi métier. Le taux et la règle appliqués sont figés avec le document.

Les devis multi-taux, autres monnaies, remises et factures ne sont pas ajoutés à cette première version. Si ce périmètre évolue, revoir calculs, stockage et tests plutôt que changer silencieusement les anciennes versions.

## 7. Index et suppression : préparation

Outre les clés et unicités précédentes : index candidats sur événements `(client_id, debut)` et `(statut, debut)`, prospects `(statut, cree_le)`, notes `(evenement_id, cree_le)` et `cree_le`, tâches `(assigne_a, statut)`, avis `(statut, cree_le)`, versions `(statut, cree_le)`, livraison `(etat, prochain_essai_le)`. Vérifier les plans des requêtes réelles avant d'ajouter des index redondants.

Le choix provisoire `RESTRICT` évite un effacement accidentel ; il **ne réalise pas** la demande de suppression totale du sujet. Une procédure explicite doit encore déterminer le traitement du compte, des fiches, demandes, notes/avis, snapshots, PDF privés, files de livraison, journaux et sauvegardes. La désactivation seule ne suffit pas. Aucune durée de conservation légale ni cascade générale n'est inventée ici ; ce point reste ouvert avant de finaliser le script et la fonction de suppression.

## 8. Contrôles à exécuter et bilan

| Cas | Résultat à vérifier |
| --- | --- |
| Deux inscriptions avec le même e-mail normalisé | Seconde insertion refusée |
| Même compte rattaché à deux clients | Refus par unicité |
| Événement rattaché à une demande d'un autre client | Refus par clé composée / service |
| Version courante appartenant à un autre dossier | Refus par clé composée |
| Création interrompue entre dossier et première version | Transaction annulée, pas de dossier métier orphelin |
| Deux réponses simultanées au même devis | Une décision conservée ; seconde refusée |
| Brouillon sans lignes / PDF d'une ancienne révision | Sauvegarde du brouillon possible ; génération/envoi invalides refusés |
| Motif absent pour modification | Refus ; aucun motif exigé pour refus du devis |
| Mauvais rôle, événement non terminé, avis en attente | Accès/avis/publication refusés selon le cas |
| Panne MongoDB après validation métier | Intention conservée ; reprise sans rejouer l'opération métier |

Relecture documentaire : les quinze entités métier sont traduites ; les liens facultatifs, uniques et composés correspondent au MCD. Les règles de rôle, de version, d'agrégat et d'envoi sont distinguées des contraintes SQL. Aucun test ci-dessus n'est déclaré exécuté.

Reste à faire : contrôle visuel et export du MCD, choix de suppression/conservation, scripts SQL manuels, scripts MongoDB, jeu d'essai, installation et essais de restauration. Ce fichier est sauvegardé pour alimenter la documentation technique ; #6 reste ouverte.

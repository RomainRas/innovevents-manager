# Modèle des journaux MongoDB — v1

Date : 9 novembre 2026. Conception de la collection `logs`, distincte des données métier MySQL. Source : [énoncé](../enonce/enonce-ecf-cda.pdf), p. 12–13 ; [dictionnaire](dictionnaire-donnees.md) et [MCD relationnel](mcd-relationnel.md).

## Structure proposée

| Champ | Valeur attendue | Origine / règle |
| --- | --- | --- |
| `_id` | Identifiant du document | Identifiant technique MongoDB |
| `horodatage` | Date et heure de l'action | Requis par le sujet, type date présenté sous la forme ISODate ; pas un simple texte d'affichage |
| `type_action` | Un des huit codes ci-dessous | Codes normalisés proposés à partir des huit actions imposées |
| `id_utilisateur` | Identifiant MySQL de l'acteur, ou nul si inconnu | Référence de traçabilité, pas une clé étrangère entre moteurs ; identifiant nul uniquement pour une connexion échouée non identifiée |
| `details` | Objet dont les champs dépendent de l'action | Champs requis du sujet ; pas de copie globale de la requête |
| `id_trace` | Identifiant stable d'une action journalisable | Choix de conception pour dédupliquer la reprise d'une même action ; ne pas le régénérer à chaque tentative d'écriture |
| `version_schema` | Numéro de la structure du journal | Choix de conception : première version 1, pour encadrer les évolutions |

Les identifiants MySQL seront transportés sous une représentation cohérente et sans perte de précision, proposée ici sous forme de chaînes décimales. Le choix physique définitif sera aligné avec les identifiants du MLD. Les données détaillées ne sont pas libres : seules les propriétés prévues pour chaque action sont acceptées.

## Contrat par action

| Code proposé | Propriétés obligatoires de `details` |
| --- | --- |
| `CONNEXION_REUSSIE` | `ip` |
| `CONNEXION_ECHOUEE` | `email_utilise`, `ip` |
| `CREATION_CLIENT` | `id_client`, `nom_client` |
| `MODIFICATION_CLIENT` | `id_client` |
| `SUPPRESSION_CLIENT` | `id_client`, `nom_client` |
| `CREATION_EVENEMENT` | `id_evenement`, `nom_evenement` |
| `CHANGEMENT_STATUT_EVENEMENT` | `id_evenement`, `ancien_statut`, `nouveau_statut` |
| `GENERATION_PDF_DEVIS` | `id_evenement` |

L'IP et l'e-mail de connexion échouée figurent explicitement dans le sujet. Les mots de passe, cookies, jetons, paramètres SMTP et contenu complet des PDF sont exclus. Les noms avant suppression doivent être capturés avant disparition de la fiche, sans conserver arbitrairement toutes ses coordonnées.

Le validateur à écrire vérifiera les champs communs, l'énumération des actions, les détails propres à chacune et les règles de nullabilité. Un document d'action inconnue ou incomplet sera refusé. Aucun validateur MongoDB n'a encore été exécuté.

## Liens avec MySQL et cas de panne

L'acteur et les objets concernés sont référencés par leur identifiant métier. La collection n'est pas utilisée pour accorder des droits ou reconstruire une décision commerciale : MySQL reste la référence pour ces données.

Une transaction MySQL n'englobe pas automatiquement une écriture MongoDB. Proposition à préciser dans l'architecture : enregistrer l'opération métier et une intention de journalisation dans la même transaction MySQL, puis acheminer cette intention vers MongoDB. La réception de la même intention utilise le même `id_trace`, afin d'éviter deux traces finales. Cette file technique n'est pas encore ajoutée au MCD ni installée.

- Échec avant validation MySQL : ne pas produire de trace de succès métier.
- Échec MongoDB après validation métier : conserver l'intention et la reprendre ; ne pas faire croire que l'action métier a échoué si elle est enregistrée.
- Connexions et génération PDF : préciser le moment de constat du résultat et la mise en attente durable avant de déclarer la journalisation fiable. Un échec de connexion n'est pas un succès métier à annuler.
- Afficher/superviser les reprises en échec, sans exposer les journaux aux comptes clients ou employés.

Ces règles décrivent la solution envisagée, pas une garantie déjà démontrée. Les scénarios de panne devront être exécutés après installation des deux bases.

## Accès, recherche et conservation

L'administrateur consulte les journaux ; aucun écran de modification ou suppression manuelle de trace n'est prévu. Les comptes techniques et leurs droits seront séparés selon les usages de l'application et des opérations de maintenance.

Index candidats à valider avec les requêtes réelles : unicité de `id_trace`, tri par horodatage, recherches par acteur et date, et par type d'action et date. Les recherches par client/événement et leurs index seront ajoutées seulement si nécessaires à l'écran retenu. Aucun index ni mesure de performance n'est déclaré réalisé.

La durée de conservation, la purge, le traitement des demandes de suppression et le contenu des sauvegardes restent à définir. Ne pas activer une expiration automatique avec une durée inventée. L'absence de bouton de suppression dans l'interface ne signifie pas conservation illimitée.

## Cas de vérification préparés

1. Les huit actions produisent les détails imposés, sans champ sensible supplémentaire.
2. Une connexion échouée avec compte inconnu permet un acteur nul ; une création client ne l'autorise pas.
3. Une répétition de livraison du même `id_trace` ne crée pas deux journaux.
4. Une panne MongoDB après succès MySQL laisse une reprise durable et ne rejoue pas l'opération commerciale.
5. Un employé ou un client ne peut pas consulter les logs par accès direct.
6. Un document sans horodatage, de type inconnu ou sans détail obligatoire est refusé.
7. Une suppression de compte/fiche est traitée selon la politique définie, sans référence présentée comme active ni secret conservé.

## Sauvegarde et usage pour le rendu

Ce document explique l'usage effectif prévu de la seconde base et complète le MCD MySQL. Il alimentera la documentation technique, la sécurité et les scénarios de test. Les scripts de validation/index, la politique de conservation, les tests et la restauration MongoDB restent à produire. La tâche #6 reste ouverte.

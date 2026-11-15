# Scripts de bases — première version

Préparés puis exécutés sur MySQL 8.4.13 et MongoDB 8.0.34 dans Docker. Les deux services sont opérationnels : 14 tests MySQL et 17 contrôles MongoDB réussis. Aucune base XAMPP modifiée. Voir [l’environnement](../docs/environnement-local.md) et [les preuves et limites](../docs/verification-bases.md).

## Fichiers

- [mysql/001_schema.sql](mysql/001_schema.sql) : seize tables (quinze métier, une file de livraison), contraintes et remerciement initial. SQL rédigé à la main. Pas de compte administrateur avec un mot de passe prédéfini ; initialisation Chloé à préparer séparément.
- [mongodb/001_logs.js](mongodb/001_logs.js) : collection logs, validation stricte des huit actions, index unique de trace et trois index de recherche. Aucun TTL ou jeu de données ajouté.
- [Règles de suppression](../docs/conception/suppression-conservation.md), [MLD](../docs/conception/mld-mysql.md), [modèle MongoDB](../docs/conception/modele-journaux-mongodb.md).

## Initialisation manuelle sur une nouvelle base vide

Avec Compose, les scripts sont déjà importés au premier démarrage : ne pas les rejouer sur les bases existantes. Pour une initialisation manuelle distincte, créer explicitement deux bases isolées nommées `innovevents_test` sur les moteurs prévus. Sélectionner cette base avant toute importation ; ne pas utiliser une base contenant des données personnelles ou d'autres projets. Utiliser des comptes techniques adaptés et des secrets fournis hors dépôt.

Dans le client MySQL 8.4 connecté à cette base :

```sql
SELECT VERSION(), DATABASE();
SOURCE database/mysql/001_schema.sql;
SHOW TABLES;
```

Le chemin SOURCE est relatif au répertoire de lancement du client : lancer depuis la racine du dépôt ou fournir le chemin absolu. Le script SQL ne crée pas la base et ne contient pas `USE`. Ne pas exécuter sur MariaDB en prétendant valider MySQL 8.4. Les collations sont celles de MySQL 8.4.

Dans `mongosh`, connecté au serveur de test et avec `innovevents_test` sélectionnée :

```javascript
db.getName();
load('database/mongodb/001_logs.js');
db.getCollectionInfos({name: 'logs'});
db.logs.getIndexes();
```

Le script refuse une base ne commençant pas par `innovevents_` et refuse de remplacer `logs` si elle existe. Une erreur au milieu de l'initialisation peut laisser des objets créés ; ne pas masquer l'erreur ni ajouter une suppression automatique. Examiner le résultat et utiliser une nouvelle base de test vide ou une migration revue. Le SQL s'arrête sur les objets déjà présents ; ne pas utiliser l'option de poursuite forcée du client.

## Répartition des contrôles

Les contraintes SQL couvrent types, nullabilité, unicités, références, états et plusieurs combinaisons de champs. Elles ne remplacent pas les contrôles de rôle, de session, d'appartenance, de version courante, de total de lignes, de structure complète des snapshots, de révision de PDF et de transitions métier côté PHP. Une transaction doit créer le dossier, sa première version et sélectionner cette version avant validation.

Les références utilisent le comportement restrictif InnoDB par défaut, sans clauses d'actions automatiques sur les colonnes également utilisées dans des CHECK. Une suppression complète exige la procédure explicite documentée. Source : [contraintes CHECK MySQL 8.4](https://dev.mysql.com/doc/refman/8.4/en/create-table-check-constraints.html).

Le validateur MongoDB interdit les champs supplémentaires, y compris dans les détails propres à chaque action. Il accepte un acteur nul uniquement pour une connexion échouée. Il vérifie la forme des identifiants, pas l'existence de comptes dans MySQL ni la validité réseau d'une IP ; ces vérifications restent côté service. Utiliser un entier BSON pour `version_schema`, par exemple `NumberInt(1)` dans mongosh. Source : [validation JSON Schema MongoDB](https://www.mongodb.com/docs/manual/core/schema-validation/specify-json-schema/json-schema-tips/).

## Vérifications et suite

Contrôles exécutés : lecture croisée avec le MLD ; inventaire de 16 tables, 21 références internes et une clé composée ajoutée par ALTER ; présence des colonnes référencées ; unicité des noms de contraintes ; liens documentaires locaux valides. La syntaxe JavaScript a été vérifiée par `node --check`.

Le script MongoDB a aussi été exécuté avec un objet de base simulé : une base mal nommée et une collection déjà existante provoquent un arrêt avant toute écriture ; le chemin nominal construit huit contrats d'action et quatre index. Ces contrôles ne prouvent ni l'acceptation du DDL par MySQL ni le fonctionnement du validateur sur le moteur MongoDB.

Suite des contrôles : environnement installé, imports réussis et jeu fictif transactionnel testé (dont Chloé et José). Restent le jeu de démonstration persistant, les contrôles métier PHP et de concurrence, puis la sauvegarde et la restauration des deux bases. Aucun résultat de restauration ni pourcentage de couverture annoncé.

## Mise à jour

Le jeu permanent existe désormais dans innovevents_demo, distinct de la base de tests. Les sauvegardes et restaurations MySQL/MongoDB ont réussi dans de nouvelles bases, y compris un deuxième essai sans réinitialisation. Voir [le guide et les preuves](../docs/demo-sauvegarde-restauration.md). Les mentions précédentes de restauration ou de comptes de démonstration « à préparer » décrivent l’état ; les scénarios métier complets, les droits applicatifs et la reprise inter-bases restent à développer.

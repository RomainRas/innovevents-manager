# Vérification réelle des bases — 9 novembre 2026

## Environnement et résultat

WSL 2.7.14.0, Docker Desktop 4.99.0, moteur 29.9.3, Compose 5.7.0. Images officielles figées par empreinte dans [Compose](../compose.yaml), MySQL **8.4.13**, MongoDB **8.0.34**. Deux services déclarés sains après import initial : seize tables MySQL, collection logs avec validateur et quatre index MongoDB.

**14 tests MySQL et 17 contrôles MongoDB réussis** avec les scripts conservés dans le dépôt. Le lanceur final a été exécuté après fixation des empreintes. Aucune modification de XAMPP, aucun port publié sur Windows. Les comptes techniques administrateurs servent uniquement à cet environnement local.

## Reproduire

Depuis la racine, Docker Desktop démarré, Docker et Python 3 accessibles dans le terminal :

```powershell
./scripts/Start-Databases.ps1
./scripts/Test-Databases.ps1
```

Le paramètre `-Python` du second script accepte un chemin vers Python. `DOCKER_BIN` peut fournir le chemin Docker pour le test Python direct ; le lanceur PowerShell utilise Docker dans PATH. Les rapports JSON sont produits dans `tmp/database-tests/`, exclus de Git. Ce bilan constitue la synthèse conservée pour le dossier ; les scripts permettent de régénérer les résultats.

## MySQL : données et contraintes

[Jeu fictif](../tests/database/mysql-fixture.sql), [tests](../tests/database/check_mysql.py). Chaque cas crée son propre jeu dans une transaction, puis l’annule. Un marqueur confirme la création du jeu avant de juger le résultat d’un rejet. Les erreurs attendues sont comparées aux codes MySQL, pas seulement à un échec quelconque.

| Cas | Résultat attendu et obtenu |
| --- | --- |
| Jeu fictif complet | Insertion acceptée |
| E-mail normalisé dupliqué | Rejet 1062 |
| Compte client réutilisé | Rejet 1062 |
| Événement rattaché à un autre client que le prospect | Rejet 1452 |
| Version courante appartenant à un autre devis | Rejet 1452 |
| Ordre de prestation dupliqué | Rejet 1062 |
| Montant négatif | Rejet 3819 |
| Fin d’événement égale au début | Rejet 3819 |
| Seconde décision pour la même version | Rejet 1062 |
| Demande de modification sans motif | Rejet 3819 |
| Avis validé sans modérateur | Rejet 3819 |
| Suppression d’un client encore référencé | Rejet 1451 |
| Total TTC incohérent | Rejet 3819 |
| Version brouillon sans prestation | Acceptée, zéro prestation |

Le jeu comprend Chloé et José fictifs, des clients, événements, devis, versions, prestation, décision, métadonnées PDF et transmission, tâches, notes, avis et entrée de file des journaux. Le paramètre de remerciement provient de l’initialisation. Aucun PDF ou e-mail n’a réellement été produit. Le taux utilisé est un exemple de test, pas une exigence du sujet. Les mots de passe des comptes d’essai ne sont pas conservés : ces comptes ne servent pas à une connexion de démonstration.

Après le dernier lancement, utilisateurs, clients et file des journaux contiennent chacun zéro ligne, conformément à l’annulation des transactions. Les compteurs d’identifiants peuvent avancer malgré l’annulation.

## MongoDB : journaux et validation

[Tests](../tests/database/check_mongodb.js) : huit actions valides acceptées ; six documents invalides rejetés avec le code 121 (action inconnue, champ sensible supplémentaire, date texte, acteur absent pour une réussite, détails manquants, version inconnue) ; première trace acceptée puis doublon rejeté avec le code 11000 ; dernier contrôle confirmant le nettoyage ciblé. Total : 17 contrôles réussis.

Seuls les identifiants créés par le test sont supprimés, sans suppression de collection. Les identifiants métier sont fictifs : le test n’établit pas de relation effective avec MySQL. La coordination des deux bases reste à développer.

## Limites et prochaines preuves

Ces résultats vérifient des contraintes de stockage, pas l’application complète. Restent : jeu de démonstration persistant et initialisation Chloé, comptes applicatifs à droits limités, contrôles PHP des droits et des calculs, concurrence, livraison/reprise des journaux, suppression orchestrée, sauvegarde et restauration. Le MCD visuel et les diagrammes restent à compléter. Les tâches #6 et #7 ne sont pas clôturées.

## Correction Windows

Le client MySQL refusait le fichier secret monté depuis Windows parce qu’il apparaissait accessible en écriture à tous. [Le lanceur client](../docker/mysql/client.sh) crée une copie temporaire privée avec un masque 077 et la retire à la sortie ; le contrôle de santé et les tests passent par ce lanceur. Les secrets restent locaux, exclus de Git et du rendu.

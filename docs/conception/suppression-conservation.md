# Suppression et conservation — règles de conception

9 novembre 2026. Le sujet prévoit une demande de suppression des informations et du compte (p. 10), la gestion des suppressions par Chloé (p. 12) et une journalisation comportant des données personnelles (p. 12–13). Cette procédure décrit ce qu'il faudra implémenter et tester ; aucune suppression n'est exécutée.

## Périmètre retenu

Une suspension interdit l'accès mais conserve les informations : elle ne clôture pas une demande d'effacement. Une suppression de fiche, une suppression de compte interne et une demande d'effacement total sont des opérations distinctes. Le serveur vérifie l'identité, l'appartenance et les droits ; la fourniture d'un identifiant ou d'un e-mail dans un formulaire ne suffit pas.

Les essais ECF utiliseront des données fictives. Pour une demande d'effacement complète de ce jeu de démonstration, le scénario de référence supprime les données du client et ses dossiers liés après contrôle du périmètre, sans archive commerciale permanente ajoutée au projet. Ce choix de démonstration n'est pas une règle de conservation légale pour une entreprise réelle.

La CNIL rappelle que le droit à l'effacement comporte des exceptions et qu'une suppression de compte n'efface pas nécessairement les pièces soumises à une obligation de conservation. Les finalités et durées applicables à un déploiement réel devront être définies ; aucune durée chiffrée n'est déduite de l'énoncé. [CNIL — droit à l'effacement](https://www.cnil.fr/fr/comprendre-mes-droits/le-droit-leffacement-supprimer-vos-donnees-en-ligne).

## Parcours d'effacement complet à implémenter

1. Enregistrer une demande authentifiée avec un identifiant de traitement ; confirmer le périmètre et informer le demandeur. Restreindre les accès concernés et révoquer sessions/jetons avant le traitement. Ne pas afficher « supprimé » à ce stade.
2. Constituer un inventaire contrôlé des identifiants client, compte, demandes, événements, versions, fichiers privés et livraisons. Il doit rester accessible uniquement au traitement et pendant sa durée. Rechercher aussi les données personnelles en texte libre ; ne pas se limiter aux clés étrangères.
3. Empêcher de nouvelles écritures, publications, générations de PDF et transmissions sur ce périmètre. Attendre ou arrêter proprement les tâches déjà engagées. Une transmission incertaine doit être examinée ; un travailleur d'envoi ou de journalisation ne doit pas réintroduire les données après purge.
4. Réaliser la suppression relationnelle dans une transaction contrôlée, selon l'ordre ci-dessous. En cas d'échec, annuler cette phase et conserver un état de traitement non terminé.
5. Retirer les PDF, images personnelles concernées, caches et objets stockés hors MySQL. Nettoyer les intentions de journalisation concernées et les documents MongoDB selon le périmètre validé, y compris les identifiants, noms, IP et e-mails pouvant encore identifier la personne. Ce travail doit être reprenable après un échec partiel.
6. Enregistrer un résultat minimal du traitement sans recopier le dossier supprimé, vérifier l'absence de données dans les systèmes actifs, puis informer l'utilisateur. Conserver la distinction entre purge active réalisée et fin du cycle des sauvegardes.

MySQL, MongoDB et le stockage des fichiers ne forment pas une transaction commune. Une orchestration durable de la demande, des reprises et des exclusions d'envoi est nécessaire. Elle reste à implémenter : le présent schéma ne prétend pas contenir déjà le service ou la table de suivi des demandes d'effacement.

## Ordre relationnel pour le client de démonstration

Les clés étrangères restent actives. Le script de création utilise leur comportement restrictif par défaut, sans cascade globale.

1. Retirer les avis, tâches et notes événementielles des événements du périmètre, et les décisions sur leurs versions.
2. Retirer les tentatives de transmission, puis les références de documents PDF, après avoir enregistré les fichiers à purger dans l'inventaire du traitement.
3. Retirer les prestations ; mettre `devis.version_courante_id` à NULL ; supprimer les versions puis les dossiers de devis. Cette mise à NULL rompt le cycle prévu sans désactiver les contraintes.
4. Supprimer les événements, puis leurs demandes prospects converties. Les demandes non converties contenant des coordonnées de la personne doivent faire l'objet d'un rapprochement vérifié ; l'égalité d'e-mail seule n'autorise pas une suppression de dossiers d'autrui.
5. Supprimer la fiche client ; vérifier les éventuelles autres références du compte, puis supprimer le compte client. Une référence inattendue bloque la transaction et déclenche une revue, au lieu d'être supprimée arbitrairement.

Les notes d'équipe rattachées à un événement supprimé disparaissent avec le dossier dans ce scénario d'effacement complet. Les notes globales et contributions concernant d'autres clients ne doivent pas être supprimées en masse : revue du contenu et du motif de conservation. Les décisions commerciales d'un autre dossier ne sont jamais déplacées vers un compte générique pour contourner une contrainte.

## Suppression d'un employé et action simple de gestion

La suppression d'un compte interne ayant des notes, décisions de modération ou transmissions est bloquée par les références. La procédure doit réaffecter les tâches et traiter les contributions sans falsifier leur auteur. Une désactivation provisoire est possible pour retirer les accès, mais ne doit pas être présentée comme un effacement achevé. L'anonymisation effective et la conservation justifiée des contributions restent à définir avant activation de cette fonction ; remplacer un nom tout en conservant des liens réidentifiants n'est pas déclaré anonyme.

Une suppression de fiche depuis l'administration n'est pas une purge silencieuse de tous ses événements. La première version bloque l'action si des dossiers dépendants existent et renvoie vers le traitement du périmètre complet.

## Journaux, files et sauvegardes

La trace de suppression de client demandée par l'énoncé comporte un identifiant et un nom. Elle est produite lors d'une action normale de gestion, puis soumise à la politique de conservation/effacement ; elle ne justifie pas de conserver indéfiniment une identité après effacement. La maintenance de purge est distincte de l'interface de consultation des logs.

Les sauvegardes sont protégées et leur accès limité. Une restauration doit se faire dans un environnement isolé : rejouer les effacements applicables avant de rouvrir les accès ou de relancer les envois et la livraison des journaux. Le registre minimal permettant cette reprise est lui-même protégé, à durée bornée et purgé après expiration des sauvegardes concernées. Le cycle chiffré et son paramétrage restent à arrêter avant tout usage de données réelles. [CNIL — sécurité des sauvegardes](https://www.cnil.fr/fr/securite-sauvegarder).

## Critères de vérification

- Demande par un autre client refusée ; compte suspendu sans session encore active.
- Aucun effacement de dossier voisin, même si des coordonnées sont proches.
- Échec SQL : transaction annulée ; échec MongoDB/fichier : demande non clôturée et reprise possible.
- Aucun PDF encore accessible ni envoi différé après effacement.
- Aucune réapparition par reprise d'outbox ou restauration.
- Suppression d'un auteur référencé bloquée et expliquée, sans perte silencieuse de contributions.

Ces critères sont préparés, non exécutés. Les règles de périmètre et d'ordre sont définies pour la démonstration ; durées de production, orchestration d'effacement, anonymisation des contributions internes et tests restent ouverts. Le document complète le [MLD](mld-mysql.md) et servira à justifier les choix dans le dossier.

# Images des brouillons d’événements

> **Note :** ce document retrace l’étape où cette fonctionnalité a été développée. Les éléments qu’il signale comme « à développer » ont pour la plupart été réalisés depuis ; l’état final, avec les limites encore valables, est dans le [bilan des fonctionnalités](bilan-fonctionnalites.md).

## Source et périmètre

L’énoncé p. 9 demande à Chloé d’ajouter une image de l’événement, par exemple celle du lieu. La page 7 prévoit une image sur la page publique, mais uniquement avec l’accord du client et un statut différent de brouillon.

Cette livraison couvre l’ajout et le remplacement d’une image dans un brouillon, sa description accessible et son aperçu privé pour l’administratrice. Elle ne publie pas l’événement. La collecte du consentement, les transitions de statut et la page publique restent à développer ; l’acceptation du devis doit conserver sa place dans le parcours prévu.

## Utilisation

1. Depuis une fiche client, ouvrir l’événement déjà créé en brouillon.
2. Dans « Image de l’événement », sélectionner un JPEG ou PNG et saisir une description utile pour une personne qui ne voit pas l’image.
3. Enregistrer l’image. L’aperçu apparaît dans la fiche.
4. Pour la remplacer, sélectionner un autre fichier, adapter la description et enregistrer.

Les informations du brouillon et son image se sauvegardent séparément. Une opération en cours, ou dont la réponse est incertaine, bloque temporairement l’autre formulaire. En cas de réponse perdue, le bouton de reprise renvoie exactement le même contenu. Après un conflit avec une modification plus récente, recharger la fiche avant de recommencer.

## Choix techniques

- JPEG/PNG uniquement, maximum 5 Mio reçus, 4 096 pixels par côté et 8 millions de pixels au total. Ces limites complètent l’énoncé ; elles ne sont pas imposées par celui-ci.
- Le serveur contrôle les dimensions et le format réel, décode avec GD puis réencode. Le nom et le type annoncés par le navigateur ne servent pas à choisir le chemin ou le format enregistré. SVG et fichiers non décodables sont refusés.
- Le réencodage retire les métadonnées et le contenu ajouté après l’image. La correction automatique de l’orientation EXIF n’est pas implémentée : vérifier l’aperçu après ajout.
- La description est obligatoire et limitée à 255 caractères ; le formulaire la reprend dans l’attribut alternatif de l’image.
- Le fichier est nommé par l’empreinte SHA-256 de l’image réencodée et stocké hors du dossier web. Aucun chemin fourni par l’utilisateur n’est utilisé.
- Une route authentifiée vérifie le rôle administrateur avant de lire l’image courante. Réponses sans cache, type JPEG/PNG fixe et interdiction de détection automatique du type. L’identifiant de l’événement seul ne donne aucun accès à l’image.
- Les écritures vérifient session, rôle, CSRF, statut brouillon et révision courante. Une répétition identique après perte de réponse retrouve la révision précédente sans nouvelle modification.
- Le compte SQL dédié peut lire les seules colonnes nécessaires et modifier uniquement la référence d’image, sa description et la révision. Il ne peut pas publier, changer le statut ou réaffecter le client.

## Installation et conservation

GD avec JPEG/PNG est ajouté à l’image PHP et déclaré dans Composer. `Start-App.ps1` provisionne `ecf_event_images`, monte son secret dans l’API et reconstruit le conteneur. Aucune migration de schéma ni table supplémentaire : les colonnes d’image existaient déjà.

Les fichiers sont persistants dans le volume local `auth_state`, sous `/var/lib/innovevents/event-images`, hors de Git. Le secret reste dans `.secrets/mysql-event-images.txt`, ignoré par Git. Redémarrer le service ne supprime pas les fichiers ; supprimer le volume les supprime.

Une image est écrite avant de modifier la référence SQL. Une erreur de base peut donc laisser un fichier inutilisé, mais ne remplace pas la référence courante. Les anciennes images restent conservées lors d’un remplacement : aucun nettoyage automatique susceptible de casser une référence partagée n’est introduit. Prévoir un nettoyage des fichiers non référencés et une politique de conservation avant production.

Les sauvegardes MySQL/MongoDB existantes ne sauvegardent pas ce volume d’images. La sauvegarde/restauration cohérente des fichiers avec les références SQL reste à ajouter et à tester. Ne pas présenter la sauvegarde du socle initial comme une sauvegarde complète des images.

## Tests

`tests/app/event-images.py` crée ses propres fiches et images fictives, vérifie les routes, puis lance `event-images-browser.cjs` à 1280 et 390 px. `backend/tests/event-images-permissions.php` vérifie les refus SQL. Les fiches de test sont nettoyées en sortie ; les petites images réencodées restent dans le volume, comme les fichiers non référencés décrits ci-dessus. Un arrêt brutal du processus peut empêcher le nettoyage des fiches.

Les résultats exécutés sont conservés séparément dans le fichier de preuve. Les captures et données temporaires sont dans `tmp/`. Pas de test de charge, d’audit complet d’accessibilité ou de publication publique revendiqué.

### Résultats

- 24 contrôles serveur/données réussis : rôles, CSRF, description, faux PNG, SVG, image tronquée, dimensions et poids, réencodage, en-têtes privés, nom de fichier non utilisé comme chemin, lecture protégée, reprise identique, conflit, remplacement JPEG, révision partagée, publication inchangée et refus hors brouillon.
- Six refus de permissions SQL vérifiés.
- Parcours Chromium 1280/390 px : ajout, erreurs et focus, blocage de l’autre formulaire pendant la reprise, remplacement, aperçu réellement chargé, persistance après rechargement et modification des informations du brouillon après l’image. Aucun débordement ni erreur JavaScript ; capture mobile relue.
- La perte de confirmation est simulée côté navigateur après un véritable transfert réussi, en empêchant le formulaire de recevoir sa réponse. Elle ne correspond pas à une coupure physique du réseau.
- Compilation Vue et construction Docker réussies ; Composer valide le manifeste et le verrouillage. Ses avertissements préexistants concernent la licence non renseignée et le verrouillage exact du pilote MongoDB.
- Fiches fictives nettoyées. Les petites images de test restent dans le volume ; aucune suppression de fichier partagé n’a été lancée.

[Preuve sans secrets](preuves/images-evenements.json).

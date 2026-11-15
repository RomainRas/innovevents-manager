# Génération et téléchargement du devis PDF — 14 novembre 2026

> **Note du 15 novembre 2026 :** ce document décrit l’état du projet à sa date de rédaction. Les éléments qu’il signale comme « à développer » ont pour la plupart été réalisés depuis ; l’état final, avec les limites encore valables, est dans le [bilan des fonctionnalités](bilan-fonctionnalites.md).

## Périmètre et utilisation

L’énoncé p. 9 prévoit la génération du devis PDF, son téléchargement pour envoi manuel ou son envoi par la plateforme ; la journalisation de la génération est prévue p. 13. Cette livraison réalise **la génération et le téléchargement administrateur**, avec trace MongoDB. La transmission, le passage en étude côté client et la réponse du client restent à développer.

1. Compléter le client et l’événement (nom, dates/heures, lieu, image avec description).
2. Enregistrer les prestations et le taux de TVA depuis « Préparer les prestations et le devis ».
3. Cliquer « Générer le PDF », puis « Télécharger le PDF ».
4. Après modification d’une donnée du document, générer un nouveau fichier. Le téléchargement ne vaut pas envoi et ne modifie pas le statut commercial.

Les coordonnées juridiques complètes de l’agence n’étant pas fournies, aucune adresse, immatriculation ou condition commerciale n’est inventée. Le PDF porte une mention de démonstration ECF, non transmis, avec coordonnées à compléter avant usage commercial. Il contient la référence, version/révision, coordonnées du client, nom/lieu/dates de l’événement en heure de Paris, son image, les prestations, HT/TVA/TTC, taux et pagination.

La génération exige un événement brouillon, les informations de préparation ci-dessus, une version de devis brouillon non figée et des totaux cohérents avec les lignes. Ces contrôles de complétude sont des décisions de conception. Le taux et l’arrondi restent ceux documentés dans [les prestations](prestations-devis.md).

## Source courante et cohérence

La migration additive 008 ajoute `source_empreinte` au document. Une empreinte couvre les informations client/événement, la référence d’image, les prestations et les données commerciales de la version. Le PDF est retrouvé lors d’une répétition identique, sans deuxième document ni trace de génération supplémentaire.

La génération partage le verrou nommé du service de prestations, puis prend des verrous de lecture sur les données sources. Le fichier est produit et écrit hors du dossier web. Dans la transaction SQL sont enregistrés les copies des coordonnées client/événement, l’obsolescence des anciens fichiers disponibles, le nouveau document et l’intention `GENERATION_PDF_DEVIS`. Le traitement existant livre le journal vers MongoDB. Une erreur de mise en file annule l’enregistrement SQL : le nouveau fichier n’est pas offert au téléchargement.

Les copies historiques sont renseignées mais pas figées à cette étape : le futur envoi doit coordonner son verrouillage et son gel avec le même verrou de devis. Un document déjà transmis n’est pas géré par ces nouvelles routes, limitées au brouillon. Aucun gel ni envoi n’est prétendu réalisé.

Une modification des prestations marque les PDF disponibles obsolètes. Un changement du client, du lieu, des dates ou de l’image est détecté par l’empreinte lors de la consultation et du téléchargement, même si le fichier n’a pas été explicitement marqué obsolète. Le téléchargement vérifie aussi l’intégrité SHA-256 des octets. Il ne sert jamais un chemin fourni par l’utilisateur.

## Rendu et accès

La bibliothèque [Dompdf](https://github.com/dompdf/dompdf) est installée par Composer et verrouillée à 3.1.6, avec ses dépendances. Son installation et les extensions DOM/MBString suivent le dépôt officiel ; la police DejaVu Sans fournie couvre les accents français. Les accès distants, PHP et JavaScript intégrés sont désactivés, les valeurs métier sont échappées et seule l’image interne vérifiée est incorporée.

Les trois routes exigent le rôle administrateur ; génération avec CSRF. Le PDF est servi en pièce jointe, sans cache. L’interface bloque l’édition pendant une génération incertaine et propose une reprise ; une modification non enregistrée masque le PDF proposé. Les clients, employés et visiteurs n’ont pas accès à ce téléchargement administrateur.

Le compte `ecf_quote_pdf` lit les sources, met à jour les copies et les états des documents, insère le document et l’intention de journal. Il ne modifie ni les montants, ni les statuts commerciaux, ni les coordonnées client.

## Stockage, installation et limites

Start-App applique la migration et provisionne le compte ; Docker ajoute DOM/MBString et Composer installe les dépendances verrouillées. Les PDF et le cache des polices sont dans le volume `auth_state` sous `quote-pdfs` et `pdf-cache`. Aucune archive ou clé locale n’est versionnée.

Un échec après écriture du fichier mais avant validation SQL peut laisser un fichier non référencé. Les anciennes versions de fichiers restent conservées. La sauvegarde/restauration du volume avec les bases et le nettoyage contrôlé des fichiers non référencés restent à développer ; la procédure de sauvegarde des seules bases ne couvre pas les PDF.

Tests : `tests/app/quote-pdf.py`, `quote-pdf-browser.cjs` et `backend/tests/quote-pdf-permissions.php`. Ils créent des clients, événements et devis fictifs, retirent brièvement le droit d’écriture du journal, puis rétablissent ce droit et nettoient les fiches. À exécuter localement hors autre travail concurrent. Les traces et fichiers fictifs restent conservés ; `finally` ne protège pas d’un arrêt brutal.

Les exemples locaux sont dans `tmp/quote-pdf`, hors Git. Les preuves exécutées sont conservées séparément. Pas de garantie de transaction distribuée entre fichier, MySQL et MongoDB ; aucune mesure de couverture, charge, concurrence multi-processus ou conformité PDF accessible complète revendiquée.

## Résultats exécutés le 14 novembre

- 22 contrôles serveur/données et six refus SQL réussis ; génération, reprises, invalidation, confidentialité, trace MongoDB et annulation si le journal échoue.
- Chromium 1280/390 px : génération, reprise après confirmation perdue simulée, focus, blocage du formulaire concurrent, téléchargement réel, rechargement et masquage du PDF après saisie non enregistrée.
- PDF court d’une page et long de neuf pages (100 prestations) rendus et relus : colonne de libellé élargie, numéro 100 maintenu sur une ligne. Dernière page régénérée et relue après correction ; extraction des 100 prestations vérifiée.
- Compilation Vue et construction Docker réussies ; Dompdf 3.1.6 verrouillé, cinq nouveaux paquets au total, sans modification des paquets existants.
- Droit SQL rétabli et fiches fictives nettoyées. Traces et fichiers fictifs conservés.

[Preuve sans secrets](preuves/devis-pdf-2026-11-14.json). Aucun envoi ni accès client au PDF réalisé à ce stade.

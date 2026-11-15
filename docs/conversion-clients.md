# Conversion d’un prospect en client

## Périmètre et sources

L’énoncé p. 9 prévoit que Chloé retrouve un prospect et le convertisse en client, avec reprise automatique des informations dans le formulaire. Les journaux de création de client sont prévus p. 12–13 et précisés dans le [modèle MongoDB](conception/modele-journaux-mongodb.md). Le parcours et la matrice des droits réservent la conversion à l’administratrice.

Cette livraison couvre la fiche client et la trace de sa création. L’événement, les prestations, la TVA, le PDF et la transmission du devis restent les étapes suivantes. Aucun compte utilisateur n’est créé ou rattaché par cette conversion : ce rattachement sera traité avec la mise à disposition du devis, conformément à la décision de conception déjà documentée.

## Utilisation

1. Depuis l’espace administratrice, ouvrir « Consulter les demandes reçues » puis une demande à contacter.
2. Cliquer « Convertir en client ». Entreprise, nom et prénom du contact, adresse électronique et téléphone sont repris automatiquement.
3. Corriger les coordonnées si nécessaire, confirmer puis créer la fiche.
4. Consulter la fiche client créée. Le prospect apparaît désormais comme converti et permet de retrouver cette fiche.

Le lieu de l’événement n’est pas repris comme adresse du client : ces deux informations sont distinctes. La demande originale est conservée sans réécrire ses coordonnées. Deux demandes distinctes avec la même adresse électronique ne sont pas fusionnées automatiquement ; l’adresse seule ne prouve ni une identité ni une même entreprise. La gestion d’un client déjà existant reste à prévoir dans les prochaines fonctions de gestion.

## Cohérence et sécurité

Le serveur vérifie le rôle, la session, CSRF et les cinq champs. Les longueurs sont celles du schéma (entreprise 200, nom/prénom 100, adresse électronique 254, téléphone 32). Le téléphone accepte les chiffres et séparateurs usuels avec au moins six chiffres. Ces bornes sont des décisions techniques.

La conversion verrouille le prospect puis crée la fiche, renseigne le lien et le statut converti, et insère une intention de journal dans la même transaction MySQL. Un échec d’écriture du journal annule la création et le changement d’état. Le client ne peut pas fournir un rôle, un compte utilisateur à rattacher ou un statut à imposer.

Une empreinte des coordonnées normalisées est stockée sur le prospect. Une nouvelle tentative avec les mêmes coordonnées retrouve la même fiche sans rejouer la création ni le journal. Si le contenu diffère ou si la demande est échouée, l’action est refusée. La protection vaut pour une demande donnée ; elle n’identifie pas les doublons entre demandes différentes.

Le formulaire conserve les données et bloque leur modification après une réponse incertaine, pour permettre une reprise identique. Les erreurs sont associées aux champs, le focus va à la première erreur puis au résultat. Les destinations après connexion restent limitées aux chemins internes autorisés.

## Journalisation MySQL → MongoDB

La table existante `journal_a_emettre` reçoit un UUID stable, la date UTC, l’acteur administrateur, `CREATION_CLIENT` et les détails `id_client`/`nom_client`. Par choix de conception, `nom_client` correspond au nom de l’entreprise. Il n’y a pas de copie des coordonnées complètes ni de mot de passe dans la trace.

`journal_worker` lit une ligne à traiter sous verrou MySQL, tente l’insertion dans MongoDB et marque la livraison. L’index unique MongoDB sur `id_trace` permet de reconnaître une trace déjà reçue après perte de l’accusé SQL. L’identifiant est créé côté serveur et les champs sources ne sont pas modifiables par le compte de livraison. Aucune garantie de transaction distribuée entre les moteurs n’est prétendue.

Après un échec, la ligne reste durablement en attente avec une tentative supplémentaire et une échéance à 60 secondes. Au cinquième échec, elle reste en état échec, sans suppression : diagnostic et relance manuelle à prévoir. Seul le nom de classe d’erreur est conservé. Aucun écran de supervision ni bouton de reprise des échecs définitifs n’est livré à cette étape. Un arrêt prolongé doit donc être suivi en consultant la file et les journaux de service.

Une session administrateur déjà ouverte peut convertir pendant une panne MongoDB, car l’intention de journal est enregistrée dans MySQL. Une nouvelle connexion continue de dépendre de la journalisation d’authentification existante : elle n’a pas été basculée vers ce traitement différé.

## Installation et permissions

`006_prospect_conversion.sql` ajoute une colonne d’empreinte, sans nouvelle table. `provision-conversion.py` vérifie sa présence avant application, prépare les secrets locaux et les comptes :

- `ecf_conversion` : lecture prospects/clients, insertion des coordonnées client, mise à jour des seules colonnes de conversion, insertion des champs sources du journal. Aucun rattachement de compte ni mise à jour/suppression d’une fiche client.
- `ecf_journal` : lecture de la file et modification des seuls champs de livraison. Aucun accès aux clients ni modification du contenu source du journal.
- MongoDB : réutilisation du compte d’insertion seule, sans lecture, modification ou suppression des traces.

Start-App prépare cette migration puis lance le service de livraison. Le nombre de tables de démonstration reste 18. Secrets dans `.secrets/`, exclus du dépôt. La restauration complète après cette nouvelle colonne et ces droits reste à rejouer.

## Vérifications et conservation

Les tests sont dans `tests/app/conversion.py`, `conversion-browser.cjs` et `backend/tests/`. Ils utilisent des prospects et fiches fictifs propres à l’essai, puis les suppriment par une adresse unique. Les traces de création restent dans le journal comme historique de test ; elles ne sont pas supprimées par l’application. Les essais retirent temporairement un droit SQL et arrêtent MongoDB : les exécuter hors autres travaux locaux. Nettoyage et restauration prévus dans `finally`, sans garantie en cas d’arrêt brutal du processus.

Les rapports et captures restent dans `tmp/` ; la synthèse des résultats exécutés figure dans le fichier de preuve associé. Ce travail alimente le dossier technique, les tests et l’explication des deux types de bases. Il ne constitue pas un déploiement public ni une validation supplémentaire des maquettes Figma.

### Résultats exécutés

- 24 contrôles HTTP/données et reprise : droits, CSRF, champs, coordonnées corrigées, conservation de la demande originale, absence de rattachement automatique à un compte, reprise identique, conflit, refus d’une demande échouée, contenu du journal, déduplication MongoDB, annulation de la conversion si la mise en file échoue, conversion pendant panne MongoDB et livraison différée après le délai réel de reprise.
- Neuf refus de permissions vérifiés : cinq pour le compte de conversion, quatre pour le traitement du journal.
- Chromium à 1280/390 px : retour à la conversion après connexion, préremplissage, confirmation, focus sur erreur, modification des coordonnées, perte de réponse après création puis reprise, focus sur résultat, fiche client, absence de débordement et d’erreur JavaScript.
- Compilation Vue réussie ; capture mobile relue. MongoDB redémarré et droit SQL rétabli. Les fiches fictives ont été nettoyées ; les journaux fictifs restent conservés.

[Preuve sans secrets](preuves/conversion-clients.json). Le test de reprise MongoDB replace une trace déjà livrée en attente pour simuler l’accusé SQL perdu. La panne MongoDB est réelle ; les cinq échecs définitifs et la concurrence de conversion entre plusieurs administrateurs n’ont pas été injectés. Aucun test de charge ou audit complet d’accessibilité revendiqué.

# Consultation et qualification négative des prospects

Liste, recherche, détail et classement en échoué développés et testés localement. La [conversion en client](conversion-clients.md) a ensuite été ajoutée, avec son propre bilan et ses preuves.

## Consignes et périmètre

La page 9 de l’[énoncé](enonce/enonce-ecf-cda.pdf) a été relue directement. Chloé contacte le prospect pour préciser son besoin et la faisabilité. Si le projet ne peut pas aboutir, elle classe la demande en échoué et un message est envoyé au prospect. Le sujet prévoit aussi la conversion en client et la préparation du devis, qui ne sont pas incluses dans cette livraison.

La [matrice des droits](conception/utilisateurs-et-droits.md) réserve la qualification à l’administratrice. Les employés et clients n’accèdent ni à la liste ni au détail, même par appel direct à l’API. Le statut et l’empreinte de session sont revérifiés par l’authentification existante. Aucune nouvelle création de compte administrateur n’est ajoutée.

## Utilisation locale

1. Se connecter comme Chloé, puis choisir « Consulter les demandes reçues » dans son espace.
2. La page `/prospects` présente les demandes à contacter par défaut. Rechercher par entreprise, nom ou adresse électronique, choisir un statut et ouvrir une fiche.
3. Consulter les dix champs saisis par le prospect et prendre contact hors de l’application pour qualifier le besoin.
4. Si le projet ne peut pas aboutir, saisir le message, cocher la confirmation, puis classer en échoué. L’interface confirme la mise en attente du courriel, pas sa livraison effective.
5. Vérifier le courriel de démonstration dans Mailpit sur http://127.0.0.1:8025. Aucun courriel externe n’est envoyé.

L’accès direct à une page protégée mène à la connexion puis revient à une destination interne autorisée. Le retour vers une fiche est accepté uniquement si son chemin et son identifiant correspondent au format prévu.

## Fonctionnement et choix techniques

- Liste par identifiant décroissant, 25 résultats par page, curseur pour les suivants et retour aux premières demandes. La recherche textuelle utilise une requête préparée et ne traite pas les caractères saisis comme du SQL. La recherche est limitée à 200 caractères.
- Message non vide, 2 000 caractères maximum, conservé et envoyé comme texte. Ces limites, la pagination et la confirmation sont des choix de conception.
- La qualification utilise un verrou de ligne et une transaction MySQL qui regroupe le statut, le message, la date et la mise en file du courriel. Si cette dernière échoue, la modification du prospect est annulée.
- Une nouvelle tentative sur un prospect déjà échoué avec exactement le même message normalisé retrouve le résultat sans ajouter de courriel. Un message différent ou un autre état déjà traité produit un conflit. Aucune réouverture ni modification rétroactive n’est proposée.
- Après une réponse perdue, le formulaire conserve et bloque le texte pour permettre une reprise identique. Un état devenu incompatible entraîne une relecture de la fiche.
- `ecf_prospects_admin` dispose de SELECT sur prospects, UPDATE sur statut/message_echec/qualifie_le et INSERT sur la file. Il ne peut modifier les coordonnées, rattacher un client, supprimer un prospect, lire les utilisateurs ou lire les courriels.
- Migration manuelle `005_prospect_qualification.sql` : ajoute le type de courriel `qualification_echec` à la contrainte existante. `provision-prospects.py` vérifie cette présence avant application et prépare le secret local. Start-App l’appelle après la migration des demandes. Le nombre de tables reste 18 dans la démonstration et 16 dans la base de vérification.

La page ne prétend pas journaliser une action MongoDB de qualification : cette action ne figure pas dans les contrats de journaux retenus. Les actions client/événement de la future conversion devront respecter leurs contrats spécifiques.

## Tests et preuves

`tests/app/prospects.py` produit 25 contrôles HTTP/données, plus le résultat du parcours navigateur. Il crée 30 prospects fictifs : tests de pagination 25 + 5, recherche vide, champs et accents, identifiants/filtres invalides, accès client/employé/anonyme, CSRF, message vide/trop long, qualification, reprise identique, conflit et refus d’un prospect converti. Le courriel reçu localement contient le message attendu.

Le retrait temporaire du droit d’insertion dans la file provoque un échec réel après la tentative de modification du prospect : réponse 503 et état initial inchangé vérifiés. Le droit est rétabli dans le nettoyage. Exécuter ce test hors d’autres qualifications locales. Les prospects fictifs sont supprimés par une adresse unique de test ; courriels de démonstration et métadonnées de file conservés. Le nettoyage ne couvre pas l’arrêt brutal du processus.

`backend/tests/prospect-permissions.php` vérifie cinq refus SQL. `tests/app/prospects-browser.cjs`, lancé par le test Python avec ses fiches fictives, contrôle Chromium à 1280 et 390 px : retour après connexion, recherche, fiche, confirmation obligatoire, réponse perdue après enregistrement puis reprise, affichage en texte, focus sur le résultat, absence de débordement et d’erreur JavaScript. Capture mobile relue et compilation Vue réussie.

Les rapports et captures bruts restent dans `tmp/`. [Preuve synthétique sans secret](preuves/qualification-prospects.json). Aucun audit exhaustif d’accessibilité, test de charge ou essai de concurrence de qualification par deux administrateurs n’est revendiqué. La réception unique après nouvelle tentative n’annule pas les limites SMTP documentées : un arrêt après envoi et avant validation SQL peut encore produire un doublon.

## Rendu et suite

Code, migration, documentation et preuve sont conservés sur `dev` pour alimenter le dossier technique. Le reporting personnel est actualisé. Aucun hébergement public, changement direct du tableau Projects ou fusion sur `main` n’est effectué. Après la conversion désormais disponible, restent l’association d’un événement et la préparation commerciale du devis.

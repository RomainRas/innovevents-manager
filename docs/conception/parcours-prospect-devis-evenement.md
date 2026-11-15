# Parcours prospect devis événement

Ce document décrit le parcours commercial d’Innov’Events, depuis la demande initiale jusqu’au suivi de l’événement. Il fixe les responsabilités, les changements d’état et les notifications à réaliser. Il complète [l’analyse des exigences](analyse-exigences.md) et [la matrice des droits](utilisateurs-et-droits.md).

Source : [énoncé ECF](../enonce/enonce-ecf-cda.pdf), pages 7 à 14. Les liens relatifs sont destinés à la version de référence dans `docs/conception/` sur GitHub. Les choix qui complètent les consignes sont identifiés comme décisions de conception. Les fonctions décrites ne sont pas encore développées.

## Objets du parcours

| Objet | Rôle |
|---|---|
| Demande du prospect | Besoin initial et informations de contact enregistrés dans la table `prospects` ; qualification par Chloé |
| Fiche client | Informations du client et rattachement de ses événements ; distincte du compte de connexion |
| Devis | Proposition commerciale avec prestations, montants, PDF et décision du client |
| Événement | Projet à préparer et suivre, avec dates, lieu, client, statut, notes et tâches |
| Compte utilisateur | Authentification et accès aux devis/projets affectés ; sa création seule ne vaut pas acceptation d’un devis |

Les statuts de la demande, du devis et de l’événement sont séparés. Un devis accepté et un événement en cours peuvent ainsi coexister sans que l’un efface le statut de l’autre.

## Déroulement principal

| Étape | Acteur et action | Effet attendu | Référence |
|---|---|---|---|
| 1 Demande initiale | Visiteur ou utilisateur remplit le formulaire | Validation de tous les champs, enregistrement dans `prospects`, statut « à contacter », courriel à l’agence et remerciement modifiable affiché | p. 8 |
| 2 Qualification | Chloé contacte le prospect pour préciser le besoin et la faisabilité | Si impossible : « échoué » et message envoyé au prospect. Si possible : poursuite vers la conversion | p. 9 |
| 3 Conversion | Chloé sélectionne le prospect et utilise « convertir en client » | Reprise automatique des informations dans la fiche client ; possibilité d’associer un événement | p. 9 |
| 4 Préparation | Chloé complète l’événement, son image et les prestations | Informations de l’événement modifiables ; prestations avec libellé et HT ; calcul automatique de la TVA et du TTC | p. 9 |
| 5 Proposition | Chloé génère le PDF puis le transmet | Téléchargement pour envoi manuel ou envoi par l’application ; devis disponible dans l’espace utilisateur et statut « étude côté client » | p. 9–10 |
| 6 Décision | Le client consulte son devis dans son espace | Acceptation, demande motivée de modification ou refus ; détails ci-dessous | p. 10 |
| 7 Organisation | Après acceptation, Chloé lance la préparation | Passage en cours, contact avec les prestataires, notes et suivi des tâches ; consultation et contribution des employés | p. 11–12 |
| 8 Mobilité | Chloé ou José consulte les informations en déplacement | Événements à venir, fiche client, appel/courriel/itinéraire et ajout de notes | p. 14 |
| 9 Fin et avis | Chloé termine l’événement ; le client peut l’évaluer | Avis à valider ou refuser par un employé/administrateur ; seuls les avis validés sont publics | p. 10–12 |

## Données et contrôles à chaque étape

- Demande initiale : entreprise, nom/prénom, courriel, téléphone, lieu, type d’événement, date souhaitée, participants estimés et description du besoin sont tous obligatoires (p. 8). Une demande invalide n’est pas enregistrée comme complète.
- Conversion : reprendre les données existantes sans les ressaisir ; conserver le lien avec la demande initiale. La conservation de ce lien est un choix de traçabilité.
- Événement : nom, date/heure de début, date/heure de fin, lieu, statut, visibilité et image (p. 9). Contrôler une fin postérieure au début est une règle de cohérence retenue pour le projet.
- Devis : chaque prestation possède un libellé et un HT. Les calculs TVA/TTC sont automatiques, mais le taux, la devise et les arrondis ne sont pas fournis ; leur paramétrage sera défini dans la conception des calculs avant développement.
- Compte absent : le système crée un compte client et envoie un mot de passe par défaut, modifiable à la première connexion (p. 10). Décision de conception : réaliser ce rattachement avant la mise à disposition du premier devis afin que le client puisse consulter et répondre. Ne pas créer un second compte si un compte correctement identifié existe déjà.
- Décision client : contrôler côté serveur que le devis est affecté au compte connecté et qu’il est actuellement soumis pour décision. Le client ne modifie pas directement les prestations ni les montants.

## États de la demande du prospect

| État de départ | Déclencheur | État d’arrivée | Statut de la règle |
|---|---|---|---|
| Aucun | Formulaire complet enregistré | À contacter | Exigence p. 8 |
| À contacter | Qualification conclue impossible par Chloé | Échoué | Exigence p. 9 ; courriel avec message |
| À contacter | Conversion validée par Chloé | Converti | Conversion exigée p. 9 ; libellé « converti » ajouté par choix de conception |

« Converti » permet de distinguer une demande déjà traitée d’une demande encore à contacter. La table `prospects` conserve la demande et le lien vers la fiche client ; aucune suppression automatique n’est déduite du sujet. Pour cette première version, les réouvertures d’une demande échouée ou convertie ne sont pas prévues ; un nouveau besoin fait l’objet d’une nouvelle demande.

## États et décisions du devis

| État de départ | Acteur et déclencheur | État d’arrivée | Effet associé | Source ou décision |
|---|---|---|---|---|
| Aucun | Chloé prépare une proposition | Brouillon | Proposition interne, non soumise à la décision du client | État de préparation choisi ; le sujet ne nomme pas le statut initial du devis |
| Brouillon | Chloé transmet le devis finalisé | Étude côté client | PDF et proposition disponibles pour le client | p. 9–10 ; déclencheur de transmission précisé par conception |
| Étude côté client | Client accepte | Accepté | Courriel à Innov’Events ; Chloé retrouve les devis acceptés | p. 10, 12 |
| Étude côté client | Client demande une modification avec un motif | Modification | Motif visible à Chloé ; contre-proposition possible | p. 10 |
| Modification | Chloé prépare et transmet la contre-proposition | Étude côté client | Nouvelle proposition présentée au client | Contre-proposition et renvoi exigés p. 10, 12 ; retour d’état choisi |
| Étude côté client | Client refuse | Refusé | Fin de cette proposition | p. 10 |

Décisions de conception pour préserver la cohérence :

- La génération du PDF prépare un document ; la transmission constitue la soumission au client. Pour l’envoi manuel, Chloé confirme dans l’application que l’envoi a été effectué. Pour l’envoi automatique, conserver le résultat technique d’envoi séparément de la décision commerciale du client.
- Conserver la version de la proposition transmise et le motif de modification. Le client répond à la version qui lui est actuellement présentée ; une ancienne version ne peut plus être acceptée après une contre-proposition.
- Un devis accepté ou refusé n’est pas modifiable par le client. La révision après acceptation et la négociation après refus ne sont pas décrites dans le sujet et ne sont pas ajoutées au parcours initial.
- Un refus du devis ne supprime ni la fiche client ni l’historique du projet. L’événement reste à traiter par Chloé ; il n’est pas automatiquement déclaré accepté ou en cours.

## États de l’événement

Les statuts « brouillon », « accepté », « en cours », « terminé » et « annuler » apparaissent dans l’énoncé (p. 7, 11–12). L’interface utilisera « annulé » pour ce dernier, par choix de rédaction. Le sujet n’énumère pas toutes les transitions ; celles retenues ci-dessous complètent le parcours.

| État de départ | Déclencheur | État d’arrivée | Justification |
|---|---|---|---|
| Aucun | Chloé crée l’événement associé à la proposition | Brouillon | État initial choisi pour éviter une publication prématurée |
| Brouillon | Le client accepte le devis associé | Accepté | Synchronisation choisie entre acceptation commerciale et projet ; statut événement accepté prévu p. 12 |
| Accepté | Chloé lance l’organisation | En cours | Mise en cours après acceptation prévue p. 12 |
| En cours | Chloé confirme la fin de l’événement | Terminé | Statut prévu p. 12 ; action explicite de clôture retenue |
| Brouillon, accepté ou en cours | Chloé annule le projet | Annulé | Statut prévu p. 12 ; états de départ autorisés choisis |

Décisions de conception : les retours vers un état antérieur et la réouverture d’un événement terminé/annulé ne sont pas prévus dans la première version. La date de fin seule ne termine pas automatiquement le projet. L’annulation de l’événement ne réécrit pas la décision commerciale historique du devis ; aucun processus de remboursement ou de facturation n’est ajouté, car ces fonctions ne sont pas décrites dans l’énoncé.

Les changements de statut d’un événement sont journalisés avec identifiant, ancien et nouveau statut, conformément à la page 13.

## Publication avis et notifications

La visibilité publique est indépendante de l’avancement commercial. Un événement ne peut apparaître sur la page publique que si le client a donné son accord et si son statut n’est pas brouillon (p. 7). Le prix n’est pas affiché. Le sujet ne demande pas d’exclure automatiquement les événements terminés ou annulés ; toute règle supplémentaire devra être justifiée.

Un avis porte sur un événement du client, une fois celui-ci terminé (p. 10). Il reste non public avant validation par un employé ou administrateur (p. 11). Le nom technique de l’état initial de modération sera un choix de données, par exemple « en attente ».

| Déclencheur | Destinataire et contenu | Obligation |
|---|---|---|
| Demande initiale enregistrée | Agence : informations de la demande pour prise de contact | p. 8 |
| Qualification impossible | Prospect : message de Chloé expliquant l’échec | p. 9 |
| Transmission automatisée du devis | Client : PDF de proposition | p. 9 ; téléchargement/envoi manuel aussi prévu |
| Création automatique de compte | Client : mot de passe par défaut et informations d’accès nécessaires | Mot de passe par courriel exigé p. 10 ; contenu d’accompagnement à concevoir |
| Acceptation du devis | Innov’Events : notification de l’acceptation | p. 10 |
| Inscription volontaire | Utilisateur : courriel de confirmation | p. 5 ; parcours de compte connexe |
| Mot de passe oublié | Utilisateur : mot de passe généré avec changement obligatoire à la connexion suivante | p. 6 ; parcours de compte connexe |

Le sujet ne prescrit pas de courriel supplémentaire pour chaque refus, demande de modification, note ou changement de statut. Ces actions restent visibles dans les espaces prévus. Aucune notification supplémentaire n’est considérée obligatoire dans cette conception.

Les journaux requis pour ce parcours sont : création/modification/suppression d’un client, création d’un événement, changement de statut d’événement et génération PDF ; les connexions réussies/échouées sont également journalisées (p. 12–13). Leur structure détaillée figure dans l’analyse des exigences.

## Cas d’échec à prendre en compte

Ces règles techniques sont proposées pour éviter les pertes et incohérences ; elles ne sont pas des fonctionnalités déjà réalisées.

- Échec d’un courriel : conserver la demande ou la proposition enregistrée et une information d’échec ; prévoir une reprise de l’envoi sans créer un deuxième dossier.
- Échec de génération du PDF : conserver le brouillon et afficher l’erreur ; ne pas présenter la proposition comme transmise.
- Double clic sur conversion, envoi ou acceptation : contrôler l’état courant pour éviter les doubles opérations.
- Réponse à une version obsolète : refuser la décision et inviter à consulter la version courante.
- Demande de modification sans motif ou réponse sur le devis d’un autre client : refuser l’action côté serveur.

## Scénarios de validation à implémenter

| ID | Parcours | Résultat attendu |
|---|---|---|
| PAR-01 | Demande complète puis qualification positive | Prospect à contacter, notification, conversion avec reprise des données et événement brouillon |
| PAR-02 | Demande incomplète | Champs invalides signalés, aucune demande complète créée |
| PAR-03 | Qualification négative | État échoué et courriel au prospect ; aucune acceptation ni événement lancé |
| PAR-04 | Préparation et transmission à un client sans compte | Compte rattaché, PDF disponible, devis en étude côté client |
| PAR-05 | Demande de modification motivée puis contre-proposition | État modification puis étude côté client ; ancienne proposition non décidable |
| PAR-06 | Acceptation puis organisation et fin | Devis accepté, notification agence, événement accepté puis en cours puis terminé |
| PAR-07 | Refus de proposition | Devis refusé, historique conservé, événement non lancé automatiquement |
| PAR-08 | Évaluation après événement terminé | Avis non public jusqu’à validation, puis visible sur la page avis |
| PAR-09 | Événement sans accord ou brouillon | Aucune exposition publique, y compris par accès direct |
| PAR-10 | Échec d’envoi, double clic ou réponse à une ancienne version | Pas de perte de données, de doublon commercial ni de décision sur une version périmée |

Ces scénarios sont préparés, pas exécutés. Le détail des calculs et les tests de sécurité seront complétés dans les tâches correspondantes.

## Conservation et bilan de la tâche

Fichier de référence : `docs/conception/parcours-prospect-devis-evenement.md`, enregistré sur la branche `dev`. Il alimente le dossier de conception, les futurs diagrammes de séquence et de cas d’utilisation, le modèle de données et les tests fonctionnels.

Travail réalisé : parcours principal, branches de refus/modification/échec, séparation des états de demande/devis/événement, acteurs, courriels et traces attendus, décisions de conception et scénarios préparés. La vérification consiste à relire ces règles contre les pages sources et la matrice des droits, puis à enregistrer le document et le bilan dans la tâche GitHub. Les choix technologiques ne sont pas fixés par ce document.

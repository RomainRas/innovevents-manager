# Bilan de couverture des écrans
Version du 3 novembre 2026 — tâche [#5](https://github.com/RomainRas/innovevents-manager/issues/5), toujours ouverte.

## Objet et sources
Comparer les représentations sauvegardées aux besoins de l’application, puis fixer la suite du travail.
Sources relues : [énoncé](../enonce/enonce-ecf-cda.pdf), pages 4 à 12, 14 et 16 ; [notice des évaluations](../enonce/notice-evaluations-studi.pdf). Compléments : [analyse des exigences](analyse-exigences.md) et [utilisateurs et droits](utilisateurs-et-droits.md).

La page 16 demande trois wireframes et trois maquettes pour chacun des supports web et mobile, une charte et un schéma d’enchaînement. Elle demande aussi des diagrammes de cas d’utilisation et de séquence. Les besoins recensés doivent couvrir l’ensemble du cahier des charges. Elle n’impose pas une maquette détaillée de chaque page.

Le présent bilan est documentaire : inventaire des fichiers et rapprochement avec le sujet. Il ne constitue ni une nouvelle revue visuelle de tous les écrans, ni un test de l’application.

## Représentations disponibles
Les six wireframes et les six maquettes principales existent en première version. Les états complémentaires ne sont pas comptés comme de nouvelles maquettes principales.

| Ensemble | Preuve et état |
| --- | --- |
| Web : demande de devis | [Maquette et wireframe associé](maquette-web-01-demande-devis.md) ; [quatre états complémentaires](etats-demande-devis.md) |
| Web : préparation du devis | [Maquette et wireframe associé](maquette-web-02-preparation-devis.md) ; brouillon incomplet représenté |
| Web : consultation du devis | [Maquette et wireframe associé](maquette-web-03-consultation-devis.md) ; [dix extraits de décisions et incidents](etats-decisions-devis.md) |
| Mobile : événements à venir | [Maquette et wireframe associé](maquette-mobile-01-evenements-a-venir.md) |
| Mobile : fiche événement | [Maquette, saisie de note et wireframe associé](maquette-mobile-02-fiche-evenement.md) ; [six états de note](etats-note-mobile.md) |
| Mobile : fiche client | [Maquette et wireframe associé](maquette-mobile-03-fiche-client.md) |
| Fondations graphiques | [Charte](charte-graphique.md), proposition bleu nuit, turquoise, corail et Inter ; pas une identité officielle fournie |
| Navigation sélectionnée | [Enchaînements web et mobile](enchainement-ecrans.md), statiques ; pas encore un plan de toutes les pages |

Figma conserve les sources modifiables ; les SVG sont sauvegardés dans docs/maquettes. Les anciennes mentions datées de « 5 sur 6 » décrivent des étapes antérieures : ce bilan donne l’état actuel. Aucune validation finale de l’ensemble n’est enregistrée.

## Couverture des fonctions et pages du projet
« À concevoir » signifie qu’aucune représentation dédiée n’est recensée dans les livrables actuels. Une fonctionnalité décrite dans l’analyse n’est pas pour autant dessinée ou développée. Les regroupements ci-dessous sont une organisation proposée, pas un découpage de pages imposé.

| Périmètre du sujet | Pages | Couverture visuelle actuelle et reste à faire |
| --- | --- | --- |
| Menu commun et accueil | 4 | Menu représenté sur les écrans web ; accueil, présentation, images et adaptation du menu aux petits écrans à concevoir |
| Création de compte et confirmation par mail | 5 | À concevoir : formulaire, règles de mot de passe et retour après création |
| Connexion et retour à l’action demandée | 5–6 | À concevoir, avec erreurs et accès selon le rôle |
| Mot de passe oublié et changement à la prochaine connexion | 6 | À concevoir selon le mécanisme décrit dans le sujet |
| Accessibilité | 6 | Fondations de contraste et de lisibilité préparées ; focus, ordre clavier, structure sémantique et alternatives des images à prévoir puis tester |
| Mentions légales | 6–7 | Accès prévu ; page et informations de l’éditeur/hébergeur à compléter sans inventer leurs valeurs |
| Contact | 7 | À concevoir ; nom d’utilisateur facultatif, email obligatoire si ce nom n’est pas fourni, titre et description |
| Événements publics et détail | 7 | À concevoir : consentement, exclusion des brouillons, absence de prix, filtres date/type/thème, image et lien Prestations et Devis |
| Demande de devis | 8 | WEB01 et quatre états ; administration du message de remerciement modifiable à prévoir |
| Prospects et qualification | 8–9 | Parcours décrit ; liste, recherche du prospect, échec avec message et conversion en client à concevoir |
| Préparation et transmission du devis | 9–10 | WEB02 partiel : brouillon ; version complète, génération PDF, téléchargement et envoi automatique à compléter |
| Consultation et réponse du client | 10 | WEB03 et décisions représentés ; liste de tous ses devis et suivi global à concevoir |
| Profil, suppression de compte et évaluation | 10 | À concevoir : modification des informations, demande de suppression totale et avis après fin de l’événement |
| Widget client des trois prochains événements | 10 | Présent dans WEB03 ; absence d’événement à représenter |
| Clients et événements côté employé | 11 | Listes, recherche, historique et fiches web à concevoir ; les fiches mobiles ne les remplacent pas |
| Notes collaboratives et tâches assignées | 11 | Ajout de note mobile représenté ; création/modification/suppression web et changement du statut des tâches assignées à concevoir |
| Modération et page publique des avis | 4, 11 | À concevoir : validation/refus et publication des seuls avis validés |
| Tableau de bord administrateur | 11 | À concevoir : trois prochains événements, cinq dernières notes et indicateurs |
| Gestion administrateur | 11–12 | À concevoir : clients, événements, notes globales, comptes clients/employés, suspension/suppression ; pas de création d’administrateur dans l’application |
| Gestion commerciale et contre-proposition | 9–12 | Préparation et parcours documentés ; liste de gestion, repérage des devis acceptés et reprise d’une demande de modification à concevoir |
| Consultation du journal des actions | 12–13 | Besoin analysé ; interface de consultation à concevoir, journalisation à développer |
| Consultation mobile et contact | 14 | Trois écrans représentés ; liste sans limite artificielle de trois résultats, coordonnées du client actionnables dans la conception |
| Note rapide mobile | 14 | Saisie et six états représentés ; clavier, conservation du texte et enregistrement à développer/tester |
| CGU et CGV mobile | 14 | Liens présents ; pages et contenu à préparer |

## Compléments prioritaires sur les écrans sélectionnés
Les états d’attente, d’échec, de confirmation et les protections contre les doubles actions sont des choix de conception pour rendre les parcours utilisables. Le sujet ne prescrit pas un nombre de planches d’états.

1. **Terminer WEB02** : devis complet prêt à générer, champs incohérents, génération en cours/échec, PDF disponible, envoi en cours/réussi/échoué. Distinguer téléchargement pour envoi manuel et envoi par la plateforme. Ne pas inventer un taux de TVA, une devise ou une règle d’arrondi comme exigence du sujet.
2. **Regrouper les états communs** : listes vides, chargement, échec de lecture, session expirée et accès refusé. Les appliquer aux écrans concernés sans produire une planche identique par page.
3. **Compléter les cas de contact mobile** : coordonnées absentes ou application externe indisponible. Conserver la distinction entre adresse du client et lieu de l’événement.
4. **Vérifier les petits écrans web et le focus** : les trois écrans de l’application mobile ne prouvent pas l’adaptation du site web. Prévoir également le résultat réseau incertain du formulaire de demande.
5. **Relire et préparer les exports** : cohérence des rôles, libellés, champs, transitions, messages, lisibilité et correspondance wireframe/maquette. Corriger puis exporter les versions retenues.

Ces étapes n’impliquent pas de dessiner chaque combinaison possible. Un composant commun et une règle documentée peuvent couvrir plusieurs écrans.

## Suite pour l’ensemble de l’application
Après stabilisation des six écrans représentatifs :
- Définir le plan des pages publiques et de l’authentification, puis leurs wireframes utiles.
- Définir les listes, fiches et actions des espaces client, employé et administrateur. Réutiliser les modèles de formulaire et de tableau.
- Concevoir en détail les pages présentant une interaction nouvelle ou un risque d’incompréhension ; documenter les pages simples sans multiplier les maquettes décoratives.
- Réaliser les diagrammes de cas d’utilisation et de séquence, l’architecture et le modèle de données, en coordination avec #6.
- Préparer l’environnement, développer, tester et déployer. Les maquettes ne constituent pas une implémentation ; aucune base de données installée ni fonctionnalité exécutée n’est attestée par ce bilan.

## Sauvegarde, rendu et clôture
Ce bilan est enregistré sur dev et référencé dans #5. Il sert de plan de travail et de justification de la couverture des besoins ; il ne remplace pas les visuels du dossier.

Pour le rendu, utiliser la copie fournie conformément à la notice. Les exports lisibles seront intégrés au dossier ou joints selon les possibilités de dépôt. La notice prévoit la fusion en un PDF si plusieurs fichiers doivent être remis dans un seul espace. Les formats et tailles réellement acceptés doivent être vérifiés sur la plateforme ; ils ne sont pas établis ici.

La tâche #5 reste ouverte jusqu’à la relecture et validation des douze représentations, de la charte et de l’enchaînement, puis la sauvegarde des exports retenus et du bilan de clôture. Les SVG de travail sont déjà disponibles ; les exports finaux restent à préparer. Les autres pages et les diagrammes doivent être suivis explicitement dans la suite du projet, sans prétendre que #5 clôture toute la conception.

**Prochaine action : compléter la préparation du devis, sa génération PDF et les retours de transmission.**

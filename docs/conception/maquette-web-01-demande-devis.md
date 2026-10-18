# Maquette web 01 - Demande de devis

Version du 18 octobre 2026. Tâche [#5](https://github.com/RomainRas/innovevents-manager/issues/5).

## Travail réalisé

Première maquette détaillée de l’interface publique de demande de devis, construite à partir du wireframe existant.

- [Maquette modifiable Figma](https://www.figma.com/design/eAJJZyMSjoOff7mA1pNpCU?node-id=41-286).
- [Export SVG](../maquettes/web-01-demande-devis.svg).
- [Wireframe et règles du formulaire](wireframe-web-01-demande-devis.md).
- [Charte graphique](charte-graphique.md).

Le cadre mesure 1440 × 1634 pixels. C’est une représentation statique, sans traitement de formulaire ni navigation fonctionnelle.

## Exigences conservées

Les dix champs obligatoires de la page 8 de l’énoncé sont conservés : entreprise, nom, prénom, e-mail, téléphone, lieu, type d’événement, date, nombre de participants et description du besoin.

Le menu commun comprend Accueil, Événements, Avis, Contact, Demande de devis et Connexion. Les mentions légales sont accessibles dans le pied de page. L’envoi ne nécessite pas d’inscription préalable.

Le panneau Et ensuite explique la prise de contact, puis la proposition si le projet peut être pris en charge. Il ne promet ni acceptation automatique du projet ni délai non fourni par le sujet.

## Direction artistique appliquée

- Introduction et panneau d’accompagnement en bleu nuit.
- Action Envoyer ma demande et rubrique active en turquoise.
- Accroche et repères corail #F3A48B pour apporter de la chaleur à la page publique.
- Formulaire blanc sur fond clair, sections et libellés visibles.
- Inter, styles de la charte et composants Simple Design System conservés en instances.
- Boutons de 48 pixels de haut ; champs de saisie visuellement distincts.

L’accroche « Vos idées, notre énergie » est une proposition éditoriale du projet, pas un slogan fourni dans le sujet. L’identité n’est pas présentée comme la charte officielle d’une entreprise réelle.

La page ne contient pas d’image d’ambiance à ce stade. Les images restent à préparer pour l’accueil et les présentations d’événements ; aucun visuel fictif n’est présenté comme une réalisation réelle.

## États et comportement à développer

Les règles décrites dans le document du wireframe restent applicables :
- Signaler les champs manquants et l’e-mail invalide, conserver la saisie.
- Empêcher les soumissions répétées pendant l’envoi.
- Afficher le message de remerciement après enregistrement effectif.
- Conserver la demande si seul le courriel de notification échoue.
- Prévoir un focus visible, une navigation clavier cohérente et des erreurs compréhensibles.

Cette version montre l’état initial. Les variantes visuelles erreur, chargement, confirmation et focus ne sont pas encore dessinées. Les règles documentées ne sont pas des fonctions déjà développées.

## Vérifications

- Présence des dix champs contrôlée dans la structure Figma.
- Menu commun et mentions légales présents.
- Correction de la largeur des boutons après changement de typographie.
- Capture complète relue après correction : pas de bouton tronqué ni de texte coupé constaté.
- Export SVG préparé ; contenus éditables conservés.

Aucun test fonctionnel, serveur, clavier ou mobile n’a été exécuté. La maquette mobile du projet correspond à l’application de Chloé et José ; elle ne remplace pas le travail responsive du site web.

## Sauvegarde et rendu

Source modifiable dans Figma ; SVG et explication sauvegardés sur dev. Les wireframes d’origine restent distincts. Pour le rendu, préparer un export PDF ou des images lisibles intégrées au dossier, selon le format accepté par la plateforme.

Avancement : six wireframes principaux en première version ; une maquette web sur trois et aucune maquette mobile sur trois. La tâche #5 reste ouverte. Prochaine maquette : préparation du devis par Chloé.

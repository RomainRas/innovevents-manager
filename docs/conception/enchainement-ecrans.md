# Enchaînement des écrans web et mobile

Tâche [#5](https://github.com/RomainRas/innovevents-manager/issues/5).

## Livrables et périmètre

Deux planches éditables sont présentes dans la page « 06 — Enchaînement des écrans » du fichier Figma :

- [Parcours web](https://www.figma.com/design/eAJJZyMSjoOff7mA1pNpCU?node-id=60-286), 1600 × 1360 pixels ; [export SVG](../maquettes/enchainement/enchainement-web.svg).
- [Parcours mobile](https://www.figma.com/design/eAJJZyMSjoOff7mA1pNpCU?node-id=60-287), 1600 × 1280 pixels ; [export SVG](../maquettes/enchainement/enchainement-mobile.svg).

Les schémas relient les six maquettes principales, leur état complémentaire de note et les étapes nécessaires à la compréhension du parcours. Ils ne constituent ni un inventaire complet des pages de l’application ni un prototype interactif.

## Sources utilisées

- [Énoncé ECF](../enonce/enonce-ecf-cda.pdf), page 16 pour l’enchaînement ; pages 8 à 10 pour le parcours commercial ; page 14 pour le mobile.
- [Parcours prospect, devis, événement](parcours-prospect-devis-evenement.md) et [matrice des droits](utilisateurs-et-droits.md), relus pour cette étape.
- Six wireframes et six maquettes existants ; règles de note et de contact documentées dans les fiches mobile.

Les choix déjà documentés restent des choix de conception : confirmation de l’envoi manuel, moment du rattachement du compte client, contrôle de la session mobile, retour après enregistrement, confirmation d’abandon d’une note et traitement des échecs.

## Repères vers les maquettes

| Repère | Écran | Acteur | Maquette |
| --- | --- | --- | --- |
| WEB01 | Demande de devis | Public | [Figma](https://www.figma.com/design/eAJJZyMSjoOff7mA1pNpCU?node-id=41-286) |
| WEB02 | Préparation du devis | Chloé, administratrice | [Figma](https://www.figma.com/design/eAJJZyMSjoOff7mA1pNpCU?node-id=47-364) |
| WEB03 | Consultation du devis | Client concerné | [Figma](https://www.figma.com/design/eAJJZyMSjoOff7mA1pNpCU?node-id=48-509) |
| MOB01 | Événements à venir | Chloé et José | [Figma](https://www.figma.com/design/eAJJZyMSjoOff7mA1pNpCU?node-id=53-349) |
| MOB02 | Fiche événement | Chloé et José | [Figma](https://www.figma.com/design/eAJJZyMSjoOff7mA1pNpCU?node-id=55-367) |
| MOB03 | Fiche client | Chloé et José | [Figma](https://www.figma.com/design/eAJJZyMSjoOff7mA1pNpCU?node-id=57-394) |
| MOB02-N | Saisie d’une note, état complémentaire | Chloé et José | [Figma](https://www.figma.com/design/eAJJZyMSjoOff7mA1pNpCU?node-id=55-392) |

Un contour de couleur désigne une maquette principale. Les blocs gris représentent des étapes, résultats, écrans intermédiaires, règles ou applications externes. L’état de saisie de note est également maquetté, avec un repère propre ; il n’augmente pas le nombre des six écrans principaux.

## Lecture du parcours web

1. Le prospect envoie WEB01. Après validation et enregistrement : remerciement, prospect à contacter et notification à l’agence. Les champs invalides doivent être corrigés avant de poursuivre.
2. Chloé qualifie le besoin. Si la demande est impossible, elle est marquée échouée et un message est envoyé ; sinon, conversion en client et accès à WEB02.
3. Chloé prépare la proposition et son PDF. La génération seule ne vaut pas transmission ; la confirmation de l’envoi manuel ou le traitement de l’envoi automatique suit les règles du parcours commercial.
4. Le client utilise son propre compte pour ouvrir WEB03. La flèche de transmission est un changement d’acteur, pas une navigation de la session de Chloé vers le compte du client. Le compte est créé et rattaché si nécessaire selon les règles documentées.
5. Depuis WEB03, le client accepte, demande une modification motivée ou refuse. L’acceptation notifie l’agence. Le refus termine la proposition sans lancer automatiquement l’événement.
6. Une demande de modification revient à Chloé. Elle reprend WEB02, transmet une contre-proposition, puis le client revient à WEB03 pour une nouvelle décision.

Les écrans de qualification, connexion, suivi et les variantes de décision ne sont pas déclarés réalisés par leur présence sous forme de blocs. Les effets métier, anciennes versions de devis, échecs de PDF ou de courriel et contrôles d’appartenance restent détaillés dans le parcours commercial.

## Lecture du parcours mobile

1. Une session interne autorisée donne accès à MOB01. Choisir un événement ouvre MOB02 ; le retour à la liste reste possible.
2. Le nom du client dans MOB02 ouvre MOB03 ; le retour conserve le contexte de l’événement.
3. Toucher une coordonnée ouvre le téléphone, la messagerie par défaut ou la cartographie. L’itinéraire concerne l’adresse postale du client, pas le lieu de l’événement. Les retours depuis les applications externes devront être testés sur appareil.
4. Ajouter une note ouvre MOB02-N. Enregistrer avec succès revient à MOB02 et confirme la sauvegarde. Annuler revient sans sauvegarder, avec confirmation d’abandon si nécessaire.
5. Note vide : validation refusée. Échec de sauvegarde : texte conservé. Coordonnée absente : aucune action active. Les variantes visuelles restent à compléter.
6. Les accès CGU et CGV sont conservés sur les trois écrans principaux. La réauthentification après expiration de session est un comportement de conception à développer.

La liste n’est pas limitée aux trois cartes illustratives de MOB01. L’ouverture d’une application externe ne déclenche pas automatiquement un appel ou l’envoi d’un courriel.

## Vérifications réalisées

- Relecture des acteurs et transitions contre les deux documents métier et les maquettes.
- Six repères principaux et l’état de note présents ; trois réponses au devis et reprise après modification représentées.
- Changement de session entre agence et client explicite ; absence d’accès client aux écrans mobiles internes.
- Deux planches relues visuellement après ajout des repères : aucune coupure de texte ni superposition gênante constatée.
- Web : 40 textes, 18 cadres internes et 18 vecteurs ; mobile : 29 textes, 12 cadres internes et 12 vecteurs.
- Police Inter contrôlée ; aucune image d’interface aplatie ; flèches et textes éditables.
- Deux SVG avec texte préparés pour sauvegarde.

Ces contrôles portent sur la conception statique. Aucun test de navigation, de droits, de sauvegarde ou d’application native n’a été exécuté.

## Sauvegarde, rendu et clôture

Les schémas Figma restent modifiables ; les deux SVG et ce bilan sont sauvegardés sur dev. Ils alimenteront le dossier de conception et les exports finaux adaptés aux modalités du dépôt.

Les diagrammes de cas d’utilisation et de séquence constituent d’autres livrables ; ces schémas d’enchaînement ne les remplacent pas.

Avant développement, compléter également la couverture des autres pages exigées : accueil, compte, connexion, mot de passe oublié, contact, événements publics, avis, mentions légales, espaces utilisateur/employé/administrateur et journalisation. Les six écrans sélectionnés ne réduisent pas le périmètre fonctionnel du projet.

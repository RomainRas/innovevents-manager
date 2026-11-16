# Charte graphique Innov’Events - proposition v1

Tâche [#5](https://github.com/RomainRas/innovevents-manager/issues/5).

## Sources et décision

L’[énoncé](../enonce/enonce-ecf-cda.pdf), pages 4, 6 et 16, a été relu directement : cohérence graphique, adaptation au support, accessibilité, palette et police sont attendues. Ces pages n’imposent pas de palette.

J’ai retenu une direction graphique qui associe bleu nuit et turquoise, pour une interface professionnelle destinée à l’événementiel d’entreprise. Il s’agit de l’identité conçue pour le projet ECF, sans prétendre reproduire une marque commerciale officielle.

- [Planche Figma modifiable](https://www.figma.com/design/eAJJZyMSjoOff7mA1pNpCU?node-id=35-388).
- [Export SVG](../maquettes/charte/charte-graphique-v1.svg).
- [Valeurs réutilisables](../maquettes/charte/fondations-v1.json).

## Palette

| Rôle | Valeur | Usage |
| --- | --- | --- |
| Bleu nuit | #142D43 | Identité, titres, focus |
| Turquoise | #087F8C | Action principale |
| Turquoise profond | #06616B | Survol |
| Encre | #172B3A | Texte courant |
| Gris ardoise | #526574 | Texte secondaire |
| Gris clair | #F4F7FA | Fond de page |
| Blanc | #FFFFFF | Surfaces et texte sur action foncée |
| Contour | #718391 | Champs et séparations |
| Succès | #216E4E | Retour positif avec libellé |
| Erreur | #B42318 | Erreur avec explication |
| Attention | #8A4B08 | Avertissement avec libellé |
| Accent corail | #F3A48B | Accroches et touches chaleureuses sur les pages publiques |

Les liens intégrés dans un texte seront soulignés. Un état ne repose jamais uniquement sur sa couleur. Les états désactivés et autres variantes seront détaillés lors des maquettes.

## Typographie

Une seule famille : Inter, déjà utilisée dans les wireframes.

| Style | Taille / interligne | Graisse |
| --- | --- | --- |
| Titre web | 36 / 44 px | Bold |
| Titre mobile | 28 / 36 px | Bold |
| Section | 24 / 32 px | Semi Bold |
| Sous-titre | 20 / 28 px | Semi Bold |
| Corps | 16 / 24 px | Regular |
| Libellé | 16 / 24 px | Semi Bold |
| Légende | 14 / 20 px | Regular |

Les paragraphes et champs conservent des libellés lisibles. Les dimensions ne constituent pas une obligation de bloquer le zoom ou les préférences de taille de texte.

## Règles communes

- Espacements : 8, 16, 24, 32 et 48 pixels.
- Rayon des cartes : 8 pixels.
- Zones tactiles d’au moins 48 pixels de haut, comme choix de conception.
- Une action principale mise en évidence par zone.
- Libellés au-dessus des champs ; les exemples ne les remplacent pas.
- Focus clavier proposé : contour bleu nuit de 2 pixels, séparé du contrôle par 2 pixels blancs.
- Conserver les erreurs près des champs et les informations saisies en cas d’échec.
- Adapter les mises en page au support et vérifier les contenus longs.

Le sujet exige également la navigation clavier, une structure sémantique et des alternatives pour les images informatives. Ces points seront à implémenter et tester ; une planche Figma ne les valide pas.

## Mesures de contraste

Calcul par luminance relative sRGB sur les valeurs hexadécimales opaques, avec le rapport (luminance claire + 0,05) / (luminance sombre + 0,05).

| Association | Rapport calculé |
| --- | --- |
| #087F8C / blanc | 4.75:1 |
| #06616B / blanc | 7.17:1 |
| #142D43 / blanc | 14.13:1 |
| #172B3A / blanc | 14.55:1 |
| #526574 / blanc | 6.05:1 |
| #216E4E / blanc | 6.17:1 |
| #B42318 / blanc | 6.57:1 |
| #8A4B08 / blanc | 6.79:1 |

Les mesures concernent ces paires seulement, sans transparence, image ou dégradé. Elles ne constituent pas un audit global d’accessibilité ni une attestation de conformité.

## Fondations Figma réalisées

- Deux collections locales : IE — Primitives et IE — Interface.
- 32 variables : 12 couleurs primitives, 14 rôles de couleur, 5 espacements et un rayon.
- 19 alias : 14 couleurs liées aux primitives et 5 espacements liés à la bibliothèque Simple Design System.
- Sept styles de texte locaux IE/.
- Un seul mode clair pour la proposition ; aucun mode sombre n’est annoncé.
- Noms CSS proposés dans le JSON et associés aux variables Figma. Ils ne sont pas encore implémentés dans une application.

La bibliothèque Simple Design System a été inspectée. Les espacements compatibles sont réutilisés ; les couleurs et styles locaux permettent de maîtriser l’identité proposée. Aucun composant ou écran de maquette n’est compté comme réalisé dans cette étape.

## Relecture et vérifications

Les six cadres principaux sont dans Figma : trois web et trois mobile, avec leurs sources et notes de conception sauvegardées.

La planche de charte a été contrôlée visuellement : aucune coupure ni chevauchement constaté. Les sept styles sont appliqués. Les 32 variables ont une syntaxe CSS ; aucune n’a une portée ALL_SCOPES. Les 19 alias ont été retrouvés.

Les wireframes restent en niveaux de gris. Cette charte prépare les futures maquettes sans transformer rétroactivement les wireframes en maquettes.

## Sauvegarde et suite

Figma reste la source modifiable. La planche SVG, les valeurs JSON et ce document sont sauvegardés sur dev. La planche ne remplace pas les six maquettes attendues dans la charte finale.

Première maquette web Demande de devis réalisée en v1. Il restera à formaliser l’enchaînement des maquettes, les diagrammes et les exports pour le rendu.

## Complément événementiel

À la suite des échanges sur l’identité de l’agence, l’accent corail #F3A48B apporte une touche chaleureuse aux pages publiques. Il complète le bleu nuit et le turquoise ; il ne remplace pas la couleur d’action principale. Sur fond corail, utiliser le texte bleu nuit, sans blanc. L’accent est ajouté aux variables Figma et à la planche. La première maquette de demande de devis applique ces règles sans image d’ambiance ; les images restent à préparer pour les écrans où elles servent le contenu.

## Version affiche (site web)

Pour donner plus de caractère à la vitrine, j’ai réalisé une maquette de la page d’accueil dans un style affiche : papier crème, aplats rouge, bleu et jaune, photos inclinées, titres condensés en majuscules ([maquette](../maquettes/accueil-affiche.webp)). J’ai ensuite décliné ce style sur tout le site web à partir d’une maquette par écran : en-tête et pied de page, pages publiques, inscription et connexion, espace client, espaces de l’équipe et de l’administration, jusqu’à la préparation et la transmission des devis. Les pages publiques gardent le décor d’affiche (feuilles déchirées, aplats, tickets) ; les écrans de travail en reprennent la palette et les titres en Anton sur des cartes claires plus sobres, pour rester lisibles. L’application mobile garde la charte bleu nuit et turquoise. Les couleurs par défaut du site (boutons, liens, contours) suivent désormais cette palette ; la charte bleu nuit et turquoise ci-dessus reste la première proposition.

| Rôle | Valeur | Usage |
| --- | --- | --- |
| Papier | #F4EFE4 | Fond de la page, étiquettes et boutons |
| Rouge | #C8221A | Bandeau d’ouverture, astérisques, décor |
| Bleu | #1D3FBF | Aplats « Nos univers » et sous la photo |
| Jaune | #FFD836 | Surlignages et bandeau d’appel au devis |
| Encre | #111111 | Titres et texte sur fond clair |

Titres : police Anton (licence SIL OFL), servie par le site lui-même (`frontend/public/fonts/`, 18 Ko, licence jointe) ; aucun appel à un service de polices extérieur. Le texte courant reste en Inter ou en police du système.

| Association | Rapport calculé |
| --- | --- |
| Blanc / #C8221A | 5.68:1 |
| Encre / #C8221A | 3.33:1, réservé aux très grands titres (plus de 40 px) |
| Blanc / #1D3FBF | 8.35:1 |
| Encre / #FFD836 | 13.6:1 |
| Encre / #F4EFE4 | 16.47:1 |
| #C8221A / #F4EFE4 | 4.95:1 |

Les trois photos d’ambiance (soirée dansante, séminaire, dîner de gala) sont réduites et converties en WebP (66 à 119 Ko) et chacune a un texte alternatif. Le ticket « Programme événement » est un décor masqué aux lecteurs d’écran. La page a été vérifiée à 1440 et 390 pixels de large, sans défilement horizontal, et le test des pages publiques passe.

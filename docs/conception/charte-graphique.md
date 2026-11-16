# Charte graphique Innov’Events

Tâche [#5](https://github.com/RomainRas/innovevents-manager/issues/5).

## Sources et décision

L’[énoncé](../enonce/enonce-ecf-cda.pdf), pages 4, 6 et 16, a été relu directement : cohérence graphique, adaptation au support, accessibilité, palette et police sont attendues. Ces pages n’imposent pas de palette.

Pour une agence événementielle, j’ai voulu une identité qui évoque l’affiche de spectacle : papier crème, aplats de couleurs franches rouge, bleu et jaune, photos inclinées, titres condensés en majuscules. Il s’agit de l’identité conçue pour le projet ECF, sans prétendre reproduire une marque commerciale officielle.

- [Planche de la charte](../maquettes/charte/charte-graphique.webp) : palette, typographie, composants et contrastes.
- [Maquette de la page d’accueil](../maquettes/accueil-affiche.webp).
- [Maquettes de tous les écrans, site et application mobile](maquettes-affiche.md).

## Palette

| Rôle | Valeur | Usage |
| --- | --- | --- |
| Papier | #F4EFE4 | Fond de page, étiquettes et boutons clairs |
| Carte | #FFFDF8 | Surfaces des cartes, champs de saisie |
| Rouge | #C8221A | Action principale, bandeau d’ouverture, soulignés, décor |
| Bleu | #1D3FBF | Liens, focus clavier, aplats secondaires |
| Jaune | #FFD836 | Surlignage des titres, appel au devis, alertes hors ligne |
| Encre | #111111 | Titres, texte courant, contours, ombres portées |
| Texte secondaire | #3D3A33 | Dates, lieux, aides sous les champs |
| Succès | #0B6B3A | Retour positif avec libellé |
| Erreur | #A31B14 | Erreur avec explication (fond #FBE4E1 pour les encadrés) |

Les liens intégrés dans un texte sont soulignés. Un état ne repose jamais uniquement sur sa couleur : les statuts ont un libellé, les erreurs un texte.

## Typographie

| Usage | Police | Style |
| --- | --- | --- |
| Titres, boutons d’action, nom de la marque | Anton (licence SIL OFL) | Majuscules, interlettrage léger, surlignage jaune pour les titres de page |
| Texte courant, formulaires | Inter ou police du système | Regular et Semi Bold |
| Citations, sous-titres de feuille | Georgia | Regular et italique |

| Style | Taille |
| --- | --- |
| Titre de page web | 40 à 72 px selon la largeur |
| Titre mobile | 32 px |
| Titre de section | 28 à 40 px |
| Corps | 16 à 17 px |
| Légende | 14 px |

Anton est servie par le site et par l’application mobile eux-mêmes (`frontend/public/fonts/`, `mobile/public/fonts/`, 18 Ko, licence jointe) : aucun appel à un service de polices extérieur, et la police reste disponible hors ligne dans l’application mobile. Les tailles ne bloquent ni le zoom ni les préférences de taille de texte.

## Règles communes

- Pages publiques : décor d’affiche (feuilles déchirées, aplats inclinés, tickets, étoiles), masqué aux lecteurs d’écran.
- Écrans de travail (équipe, administration, client) : même palette et titres en Anton, sur des cartes claires plus sobres pour rester lisibles.
- Cartes : contour encre de 2 px, coins peu arrondis, ombre portée décalée sur les éléments cliquables.
- Boutons d’action principaux rouges à texte clair ; boutons secondaires sur fond carte à contour encre.
- Espacements : 8, 16, 24, 32 et 48 pixels.
- Zones tactiles d’au moins 48 pixels de haut.
- Libellés au-dessus des champs ; les exemples ne les remplacent pas.
- Focus clavier visible : contour bleu de 3 px décalé de 2 px.
- Erreurs près des champs, informations saisies conservées en cas d’échec.
- Mises en page adaptées au support, vérifiées à 1440 et 390 pixels de large, sans défilement horizontal.

Application mobile : bandeau noir liséré de rouge avec le nom en Anton jaune, fond papier, titres surlignés de jaune, cartes à contour noir et ombre portée, boutons d’action rouges, pastilles de contact rouge, bleue et noire ; l’icône de l’application reprend le rouge, le crème et le jaune.

Le sujet exige également la navigation clavier, une structure sémantique et des alternatives pour les images informatives : elles sont implémentées et vérifiées par les tests de navigateur.

## Mesures de contraste

Calcul par luminance relative sRGB sur les valeurs hexadécimales opaques, avec le rapport (luminance claire + 0,05) / (luminance sombre + 0,05).

| Association | Rapport calculé |
| --- | --- |
| Encre / papier #F4EFE4 | 16.47:1 |
| Encre / carte #FFFDF8 | 18.58:1 |
| Texte secondaire #3D3A33 / papier | 9.89:1 |
| Bleu #1D3FBF / papier | 7.28:1 |
| Blanc / rouge #C8221A | 5.68:1 |
| Blanc / bleu #1D3FBF | 8.35:1 |
| Encre / jaune #FFD836 | 13.6:1 |
| Rouge #C8221A / papier | 4.95:1 |
| Jaune / encre (nom de la marque sur le bandeau mobile) | 13.6:1 |
| Erreur #A31B14 / carte | 7.58:1 |
| Succès #0B6B3A / carte | 6.5:1 |

Les mesures concernent ces paires seulement, sans transparence, image ou dégradé. Elles ne constituent pas une attestation de conformité globale.

## Images

Les photos d’ambiance et des événements sont réduites et converties en WebP, et chacune a un texte alternatif. Les éléments de décor sont masqués aux lecteurs d’écran.

## Wireframes

Les wireframes restent en niveaux de gris : ils fixent la structure, les contenus et les états de chaque écran, et la charte s’applique ensuite dans les maquettes.

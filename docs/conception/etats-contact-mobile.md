# États de contact mobile
Complément de MOB03 ; tâche [#5](https://github.com/RomainRas/innovevents-manager/issues/5).

## Réalisation et fichiers
- [Planche Figma](https://www.figma.com/design/eAJJZyMSjoOff7mA1pNpCU?node-id=78-382), 1362 × 1128, avec six extraits de 390 pixels de large.
- [SVG](../maquettes/etats/mobile-03-etats-contact.svg).
- [Fiche client principale](maquette-mobile-03-fiche-client.md).

Trois cas de coordonnées absentes et trois cas d’ouverture impossible sont représentés. Les messages et boutons réutilisent les composants, styles et variables existants. Ces extraits complètent la fiche client ; ils ne comptent pas comme six maquettes principales supplémentaires.

## Sources et choix
La page 14 de l’[énoncé](../enonce/enonce-ecf-cda.pdf) demande les coordonnées actionnables depuis la fiche client : téléphone, courriel et itinéraire. Les messages d’indisponibilité et la copie comme solution de remplacement sont des choix de conception, non des fonctionnalités explicitement imposées.

Téléphone et email sont obligatoires dans la demande de devis (page 8). Les états absents servent de protection face à une donnée incomplète ; ils n’autorisent pas à rendre ces champs facultatifs dans le formulaire. L’adresse postale du client doit être collectée dans la gestion client ; elle ne doit pas être remplacée automatiquement par le lieu de l’événement.

## Six états représentés
| État | Présentation et action |
| --- | --- |
| Téléphone absent | Téléphone non renseigné ; retour aux autres coordonnées |
| Courriel absent | Courriel non renseigné ; retour aux autres coordonnées |
| Adresse absente | Adresse non renseignée ; aucun itinéraire proposé |
| Téléphone impossible à ouvrir | Message, numéro visible et action Copier le numéro |
| Messagerie impossible à ouvrir | Message, email visible et action Copier le courriel |
| Cartographie impossible à ouvrir | Message, adresse du client visible et action Copier l’adresse |

Le retour à la fiche est une action de sortie de l’extrait/message. L’interface complète doit conserver la navigation et le contexte du client. Un champ absent ne doit pas rester un lien de contact actif.

## Comportements à développer
- Vérifier la présence et la validité des coordonnées avant de construire un lien vers une application externe. Si une valeur est présente mais invalide, utiliser un message distinct, par exemple « Numéro à vérifier », sans affirmer qu’elle est absente. Ce cas est décrit ici, sans planche supplémentaire.
- Ouvrir le composeur téléphonique, la messagerie ou la cartographie ; ne pas appeler ni envoyer automatiquement.
- Afficher l’échec seulement lorsque la plateforme permet de le détecter. L’absence de retour ne suffit pas à conclure que l’application est indisponible.
- Ne pas promettre un contrôle de réception du courriel, de réalisation de l’appel ou de succès de l’itinéraire.
- Copier la valeur affichée uniquement après l’action explicite. Après succès, annoncer « Numéro copié », « Adresse e-mail copiée » ou « Adresse copiée ».
- Si la copie échoue, annoncer « Copie impossible. Sélectionnez le texte pour le copier. » et permettre la sélection de la coordonnée. Ces retours sont documentés, non dessinés comme nouvelles planches.
- Conserver la fiche au retour de l’application externe, sous réserve de la validité de la session et des droits.
- Un problème réseau dans l’application externe ne doit pas être annoncé comme un échec confirmé par Innov’Events sans information fiable.
- Prévoir les retours à la ligne pour les coordonnées longues. Les valeurs entre crochets sont des repères, pas des contacts réels.

## Vérification et limites
Planche complète relue visuellement : aucune coupure ou superposition constatée. 36 textes, 8 cadres internes, 12 instances ; police Inter contrôlée et aucune image d’interface aplatie. Les boutons conservent le style turquoise de la fiche mobile. Le SVG conserve les textes.

Aucun appel, courriel, itinéraire ou copie dans le presse-papiers n’a été exécuté. La détection des applications, la navigation de retour, la sélection des textes, les annonces accessibles et les longues coordonnées devront être testées sur appareil. Aucun fonctionnement hors connexion n’est promis.

## Rendu et suivi
La source modifiable reste dans Figma ; le SVG et cette explication sont sauvegardés sur dev pour le dossier de conception. Les exports finaux seront préparés après validation d’ensemble.

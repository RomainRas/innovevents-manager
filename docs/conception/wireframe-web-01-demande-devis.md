# Wireframe web 01 - Demande de devis

Version du 18 octobre 2026. Tâche GitHub : [#5](https://github.com/RomainRas/innovevents-manager/issues/5).

## Ce qui est réalisé

Le premier wireframe web est dessiné dans Figma et relu visuellement. Il représente le formulaire public de demande de devis, avant l’étude du besoin par Chloé.

- [Ouvrir l’écran modifiable dans Figma](https://www.figma.com/design/eAJJZyMSjoOff7mA1pNpCU?node-id=7-24).
- [Ouvrir les notes et états associés](https://www.figma.com/design/eAJJZyMSjoOff7mA1pNpCU?node-id=9-80).
- [Export SVG de l’écran](../maquettes/wireframes/web-01-demande-devis.svg).
- [Export SVG des notes](../maquettes/wireframes/web-01-demande-devis-notes.svg).

L’écran mesure 1440 × 1437 pixels. Il s’agit d’un wireframe en niveaux de gris. La palette et la police définitives restent à choisir lors de la charte graphique.

## Consignes utilisées

Source principale : [énoncé ECF](../enonce/enonce-ecf-cda.pdf), page 8, section « Demande de devis ». Cette page a été relue directement pour ce travail.

La navigation commune vient de la page 4 ; l’accessibilité et les mentions légales des pages 6 et 7. La page 16 demande trois wireframes et trois mockups pour chacun des supports web et mobile.

Le sujet fournit des exemples de types d’événements : séminaire, conférence, soirée d’entreprise, autre. Il ne fixe pas une liste exhaustive. Un champ libre avec des exemples est retenu ici.

## Contenu du formulaire

Les dix champs suivants sont obligatoires dans le sujet. Un astérisque accompagne chaque libellé et une phrase explique ce repère au-dessus du formulaire.

| Partie | Champ |
| --- | --- |
| Vos coordonnées | Nom de l’entreprise |
| Vos coordonnées | Nom |
| Vos coordonnées | Prénom |
| Vos coordonnées | Adresse e-mail |
| Vos coordonnées | Téléphone |
| Votre événement | Lieu |
| Votre événement | Type d’événement envisagé |
| Votre événement | Date souhaitée |
| Votre événement | Nombre de participants estimé |
| Votre événement | Votre besoin en quelques mots |

L’action principale est « Envoyer ma demande ». Le menu comprend Accueil, Événements, Avis, Contact, Demande de devis et Connexion pour un visiteur non connecté. Le pied de page comporte Mentions légales.

## Choix de conception

Ces choix organisent l’interface ; ils ne sont pas présentés comme des prescriptions supplémentaires de l’énoncé.

- Regrouper les champs en deux parties pour faciliter la lecture.
- Aligner nom/prénom, e-mail/téléphone et type/date sur deux colonnes pour cette version web.
- Garder les libellés visibles au-dessus des champs ; les exemples ne les remplacent pas.
- Utiliser un champ libre pour le type d’événement et une zone de texte pour le besoin.
- Expliquer les étapes suivantes dans un encadré « Et ensuite ? ». La préparation d’un devis dépend de la possibilité de prendre en charge le projet.
- Garder le formulaire accessible sans imposer une création de compte.
- Utiliser provisoirement Inter et les composants neutres de la bibliothèque Figma Simple Design System. Les champs et boutons restent des instances modifiables, les titres et paragraphes des textes éditables.

## Confirmation et erreurs

Le panneau de notes est une documentation de cet écran. Il ne compte pas comme un deuxième wireframe et ne fait pas partie de l’interface montrée au visiteur.

Après l’enregistrement effectif, afficher le message de remerciement modifiable demandé par le sujet. L’exemple fourni page 8 est : « Merci pour votre demande. Chloé vous recontactera dans les plus brefs délais pour discuter de votre projet. »

Les comportements suivants sont proposés pour le développement :

- Champ manquant ou e-mail invalide : message explicite près du champ, conservation des autres valeurs et focus sur la première erreur.
- Enregistrement impossible : message d’échec et possibilité de réessayer en conservant les valeurs.
- Envoi en cours : empêcher les soumissions répétées.
- Échec du seul courriel à l’agence : conserver la demande enregistrée et permettre une reprise de la notification sans créer de doublon.

En arrière-plan, le sujet impose l’enregistrement dans la table prospects, le statut initial « à contacter » et un courriel à l’agence. Ces informations techniques ne sont pas affichées dans le formulaire public.

## Vérifications réalisées

- Les dix champs ont été rapprochés de la page 8 de l’énoncé.
- Les dix libellés comportent une indication obligatoire.
- Le menu commun, l’action d’envoi et les mentions légales sont présents.
- L’écran et ses notes ont été contrôlés par captures Figma après correction des hauteurs de texte : pas de chevauchement ni de texte coupé constaté.
- L’inspection du cadre trouve 89 éléments descendants, dont 45 textes et 19 instances ; aucune image aplatie de l’interface.
- La police des textes de l’écran a été contrôlée : Inter.

Ces contrôles portent sur la conception. Aucun test fonctionnel de formulaire, d’envoi de courriel, de validation serveur ou d’accessibilité clavier n’a encore été exécuté. Le wireframe est statique ; les interactions et le schéma d’enchaînement restent à réaliser.

## Sauvegarde et utilisation pour le rendu

Figma reste la source modifiable. Les deux exports SVG et cette explication sont sauvegardés sur la branche dev. Ils permettent de conserver une trace du travail et de préparer la partie conception du dossier.

Après les retours de Romain, les wireframes serviront de base aux maquettes. Des exports PDF ou PNG adaptés au rendu final seront préparés lorsque les écrans seront validés. Un lien Figma seul ne remplace pas les livrables à déposer.

## Avancement de la tâche

- Wireframes web : 1 version réalisée sur 3, à relire avec Romain.
- Wireframes mobile : 0 sur 3.
- Maquettes web et mobile : 0 sur 6.
- Charte graphique et enchaînement des écrans : à réaliser.

La tâche #5 reste ouverte. Prochaine étape proposée : le wireframe de préparation du devis par Chloé.

# Pages publiques — 14 novembre 2026

## Contenu

| Page | Adresse | Exigence de l'énoncé |
| --- | --- | --- |
| Accueil | `/` | Présentation de l'agence, images, bouton « Demander un devis » |
| Évènements | `/evenements-publics` | Événements commandés, accord du client, statut différent de brouillon, image et informations sans prix ; filtres par période, type et thème |
| Détail d'un événement | `/evenements-publics/:id` | Section « Prestations et Devis » qui renvoie vers la demande de devis |
| Avis | `/avis` | Avis validés par un employé |
| Contact | `/contact` | Nom d'utilisateur facultatif, titre, adresse électronique obligatoire sans nom d'utilisateur, description ; courriel envoyé à l'adresse générique de l'agence |
| Mentions légales, CGU, CGV, confidentialité | `/mentions-legales`, `/cgu`, `/cgv`, `/confidentialite` | Accessibles depuis le pied de page de toutes les pages |

Le menu présent sur toutes les pages contient Accueil, Évènements, Avis, Contact, Demande de devis et Se connecter (ou Mon espace et Se déconnecter).

## Sécurité et données

- **Vues SQL filtrées** (migration `011_public_pages.sql`) : `v_evenements_publics` ne contient que les événements non brouillons, rendus visibles par l'agence **et** dont le client a donné son accord ; aucune colonne client, montant ou preuve d'accord. `v_avis_publics` ne contient que les avis validés et réduit l'auteur au prénom et à l'initiale du nom. Le code des pages publiques ne lit que ces vues.
- Le retrait de l'accord ou de la visibilité masque immédiatement l'événement, sa fiche et son image (testé).
- Les lectures publiques n'ouvrent pas de session : aucun cookie n'est déposé chez un simple visiteur. L'image publiée est servie avec un en-tête `Content-Security-Policy: sandbox` et une mise en cache d'une heure.
- Filtres validés côté serveur (dates réelles, période dans le bon ordre, valeurs limitées en longueur) et requêtes préparées.
- Contact : jeton CSRF, limitation à 10 messages par adresse IP sur 15 minutes, champs contrôlés (longueur, caractères de contrôle). Le message est chiffré dans la file de courriels puis envoyé à `AGENCY_EMAIL` ; il n'est pas conservé ailleurs.

## Démonstration

Six événements fictifs publiés (conférence, séminaire, soirée, team building, atelier, lancement) et quatre avis validés. Leur accord de publication est explicitement marqué comme fictif. Les illustrations sont générées par `backend/bin/demo-images.php` (GD) : aucune photo tierce. Elles sont livrées dans l'image PHP (`backend/demo-images`, lecture seule) ; les images envoyées par Chloé restent dans le stockage privé.

## Accessibilité (RGAA)

Lien d'évitement « Aller au contenu », titre de page propre à chaque écran, navigation au clavier, structure de titres, listes et régions nommées, alternatives textuelles sur toutes les images, libellés de formulaire, erreurs reliées aux champs (`aria-describedby`) avec focus sur le premier champ en erreur, mise en page sans défilement horizontal à 390 px.

## Tests

- `tests/app/public-pages.py` : 31 contrôles HTTP et données (filtrage, absence de champs privés, brouillon et accord retiré invisibles, image, avis en attente invisible, contact : CSRF, champs obligatoires, adresse ou nom d'utilisateur, longueur, mise en file chiffrée).
- `tests/app/public-pages-browser.cjs` : 29 contrôles navigateur à 1 280 et 390 px (menu, lien d'évitement, filtres, détail et lien vers le devis, avis, contact avec erreur puis envoi, pages légales, absence de prix et de débordement).
- Non-régression relancée : images d'événement, PDF de devis, demande de devis, authentification.

## Limites

- Mentions légales : l'éditeur est l'entreprise fictive de l'énoncé. Le téléphone de l'hébergeur provient du registre RIPE et doit être confirmé auprès de Hostinger. Une mise en ligne réelle exigerait l'identité de l'éditeur réel.
- Le dépôt d'un avis par le client et sa validation par un employé font partie des espaces client et employé, pas encore réalisés.
- Durée de conservation des journaux de connexion (adresses IP) à fixer et à automatiser.

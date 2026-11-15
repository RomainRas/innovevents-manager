# Application mobile (PWA)

Version du 15 novembre 2026. Partie « 2. Application mobile » du sujet : consultation et contact en mobilité pour Chloé et José.

## Choix technique : application web installable (PWA)

Le sujet n’impose ni Android ni iOS ; il demande que l’application soit **« packagée avec docker »** et liste des fonctions qui reposent sur les applications du téléphone (appel, messagerie, cartographie). Une PWA répond aux deux :

- elle s’installe sur l’écran d’accueil (icône, ouverture plein écran sans barre d’adresse) depuis Chrome sur Android ou Safari sur iOS (« Ajouter à l’écran d’accueil ») ;
- elle a son **propre conteneur Docker** (`docker/mobile/Dockerfile`), distinct du site ;
- les liens `tel:`, `mailto:` et Google Maps ouvrent les applications natives du téléphone ;
- les dernières données consultées restent lisibles hors ligne (service worker) ;
- une seule base de code, testée sur un téléphone émulé ; elle pourrait être emballée plus tard en fichier Android (Capacitor) sans réécriture.

Ce n’est pas le site affiché en petit : l’application a ses propres écrans, son manifeste et son service worker, et elle est réservée à l’équipe.

## Fonctions

| Exigence du sujet | Réalisation |
| --- | --- |
| Tableau de bord simplifié | À l’ouverture : tous les événements à venir ou en cours (hors annulés), du plus proche au plus lointain, avec date, client, horaires, lieu et statut. Pas de limite artificielle. |
| Fiche événement concise | Nom, dates, lieu, statut et client associé ; cinq dernières notes. |
| Fiche client interactive | Depuis la fiche événement : **Appeler** (`tel:` avec numéro nettoyé), **Envoyer un courriel** (`mailto:`), **Itinéraire** (lien universel Google Maps, ouvert par l’application de cartographie). Une action sans donnée est affichée comme indisponible. |
| Ajout de notes rapides | Bouton « ＋ Ajouter une note » visible sans défiler, zone de saisie qui prend le focus, enregistrement immédiat. En cas d’échec réseau, le texte est conservé sur le téléphone. Les notes sont partagées avec l’espace employé et le tableau de bord. |
| CGU et CGV | Liens depuis l’écran de connexion vers les pages du site (`/cgu`, `/cgv`, `/confidentialite`). |

Adresse : `/mobile/` (en local http://127.0.0.1:5173/mobile/, en ligne https://innovevents.rascleromain.fr/mobile/).

## Sécurité et données personnelles

- API dédiée `/api/mobile/*` : réservée aux employés et à l’administratrice **dont le compte a l’accès mobile** (`utilisateurs.acces_mobile`, réglable lors de la création du compte ; retiré à la suspension). Un client voit « Accès réservé ». Retirer l’accès refuse immédiatement la session ouverte.
- Même origine que l’API : session et jeton CSRF existants ; aucune nouvelle clé ni jeton stocké dans l’application.
- Compte MySQL limité de l’équipe : lecture des clients et événements, ajout de notes seulement.
- Hors ligne : seules les réponses de consultation sont mises en cache. Elles sont **effacées à la déconnexion** et dès que le serveur répond 401/403. L’appareil garde uniquement le prénom et le rôle du dernier utilisateur pour rouvrir l’application hors ligne ; les écritures exigent le réseau.
- La fiche contact ne renvoie que les champs utiles aux trois actions.

## Architecture

| Élément | Rôle |
| --- | --- |
| `mobile/` | Projet Vue 3 + Vite séparé (base `/mobile/`), navigation par ancre (fonctionne hors ligne). |
| `mobile/public/manifest.webmanifest`, icônes | Installation : nom, couleurs, icônes 192/512 px et icône « maskable ». |
| `mobile/public/sw.js` | Service worker : coquille et données de consultation (réseau d’abord, cache en secours), effacement à la demande. |
| `docker/mobile/Dockerfile` | Compilation puis Caddy interne (port 8080) servant les fichiers statiques. |
| `docker/caddy/Caddyfile` | Le Caddy public relaie `/mobile/*` vers le conteneur `mobile` (HTTPS en façade, mêmes en-têtes de sécurité). |
| `compose.app.yaml` | En local, serveur Vite de l’application mobile relayé par celui du site. |
| `backend/src/Service/MobileApp.php` | API mobile. |
| `database/mysql/015_mobile_demo.sql` | Adresse postale fictive du client de démonstration (action « Itinéraire »), sans écraser une adresse existante. |

## Tests

| Fichier | Contrôles | Résultat du 15 novembre 2026 |
| --- | --- | --- |
| `tests/app/mobile.py` | Connexion requise, client refusé, CSRF, tableau de bord complet et trié sans événements passés ou annulés, fiche événement, fiche contact actionnable sans champ superflu, note rapide (vide, partagée avec l’équipe), retrait de l’accès mobile, manifeste, icônes, service worker | 22/22 |
| `tests/app/mobile-browser.cjs` | Téléphone émulé (Pixel 7, tactile) : erreur de connexion, service worker actif, tableau de bord, bouton de note visible sans défiler, note avec erreur puis enregistrée, liens appel / courriel / itinéraire, consultation **hors ligne** (liste et fiche), ajout désactivé hors ligne, déconnexion effaçant les données, client refusé, aucun débordement | 21/21, trois exécutions successives |
| Image de production | `docker build -f docker/mobile/Dockerfile` puis vérification des réponses (page, manifeste, service worker sans cache, icônes, redirection) | réussi |
| Non-régression | Toutes les suites HTTP et navigateur existantes, compilation Vue | réussies |

Captures : `tmp/mobile/` (tableau de bord, fiche événement, fiche contact, hors ligne).

## Limites

- Les données sont disponibles hors ligne seulement après une première consultation en ligne (et un premier rechargement de l’application installée).
- Une note saisie hors ligne n’est pas envoyée automatiquement au retour du réseau : elle reste dans la zone de saisie et l’utilisateur l’enregistre.
- Le changement de mot de passe obligatoire se fait sur le site.

# Veille technologique et sécurité

Objectif : suivre les évolutions et les failles des briques utilisées (PHP, Vue, MySQL, MongoDB, Caddy, Docker, GitHub Actions) et les intégrer sans casser la production.

## Ce qui est automatisé

| Contrôle | Où | Effet |
| --- | --- | --- |
| Audit des dépendances PHP (`composer audit`) | Intégration continue, à chaque push sur `dev` et `main` | Le pipeline échoue si une dépendance de production a une faille connue (base GitHub Security Advisories / FriendsOfPHP). |
| Audit des dépendances JavaScript (`npm audit --omit=dev`) du site et de l’application mobile | Intégration continue | Échec si une faille de gravité haute ou critique touche une dépendance livrée. |
| Images Docker | `docker compose build --pull` au déploiement | Les images de base (PHP, Caddy, MySQL, MongoDB, Node) sont rechargées : correctifs de sécurité de la version mineure fixée. Les images de développement sont épinglées par empreinte (`@sha256`) pour la reproductibilité. |
| Versions verrouillées | `composer.lock`, `package-lock.json` | Installations identiques en local, en intégration continue et en production. |
| Certificats | Caddy | Renouvellement automatique Let’s Encrypt. |

## Sources suivies

| Domaine | Source | Rythme |
| --- | --- | --- |
| Failles des dépendances | Alertes GitHub du dépôt (Dependabot), [GitHub Advisory Database](https://github.com/advisories) | À chaque alerte ; audit en CI à chaque push |
| PHP | [php.net — versions supportées](https://www.php.net/supported-versions.php) et notes de version | Mensuel |
| Vue / Vite | Blogs officiels [Vue](https://blog.vuejs.org/) et [Vite](https://vite.dev/blog/) | Mensuel |
| MySQL, MongoDB | Notes de version MySQL 8.4 LTS et MongoDB 8.0 | Mensuel |
| Caddy, Docker | Versions GitHub de [Caddy](https://github.com/caddyserver/caddy/releases) et de Docker Engine | Mensuel |
| Sécurité web | [ANSSI — CERT-FR](https://www.cert.ssi.gouv.fr/) (avis et alertes), [OWASP Top 10](https://owasp.org/www-project-top-ten/), [OWASP Cheat Sheets](https://cheatsheetseries.owasp.org/) | Hebdomadaire (CERT-FR), à chaque nouvelle fonctionnalité (OWASP) |
| Accessibilité, RGPD | [RGAA](https://accessibilite.numerique.gouv.fr/), [CNIL](https://www.cnil.fr/) | À chaque écran / traitement de données |
| DevOps | Journal des modifications de [GitHub Actions](https://github.blog/changelog/) | Mensuel |

## Démarche quand une faille ou une évolution est repérée

1. **Qualifier** : la brique est-elle utilisée en production ? La partie vulnérable est-elle atteignable (ex. : outil de développement absent de l’image de production) ?
2. **Corriger sur `dev`** : mise à jour de la version dans le fichier de verrouillage, reconstruction des images.
3. **Vérifier** : tests unitaires, fonctionnels et de bout en bout (`scripts/run-tests.py`), puis intégration continue.
4. **Déployer** : fusion vers `main`, déploiement automatique, vérification du site.
5. **Tracer** : ticket GitHub et note dans la documentation concernée.

## Points de vigilance déjà appliqués (issus de cette veille)

- OWASP : requêtes préparées partout, jeton CSRF et contrôle d’origine, sessions HttpOnly/SameSite, CSP stricte, mots de passe bcrypt, limitation des tentatives de connexion, réponses identiques pour « inexistant » et « interdit » (pas d’énumération).
- Moindre privilège : un compte MySQL par fonctionnalité ; outils de test et de couverture absents de l’image de production (`composer install --no-dev`).
- RGPD : adresse IP de connexion conservée en clair uniquement dans le journal exigé (le limiteur de tentatives n’en garde qu’une empreinte, effacée après 15 minutes) ; données personnelles anonymisées à la suppression d’un compte ; données mobiles effacées du téléphone à la déconnexion ; courriels en attente chiffrés.
- Docker : secrets transmis par fichiers (`/run/secrets`), jamais dans les variables d’environnement ni dans Git.

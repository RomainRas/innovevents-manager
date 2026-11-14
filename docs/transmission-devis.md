# Transmission du devis et espace client — 14 novembre 2026

## Périmètre et utilisation

L’énoncé, pages 9 et 10, prévoit le téléchargement du PDF pour un envoi manuel ou l’envoi par la plateforme au courriel du client, le passage au statut « étude côté client », l’affichage du devis dans l’espace client et la création automatique du compte client s’il n’existe pas. Cette livraison réalise ces points. **La réponse du client (acceptation, demande de modification motivée, refus) reste à développer.**

Depuis « Préparer les prestations et le devis », une fois le PDF à jour généré, la section « Transmission au client » propose :

1. **Envoyer par la plateforme…** : confirmation du destinataire, de la référence et de la version, puis envoi d’un courriel avec le PDF en pièce jointe.
2. **Déclarer un envoi manuel…** : après envoi du PDF téléchargé depuis sa messagerie, Chloé coche une déclaration explicite. La plateforme n’a pas de preuve de cet envoi : c’est une déclaration, enregistrée comme telle.

La première transmission fige la version (`figee_le`, `transmise_le`) et la passe en `etude_client` : prestations, taux et PDF ne sont plus modifiables. L’historique des envois (mode, destinataire, état, dates) est affiché. Un nouvel envoi du même PDF reste possible tant que le devis est en étude, par exemple après un échec.

## Compte client

Si la fiche client n’est liée à aucun compte, la transmission crée un compte `client` avec un mot de passe par défaut aléatoire, envoyé par courriel, et `changement_mdp_requis=1` : le client doit le changer à sa première connexion (parcours existant `/mot-de-passe`). Le mot de passe n’est stocké que haché ; le courriel est chiffré dans la file jusqu’à son envoi, puis effacé.

**Choix de sécurité :** si un compte existe déjà avec l’adresse du client, il n’est **pas** rattaché automatiquement et rien n’est transmis. L’inscription publique ne prouve pas la possession de l’adresse : un rattachement automatique pourrait exposer le devis à un tiers. Un rattachement vérifié reste à développer.

## Fiabilité

- **Réponse perdue :** chaque demande porte une clé d’idempotence. Répétée, elle renvoie le résultat déjà enregistré, sans deuxième envoi ni deuxième compte. L’interface conserve la clé et propose « Vérifier le résultat ».
- **Envoi asynchrone :** le courriel passe par la file existante. La transmission reste « en attente » jusqu’à l’acceptation par le serveur de messagerie, puis devient « confirmée » ; après cinq échecs ou expiration (48 h), elle passe en « échec ». Un nouvel envoi est bloqué tant qu’un envoi est en attente.
- **Pièce jointe :** le traitement d’envoi ne lit qu’un PDF interne nommé par son empreinte et vérifie son SHA-256 avant de le joindre. « Confirmé » signifie accepté par le service d’envoi, pas reçu ni lu.
- **Concurrence :** la transmission partage le verrou du devis avec les prestations et la génération du PDF ; seul le PDF courant et intact peut être transmis.

## Espace client

Dans « Mon espace », la section « Vos devis » liste uniquement les devis transmis au client connecté (jamais les brouillons), avec l’événement figé au moment de la génération, les prestations, les totaux, le statut et le téléchargement du PDF transmis. Un client ne peut pas télécharger le document d’un autre : la réponse est identique à celle d’un document inexistant (404).

## Droits et installation

- `ecf_quote_send` : lecture des données du devis, création du compte client (sans lecture des mots de passe ni changement de rôle), rattachement `clients.utilisateur_id`, statut et dates de la version, insertion des transmissions et des courriels.
- `ecf_client_quotes` : lecture seule des devis, sans accès aux utilisateurs.
- `ecf_mail` : mise à jour de l’état des transmissions liées à ses courriels.
- Migration additive `009` (types de courriel, lien transmission/courriel) appliquée par `scripts/provision-quote-send.py`, intégré à `Start-App.ps1`. Le traitement d’envoi accède au volume des PDF en lecture seule.

## Tests exécutés le 14 novembre

`tests/app/quote-transmission.py` (avec `quote-transmission-browser.cjs` et `backend/tests/quote-send-permissions.php`) : **29 contrôles réussis.**

- Droits : visiteur, client, employé refusés ; CSRF exigé ; entrées invalides refusées ; PDF d’un autre devis refusé.
- Envoi plateforme avec traitement des courriels arrêté puis relancé : en attente, puis confirmé ; courriel reçu dans Mailpit avec le PDF joint, d’empreinte identique au document.
- Réponse perdue rejouée sans doublon ; clé réutilisée pour un autre mode refusée ; nouvel envoi bloqué pendant l’attente.
- Version figée : prestations, génération et édition refusées.
- Compte créé et identifiants reçus ; première connexion bloquée jusqu’au changement de mot de passe ; espace client avec le seul devis transmis ; téléchargement identique ; isolation entre clients et brouillon caché.
- Envoi manuel : déclaration obligatoire, historique à deux entrées.
- Compte existant de même adresse : refus, aucune transmission, devis resté brouillon.
- Dix refus SQL des comptes limités.
- Chromium 1280 px (envoi plateforme avec réponse perdue simulée) et 390 px (envoi manuel avec focus sur la déclaration), puis espace client et téléchargement aux deux largeurs ; pas de débordement horizontal.

Non-régression : `quote-pdf.py` 23/23, `quote-drafts.py` 22/22, `mail-recovery.py` 7/7 ; compilation Vue réussie. [Preuve sans secrets](preuves/transmission-devis-2026-11-14.json).

## Limites

- Réponse du client au devis et contre-proposition : à développer (étape suivante).
- La transmission n’est pas journalisée dans MongoDB : elle ne figure pas dans la liste des actions à journaliser de l’énoncé (page 13), qui prévoit déjà la génération du PDF.
- L’administratrice ne peut plus télécharger le PDF une fois transmis depuis cet écran.
- La sauvegarde du volume des PDF reste à développer, comme indiqué dans le [guide PDF](devis-pdf.md).
- Le limiteur de connexion compte aussi les connexions réussies (dix par compte en 15 minutes) : enchaîner les tests impose d’attendre ou de réinitialiser le compteur local.
- Pour Vite dans Docker sous Windows, les modifications de fichiers ne sont pas toujours détectées : redémarrer le service `web` après une modification.

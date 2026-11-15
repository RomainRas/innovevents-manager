# Transmission du devis et espace client

> **Note :** ce document retrace l’étape où cette fonctionnalité a été développée. Les éléments qu’il signale comme « à développer » ont pour la plupart été réalisés depuis ; l’état final, avec les limites encore valables, est dans le [bilan des fonctionnalités](bilan-fonctionnalites.md).

## Périmètre et utilisation

L’énoncé, pages 9 et 10, prévoit le téléchargement du PDF pour un envoi manuel ou l’envoi par la plateforme au courriel du client, le passage au statut « étude côté client », l’affichage du devis dans l’espace client et la création automatique du compte client s’il n’existe pas. Cette livraison réalise ces points. **La réponse du client (acceptation, demande de modification motivée, refus) reste à développer.**

Depuis « Préparer les prestations et le devis », une fois le PDF à jour généré, la section « Transmission au client » propose :

1. **Envoyer par la plateforme…** : confirmation du destinataire, de la référence et de la version, puis envoi d’un courriel avec le PDF en pièce jointe.
2. **Déclarer un envoi manuel…** : après envoi du PDF téléchargé depuis sa messagerie, Chloé coche une déclaration explicite. La plateforme n’a pas de preuve de cet envoi : c’est une déclaration, enregistrée comme telle.

La première transmission fige la version (`figee_le`, `transmise_le`) et la passe en `etude_client` : prestations, taux et PDF ne sont plus modifiables. L’historique des envois (mode, destinataire, état, dates) est affiché. Un nouvel envoi du même PDF reste possible tant que le devis est en étude, par exemple après un échec.

## Compte client

Si la fiche client n’est liée à aucun compte, la transmission crée un compte `client` avec un mot de passe par défaut aléatoire, envoyé par courriel, et `changement_mdp_requis=1` : le client doit le changer à sa première connexion (parcours existant `/mot-de-passe`). Le mot de passe n’est stocké que haché ; le courriel est chiffré dans la file jusqu’à son envoi, puis effacé.

**Client déjà inscrit :** si le client a créé lui-même son compte avant sa demande de devis, avec la même adresse, ce compte est rattaché à sa fiche lors de la première transmission. L’inscription publique ne prouve pas la possession de l’adresse : le mot de passe du compte est donc **remplacé** par un mot de passe provisoire aléatoire, envoyé à cette adresse, à changer à la première connexion. Seul le vrai titulaire de l’adresse peut ainsi accéder au devis ; l’ancien mot de passe ne fonctionne plus et toute session ouverte sur ce compte est fermée (elle est liée à l’empreinte du mot de passe). L’écran de transmission l’annonce avant l’envoi.

Le rattachement est refusé, et rien n’est transmis, si l’adresse appartient à un compte de l’équipe, à un compte suspendu ou supprimé, ou à un compte déjà lié à une autre fiche : l’administratrice doit alors corriger l’adresse de la fiche.

## Fiabilité

- **Réponse perdue :** chaque demande porte une clé d’idempotence. Répétée, elle renvoie le résultat déjà enregistré, sans deuxième envoi ni deuxième compte. L’interface conserve la clé et propose « Vérifier le résultat ».
- **Envoi asynchrone :** le courriel passe par la file existante. La transmission reste « en attente » jusqu’à l’acceptation par le serveur de messagerie, puis devient « confirmée » ; après cinq échecs ou expiration (48 h), elle passe en « échec ». Un nouvel envoi est bloqué tant qu’un envoi est en attente.
- **Pièce jointe :** le traitement d’envoi ne lit qu’un PDF interne nommé par son empreinte et vérifie son SHA-256 avant de le joindre. « Confirmé » signifie accepté par le service d’envoi, pas reçu ni lu.
- **Concurrence :** la transmission partage le verrou du devis avec les prestations et la génération du PDF ; seul le PDF courant et intact peut être transmis.

## Espace client

Dans « Mon espace », la section « Vos devis » liste uniquement les devis transmis au client connecté (jamais les brouillons), avec l’événement figé au moment de la génération, les prestations, les totaux, le statut et le téléchargement du PDF transmis. Un client ne peut pas télécharger le document d’un autre : la réponse est identique à celle d’un document inexistant (404).

## Droits et installation

- `ecf_quote_send` : lecture des données du devis, création du compte client ou remplacement du mot de passe d’un compte client déjà inscrit (sans lecture des mots de passe, ni changement de rôle, d’adresse ou de statut), rattachement `clients.utilisateur_id`, statut et dates de la version, insertion des transmissions et des courriels.
- `ecf_client_quotes` : lecture seule des devis, sans accès aux utilisateurs.
- `ecf_mail` : mise à jour de l’état des transmissions liées à ses courriels.
- Migration additive `009` (types de courriel, lien transmission/courriel) appliquée par `scripts/provision-quote-send.py`, intégré à `Start-App.ps1`. Le traitement d’envoi accède au volume des PDF en lecture seule.

## Tests exécutés

`tests/app/quote-transmission.py` (avec `quote-transmission-browser.cjs` et `backend/tests/quote-send-permissions.php`) : **33 contrôles réussis.**

- Droits : visiteur, client, employé refusés ; CSRF exigé ; entrées invalides refusées ; PDF d’un autre devis refusé.
- Envoi plateforme avec traitement des courriels arrêté puis relancé : en attente, puis confirmé ; courriel reçu dans Mailpit avec le PDF joint, d’empreinte identique au document.
- Réponse perdue rejouée sans doublon ; clé réutilisée pour un autre mode refusée ; nouvel envoi bloqué pendant l’attente.
- Version figée : prestations, génération et édition refusées.
- Compte créé et identifiants reçus ; première connexion bloquée jusqu’au changement de mot de passe ; espace client avec le seul devis transmis ; téléchargement identique ; isolation entre clients et brouillon caché.
- Envoi manuel : déclaration obligatoire, historique à deux entrées.
- Client déjà inscrit avec la même adresse : situation annoncée à l’écran, compte rattaché à la fiche (aucun doublon), ancien mot de passe et session ouverte refusés, mot de passe provisoire reçu par courriel et changement exigé à la connexion.
- Adresse d’un compte déjà lié à une autre fiche : refus, aucune transmission, devis resté brouillon.
- Onze refus SQL des comptes limités (dont modification de l’adresse, du rôle ou du statut d’un compte).
- Chromium 1280 px (envoi plateforme avec réponse perdue simulée) et 390 px (envoi manuel avec focus sur la déclaration), puis espace client et téléchargement aux deux largeurs ; pas de débordement horizontal.

Non-régression : `quote-pdf.py` 23/23, `quote-drafts.py` 22/22, `mail-recovery.py` 7/7 ; compilation Vue réussie. [Preuve sans secrets](preuves/transmission-devis.json).

## Limites

- Réponse du client au devis et contre-proposition : à développer (étape suivante).
- La transmission n’est pas journalisée dans MongoDB : elle ne figure pas dans la liste des actions à journaliser de l’énoncé (page 13), qui prévoit déjà la génération du PDF.
- L’administratrice ne peut plus télécharger le PDF une fois transmis depuis cet écran.
- La sauvegarde du volume des PDF reste à développer, comme indiqué dans le [guide PDF](devis-pdf.md).
- Le limiteur de connexion compte aussi les connexions réussies (dix par compte en 15 minutes) : enchaîner les tests impose d’attendre ou de réinitialiser le compteur local.
- Pour Vite dans Docker sous Windows, les modifications de fichiers ne sont pas toujours détectées : redémarrer le service `web` après une modification.

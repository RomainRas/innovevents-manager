SET NAMES utf8mb4;
-- Pages publiques : événements publiés, avis validés et formulaire de contact.
-- Les pages publiques ne lisent que ces vues : aucune colonne privée (client, prix, preuve d'accord) n'y figure.

ALTER TABLE courriels_a_envoyer DROP CHECK ck_mail_type,
 ADD CONSTRAINT ck_mail_type CHECK (type IN ('confirmation','recuperation','demande_devis','qualification_echec','devis','compte_client','devis_accepte','contact'));

-- Un événement est public seulement s'il n'est plus en brouillon, que Chloé l'a rendu visible
-- et que le client a donné son accord de publication.
CREATE OR REPLACE SQL SECURITY DEFINER VIEW v_evenements_publics AS
SELECT e.id, e.nom, e.debut, e.fin, e.lieu, e.type_evenement, e.theme, e.participants_estimes, e.statut,
 e.image_reference, e.image_alternative
FROM evenements e
WHERE e.statut <> 'brouillon' AND e.visible_demande = 1 AND e.accord_publication = 1
 AND e.nom IS NOT NULL AND e.debut IS NOT NULL AND e.fin IS NOT NULL;

-- Avis validés par un employé ; l'auteur est réduit au prénom et à l'initiale du nom.
CREATE OR REPLACE SQL SECURITY DEFINER VIEW v_avis_publics AS
SELECT a.id, a.contenu, a.modere_le AS publie_le, p.nom AS evenement, p.type_evenement,
 CONCAT(u.prenom, ' ', LEFT(u.nom, 1), '.') AS auteur
FROM avis a
JOIN v_evenements_publics p ON p.id = a.evenement_id
JOIN utilisateurs u ON u.id = a.auteur_id
WHERE a.statut = 'valide';

-- Démonstration : événements fictifs publiés avec un accord fictif explicitement signalé, et avis fictifs.
START TRANSACTION;
SET @client=(SELECT c.id FROM clients c JOIN utilisateurs u ON u.id=c.utilisateur_id WHERE u.email_normalise='alice@example.invalid');
SET @auteur=(SELECT id FROM utilisateurs WHERE email_normalise='alice@example.invalid');
SET @moderateur=(SELECT id FROM utilisateurs WHERE email_normalise='jose@example.invalid');
SET @preuve='Démonstration : accord fictif, aucun client réel.';
INSERT INTO evenements(client_id,nom,debut,fin,lieu,type_evenement,theme,participants_estimes,statut,visible_demande,accord_publication,accord_recueilli_le,preuve_accord,image_reference,image_alternative)
SELECT @client,v.nom,v.debut,v.fin,v.lieu,v.type_evenement,v.theme,v.participants,v.statut,1,1,UTC_TIMESTAMP(6),@preuve,v.image,v.alt
FROM (
 SELECT 'Convention annuelle Horizon' nom,'2026-03-12 08:00:00' debut,'2026-03-12 17:00:00' fin,'Centre de congrès fictif, Lyon' lieu,'Conférence' type_evenement,'Innovation' theme,180 participants,'termine' statut,
  '600147525fe160baab01850945ae43f0fc4e6e6645e6ed43e485e4d8f611a42e.jpg' image,'Scène éclairée par deux projecteurs devant un public assis' alt
 UNION ALL SELECT 'Séminaire de direction Horizon','2026-05-20 07:30:00','2026-05-21 15:00:00','Domaine fictif, Annecy','Séminaire','Stratégie',24,'termine',
  '36c4074425ec5519ab82d6a7a66104094c92d367b001910cd07d5d48cff11ca3.jpg','Salle lumineuse avec des participants réunis autour de tables rondes'
 UNION ALL SELECT 'Soirée des dix ans Horizon','2026-06-26 17:00:00','2026-06-26 23:00:00','Salle de réception fictive, Paris','Soirée d’entreprise','Gala',150,'termine',
  'c13bdec59b19ff9d104ae7518712c85d425569e29cdf21d138f118896a17dfa7.jpg','Salle de soirée décorée de guirlandes lumineuses colorées'
 UNION ALL SELECT 'Journée cohésion Horizon','2026-09-17 07:00:00','2026-09-17 15:00:00','Parc fictif, Bordeaux','Team building','Bien-être',60,'termine',
  'df82faeeaa6e6781a8845d46cb687c1e6fa7a8b777592b4448512411d7d13989.jpg','Équipe alignée sur une colline au soleil couchant'
 UNION ALL SELECT 'Ateliers innovation Horizon','2026-12-03 08:30:00','2026-12-03 12:30:00','Espace de travail fictif, Nantes','Atelier','Innovation',30,'accepte',
  'e1c25a5cd28b528637aa97db9eb102be1ecf886065db5448601b6bd0b5d7fa0a.jpg','Tables de travail couvertes de notes colorées vues du dessus'
 UNION ALL SELECT 'Lancement produit Horizon','2027-01-28 17:30:00','2027-01-28 21:00:00','Rooftop fictif, Marseille','Soirée d’entreprise','Lancement',90,'en_cours',
  '539f8e9aca14ce051d998c8ae02379922c186e4e5ca492573f08e38c63a35ed6.jpg','Verres de cocktail alignés sous une lumière douce'
) v
WHERE @client IS NOT NULL AND NOT EXISTS (SELECT 1 FROM evenements x WHERE x.nom=v.nom AND x.client_id=@client);
INSERT INTO avis(evenement_id,auteur_id,contenu,statut,moderateur_id,modere_le)
SELECT e.id,@auteur,v.contenu,'valide',@moderateur,UTC_TIMESTAMP(6)
FROM (
 SELECT 'Convention annuelle Horizon' nom,'Organisation impeccable du début à la fin : accueil fluide, timing respecté et une technique irréprochable. Nos équipes en parlent encore.' contenu
 UNION ALL SELECT 'Séminaire de direction Horizon','Un cadre idéal pour prendre du recul. Chloé a su adapter le programme à nos contraintes jusqu’à la dernière minute.'
 UNION ALL SELECT 'Soirée des dix ans Horizon','Une soirée à notre image, élégante et chaleureuse. Les invités ont été bluffés par la décoration.'
 UNION ALL SELECT 'Journée cohésion Horizon','Activités bien pensées et équilibrées, tout le monde a pu participer. Merci pour l’écoute et la réactivité.'
) v JOIN evenements e ON e.nom=v.nom AND e.client_id=@client
WHERE @auteur IS NOT NULL AND @moderateur IS NOT NULL
 AND NOT EXISTS (SELECT 1 FROM avis a WHERE a.evenement_id=e.id AND a.auteur_id=@auteur);
COMMIT;

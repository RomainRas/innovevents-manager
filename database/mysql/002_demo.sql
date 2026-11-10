-- Démonstration locale uniquement. Base neuve, @demo_hash fourni par le lanceur.
-- Aucun PDF, e-mail ou accord de publication simulé comme réellement obtenu.
START TRANSACTION;
INSERT INTO utilisateurs(email,email_normalise,prenom,nom,nom_utilisateur,mot_de_passe_hash,role,acces_mobile)
VALUES ('chloe@example.invalid','chloe@example.invalid','Chloé','Démo','chloe-demo',@demo_hash,'administrateur',1),
 ('jose@example.invalid','jose@example.invalid','José','Démo','jose-demo',@demo_hash,'employe',1),
 ('alice@example.invalid','alice@example.invalid','Alice','Démo','alice-demo',@demo_hash,'client',0);
INSERT INTO clients(utilisateur_id,entreprise,contact_nom,contact_prenom,email,telephone)
SELECT id,'Entreprise fictive Horizon','Démo','Alice','alice@example.invalid','0000000000'
FROM utilisateurs WHERE email_normalise='alice@example.invalid';
SET @client=LAST_INSERT_ID();
INSERT INTO prospects(entreprise,nom,prenom,email,telephone,lieu,type_evenement,date_souhaitee,participants_estimes,besoin)
VALUES ('Entreprise fictive Atelier','Démo','Léa','lea@example.invalid','0000000000','Lieu fictif Lyon','Séminaire','2030-06-10',30,'Organiser un séminaire fictif');
INSERT INTO evenements(client_id,nom,debut,fin,lieu,type_evenement,participants_estimes)
VALUES (@client,'Séminaire Horizon — démonstration','2030-06-10 08:00:00','2030-06-10 16:00:00','Lieu fictif Lyon','Séminaire',30),
 (@client,'Atelier Horizon — démonstration','2030-07-12 08:00:00','2030-07-12 11:00:00','Lieu fictif Paris','Atelier',12),
 (@client,'Rencontre Horizon — démonstration','2030-09-05 16:00:00','2030-09-05 20:00:00','Lieu fictif Nantes','Rencontre',45);
INSERT INTO devis(evenement_id,reference) SELECT id,CONCAT('DEMO-2030-',id) FROM evenements;
INSERT INTO versions_devis(devis_id,numero) SELECT id,1 FROM devis;
UPDATE devis d JOIN versions_devis v ON v.devis_id=d.id SET d.version_courante_id=v.id;
INSERT INTO prestations_devis(version_id,ordre,libelle,montant_ht)
SELECT id,1,'Prestation indicative de démonstration',100 FROM versions_devis;
INSERT INTO taches(evenement_id,assigne_a,titre)
SELECT e.id,u.id,'Préciser le besoin et préparer le devis' FROM evenements e
CROSS JOIN utilisateurs u WHERE u.email_normalise='jose@example.invalid';
INSERT INTO notes_evenement(evenement_id,auteur_id,contenu)
SELECT e.id,u.id,'Données fictives. Devis encore en brouillon, aucun envoi réalisé.' FROM evenements e
CROSS JOIN utilisateurs u WHERE u.email_normalise='jose@example.invalid';
INSERT INTO notes_globales(auteur_id,contenu)
SELECT id,'Environnement de démonstration, aucune donnée personnelle réelle.' FROM utilisateurs WHERE role='administrateur';
COMMIT;

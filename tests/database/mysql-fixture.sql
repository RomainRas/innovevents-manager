-- Jeu fictif temporaire : exécuté dans une transaction annulée par le lanceur.
-- Empreinte d'un mot de passe aléatoire non conservé : aucun compte de démonstration utilisable.
SET @suffix = UUID();
SET @hash = '$2y$10$opK.m3XooPgJCZVLxKCezekkj4HyLKmJSE54KM2ZGjbPwGpWkkMaC';
INSERT INTO utilisateurs (email,email_normalise,prenom,nom,nom_utilisateur,mot_de_passe_hash,role,acces_mobile)
 VALUES (CONCAT(@suffix,'-admin@example.invalid'),CONCAT(@suffix,'-admin@example.invalid'),'Chloe','Test','chloe-test',@hash,'administrateur',1);
SET @admin = LAST_INSERT_ID();
INSERT INTO utilisateurs (email,email_normalise,prenom,nom,nom_utilisateur,mot_de_passe_hash,role,acces_mobile)
 VALUES (CONCAT(@suffix,'-employe@example.invalid'),CONCAT(@suffix,'-employe@example.invalid'),'Jose','Test','jose-test',@hash,'employe',1);
SET @employe = LAST_INSERT_ID();
INSERT INTO utilisateurs (email,email_normalise,prenom,nom,nom_utilisateur,mot_de_passe_hash)
 VALUES (CONCAT(@suffix,'-client@example.invalid'),CONCAT(@suffix,'-client@example.invalid'),'Alice','Test','alice-test',@hash);
SET @user_a = LAST_INSERT_ID();
INSERT INTO clients (utilisateur_id,entreprise,contact_nom,contact_prenom,email,telephone)
 VALUES (@user_a,'Entreprise fictive A','Test','Alice','alice@example.invalid','0000000000');
SET @client_a = LAST_INSERT_ID();
INSERT INTO clients (entreprise,contact_nom,contact_prenom,email,telephone)
 VALUES ('Entreprise fictive B','Test','Bob','bob@example.invalid','0000000000');
SET @client_b = LAST_INSERT_ID();
INSERT INTO prospects (entreprise,nom,prenom,email,telephone,lieu,type_evenement,date_souhaitee,participants_estimes,besoin,statut,client_id,qualifie_le)
 VALUES ('Entreprise fictive A','Test','Alice','alice@example.invalid','0000000000','Lieu fictif','Seminaire','2030-06-10',12,'Demande de test','converti',@client_a,UTC_TIMESTAMP(6));
SET @prospect = LAST_INSERT_ID();
INSERT INTO evenements (client_id,prospect_id,nom,debut,fin,lieu)
 VALUES (@client_a,@prospect,'Evenement fictif A','2030-06-10 08:00:00','2030-06-10 17:00:00','Lieu fictif');
SET @event_a = LAST_INSERT_ID();
INSERT INTO evenements (client_id,nom) VALUES (@client_b,'Evenement fictif B');
SET @event_b = LAST_INSERT_ID();
INSERT INTO devis (evenement_id,reference) VALUES (@event_a,CONCAT('TEST-A-',@suffix));
SET @devis_a = LAST_INSERT_ID();
INSERT INTO versions_devis (devis_id,numero) VALUES (@devis_a,1);
SET @version_a = LAST_INSERT_ID();
UPDATE devis SET version_courante_id=@version_a WHERE id=@devis_a;
INSERT INTO devis (evenement_id,reference) VALUES (@event_b,CONCAT('TEST-B-',@suffix));
SET @devis_b = LAST_INSERT_ID();
INSERT INTO versions_devis (devis_id,numero) VALUES (@devis_b,1);
SET @version_b = LAST_INSERT_ID();
UPDATE devis SET version_courante_id=@version_b WHERE id=@devis_b;
INSERT INTO prestations_devis (version_id,ordre,libelle,montant_ht) VALUES (@version_a,1,'Prestation fictive',100.00);
-- Taux purement fictif pour tester les champs, pas une règle fiscale.
UPDATE versions_devis SET client_snapshot=JSON_OBJECT('entreprise','Entreprise fictive A','contact','Alice Test'),
 evenement_snapshot=JSON_OBJECT('nom','Evenement fictif A','lieu','Lieu fictif','debut','2030-06-10T08:00:00Z','fin','2030-06-10T17:00:00Z'),
 devise='EUR',taux_tva=7.50,regle_calcul='TVA_TOTAL_2D_HALF_UP_V1',total_ht=100,total_tva=7.50,total_ttc=107.50,
 figee_le=UTC_TIMESTAMP(6),transmise_le=UTC_TIMESTAMP(6),statut='etude_client' WHERE id=@version_a;
INSERT INTO documents_devis (version_id,revision_contenu,etat,fichier_reference,empreinte_sha256,genere_le)
 VALUES (@version_a,1,'disponible','test-fictif/non-genere.pdf',REPEAT('a',64),UTC_TIMESTAMP(6));
SET @document = LAST_INSERT_ID();
INSERT INTO transmissions_devis (document_id,acteur_id,mode,destinataire,etat,cle_idempotence,confirmee_le)
 VALUES (@document,@admin,'manuel','alice@example.invalid','confirme',UUID(),UTC_TIMESTAMP(6));
INSERT INTO taches (evenement_id,assigne_a,titre) VALUES (@event_a,@employe,'Tache fictive');
INSERT INTO notes_evenement (evenement_id,auteur_id,contenu) VALUES (@event_a,@employe,'Note fictive');
INSERT INTO notes_globales (auteur_id,contenu) VALUES (@admin,'Note globale fictive');
INSERT INTO decisions_devis (version_id,auteur_id,choix) VALUES (@version_a,@user_a,'accepte');
UPDATE versions_devis SET statut='accepte' WHERE id=@version_a;
UPDATE evenements SET statut='termine' WHERE id=@event_a;
INSERT INTO avis (evenement_id,auteur_id,contenu) VALUES (@event_a,@user_a,'Avis fictif en attente');
INSERT INTO journal_a_emettre (id_trace,horodatage,type_action,id_utilisateur,details)
 VALUES (UUID(),UTC_TIMESTAMP(6),'CREATION_CLIENT',@admin,JSON_OBJECT('id_client',CAST(@client_a AS CHAR),'nom_client','Entreprise fictive A'));

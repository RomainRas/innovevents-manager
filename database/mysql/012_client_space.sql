SET NAMES utf8mb4;
-- Espace client : profil, demande de suppression du compte et avis après événement ; modération par l'équipe.

ALTER TABLE courriels_a_envoyer DROP CHECK ck_mail_type,
 ADD CONSTRAINT ck_mail_type CHECK (type IN ('confirmation','recuperation','demande_devis','qualification_echec','devis','compte_client','devis_accepte','contact','suppression'));

-- Une seule demande en attente par compte (colonne calculée unique) ; le traitement effectif
-- (effacement des données) relève de l'administration et retire la demande avec le compte.
CREATE TABLE demandes_suppression (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 utilisateur_id BIGINT UNSIGNED NOT NULL,
 motif TEXT NULL,
 statut VARCHAR(20) NOT NULL DEFAULT 'en_attente',
 cree_le DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 traitee_le DATETIME(6) NULL,
 en_attente_pour BIGINT UNSIGNED AS (CASE WHEN statut = 'en_attente' THEN utilisateur_id END) STORED,
 CONSTRAINT uq_suppression_en_attente UNIQUE (en_attente_pour),
 CONSTRAINT fk_suppression_utilisateur FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id),
 CONSTRAINT ck_suppression_statut CHECK (statut IN ('en_attente','traitee')),
 CONSTRAINT ck_suppression_traitement CHECK ((statut = 'en_attente' AND traitee_le IS NULL) OR (statut = 'traitee' AND traitee_le IS NOT NULL)),
 CONSTRAINT ck_suppression_motif CHECK (motif IS NULL OR CHAR_LENGTH(TRIM(motif)) > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Démonstration : un événement terminé et publié, encore sans avis, pour montrer le dépôt d'un avis.
START TRANSACTION;
SET @client=(SELECT c.id FROM clients c JOIN utilisateurs u ON u.id=c.utilisateur_id WHERE u.email_normalise='alice@example.invalid');
INSERT INTO evenements(client_id,nom,debut,fin,lieu,type_evenement,theme,participants_estimes,statut,visible_demande,accord_publication,accord_recueilli_le,preuve_accord)
SELECT @client,'Afterwork de rentrée Horizon','2026-10-08 16:00:00','2026-10-08 20:00:00','Péniche fictive, Lyon','Soirée d’entreprise','Convivialité',70,'termine',1,1,UTC_TIMESTAMP(6),
 'Démonstration : accord fictif, aucun client réel.'
FROM DUAL WHERE @client IS NOT NULL AND NOT EXISTS (SELECT 1 FROM evenements x WHERE x.nom='Afterwork de rentrée Horizon' AND x.client_id=@client);
COMMIT;

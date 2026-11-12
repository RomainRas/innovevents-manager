-- Migration de innovevents_demo, appliquée par provision-mail.py après contrôle des colonnes.
CREATE TABLE IF NOT EXISTS courriels_a_envoyer (
 id CHAR(36) PRIMARY KEY,
 type VARCHAR(20) NOT NULL,
 contenu_chiffre MEDIUMTEXT NULL,
 etat VARCHAR(20) NOT NULL DEFAULT 'attente',
 tentatives INT NOT NULL DEFAULT 0,
 prochain_essai_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 expire_le DATETIME NOT NULL,
 cree_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 envoye_le DATETIME NULL,
 CONSTRAINT ck_mail_type CHECK (type IN ('confirmation','recuperation')),
 CONSTRAINT ck_mail_etat CHECK (etat IN ('attente','envoye','echec','expire')),
 CONSTRAINT ck_mail_payload CHECK ((etat='attente' AND contenu_chiffre IS NOT NULL) OR (etat<>'attente' AND contenu_chiffre IS NULL)),
 INDEX ix_mail_pending (etat,prochain_essai_le)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
ALTER TABLE utilisateurs
 ADD COLUMN mdp_temporaire_hash VARCHAR(255) NULL,
 ADD COLUMN mdp_temporaire_expire_le DATETIME NULL;

-- Réponse du client au devis. Garde d’application dans provision-quote-decisions.py.
ALTER TABLE courriels_a_envoyer DROP CHECK ck_mail_type,
 ADD CONSTRAINT ck_mail_type CHECK (type IN ('confirmation','recuperation','demande_devis','qualification_echec','devis','compte_client','devis_accepte'));
ALTER TABLE decisions_devis
 ADD COLUMN cle_idempotence CHAR(36) COLLATE utf8mb4_0900_bin NULL,
 ADD CONSTRAINT uq_decisions_cle UNIQUE (cle_idempotence);

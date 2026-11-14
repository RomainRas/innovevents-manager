-- Transmission du devis et accès client. Garde d’application dans provision-quote-send.py.
ALTER TABLE courriels_a_envoyer DROP CHECK ck_mail_type,
 ADD CONSTRAINT ck_mail_type CHECK (type IN ('confirmation','recuperation','demande_devis','qualification_echec','devis','compte_client'));
ALTER TABLE transmissions_devis
 ADD COLUMN courriel_id CHAR(36) NULL,
 ADD CONSTRAINT uq_transmissions_courriel UNIQUE (courriel_id),
 ADD CONSTRAINT ck_transmissions_courriel CHECK ((mode = 'automatique' AND courriel_id IS NOT NULL) OR (mode = 'manuel' AND courriel_id IS NULL));

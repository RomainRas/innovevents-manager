-- Appliquer après la migration 004, une seule fois (garde dans provision-prospects.py).
ALTER TABLE courriels_a_envoyer DROP CHECK ck_mail_type,
 ADD CONSTRAINT ck_mail_type CHECK (type IN ('confirmation','recuperation','demande_devis','qualification_echec'));

SET NAMES utf8mb4;
-- Administration des comptes : création, suspension et suppression des comptes employés et clients.
-- Un compte supprimé est anonymisé (identité, adresse électronique, mot de passe) et marqué supprime_le :
-- l'historique commercial (devis, décisions, transmissions) reste cohérent sans donnée personnelle.

ALTER TABLE utilisateurs ADD COLUMN supprime_le DATETIME(6) NULL,
 ADD CONSTRAINT ck_utilisateurs_suppression CHECK (supprime_le IS NULL OR (statut = 'suspendu' AND acces_mobile = 0));

ALTER TABLE courriels_a_envoyer DROP CHECK ck_mail_type,
 ADD CONSTRAINT ck_mail_type CHECK (type IN ('confirmation','recuperation','demande_devis','qualification_echec','devis','compte_client','devis_accepte','contact','suppression','compte_cree','compte_supprime'));

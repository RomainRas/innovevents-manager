-- Innov'Events : MySQL 8.4 / InnoDB. Script écrit à la main.
-- Exécuter une seule fois dans un schéma VIDE sélectionné par le client mysql.
-- Aucun DROP, aucune désactivation des clés étrangères. DDL non transactionnel.
SET NAMES utf8mb4;
SET SESSION time_zone = '+00:00';
SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

CREATE TABLE utilisateurs (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 email VARCHAR(254) NOT NULL,
 email_normalise VARCHAR(254) COLLATE utf8mb4_0900_bin NOT NULL,
 prenom VARCHAR(100) NOT NULL, nom VARCHAR(100) NOT NULL,
 nom_utilisateur VARCHAR(100) NOT NULL, mot_de_passe_hash VARCHAR(255) NOT NULL,
 role VARCHAR(20) NOT NULL DEFAULT 'client', statut VARCHAR(20) NOT NULL DEFAULT 'actif',
 changement_mdp_requis TINYINT NOT NULL DEFAULT 0, acces_mobile TINYINT NOT NULL DEFAULT 0,
 cree_le DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 modifie_le DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
 CONSTRAINT uq_utilisateurs_email UNIQUE (email_normalise),
 CONSTRAINT ck_utilisateurs_role CHECK (role IN ('client','employe','administrateur')),
 CONSTRAINT ck_utilisateurs_statut CHECK (statut IN ('actif','suspendu')),
 CONSTRAINT ck_utilisateurs_flags CHECK (changement_mdp_requis IN (0,1) AND acces_mobile IN (0,1)),
 CONSTRAINT ck_utilisateurs_mobile CHECK (acces_mobile = 0 OR role IN ('employe','administrateur')),
 CONSTRAINT ck_utilisateurs_textes CHECK (CHAR_LENGTH(TRIM(email)) > 0 AND CHAR_LENGTH(TRIM(email_normalise)) > 0
  AND CHAR_LENGTH(TRIM(prenom)) > 0 AND CHAR_LENGTH(TRIM(nom)) > 0
  AND CHAR_LENGTH(TRIM(nom_utilisateur)) > 0 AND CHAR_LENGTH(mot_de_passe_hash) > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE clients (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, utilisateur_id BIGINT UNSIGNED NULL,
 entreprise VARCHAR(200) NOT NULL, contact_nom VARCHAR(100) NOT NULL, contact_prenom VARCHAR(100) NOT NULL,
 email VARCHAR(254) NOT NULL, telephone VARCHAR(32) NOT NULL,
 adresse_ligne1 VARCHAR(255) NULL, adresse_ligne2 VARCHAR(255) NULL,
 code_postal VARCHAR(20) NULL, ville VARCHAR(120) NULL, pays_code CHAR(2) NULL,
 cree_le DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 modifie_le DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
 CONSTRAINT uq_clients_compte UNIQUE (utilisateur_id),
 CONSTRAINT fk_clients_compte FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id),
 CONSTRAINT ck_clients_textes CHECK (CHAR_LENGTH(TRIM(entreprise)) > 0 AND CHAR_LENGTH(TRIM(contact_nom)) > 0
  AND CHAR_LENGTH(TRIM(contact_prenom)) > 0 AND CHAR_LENGTH(TRIM(email)) > 0 AND CHAR_LENGTH(TRIM(telephone)) > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE prospects (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 entreprise VARCHAR(200) NOT NULL, nom VARCHAR(100) NOT NULL, prenom VARCHAR(100) NOT NULL,
 email VARCHAR(254) NOT NULL, telephone VARCHAR(32) NOT NULL, lieu VARCHAR(255) NOT NULL,
 type_evenement VARCHAR(100) NOT NULL, date_souhaitee DATE NOT NULL,
 participants_estimes INT UNSIGNED NOT NULL, besoin TEXT NOT NULL,
 statut VARCHAR(20) NOT NULL DEFAULT 'a_contacter', message_echec TEXT NULL,
 client_id BIGINT UNSIGNED NULL, qualifie_le DATETIME(6) NULL,
 cree_le DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 modifie_le DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
 CONSTRAINT uq_prospects_client UNIQUE (id, client_id),
 CONSTRAINT fk_prospects_client FOREIGN KEY (client_id) REFERENCES clients(id),
 CONSTRAINT ck_prospects_participants CHECK (participants_estimes > 0),
 CONSTRAINT ck_prospects_textes CHECK (CHAR_LENGTH(TRIM(entreprise)) > 0 AND CHAR_LENGTH(TRIM(nom)) > 0
  AND CHAR_LENGTH(TRIM(prenom)) > 0 AND CHAR_LENGTH(TRIM(email)) > 0 AND CHAR_LENGTH(TRIM(telephone)) > 0
  AND CHAR_LENGTH(TRIM(lieu)) > 0 AND CHAR_LENGTH(TRIM(type_evenement)) > 0 AND CHAR_LENGTH(TRIM(besoin)) > 0),
 CONSTRAINT ck_prospects_qualification CHECK (
  (statut = 'a_contacter' AND client_id IS NULL AND qualifie_le IS NULL AND message_echec IS NULL)
  OR (statut = 'echoue' AND client_id IS NULL AND qualifie_le IS NOT NULL
      AND message_echec IS NOT NULL AND CHAR_LENGTH(TRIM(message_echec)) > 0)
  OR (statut = 'converti' AND client_id IS NOT NULL AND qualifie_le IS NOT NULL AND message_echec IS NULL)),
 INDEX ix_prospects_statut_date (statut, cree_le)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE evenements (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, client_id BIGINT UNSIGNED NOT NULL,
 prospect_id BIGINT UNSIGNED NULL, nom VARCHAR(200) NULL,
 debut DATETIME(6) NULL, fin DATETIME(6) NULL, fuseau VARCHAR(64) NOT NULL DEFAULT 'Europe/Paris',
 lieu VARCHAR(255) NULL, type_evenement VARCHAR(100) NULL, theme VARCHAR(100) NULL,
 participants_estimes INT UNSIGNED NULL, statut VARCHAR(20) NOT NULL DEFAULT 'brouillon',
 visible_demande TINYINT NOT NULL DEFAULT 0, accord_publication TINYINT NOT NULL DEFAULT 0,
 accord_recueilli_le DATETIME(6) NULL, preuve_accord TEXT NULL,
 image_reference VARCHAR(512) NULL, image_alternative VARCHAR(255) NULL,
 cree_le DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 modifie_le DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
 CONSTRAINT uq_evenements_prospect UNIQUE (prospect_id),
 CONSTRAINT fk_evenements_client FOREIGN KEY (client_id) REFERENCES clients(id),
 CONSTRAINT fk_evenements_origine FOREIGN KEY (prospect_id, client_id) REFERENCES prospects(id, client_id),
 CONSTRAINT ck_evenements_dates CHECK (debut IS NULL OR fin IS NULL OR fin > debut),
 CONSTRAINT ck_evenements_participants CHECK (participants_estimes IS NULL OR participants_estimes > 0),
 CONSTRAINT ck_evenements_statut CHECK (statut IN ('brouillon','accepte','en_cours','termine','annule')),
 CONSTRAINT ck_evenements_flags CHECK (visible_demande IN (0,1) AND accord_publication IN (0,1)),
 CONSTRAINT ck_evenements_accord CHECK (accord_publication = 0 OR
  (accord_recueilli_le IS NOT NULL AND preuve_accord IS NOT NULL AND CHAR_LENGTH(TRIM(preuve_accord)) > 0)),
 INDEX ix_evenements_client_debut (client_id, debut), INDEX ix_evenements_statut_debut (statut, debut)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE devis (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, evenement_id BIGINT UNSIGNED NOT NULL,
 reference VARCHAR(50) COLLATE utf8mb4_0900_bin NOT NULL, version_courante_id BIGINT UNSIGNED NULL,
 cree_le DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 modifie_le DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
 CONSTRAINT uq_devis_evenement UNIQUE (evenement_id), CONSTRAINT uq_devis_reference UNIQUE (reference),
 CONSTRAINT fk_devis_evenement FOREIGN KEY (evenement_id) REFERENCES evenements(id),
 CONSTRAINT ck_devis_reference CHECK (CHAR_LENGTH(TRIM(reference)) > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE versions_devis (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, devis_id BIGINT UNSIGNED NOT NULL,
 numero INT UNSIGNED NOT NULL, revision_contenu INT UNSIGNED NOT NULL DEFAULT 1,
 statut VARCHAR(30) NOT NULL DEFAULT 'brouillon', client_snapshot JSON NULL, evenement_snapshot JSON NULL,
 devise CHAR(3) COLLATE utf8mb4_0900_bin NULL, taux_tva DECIMAL(5,2) NULL, regle_calcul VARCHAR(50) NULL,
 total_ht DECIMAL(15,2) NULL, total_tva DECIMAL(15,2) NULL, total_ttc DECIMAL(15,2) NULL,
 figee_le DATETIME(6) NULL, transmise_le DATETIME(6) NULL,
 cree_le DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 modifie_le DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
 CONSTRAINT uq_versions_numero UNIQUE (devis_id, numero), CONSTRAINT uq_versions_dossier_id UNIQUE (devis_id, id),
 CONSTRAINT fk_versions_devis FOREIGN KEY (devis_id) REFERENCES devis(id),
 CONSTRAINT ck_versions_numero CHECK (numero > 0 AND revision_contenu > 0),
 CONSTRAINT ck_versions_statut CHECK (statut IN ('brouillon','etude_client','modification','accepte','refuse')),
 CONSTRAINT ck_versions_taux CHECK (taux_tva IS NULL OR taux_tva BETWEEN 0 AND 100),
 CONSTRAINT ck_versions_devise CHECK (devise IS NULL OR devise = 'EUR'),
 CONSTRAINT ck_versions_regle CHECK (regle_calcul IS NULL OR regle_calcul = 'TVA_TOTAL_2D_HALF_UP_V1'),
 CONSTRAINT ck_versions_snapshots CHECK (
  (client_snapshot IS NULL OR JSON_TYPE(client_snapshot) = 'OBJECT') AND
  (evenement_snapshot IS NULL OR JSON_TYPE(evenement_snapshot) = 'OBJECT')),
 CONSTRAINT ck_versions_totaux CHECK (
  (total_ht IS NULL AND total_tva IS NULL AND total_ttc IS NULL) OR
  (total_ht IS NOT NULL AND total_tva IS NOT NULL AND total_ttc IS NOT NULL
   AND total_ht >= 0 AND total_tva >= 0 AND total_ttc = total_ht + total_tva)),
 CONSTRAINT ck_versions_figee CHECK (figee_le IS NULL OR
  (client_snapshot IS NOT NULL AND evenement_snapshot IS NOT NULL AND devise IS NOT NULL
   AND taux_tva IS NOT NULL AND regle_calcul IS NOT NULL AND total_ht IS NOT NULL
   AND total_tva IS NOT NULL AND total_ttc IS NOT NULL)),
 CONSTRAINT ck_versions_transmission CHECK (
  (statut = 'brouillon' AND transmise_le IS NULL) OR
  (statut <> 'brouillon' AND transmise_le IS NOT NULL AND figee_le IS NOT NULL)),
 INDEX ix_versions_statut_date (statut, cree_le)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Cycle volontaire : insertion dossier NULL, première version, puis sélection
-- de cette version dans la même transaction applicative.
ALTER TABLE devis ADD CONSTRAINT fk_devis_version_courante
 FOREIGN KEY (id, version_courante_id) REFERENCES versions_devis(devis_id, id);

CREATE TABLE prestations_devis (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, version_id BIGINT UNSIGNED NOT NULL,
 ordre INT UNSIGNED NOT NULL, libelle VARCHAR(255) NOT NULL, montant_ht DECIMAL(15,2) NOT NULL,
 cree_le DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 modifie_le DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
 CONSTRAINT uq_prestations_ordre UNIQUE (version_id, ordre),
 CONSTRAINT fk_prestations_version FOREIGN KEY (version_id) REFERENCES versions_devis(id),
 CONSTRAINT ck_prestations_valeurs CHECK (ordre > 0 AND montant_ht >= 0 AND CHAR_LENGTH(TRIM(libelle)) > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE decisions_devis (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, version_id BIGINT UNSIGNED NOT NULL,
 auteur_id BIGINT UNSIGNED NOT NULL, choix VARCHAR(20) NOT NULL, motif TEXT NULL,
 decidee_le DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 CONSTRAINT uq_decisions_version UNIQUE (version_id),
 CONSTRAINT fk_decisions_version FOREIGN KEY (version_id) REFERENCES versions_devis(id),
 CONSTRAINT fk_decisions_auteur FOREIGN KEY (auteur_id) REFERENCES utilisateurs(id),
 CONSTRAINT ck_decisions_motif CHECK (
  (choix = 'modification' AND motif IS NOT NULL AND CHAR_LENGTH(TRIM(motif)) > 0)
  OR (choix IN ('accepte','refuse') AND motif IS NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE documents_devis (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, version_id BIGINT UNSIGNED NOT NULL,
 revision_contenu INT UNSIGNED NOT NULL, etat VARCHAR(20) NOT NULL DEFAULT 'en_cours',
 fichier_reference VARCHAR(512) NULL, empreinte_sha256 CHAR(64) COLLATE utf8mb4_0900_bin NULL,
 genere_le DATETIME(6) NULL, cree_le DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 modifie_le DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
 CONSTRAINT fk_documents_version FOREIGN KEY (version_id) REFERENCES versions_devis(id),
 CONSTRAINT ck_documents_revision CHECK (revision_contenu > 0),
 CONSTRAINT ck_documents_etat CHECK (etat IN ('en_cours','disponible','echec','obsolete')),
 CONSTRAINT ck_documents_disponible CHECK (etat NOT IN ('disponible','obsolete') OR
  (fichier_reference IS NOT NULL AND CHAR_LENGTH(TRIM(fichier_reference)) > 0
   AND empreinte_sha256 IS NOT NULL AND CHAR_LENGTH(empreinte_sha256) = 64 AND genere_le IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE transmissions_devis (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, document_id BIGINT UNSIGNED NOT NULL,
 acteur_id BIGINT UNSIGNED NOT NULL, mode VARCHAR(20) NOT NULL, destinataire VARCHAR(254) NOT NULL,
 etat VARCHAR(20) NOT NULL DEFAULT 'en_attente', cle_idempotence CHAR(36) COLLATE utf8mb4_0900_bin NOT NULL,
 confirmee_le DATETIME(6) NULL, cree_le DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 modifie_le DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
 CONSTRAINT uq_transmissions_idempotence UNIQUE (cle_idempotence),
 CONSTRAINT fk_transmissions_document FOREIGN KEY (document_id) REFERENCES documents_devis(id),
 CONSTRAINT fk_transmissions_acteur FOREIGN KEY (acteur_id) REFERENCES utilisateurs(id),
 CONSTRAINT ck_transmissions_mode CHECK (mode IN ('automatique','manuel')),
 CONSTRAINT ck_transmissions_etat CHECK (etat IN ('en_attente','en_cours','confirme','echec','incertain')),
 CONSTRAINT ck_transmissions_confirmation CHECK (
  (etat = 'confirme' AND confirmee_le IS NOT NULL) OR (etat <> 'confirme' AND confirmee_le IS NULL)),
 CONSTRAINT ck_transmissions_textes CHECK (CHAR_LENGTH(TRIM(destinataire)) > 0 AND CHAR_LENGTH(cle_idempotence) = 36)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE taches (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, evenement_id BIGINT UNSIGNED NOT NULL,
 assigne_a BIGINT UNSIGNED NULL, titre VARCHAR(200) NOT NULL, description TEXT NULL,
 statut VARCHAR(20) NOT NULL DEFAULT 'a_faire', cree_le DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 modifie_le DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
 CONSTRAINT fk_taches_evenement FOREIGN KEY (evenement_id) REFERENCES evenements(id),
 CONSTRAINT fk_taches_assigne FOREIGN KEY (assigne_a) REFERENCES utilisateurs(id),
 CONSTRAINT ck_taches_statut CHECK (statut IN ('a_faire','en_cours','termine')),
 CONSTRAINT ck_taches_titre CHECK (CHAR_LENGTH(TRIM(titre)) > 0), INDEX ix_taches_assigne_statut (assigne_a, statut)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE notes_evenement (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, evenement_id BIGINT UNSIGNED NOT NULL,
 auteur_id BIGINT UNSIGNED NOT NULL, contenu TEXT NOT NULL,
 cree_le DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 modifie_le DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
 CONSTRAINT fk_notes_evenement FOREIGN KEY (evenement_id) REFERENCES evenements(id),
 CONSTRAINT fk_notes_auteur FOREIGN KEY (auteur_id) REFERENCES utilisateurs(id),
 CONSTRAINT ck_notes_contenu CHECK (CHAR_LENGTH(TRIM(contenu)) > 0),
 INDEX ix_notes_evenement_date (evenement_id, cree_le), INDEX ix_notes_date (cree_le)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE notes_globales (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, auteur_id BIGINT UNSIGNED NOT NULL, contenu TEXT NOT NULL,
 cree_le DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 modifie_le DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
 CONSTRAINT fk_notes_globales_auteur FOREIGN KEY (auteur_id) REFERENCES utilisateurs(id),
 CONSTRAINT ck_notes_globales_contenu CHECK (CHAR_LENGTH(TRIM(contenu)) > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE avis (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, evenement_id BIGINT UNSIGNED NOT NULL,
 auteur_id BIGINT UNSIGNED NOT NULL, contenu TEXT NOT NULL, statut VARCHAR(20) NOT NULL DEFAULT 'en_attente',
 moderateur_id BIGINT UNSIGNED NULL, modere_le DATETIME(6) NULL,
 cree_le DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 modifie_le DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
 CONSTRAINT uq_avis_auteur_evenement UNIQUE (evenement_id, auteur_id),
 CONSTRAINT fk_avis_evenement FOREIGN KEY (evenement_id) REFERENCES evenements(id),
 CONSTRAINT fk_avis_auteur FOREIGN KEY (auteur_id) REFERENCES utilisateurs(id),
 CONSTRAINT fk_avis_moderateur FOREIGN KEY (moderateur_id) REFERENCES utilisateurs(id),
 CONSTRAINT ck_avis_contenu CHECK (CHAR_LENGTH(TRIM(contenu)) > 0),
 CONSTRAINT ck_avis_moderation CHECK (
  (statut = 'en_attente' AND moderateur_id IS NULL AND modere_le IS NULL)
  OR (statut IN ('valide','refuse') AND moderateur_id IS NOT NULL AND modere_le IS NOT NULL)),
 INDEX ix_avis_statut_date (statut, cree_le)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE parametres_application (
 cle VARCHAR(100) COLLATE utf8mb4_0900_bin PRIMARY KEY, valeur TEXT NOT NULL,
 modifie_par BIGINT UNSIGNED NULL, modifie_le DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 CONSTRAINT fk_parametres_auteur FOREIGN KEY (modifie_par) REFERENCES utilisateurs(id),
 CONSTRAINT ck_parametres_cle CHECK (CHAR_LENGTH(TRIM(cle)) > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE journal_a_emettre (
 id_trace CHAR(36) COLLATE utf8mb4_0900_bin PRIMARY KEY, horodatage DATETIME(6) NOT NULL,
 type_action VARCHAR(40) NOT NULL, id_utilisateur BIGINT UNSIGNED NULL, details JSON NOT NULL,
 version_schema INT UNSIGNED NOT NULL DEFAULT 1, etat VARCHAR(20) NOT NULL DEFAULT 'en_attente',
 tentatives INT UNSIGNED NOT NULL DEFAULT 0, prochain_essai_le DATETIME(6) NULL,
 verrou_jusqua DATETIME(6) NULL, jeton_verrou CHAR(36) COLLATE utf8mb4_0900_bin NULL,
 livre_le DATETIME(6) NULL, derniere_erreur VARCHAR(500) NULL,
 cree_le DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 CONSTRAINT ck_journal_action CHECK (type_action IN ('CONNEXION_REUSSIE','CONNEXION_ECHOUEE','CREATION_CLIENT',
  'MODIFICATION_CLIENT','SUPPRESSION_CLIENT','CREATION_EVENEMENT','CHANGEMENT_STATUT_EVENEMENT','GENERATION_PDF_DEVIS')),
 CONSTRAINT ck_journal_acteur CHECK (id_utilisateur IS NOT NULL OR type_action = 'CONNEXION_ECHOUEE'),
 CONSTRAINT ck_journal_details CHECK (JSON_TYPE(details) = 'OBJECT'),
 CONSTRAINT ck_journal_schema CHECK (version_schema = 1),
 CONSTRAINT ck_journal_etat CHECK (etat IN ('en_attente','traitement','livre','echec')),
 CONSTRAINT ck_journal_livraison CHECK ((etat = 'livre' AND livre_le IS NOT NULL) OR (etat <> 'livre' AND livre_le IS NULL)),
 CONSTRAINT ck_journal_verrou CHECK (
  (etat = 'traitement' AND verrou_jusqua IS NOT NULL AND jeton_verrou IS NOT NULL)
  OR (etat <> 'traitement' AND verrou_jusqua IS NULL AND jeton_verrou IS NULL)),
 CONSTRAINT ck_journal_trace CHECK (CHAR_LENGTH(id_trace) = 36),
 INDEX ix_journal_reprise (etat, prochain_essai_le)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO parametres_application (cle, valeur) VALUES
 ('remerciement_demande_devis', 'Merci pour votre demande. Chloé vous recontactera dans les plus brefs délais pour discuter de votre projet.');
-- Le compte Chloé et le jeu d'essai sont à initialiser séparément avec des empreintes de mots de passe valides.

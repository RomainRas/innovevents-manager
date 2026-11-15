-- Demandes de devis publiques : clé d'envoi tirée par le navigateur. Un nouvel essai avec la même clé
-- retrouve la demande déjà créée (pas de doublon) ; avec un contenu différent (empreinte), il est refusé.
CREATE TABLE IF NOT EXISTS soumissions_devis (
 cle CHAR(64) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
 empreinte CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 prospect_id BIGINT UNSIGNED NOT NULL,
 remerciement TEXT NOT NULL,
 cree_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT uq_soumission_prospect UNIQUE (prospect_id),
 CONSTRAINT fk_soumission_prospect FOREIGN KEY (prospect_id) REFERENCES prospects(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

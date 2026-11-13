-- Garde d’application dans provision-conversion.py.
ALTER TABLE prospects ADD COLUMN conversion_empreinte CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL;

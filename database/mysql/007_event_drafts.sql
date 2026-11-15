-- Brouillons d'événements : empreinte de création (nouvel essai sans doublon) et révision
-- (une modification faite entre-temps par quelqu'un d'autre est détectée).
ALTER TABLE evenements
 ADD COLUMN creation_empreinte CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
 ADD COLUMN revision INT UNSIGNED NOT NULL DEFAULT 1;

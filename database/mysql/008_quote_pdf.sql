-- PDF de devis : empreinte du contenu source, pour savoir si un PDF correspond encore au devis enregistré.
ALTER TABLE documents_devis
 ADD COLUMN source_empreinte CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL;

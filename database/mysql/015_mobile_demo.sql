SET NAMES utf8mb4;
-- Application mobile : adresse postale fictive du client de démonstration, pour l'action « Itinéraire ».
-- Appliquée seulement si la fiche n'a encore aucune adresse : une adresse saisie depuis l'application n'est jamais écrasée.
UPDATE clients c JOIN utilisateurs u ON u.id=c.utilisateur_id
SET c.adresse_ligne1='12 quai Victor Augagneur', c.code_postal='69003', c.ville='Lyon', c.pays_code='FR'
WHERE u.email_normalise='alice@example.invalid' AND c.adresse_ligne1 IS NULL AND c.ville IS NULL AND c.code_postal IS NULL;

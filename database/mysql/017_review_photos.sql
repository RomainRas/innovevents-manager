SET NAMES utf8mb4;
-- Avis publics illustrés : la vue expose l'événement publié et la présence de sa photo,
-- pour afficher l'image publique de l'événement à côté de l'avis. Rejouable (CREATE OR REPLACE).
CREATE OR REPLACE SQL SECURITY DEFINER VIEW v_avis_publics AS
SELECT a.id, a.contenu, a.modere_le AS publie_le, p.id AS evenement_id, p.nom AS evenement, p.type_evenement,
 p.image_reference IS NOT NULL AS image,
 CONCAT(u.prenom, ' ', LEFT(u.nom, 1), '.') AS auteur
FROM avis a
JOIN v_evenements_publics p ON p.id = a.evenement_id
JOIN utilisateurs u ON u.id = a.auteur_id
WHERE a.statut = 'valide';

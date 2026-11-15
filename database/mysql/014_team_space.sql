SET NAMES utf8mb4;
-- Espace employé : démonstration de tâches assignées sur deux événements à venir.
-- Aucune modification de structure : les tables taches et notes_evenement existent depuis 001.
START TRANSACTION;
SET @jose=(SELECT id FROM utilisateurs WHERE email_normalise='jose@example.invalid');
SET @chloe=(SELECT id FROM utilisateurs WHERE email_normalise='chloe@example.invalid');
INSERT INTO taches(evenement_id,assigne_a,titre,description,statut)
SELECT e.id,v.assigne,v.titre,v.description,v.statut
FROM (
 SELECT 'Lancement produit Horizon' evenement,@jose assigne,'Confirmer le traiteur' titre,'Valider le menu cocktail et le nombre de convives (90).' description,'en_cours' statut
 UNION ALL SELECT 'Lancement produit Horizon',@jose,'Réserver la sonorisation','Prévoir deux micros sans fil et une console pour les discours.','a_faire'
 UNION ALL SELECT 'Lancement produit Horizon',@chloe,'Envoyer le programme au client','Programme horaire détaillé à faire valider.','a_faire'
 UNION ALL SELECT 'Ateliers innovation Horizon',@jose,'Commander le matériel d’atelier','Post-it, marqueurs et paperboards pour 30 personnes.','a_faire'
 UNION ALL SELECT 'Ateliers innovation Horizon',NULL,'Repérage du lieu','Tâche non assignée : à répartir en réunion d’équipe.','a_faire'
) v JOIN evenements e ON e.nom=v.evenement
WHERE @jose IS NOT NULL AND @chloe IS NOT NULL
 AND NOT EXISTS (SELECT 1 FROM taches t WHERE t.evenement_id=e.id AND t.titre=v.titre);
COMMIT;

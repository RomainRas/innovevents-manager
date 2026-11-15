<?php
declare(strict_types=1);

namespace InnovEvents\Service;

use InnovEvents\Http\HttpError;
use InnovEvents\Infrastructure\Connections;

/**
 * Gestion par l'administratrice (Chloé) des fiches clients, des événements, de leur statut et des tâches.
 * Journal exigé : CREATION_CLIENT, MODIFICATION_CLIENT, SUPPRESSION_CLIENT, CREATION_EVENEMENT, CHANGEMENT_STATUT_EVENEMENT.
 * Les suppressions sont refusées dès qu'elles effaceraient un historique commercial (devis, demande, compte) :
 * un événement avec devis s'annule, un client avec événements ou compte se conserve.
 */
final class AdminRecords
{
    /** Statuts qu'on peut donner à un événement depuis l'écran de gestion (l'énoncé : accepté, en cours, terminé, annulé). */
    private const STATUSES = ['accepte', 'en_cours', 'termine', 'annule'];

    private static function id(mixed $v, string $error = 'invalid_id'): string
    {
        if (!(is_int($v) || is_string($v)) || !preg_match('/^[1-9][0-9]{0,17}$/D', (string) $v)) {
            throw new HttpError(422, $error);
        }
        return (string) $v;
    }

    /**
     * Lit un champ texte du formulaire : renvoie la valeur nettoyée, ou null si elle est vide ou invalide.
     * Les erreurs sont ajoutées dans $errors (passé par référence) pour être toutes renvoyées ensemble.
     */
    private static function text(array $input, string $key, int $max, bool $required, array &$errors): ?string
    {
        $value = $input[$key] ?? '';
        if (!is_string($value) || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value) || !mb_check_encoding($value, 'UTF-8')
            || preg_match_all('/./us', trim($value)) > $max) {
            $errors[$key] = "Maximum $max caractères.";
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            if ($required) {
                $errors[$key] = "Renseignez ce champ (maximum $max caractères).";
            }
            return null;
        }
        return $value;
    }

    /** Ajoute une entrée au journal, dans la transaction en cours ; journal_worker la livre ensuite dans MongoDB. */
    private static function log(\PDO $db, string $type, string $user, array $details): void
    {
        $db->prepare('INSERT INTO journal_a_emettre(id_trace, horodatage, type_action, id_utilisateur, details) VALUES(UUID(), UTC_TIMESTAMP(6), ?, ?, ?)')
            ->execute([$type, $user, json_encode($details, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)]);
    }

    /** Les suppressions exigent une confirmation explicite envoyée par l'interface. */
    private static function confirmed(array $input): void
    {
        if (($input['confirmation'] ?? null) !== true) {
            throw new HttpError(422, 'confirmation_required');
        }
    }

    // ---- Clients -------------------------------------------------------------------------------------------

    /**
     * Validation pure de la fiche client (sans base de données), testée unitairement.
     * L'ordre des clés renvoyées correspond aux colonnes des requêtes INSERT et UPDATE.
     */
    public static function validateClient(array $input): array
    {
        $errors = [];
        $data = [];

        foreach (['entreprise' => 200, 'contact_prenom' => 100, 'contact_nom' => 100, 'email' => 254, 'telephone' => 32] as $key => $max) {
            $data[$key] = self::text($input, $key, $max, true, $errors);
        }
        foreach (['adresse_ligne1' => 255, 'adresse_ligne2' => 255, 'code_postal' => 20, 'ville' => 120] as $key => $max) {
            $data[$key] = self::text($input, $key, $max, false, $errors);
        }

        if ($data['email'] !== null && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Saisissez une adresse électronique valide.';
        }
        if ($data['telephone'] !== null && (!preg_match('/^[+0-9(). \-]+$/', $data['telephone']) || strlen(preg_replace('/\D/', '', $data['telephone'])) < 6)) {
            $errors['telephone'] = 'Saisissez un numéro de téléphone valide.';
        }
        if ($data['code_postal'] !== null && !preg_match('/^[A-Za-z0-9 \-]{2,20}$/', $data['code_postal'])) {
            $errors['code_postal'] = 'Saisissez un code postal valide.';
        }
        if ($errors) {
            throw new HttpError(422, 'validation_failed', $errors);
        }

        // Pays renseigné seulement quand une adresse l'est (agence française).
        $data['pays_code'] = ($data['adresse_ligne1'] || $data['ville']) ? 'FR' : null;
        return $data;
    }

    /** Crée une fiche client (journal CREATION_CLIENT avec l'identifiant et le nom). */
    public function createClient(array $input): array
    {
        $user = (new Authentication())->requireUser(['administrateur']);
        $data = self::validateClient($input);
        $db = Connections::records();

        try {
            $db->beginTransaction();
            $db->prepare('INSERT INTO clients(entreprise, contact_prenom, contact_nom, email, telephone, adresse_ligne1, adresse_ligne2, code_postal, ville, pays_code) VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute(array_values($data));
            $id = (string) $db->lastInsertId();
            self::log($db, 'CREATION_CLIENT', (string) $user['id'], ['id_client' => $id, 'nom_client' => $data['entreprise']]);
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        return ['id' => $id];
    }

    /** Modifie une fiche client (journal MODIFICATION_CLIENT). */
    public function saveClient(array $input): array
    {
        $user = (new Authentication())->requireUser(['administrateur']);
        $id = self::id($input['id'] ?? null);
        $data = self::validateClient($input);
        $db = Connections::records();

        try {
            $db->beginTransaction();
            $query = $db->prepare('SELECT utilisateur_id FROM clients WHERE id = ? FOR UPDATE');
            $query->execute([$id]);
            if (!$query->fetch()) {
                throw new HttpError(404, 'client_not_found');
            }

            // L'adresse de connexion d'un client avec compte se change par la gestion des comptes, pas par la fiche.
            $db->prepare('UPDATE clients SET entreprise = ?, contact_prenom = ?, contact_nom = ?, email = ?, telephone = ?, adresse_ligne1 = ?, adresse_ligne2 = ?, code_postal = ?, ville = ?, pays_code = ? WHERE id = ?')
                ->execute([...array_values($data), $id]);
            self::log($db, 'MODIFICATION_CLIENT', (string) $user['id'], ['id_client' => $id]);
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        return ['id' => $id];
    }

    /** Supprime une fiche client sans compte, sans événement ni demande de devis (journal SUPPRESSION_CLIENT). */
    public function deleteClient(array $input): array
    {
        $user = (new Authentication())->requireUser(['administrateur']);
        $id = self::id($input['id'] ?? null);
        self::confirmed($input);
        $db = Connections::records();

        try {
            $db->beginTransaction();
            $query = $db->prepare('SELECT entreprise, utilisateur_id FROM clients WHERE id = ? FOR UPDATE');
            $query->execute([$id]);
            $row = $query->fetch();
            if (!$row) {
                throw new HttpError(404, 'client_not_found');
            }

            if ($row['utilisateur_id'] !== null) {
                throw new HttpError(409, 'client_has_account');
            }
            $query = $db->prepare('SELECT COUNT(*) FROM evenements WHERE client_id = ?');
            $query->execute([$id]);
            if ((int) $query->fetchColumn() > 0) {
                throw new HttpError(409, 'client_has_events');
            }
            $query = $db->prepare('SELECT COUNT(*) FROM prospects WHERE client_id = ?');
            $query->execute([$id]);
            if ((int) $query->fetchColumn() > 0) {
                throw new HttpError(409, 'client_has_requests');
            }

            $db->prepare('DELETE FROM clients WHERE id = ?')->execute([$id]);
            self::log($db, 'SUPPRESSION_CLIENT', (string) $user['id'], ['id_client' => $id, 'nom_client' => $row['entreprise']]);
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        return ['id' => $id, 'supprime' => true];
    }

    // ---- Événements ----------------------------------------------------------------------------------------

    /** Validation pure d'un événement (sans base de données), testée unitairement. */
    public static function validateEvent(array $input): array
    {
        $errors = [];
        $data = [];

        $data['nom'] = self::text($input, 'nom', 200, true, $errors);
        foreach (['lieu' => 255, 'type_evenement' => 100, 'theme' => 100] as $key => $max) {
            $data[$key] = self::text($input, $key, $max, false, $errors);
        }

        // Dates saisies en heure de Paris, converties en UTC (voir EventSchedule).
        foreach (['debut', 'fin'] as $key) {
            try {
                $data[$key] = EventSchedule::utc($input[$key] ?? null);
            } catch (\InvalidArgumentException $e) {
                $errors[$key] = $e->getMessage();
            }
        }
        if (!isset($errors['debut']) && !isset($errors['fin']) && $data['debut'] && $data['fin'] && $data['fin'] <= $data['debut']) {
            $errors['fin'] = 'La fin doit être postérieure au début.';
        }

        $value = $input['participants_estimes'] ?? null;
        if ($value === '' || $value === null) {
            $data['participants_estimes'] = null;
        } elseif (!(is_int($value) || is_string($value)) || !preg_match('/^[1-9][0-9]{0,9}$/', (string) $value) || (float) $value > 4294967295) {
            $errors['participants_estimes'] = 'Saisissez un entier strictement positif.';
        } else {
            $data['participants_estimes'] = (int) $value;
        }

        if ($errors) {
            throw new HttpError(422, 'validation_failed', $errors);
        }
        return $data;
    }

    /** Personnes de l'équipe à qui assigner une tâche, et liste des clients pour le formulaire d'événement. */
    public function staff(): array
    {
        (new Authentication())->requireUser(['administrateur']);

        $team = Connections::records()->query("SELECT id, prenom, nom, role FROM utilisateurs WHERE role IN ('employe','administrateur') AND statut = 'actif' AND supprime_le IS NULL ORDER BY prenom, nom, id")->fetchAll();
        $clients = Connections::records()->query('SELECT id, entreprise FROM clients ORDER BY entreprise, id LIMIT 500')->fetchAll();

        return [
            'equipe'  => array_map(static fn($r) => ['id' => (string) $r['id'], 'nom' => $r['prenom'] . ' ' . $r['nom'], 'role' => $r['role']], $team),
            'clients' => array_map(static fn($r) => ['id' => (string) $r['id'], 'entreprise' => $r['entreprise']], $clients),
        ];
    }

    /** Événement à modifier (avec sa révision et la présence d'un devis), plus l'équipe et les clients. */
    public function eventForEdit(array $input): array
    {
        (new Authentication())->requireUser(['administrateur']);
        $id = self::id($input['id'] ?? null);

        $query = Connections::records()->prepare('SELECT e.id, e.client_id, e.nom, e.debut, e.fin, e.lieu, e.type_evenement, e.theme, e.participants_estimes, e.statut, e.revision,
            (SELECT COUNT(*) FROM devis d WHERE d.evenement_id = e.id) AS devis FROM evenements e WHERE e.id = ?');
        $query->execute([$id]);
        $event = $query->fetch();
        if (!$event) {
            throw new HttpError(404, 'event_not_found');
        }

        return ['evenement' => [
            'id'                   => (string) $event['id'],
            'client_id'            => (string) $event['client_id'],
            'nom'                  => $event['nom'],
            'debut'                => EventSchedule::local($event['debut']),
            'fin'                  => EventSchedule::local($event['fin']),
            'lieu'                 => $event['lieu'],
            'type_evenement'       => $event['type_evenement'],
            'theme'                => $event['theme'],
            'participants_estimes' => $event['participants_estimes'] === null ? null : (int) $event['participants_estimes'],
            'statut'               => $event['statut'],
            'revision'             => (int) $event['revision'],
            'avec_devis'           => (int) $event['devis'] > 0,
        ]] + $this->staff();
    }

    /** Crée un événement brouillon pour un client, sans passer par une demande de devis (journal CREATION_EVENEMENT). */
    public function createEvent(array $input): array
    {
        $user = (new Authentication())->requireUser(['administrateur']);
        $client = self::id($input['client_id'] ?? null, 'invalid_client');
        $data = self::validateEvent($input);
        $db = Connections::records();

        try {
            $db->beginTransaction();
            $query = $db->prepare('SELECT id FROM clients WHERE id = ?');
            $query->execute([$client]);
            if ($query->fetchColumn() === false) {
                throw new HttpError(404, 'client_not_found');
            }

            $db->prepare("INSERT INTO evenements(client_id, nom, debut, fin, fuseau, lieu, type_evenement, theme, participants_estimes) VALUES(?, ?, ?, ?, 'Europe/Paris', ?, ?, ?, ?)")
                ->execute([$client, $data['nom'], $data['debut'], $data['fin'], $data['lieu'], $data['type_evenement'], $data['theme'], $data['participants_estimes']]);
            $id = (string) $db->lastInsertId();
            self::log($db, 'CREATION_EVENEMENT', (string) $user['id'], ['id_evenement' => $id, 'nom_evenement' => $data['nom']]);
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        return ['id' => $id];
    }

    /** Modification avec contrôle de révision : deux personnes ne s'écrasent pas sans le savoir. */
    public function saveEvent(array $input): array
    {
        (new Authentication())->requireUser(['administrateur']);
        $id = self::id($input['id'] ?? null);
        $revision = self::id($input['revision'] ?? null, 'invalid_revision');
        $data = self::validateEvent($input);
        $db = Connections::records();

        try {
            $db->beginTransaction();
            $query = $db->prepare('SELECT statut, revision FROM evenements WHERE id = ? FOR UPDATE');
            $query->execute([$id]);
            $row = $query->fetch();
            if (!$row) {
                throw new HttpError(404, 'event_not_found');
            }
            if ($row['statut'] === 'annule') {
                throw new HttpError(409, 'event_cancelled');
            }
            if ((string) $row['revision'] !== $revision) {
                throw new HttpError(409, 'event_revision_conflict');
            }

            $db->prepare('UPDATE evenements SET nom = ?, debut = ?, fin = ?, lieu = ?, type_evenement = ?, theme = ?, participants_estimes = ?, revision = revision + 1 WHERE id = ?')
                ->execute([$data['nom'], $data['debut'], $data['fin'], $data['lieu'], $data['type_evenement'], $data['theme'], $data['participants_estimes'], $id]);
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        return $this->eventForEdit(['id' => $id]);
    }

    /**
     * Change le statut d'un événement (journal CHANGEMENT_STATUT_EVENEMENT avec l'ancien et le nouveau statut).
     * « Annulé » est définitif ; le nom et les dates sont exigés pour tout autre statut.
     */
    public function setEventStatus(array $input): array
    {
        $user = (new Authentication())->requireUser(['administrateur']);
        $id = self::id($input['id'] ?? null);
        $to = $input['statut'] ?? null;
        if (!in_array($to, self::STATUSES, true)) {
            throw new HttpError(422, 'invalid_status');
        }
        $db = Connections::records();

        try {
            $db->beginTransaction();
            $query = $db->prepare('SELECT statut, nom, debut, fin FROM evenements WHERE id = ? FOR UPDATE');
            $query->execute([$id]);
            $row = $query->fetch();
            if (!$row) {
                throw new HttpError(404, 'event_not_found');
            }
            if ($row['statut'] === 'annule') {
                throw new HttpError(409, 'event_cancelled');
            }
            if ($row['statut'] === $to) {
                throw new HttpError(409, 'status_unchanged');
            }
            if ($to !== 'annule' && ($row['nom'] === null || $row['debut'] === null || $row['fin'] === null)) {
                throw new HttpError(422, 'event_incomplete');
            }

            $db->prepare('UPDATE evenements SET statut = ?, revision = revision + 1 WHERE id = ?')->execute([$to, $id]);
            self::log($db, 'CHANGEMENT_STATUT_EVENEMENT', (string) $user['id'], ['id_evenement' => $id, 'ancien_statut' => $row['statut'], 'nouveau_statut' => $to]);
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        return $this->eventForEdit(['id' => $id]);
    }

    /** Suppression réservée aux événements sans devis ; ses notes, tâches et avis sont supprimés avec lui. */
    public function deleteEvent(array $input): array
    {
        (new Authentication())->requireUser(['administrateur']);
        $id = self::id($input['id'] ?? null);
        self::confirmed($input);
        $db = Connections::records();

        try {
            $db->beginTransaction();
            $query = $db->prepare('SELECT id FROM evenements WHERE id = ? FOR UPDATE');
            $query->execute([$id]);
            if ($query->fetchColumn() === false) {
                throw new HttpError(404, 'event_not_found');
            }

            $query = $db->prepare('SELECT COUNT(*) FROM devis WHERE evenement_id = ?');
            $query->execute([$id]);
            if ((int) $query->fetchColumn() > 0) {
                throw new HttpError(409, 'event_has_quote');
            }

            // Noms de tables issus d'une liste fixe (jamais d'une saisie).
            foreach (['notes_evenement', 'taches', 'avis'] as $table) {
                $db->prepare("DELETE FROM $table WHERE evenement_id = ?")->execute([$id]);
            }
            $db->prepare('DELETE FROM evenements WHERE id = ?')->execute([$id]);
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        return ['id' => $id, 'supprime' => true];
    }

    // ---- Tâches --------------------------------------------------------------------------------------------

    /** Crée une tâche sur un événement non annulé, assignée à une personne active de l'équipe ou à personne. */
    public function createTask(array $input): array
    {
        (new Authentication())->requireUser(['administrateur']);
        $event = self::id($input['evenement_id'] ?? null);

        $errors = [];
        $title = self::text($input, 'titre', 200, true, $errors);
        $description = self::text($input, 'description', 2000, false, $errors);
        $assignee = $input['assigne_a'] ?? '';
        if ($assignee !== '' && $assignee !== null && !(is_string($assignee) && preg_match('/^[1-9][0-9]{0,17}$/D', $assignee))) {
            $errors['assigne_a'] = 'Choisissez une personne de l’équipe.';
        }
        if ($errors) {
            throw new HttpError(422, 'validation_failed', $errors);
        }

        $db = Connections::records();
        $query = $db->prepare('SELECT statut FROM evenements WHERE id = ?');
        $query->execute([$event]);
        $status = $query->fetchColumn();
        if ($status === false) {
            throw new HttpError(404, 'event_not_found');
        }
        if ($status === 'annule') {
            throw new HttpError(409, 'event_cancelled');
        }

        if ($assignee !== '' && $assignee !== null) {
            // Jamais un client ni un compte suspendu ou supprimé.
            $query = $db->prepare("SELECT id FROM utilisateurs WHERE id = ? AND role IN ('employe','administrateur') AND statut = 'actif' AND supprime_le IS NULL");
            $query->execute([$assignee]);
            if ($query->fetchColumn() === false) {
                throw new HttpError(422, 'validation_failed', ['assigne_a' => 'Choisissez une personne active de l’équipe.']);
            }
        } else {
            $assignee = null;
        }

        $db->prepare('INSERT INTO taches(evenement_id, assigne_a, titre, description) VALUES(?, ?, ?, ?)')->execute([$event, $assignee, $title, $description]);

        return ['id' => (string) $db->lastInsertId()];
    }

    /** Supprime une tâche. */
    public function deleteTask(array $input): array
    {
        (new Authentication())->requireUser(['administrateur']);
        $id = self::id($input['id'] ?? null);

        $query = Connections::records()->prepare('DELETE FROM taches WHERE id = ?');
        $query->execute([$id]);
        if ($query->rowCount() !== 1) {
            throw new HttpError(404, 'task_not_found');
        }

        return ['id' => $id, 'supprime' => true];
    }
}

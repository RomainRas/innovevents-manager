<?php
declare(strict_types=1);

namespace InnovEvents\Service;

use InnovEvents\Http\HttpError;
use InnovEvents\Infrastructure\Connections;

/**
 * Brouillon d'événement créé à partir d'une demande convertie (administratrice).
 * Un seul événement par demande ; création journalisée (CREATION_EVENEMENT).
 * Pour la gestion complète des événements (statuts, suppression…), voir AdminRecords.
 */
final class EventDrafts
{
    private function id(mixed $id): string
    {
        if (!(is_int($id) || is_string($id)) || !preg_match('/^[1-9][0-9]{0,17}$/', (string) $id)) {
            throw new HttpError(422, 'invalid_id');
        }
        return (string) $id;
    }

    /** Informations de la demande convertie, proposées pour préremplir l'événement. */
    public function context(array $input): array
    {
        (new Authentication())->requireUser(['administrateur']);
        $db = Connections::events();

        $query = $db->prepare('SELECT p.*, c.entreprise AS client_entreprise, e.id AS evenement_id FROM prospects p JOIN clients c ON c.id = p.client_id LEFT JOIN evenements e ON e.prospect_id = p.id WHERE p.id = ? AND p.statut = ?');
        $query->execute([$this->id($input['prospect_id'] ?? null), 'converti']);
        $prospect = $query->fetch();
        if (!$prospect) {
            throw new HttpError(404, 'converted_prospect_not_found');
        }

        return [
            'client_id'         => (string) $prospect['client_id'],
            'client_entreprise' => $prospect['client_entreprise'],
            'event_id'          => $prospect['evenement_id'] === null ? null : (string) $prospect['evenement_id'],
            'suggested'         => [
                'nom'                  => $prospect['type_evenement'],
                'lieu'                 => $prospect['lieu'],
                'type_evenement'       => $prospect['type_evenement'],
                'participants_estimes' => $prospect['participants_estimes'],
                'date_souhaitee'       => $prospect['date_souhaitee'],
            ],
        ];
    }

    /** Événement sous la forme envoyée au navigateur (dates en heure de Paris). */
    private function output(array $row): array
    {
        return [
            'id'                   => (string) $row['id'],
            'client_id'            => (string) $row['client_id'],
            'prospect_id'          => $row['prospect_id'] === null ? null : (string) $row['prospect_id'],
            'nom'                  => $row['nom'],
            'lieu'                 => $row['lieu'],
            'type_evenement'       => $row['type_evenement'],
            'participants_estimes' => $row['participants_estimes'],
            'debut'                => EventSchedule::local($row['debut']),
            'fin'                  => EventSchedule::local($row['fin']),
            'fuseau'               => $row['fuseau'],
            'statut'               => $row['statut'],
            'revision'             => (int) $row['revision'],
            'has_image'            => $row['image_reference'] !== null,
            'image_alternative'    => $row['image_alternative'],
        ];
    }

    /** Fiche d'un événement pour l'écran de modification du brouillon. */
    public function detail(array $input): array
    {
        (new Authentication())->requireUser(['administrateur']);

        $query = Connections::events()->prepare('SELECT * FROM evenements WHERE id = ?');
        $query->execute([$this->id($input['id'] ?? null)]);
        $row = $query->fetch();
        if (!$row) {
            throw new HttpError(404, 'event_not_found');
        }

        return ['event' => $this->output($row)];
    }

    /** Contrôle des champs d'un brouillon : nom obligatoire, lieu et type facultatifs, dates de Paris, participants entiers. */
    private function validate(array $input): array
    {
        $data = [];
        $errors = [];

        foreach (['nom' => 200, 'lieu' => 255, 'type_evenement' => 100] as $field => $max) {
            $value = $input[$field] ?? '';
            if (!is_string($value) || preg_match_all('/./us', $value) > $max || preg_match('/[\x00-\x1F\x7F]/', $value) || ($field === 'nom' && trim($value) === '')) {
                $errors[$field] = "Vérifiez ce champ (maximum $max caractères).";
            } else {
                $data[$field] = trim($value) === '' ? null : trim($value);
            }
        }

        foreach (['debut', 'fin'] as $field) {
            try {
                $data[$field] = EventSchedule::utc($input[$field] ?? null);
            } catch (\InvalidArgumentException $e) {
                $errors[$field] = $e->getMessage();
            }
        }
        if (!isset($errors['debut']) && !isset($errors['fin']) && ($data['debut'] ?? null) && ($data['fin'] ?? null) && $data['fin'] <= $data['debut']) {
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

    /**
     * Crée le brouillon d'événement d'une demande convertie.
     * Une empreinte des données permet de répéter la même création sans doublon ; données différentes = 409.
     */
    public function create(array $input): array
    {
        $user = (new Authentication())->requireUser(['administrateur']);
        $id = $this->id($input['prospect_id'] ?? null);
        $data = $this->validate($input);
        $hash = hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
        $db = Connections::events();

        // Verrou nommé par demande : deux créations simultanées ne peuvent pas aboutir (sans droit d'écriture sur les prospects).
        $lock = 'innovevents.event.' . $id;
        $query = $db->prepare('SELECT GET_LOCK(?, 5)');
        $query->execute([$lock]);
        if ((int) $query->fetchColumn() !== 1) {
            throw new HttpError(503, 'event_busy');
        }

        try {
            $db->beginTransaction();

            $query = $db->prepare('SELECT client_id, statut FROM prospects WHERE id = ? FOR SHARE');
            $query->execute([$id]);
            $prospect = $query->fetch();
            if (!$prospect) {
                throw new HttpError(404, 'prospect_not_found');
            }
            if ($prospect['statut'] !== 'converti') {
                throw new HttpError(409, 'prospect_not_converted');
            }

            $query = $db->prepare('SELECT id, creation_empreinte FROM evenements WHERE prospect_id = ?');
            $query->execute([$id]);
            $existing = $query->fetch();
            if ($existing) {
                if (!is_string($existing['creation_empreinte']) || !hash_equals($existing['creation_empreinte'], $hash)) {
                    throw new HttpError(409, 'event_already_exists');
                }
                $db->commit();
                return ['event_id' => (string) $existing['id']];
            }

            $db->prepare('INSERT INTO evenements(client_id, prospect_id, nom, lieu, type_evenement, debut, fin, participants_estimes, fuseau, creation_empreinte) VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute([$prospect['client_id'], $id, $data['nom'], $data['lieu'], $data['type_evenement'], $data['debut'], $data['fin'], $data['participants_estimes'], 'Europe/Paris', $hash]);
            $eventId = $db->lastInsertId();

            // Journal exigé : création d'un événement (identifiant et nom).
            $db->prepare("INSERT INTO journal_a_emettre(id_trace, horodatage, type_action, id_utilisateur, details) VALUES(UUID(), UTC_TIMESTAMP(6), 'CREATION_EVENEMENT', ?, ?)")
                ->execute([$user['id'], json_encode(['id_evenement' => (string) $eventId, 'nom_evenement' => $data['nom']], JSON_THROW_ON_ERROR)]);

            $db->commit();
            return ['event_id' => (string) $eventId];
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        } finally {
            $db->prepare('SELECT RELEASE_LOCK(?)')->execute([$lock]);
        }
    }

    /**
     * Modifie un brouillon, avec contrôle de révision (pas d'écrasement d'une modification plus récente).
     * Un enregistrement répété à l'identique après une réponse perdue est accepté sans rien refaire.
     */
    public function save(array $input): array
    {
        (new Authentication())->requireUser(['administrateur']);
        $id = $this->id($input['id'] ?? null);
        $revision = $this->id($input['revision'] ?? null);
        $data = $this->validate($input);
        $db = Connections::events();

        $db->beginTransaction();
        try {
            $query = $db->prepare('SELECT * FROM evenements WHERE id = ? FOR UPDATE');
            $query->execute([$id]);
            $row = $query->fetch();
            if (!$row) {
                throw new HttpError(404, 'event_not_found');
            }
            if ($row['statut'] !== 'brouillon') {
                throw new HttpError(409, 'event_not_draft');
            }

            // Les valeurs en base sont-elles déjà exactement celles envoyées ?
            $same = true;
            foreach ($data as $key => $value) {
                $actual = $row[$key];
                if (in_array($key, ['debut', 'fin'], true) && $actual !== null) {
                    $actual = substr($actual, 0, 19);
                }
                if ($key === 'participants_estimes' && $actual !== null) {
                    $actual = (int) $actual;
                }
                if ($actual !== $value) {
                    $same = false;
                }
            }

            if ((string) $row['revision'] !== $revision) {
                if ($same && (int) $row['revision'] === (int) $revision + 1) {
                    $db->commit();
                    return ['event_id' => $id, 'revision' => (int) $row['revision']];
                }
                throw new HttpError(409, 'event_revision_conflict');
            }

            $db->prepare('UPDATE evenements SET nom = ?, lieu = ?, type_evenement = ?, debut = ?, fin = ?, participants_estimes = ?, revision = revision + 1 WHERE id = ?')
                ->execute([$data['nom'], $data['lieu'], $data['type_evenement'], $data['debut'], $data['fin'], $data['participants_estimes'], $id]);
            $db->commit();

            return ['event_id' => $id, 'revision' => (int) $revision + 1];
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }
}

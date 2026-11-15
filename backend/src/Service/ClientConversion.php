<?php
declare(strict_types=1);

namespace InnovEvents\Service;

use InnovEvents\Http\HttpError;
use InnovEvents\Infrastructure\Connections;

/**
 * Conversion d'un prospect en client (administratrice).
 * Crée la fiche client, marque la demande « convertie » et journalise CREATION_CLIENT dans la même transaction.
 */
final class ClientConversion
{
    private function id(mixed $id): string
    {
        if (!(is_string($id) || is_int($id)) || !preg_match('/^[1-9][0-9]{0,17}$/', (string) $id)) {
            throw new HttpError(422, 'invalid_id');
        }
        return (string) $id;
    }

    /** Fiche d'un client et liste des demandes converties qui lui sont rattachées. */
    public function detail(array $input): array
    {
        (new Authentication())->requireUser(['administrateur']);

        $query = Connections::conversion()->prepare(
            'SELECT id, entreprise, contact_nom, contact_prenom, email, telephone FROM clients WHERE id = ?'
        );
        $query->execute([$this->id($input['id'] ?? null)]);
        $client = $query->fetch();
        if (!$client) {
            throw new HttpError(404, 'client_not_found');
        }

        $query = Connections::conversion()->prepare(
            "SELECT id, type_evenement, date_souhaitee FROM prospects WHERE client_id = ? AND statut = 'converti' ORDER BY id DESC"
        );
        $query->execute([$client['id']]);

        return ['client' => $client, 'demandes' => $query->fetchAll()];
    }

    /**
     * Convertit le prospect : crée la fiche client avec les coordonnées vérifiées par l'administratrice.
     * Une empreinte des données permet de répéter la même demande sans créer de doublon.
     */
    public function convert(array $input): array
    {
        $user = (new Authentication())->requireUser(['administrateur']);
        $id = $this->id($input['id'] ?? null);

        // Validation des champs (l'ordre compte : il correspond aux colonnes de l'INSERT plus bas).
        $data = [];
        $errors = [];
        foreach (['entreprise' => 200, 'contact_nom' => 100, 'contact_prenom' => 100, 'email' => 254, 'telephone' => 32] as $key => $max) {
            $value = $input[$key] ?? null;
            if (!is_string($value) || trim($value) === '' || preg_match_all('/./us', $value) > $max || preg_match('/[\x00-\x1F\x7F]/', $value)) {
                $errors[$key] = "Renseignez ce champ (maximum $max caractères).";
            } else {
                $data[$key] = trim($value);
            }
        }
        if (isset($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Adresse électronique invalide.';
        }
        if (isset($data['telephone']) && (!preg_match('/^[+0-9(). \-]+$/', $data['telephone']) || strlen(preg_replace('/\D/', '', $data['telephone'])) < 6)) {
            $errors['telephone'] = 'Téléphone invalide.';
        }
        if ($errors) {
            throw new HttpError(422, 'validation_failed', $errors);
        }

        $hash = hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
        $db = Connections::conversion();
        $db->beginTransaction();
        try {
            $query = $db->prepare('SELECT statut, client_id, conversion_empreinte FROM prospects WHERE id = ? FOR UPDATE');
            $query->execute([$id]);
            $row = $query->fetch();
            if (!$row) {
                throw new HttpError(404, 'prospect_not_found');
            }

            // Même conversion déjà faite avec les mêmes données (réponse perdue, double clic) : on renvoie le client existant.
            if ($row['statut'] === 'converti' && is_string($row['conversion_empreinte']) && hash_equals($row['conversion_empreinte'], $hash)) {
                $db->commit();
                return ['client_id' => (string) $row['client_id'], 'status' => 'converti'];
            }
            if ($row['statut'] !== 'a_contacter') {
                throw new HttpError(409, 'prospect_already_processed');
            }

            $db->prepare('INSERT INTO clients(entreprise, contact_nom, contact_prenom, email, telephone) VALUES(?, ?, ?, ?, ?)')
                ->execute(array_values($data));
            $clientId = $db->lastInsertId();

            $db->prepare("UPDATE prospects SET statut = 'converti', client_id = ?, qualifie_le = UTC_TIMESTAMP(6), conversion_empreinte = ? WHERE id = ?")
                ->execute([$clientId, $hash, $id]);

            // Journal exigé par l'énoncé : création d'un client (identifiant et nom).
            // Écrit dans MySQL avec le reste, puis livré dans MongoDB par le traitement journal_worker.
            $db->prepare("INSERT INTO journal_a_emettre(id_trace, horodatage, type_action, id_utilisateur, details) VALUES(UUID(), UTC_TIMESTAMP(6), 'CREATION_CLIENT', ?, ?)")
                ->execute([$user['id'], json_encode(['id_client' => (string) $clientId, 'nom_client' => $data['entreprise']], JSON_THROW_ON_ERROR)]);

            $db->commit();
            return ['client_id' => (string) $clientId, 'status' => 'converti'];
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }
}

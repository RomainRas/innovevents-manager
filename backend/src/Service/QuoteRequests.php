<?php
declare(strict_types=1);

namespace InnovEvents\Service;

use InnovEvents\Http\HttpError;
use InnovEvents\Infrastructure\Connections;
use InnovEvents\Security\LoginLimiter;

/**
 * Demande de devis du formulaire public : crée un prospect et prévient l'agence par courriel.
 * Le message de remerciement affiché ensuite est modifiable par l'administratrice.
 */
final class QuoteRequests
{
    /** Message de remerciement affiché après l'envoi du formulaire. */
    public function config(): array
    {
        $query = Connections::mysql()->prepare('SELECT valeur FROM parametres_application WHERE cle = ?');
        $query->execute(['remerciement_demande_devis']);
        $value = $query->fetchColumn();
        if (!is_string($value) || $value === '') {
            throw new \RuntimeException('Missing quote configuration');
        }

        return ['message' => $value];
    }

    /** Modification du message de remerciement par l'administratrice. */
    public function saveThanks(array $input): array
    {
        $user = (new Authentication())->requireUser(['administrateur']);

        $message = $input['message'] ?? null;
        if (!is_string($message) || trim($message) === '' || preg_match_all('/./us', $message) > 2000
            || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $message)) {
            throw new HttpError(422, 'invalid_message');
        }

        Connections::settings()
            ->prepare('UPDATE parametres_application SET valeur = ?, modifie_par = ? WHERE cle = ?')
            ->execute([trim($message), $user['id'], 'remerciement_demande_devis']);

        return ['message' => trim($message)];
    }

    /**
     * Contrôle tous les champs du formulaire et renvoie les valeurs nettoyées.
     * L'ordre des champs compte : il sert à calculer l'empreinte de la demande (voir submit()).
     */
    private function validate(array $input): array
    {
        $data = [];
        $errors = [];

        $limits = ['entreprise' => 200, 'nom' => 100, 'prenom' => 100, 'email' => 254, 'telephone' => 32,
            'lieu' => 255, 'type_evenement' => 100, 'date_souhaitee' => 10, 'besoin' => 5000];
        foreach ($limits as $key => $max) {
            $value = $input[$key] ?? null;
            if (!is_string($value) || trim($value) === '' || preg_match_all('/./us', $value) > $max
                || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value)) {
                $errors[$key] = "Renseignez ce champ (maximum $max caractères).";
                continue;
            }
            $data[$key] = trim($value);
        }

        if (isset($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Saisissez une adresse électronique valide.';
        }
        if (isset($data['telephone']) && (!preg_match('/^[+0-9(). \-]+$/', $data['telephone']) || strlen(preg_replace('/\D/', '', $data['telephone'])) < 6)) {
            $errors['telephone'] = 'Saisissez un numéro de téléphone valide.';
        }
        if (isset($data['date_souhaitee'])) {
            // Lecture stricte : « 2027-02-30 » est refusé au lieu d'être converti en 2 mars.
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $data['date_souhaitee']);
            if (!$date || $date->format('Y-m-d') !== $data['date_souhaitee'] || $data['date_souhaitee'] < '1000-01-01') {
                $errors['date_souhaitee'] = 'Saisissez une date valide.';
            }
        }

        // Nombre entier strictement positif, dans la limite d'un entier MySQL non signé.
        $count = $input['participants_estimes'] ?? null;
        if (!(is_int($count) || is_string($count)) || !preg_match('/^[1-9][0-9]{0,9}$/', (string) $count) || (float) $count > 4294967295) {
            $errors['participants_estimes'] = 'Saisissez un nombre entier strictement positif.';
        } else {
            $data['participants_estimes'] = (int) $count;
        }

        if ($errors) {
            throw new HttpError(422, 'validation_failed', $errors);
        }
        return $data;
    }

    /**
     * Enregistre une demande de devis.
     *
     * Le navigateur envoie une clé aléatoire unique par demande (« submission_key ») : si la même demande
     * arrive deux fois (double clic, connexion qui coupe), elle n'est enregistrée qu'une seule fois.
     * La même clé avec des données différentes est refusée (409).
     */
    public function submit(array $input): array
    {
        $key = $input['submission_key'] ?? null;
        if (!is_string($key) || !preg_match('/^[a-f0-9]{64}$/', $key)) {
            throw new HttpError(422, 'invalid_submission');
        }
        $data = $this->validate($input);
        $hash = hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
        $db = Connections::quotes();

        // Cherche une demande déjà enregistrée avec cette clé.
        $lookup = function () use ($db, $key, $hash): ?array {
            $query = $db->prepare('SELECT empreinte, remerciement FROM soumissions_devis WHERE cle = ?');
            $query->execute([$key]);
            $row = $query->fetch();
            if (!$row) {
                return null;
            }
            if (!hash_equals($row['empreinte'], $hash)) {
                throw new HttpError(409, 'submission_conflict');
            }
            return ['status' => 'accepted', 'message' => $row['remerciement']];
        };

        if ($previous = $lookup()) {
            return $previous;
        }

        // Limite anti-abus par adresse réseau (même mécanisme que la connexion).
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        if (!(new LoginLimiter())->allow('quote:' . $ip, $ip)) {
            throw new HttpError(429, 'too_many_attempts');
        }

        $message = $this->config()['message'];
        $recipient = getenv('AGENCY_EMAIL') ?: 'agence@example.invalid';
        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('Agency configuration');
        }

        $db->beginTransaction();
        try {
            $columns = ['entreprise', 'nom', 'prenom', 'email', 'telephone', 'lieu', 'type_evenement', 'date_souhaitee', 'participants_estimes', 'besoin'];
            $db->prepare('INSERT INTO prospects(entreprise, nom, prenom, email, telephone, lieu, type_evenement, date_souhaitee, participants_estimes, besoin) VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute(array_map(fn($column) => $data[$column], $columns));
            $id = $db->lastInsertId();

            $db->prepare('INSERT INTO soumissions_devis(cle, empreinte, prospect_id, remerciement) VALUES(?, ?, ?, ?)')
                ->execute([$key, $hash, $id, $message]);

            // Courriel à l'agence, champ par champ.
            $labels = ['entreprise' => 'Entreprise', 'nom' => 'Nom', 'prenom' => 'Prénom', 'email' => 'Adresse électronique',
                'telephone' => 'Téléphone', 'lieu' => 'Lieu', 'type_evenement' => 'Type d’événement',
                'date_souhaitee' => 'Date souhaitée', 'participants_estimes' => 'Participants estimés', 'besoin' => 'Besoin'];
            $lines = [];
            foreach ($data as $field => $value) {
                $lines[] = $labels[$field] . ' : ' . $value;
            }
            MailQueue::enqueue($db, 'demande_devis', $recipient, 'Nouvelle demande de devis Innov’Events', implode("\n", $lines), 1440);

            $db->commit();
            return ['status' => 'accepted', 'message' => $message];
        } catch (\PDOException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            // 1062 = clé déjà utilisée : la même demande vient d'être enregistrée par une requête parallèle.
            if ((int) ($e->errorInfo[1] ?? 0) === 1062 && ($previous = $lookup())) {
                return $previous;
            }
            throw $e;
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }
}

<?php
declare(strict_types=1);

namespace InnovEvents\Service;

use InnovEvents\Http\HttpError;
use InnovEvents\Infrastructure\Connections;

/**
 * Prospects : personnes qui ont envoyé une demande de devis, avant qu'elles deviennent clientes.
 * Réservé à l'administratrice : consultation, recherche et qualification négative (« échec »).
 */
final class Prospects
{
    private function authorize(): void
    {
        (new Authentication())->requireUser(['administrateur']);
    }

    /** Identifiant numérique strictement positif, renvoyé sous forme de texte (les identifiants MySQL dépassent la taille d'un entier PHP 32 bits). */
    private function id(mixed $value): string
    {
        if (!(is_string($value) || is_int($value)) || !preg_match('/^[1-9][0-9]{0,17}$/', (string) $value)) {
            throw new HttpError(422, 'invalid_id');
        }
        return (string) $value;
    }

    /**
     * Liste des prospects, filtrée par statut et par recherche texte, 25 par page.
     * La pagination utilise l'identifiant du dernier élément affiché (« before ») plutôt qu'un numéro de page :
     * les résultats restent justes même si de nouvelles demandes arrivent entre deux pages.
     */
    public function listing(array $input): array
    {
        $this->authorize();

        $status = $input['status'] ?? 'a_contacter';
        $search = $input['search'] ?? '';
        if (!is_string($status) || !in_array($status, ['', 'a_contacter', 'echoue', 'converti'], true)
            || !is_string($search) || preg_match_all('/./us', $search) > 200) {
            throw new HttpError(422, 'invalid_filter');
        }

        $where = [];
        $params = [];
        if ($status !== '') {
            $where[] = 'statut = ?';
            $params[] = $status;
        }
        if (trim($search) !== '') {
            // LOCATE cherche le texte saisi tel quel (pas de caractères spéciaux comme avec LIKE).
            $where[] = "LOCATE(?, CONCAT_WS(' ', entreprise, nom, prenom, email)) > 0";
            $params[] = trim($search);
        }
        if (isset($input['before']) && $input['before'] !== '') {
            $where[] = 'id < ?';
            $params[] = $this->id($input['before']);
        }

        // On lit 26 lignes pour savoir s'il existe une page suivante, puis on n'en renvoie que 25.
        $query = Connections::prospects()->prepare(
            'SELECT id, entreprise, nom, prenom, type_evenement, date_souhaitee, statut FROM prospects'
            . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
            . ' ORDER BY id DESC LIMIT 26'
        );
        $query->execute($params);
        $rows = $query->fetchAll();

        $more = count($rows) > 25;
        if ($more) {
            array_pop($rows);
        }

        return ['prospects' => $rows, 'next' => $more ? (string) $rows[24]['id'] : null];
    }

    /** Fiche complète d'un prospect. */
    public function detail(array $input): array
    {
        $this->authorize();
        $id = $this->id($input['id'] ?? null);

        $query = Connections::prospects()->prepare('SELECT * FROM prospects WHERE id = ?');
        $query->execute([$id]);
        $row = $query->fetch();
        if (!$row) {
            throw new HttpError(404, 'prospect_not_found');
        }

        return ['prospect' => $row];
    }

    /**
     * Qualification négative : le prospect passe en « échec » et reçoit le message saisi par courriel.
     * Renvoyer la même demande ne crée pas de second courriel (idempotence).
     */
    public function fail(array $input): array
    {
        $this->authorize();
        $id = $this->id($input['id'] ?? null);

        $message = $input['message'] ?? null;
        if (!is_string($message) || trim($message) === '' || preg_match_all('/./us', $message) > 2000
            || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $message)) {
            throw new HttpError(422, 'invalid_message');
        }
        $message = trim($message);

        $db = Connections::prospects();
        $db->beginTransaction();
        try {
            // FOR UPDATE : verrouille la ligne jusqu'à la fin de la transaction (pas de double traitement simultané).
            $query = $db->prepare('SELECT * FROM prospects WHERE id = ? FOR UPDATE');
            $query->execute([$id]);
            $row = $query->fetch();
            if (!$row) {
                throw new HttpError(404, 'prospect_not_found');
            }

            // Même demande déjà enregistrée (double clic, réponse perdue) : rien à refaire.
            if ($row['statut'] === 'echoue' && $row['message_echec'] === $message) {
                $db->commit();
                return ['status' => 'echoue'];
            }
            if ($row['statut'] !== 'a_contacter') {
                throw new HttpError(409, 'prospect_already_processed');
            }

            $db->prepare("UPDATE prospects SET statut = 'echoue', message_echec = ?, qualifie_le = UTC_TIMESTAMP(6) WHERE id = ?")
                ->execute([$message, $id]);
            // Le courriel est placé dans la file dans la même transaction : il part seulement si le statut est enregistré.
            MailQueue::enqueue($db, 'qualification_echec', $row['email'], 'Suite à votre demande Innov’Events', $message, 1440);

            $db->commit();
            return ['status' => 'echoue'];
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }
}

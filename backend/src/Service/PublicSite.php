<?php
declare(strict_types=1);

namespace InnovEvents\Service;

use InnovEvents\Http\HttpError;
use InnovEvents\Infrastructure\Connections;
use InnovEvents\Security\LoginLimiter;

/**
 * Pages publiques sans authentification : événements publiés, avis validés et formulaire de contact.
 * Les lectures passent uniquement par les vues v_evenements_publics et v_avis_publics (migrations 011 et 017),
 * qui excluent les brouillons, les événements sans accord du client, le client et les montants.
 */
final class PublicSite
{
    /** Colonnes publiées d'un événement (aucune information sur le client ni sur le devis). */
    private const COLUMNS = 'id,nom,debut,fin,lieu,type_evenement,theme,participants_estimes,statut,image_reference IS NOT NULL AS image,image_alternative';

    /**
     * Liste des événements publiés, avec les listes de types et de thèmes pour les filtres.
     * Filtres : du / au (AAAA-MM-JJ, heure de Paris), type, theme.
     */
    public function events(array $input): array
    {
        $where = [];
        $params = [];

        foreach (['du' => '>=', 'au' => '<'] as $key => $operator) {
            $value = $input[$key] ?? '';
            if ($value === '') {
                continue;
            }
            if (!is_string($value)
                || !($date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value, new \DateTimeZone('Europe/Paris')))
                || $date->format('Y-m-d') !== $value) {
                throw new HttpError(422, 'validation_failed', [$key => 'Saisissez une date valide.']);
            }
            // « au » est inclusif : on prend jusqu'au début du jour suivant.
            if ($key === 'au') {
                $date = $date->modify('+1 day');
            }
            // Un événement correspond s'il chevauche la période : fin après le début de période, début avant sa fin.
            $where[] = ($key === 'du' ? 'fin' : 'debut') . " $operator ?";
            $params[] = $date->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        }
        if (isset($params[0], $params[1]) && $params[0] >= $params[1]) {
            throw new HttpError(422, 'validation_failed', ['au' => 'La date de fin doit suivre la date de début.']);
        }

        foreach (['type' => 'type_evenement', 'theme' => 'theme'] as $key => $column) {
            $value = $input[$key] ?? '';
            if ($value === '') {
                continue;
            }
            if (!is_string($value) || preg_match_all('/./us', $value) > 100) {
                throw new HttpError(422, 'validation_failed', [$key => 'Choisissez une valeur de la liste.']);
            }
            $where[] = "$column = ?";
            $params[] = $value;
        }

        $db = Connections::mysql();
        $query = $db->prepare('SELECT ' . self::COLUMNS . ' FROM v_evenements_publics' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY debut DESC LIMIT 200');
        $query->execute($params);
        $events = array_map([$this, 'format'], $query->fetchAll());

        // Valeurs proposées dans les listes de filtres ($column vient de la liste fixe ci-dessous, jamais de l'utilisateur).
        $options = fn(string $column) => $db
            ->query("SELECT DISTINCT $column FROM v_evenements_publics WHERE $column IS NOT NULL ORDER BY $column")
            ->fetchAll(\PDO::FETCH_COLUMN);

        return ['events' => $events, 'types' => $options('type_evenement'), 'themes' => $options('theme')];
    }

    /** Détail d'un événement publié (404 pour un brouillon ou un événement sans accord de publication). */
    public function event(array $input): array
    {
        $query = Connections::mysql()->prepare('SELECT ' . self::COLUMNS . ' FROM v_evenements_publics WHERE id = ?');
        $query->execute([$this->id($input['id'] ?? null)]);
        $row = $query->fetch();
        if (!$row) {
            throw new HttpError(404, 'event_not_found');
        }

        return ['event' => $this->format($row)];
    }

    /** Image d'un événement publié. */
    public function image(array $input): void
    {
        $query = Connections::mysql()->prepare('SELECT image_reference FROM v_evenements_publics WHERE id = ?');
        $query->execute([$this->id($input['id'] ?? null)]);
        $path = EventImages::path($query->fetchColumn());
        if ($path === null) {
            throw new HttpError(404, 'image_not_found');
        }

        // Image publiée : mise en cache courte, contenu jamais interprété comme document.
        header('Cache-Control: public, max-age=3600');
        header('Content-Type: ' . (str_ends_with($path, '.png') ? 'image/png' : 'image/jpeg'));
        header('Content-Length: ' . filesize($path));
        header("Content-Security-Policy: default-src 'none'; sandbox");
        readfile($path);
    }

    /** Avis validés, du plus récent au plus ancien (auteur réduit au prénom et à l'initiale du nom par la vue). */
    public function reviews(): array
    {
        $rows = Connections::mysql()
            ->query('SELECT id, contenu, publie_le, evenement_id, evenement, type_evenement, image, auteur FROM v_avis_publics ORDER BY publie_le DESC, id DESC LIMIT 100')
            ->fetchAll();

        foreach ($rows as &$row) {
            $row['id'] = (int) $row['id'];
            $row['evenement_id'] = (int) $row['evenement_id'];
            $row['image'] = (bool) $row['image'];
            $row['publie_le'] = (new \DateTimeImmutable($row['publie_le'], new \DateTimeZone('UTC')))
                ->setTimezone(new \DateTimeZone('Europe/Paris'))
                ->format('Y-m-d');
        }

        return ['reviews' => $rows];
    }

    /**
     * Message de contact transmis par courriel à l'adresse générique de l'agence ; rien n'est conservé hors de la file chiffrée.
     * Champs de l'énoncé : nom d'utilisateur facultatif, adresse électronique obligatoire sans nom d'utilisateur, titre, description.
     */
    public function contact(array $input): array
    {
        $errors = [];
        $data = [];

        // [longueur maximale, obligatoire] ; la description peut contenir des retours à la ligne.
        $fields = ['nom_utilisateur' => [100, false], 'email' => [254, false], 'titre' => [150, true], 'description' => [5000, true]];
        foreach ($fields as $key => [$max, $required]) {
            $value = $input[$key] ?? '';
            $forbidden = $key === 'description' ? '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/' : '/[\x00-\x1F\x7F]/';
            if (!is_string($value) || preg_match_all('/./us', $value) > $max || preg_match($forbidden, $value)) {
                $errors[$key] = "Maximum $max caractères, sans caractère de contrôle.";
                continue;
            }
            $value = trim($value);
            if ($required && $value === '') {
                $errors[$key] = 'Renseignez ce champ.';
            }
            $data[$key] = $value;
        }

        if (!isset($errors['email']) && !isset($errors['nom_utilisateur'])) {
            if (($data['email'] ?? '') === '' && ($data['nom_utilisateur'] ?? '') === '') {
                $errors['email'] = 'Indiquez votre adresse électronique ou votre nom d’utilisateur.';
            } elseif (($data['email'] ?? '') !== '' && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                $errors['email'] = 'Saisissez une adresse électronique valide.';
            }
        }
        if ($errors) {
            throw new HttpError(422, 'validation_failed', $errors);
        }

        // Limite anti-abus par adresse réseau.
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        if (!(new LoginLimiter())->allow('contact:' . $ip, $ip)) {
            throw new HttpError(429, 'too_many_attempts');
        }

        $recipient = getenv('AGENCY_EMAIL') ?: 'agence@example.invalid';
        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('Agency configuration');
        }

        $user = (new Authentication())->current();
        $lines = [
            'Titre : ' . $data['titre'],
            'Nom d’utilisateur : ' . ($data['nom_utilisateur'] !== '' ? $data['nom_utilisateur'] : 'non renseigné'),
            'Adresse électronique : ' . ($data['email'] !== '' ? $data['email'] : 'non renseignée'),
            'Compte connecté : ' . ($user ? 'oui (identifiant ' . $user['id'] . ')' : 'non, visiteur'),
            '',
            'Message :',
            $data['description'],
        ];
        MailQueue::enqueue(Connections::quotes(), 'contact', $recipient, 'Contact Innov’Events : ' . $data['titre'], implode("\n", $lines), 1440);

        return ['status' => 'accepted'];
    }

    /** Identifiant d'événement ; une valeur invalide donne 404, comme un événement inexistant. */
    private function id(mixed $value): string
    {
        if (!is_string($value) || !preg_match('/^[1-9][0-9]{0,17}$/', $value)) {
            throw new HttpError(404, 'event_not_found');
        }
        return $value;
    }

    /** Mise en forme d'un événement publié pour le navigateur (dates en heure de Paris). */
    private function format(array $row): array
    {
        return [
            'id'                => (int) $row['id'],
            'nom'               => $row['nom'],
            'debut'             => EventSchedule::local($row['debut']),
            'fin'               => EventSchedule::local($row['fin']),
            'lieu'              => $row['lieu'],
            'type_evenement'    => $row['type_evenement'],
            'theme'             => $row['theme'],
            'participants'      => $row['participants_estimes'] === null ? null : (int) $row['participants_estimes'],
            'statut'            => $row['statut'],
            'image'             => (bool) $row['image'],
            'image_alternative' => $row['image_alternative'],
        ];
    }
}

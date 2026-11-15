<?php
declare(strict_types=1);

namespace InnovEvents\Service;

use InnovEvents\Http\HttpError;
use InnovEvents\Infrastructure\Connections;

/**
 * API de l'application mobile (PWA) : consultation et contact en mobilité pour Chloé et José.
 * Réservée aux employés et à l'administratrice dont le compte a l'accès mobile (utilisateurs.acces_mobile).
 * Réutilise le compte MySQL limité de l'équipe : lecture des clients et événements, ajout de notes.
 */
final class MobileApp
{
    /** Utilisateur connecté de l'équipe, avec l'accès mobile activé sur son compte. */
    private static function user(): array
    {
        $user = (new Authentication())->requireUser(['employe', 'administrateur']);
        if ((int) ($user['acces_mobile'] ?? 0) !== 1) {
            throw new HttpError(403, 'mobile_access_required');
        }
        return $user;
    }

    private static function id(mixed $v): string
    {
        if (!(is_int($v) || is_string($v)) || !preg_match('/^[1-9][0-9]{0,17}$/D', (string) $v)) {
            throw new HttpError(422, 'invalid_id');
        }
        return (string) $v;
    }

    /** Tableau de bord : tous les événements à venir ou en cours (fin non passée), hors annulés, du plus proche au plus lointain. */
    public function events(): array
    {
        self::user();

        $rows = Connections::team()->query(
            "SELECT e.id, e.nom, e.debut, e.fin, e.lieu, e.statut, c.entreprise
             FROM evenements e
             JOIN clients c ON c.id = e.client_id
             WHERE e.statut <> 'annule' AND e.debut IS NOT NULL
               AND (e.fin >= UTC_TIMESTAMP() OR (e.fin IS NULL AND e.debut >= UTC_TIMESTAMP()))
             ORDER BY e.debut, e.id
             LIMIT 200"
        )->fetchAll();

        return [
            'evenements' => array_map(static fn($r) => [
                'id'     => (string) $r['id'],
                'nom'    => $r['nom'] ?? 'Événement sans nom',
                'debut'  => EventSchedule::local($r['debut']),
                'fin'    => EventSchedule::local($r['fin']),
                'lieu'   => $r['lieu'],
                'statut' => $r['statut'],
                'client' => $r['entreprise'],
            ], $rows),
            'genere_le' => gmdate('c'),
        ];
    }

    /** Fiche événement concise : nom, dates, lieu, statut, client associé et cinq dernières notes. */
    public function event(array $input): array
    {
        self::user();
        $id = self::id($input['id'] ?? null);
        $db = Connections::team();

        $query = $db->prepare('SELECT e.id, e.nom, e.debut, e.fin, e.lieu, e.statut, e.client_id, c.entreprise, c.contact_prenom, c.contact_nom FROM evenements e JOIN clients c ON c.id = e.client_id WHERE e.id = ?');
        $query->execute([$id]);
        $event = $query->fetch();
        if (!$event) {
            throw new HttpError(404, 'event_not_found');
        }

        $query = $db->prepare('SELECT n.id, n.contenu, n.cree_le, u.prenom FROM notes_evenement n JOIN utilisateurs u ON u.id = n.auteur_id WHERE n.evenement_id = ? ORDER BY n.cree_le DESC, n.id DESC LIMIT 5');
        $query->execute([$id]);

        return [
            'evenement' => [
                'id'     => (string) $event['id'],
                'nom'    => $event['nom'] ?? 'Événement sans nom',
                'debut'  => EventSchedule::local($event['debut']),
                'fin'    => EventSchedule::local($event['fin']),
                'lieu'   => $event['lieu'],
                'statut' => $event['statut'],
                'client' => [
                    'id'         => (string) $event['client_id'],
                    'entreprise' => $event['entreprise'],
                    'contact'    => $event['contact_prenom'] . ' ' . $event['contact_nom'],
                ],
            ],
            'notes' => array_map(static fn($n) => [
                'id'      => (string) $n['id'],
                'contenu' => $n['contenu'],
                'cree_le' => $n['cree_le'],
                'auteur'  => $n['prenom'],
            ], $query->fetchAll()),
        ];
    }

    /** Fiche contact : téléphone, courriel et adresse postale, rendus cliquables par l'application. */
    public function client(array $input): array
    {
        self::user();
        $id = self::id($input['id'] ?? null);

        $query = Connections::team()->prepare('SELECT id, entreprise, contact_prenom, contact_nom, email, telephone, adresse_ligne1, adresse_ligne2, code_postal, ville, pays_code FROM clients WHERE id = ?');
        $query->execute([$id]);
        $client = $query->fetch();
        if (!$client) {
            throw new HttpError(404, 'client_not_found');
        }

        // Adresse sur une ligne pour l'itinéraire : « 12 rue …, 69003 Lyon ».
        $city = trim(($client['code_postal'] ?? '') . ' ' . ($client['ville'] ?? ''));
        $address = implode(', ', array_filter([$client['adresse_ligne1'], $client['adresse_ligne2'], $city], static fn($v) => is_string($v) && trim($v) !== ''));

        // Un numéro composable : chiffres et « + » initial uniquement.
        $phone = preg_replace('/(?!^\+)[^0-9]/', '', trim((string) $client['telephone']));

        return ['client' => [
            'id'                   => (string) $client['id'],
            'entreprise'           => $client['entreprise'],
            'contact'              => $client['contact_prenom'] . ' ' . $client['contact_nom'],
            'telephone'            => $client['telephone'],
            'telephone_composable' => strlen(preg_replace('/\D/', '', $phone)) >= 6 ? $phone : null,
            'email'                => filter_var($client['email'], FILTER_VALIDATE_EMAIL) ? $client['email'] : null,
            'adresse'              => $address === '' ? null : $address,
        ]];
    }

    /** Note rapide « à chaud » sur un événement ; renvoie la fiche mise à jour. */
    public function addNote(array $input): array
    {
        $user = self::user();
        $event = self::id($input['evenement_id'] ?? null);

        $text = $input['contenu'] ?? null;
        if (!is_string($text) || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $text) || !mb_check_encoding($text, 'UTF-8')
            || trim($text) === '' || preg_match_all('/./us', trim($text)) > 5000) {
            throw new HttpError(422, 'validation_failed', ['contenu' => 'Saisissez la note (5 000 caractères au maximum).']);
        }

        $db = Connections::team();
        $query = $db->prepare('SELECT id FROM evenements WHERE id = ?');
        $query->execute([$event]);
        if ($query->fetchColumn() === false) {
            throw new HttpError(404, 'event_not_found');
        }

        $db->prepare('INSERT INTO notes_evenement(evenement_id, auteur_id, contenu) VALUES(?, ?, ?)')->execute([$event, $user['id'], trim($text)]);

        return $this->event(['id' => $event]);
    }
}

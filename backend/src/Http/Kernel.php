<?php
declare(strict_types=1);

namespace InnovEvents\Http;

use InnovEvents\Repository\Spaces;
use InnovEvents\Repository\Users;
use InnovEvents\Security\WebSession;
use InnovEvents\Service;
use InnovEvents\Service\Authentication;

/**
 * Contrôleur unique de l'API : reçoit toutes les requêtes /api/*, applique les contrôles communs
 * et appelle le service métier correspondant.
 *
 * Contrôles communs, dans l'ordre :
 *  1. route connue (sinon 404) et méthode HTTP attendue (sinon 405) ;
 *  2. session démarrée, sauf pour le contrôle de santé et les lectures publiques (aucun cookie pour un visiteur) ;
 *  3. jeton CSRF pour toute écriture (POST) ;
 *  4. corps JSON borné en taille et décodé (sinon 415, 413, 400 ou 422).
 * Les droits (rôle, propriétaire des données) sont vérifiés ensuite par chaque service.
 * Une erreur prévue (HttpError) donne son code et un message court ; toute autre erreur donne 503 sans détail technique.
 */
final class Kernel
{
    /** Écritures qui acceptent un corps JSON plus grand (64 Ko au lieu de 8 Ko) : listes de prestations, notes longues. */
    private const LARGE_BODIES = ['/api/quote-requests', '/api/admin/quote-draft-save', '/api/team/note-save', '/api/mobile/note'];

    /** Écritures sans corps JSON : la déconnexion (aucune donnée) et l'envoi d'image (formulaire multipart). */
    private const NO_JSON_BODY = ['/api/auth/logout', '/api/admin/event-image-upload'];

    /**
     * Table des routes : adresse => [méthode HTTP, fonction appelée, code HTTP en cas de succès].
     * La fonction reçoit le corps JSON décodé (écritures) et renvoie la réponse JSON,
     * ou null quand elle a déjà envoyé elle-même un fichier (image, PDF).
     */
    private function routes(): array
    {
        $auth = new Authentication();

        return [
            // --- Santé et authentification -------------------------------------------------------------------
            '/api/health'       => ['GET', function () {
                (new Service\Readiness())->check();
                return ['status' => 'ready'];
            }],
            '/api/auth/session' => ['GET', function () use ($auth) {
                $user = $auth->current();
                return ['user' => $user ? Users::publicData($user) : null, 'csrf' => $_SESSION['csrf']];
            }],
            '/api/auth/login'    => ['POST', fn($data) => ['user' => $auth->login($data), 'csrf' => $_SESSION['csrf']]],
            '/api/auth/logout'   => ['POST', function () {
                WebSession::reset();
                return ['user' => null, 'csrf' => $_SESSION['csrf']];
            }],
            '/api/auth/register' => ['POST', fn($data) => (new Service\Accounts())->register($data), 202],
            '/api/auth/forgot'   => ['POST', fn($data) => (new Service\Accounts())->forgot($data), 202],
            '/api/auth/password' => ['POST', fn($data) => (new Service\Accounts())->change($data)],

            // --- Pages publiques (sans session) et contact ---------------------------------------------------
            '/api/public/events'      => ['GET', fn() => (new Service\PublicSite())->events($_GET)],
            '/api/public/event'       => ['GET', fn() => (new Service\PublicSite())->event($_GET)],
            '/api/public/event-image' => ['GET', fn() => (new Service\PublicSite())->image($_GET)],
            '/api/public/reviews'     => ['GET', fn() => (new Service\PublicSite())->reviews()],
            '/api/contact'            => ['POST', fn($data) => (new Service\PublicSite())->contact($data), 202],

            // --- Demande de devis publique -------------------------------------------------------------------
            '/api/quote-request/config' => ['GET', fn() => (new Service\QuoteRequests())->config()],
            '/api/quote-requests'       => ['POST', fn($data) => (new Service\QuoteRequests())->submit($data)],
            '/api/admin/quote-thanks'   => ['POST', fn($data) => (new Service\QuoteRequests())->saveThanks($data)],

            // --- Espace client -------------------------------------------------------------------------------
            '/api/me/events' => ['GET', function () use ($auth) {
                $user = $auth->requireUser(['client']);
                return ['events' => (new Spaces())->events((string) $user['id'])];
            }],
            '/api/me/quotes'           => ['GET', fn() => (new Service\ClientQuotes())->listing()],
            '/api/me/quote-pdf'        => ['GET', fn() => (new Service\ClientQuotes())->download($_GET)],
            '/api/me/quote-decision'   => ['POST', fn($data) => (new Service\ClientQuotes())->decide($data)],
            '/api/me/profile'          => ['GET', fn() => (new Service\ClientSpace())->profile()],
            '/api/me/profile-save'     => ['POST', fn($data) => (new Service\ClientSpace())->saveProfile($data)],
            '/api/me/deletion-request' => ['POST', fn($data) => (new Service\ClientSpace())->requestDeletion($data), 202],
            '/api/me/reviews'          => ['GET', fn() => (new Service\ClientSpace())->reviews()],
            '/api/me/review'           => ['POST', fn($data) => (new Service\ClientSpace())->submitReview($data)],

            // --- Espace équipe (employés et administratrice) -------------------------------------------------
            '/api/team/notes' => ['GET', function () use ($auth) {
                $auth->requireUser(['employe', 'administrateur']);
                return ['notes' => (new Spaces())->notes()];
            }],
            '/api/team/clients'           => ['GET', fn() => (new Service\TeamSpace())->clients($_GET)],
            '/api/team/client'            => ['GET', fn() => (new Service\TeamSpace())->client($_GET)],
            '/api/team/events'            => ['GET', fn() => (new Service\TeamSpace())->events($_GET)],
            '/api/team/event'             => ['GET', fn() => (new Service\TeamSpace())->eventDetail($_GET)],
            '/api/team/note-save'         => ['POST', fn($data) => (new Service\TeamSpace())->saveNote($data)],
            '/api/team/note-delete'       => ['POST', fn($data) => (new Service\TeamSpace())->deleteNote($data)],
            '/api/team/task-status'       => ['POST', fn($data) => (new Service\TeamSpace())->taskStatus($data)],
            '/api/team/reviews'           => ['GET', fn() => (new Service\ReviewModeration())->listing($_GET)],
            '/api/team/review-moderation' => ['POST', fn($data) => (new Service\ReviewModeration())->moderate($data)],

            // --- Application mobile --------------------------------------------------------------------------
            '/api/mobile/events' => ['GET', fn() => (new Service\MobileApp())->events()],
            '/api/mobile/event'  => ['GET', fn() => (new Service\MobileApp())->event($_GET)],
            '/api/mobile/client' => ['GET', fn() => (new Service\MobileApp())->client($_GET)],
            '/api/mobile/note'   => ['POST', fn($data) => (new Service\MobileApp())->addNote($data)],

            // --- Administration : tableau de bord, comptes, journal ------------------------------------------
            '/api/admin/dashboard'      => ['GET', fn() => (new Service\AdminAccounts())->dashboard()],
            '/api/admin/accounts'       => ['GET', fn() => (new Service\AdminAccounts())->listing($_GET)],
            '/api/admin/account-create' => ['POST', fn($data) => (new Service\AdminAccounts())->create($data), 201],
            '/api/admin/account-status' => ['POST', fn($data) => (new Service\AdminAccounts())->setStatus($data)],
            '/api/admin/account-delete' => ['POST', fn($data) => (new Service\AdminAccounts())->delete($data)],
            '/api/admin/logs' => ['GET', function () use ($auth) {
                $auth->requireUser(['administrateur']);
                return ['logs' => (new Spaces())->logs()];
            }],

            // --- Administration : prospects, clients et événements -------------------------------------------
            '/api/admin/prospects'           => ['GET', fn() => (new Service\Prospects())->listing($_GET)],
            '/api/admin/prospect'            => ['GET', fn() => (new Service\Prospects())->detail($_GET)],
            '/api/admin/prospect-failure'    => ['POST', fn($data) => (new Service\Prospects())->fail($data)],
            '/api/admin/prospect-conversion' => ['POST', fn($data) => (new Service\ClientConversion())->convert($data)],
            '/api/admin/client'              => ['GET', fn() => (new Service\ClientConversion())->detail($_GET)],
            '/api/admin/event-context'       => ['GET', fn() => (new Service\EventDrafts())->context($_GET)],
            '/api/admin/event'               => ['GET', fn() => (new Service\EventDrafts())->detail($_GET)],
            '/api/admin/event-create'        => ['POST', fn($data) => (new Service\EventDrafts())->create($data)],
            '/api/admin/event-save'          => ['POST', fn($data) => (new Service\EventDrafts())->save($data)],
            '/api/admin/event-image'         => ['GET', fn() => (new Service\EventImages())->show($_GET)],
            '/api/admin/event-image-upload'  => ['POST', function () {
                if (!str_starts_with(strtolower($_SERVER['CONTENT_TYPE'] ?? ''), 'multipart/form-data;')) {
                    throw new HttpError(415, 'multipart_required');
                }
                return (new Service\EventImages())->upload($_POST, $_FILES);
            }],
            '/api/admin/records-context' => ['GET', fn() => (new Service\AdminRecords())->staff()],
            '/api/admin/event-edit'      => ['GET', fn() => (new Service\AdminRecords())->eventForEdit($_GET)],
            '/api/admin/client-create'   => ['POST', fn($data) => (new Service\AdminRecords())->createClient($data), 201],
            '/api/admin/client-save'     => ['POST', fn($data) => (new Service\AdminRecords())->saveClient($data)],
            '/api/admin/client-delete'   => ['POST', fn($data) => (new Service\AdminRecords())->deleteClient($data)],
            '/api/admin/event-new'       => ['POST', fn($data) => (new Service\AdminRecords())->createEvent($data), 201],
            '/api/admin/event-update'    => ['POST', fn($data) => (new Service\AdminRecords())->saveEvent($data)],
            '/api/admin/event-status'    => ['POST', fn($data) => (new Service\AdminRecords())->setEventStatus($data)],
            '/api/admin/event-delete'    => ['POST', fn($data) => (new Service\AdminRecords())->deleteEvent($data)],
            '/api/admin/task-create'     => ['POST', fn($data) => (new Service\AdminRecords())->createTask($data), 201],
            '/api/admin/task-delete'     => ['POST', fn($data) => (new Service\AdminRecords())->deleteTask($data)],

            // --- Administration : devis ----------------------------------------------------------------------
            '/api/admin/quotes'                 => ['GET', fn() => (new Service\QuoteTransmissions())->overview($_GET)],
            '/api/admin/quote-draft'            => ['GET', fn() => (new Service\QuoteDrafts())->read($_GET)],
            '/api/admin/quote-draft-save'       => ['POST', fn($data) => (new Service\QuoteDrafts())->save($data)],
            '/api/admin/quote-counter-proposal' => ['POST', fn($data) => (new Service\QuoteDrafts())->counterProposal($data)],
            '/api/admin/quote-pdf-status'       => ['GET', fn() => (new Service\QuotePdfs())->status($_GET)],
            '/api/admin/quote-pdf'              => ['GET', fn() => (new Service\QuotePdfs())->download($_GET)],
            '/api/admin/quote-pdf-generate'     => ['POST', fn($data) => (new Service\QuotePdfs())->generate($data)],
            '/api/admin/quote-transmission'     => ['GET', fn() => (new Service\QuoteTransmissions())->status($_GET)],
            '/api/admin/quote-send'             => ['POST', fn($data) => (new Service\QuoteTransmissions())->send($data)],
        ];
    }

    /** Traite la requête en cours et écrit la réponse. */
    public function run(): void
    {
        // En-têtes communs : réponse JSON jamais mise en cache, type de contenu imposé au navigateur.
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');

        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

        try {
            $routes = $this->routes();
            if (!isset($routes[$path])) {
                throw new HttpError(404, 'not_found');
            }
            [$method, $handler] = $routes[$path];
            $successStatus = $routes[$path][2] ?? 200;

            if (($_SERVER['REQUEST_METHOD'] ?? '') !== $method) {
                header('Allow: ' . $method);
                throw new HttpError(405, 'method_not_allowed');
            }

            // Pas de session pour le contrôle de santé ni pour les lectures publiques : aucun cookie déposé chez un simple visiteur.
            if ($path !== '/api/health' && !str_starts_with($path, '/api/public/')) {
                WebSession::start();
            }

            $data = [];
            if ($method === 'POST') {
                WebSession::csrf();
                if (!in_array($path, self::NO_JSON_BODY, true)) {
                    $data = $this->jsonBody($path);
                }
            }

            $result = $handler($data);
            if ($result === null) {
                return;   // le service a déjà envoyé un fichier (image ou PDF)
            }

            if ($successStatus !== 200) {
                http_response_code($successStatus);
            }
            echo json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        } catch (HttpError $e) {
            http_response_code($e->status);
            if ($e->status === 429) {
                header('Retry-After: 900');   // limiteur de tentatives : réessayer dans 15 minutes
            }
            echo json_encode(['error' => $e->getMessage()] + ($e->fields ? ['fields' => $e->fields] : []));
        } catch (\Throwable $e) {
            // Panne inattendue : seul le type d'erreur est journalisé côté serveur, rien n'est montré au navigateur.
            error_log('Request failed: ' . get_class($e));
            http_response_code(503);
            echo json_encode(['status' => 'unavailable']);
        }
    }

    /**
     * Lit et décode le corps JSON d'une écriture.
     * Refuse un autre type de contenu (415), un corps trop grand (413), un JSON invalide (400) ou qui n'est pas un objet (422).
     */
    private function jsonBody(string $path): array
    {
        if (strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0])) !== 'application/json') {
            throw new HttpError(415, 'json_required');
        }

        $limit = in_array($path, self::LARGE_BODIES, true) ? 65536 : 8192;
        // On lit un octet de plus que la limite pour savoir si elle est dépassée.
        $raw = file_get_contents('php://input', false, null, 0, $limit + 1);
        if (strlen($raw) > $limit) {
            throw new HttpError(413, 'payload_too_large');
        }

        try {
            $data = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new HttpError(400, 'invalid_json');
        }
        if (!is_array($data)) {
            throw new HttpError(422, 'invalid_input');
        }
        return $data;
    }
}

<?php
declare(strict_types=1);

namespace InnovEvents\Security;

use InnovEvents\Http\HttpError;

/**
 * Session PHP du site web et protection contre les requêtes forgées (CSRF).
 *
 * La session est stockée côté serveur ; le navigateur ne reçoit qu'un identifiant dans un cookie
 * HttpOnly (illisible en JavaScript), limité au chemin /api et envoyé seulement en HTTPS en production.
 */
final class WebSession
{
    /** Démarre (ou reprend) la session et crée le jeton CSRF s'il n'existe pas encore. */
    public static function start(): void
    {
        $dir = '/var/lib/innovevents/sessions';
        if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new \RuntimeException('Session storage');
        }
        session_save_path($dir);

        // Refuse les identifiants de session inventés et toute session passée dans l'URL.
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        ini_set('session.gc_maxlifetime', '28800');

        session_name('innovevents_session');
        session_set_cookie_params([
            'lifetime' => 0,                               // cookie supprimé à la fermeture du navigateur
            'path'     => '/api',
            'secure'   => getenv('APP_ENV') !== 'local',   // HTTPS obligatoire hors du poste de développement
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        if (!session_start()) {
            throw new \RuntimeException('Session unavailable');
        }

        $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
    }

    /**
     * Vérifie qu'une requête d'écriture (POST) vient bien de notre site.
     * Trois contrôles : origine déclarée, en-tête Sec-Fetch-Site et jeton CSRF de la session.
     */
    public static function csrf(): void
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? null;
        if ($origin !== null && $origin !== (getenv('APP_ORIGIN') ?: 'https://invalid.local')) {
            throw new HttpError(403, 'csrf_invalid');
        }
        if (($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '') === 'cross-site') {
            throw new HttpError(403, 'csrf_invalid');
        }

        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        // hash_equals : comparaison en temps constant, qui ne laisse rien deviner du jeton.
        if (!is_string($token) || !hash_equals($_SESSION['csrf'], $token)) {
            throw new HttpError(403, 'csrf_invalid');
        }
    }

    /**
     * Vide la session et change son identifiant (connexion, déconnexion, session expirée).
     * Empêche la réutilisation d'un ancien identifiant (fixation de session).
     */
    public static function reset(): void
    {
        $_SESSION = [];
        if (!session_regenerate_id(true)) {
            throw new \RuntimeException('Session rotation');
        }
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
}

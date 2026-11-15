<?php
declare(strict_types=1);

namespace InnovEvents\Service;

use InnovEvents\Http\HttpError;
use InnovEvents\Repository\Users;
use InnovEvents\Security\LoginLimiter;
use InnovEvents\Security\WebSession;

/**
 * Connexion et contrôle d'accès des utilisateurs du site.
 *
 * Tous les services commencent par requireUser() avec la liste des rôles autorisés :
 * c'est ici que se décident les réponses 401 (non connecté) et 403 (connecté mais non autorisé).
 */
final class Authentication
{
    /**
     * Renvoie l'utilisateur de la session, ou null si personne n'est connecté.
     *
     * La session est fermée si le compte est suspendu, inactif depuis 30 minutes, ouvert depuis
     * plus de 8 heures, ou si son mot de passe a changé depuis la connexion.
     */
    public function current(): ?array
    {
        if (!isset($_SESSION['uid'])) {
            return null;
        }

        $user = (new Users())->find('id', (string) $_SESSION['uid']);
        $temporary = (bool) ($_SESSION['temporary_auth'] ?? false);
        $credential = $temporary ? ($user['mdp_temporaire_hash'] ?? '') : ($user['mot_de_passe_hash'] ?? '');

        $expired = !$user
            || $user['statut'] !== 'actif'
            || time() - ($_SESSION['last'] ?? 0) > 1800
            || time() - ($_SESSION['created'] ?? 0) > 28800
            || ($temporary && !$user['temporary_valid'])
            // Empreinte du mot de passe au moment de la connexion : un changement de mot de passe déconnecte partout.
            || !hash_equals($_SESSION['credential'] ?? '', hash('sha256', $credential));

        if ($expired) {
            WebSession::reset();
            return null;
        }

        $_SESSION['last'] = time();

        // Connecté avec un mot de passe temporaire : il doit en choisir un nouveau avant toute autre action.
        if ($temporary) {
            $user['changement_mdp_requis'] = 1;
        }

        return $user;
    }

    /**
     * Exige un utilisateur connecté, avec un des rôles indiqués (tous les rôles si la liste est vide).
     *
     * @param string[] $roles Rôles autorisés : « client », « employe », « administrateur ».
     */
    public function requireUser(array $roles = []): array
    {
        $user = $this->current();

        if (!$user) {
            throw new HttpError(401, 'authentication_required');
        }
        if ((bool) $user['changement_mdp_requis']) {
            throw new HttpError(403, 'password_change_required');
        }
        if ($roles && !in_array($user['role'], $roles, true)) {
            throw new HttpError(403, 'forbidden');
        }

        return $user;
    }

    /**
     * Connecte un utilisateur à partir de son adresse électronique et de son mot de passe.
     * Chaque tentative, réussie ou non, est inscrite dans le journal MongoDB.
     */
    public function login(array $input): array
    {
        // Toute connexion repart d'une session sans utilisateur.
        unset($_SESSION['uid'], $_SESSION['created'], $_SESSION['last'], $_SESSION['credential'], $_SESSION['temporary_auth']);

        if (!is_string($input['email'] ?? null) || !is_string($input['password'] ?? null)) {
            throw new HttpError(422, 'invalid_input');
        }
        $email = trim($input['email']);
        $password = $input['password'];
        // 72 octets : longueur maximale prise en compte par bcrypt.
        if (strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) > 72 || $password === '') {
            throw new HttpError(422, 'invalid_input');
        }

        // Ne jamais accepter un rôle ou un identifiant utilisateur envoyé par le navigateur.
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            $ip = '127.0.0.1';
        }
        $user = (new Users())->find('email_normalise', strtolower($email));

        if (!(new LoginLimiter())->allow(strtolower($email), $ip)) {
            (new AuthAudit())->record(false, $user ? (string) $user['id'] : null, $email, $ip);
            throw new HttpError(429, 'too_many_attempts');
        }

        // Compte inconnu : vérification contre une empreinte factice, pour que la réponse prenne
        // le même temps qu'avec un compte existant (on ne peut pas deviner quelles adresses existent).
        $hash = $user['mot_de_passe_hash'] ?? '$2y$10$opK.m3XooPgJCZVLxKCezekkj4HyLKmJSE54KM2ZGjbPwGpWkkMaC';
        $valid = password_verify($password, $hash);

        // Sinon, essai avec le mot de passe temporaire envoyé par « mot de passe oublié » (30 minutes).
        $temporary = false;
        if (!$valid && $user && $user['temporary_valid'] && $user['mdp_temporaire_hash']) {
            $temporary = password_verify($password, $user['mdp_temporaire_hash']);
            $valid = $temporary;
        }

        $valid = $valid && $user !== null && $user['statut'] === 'actif';
        (new AuthAudit())->record($valid, $user ? (string) $user['id'] : null, $email, $ip);
        if (!$valid) {
            throw new HttpError(401, 'invalid_credentials');
        }

        // Nouvel identifiant de session à la connexion, puis mémorisation de l'utilisateur.
        WebSession::reset();
        $_SESSION['uid'] = (string) $user['id'];
        $_SESSION['created'] = time();
        $_SESSION['last'] = time();
        $_SESSION['temporary_auth'] = $temporary;
        $_SESSION['credential'] = hash('sha256', $temporary ? $user['mdp_temporaire_hash'] : $user['mot_de_passe_hash']);

        if ($temporary) {
            $user['changement_mdp_requis'] = 1;
        }

        return Users::publicData($user);
    }
}

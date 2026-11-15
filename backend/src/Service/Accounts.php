<?php
declare(strict_types=1);

namespace InnovEvents\Service;

use InnovEvents\Http\HttpError;
use InnovEvents\Infrastructure\Connections;
use InnovEvents\Security\LoginLimiter;
use InnovEvents\Security\WebSession;

/**
 * Parcours de compte : création, mot de passe oublié et changement de mot de passe.
 * Mots de passe hachés avec bcrypt (coût 12) ; aucun mot de passe n'est jamais stocké ni journalisé en clair.
 */
final class Accounts
{
    /** Adresse électronique valide, renvoyée en minuscules (une même adresse = un seul compte). */
    private function email(array $input): string
    {
        $email = $input['email'] ?? null;
        if (!is_string($email) || strlen(trim($email)) > 254 || !filter_var(trim($email), FILTER_VALIDATE_EMAIL)) {
            throw new HttpError(422, 'invalid_email');
        }
        return strtolower(trim($email));
    }

    /**
     * Règle de mot de passe de l'énoncé : 8 caractères minimum avec majuscule, minuscule, chiffre et caractère spécial.
     * Les deux saisies (mot de passe et confirmation) doivent être identiques.
     */
    private function password(array $input): string
    {
        $password = $input['password'] ?? null;
        if (!is_string($password)
            || strlen($password) > 72                              // limite de bcrypt
            || preg_match_all('/./us', $password) < 8
            || preg_match('/\p{C}/u', $password)                   // pas de caractère de contrôle invisible
            || !preg_match('/\p{Lu}/u', $password)                 // une majuscule
            || !preg_match('/\p{Ll}/u', $password)                 // une minuscule
            || !preg_match('/\p{N}/u', $password)                  // un chiffre
            || !preg_match('/[^\p{L}\p{N}\s]/u', $password)) {     // un caractère spécial
            throw new HttpError(422, 'password_policy');
        }
        if (!is_string($input['confirmation'] ?? null) || !hash_equals($password, $input['confirmation'])) {
            throw new HttpError(422, 'password_confirmation');
        }
        return $password;
    }

    /** Limite anti-abus partagée avec la connexion (créations de compte, récupérations répétées). */
    private function limit(string $key): void
    {
        if (!(new LoginLimiter())->allow($key, $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1')) {
            throw new HttpError(429, 'too_many_attempts');
        }
    }

    /**
     * Création de compte par un visiteur, puis courriel de confirmation.
     * Si l'adresse est déjà utilisée, la réponse est la même que pour un nouveau compte :
     * impossible de savoir, depuis le formulaire, quelles adresses ont un compte.
     */
    public function register(array $input): array
    {
        $email = $this->email($input);
        $password = $this->password($input);

        $names = [];
        foreach (['prenom', 'nom', 'nom_utilisateur'] as $field) {
            $value = $input[$field] ?? null;
            if (!is_string($value) || trim($value) === '' || preg_match_all('/./us', $value) > 100 || preg_match('/\p{C}/u', $value)) {
                throw new HttpError(422, 'invalid_identity');
            }
            $names[] = trim($value);
        }

        $this->limit('register:' . $email);
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

        $db = Connections::accounts();
        $db->beginTransaction();
        try {
            $db->prepare('INSERT INTO utilisateurs(email, email_normalise, prenom, nom, nom_utilisateur, mot_de_passe_hash) VALUES(?, ?, ?, ?, ?, ?)')
                ->execute([$email, $email, ...$names, $hash]);
            MailQueue::enqueue($db, 'confirmation', $email, 'Bienvenue chez Innov’Events',
                "Bonjour {$names[0]},\nVotre compte Innov’Events a été créé. Vous pouvez vous connecter avec votre adresse électronique.\nAucun mot de passe ne figure dans ce courriel.", 1440);
            $db->commit();
        } catch (\PDOException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            // 1062 = adresse déjà enregistrée : on répond comme si tout allait bien (voir plus haut).
            if ((int) ($e->errorInfo[1] ?? 0) !== 1062) {
                throw $e;
            }
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        return ['status' => 'accepted'];
    }

    /**
     * Mot de passe oublié : envoie un mot de passe temporaire valable 30 minutes.
     * L'ancien mot de passe reste valable (si la demande vient de quelqu'un d'autre, rien n'est cassé).
     * Même réponse que l'adresse existe ou non.
     */
    public function forgot(array $input): array
    {
        $email = $this->email($input);
        $this->limit('forgot:' . $email);

        $temporary = 'Tmp!7' . bin2hex(random_bytes(16));
        $hash = password_hash($temporary, PASSWORD_BCRYPT, ['cost' => 12]);

        $db = Connections::accounts();
        $db->beginTransaction();
        try {
            $query = $db->prepare('SELECT *, mdp_temporaire_expire_le > UTC_TIMESTAMP() AS pending FROM utilisateurs WHERE email_normalise = ? FOR UPDATE');
            $query->execute([$email]);
            $user = $query->fetch();

            // Pas de nouveau mot de passe temporaire tant que le précédent est encore valable.
            if ($user && $user['statut'] === 'actif' && !$user['pending']) {
                $db->prepare('UPDATE utilisateurs SET mdp_temporaire_hash = ?, mdp_temporaire_expire_le = DATE_ADD(UTC_TIMESTAMP(), INTERVAL 30 MINUTE) WHERE id = ?')
                    ->execute([$hash, $user['id']]);
                MailQueue::enqueue($db, 'recuperation', $email, 'Votre mot de passe temporaire Innov’Events',
                    "Mot de passe temporaire : $temporary\nValable 30 minutes. Connectez-vous puis choisissez immédiatement un nouveau mot de passe.\nSi vous n’avez pas demandé cette récupération, ignorez ce message : votre mot de passe habituel reste valable.", 30);
            }
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        return ['status' => 'accepted'];
    }

    /**
     * Changement de mot de passe par l'utilisateur connecté (obligatoire après un mot de passe temporaire
     * ou une création de compte par l'agence). Le nouveau doit être différent de l'ancien.
     * La session est ensuite fermée : il faut se reconnecter avec le nouveau mot de passe.
     */
    public function change(array $input): array
    {
        $user = (new Authentication())->current();
        if (!$user) {
            throw new HttpError(401, 'authentication_required');
        }
        $this->limit('change:' . $user['id']);
        $new = $this->password($input);

        $old = $input['current_password'] ?? null;
        if (!is_string($old) || strlen($old) > 72) {
            throw new HttpError(422, 'invalid_input');
        }

        $db = Connections::accounts();
        $db->beginTransaction();
        try {
            $query = $db->prepare('SELECT *, mdp_temporaire_expire_le > UTC_TIMESTAMP() AS temporary_valid FROM utilisateurs WHERE id = ? FOR UPDATE');
            $query->execute([$user['id']]);
            $row = $query->fetch();

            // Le mot de passe actuel est celui utilisé pour se connecter : habituel ou temporaire.
            $temporary = (bool) ($_SESSION['temporary_auth'] ?? false);
            $hash = $temporary ? ($row['mdp_temporaire_hash'] ?? '') : ($row['mot_de_passe_hash'] ?? '');

            if (!$row || $row['statut'] !== 'actif' || ($temporary && !$row['temporary_valid'])
                || !hash_equals($_SESSION['credential'] ?? '', hash('sha256', $hash))) {
                throw new HttpError(401, 'authentication_required');
            }
            if (!password_verify($old, $hash)) {
                throw new HttpError(422, 'current_password_invalid');
            }
            if (password_verify($new, $row['mot_de_passe_hash']) || ($row['mdp_temporaire_hash'] && password_verify($new, $row['mdp_temporaire_hash']))) {
                throw new HttpError(422, 'password_must_change');
            }

            $db->prepare('UPDATE utilisateurs SET mot_de_passe_hash = ?, changement_mdp_requis = 0, mdp_temporaire_hash = NULL, mdp_temporaire_expire_le = NULL WHERE id = ?')
                ->execute([password_hash($new, PASSWORD_BCRYPT, ['cost' => 12]), $user['id']]);
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        WebSession::reset();
        return ['status' => 'password_changed', 'csrf' => $_SESSION['csrf'], 'user' => null];
    }
}

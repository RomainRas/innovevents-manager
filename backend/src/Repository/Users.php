<?php
declare(strict_types=1);

namespace InnovEvents\Repository;

use InnovEvents\Infrastructure\Connections;

/** Lecture des comptes utilisateurs pour l'authentification. */
final class Users
{
    /**
     * Cherche un compte par identifiant ou par adresse électronique normalisée.
     * Renvoie aussi les empreintes de mot de passe : réservé à la vérification de connexion.
     *
     * @param string $column « id » ou « email_normalise » (liste fermée : jamais une colonne choisie par l'utilisateur).
     */
    public function find(string $column, string $value): ?array
    {
        if (!in_array($column, ['id', 'email_normalise'], true)) {
            throw new \InvalidArgumentException();
        }

        $query = Connections::mysql()->prepare(
            "SELECT id, email, prenom, nom, role, statut, acces_mobile, changement_mdp_requis,
                    mot_de_passe_hash, mdp_temporaire_hash,
                    mdp_temporaire_expire_le > UTC_TIMESTAMP() AS temporary_valid
             FROM utilisateurs
             WHERE $column = ?"
        );
        $query->execute([$value]);

        return $query->fetch() ?: null;
    }

    /** Informations du compte transmises au navigateur : jamais d'empreinte de mot de passe. */
    public static function publicData(array $user): array
    {
        return [
            'id'                       => (string) $user['id'],
            'email'                    => $user['email'],
            'prenom'                   => $user['prenom'],
            'nom'                      => $user['nom'],
            'role'                     => $user['role'],
            'password_change_required' => (bool) $user['changement_mdp_requis'],
        ];
    }
}

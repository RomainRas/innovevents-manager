<?php
declare(strict_types=1);

namespace InnovEvents\Infrastructure;

use MongoDB\Client;
use PDO;
use RuntimeException;

/**
 * Connexions aux bases de données, avec le principe du moindre privilège.
 *
 * Chaque fonctionnalité utilise son propre compte MySQL, qui n'a le droit d'écrire que dans les
 * colonnes dont elle a besoin (droits créés par les scripts scripts/provision-*.py).
 * Exemple : le compte de l'espace client ne peut pas modifier un rôle ni lire une empreinte de mot de passe.
 *
 * Les mots de passe des comptes sont lus dans des fichiers secrets montés par Docker (/run/secrets),
 * jamais dans le code ni dans des variables d'environnement.
 */
final class Connections
{
    /** Lit un secret Docker ; échoue si le fichier est absent ou vide. */
    private static function secret(string $name): string
    {
        $value = file_get_contents('/run/secrets/' . $name);
        if ($value === false || trim($value) === '') {
            throw new RuntimeException('Configuration indisponible');
        }
        return trim($value);
    }

    /** Compte en lecture seule utilisé pour l'authentification et les pages publiques. */
    public static function mysql(): PDO
    {
        return self::accountDatabase('ecf_api_read', 'mysql_api');
    }

    // Un compte limité par fonctionnalité (nom du compte MySQL, nom du secret Docker).
    public static function accounts(): PDO { return self::accountDatabase('ecf_accounts', 'mysql_accounts'); }                  // création de compte, mots de passe
    public static function mail(): PDO { return self::accountDatabase('ecf_mail', 'mysql_mail'); }                              // file des courriels
    public static function quotes(): PDO { return self::accountDatabase('ecf_quotes', 'mysql_quotes'); }                        // demandes de devis publiques
    public static function prospects(): PDO { return self::accountDatabase('ecf_prospects_admin', 'mysql_prospects_admin'); }   // qualification des prospects
    public static function conversion(): PDO { return self::accountDatabase('ecf_conversion', 'mysql_conversion'); }            // conversion en client
    public static function journal(): PDO { return self::accountDatabase('ecf_journal', 'mysql_journal'); }                     // livraison du journal
    public static function events(): PDO { return self::accountDatabase('ecf_events', 'mysql_events'); }                        // brouillons d'événement
    public static function quotePdf(): PDO { return self::accountDatabase('ecf_quote_pdf', 'mysql_quote_pdf'); }                // génération des PDF
    public static function quoteDrafts(): PDO { return self::accountDatabase('ecf_quote_drafts', 'mysql_quote_drafts'); }       // prestations du devis
    public static function eventImages(): PDO { return self::accountDatabase('ecf_event_images', 'mysql_event_images'); }       // images des événements
    public static function settings(): PDO { return self::accountDatabase('ecf_settings', 'mysql_settings'); }                  // paramètres modifiables
    public static function quoteSend(): PDO { return self::accountDatabase('ecf_quote_send', 'mysql_quote_send'); }             // transmission du devis
    public static function clientQuotes(): PDO { return self::accountDatabase('ecf_client_quotes', 'mysql_client_quotes'); }    // devis vus par le client
    public static function quoteDecision(): PDO { return self::accountDatabase('ecf_quote_decision', 'mysql_quote_decision'); } // réponse du client
    public static function clientSpace(): PDO { return self::accountDatabase('ecf_client_space', 'mysql_client_space'); }       // profil, suppression, avis
    public static function reviews(): PDO { return self::accountDatabase('ecf_reviews', 'mysql_reviews'); }                     // modération des avis
    public static function admin(): PDO { return self::accountDatabase('ecf_admin', 'mysql_admin'); }                           // tableau de bord, comptes
    public static function team(): PDO { return self::accountDatabase('ecf_team', 'mysql_team'); }                              // espace employé, mobile
    public static function records(): PDO { return self::accountDatabase('ecf_records', 'mysql_records'); }                     // gestion des fiches

    /**
     * Ouvre une connexion PDO à la base de démonstration avec le compte demandé.
     * Requêtes préparées réelles (pas d'émulation) et erreurs levées en exceptions.
     */
    private static function accountDatabase(string $user, string $secret): PDO
    {
        return new PDO('mysql:host=mysql;dbname=innovevents_demo;charset=utf8mb4', $user, self::secret($secret), [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    /** Lecture du journal MongoDB (page « Journaux », contrôle de santé). */
    public static function mongo(): Client
    {
        return new Client('mongodb://mongodb:27017', [
            'username'                 => 'ecf_api_read',
            'password'                 => self::secret('mongo_api'),
            'authSource'               => 'innovevents_demo',
            'serverSelectionTimeoutMS' => 2000,
            'connectTimeoutMS'         => 1000,
            'socketTimeoutMS'          => 2000,
        ]);
    }

    /** Écriture du journal des connexions : ce compte MongoDB ne peut qu'ajouter des documents dans « logs ». */
    public static function audit(): Client
    {
        return new Client('mongodb://mongodb:27017', [
            'username'                 => 'ecf_auth_log',
            'password'                 => self::secret('mongo_auth'),
            'authSource'               => 'innovevents_demo',
            'serverSelectionTimeoutMS' => 2000,
            'connectTimeoutMS'         => 1000,
            'socketTimeoutMS'          => 2000,
        ]);
    }
}

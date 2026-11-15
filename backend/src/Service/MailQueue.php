<?php
declare(strict_types=1);

namespace InnovEvents\Service;

use PDO;

/**
 * File d'attente des courriels.
 *
 * Les services n'envoient jamais un courriel directement : ils l'ajoutent dans la table courriels_a_envoyer,
 * dans la même transaction que l'action qui le déclenche. Le traitement de fond mail_worker l'envoie ensuite,
 * et réessaie si le serveur de messagerie est indisponible. Aucun courriel n'est perdu ni envoyé pour rien.
 *
 * Le contenu (destinataire, sujet, texte, parfois un mot de passe temporaire) est chiffré en base
 * avec libsodium (secretbox), avec une clé gardée dans un secret Docker.
 */
final class MailQueue
{
    /** Clé de chiffrement de 32 octets, lue dans le secret Docker « mail_key » (64 caractères hexadécimaux). */
    private static function key(): string
    {
        $key = hex2bin(trim(file_get_contents('/run/secrets/mail_key')));
        if ($key === false || strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw new \RuntimeException('Mail key');
        }
        return $key;
    }

    /**
     * Ajoute un courriel chiffré dans la file et renvoie son identifiant.
     *
     * @param PDO    $db         Connexion de la transaction en cours (le courriel n'existe que si elle est validée).
     * @param string $type       Type de courriel, contrôlé par la base (confirmation, devis, contact…).
     * @param int    $minutes    Durée de validité : au-delà, un courriel non envoyé est abandonné.
     * @param array{reference:string,sha256:string,name:string}|null $attachment PDF interne vérifié par le traitement d’envoi.
     */
    public static function enqueue(PDO $db, string $type, string $email, string $subject, string $text, int $minutes, ?array $attachment = null): string
    {
        $data = compact('email', 'subject', 'text');
        if ($attachment !== null) {
            $data['attachment'] = $attachment;
        }

        // Un nombre aléatoire unique (nonce) par message, placé devant le texte chiffré.
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $payload = base64_encode($nonce . sodium_crypto_secretbox(json_encode($data, JSON_THROW_ON_ERROR), $nonce, self::key()));

        $id = (string) $db->query('SELECT UUID()')->fetchColumn();
        $db->prepare('INSERT INTO courriels_a_envoyer(id, type, contenu_chiffre, expire_le) VALUES(?, ?, ?, DATE_ADD(UTC_TIMESTAMP(), INTERVAL ? MINUTE))')
            ->execute([$id, $type, $payload, $minutes]);

        return $id;
    }

    /** Déchiffre un courriel de la file (utilisé par mail_worker) ; échoue s'il a été modifié. */
    public static function decode(string $payload): array
    {
        $raw = base64_decode($payload, true);
        if ($raw === false || strlen($raw) < 40) {
            throw new \RuntimeException('Mail payload');
        }

        // Les 24 premiers octets sont le nonce, le reste le texte chiffré et authentifié.
        $plain = sodium_crypto_secretbox_open(substr($raw, 24), substr($raw, 0, 24), self::key());
        if ($plain === false) {
            throw new \RuntimeException('Mail authentication');
        }

        return json_decode($plain, true, 16, JSON_THROW_ON_ERROR);
    }
}

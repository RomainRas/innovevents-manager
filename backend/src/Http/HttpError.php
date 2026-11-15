<?php
declare(strict_types=1);

namespace InnovEvents\Http;

/**
 * Erreur « attendue » renvoyée au navigateur avec un code HTTP précis.
 *
 * Les services lèvent cette exception quand une demande est refusée (droits, validation, conflit…).
 * Le Kernel la transforme en réponse JSON : {"error": "<code>", "fields": {...}}.
 * Toute autre exception est considérée comme une panne et donne une réponse 503 sans détail.
 */
final class HttpError extends \RuntimeException
{
    /**
     * @param int    $status Code HTTP renvoyé (401, 403, 404, 409, 422…).
     * @param string $code   Code d'erreur court et stable, lu par l'interface (ex. « invalid_id »).
     * @param array  $fields Messages d'erreur par champ de formulaire, affichés à côté des champs.
     */
    public function __construct(public readonly int $status, string $code, public readonly array $fields = [])
    {
        parent::__construct($code);
    }
}

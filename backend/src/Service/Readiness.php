<?php
declare(strict_types=1);

namespace InnovEvents\Service;

use InnovEvents\Infrastructure\Connections;

/**
 * Contrôle de santé (/api/health) : l'API est « prête » seulement si les deux bases répondent.
 * Utilisé par Docker (healthcheck) et par le pipeline de déploiement.
 */
final class Readiness
{
    public function check(): void
    {
        Connections::mysql()->query('SELECT 1 FROM parametres_application LIMIT 1')->fetch();
        Connections::mongo()->selectCollection('innovevents_demo', 'logs')->findOne([], ['projection' => ['_id' => 1]]);
    }
}

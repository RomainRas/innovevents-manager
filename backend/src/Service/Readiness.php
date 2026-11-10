<?php
declare(strict_types=1);
namespace InnovEvents\Service;

use InnovEvents\Infrastructure\Connections;

final class Readiness
{
    public function check(): void
    {
        Connections::mysql()->query('SELECT 1 FROM parametres_application LIMIT 1')->fetch();
        Connections::mongo()->selectCollection('innovevents_demo', 'logs')->findOne([], ['projection' => ['_id' => 1]]);
    }
}

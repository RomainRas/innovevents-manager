<?php
declare(strict_types=1);

// Point d'entrée unique de l'API : toutes les requêtes /api/* arrivent ici
// (Caddy en production, serveur PHP intégré en local) et sont confiées au Kernel.
require dirname(__DIR__) . '/vendor/autoload.php';

(new InnovEvents\Http\Kernel())->run();

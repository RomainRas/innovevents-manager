<?php
declare(strict_types=1);
// Mesure de couverture des tests fonctionnels et de bout en bout (développement uniquement).
// Chargé par auto_prepend_file seulement avec compose.coverage.yaml (pcov.enabled=1) : chaque requête HTTP
// traitée par l'API enregistre les lignes de src/ exécutées dans tmp/coverage/requetes/, fusionnées ensuite par phpcov.
if (!extension_loaded('pcov') || !ini_get('pcov.enabled') || PHP_SAPI !== 'cli-server') return;
require '/app/vendor/autoload.php';
(static function (): void {
    $filter = new \SebastianBergmann\CodeCoverage\Filter();
    $filter->includeFiles((new \SebastianBergmann\FileIterator\Facade())->getFilesAsArray('/app/src', '.php'));
    $coverage = new \SebastianBergmann\CodeCoverage\CodeCoverage((new \SebastianBergmann\CodeCoverage\Driver\Selector())->forLineCoverage($filter), $filter);
    $coverage->start(($_SERVER['REQUEST_METHOD'] ?? '') . ' ' . parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
    register_shutdown_function(static function () use ($coverage): void {
        $coverage->stop();
        $dir = '/app/tmp/coverage/requetes';
        if (!is_dir($dir)) @mkdir($dir, 0777, true);
        (new \SebastianBergmann\CodeCoverage\Report\PHP())->process($coverage, $dir . '/' . bin2hex(random_bytes(8)) . '.cov');
    });
})();

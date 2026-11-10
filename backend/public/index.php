<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if ($path !== '/api/health') {
    http_response_code(404);
    echo json_encode(['error' => 'not_found']);
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    echo json_encode(['error' => 'method_not_allowed']);
    exit;
}
try {
    (new InnovEvents\Service\Readiness())->check();
    echo json_encode(['status' => 'ready']);
} catch (Throwable $error) {
    // Ni identifiant, ni secret, ni détail de connexion dans la réponse HTTP.
    error_log('Readiness failed: ' . get_class($error));
    http_response_code(503);
    echo json_encode(['status' => 'unavailable']);
}

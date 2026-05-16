<?php

declare(strict_types=1);

use App\Helpers\Response;
use Dotenv\Dotenv;

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit;
}

require_once dirname(__DIR__) . '/vendor/autoload.php';

$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

$routes = require dirname(__DIR__) . '/app/Routes/api.php';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

$basePath = parse_url($_ENV['APP_URL'] ?? '', PHP_URL_PATH) ?: '';
if ($basePath !== '' && str_starts_with($uri, $basePath)) {
    $uri = substr($uri, strlen($basePath));
    $uri = $uri === '' ? '/' : $uri;
}

foreach ($routes as [$routeMethod, $routePath, $handler]) {
    $regex = '#^' . preg_replace('#\{id\}#', '(\\d+)', $routePath) . '$#';

    if ($method === $routeMethod && preg_match($regex, $uri, $matches) === 1) {
        array_shift($matches);
        $handler(...array_map('intval', $matches));
        return;
    }
}

Response::error('Rota não encontrada.', [], 404);

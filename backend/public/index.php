<?php

/**
 * Front-Controller: der EINE Einstiegspunkt für alle Anfragen.
 * Der eingebaute PHP-Server (php -S ... public/index.php) leitet jede
 * Anfrage hierher. Aufgaben: Autoloading, CORS, Routing, Fehlerbehandlung.
 */

declare(strict_types=1);

use App\Response;
use App\Router;
use App\Controllers\KundeController;

// --- 1) Autoloader: lädt Klassen automatisch aus dem src/-Verzeichnis ---------
//     Begründung: spart manuelle require-Aufrufe. Konvention: die Klasse
//     App\Foo\Bar liegt in der Datei src/Foo/Bar.php (PSR-4-Stil).
spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (str_starts_with($class, $prefix)) {
        $relative = substr($class, strlen($prefix));
        $file = __DIR__ . '/../src/' . str_replace('\\', '/', $relative) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

$config = require __DIR__ . '/../config/config.php';
$debug = $config['debug'] ?? false;

// --- 2) CORS: erlaubt dem React-Frontend (anderer Port) den Zugriff -----------
//     Entwicklung: jede Herkunft erlauben. In Produktion auf die echte
//     Frontend-Adresse einschränken.
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Preflight-Anfrage (der Browser fragt vor einem POST "darf ich?") sofort
// und ohne Inhalt beantworten.
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// --- 3) Routen definieren -----------------------------------------------------
$router = new Router();

// Kleine Startseite zum schnellen Prüfen im Browser.
$router->get('/', function (): void {
    Response::json([
        'name'      => 'ServiceManager API',
        'status'    => 'ok',
        'endpoints' => ['GET /kunden', 'POST /kunden'],
    ]);
});

$kundeController = new KundeController();
$router->get('/kunden',  fn() => $kundeController->index());
$router->post('/kunden', fn() => $kundeController->create());

// --- 4) Anfrage abarbeiten; Fehler einheitlich als JSON ausgeben --------------
try {
    $method = $_SERVER['REQUEST_METHOD'];
    $path   = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $router->dispatch($method, $path);
} catch (\Throwable $e) {
    error_log($e->getMessage()); // Detail in die Server-Logs (docker compose logs backend)
    $extra = $debug ? ['detail' => $e->getMessage()] : [];
    Response::error('Interner Serverfehler', 500, $extra);
}

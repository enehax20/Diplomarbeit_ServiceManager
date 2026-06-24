<?php

/**
 * Front-Controller: der EINE Einstiegspunkt für alle Anfragen.
 * Der eingebaute PHP-Server (php -S ... public/index.php) leitet jede
 * Anfrage hierher. Aufgaben: Autoloading, CORS, Routing, Fehlerbehandlung.
 */

declare(strict_types=1);

use App\Auth;
use App\Response;
use App\Router;
use App\Controllers\AuthController;
use App\Controllers\KundeController;
use App\Controllers\MitarbeiterController;
use App\Controllers\AuftragController;

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
//     Weil wir mit Session-Cookies arbeiten (credentials), darf die erlaubte
//     Herkunft NICHT "*" sein – wir spiegeln die anfragende Herkunft zurück und
//     erlauben das Mitsenden von Cookies. In Produktion auf die echte
//     Frontend-Adresse einschränken.
$origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
header('Access-Control-Allow-Origin: ' . $origin);
header('Vary: Origin');
header('Access-Control-Allow-Credentials: true');
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
        'endpoints' => [
            'POST /login', 'POST /logout', 'GET /me',
            'GET /kunden', 'GET /kunden/auswahl', 'POST /kunden', 'PUT /kunden/{id}', 'DELETE /kunden/{id}',
            'GET /mitarbeiter', 'GET /mitarbeiter/auswahl', 'POST /mitarbeiter', 'PUT /mitarbeiter/{id}', 'DELETE /mitarbeiter/{id}',
            'GET /auftraege', 'GET /auftraege/{id}', 'POST /auftraege', 'PUT /auftraege/{id}', 'PUT /auftraege/{id}/status', 'DELETE /auftraege/{id}',
        ],
    ]);
});

// --- Anmeldung (öffentlich erreichbar) ---------------------------------------
$authController = new AuthController();
$router->post('/login',  fn() => $authController->login());
$router->post('/logout', fn() => $authController->logout());
$router->get('/me',      fn() => $authController->me());

// --- Kund:innen (nur für angemeldete Personen) -------------------------------
//     Auth::requireLogin() sendet bei fehlender Anmeldung 401 und bricht ab.
$kundeController = new KundeController();
$router->get('/kunden',         function () use ($kundeController) {
    Auth::requireLogin();
    $kundeController->index();
});
// Schlanke Auswahlliste (ID + Name) für Auswahlfelder, z. B. im Auftrag.
$router->get('/kunden/auswahl', function () use ($kundeController) {
    Auth::requireLogin();
    $kundeController->auswahl();
});
$router->post('/kunden',        function () use ($kundeController) {
    Auth::requireLogin();
    $kundeController->create();
});
$router->put('/kunden/{id}',    function ($params) use ($kundeController) {
    Auth::requireLogin();
    $kundeController->update($params);
});
$router->delete('/kunden/{id}', function ($params) use ($kundeController) {
    Auth::requireLogin();
    $kundeController->delete($params);
});

// --- Mitarbeiter:innen (NUR Admin) -------------------------------------------
//     Auth::requireAdmin() sendet 401 (nicht angemeldet) bzw. 403 (kein Admin).
$mitarbeiterController = new MitarbeiterController();
// Auswahlliste der aktiven Mitarbeiter:innen – für ALLE Angemeldeten (Zuweisung
// am Auftrag), daher NUR requireLogin (nicht requireAdmin).
$router->get('/mitarbeiter/auswahl', function () use ($mitarbeiterController) {
    Auth::requireLogin();
    $mitarbeiterController->auswahl();
});
$router->get('/mitarbeiter',         function () use ($mitarbeiterController) {
    Auth::requireAdmin();
    $mitarbeiterController->index();
});
$router->post('/mitarbeiter',        function () use ($mitarbeiterController) {
    Auth::requireAdmin();
    $mitarbeiterController->create();
});
$router->put('/mitarbeiter/{id}',    function ($params) use ($mitarbeiterController) {
    Auth::requireAdmin();
    $mitarbeiterController->update($params);
});
$router->delete('/mitarbeiter/{id}', function ($params) use ($mitarbeiterController) {
    Auth::requireAdmin();
    $mitarbeiterController->delete($params);
});

// --- Aufträge (für alle Angemeldeten; alle sehen alle Aufträge) --------------
$auftragController = new AuftragController();
$router->get('/auftraege',            function () use ($auftragController) {
    Auth::requireLogin();
    $auftragController->index();
});
$router->get('/auftraege/{id}',       function ($params) use ($auftragController) {
    Auth::requireLogin();
    $auftragController->show($params);
});
$router->post('/auftraege',           function () use ($auftragController) {
    Auth::requireLogin();
    $auftragController->create();
});
$router->put('/auftraege/{id}',       function ($params) use ($auftragController) {
    Auth::requireLogin();
    $auftragController->update($params);
});
// Eigener Endpunkt: Statuswechsel (Status + Verlaufszeile in EINER Transaktion).
$router->put('/auftraege/{id}/status', function ($params) use ($auftragController) {
    Auth::requireLogin();
    $auftragController->updateStatus($params);
});
$router->delete('/auftraege/{id}',    function ($params) use ($auftragController) {
    Auth::requireLogin();
    $auftragController->delete($params);
});

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

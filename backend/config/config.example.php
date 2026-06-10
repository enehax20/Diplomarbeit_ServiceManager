<?php
/**
 * Beispiel-Konfiguration.
 *
 * Zum Einrichten: diese Datei nach "config.php" kopieren und die echten
 * Zugangsdaten eintragen. "config.php" ist per .gitignore ausgeschlossen,
 * damit niemals Zugangsdaten ins Repository gelangen.
 *
 * Zwei Betriebsarten:
 *  - Backend läuft in Docker (Standard hier): host = "db", port = 3306
 *    (Servicename + interner Port aus docker-compose.yml).
 *  - Backend läuft lokal auf dem Host: host = "127.0.0.1", port = 3307
 *    (der nach außen veröffentlichte Port der DB).
 */
return [
    'db' => [
        'host'     => 'db',
        'port'     => 3306,
        'name'     => 'servicemanager',
        'user'     => 'servicemanager',
        'password' => 'CHANGE_ME',
        'charset'  => 'utf8mb4',
    ],

    // true: Fehlerantworten enthalten zusätzliche Details (nur Entwicklung).
    // In Produktion auf false setzen.
    'debug' => true,
];

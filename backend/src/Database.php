<?php

namespace App;

use PDO;

/**
 * Erzeugt die Datenbankverbindung (PDO) aus der Konfiguration.
 *
 * Wir verwenden durchgehend PDO mit echten Prepared Statements: Werte werden
 * getrennt von der SQL-Anweisung an die Datenbank gesendet, was SQL-Injection
 * verhindert.
 */
class Database
{
    // Die Verbindung wird einmal erstellt und wiederverwendet.
    private static ?PDO $pdo = null;

    /** Liefert die (einmalig aufgebaute) PDO-Verbindung. */
    public static function getConnection(): PDO
    {
        if (self::$pdo === null) {
            $config = require __DIR__ . '/../config/config.php';
            $db = $config['db'];

            // DSN = "Adresse" der Datenbank für PDO.
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $db['host'],
                $db['port'],
                $db['name'],
                $db['charset']
            );

            self::$pdo = new PDO($dsn, $db['user'], $db['password'], [
                // Fehler als Exceptions melden (statt stiller false-Rückgaben).
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                // Ergebniszeilen als assoziative Arrays liefern.
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                // Echte Prepared Statements der DB nutzen (keine PDO-Emulation).
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        }

        return self::$pdo;
    }
}

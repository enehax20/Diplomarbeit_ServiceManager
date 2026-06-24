<?php

namespace App\Controllers;

use App\Auth;
use App\Database;
use App\Response;

/**
 * Anmeldung (Login) für Mitarbeiter:innen.
 *  POST /login   – Benutzername + Passwort prüfen, Session anlegen.
 *  POST /logout  – Session beenden.
 *  GET  /me      – aktuell angemeldete Person zurückgeben (oder 401).
 */
class AuthController
{
    /** POST /login – prüft die Zugangsdaten und meldet die Person an. */
    public function login(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) {
            Response::error('Ungültiger oder leerer JSON-Body', 400);
            return;
        }

        $benutzername = trim((string)($input['benutzername'] ?? ''));
        $passwort     = (string)($input['passwort'] ?? '');

        if ($benutzername === '' || $passwort === '') {
            Response::error('Benutzername und Passwort sind erforderlich.', 422);
            return;
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT mitarbeiter_id, vorname, nachname, benutzername, passwort_hash, rolle, aktiv
             FROM mitarbeiter
             WHERE benutzername = :b
             LIMIT 1'
        );
        $stmt->execute([':b' => $benutzername]);
        $mitarbeiter = $stmt->fetch();

        // Bewusst EINE allgemeine Fehlermeldung, egal ob Benutzer:in unbekannt,
        // Passwort falsch oder Konto deaktiviert -> verrät Angreifern nichts.
        $passwortOk = $mitarbeiter && password_verify($passwort, $mitarbeiter['passwort_hash']);
        if (!$passwortOk || (int) $mitarbeiter['aktiv'] !== 1) {
            Response::error('Benutzername oder Passwort ist falsch.', 401);
            return;
        }

        Auth::login($mitarbeiter);

        // Nur unkritische Felder zurückgeben (kein Hash).
        Response::json([
            'mitarbeiter_id' => (int) $mitarbeiter['mitarbeiter_id'],
            'vorname'        => $mitarbeiter['vorname'],
            'nachname'       => $mitarbeiter['nachname'],
            'benutzername'   => $mitarbeiter['benutzername'],
            'rolle'          => $mitarbeiter['rolle'],
        ], 200);
    }

    /** POST /logout – beendet die Anmeldung. */
    public function logout(): void
    {
        Auth::logout();
        http_response_code(204);
    }

    /** GET /me – gibt die angemeldete Person zurück, sonst 401. */
    public function me(): void
    {
        $user = Auth::user();
        if ($user === null) {
            Response::error('Nicht angemeldet', 401);
            return;
        }
        Response::json($user, 200);
    }
}

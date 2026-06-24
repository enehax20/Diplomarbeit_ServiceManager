<?php

namespace App;

/**
 * Einfache, sitzungsbasierte Anmeldung (Login).
 *
 * Idee (bewusst simpel gehalten, gut erklärbar):
 *  - Nach erfolgreichem Login merken wir uns die angemeldete Person in der
 *    PHP-Session ($_SESSION). Der Browser bekommt dafür ein Session-Cookie.
 *  - Geschützte Endpunkte rufen requireLogin() (bzw. requireAdmin()) auf.
 *  - Es gibt zwei Rollen: 'admin' und 'mitarbeiter' (Spalte mitarbeiter.rolle).
 *
 * Wir speichern in der Session NUR unkritische Daten (ID, Name, Rolle) –
 * niemals das Passwort oder den Hash.
 */
class Auth
{
    /** Startet die Session genau einmal (mit sinnvollen Cookie-Einstellungen). */
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        // Cookie-Einstellungen VOR session_start setzen.
        //  - httponly: das Cookie ist für JavaScript nicht lesbar (Schutz vor XSS).
        //  - samesite 'Lax': für die lokale Entwicklung (Frontend :5173, Backend
        //    :8000 = gleiche Site "localhost") ausreichend; das Cookie wird gesendet.
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    /** Merkt sich die angemeldete Person in der Session. */
    public static function login(array $mitarbeiter): void
    {
        self::start();
        // Session-ID erneuern -> Schutz vor "Session Fixation".
        session_regenerate_id(true);
        $_SESSION['user'] = [
            'mitarbeiter_id' => (int) $mitarbeiter['mitarbeiter_id'],
            'vorname'        => $mitarbeiter['vorname'],
            'nachname'       => $mitarbeiter['nachname'],
            'benutzername'   => $mitarbeiter['benutzername'],
            'rolle'          => $mitarbeiter['rolle'],
        ];
    }

    /** Beendet die Anmeldung (Logout) und räumt die Session auf. */
    public static function logout(): void
    {
        self::start();
        $_SESSION = [];
        session_destroy();
    }

    /** Liefert die angemeldete Person oder null, wenn niemand angemeldet ist. */
    public static function user(): ?array
    {
        self::start();
        return $_SESSION['user'] ?? null;
    }

    /**
     * Stellt sicher, dass jemand angemeldet ist. Sonst: 401 + Abbruch.
     * Beide Rollen dürfen Kund:innen/Aufträge verwalten.
     */
    public static function requireLogin(): array
    {
        $user = self::user();
        if ($user === null) {
            Response::error('Nicht angemeldet', 401);
            exit;
        }
        return $user;
    }

    /**
     * Stellt sicher, dass die angemeldete Person die Rolle 'admin' hat.
     * Sonst: 403 + Abbruch. (Für spätere, nur-Admin-Funktionen wie die
     * Mitarbeiter-Verwaltung vorbereitet.)
     */
    public static function requireAdmin(): array
    {
        $user = self::requireLogin();
        if (($user['rolle'] ?? null) !== 'admin') {
            Response::error('Keine Berechtigung (nur Admin)', 403);
            exit;
        }
        return $user;
    }
}

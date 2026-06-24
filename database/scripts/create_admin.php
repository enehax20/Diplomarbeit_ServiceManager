<?php
/**
 * create_admin.php – legt einen Login-Benutzer mit Rolle 'admin' an.
 *
 * WAS ES TUT
 *   - erzeugt ein zufälliges Klartext-Passwort,
 *   - hasht es mit PHP password_hash() (bcrypt) – im Klartext wird es NICHT
 *     gespeichert, nur der Hash landet in der Spalte mitarbeiter.passwort_hash,
 *   - fügt eine Zeile in die Tabelle "mitarbeiter" ein (rolle='admin', aktiv=1),
 *   - gibt Benutzername UND Klartext-Passwort EINMALIG aus, damit du dich
 *     anmelden kannst. Notiere es dir – es lässt sich später nicht erneut
 *     anzeigen (es ist nur als Hash gespeichert).
 *
 * AUFRUF (Benutzername als Parameter, Rolle optional):
 *   docker compose run --rm -v "${PWD}/database/scripts:/scripts" \
 *     backend php /scripts/create_admin.php <benutzername> [rolle]
 *
 *   [rolle] ist 'admin' (Standard) oder 'mitarbeiter'. So lassen sich für den
 *   Test der zwei Rollen beide Benutzertypen anlegen, z. B.:
 *     ... create_admin.php chef            -> Rolle admin
 *     ... create_admin.php anna mitarbeiter -> Rolle mitarbeiter
 *
 *   PowerShell (Windows): ${PWD} funktioniert genauso, z. B.
 *     docker compose run --rm -v "${PWD}/database/scripts:/scripts" `
 *       backend php /scripts/create_admin.php chef
 *
 * Das Skript läuft im Backend-Container (hat PHP + pdo_mysql) und verbindet
 * sich im Docker-Netz zur DB über den Servicenamen "db". DB-Zugangsdaten
 * kommen aus Umgebungsvariablen mit sinnvollen Entwicklungs-Standardwerten
 * (siehe docker-compose.yml). So bleibt das Skript von backend/config.php
 * unabhängig.
 */

declare(strict_types=1);

// CLI-Argumente prüfen --------------------------------------------------------
if ($argc < 2 || trim((string) $argv[1]) === '') {
    fwrite(STDERR, "Fehler: Benutzername fehlt.\n");
    fwrite(STDERR, "Aufruf: php create_admin.php <benutzername> [admin|mitarbeiter]\n");
    exit(1);
}
$benutzername = trim((string) $argv[1]);

// Rolle optional (Standard: admin). Nur die beiden gültigen Werte zulassen.
$rolle = strtolower(trim((string) ($argv[2] ?? 'admin')));
if (!in_array($rolle, ['admin', 'mitarbeiter'], true)) {
    fwrite(STDERR, "Fehler: Rolle muss 'admin' oder 'mitarbeiter' sein.\n");
    exit(1);
}

// DB-Verbindungsdaten (Env mit Dev-Standardwerten aus docker-compose.yml) ------
$dbHost = getenv('DB_HOST') ?: 'db';
$dbPort = getenv('DB_PORT') ?: '3306';
$dbName = getenv('DB_NAME') ?: 'servicemanager';
$dbUser = getenv('DB_USER') ?: 'servicemanager';
$dbPass = getenv('DB_PASSWORD') ?: 'servicemanager_pw';

// Zufälliges, gut lesbares Klartext-Passwort erzeugen (12 Zeichen) ------------
// Mehrdeutige Zeichen (0/O, 1/l/I) bewusst weggelassen.
$alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
$klartext = '';
for ($i = 0; $i < 12; $i++) {
    $klartext .= $alphabet[random_int(0, strlen($alphabet) - 1)];
}
$hash = password_hash($klartext, PASSWORD_DEFAULT); // bcrypt

// Verbindung + Insert ---------------------------------------------------------
$dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $dbHost, $dbPort, $dbName);

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // Doppelten Benutzernamen vorab abfangen (benutzername ist UNIQUE).
    $check = $pdo->prepare('SELECT 1 FROM mitarbeiter WHERE benutzername = :b LIMIT 1');
    $check->execute([':b' => $benutzername]);
    if ($check->fetchColumn() !== false) {
        fwrite(STDERR, "Fehler: Benutzername '{$benutzername}' existiert bereits.\n");
        exit(1);
    }

    // Prepared Statement – keine Werte direkt in den SQL-Text einsetzen.
    $insert = $pdo->prepare(
        'INSERT INTO mitarbeiter (vorname, nachname, email, benutzername, passwort_hash, rolle, aktiv)
         VALUES (:vorname, :nachname, NULL, :benutzername, :hash, :rolle, 1)'
    );
    $insert->execute([
        ':vorname'      => $rolle === 'admin' ? 'Admin' : 'Mitarbeiter',
        ':nachname'     => $benutzername,
        ':benutzername' => $benutzername,
        ':hash'         => $hash,
        ':rolle'        => $rolle,
    ]);

    $id = (int) $pdo->lastInsertId();

    // Erfolg + Zugangsdaten ausgeben (Klartext nur HIER, einmalig) ------------
    echo "\n";
    echo "Benutzer angelegt (mitarbeiter_id = {$id}).\n";
    echo "------------------------------------------------------------\n";
    echo "  Benutzername : {$benutzername}\n";
    echo "  Passwort     : {$klartext}\n";
    echo "  Rolle        : {$rolle}\n";
    echo "------------------------------------------------------------\n";
    echo "Bitte das Passwort notieren – es wird nur als Hash gespeichert\n";
    echo "und kann nicht erneut angezeigt werden.\n\n";
    exit(0);

} catch (PDOException $e) {
    fwrite(STDERR, "Datenbankfehler: " . $e->getMessage() . "\n");
    exit(1);
}

<?php

namespace App\Controllers;

use App\Auth;
use App\Database;
use App\Response;

/**
 * Endpunkte für Mitarbeiter:innen (Stammdaten) – NUR für Admins.
 *   GET    /mitarbeiter        – alle auflisten (ohne Passwort-Hash)
 *   POST   /mitarbeiter        – neue:n Mitarbeiter:in anlegen (Admin tippt Passwort)
 *   PUT    /mitarbeiter/{id}   – bearbeiten (Name/E-Mail/Rolle/aktiv, optional neues Passwort)
 *
 * Mitarbeiter:innen werden NICHT gelöscht, sondern über "aktiv" deaktiviert,
 * damit historische Zuordnungen erhalten bleiben.
 *
 * Hinweis: Der Passwort-Hash wird NIE zurückgegeben.
 */
class MitarbeiterController
{
    /** GET /mitarbeiter – alle Mitarbeiter:innen, sortiert nach Name. */
    public function index(): void
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->query(
            'SELECT mitarbeiter_id, vorname, nachname, email, benutzername, rolle, aktiv, erstellt_am
             FROM mitarbeiter
             ORDER BY nachname, vorname'
        );
        Response::json($stmt->fetchAll(), 200);
    }

    /**
     * GET /mitarbeiter/auswahl – schlanke Liste der AKTIVEN Mitarbeiter:innen
     * (nur ID + Name) für Auswahlfelder, z. B. die Zuweisung am Auftrag.
     *
     * Anders als index() ist dieser Endpunkt NICHT auf Admins beschränkt:
     * Auch normale Mitarbeiter:innen müssen beim Anlegen/Bearbeiten eines
     * Auftrags eine:n Bearbeiter:in auswählen können. Es werden bewusst nur
     * unkritische Felder zurückgegeben (kein Passwort, keine Rolle).
     */
    public function auswahl(): void
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->query(
            'SELECT mitarbeiter_id, vorname, nachname
             FROM mitarbeiter
             WHERE aktiv = 1
             ORDER BY nachname, vorname'
        );
        Response::json($stmt->fetchAll(), 200);
    }

    /** POST /mitarbeiter – legt eine:n neue:n Mitarbeiter:in an. */
    public function create(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) {
            Response::error('Ungültiger oder leerer JSON-Body', 400);
            return;
        }

        $data = $this->cleanInput($input);
        $passwort = (string)($input['passwort'] ?? '');

        // Validieren (inkl. Passwort, das beim Anlegen Pflicht ist).
        $errors = $this->validate($data, $passwort, true);
        if ($errors) {
            Response::error('Validierung fehlgeschlagen', 422, ['fields' => $errors]);
            return;
        }

        $pdo = Database::getConnection();
        if ($this->benutzernameExists($pdo, $data['benutzername'], null)) {
            Response::error('Validierung fehlgeschlagen', 422, [
                'fields' => ['benutzername' => 'Dieser Benutzername ist bereits vergeben.'],
            ]);
            return;
        }

        $stmt = $pdo->prepare(
            'INSERT INTO mitarbeiter (vorname, nachname, email, benutzername, passwort_hash, rolle, aktiv)
             VALUES (:vorname, :nachname, :email, :benutzername, :hash, :rolle, :aktiv)'
        );
        try {
            $stmt->execute([
                ':vorname'      => $data['vorname'],
                ':nachname'     => $data['nachname'],
                ':email'        => $data['email'] !== '' ? $data['email'] : null,
                ':benutzername' => $data['benutzername'],
                ':hash'         => password_hash($passwort, PASSWORD_DEFAULT),
                ':rolle'        => $data['rolle'],
                ':aktiv'        => $data['aktiv'],
            ]);
        } catch (\PDOException $e) {
            if (($e->errorInfo[1] ?? null) === 1062) { // doppelter benutzername (Wettlauf)
                Response::error('Dieser Benutzername ist bereits vergeben.', 409);
                return;
            }
            throw $e;
        }

        $newId = (int) $pdo->lastInsertId();
        Response::json($this->findById($pdo, $newId), 201);
    }

    /** PUT /mitarbeiter/{id} – bearbeitet eine:n Mitarbeiter:in. */
    public function update(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            Response::error('Ungültige ID', 400);
            return;
        }

        $pdo = Database::getConnection();
        if ($this->findById($pdo, $id) === false) {
            Response::error('Mitarbeiter:in nicht gefunden', 404);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) {
            Response::error('Ungültiger oder leerer JSON-Body', 400);
            return;
        }

        $data = $this->cleanInput($input);
        // Passwort ist beim Bearbeiten OPTIONAL: leer = unverändert lassen.
        $passwort = (string)($input['passwort'] ?? '');

        $errors = $this->validate($data, $passwort, false);
        if ($errors) {
            Response::error('Validierung fehlgeschlagen', 422, ['fields' => $errors]);
            return;
        }

        // Selbstschutz: Man darf sich nicht selbst deaktivieren oder sich selbst
        // die Admin-Rolle entziehen (sonst sperrt man sich womöglich aus).
        $current = Auth::user();
        if ((int) $current['mitarbeiter_id'] === $id) {
            if ($data['aktiv'] === 0) {
                Response::error('Sie können sich nicht selbst deaktivieren.', 409);
                return;
            }
            if ($data['rolle'] !== 'admin') {
                Response::error('Sie können sich nicht selbst die Admin-Rolle entziehen.', 409);
                return;
            }
        }

        if ($this->benutzernameExists($pdo, $data['benutzername'], $id)) {
            Response::error('Validierung fehlgeschlagen', 422, [
                'fields' => ['benutzername' => 'Dieser Benutzername ist bereits vergeben.'],
            ]);
            return;
        }

        // Passwort nur aktualisieren, wenn ein neues angegeben wurde.
        $setPasswort = $passwort !== '' ? ', passwort_hash = :hash' : '';
        $sql =
            'UPDATE mitarbeiter
                SET vorname = :vorname, nachname = :nachname, email = :email,
                    benutzername = :benutzername, rolle = :rolle, aktiv = :aktiv' . $setPasswort . '
              WHERE mitarbeiter_id = :id';

        $bind = [
            ':vorname'      => $data['vorname'],
            ':nachname'     => $data['nachname'],
            ':email'        => $data['email'] !== '' ? $data['email'] : null,
            ':benutzername' => $data['benutzername'],
            ':rolle'        => $data['rolle'],
            ':aktiv'        => $data['aktiv'],
            ':id'           => $id,
        ];
        if ($passwort !== '') {
            $bind[':hash'] = password_hash($passwort, PASSWORD_DEFAULT);
        }

        $stmt = $pdo->prepare($sql);
        try {
            $stmt->execute($bind);
        } catch (\PDOException $e) {
            if (($e->errorInfo[1] ?? null) === 1062) {
                Response::error('Dieser Benutzername ist bereits vergeben.', 409);
                return;
            }
            throw $e;
        }

        Response::json($this->findById($pdo, $id), 200);
    }

    /** DELETE /mitarbeiter/{id} – löscht eine:n Mitarbeiter:in endgültig. */
    public function delete(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            Response::error('Ungültige ID', 400);
            return;
        }

        // Selbstschutz: Man darf das eigene Konto nicht löschen.
        $current = Auth::user();
        if ((int) $current['mitarbeiter_id'] === $id) {
            Response::error('Sie können Ihr eigenes Konto nicht löschen.', 409);
            return;
        }

        $pdo = Database::getConnection();
        try {
            $stmt = $pdo->prepare('DELETE FROM mitarbeiter WHERE mitarbeiter_id = :id');
            $stmt->execute([':id' => $id]);

            if ($stmt->rowCount() === 0) {
                Response::error('Mitarbeiter:in nicht gefunden', 404);
                return;
            }

            http_response_code(204); // erfolgreich gelöscht
        } catch (\PDOException $e) {
            // 23000 = Integritätsverletzung: der Fremdschlüssel auftrag -> mitarbeiter
            // (ON DELETE RESTRICT) verhindert das Löschen, weil noch Aufträge
            // zugewiesen sind. Empfehlung: stattdessen deaktivieren.
            if ($e->getCode() === '23000') {
                Response::error(
                    'Mitarbeiter:in kann nicht gelöscht werden, weil noch Aufträge zugewiesen sind. Bitte stattdessen deaktivieren.',
                    409
                );
                return;
            }
            throw $e;
        }
    }

    // ------------------------------------------------------------------------
    // Hilfsmethoden (privat)
    // ------------------------------------------------------------------------

    /** Säubert die gemeinsamen Felder aus dem JSON-Body. */
    private function cleanInput(array $input): array
    {
        return [
            'vorname'      => trim((string)($input['vorname']      ?? '')),
            'nachname'     => trim((string)($input['nachname']     ?? '')),
            'email'        => trim((string)($input['email']        ?? '')),
            'benutzername' => trim((string)($input['benutzername'] ?? '')),
            'rolle'        => trim((string)($input['rolle']        ?? '')),
            // aktiv kommt als true/false oder 1/0 -> auf 0/1 normalisieren.
            'aktiv'        => !empty($input['aktiv']) ? 1 : 0,
        ];
    }

    /**
     * Prüft die Felder. $passwortPflicht = true beim Anlegen (Passwort nötig),
     * false beim Bearbeiten (leeres Passwort = unverändert).
     */
    private function validate(array $data, string $passwort, bool $passwortPflicht): array
    {
        $errors = [];
        if ($data['vorname'] === '') {
            $errors['vorname'] = 'Vorname ist erforderlich.';
        } elseif (mb_strlen($data['vorname']) > 80) {
            $errors['vorname'] = 'Vorname ist zu lang (max. 80 Zeichen).';
        }
        if ($data['nachname'] === '') {
            $errors['nachname'] = 'Nachname ist erforderlich.';
        } elseif (mb_strlen($data['nachname']) > 80) {
            $errors['nachname'] = 'Nachname ist zu lang (max. 80 Zeichen).';
        }
        if ($data['benutzername'] === '') {
            $errors['benutzername'] = 'Benutzername ist erforderlich.';
        } elseif (mb_strlen($data['benutzername']) > 60) {
            $errors['benutzername'] = 'Benutzername ist zu lang (max. 60 Zeichen).';
        }
        if ($data['email'] !== '' && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'E-Mail-Adresse ist ungültig.';
        }
        if (!in_array($data['rolle'], ['admin', 'mitarbeiter'], true)) {
            $errors['rolle'] = 'Rolle muss „admin" oder „mitarbeiter" sein.';
        }
        // Passwort: beim Anlegen Pflicht; wenn gesetzt, mind. 8 Zeichen.
        if ($passwortPflicht && $passwort === '') {
            $errors['passwort'] = 'Passwort ist erforderlich.';
        } elseif ($passwort !== '' && mb_strlen($passwort) < 8) {
            $errors['passwort'] = 'Passwort muss mindestens 8 Zeichen haben.';
        }
        return $errors;
    }

    /** Prüft, ob der Benutzername bereits bei einer ANDEREN Person existiert. */
    private function benutzernameExists(\PDO $pdo, string $benutzername, ?int $ignoreId): bool
    {
        $sql = 'SELECT 1 FROM mitarbeiter WHERE benutzername = :b';
        $bind = [':b' => $benutzername];
        if ($ignoreId !== null) {
            $sql .= ' AND mitarbeiter_id <> :id';
            $bind[':id'] = $ignoreId;
        }
        $sql .= ' LIMIT 1';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($bind);
        return (bool) $stmt->fetchColumn();
    }

    /** Liest eine:n Mitarbeiter:in (ohne Passwort-Hash); Array oder false. */
    private function findById(\PDO $pdo, int $id)
    {
        $stmt = $pdo->prepare(
            'SELECT mitarbeiter_id, vorname, nachname, email, benutzername, rolle, aktiv, erstellt_am
             FROM mitarbeiter WHERE mitarbeiter_id = :id'
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }
}

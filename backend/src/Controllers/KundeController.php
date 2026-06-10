<?php

namespace App\Controllers;

use App\Database;
use App\Response;

/**
 * Endpunkte für Kund:innen (Stammdaten).
 * Aktuell: alle auflisten (index) und neu anlegen (create).
 */
class KundeController
{
    /** GET /kunden – alle Kund:innen, sortiert nach Name. */
    public function index(): void
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->query(
            'SELECT kunde_id, betrieb_id, vorname, nachname, telefon, email,
                    strasse, plz, ort, erstellt_am
             FROM kunde
             ORDER BY nachname, vorname'
        );
        $kunden = $stmt->fetchAll();

        Response::json($kunden, 200);
    }

    /** POST /kunden – legt eine neue Kund:in an und gibt den Datensatz zurück. */
    public function create(): void
    {
        // 1) JSON-Body einlesen.
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) {
            Response::error('Ungültiger oder leerer JSON-Body', 400);
            return;
        }

        // 2) Eingaben säubern (trimmen).
        $vorname  = trim((string)($input['vorname']  ?? ''));
        $nachname = trim((string)($input['nachname'] ?? ''));
        $telefon  = trim((string)($input['telefon']  ?? ''));
        $email    = trim((string)($input['email']    ?? ''));
        $strasse  = trim((string)($input['strasse']  ?? ''));
        $plz      = trim((string)($input['plz']      ?? ''));
        $ort      = trim((string)($input['ort']      ?? ''));

        // 3) Validieren und Fehler je Feld sammeln (konsistente Validierung, Ziel 2).
        $errors = [];
        if ($vorname === '') {
            $errors['vorname'] = 'Vorname ist erforderlich.';
        } elseif (mb_strlen($vorname) > 80) {
            $errors['vorname'] = 'Vorname ist zu lang (max. 80 Zeichen).';
        }
        if ($nachname === '') {
            $errors['nachname'] = 'Nachname ist erforderlich.';
        } elseif (mb_strlen($nachname) > 80) {
            $errors['nachname'] = 'Nachname ist zu lang (max. 80 Zeichen).';
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'E-Mail-Adresse ist ungültig.';
        }

        if ($errors) {
            // 422 = Unprocessable Entity: Anfrage verstanden, Inhalt ungültig.
            Response::error('Validierung fehlgeschlagen', 422, ['fields' => $errors]);
            return;
        }

        $pdo = Database::getConnection();

        // Eindeutigkeit prüfen: E-Mail und Telefon dürfen nicht doppelt vorkommen
        // (zusätzlich zur UNIQUE-Sicherung in der DB – hier für klare Feldmeldungen).
        $dupErrors = [];
        if ($email !== '' && $this->existsKundeWith($pdo, 'email', $email)) {
            $dupErrors['email'] = 'Diese E-Mail-Adresse ist bereits vergeben.';
        }
        if ($telefon !== '' && $this->existsKundeWith($pdo, 'telefon', $telefon)) {
            $dupErrors['telefon'] = 'Diese Telefonnummer ist bereits vergeben.';
        }
        if ($dupErrors) {
            Response::error('Validierung fehlgeschlagen', 422, ['fields' => $dupErrors]);
            return;
        }

        // 4) Betrieb bestimmen: Das System bedient aktuell EINEN Betrieb; neue
        //    Kund:innen werden ihm zugeordnet. (Eine Betriebsauswahl ist erst
        //    bei Mehrbetrieb-Unterstützung nötig.)
        $betriebId = (int) $pdo->query(
            'SELECT betrieb_id FROM betrieb ORDER BY betrieb_id LIMIT 1'
        )->fetchColumn();

        if ($betriebId === 0) {
            Response::error('Kein Betrieb angelegt – bitte zuerst die Seed-Daten einspielen.', 409);
            return;
        }

        // 5) Einfügen mit Prepared Statement (Schutz vor SQL-Injection).
        //    Leere optionale Felder werden als NULL gespeichert.
        $stmt = $pdo->prepare(
            'INSERT INTO kunde (betrieb_id, vorname, nachname, telefon, email, strasse, plz, ort)
             VALUES (:betrieb_id, :vorname, :nachname, :telefon, :email, :strasse, :plz, :ort)'
        );
        try {
            $stmt->execute([
                ':betrieb_id' => $betriebId,
                ':vorname'    => $vorname,
                ':nachname'   => $nachname,
                ':telefon'    => $telefon !== '' ? $telefon : null,
                ':email'      => $email   !== '' ? $email   : null,
                ':strasse'    => $strasse !== '' ? $strasse : null,
                ':plz'        => $plz     !== '' ? $plz     : null,
                ':ort'        => $ort     !== '' ? $ort     : null,
            ]);
        } catch (\PDOException $e) {
            // Sicherheitsnetz gegen Wettlauf zweier gleichzeitiger Anfragen:
            // 1062 = doppelter Eintrag in einem UNIQUE-Index.
            if (($e->errorInfo[1] ?? null) === 1062) {
                Response::error('E-Mail-Adresse oder Telefonnummer ist bereits vergeben.', 409);
                return;
            }
            throw $e;
        }

        // 6) Den neu angelegten Datensatz zurücklesen (inkl. erstellt_am aus der DB).
        $newId = (int) $pdo->lastInsertId();
        $stmt = $pdo->prepare(
            'SELECT kunde_id, betrieb_id, vorname, nachname, telefon, email,
                    strasse, plz, ort, erstellt_am
             FROM kunde WHERE kunde_id = :id'
        );
        $stmt->execute([':id' => $newId]);
        $kunde = $stmt->fetch();

        // 201 = Created: Ressource wurde neu angelegt.
        Response::json($kunde, 201);
    }

    /** DELETE /kunden/{id} – löscht eine Kund:in, sofern nichts verknüpft ist. */
    public function delete(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            Response::error('Ungültige ID', 400);
            return;
        }

        $pdo = Database::getConnection();

        try {
            $stmt = $pdo->prepare('DELETE FROM kunde WHERE kunde_id = :id');
            $stmt->execute([':id' => $id]);

            // rowCount() = 0 -> es gab keine Kund:in mit dieser ID.
            if ($stmt->rowCount() === 0) {
                Response::error('Kund:in nicht gefunden', 404);
                return;
            }

            // 204 = No Content: erfolgreich gelöscht, nichts zurückzugeben.
            http_response_code(204);
        } catch (\PDOException $e) {
            // SQLSTATE 23000 = Integritätsverletzung. Hier: ein Fremdschlüssel
            // (servicegegenstand -> kunde, ON DELETE RESTRICT) verhindert das
            // Löschen, weil noch Daten an der Kund:in hängen.
            if ($e->getCode() === '23000') {
                Response::error(
                    'Kund:in kann nicht gelöscht werden, weil noch Servicegegenstände oder Aufträge zugeordnet sind.',
                    409 // Conflict
                );
                return;
            }
            throw $e; // andere DB-Fehler an den zentralen Fehler-Handler weiterreichen
        }
    }

    /** Prüft, ob bereits eine Kund:in mit diesem Wert in der Spalte existiert. */
    private function existsKundeWith(\PDO $pdo, string $column, string $value): bool
    {
        // Spaltenname aus fester Whitelist (kein Nutzer-Input) -> kein Injection-Risiko.
        if (!in_array($column, ['email', 'telefon'], true)) {
            return false;
        }
        $stmt = $pdo->prepare("SELECT 1 FROM kunde WHERE $column = :value LIMIT 1");
        $stmt->execute([':value' => $value]);
        return (bool) $stmt->fetchColumn();
    }
}

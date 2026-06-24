<?php

namespace App\Controllers;

use App\Database;
use App\Response;

/**
 * Endpunkte für Kund:innen (Stammdaten):
 *   GET    /kunden        – alle auflisten
 *   POST   /kunden        – neue:n Kund:in anlegen
 *   PUT    /kunden/{id}   – bestehende:n Kund:in bearbeiten
 *   DELETE /kunden/{id}   – Kund:in löschen (sofern kein Auftrag hängt)
 *
 * Hinweis: Das vereinfachte Modell kennt keinen Betrieb mehr; Kund:innen
 * stehen direkt für sich (kein betrieb_id mehr).
 */
class KundeController
{
    /** GET /kunden – alle Kund:innen, sortiert nach Name. */
    public function index(): void
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->query(
            'SELECT kunde_id, vorname, nachname, telefon, email,
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
        $data = $this->readAndValidate();
        if ($data === null) {
            return; // Fehlerantwort wurde bereits gesendet
        }

        $pdo = Database::getConnection();

        // Eindeutigkeit prüfen (klare Feldmeldungen zusätzlich zur DB-UNIQUE-Sicherung).
        $dupErrors = $this->duplicateErrors($pdo, $data['email'], $data['telefon'], null);
        if ($dupErrors) {
            Response::error('Validierung fehlgeschlagen', 422, ['fields' => $dupErrors]);
            return;
        }

        // Einfügen mit Prepared Statement (Schutz vor SQL-Injection).
        $stmt = $pdo->prepare(
            'INSERT INTO kunde (vorname, nachname, telefon, email, strasse, plz, ort)
             VALUES (:vorname, :nachname, :telefon, :email, :strasse, :plz, :ort)'
        );
        try {
            $stmt->execute($this->bindValues($data));
        } catch (\PDOException $e) {
            // Sicherheitsnetz gegen Wettlauf zweier gleichzeitiger Anfragen:
            // 1062 = doppelter Eintrag in einem UNIQUE-Index.
            if (($e->errorInfo[1] ?? null) === 1062) {
                Response::error('E-Mail-Adresse oder Telefonnummer ist bereits vergeben.', 409);
                return;
            }
            throw $e;
        }

        // Den neu angelegten Datensatz zurücklesen (inkl. erstellt_am aus der DB).
        $newId = (int) $pdo->lastInsertId();
        Response::json($this->findById($pdo, $newId), 201);
    }

    /** PUT /kunden/{id} – aktualisiert eine bestehende Kund:in. */
    public function update(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            Response::error('Ungültige ID', 400);
            return;
        }

        $pdo = Database::getConnection();

        // Existiert die Kund:in überhaupt?
        if ($this->findById($pdo, $id) === false) {
            Response::error('Kund:in nicht gefunden', 404);
            return;
        }

        $data = $this->readAndValidate();
        if ($data === null) {
            return;
        }

        // Eindeutigkeit prüfen, aber den EIGENEN Datensatz ausnehmen.
        $dupErrors = $this->duplicateErrors($pdo, $data['email'], $data['telefon'], $id);
        if ($dupErrors) {
            Response::error('Validierung fehlgeschlagen', 422, ['fields' => $dupErrors]);
            return;
        }

        $stmt = $pdo->prepare(
            'UPDATE kunde
                SET vorname = :vorname, nachname = :nachname, telefon = :telefon,
                    email = :email, strasse = :strasse, plz = :plz, ort = :ort
              WHERE kunde_id = :id'
        );
        try {
            $stmt->execute($this->bindValues($data) + [':id' => $id]);
        } catch (\PDOException $e) {
            if (($e->errorInfo[1] ?? null) === 1062) {
                Response::error('E-Mail-Adresse oder Telefonnummer ist bereits vergeben.', 409);
                return;
            }
            throw $e;
        }

        // Aktualisierten Datensatz zurückgeben.
        Response::json($this->findById($pdo, $id), 200);
    }

    /** DELETE /kunden/{id} – löscht eine Kund:in, sofern kein Auftrag hängt. */
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
            // SQLSTATE 23000 = Integritätsverletzung. Hier: der Fremdschlüssel
            // auftrag -> kunde (ON DELETE RESTRICT) verhindert das Löschen,
            // weil noch Aufträge an der Kund:in hängen.
            if ($e->getCode() === '23000') {
                Response::error(
                    'Kund:in kann nicht gelöscht werden, weil noch Aufträge zugeordnet sind.',
                    409 // Conflict
                );
                return;
            }
            throw $e; // andere DB-Fehler an den zentralen Fehler-Handler weiterreichen
        }
    }

    // ------------------------------------------------------------------------
    // Hilfsmethoden (privat) – von create() und update() gemeinsam genutzt.
    // ------------------------------------------------------------------------

    /**
     * Liest den JSON-Body, säubert die Felder und validiert sie.
     * Rückgabe: sauberes Datenarray, oder null wenn bereits eine Fehlerantwort
     * gesendet wurde.
     */
    private function readAndValidate(): ?array
    {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) {
            Response::error('Ungültiger oder leerer JSON-Body', 400);
            return null;
        }

        $data = [
            'vorname'  => trim((string)($input['vorname']  ?? '')),
            'nachname' => trim((string)($input['nachname'] ?? '')),
            'telefon'  => trim((string)($input['telefon']  ?? '')),
            'email'    => trim((string)($input['email']    ?? '')),
            'strasse'  => trim((string)($input['strasse']  ?? '')),
            'plz'      => trim((string)($input['plz']      ?? '')),
            'ort'      => trim((string)($input['ort']      ?? '')),
        ];

        // Validieren und Fehler je Feld sammeln (konsistente Validierung, Ziel 2).
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
        if ($data['email'] !== '' && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'E-Mail-Adresse ist ungültig.';
        }

        if ($errors) {
            // 422 = Unprocessable Entity: Anfrage verstanden, Inhalt ungültig.
            Response::error('Validierung fehlgeschlagen', 422, ['fields' => $errors]);
            return null;
        }

        return $data;
    }

    /** Baut die benannten Parameter für INSERT/UPDATE (leere Felder -> NULL). */
    private function bindValues(array $data): array
    {
        return [
            ':vorname'  => $data['vorname'],
            ':nachname' => $data['nachname'],
            ':telefon'  => $data['telefon'] !== '' ? $data['telefon'] : null,
            ':email'    => $data['email']   !== '' ? $data['email']   : null,
            ':strasse'  => $data['strasse'] !== '' ? $data['strasse'] : null,
            ':plz'      => $data['plz']     !== '' ? $data['plz']     : null,
            ':ort'      => $data['ort']     !== '' ? $data['ort']     : null,
        ];
    }

    /**
     * Prüft, ob E-Mail/Telefon bereits bei einer ANDEREN Kund:in vorkommen.
     * $ignoreId schließt den eigenen Datensatz aus (für update()).
     */
    private function duplicateErrors(\PDO $pdo, string $email, string $telefon, ?int $ignoreId): array
    {
        $errors = [];
        if ($email !== '' && $this->existsKundeWith($pdo, 'email', $email, $ignoreId)) {
            $errors['email'] = 'Diese E-Mail-Adresse ist bereits vergeben.';
        }
        if ($telefon !== '' && $this->existsKundeWith($pdo, 'telefon', $telefon, $ignoreId)) {
            $errors['telefon'] = 'Diese Telefonnummer ist bereits vergeben.';
        }
        return $errors;
    }

    /** Prüft, ob bereits eine (andere) Kund:in mit diesem Wert in der Spalte existiert. */
    private function existsKundeWith(\PDO $pdo, string $column, string $value, ?int $ignoreId): bool
    {
        // Spaltenname aus fester Whitelist (kein Nutzer-Input) -> kein Injection-Risiko.
        if (!in_array($column, ['email', 'telefon'], true)) {
            return false;
        }
        $sql = "SELECT 1 FROM kunde WHERE $column = :value";
        $params = [':value' => $value];
        if ($ignoreId !== null) {
            $sql .= ' AND kunde_id <> :id';
            $params[':id'] = $ignoreId;
        }
        $sql .= ' LIMIT 1';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (bool) $stmt->fetchColumn();
    }

    /** Liest einen Kunden-Datensatz; gibt das Array oder false zurück. */
    private function findById(\PDO $pdo, int $id)
    {
        $stmt = $pdo->prepare(
            'SELECT kunde_id, vorname, nachname, telefon, email,
                    strasse, plz, ort, erstellt_am
             FROM kunde WHERE kunde_id = :id'
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }
}

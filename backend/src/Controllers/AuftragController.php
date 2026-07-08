<?php

namespace App\Controllers;

use App\Auth;
use App\Database;
use App\Response;

/**
 * Endpunkte für Aufträge (fachlicher Kern):
 *   GET    /auftraege            – alle Aufträge auflisten (alle sehen alle)
 *   GET    /auftraege/{id}       – ein Auftrag inkl. Statusverlauf
 *   POST   /auftraege            – neuen Auftrag anlegen
 *   PUT    /auftraege/{id}       – Auftrag bearbeiten (Stammfelder, NICHT Status)
 *   PUT    /auftraege/{id}/status – Status ändern (eigene, abgesicherte Aktion)
 *   DELETE /auftraege/{id}       – Auftrag löschen (Historie via CASCADE mit)
 *
 * Zwei Designentscheidungen:
 *  - "Alle Mitarbeiter:innen sehen alle Aufträge" (kein persönlicher Filter) –
 *    so vom Auftraggeber gewünscht.
 *  - Statusänderung ist ein EIGENER Endpunkt, weil dabei laut Datenmodell IMMER
 *    zwei Dinge in EINER Transaktion passieren müssen: auftrag.status setzen UND
 *    eine Zeile in auftrag_status_historie schreiben. So laufen aktueller Status
 *    und Verlauf nie auseinander.
 */
class AuftragController
{
    /** Die fünf erlaubten Status-Werte (müssen exakt dem ENUM im Schema entsprechen). */
    private const STATUS = ['ANGENOMMEN', 'IN_DIAGNOSE', 'IN_REPARATUR', 'FERTIG', 'ABGEHOLT'];

    /**
     * GET /auftraege – Aufträge seitenweise (Pagination), zuletzt AKTUALISIERTE
     * zuerst, mit Kund:in- und Bearbeiter:in-Name.
     *
     * Query-Parameter (alle optional):
     *   page    – gewünschte Seite (1-basiert, Standard 1)
     *   perPage – Datensätze pro Seite (Standard 10, max. 100)
     *   q       – einfache Suche über Gegenstand/Hersteller/Titel/Kund:in-Name (LIKE)
     *
     * Antwortformat:
     *   { "data": [...], "total": int, "page": int, "perPage": int, "totalPages": int }
     *
     * Sortierung nach aktualisiert_am DESC: Aufträge, an denen zuletzt etwas
     * geändert wurde (z. B. ein Statuswechsel), stehen oben auf Seite 1.
     */
    public function index(): void
    {
        $pdo = Database::getConnection();

        // --- Parameter lesen und in sinnvolle Grenzen zwingen (clamp) ---------
        $page    = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = (int) ($_GET['perPage'] ?? 10);
        $perPage = max(1, min(100, $perPage)); // 1..100
        $q       = trim((string) ($_GET['q'] ?? ''));

        // --- Optionale Suche: WHERE-Bedingung + Parameter zusammenbauen -------
        // Hinweis: Bei echten Prepared Statements darf derselbe Platzhalter NICHT
        // mehrfach vorkommen -> für jede Spalte ein eigener Platzhalter mit demselben Wert.
        $where  = '';
        $params = [];
        if ($q !== '') {
            // Suche über Gegenstand/Hersteller/Titel, Kund:in-Name UND Bearbeiter:in-Name.
            $where = 'WHERE a.servicegegenstand LIKE :q1 OR a.hersteller LIKE :q2
                         OR a.titel LIKE :q3 OR k.vorname LIKE :q4 OR k.nachname LIKE :q5
                         OR m.vorname LIKE :q6 OR m.nachname LIKE :q7';
            $like = '%' . $q . '%';
            $params = [
                ':q1' => $like, ':q2' => $like, ':q3' => $like, ':q4' => $like,
                ':q5' => $like, ':q6' => $like, ':q7' => $like,
            ];
        }

        // --- Gesamtzahl -------------------------------------------------------
        // Beide JOINs, weil die Suche Kund:in- UND Bearbeiter:in-Name einschließt
        // (mitarbeiter per LEFT JOIN, da ein Auftrag noch unzugewiesen sein kann).
        $countStmt = $pdo->prepare(
            "SELECT COUNT(*)
             FROM auftrag a
             JOIN kunde k            ON a.kunde_id = k.kunde_id
             LEFT JOIN mitarbeiter m ON a.mitarbeiter_id = m.mitarbeiter_id
             $where"
        );
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $totalPages = (int) max(1, ceil($total / $perPage));
        $page   = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;

        // LIMIT/OFFSET als geprüfte Ganzzahlen direkt einsetzen (kein Injection-Risiko).
        $sql = "SELECT a.auftrag_id, a.kunde_id, a.mitarbeiter_id,
                       a.servicegegenstand, a.hersteller, a.titel, a.status,
                       a.angenommen_am, a.voraussichtlich_fertig, a.abgeschlossen_am,
                       a.aktualisiert_am,
                       k.vorname AS kunde_vorname, k.nachname AS kunde_nachname,
                       m.vorname AS mitarbeiter_vorname, m.nachname AS mitarbeiter_nachname
                FROM auftrag a
                JOIN kunde k            ON a.kunde_id = k.kunde_id
                LEFT JOIN mitarbeiter m ON a.mitarbeiter_id = m.mitarbeiter_id
                $where
                ORDER BY a.aktualisiert_am DESC, a.auftrag_id DESC
                LIMIT $perPage OFFSET $offset";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        Response::json([
            'data'       => $stmt->fetchAll(),
            'total'      => $total,
            'page'       => $page,
            'perPage'    => $perPage,
            'totalPages' => $totalPages,
        ], 200);
    }

    /** GET /auftraege/{id} – ein Auftrag mit komplettem Statusverlauf. */
    public function show(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            Response::error('Ungültige ID', 400);
            return;
        }

        $pdo = Database::getConnection();
        $auftrag = $this->findById($pdo, $id);
        if ($auftrag === false) {
            Response::error('Auftrag nicht gefunden', 404);
            return;
        }

        // Statusverlauf dazu laden (älteste zuerst), inkl. Name der ändernden Person.
        $histStmt = $pdo->prepare(
            'SELECT h.historie_id, h.status, h.geaendert_am, h.bemerkung,
                    m.vorname AS mitarbeiter_vorname, m.nachname AS mitarbeiter_nachname
             FROM auftrag_status_historie h
             LEFT JOIN mitarbeiter m ON h.geaendert_von = m.mitarbeiter_id
             WHERE h.auftrag_id = :id
             ORDER BY h.geaendert_am ASC, h.historie_id ASC'
        );
        $histStmt->execute([':id' => $id]);
        $auftrag['historie'] = $histStmt->fetchAll();

        Response::json($auftrag, 200);
    }

    /** POST /auftraege – legt einen neuen Auftrag an (Status startet bei ANGENOMMEN). */
    public function create(): void
    {
        $data = $this->readAndValidate();
        if ($data === null) {
            return; // Fehlerantwort wurde bereits gesendet
        }

        $pdo = Database::getConnection();
        $user = Auth::user(); // angemeldete Person (für geaendert_von in der Historie)

        // In EINER Transaktion: Auftrag anlegen + erste Verlaufszeile schreiben.
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO auftrag
                   (kunde_id, mitarbeiter_id, servicegegenstand, hersteller, titel,
                    problembeschreibung, diagnose, status, voraussichtlich_fertig)
                 VALUES
                   (:kunde_id, :mitarbeiter_id, :gegenstand, :hersteller, :titel,
                    :problem, :diagnose, :status, :vorauss)'
            );
            $stmt->execute([
                ':kunde_id'       => $data['kunde_id'],
                ':mitarbeiter_id' => $data['mitarbeiter_id'],
                ':gegenstand'     => $data['servicegegenstand'],
                ':hersteller'     => $data['hersteller'],
                ':titel'          => $data['titel'],
                ':problem'        => $data['problembeschreibung'],
                ':diagnose'       => $data['diagnose'],
                ':status'         => 'ANGENOMMEN',
                ':vorauss'        => $data['voraussichtlich_fertig'],
            ]);
            $newId = (int) $pdo->lastInsertId();

            $this->insertHistorie($pdo, $newId, 'ANGENOMMEN', $user, 'Auftrag angenommen.');

            $pdo->commit();
        } catch (\PDOException $e) {
            $pdo->rollBack();
            // 23000 = verletzter Fremdschlüssel (kunde_id/mitarbeiter_id existiert nicht).
            if ($e->getCode() === '23000') {
                Response::error('Ungültige Kund:in oder Mitarbeiter:in.', 422);
                return;
            }
            throw $e;
        }

        Response::json($this->findById($pdo, $newId), 201);
    }

    /** PUT /auftraege/{id} – bearbeitet die Stammfelder eines Auftrags (NICHT den Status). */
    public function update(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            Response::error('Ungültige ID', 400);
            return;
        }

        $pdo = Database::getConnection();
        if ($this->findById($pdo, $id) === false) {
            Response::error('Auftrag nicht gefunden', 404);
            return;
        }

        $data = $this->readAndValidate();
        if ($data === null) {
            return;
        }

        $stmt = $pdo->prepare(
            'UPDATE auftrag
                SET kunde_id = :kunde_id, mitarbeiter_id = :mitarbeiter_id,
                    servicegegenstand = :gegenstand, hersteller = :hersteller,
                    titel = :titel, problembeschreibung = :problem,
                    diagnose = :diagnose, voraussichtlich_fertig = :vorauss
              WHERE auftrag_id = :id'
        );
        try {
            $stmt->execute([
                ':kunde_id'       => $data['kunde_id'],
                ':mitarbeiter_id' => $data['mitarbeiter_id'],
                ':gegenstand'     => $data['servicegegenstand'],
                ':hersteller'     => $data['hersteller'],
                ':titel'          => $data['titel'],
                ':problem'        => $data['problembeschreibung'],
                ':diagnose'       => $data['diagnose'],
                ':vorauss'        => $data['voraussichtlich_fertig'],
                ':id'             => $id,
            ]);
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') {
                Response::error('Ungültige Kund:in oder Mitarbeiter:in.', 422);
                return;
            }
            throw $e;
        }

        Response::json($this->findById($pdo, $id), 200);
    }

    /**
     * PUT /auftraege/{id}/status – ändert den Status.
     * Schreibt in EINER Transaktion: auftrag.status UND eine Verlaufszeile.
     */
    public function updateStatus(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            Response::error('Ungültige ID', 400);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) {
            Response::error('Ungültiger oder leerer JSON-Body', 400);
            return;
        }

        $status    = trim((string) ($input['status'] ?? ''));
        $bemerkung = trim((string) ($input['bemerkung'] ?? ''));

        if (!in_array($status, self::STATUS, true)) {
            Response::error('Validierung fehlgeschlagen', 422, [
                'fields' => ['status' => 'Unbekannter Status.'],
            ]);
            return;
        }

        $pdo = Database::getConnection();
        if ($this->findById($pdo, $id) === false) {
            Response::error('Auftrag nicht gefunden', 404);
            return;
        }

        $user = Auth::user();

        $pdo->beginTransaction();
        try {
            // abgeschlossen_am setzen, sobald der Auftrag (erstmals) FERTIG/ABGEHOLT ist;
            // bei Rückstufung auf einen früheren Status wieder leeren.
            $abgeschlossen = in_array($status, ['FERTIG', 'ABGEHOLT'], true);
            $stmt = $pdo->prepare(
                'UPDATE auftrag
                    SET status = :status,
                        abgeschlossen_am = CASE WHEN :abg = 1 THEN COALESCE(abgeschlossen_am, NOW())
                                                ELSE NULL END
                  WHERE auftrag_id = :id'
            );
            $stmt->execute([
                ':status' => $status,
                ':abg'    => $abgeschlossen ? 1 : 0,
                ':id'     => $id,
            ]);

            $this->insertHistorie($pdo, $id, $status, $user, $bemerkung !== '' ? $bemerkung : null);

            $pdo->commit();
        } catch (\PDOException $e) {
            $pdo->rollBack();
            throw $e;
        }

        // Den aktualisierten Auftrag inkl. Verlauf zurückgeben.
        $this->show(['id' => (string) $id]);
    }

    /** DELETE /auftraege/{id} – löscht einen Auftrag (Statusverlauf via CASCADE mit). */
    public function delete(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            Response::error('Ungültige ID', 400);
            return;
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('DELETE FROM auftrag WHERE auftrag_id = :id');
        $stmt->execute([':id' => $id]);

        if ($stmt->rowCount() === 0) {
            Response::error('Auftrag nicht gefunden', 404);
            return;
        }
        http_response_code(204); // erfolgreich gelöscht, kein Inhalt
    }

    // ------------------------------------------------------------------------
    // Hilfsmethoden (privat)
    // ------------------------------------------------------------------------

    /**
     * Liest + validiert den JSON-Body für create()/update().
     * Rückgabe: sauberes Datenarray oder null (Fehlerantwort wurde gesendet).
     */
    private function readAndValidate(): ?array
    {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) {
            Response::error('Ungültiger oder leerer JSON-Body', 400);
            return null;
        }

        $data = [
            'kunde_id'               => (int) ($input['kunde_id'] ?? 0),
            'mitarbeiter_id'         => (int) ($input['mitarbeiter_id'] ?? 0),
            'servicegegenstand'      => trim((string) ($input['servicegegenstand'] ?? '')),
            'hersteller'             => trim((string) ($input['hersteller'] ?? '')),
            'titel'                  => trim((string) ($input['titel'] ?? '')),
            'problembeschreibung'    => trim((string) ($input['problembeschreibung'] ?? '')),
            'diagnose'               => trim((string) ($input['diagnose'] ?? '')),
            'voraussichtlich_fertig' => trim((string) ($input['voraussichtlich_fertig'] ?? '')),
        ];

        $errors = [];
        if ($data['kunde_id'] <= 0) {
            $errors['kunde_id'] = 'Bitte eine Kund:in auswählen.';
        }
        if ($data['servicegegenstand'] === '') {
            $errors['servicegegenstand'] = 'Der Gegenstand ist erforderlich.';
        } elseif (mb_strlen($data['servicegegenstand']) > 150) {
            $errors['servicegegenstand'] = 'Der Gegenstand ist zu lang (max. 150 Zeichen).';
        }
        if ($data['problembeschreibung'] === '') {
            $errors['problembeschreibung'] = 'Die Problembeschreibung ist erforderlich.';
        }
        if ($data['hersteller'] !== '' && mb_strlen($data['hersteller']) > 100) {
            $errors['hersteller'] = 'Der Hersteller ist zu lang (max. 100 Zeichen).';
        }
        if ($data['titel'] !== '' && mb_strlen($data['titel']) > 150) {
            $errors['titel'] = 'Der Titel ist zu lang (max. 150 Zeichen).';
        }
        // Datum (optional) muss, wenn gesetzt, das Format JJJJ-MM-TT haben.
        if ($data['voraussichtlich_fertig'] !== '' && !$this->isValidDate($data['voraussichtlich_fertig'])) {
            $errors['voraussichtlich_fertig'] = 'Bitte ein gültiges Datum angeben.';
        }

        if ($errors) {
            Response::error('Validierung fehlgeschlagen', 422, ['fields' => $errors]);
            return null;
        }

        // Leere optionale Felder zu NULL machen (saubere DB-Werte).
        $data['mitarbeiter_id']         = $data['mitarbeiter_id'] > 0 ? $data['mitarbeiter_id'] : null;
        $data['hersteller']             = $data['hersteller'] !== '' ? $data['hersteller'] : null;
        $data['titel']                  = $data['titel'] !== '' ? $data['titel'] : null;
        $data['diagnose']               = $data['diagnose'] !== '' ? $data['diagnose'] : null;
        $data['voraussichtlich_fertig'] = $data['voraussichtlich_fertig'] !== '' ? $data['voraussichtlich_fertig'] : null;

        return $data;
    }

    /** Prüft ein Datum im Format JJJJ-MM-TT auf Gültigkeit. */
    private function isValidDate(string $value): bool
    {
        $d = \DateTime::createFromFormat('Y-m-d', $value);
        return $d !== false && $d->format('Y-m-d') === $value;
    }

    /** Schreibt eine Zeile in den Statusverlauf (gemeinsam genutzt). */
    private function insertHistorie(\PDO $pdo, int $auftragId, string $status, ?array $user, ?string $bemerkung): void
    {
        $stmt = $pdo->prepare(
            'INSERT INTO auftrag_status_historie (auftrag_id, status, geaendert_von, bemerkung)
             VALUES (:auftrag_id, :status, :geaendert_von, :bemerkung)'
        );
        $stmt->execute([
            ':auftrag_id'    => $auftragId,
            ':status'        => $status,
            ':geaendert_von' => $user['mitarbeiter_id'] ?? null,
            ':bemerkung'     => $bemerkung,
        ]);
    }

    /** Liest einen Auftrag inkl. Kund:in-/Bearbeiter:in-Namen; Array oder false. */
    private function findById(\PDO $pdo, int $id)
    {
        $stmt = $pdo->prepare(
            'SELECT a.auftrag_id, a.kunde_id, a.mitarbeiter_id,
                    a.servicegegenstand, a.hersteller, a.titel,
                    a.problembeschreibung, a.diagnose, a.status,
                    a.angenommen_am, a.voraussichtlich_fertig, a.abgeschlossen_am,
                    a.erstellt_am, a.aktualisiert_am,
                    k.vorname AS kunde_vorname, k.nachname AS kunde_nachname,
                    m.vorname AS mitarbeiter_vorname, m.nachname AS mitarbeiter_nachname
             FROM auftrag a
             JOIN kunde k            ON a.kunde_id = k.kunde_id
             LEFT JOIN mitarbeiter m ON a.mitarbeiter_id = m.mitarbeiter_id
             WHERE a.auftrag_id = :id'
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }
}

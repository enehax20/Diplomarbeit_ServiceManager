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
}

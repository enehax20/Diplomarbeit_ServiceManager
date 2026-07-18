<?php

namespace App\Controllers;

use App\Auth;
use App\Database;
use App\Response;

/**
 * Endpunkt für die Startseite (Cockpit):
 *   GET /statistik – ein paar Kennzahlen auf einen Blick.
 *
 * Bewusst schlank gehalten (im Antrag als "kleine Startseite/Cockpit" vorgesehen):
 * Gesamtzahlen sowie die Kund:innen mit den meisten Aufträgen. Reine Lese-Abfragen.
 */
class StatistikController
{
    /** GET /statistik – liefert die Kennzahlen als ein JSON-Objekt. */
    public function index(): void
    {
        $pdo = Database::getConnection();

        // Gesamtzahlen: je eine einfache COUNT-Abfrage.
        $kundenGesamt     = (int) $pdo->query('SELECT COUNT(*) FROM kunde')->fetchColumn();
        $auftraegeGesamt  = (int) $pdo->query('SELECT COUNT(*) FROM auftrag')->fetchColumn();
        // "In Arbeit" = aktiv in Bearbeitung: alles vor der Fertigstellung
        // (also weder FERTIG noch ABGEHOLT).
        $auftraegeOffen   = (int) $pdo->query(
            "SELECT COUNT(*) FROM auftrag
             WHERE status IN ('ANGENOMMEN', 'IN_DIAGNOSE', 'IN_REPARATUR')"
        )->fetchColumn();

        // Persönliche Kennzahlen der angemeldeten Person (mir zugewiesene Aufträge).
        // Prepared Statement, weil hier ein Wert (die eigene ID) einfließt.
        $meId = (int) Auth::user()['mitarbeiter_id'];

        $meineStmt = $pdo->prepare('SELECT COUNT(*) FROM auftrag WHERE mitarbeiter_id = :me');
        $meineStmt->execute([':me' => $meId]);
        $meineGesamt = (int) $meineStmt->fetchColumn();

        $meineOffenStmt = $pdo->prepare(
            "SELECT COUNT(*) FROM auftrag
             WHERE mitarbeiter_id = :me
               AND status IN ('ANGENOMMEN', 'IN_DIAGNOSE', 'IN_REPARATUR')"
        );
        $meineOffenStmt->execute([':me' => $meId]);
        $meineOffen = (int) $meineOffenStmt->fetchColumn();

        // Kund:innen mit den meisten Aufträgen (Top 5). INNER JOIN: nur Kund:innen,
        // die mindestens einen Auftrag haben.
        $topKunden = $pdo->query(
            'SELECT k.kunde_id, k.vorname, k.nachname, COUNT(a.auftrag_id) AS anzahl
             FROM kunde k
             JOIN auftrag a ON a.kunde_id = k.kunde_id
             GROUP BY k.kunde_id, k.vorname, k.nachname
             ORDER BY anzahl DESC, k.nachname, k.vorname
             LIMIT 5'
        )->fetchAll();

        // COUNT kommt als String aus der DB -> für saubere JSON-Zahlen zu int wandeln.
        foreach ($topKunden as &$k) {
            $k['anzahl'] = (int) $k['anzahl'];
        }
        unset($k);

        Response::json([
            'kundenGesamt'    => $kundenGesamt,
            'auftraegeGesamt' => $auftraegeGesamt,
            'auftraegeOffen'  => $auftraegeOffen,
            'meineGesamt'     => $meineGesamt,
            'meineOffen'      => $meineOffen,
            'topKunden'       => $topKunden,
        ], 200);
    }
}

<?php
/**
 * seed_demo_data.php – befüllt die Datenbank mit realistischen DEMO-Daten.
 *
 * WAS ES TUT
 *   - legt mehrere Mitarbeiter:innen an (mit gehashtem Passwort, einem bekannten
 *     Demo-Passwort, damit man sich testweise als verschiedene Personen anmelden
 *     kann),
 *   - legt eine größere Menge Kund:innen mit plausiblen österreichischen
 *     Stammdaten an (Name, Adresse, eindeutige E-Mail + Telefonnummer),
 *   - legt einige Aufträge an und schreibt für jeden einen lückenlosen
 *     Statusverlauf (auftrag_status_historie), passend zum aktuellen Status.
 *
 * WARUM EIN PHP-SKRIPT (statt Mockaroo o. Ä.)
 *   Reproduzierbar, ohne externen Dienst, und in der Diplomprüfung Zeile für
 *   Zeile selbst erklärbar. Es nutzt durchgehend Prepared Statements (PDO) –
 *   dieselbe Technik wie das eigentliche Backend.
 *
 * AUFRUF (im Backend-Container, der PHP + pdo_mysql hat):
 *   docker compose run --rm -v "${PWD}/database/scripts:/scripts" \
 *     backend php /scripts/seed_demo_data.php [anzahlKunden] [anzahlAuftraege]
 *
 *   Standard: 250 Kund:innen, 60 Aufträge. Beispiel mit eigenen Zahlen:
 *     ... seed_demo_data.php 300 80
 *
 * SICHERHEIT GEGEN DOPPELTES EINSPIELEN
 *   E-Mail und Telefon sind UNIQUE. Das Skript erzeugt eindeutige Werte je Lauf
 *   und überspringt einzelne Datensätze, falls doch eine Kollision auftritt
 *   (z. B. beim zweiten Lauf). Bereits vorhandene Mitarbeiter (gleicher
 *   Benutzername) werden übersprungen, nicht doppelt angelegt.
 */

declare(strict_types=1);

// --- Parameter ---------------------------------------------------------------
$anzahlKunden    = isset($argv[1]) ? max(0, (int) $argv[1]) : 250;
$anzahlAuftraege = isset($argv[2]) ? max(0, (int) $argv[2]) : 60;

// --- DB-Verbindung (Env mit Dev-Standardwerten, wie in create_admin.php) ------
$dbHost = getenv('DB_HOST') ?: 'db';
$dbPort = getenv('DB_PORT') ?: '3306';
$dbName = getenv('DB_NAME') ?: 'servicemanager';
$dbUser = getenv('DB_USER') ?: 'servicemanager';
$dbPass = getenv('DB_PASSWORD') ?: 'servicemanager_pw';

$dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $dbHost, $dbPort, $dbName);

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    fwrite(STDERR, "Datenbankfehler: " . $e->getMessage() . "\n");
    exit(1);
}

// ============================================================================
//  Datengrundlage (kleine, fest verdrahtete Listen für plausible Zufallsdaten)
// ============================================================================

$vornamen = [
    'Anna', 'Lukas', 'Sophie', 'Maximilian', 'Lena', 'Felix', 'Marie', 'Paul',
    'Laura', 'David', 'Julia', 'Tobias', 'Sarah', 'Florian', 'Lisa', 'Stefan',
    'Katharina', 'Michael', 'Theresa', 'Daniel', 'Johanna', 'Andreas', 'Nina',
    'Christoph', 'Eva', 'Markus', 'Carina', 'Patrick', 'Melanie', 'Simon',
    'Sabine', 'Thomas', 'Verena', 'Martin', 'Elisabeth', 'Bernhard', 'Petra',
    'Georg', 'Claudia', 'Wolfgang',
];

$nachnamen = [
    'Gruber', 'Huber', 'Bauer', 'Wagner', 'Müller', 'Pichler', 'Steiner',
    'Moser', 'Mayer', 'Hofer', 'Leitner', 'Berger', 'Fuchs', 'Eder', 'Fischer',
    'Schmid', 'Winkler', 'Weber', 'Schwarz', 'Maier', 'Schneider', 'Reiter',
    'Mayr', 'Schmidt', 'Wimmer', 'Egger', 'Brunner', 'Lang', 'Baumgartner',
    'Auer', 'Binder', 'Lechner', 'Wallner', 'Aigner', 'Ebner', 'Köck',
    'Haas', 'Wieser', 'Holzer', 'Höller',
];

// Orte mit passender Postleitzahl (Österreich).
$orte = [
    ['Wien', '1010'], ['Graz', '8010'], ['Linz', '4020'], ['Salzburg', '5020'],
    ['Innsbruck', '6020'], ['Klagenfurt', '9020'], ['Villach', '9500'],
    ['Wels', '4600'], ['St. Pölten', '3100'], ['Dornbirn', '6850'],
    ['Wiener Neustadt', '2700'], ['Steyr', '4400'], ['Feldkirch', '6800'],
    ['Bregenz', '6900'], ['Leonding', '4060'], ['Klosterneuburg', '3400'],
    ['Baden', '2500'], ['Wolfsberg', '9400'], ['Leoben', '8700'], ['Krems', '3500'],
];

$strassen = [
    'Hauptstraße', 'Bahnhofstraße', 'Kirchengasse', 'Schulgasse', 'Mozartstraße',
    'Lindenweg', 'Gartenstraße', 'Ringstraße', 'Feldgasse', 'Bergstraße',
    'Wiener Straße', 'Grazer Straße', 'Mühlgasse', 'Parkstraße', 'Dorfstraße',
];

// Servicegegenstände (generisch) je Branche: [Gegenstand, Hersteller, [Probleme]].
$gegenstaende = [
    ['VW Golf VII', 'Volkswagen', ['Motor springt nicht an', 'Bremsen quietschen', 'Klimaanlage kühlt nicht']],
    ['BMW 3er', 'BMW', ['Warnleuchte Motor', 'Service fällig', 'Anlasser defekt']],
    ['Audi A4', 'Audi', ['Ölverlust', 'Auspuff undicht', 'Batterie leer']],
    ['Opel Astra', 'Opel', ['Kupplung rutscht', 'Scheinwerfer ausgefallen']],
    ['Škoda Octavia', 'Škoda', ['Reifen abgefahren', 'Heckscheibenheizung defekt']],
    ['iPhone 12', 'Apple', ['Display gebrochen', 'Akku hält nicht', 'Lädt nicht']],
    ['Galaxy S21', 'Samsung', ['Display gebrochen', 'Lautsprecher defekt', 'Kamera unscharf']],
    ['ThinkPad T14', 'Lenovo', ['Startet nicht', 'Lüfter sehr laut', 'Tastatur defekt']],
    ['Surface Pro', 'Microsoft', ['Akku tiefentladen', 'Display flackert']],
    ['Waschmaschine', 'Bosch', ['Schleudert nicht', 'Wasser läuft aus', 'Heizt nicht']],
    ['Geschirrspüler', 'Siemens', ['Pumpt nicht ab', 'Tür schließt nicht']],
    ['Kühlschrank', 'Liebherr', ['Kühlt nicht', 'Macht laute Geräusche']],
    ['Kaffeevollautomat', 'Jura', ['Brüht nicht', 'Mahlwerk blockiert']],
    ['Staubsauger', 'Miele', ['Saugt schwach', 'Motor überhitzt']],
];

$titelVorlagen = ['Reparatur', 'Diagnose', 'Wartung', 'Kostenvoranschlag', 'Garantiefall'];

// Mitarbeiter:innen (Demo). Ein bekanntes Passwort, damit man sich anmelden kann.
$demoMitarbeiter = [
    ['Anna',  'Berger',  'a.berger',  'mitarbeiter'],
    ['Felix', 'Steiner', 'f.steiner', 'mitarbeiter'],
    ['Marie', 'Hofer',   'm.hofer',   'mitarbeiter'],
    ['David', 'Leitner', 'd.leitner', 'mitarbeiter'],
    ['Julia', 'Moser',   'j.moser',   'mitarbeiter'],
    ['Stefan', 'Wagner', 's.wagner',  'admin'],
];
$demoPasswort = 'demo1234'; // mind. 8 Zeichen (siehe Backend-Validierung)

// ============================================================================
//  Kleine Hilfsfunktionen
// ============================================================================

/** Liefert ein zufälliges Element eines Arrays. */
function pick(array $arr)
{
    return $arr[random_int(0, count($arr) - 1)];
}

/** Wandelt Umlaute/Sonderzeichen für E-Mail-Adressen in ASCII um. */
function asciiSlug(string $s): string
{
    $map = ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss',
            'Ä' => 'ae', 'Ö' => 'oe', 'Ü' => 'ue', 'š' => 's', 'Š' => 's'];
    $s = strtr($s, $map);
    $s = strtolower($s);
    return preg_replace('/[^a-z0-9]/', '', $s);
}

/** DATETIME-String für "jetzt minus $tage Tage, plus $minuten Minuten". */
function zeitpunkt(int $tageZurueck, int $minutenDazu = 0): string
{
    $ts = time() - $tageZurueck * 86400 + $minutenDazu * 60;
    return date('Y-m-d H:i:s', $ts);
}

// ============================================================================
//  1) Mitarbeiter:innen anlegen
// ============================================================================

$mitarbeiterIds = [];   // gesammelte IDs (für die Auftrags-Zuweisung)
$mitarbeiterNeu = 0;

$findMa   = $pdo->prepare('SELECT mitarbeiter_id FROM mitarbeiter WHERE benutzername = :b LIMIT 1');
$insertMa = $pdo->prepare(
    'INSERT INTO mitarbeiter (vorname, nachname, email, benutzername, passwort_hash, rolle, aktiv)
     VALUES (:vorname, :nachname, :email, :benutzername, :hash, :rolle, 1)'
);

$hash = password_hash($demoPasswort, PASSWORD_DEFAULT);

foreach ($demoMitarbeiter as [$vorname, $nachname, $benutzername, $rolle]) {
    // Schon vorhanden? -> nicht doppelt anlegen, aber ID für Aufträge merken.
    $findMa->execute([':b' => $benutzername]);
    $vorhandene = $findMa->fetchColumn();
    if ($vorhandene !== false) {
        $mitarbeiterIds[] = (int) $vorhandene;
        continue;
    }
    $insertMa->execute([
        ':vorname'      => $vorname,
        ':nachname'     => $nachname,
        ':email'        => $benutzername . '@werkstatt.example.at',
        ':benutzername' => $benutzername,
        ':hash'         => $hash,
        ':rolle'        => $rolle,
    ]);
    $mitarbeiterIds[] = (int) $pdo->lastInsertId();
    $mitarbeiterNeu++;
}

// Falls die Tabelle vorher schon Mitarbeiter enthielt (z. B. den Admin),
// diese ebenfalls als mögliche Bearbeiter:innen einsammeln.
$alleMa = $pdo->query('SELECT mitarbeiter_id FROM mitarbeiter')->fetchAll(PDO::FETCH_COLUMN);
$mitarbeiterIds = array_values(array_unique(array_map('intval', $alleMa)));

// ============================================================================
//  2) Kund:innen anlegen
// ============================================================================

$kundeIds   = [];
$kundenNeu  = 0;
$usedEmail  = [];   // Eindeutigkeit innerhalb dieses Laufs sicherstellen
$usedTel    = [];
// Zufällige Basis je Lauf -> zweiter Lauf kollidiert kaum mit dem ersten.
$basis = random_int(1000, 8999);

$insertKunde = $pdo->prepare(
    'INSERT INTO kunde (vorname, nachname, telefon, email, strasse, plz, ort)
     VALUES (:vorname, :nachname, :telefon, :email, :strasse, :plz, :ort)'
);

$pdo->beginTransaction();
for ($i = 0; $i < $anzahlKunden; $i++) {
    $vorname  = pick($vornamen);
    $nachname = pick($nachnamen);
    [$ort, $plz] = pick($orte);

    // Eindeutige E-Mail: vorname.nachname<nummer>@example.at
    $nummer = $basis + $i;
    $email  = asciiSlug($vorname) . '.' . asciiSlug($nachname) . $nummer . '@example.at';

    // Eindeutige Telefonnummer im österreichischen Format.
    do {
        $telefon = '+43 ' . random_int(650, 699) . ' ' . random_int(1000000, 9999999);
    } while (isset($usedTel[$telefon]));
    $usedTel[$telefon] = true;

    if (isset($usedEmail[$email])) {
        continue; // sollte praktisch nie vorkommen
    }
    $usedEmail[$email] = true;

    $strasse = pick($strassen) . ' ' . random_int(1, 150);

    try {
        $insertKunde->execute([
            ':vorname'  => $vorname,
            ':nachname' => $nachname,
            ':telefon'  => $telefon,
            ':email'    => $email,
            ':strasse'  => $strasse,
            ':plz'      => $plz,
            ':ort'      => $ort,
        ]);
        $kundeIds[] = (int) $pdo->lastInsertId();
        $kundenNeu++;
    } catch (PDOException $e) {
        if (($e->errorInfo[1] ?? null) === 1062) {
            continue; // Kollision (z. B. erneuter Lauf) -> diesen Datensatz überspringen
        }
        $pdo->rollBack();
        fwrite(STDERR, "Fehler beim Anlegen einer Kund:in: " . $e->getMessage() . "\n");
        exit(1);
    }
}
$pdo->commit();

// Auch bereits vorhandene Kund:innen für die Auftrags-Zuweisung einsammeln.
$alleKunden = $pdo->query('SELECT kunde_id FROM kunde')->fetchAll(PDO::FETCH_COLUMN);
$kundeIds = array_values(array_map('intval', $alleKunden));

// ============================================================================
//  3) Aufträge + Statusverlauf anlegen
// ============================================================================

$statusReihe = ['ANGENOMMEN', 'IN_DIAGNOSE', 'IN_REPARATUR', 'FERTIG', 'ABGEHOLT'];
$auftraegeNeu = 0;

$insertAuftrag = $pdo->prepare(
    'INSERT INTO auftrag
       (kunde_id, mitarbeiter_id, servicegegenstand, hersteller, titel,
        problembeschreibung, diagnose, status, angenommen_am, voraussichtlich_fertig,
        abgeschlossen_am, erstellt_am)
     VALUES
       (:kunde_id, :mitarbeiter_id, :gegenstand, :hersteller, :titel,
        :problem, :diagnose, :status, :angenommen_am, :vorauss,
        :abgeschlossen_am, :erstellt_am)'
);
$insertHist = $pdo->prepare(
    'INSERT INTO auftrag_status_historie (auftrag_id, status, geaendert_von, geaendert_am, bemerkung)
     VALUES (:auftrag_id, :status, :geaendert_von, :geaendert_am, :bemerkung)'
);

if ($anzahlAuftraege > 0 && $kundeIds) {
    $pdo->beginTransaction();
    for ($i = 0; $i < $anzahlAuftraege; $i++) {
        [$gegenstand, $hersteller, $probleme] = pick($gegenstaende);
        $problem = pick($probleme);
        $titel   = pick($titelVorlagen) . ' ' . $gegenstand;

        // Auftragsalter: zwischen 1 und 90 Tagen.
        $alterTage     = random_int(1, 90);
        $angenommenAm  = zeitpunkt($alterTage);
        $voraussFertig = date('Y-m-d', time() - $alterTage * 86400 + random_int(3, 14) * 86400);

        // Aktueller Status: gewichtet (mehr offene als ganz abgeschlossene).
        $endIndex = pick([0, 0, 1, 1, 2, 2, 2, 3, 3, 4]);
        $status   = $statusReihe[$endIndex];

        // Bearbeiter:in: meist zugewiesen, gelegentlich noch offen (NULL).
        $mitarbeiterId = ($mitarbeiterIds && random_int(1, 10) > 2)
            ? pick($mitarbeiterIds) : null;

        // Diagnose-Text erst ab "IN_DIAGNOSE" sinnvoll.
        $diagnose = $endIndex >= 1 ? 'Fehler eingegrenzt, Reparatur eingeleitet.' : null;

        // abgeschlossen_am setzen, sobald der Auftrag mindestens FERTIG ist.
        $abgeschlossenAm = null;
        if ($endIndex >= 3) {
            // grob: FERTIG einige Tage nach Annahme
            $abgeschlossenAm = zeitpunkt(max(0, $alterTage - random_int(2, 5)));
        }

        $insertAuftrag->execute([
            ':kunde_id'         => pick($kundeIds),
            ':mitarbeiter_id'   => $mitarbeiterId,
            ':gegenstand'       => $gegenstand,
            ':hersteller'       => $hersteller,
            ':titel'            => $titel,
            ':problem'          => $problem,
            ':diagnose'         => $diagnose,
            ':status'           => $status,
            ':angenommen_am'    => $angenommenAm,
            ':vorauss'          => $voraussFertig,
            ':abgeschlossen_am' => $abgeschlossenAm,
            ':erstellt_am'      => $angenommenAm,
        ]);
        $auftragId = (int) $pdo->lastInsertId();

        // Lückenlosen Statusverlauf schreiben: jeden Schritt von ANGENOMMEN bis
        // zum aktuellen Status, mit aufsteigenden Zeitstempeln.
        for ($s = 0; $s <= $endIndex; $s++) {
            $insertHist->execute([
                ':auftrag_id'    => $auftragId,
                ':status'        => $statusReihe[$s],
                ':geaendert_von' => $mitarbeiterId,
                ':geaendert_am'  => zeitpunkt($alterTage, $s * 60), // je Schritt +1h
                ':bemerkung'     => $s === 0 ? 'Auftrag angenommen.' : null,
            ]);
        }
        $auftraegeNeu++;
    }
    $pdo->commit();
}

// ============================================================================
//  Zusammenfassung
// ============================================================================
echo "\n";
echo "Demo-Daten eingespielt.\n";
echo "------------------------------------------------------------\n";
echo "  Neue Mitarbeiter:innen : {$mitarbeiterNeu}\n";
echo "  Neue Kund:innen        : {$kundenNeu}\n";
echo "  Neue Aufträge          : {$auftraegeNeu}\n";
echo "------------------------------------------------------------\n";
if ($mitarbeiterNeu > 0) {
    echo "Anmeldung der Demo-Mitarbeiter (alle mit demselben Passwort):\n";
    echo "  Passwort: {$demoPasswort}\n";
    echo "  Benutzernamen: ";
    echo implode(', ', array_map(fn($m) => $m[2], $demoMitarbeiter)) . "\n";
    echo "------------------------------------------------------------\n";
}
echo "Hinweis: E-Mail und Telefon sind eindeutig; bei erneutem Lauf werden\n";
echo "kollidierende Datensätze übersprungen.\n\n";
exit(0);

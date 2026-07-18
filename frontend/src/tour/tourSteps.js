// Schritte für den geführten Rundgang ("Tour"), je Seite (nach Pfad).
//
// Jeder Schritt hat:
//   - selector: CSS-Selektor des Elements, das hervorgehoben wird
//               (wir markieren Elemente mit data-tour="..."). Fehlt das Element,
//               zeigt die Tour den Text mittig ohne Spotlight (Null-Schutz).
//   - titel:    Überschrift im Erklär-Kästchen
//   - text:     kurze deutsche Beschreibung
//
// Diese Datei enthält bewusst NUR Daten (keine React-Komponente), damit sie
// einfach bleibt und keine Fast-Refresh-Warnung auslöst.
export const tourSteps = {
  // --- Startseite / Cockpit ---------------------------------------------------
  "/": [
    {
      selector: '[data-tour="nav"]',
      titel: "Navigation",
      text: "Hier wechseln Sie zwischen Start, Kund:innen, Aufträgen und – als Admin – Mitarbeiter:innen.",
    },
    {
      selector: '[data-tour="home-stats"]',
      titel: "Kennzahlen",
      text: "Ihre wichtigsten Zahlen auf einen Blick. Jede Kachel ist anklickbar und öffnet die passende Liste.",
    },
    {
      selector: '[data-tour="home-meine"]',
      titel: "Meine Aufträge",
      text: "Diese Kachel zeigt Ihre eigenen offenen Aufträge und führt direkt zu Ihrer gefilterten Liste.",
    },
    {
      selector: '[data-tour="home-top"]',
      titel: "Top-Kund:innen",
      text: "Die Kund:innen mit den meisten Aufträgen – nützlich, um Stammkund:innen zu erkennen.",
    },
    {
      selector: '[data-tour="help"]',
      titel: "Diesen Rundgang wiederholen",
      text: "Über das Fragezeichen starten Sie den Rundgang jederzeit neu – passend zur jeweiligen Seite.",
    },
  ],

  // --- Kund:innen -------------------------------------------------------------
  "/kunden": [
    {
      selector: '[data-tour="kunden-form"]',
      titel: "Kund:in anlegen",
      text: "Hier legen Sie neue Kund:innen an oder bearbeiten bestehende. Mit * markierte Felder sind Pflicht.",
    },
    {
      selector: '[data-tour="kunden-search"]',
      titel: "Suchen",
      text: "Durchsuchen Sie die Kund:innen nach Name oder E-Mail. Die Liste filtert sich sofort.",
    },
    {
      selector: '[data-tour="kunden-table"]',
      titel: "Kundenliste",
      text: "Alle Kund:innen der aktuellen Seite. Über die Buttons rechts bearbeiten oder löschen Sie einen Eintrag.",
    },
    {
      selector: '[data-tour="kunden-pagination"]',
      titel: "Blättern",
      text: "Bei vielen Kund:innen blättern Sie hier seitenweise. Mit „Zurück“ und „Weiter“ wechseln Sie die Seite; dazwischen sehen Sie, auf welcher Seite Sie gerade sind.",
    },
  ],

  // --- Aufträge ---------------------------------------------------------------
  "/auftraege": [
    {
      selector: '[data-tour="auftrag-kundensuche"]',
      titel: "Kund:in finden",
      text: "Tippen Sie einen Namen, um die Auswahl darunter zu filtern – so finden Sie auch bei vielen Kund:innen schnell die richtige.",
    },
    {
      selector: '[data-tour="auftrag-datum"]',
      titel: "Voraussichtlich fertig",
      text: "Das geplante Fertigstellungsdatum. Vergangene Tage sind gesperrt.",
    },
    {
      selector: '[data-tour="auftrag-scope"]',
      titel: "Nur meine Aufträge",
      text: "Standardmäßig sehen Sie Ihre eigenen Aufträge. Haken entfernen, um alle Aufträge anzuzeigen.",
    },
    {
      selector: '[data-tour="auftrag-search"]',
      titel: "Suchen",
      text: "Suche über Gegenstand, Hersteller sowie Kund:in- und Bearbeiter:in-Name.",
    },
    {
      selector: '[data-tour="auftrag-table"]',
      titel: "Auftragsliste",
      text: "In jeder Zeile ändern Sie den Status direkt (wird im Verlauf protokolliert) und öffnen über die Buttons Verlauf, Bearbeitung oder das Löschen.",
    },
  ],

  // --- Mitarbeiter:innen (nur Admin) ------------------------------------------
  "/mitarbeiter": [
    {
      selector: '[data-tour="mitarbeiter-form"]',
      titel: "Mitarbeiter:in anlegen",
      text: "Hier legen Sie Konten an oder bearbeiten sie. Nur Admins haben Zugriff auf diese Seite.",
    },
    {
      selector: '[data-tour="mitarbeiter-rolle"]',
      titel: "Rolle",
      text: "Die Rolle bestimmt die Berechtigungen: „admin“ darf Mitarbeiter:innen verwalten, „mitarbeiter“ nicht.",
    },
    {
      selector: '[data-tour="mitarbeiter-passwort"]',
      titel: "Passwort",
      text: "Beim Anlegen ein Passwort setzen (mind. 8 Zeichen). Beim Bearbeiten leer lassen = Passwort bleibt unverändert.",
    },
    {
      selector: '[data-tour="mitarbeiter-table"]',
      titel: "Übersicht",
      text: "Alle Mitarbeiter:innen mit Rolle und Status. Das eigene Konto lässt sich nicht löschen.",
    },
  ],
};

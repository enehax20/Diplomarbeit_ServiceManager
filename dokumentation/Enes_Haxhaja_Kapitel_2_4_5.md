# Dokumentation – Enes Haxhaja (ServiceManager)

> Entwurf für die Kapitel **2 (Zieldefinition)**, **4 (Umsetzung / Projektverlauf)**
> und **5 (Ergebnisse und Evaluierung)**.
> Die Kapitelnummern (hier 2 / 4 / 5) bitte an die endgültige Position im
> Gesamtdokument anpassen (im Beispiel z. B. 2.3, 4.3, 5.3).
> Sprachniveau bewusst einfach gehalten (ca. B2), damit auch Nicht-Techniker:innen
> nachvollziehen können, was die einzelnen Funktionen tun.

---

## 2 Zieldefinition: Enes Haxhaja

Das Teilprojekt **„ServiceManager"** ist eine Webanwendung zur Verwaltung von
Reparaturaufträgen für einen Reparaturbetrieb. Das System ist bewusst
**branchenübergreifend** gedacht: Es soll für KFZ-Werkstätten, Elektronik- und
Haushaltsgeräte­reparaturen gleichermaßen funktionieren. Der zu reparierende
Gegenstand wird daher nicht branchenspezifisch, sondern **allgemein als Textfeld**
erfasst. Ziel ist eine schlanke, verständliche und im Betrieb tatsächlich
einsetzbare Lösung, keine überladene Speziallösung.

Die folgenden Ziele wurden zu Projektbeginn festgelegt. „Muss-Ziele" (M) sind
verpflichtend, „Kann-Ziele" (K) sind sinnvolle Erweiterungen, „Nicht-Ziele" (N)
grenzen das Projekt bewusst ab.

- **Ziel-M-1: Datenbankdesign und -implementierung**
  Ziel: Entwurf und Aufbau eines sauberen, relationalen Datenbankschemas als
  Grundlage des gesamten Systems.
  Erklärung: Es wird festgelegt, welche Tabellen es gibt, welche Felder sie
  enthalten und wie sie miteinander verknüpft sind. Die Daten werden normalisiert,
  damit keine Informationen doppelt gespeichert werden und nichts widersprüchlich
  werden kann.
  Nicht-Ziel: keine branchenspezifischen Zusatztabellen.

- **Ziel-M-2: Backend als REST-API**
  Ziel: Entwicklung einer Programmschnittstelle (API) in PHP, die als einzige
  Verbindung zwischen Datenbank und Benutzeroberfläche dient.
  Erklärung: Die API nimmt Anfragen entgegen (z. B. „gib mir alle Kund:innen"),
  liest oder schreibt in der Datenbank und antwortet in einem einheitlichen Format
  (JSON). Der Zugriff auf die Datenbank erfolgt ausschließlich über abgesicherte
  Abfragen (Prepared Statements), um Angriffe zu verhindern.

- **Ziel-M-3: Stammdatenverwaltung (Kund:innen und Mitarbeiter:innen)**
  Ziel: Anlegen, Anzeigen, Bearbeiten und Löschen (CRUD) von Kund:innen und
  Mitarbeiter:innen.
  Erklärung: „Stammdaten" sind die Grunddaten, die sich selten ändern – etwa Name,
  Telefonnummer und Adresse einer Kund:in. Diese müssen zuverlässig verwaltet
  werden können, weil jeder Auftrag auf ihnen aufbaut.

- **Ziel-M-4: Auftragsverwaltung mit nachvollziehbarem Statusverlauf**
  Ziel: Reparaturaufträge erstellen und bearbeiten sowie ihren Status ändern,
  wobei jede Änderung lückenlos protokolliert wird.
  Erklärung: Ein Auftrag durchläuft mehrere Stufen (angenommen, in Reparatur,
  fertig …). Bei jedem Statuswechsel wird festgehalten, wann und von wem er
  erfolgt ist. So kann man später jederzeit nachvollziehen, was mit einem Auftrag
  passiert ist.

- **Ziel-M-5: Anmeldung mit zwei Rollen und Eingabevalidierung**
  Ziel: Eine Login-Funktion, die nur berechtigten Mitarbeiter:innen Zugang gibt,
  mit zwei Rollen (Administrator und Mitarbeiter:in), sowie eine Prüfung aller
  Eingaben.
  Erklärung: Ohne Anmeldung darf niemand Daten sehen oder ändern. Die Rolle
  entscheidet, wer zusätzlich Mitarbeiter:innen verwalten darf. Die Validierung
  stellt sicher, dass z. B. eine E-Mail-Adresse ein gültiges Format hat, bevor sie
  gespeichert wird.

- **Ziel-K-1: Serverseitige Pagination und Suche**
  Ziel: Große Datenmengen seitenweise laden und durchsuchbar machen.
  Erklärung: Bei mehreren hundert Kund:innen wäre es unpraktisch und langsam, immer
  alle auf einmal anzuzeigen. Stattdessen wird immer nur eine „Seite" (z. B. 20
  Einträge) vom Server geholt, ergänzt durch ein Suchfeld.

- **Ziel-K-2: Startseite mit Kennzahlen (Cockpit)**
  Ziel: Eine Übersichtsseite mit einfachen Auswertungen (z. B. Anzahl der
  Kund:innen, Kund:innen mit den meisten Aufträgen).
  Erklärung: Eine solche Startseite gibt dem Betrieb auf einen Blick einen
  Überblick über die wichtigsten Zahlen.

- **Ziel-N-1: Bewusste Abgrenzung (Nicht-Ziele)**
  Folgende Punkte gehören ausdrücklich **nicht** zum Projektumfang:
  branchenspezifische dynamische Felder (EAV), Rechnungs- und Zahlungsabwicklung,
  Lager- bzw. Ersatzteilverwaltung, Benachrichtigungen per E-Mail/SMS, ein
  Kund:innen-Portal sowie eine mobile App. Diese Einschränkung hält das Projekt
  klar, überschaubar und in der vorgegebenen Zeit umsetzbar.

---

## 4 Umsetzung – Enes Haxhaja

Die Entwicklung des ServiceManagers baut auf einer klar getrennten Architektur
aus drei Schichten auf: einer **Datenbank** (MySQL) für die dauerhafte Speicherung,
einem **Backend** (PHP-REST-API) für die gesamte Verarbeitungslogik und einem
**Frontend** (React) als Benutzeroberfläche. Diese Trennung sorgt dafür, dass jede
Schicht genau eine Aufgabe hat und unabhängig getestet und geändert werden kann.
Alle Datenzugriffe laufen über die API; die Benutzeroberfläche spricht niemals
direkt mit der Datenbank. In den folgenden Abschnitten werden zuerst die
verwendeten Technologien erklärt und danach die konkrete Umsetzung beschrieben.

### 4.1 Theoretischer Hintergrund

Für die Umsetzung kamen mehrere aufeinander abgestimmte Werkzeuge zum Einsatz. Sie
bilden die technische Grundlage für Datenbank, Backend und Frontend.

- **Docker**
  Docker führt Programme in abgeschlossenen „Containern" aus. Dadurch laufen die
  Datenbank, das Backend und die Verwaltungsoberfläche phpMyAdmin bei allen
  Teammitgliedern exakt gleich, ohne dass PHP oder MySQL direkt auf dem Rechner
  installiert werden müssen. Ein einziger Befehl startet die gesamte Umgebung.

- **MySQL**
  MySQL ist ein weit verbreitetes, relationales Datenbanksystem. „Relational"
  bedeutet, dass Daten in Tabellen abgelegt und über Schlüssel miteinander
  verknüpft werden. MySQL speichert alle zentralen Daten des Systems: Kund:innen,
  Mitarbeiter:innen, Aufträge und den Statusverlauf.

- **PHP mit PDO und Prepared Statements**
  PHP ist die Programmiersprache des Backends. Der Datenbankzugriff erfolgt über
  PDO mit sogenannten Prepared Statements: Der Befehl (das SQL) und die Werte
  werden getrennt an die Datenbank geschickt. Das verhindert SQL-Injection – einen
  Angriff, bei dem über ein Eingabefeld schädliche Datenbankbefehle eingeschleust
  werden könnten.

- **REST-API**
  Eine REST-API ist eine standardisierte Schnittstelle für die Kommunikation
  zwischen zwei Programmen über das HTTP-Protokoll. Angefragt wird über feste
  Adressen (z. B. `/kunden`) und Methoden (GET zum Lesen, POST zum Anlegen, PUT zum
  Ändern, DELETE zum Löschen). Die Antwort kommt strukturiert im JSON-Format.

- **React und Vite**
  React ist eine JavaScript-Bibliothek zum Bau von Benutzeroberflächen aus
  wiederverwendbaren Komponenten. Vite ist das Entwicklungswerkzeug, das die
  Anwendung baut und beim Programmieren sofort im Browser aktualisiert. Über den
  React Router werden die verschiedenen Seiten (Kund:innen, Aufträge …) verwaltet.

- **phpMyAdmin**
  phpMyAdmin ist eine Weboberfläche zur Verwaltung der MySQL-Datenbank. Sie diente
  zur Kontrolle der Daten und zum einmaligen Import der Testdaten.

- **Mockaroo**
  Mockaroo ist ein Online-Werkzeug, das realistische Testdaten erzeugt. Damit
  wurden über 200 Kund:innen generiert und über phpMyAdmin importiert. Die
  Testdaten sind bewusst **nicht** Teil des Programmcodes, sondern werden von außen
  erzeugt und in die Datenbank eingespielt.

### 4.2 Datenbank

#### 4.2.1 Datenbankdesign

Das Modell ist bewusst schlank gehalten und besteht aus **vier Tabellen**:

| Tabelle | Zweck |
|---|---|
| `mitarbeiter` | Bearbeitende Person **und** Login-Benutzer. Enthält Name, Benutzername, Passwort-Hash, Rolle (`admin`/`mitarbeiter`) und ein Aktiv-Kennzeichen. |
| `kunde` | Auftraggeber:in mit Name sowie optionaler Telefonnummer, E-Mail und Adresse. |
| `auftrag` | Der eigentliche Reparaturauftrag: verweist auf Kund:in und (optional) bearbeitende Person, enthält den generischen Gegenstand, Problembeschreibung, Diagnose, Status und Datumsangaben. |
| `auftrag_status_historie` | Lückenloser Verlauf: eine Zeile pro Statuswechsel (welcher Status, wann, von wem, mit optionaler Bemerkung). |

Jede Tabelle besitzt einen künstlichen Primärschlüssel (`*_id`), der jeden
Datensatz eindeutig identifiziert. Die Beziehungen werden über Fremdschlüssel
erzwungen; die Datenbank wacht dadurch selbst über die Konsistenz. Konkret gilt:

- Eine Kund:in bzw. Mitarbeiter:in, an der noch Aufträge hängen, kann **nicht**
  einfach gelöscht werden (Regel *RESTRICT*). Das verhindert „verwaiste" Aufträge.
- Wird ein Auftrag gelöscht, verschwindet automatisch auch sein Statusverlauf
  (Regel *CASCADE*), da dieser ohne den Auftrag keinen Sinn ergibt.

Der Status eines Auftrags ist als **ENUM** (feste Auswahlliste) modelliert und
kennt fünf Werte in fester Reihenfolge:
`ANGENOMMEN → IN_DIAGNOSE → IN_REPARATUR → FERTIG → ABGEHOLT`.
Da diese Werte fachlich fest vorgegeben sind und von Benutzer:innen nie geändert
werden, ist eine Auswahlliste die einfachste korrekte Lösung – eine eigene
Tabelle dafür wäre unnötig.

### 4.3 Backend (REST-API)

#### 4.3.1 Aufbau

Das Backend hat **einen einzigen Einstiegspunkt** (`index.php`, den sogenannten
Front-Controller). Jede Anfrage läuft dort durch und wird von einem kleinen
**Router** an die passende Stelle weitergeleitet. Die eigentliche Logik steckt in
**Controllern** – je eine Klasse für Kund:innen, Mitarbeiter:innen und Aufträge.
Zwei Hilfsklassen unterstützen dabei: eine für den Datenbankaufbau und eine für
einheitliche JSON-Antworten. Diese klare Aufteilung macht den Code übersichtlich
und leicht erweiterbar: Eine neue Funktion bedeutet meist nur eine neue Zeile im
Router und eine neue Methode im Controller.

Alle Datenbankabfragen verwenden Prepared Statements. Vereinfacht gesagt: Die
eingegebenen Werte werden getrennt vom Befehl an die Datenbank übergeben, sodass
Eingaben niemals als Befehl ausgeführt werden können.

#### 4.3.2 Anmeldung und Sicherheit

Die Anmeldung ist bewusst einfach und gut erklärbar gehalten. Nach erfolgreichem
Login merkt sich der Server die angemeldete Person in einer **Session** (der
Browser erhält dazu ein Cookie). Passwörter werden **niemals im Klartext**
gespeichert, sondern nur als sicherer Hash (über die PHP-Funktionen
`password_hash` und `password_verify`).

Geschützte Endpunkte prüfen vor jeder Aktion die Anmeldung. Es gibt zwei Rollen:
Alle angemeldeten Personen dürfen Kund:innen und Aufträge verwalten; nur
Administrator:innen dürfen zusätzlich Mitarbeiter:innen anlegen oder deaktivieren.
Diese Prüfung übernimmt eine zentrale Hilfsfunktion, sodass jeder Endpunkt sie mit
einer einzigen Zeile absichern kann.

#### 4.3.3 Stammdatenverwaltung

Für Kund:innen stehen die vollständigen CRUD-Funktionen bereit: auflisten
(`GET /kunden`), anlegen (`POST /kunden`), bearbeiten (`PUT /kunden/{id}`) und
löschen (`DELETE /kunden/{id}`). Vor dem Speichern werden alle Eingaben geprüft:
Vor- und Nachname sind Pflicht, Felder dürfen nicht zu lang sein, und eine
angegebene E-Mail-Adresse muss ein gültiges Format haben. Zusätzlich sind E-Mail
und Telefonnummer eindeutig – dieselbe Adresse kann also nicht zweimal vergeben
werden. Diese Eindeutigkeit wird sowohl im Backend geprüft als auch von der
Datenbank selbst abgesichert.

Weil ein Betrieb schnell mehrere hundert Kund:innen hat, liefert die Liste die
Daten **seitenweise** (Pagination): Es werden nur die Einträge der aktuellen Seite
geladen, dazu die Gesamtzahl und die Anzahl der Seiten. Über einen optionalen
Suchbegriff kann zusätzlich nach Name oder E-Mail gefiltert werden. So bleibt die
Anwendung auch bei vielen Daten schnell.

Die Verwaltung der Mitarbeiter:innen funktioniert nach demselben Muster, ist aber
den Administrator:innen vorbehalten. Damit auch normale Mitarbeiter:innen beim
Anlegen eines Auftrags eine bearbeitende Person auswählen können, gibt es zwei
schlanke Zusatz-Endpunkte (`/kunden/auswahl` und `/mitarbeiter/auswahl`), die nur
ID und Name zurückgeben – gerade genug, um ein Auswahlfeld zu füllen.

#### 4.3.4 Auftragsverwaltung und Statusverlauf

Das Herzstück des Systems ist die Auftragsverwaltung. Ein Auftrag kann angelegt
(`POST /auftraege`) und bearbeitet (`PUT /auftraege/{id}`) werden; die Liste ist
ebenfalls seitenweise abrufbar und nach Gegenstand, Hersteller, Titel sowie nach
Kund:in- und Bearbeiter:in-Name durchsuchbar. Der Einzelabruf
(`GET /auftraege/{id}`) liefert zusätzlich den vollständigen Statusverlauf.

Besonders wichtig ist die **Statusänderung** (`PUT /auftraege/{id}/status`). Hier
gilt eine feste Regel des Datenmodells: Bei jedem Statuswechsel müssen zwei Dinge
gemeinsam passieren – der aktuelle Status am Auftrag wird gesetzt **und** es wird
eine neue Zeile in den Statusverlauf geschrieben. Damit diese beiden Schritte nie
auseinanderlaufen, laufen sie in **einer Transaktion**: Entweder werden beide
Änderungen gespeichert oder – bei einem Fehler – keine von beiden. So bleibt der
Verlauf immer lückenlos und stimmt jederzeit mit dem aktuellen Status überein.

Ergänzend wird beim Erreichen des Status „Fertig" bzw. „Abgeholt" automatisch der
Abschlusszeitpunkt gesetzt und beim Zurückstufen wieder entfernt. Der
Statusverlauf hält außerdem fest, **wer** die Änderung ausgelöst hat (die
angemeldete Person). Dadurch ist jeder Bearbeitungsschritt nachvollziehbar.

### 4.4 Frontend (Benutzeroberfläche)

#### 4.4.1 Aufbau

Die Oberfläche ist mit React umgesetzt. Beim Start prüft die Anwendung, ob bereits
eine gültige Anmeldung besteht. Ist niemand angemeldet, wird ausschließlich die
Login-Seite gezeigt; nach der Anmeldung erscheint die vollständige Anwendung mit
Kopfzeile, Navigation und dem Namen der angemeldeten Person samt Rolle. Alle
Serveraufrufe erfolgen mit der Einstellung, das Anmelde-Cookie mitzusenden, damit
das Backend die angemeldete Person erkennt. Der Anmeldezustand wird zentral
verwaltet, sodass jede Seite darauf zugreifen kann.

#### 4.4.2 Kund:innen-Seite

Die Kund:innen-Seite zeigt oben ein Formular zum Anlegen bzw. Bearbeiten und
darunter die Liste. Über eine Seitennavigation („Zurück"/„Weiter" mit Anzeige
„Seite X von Y") blättert man durch die Datensätze; ein Suchfeld filtert die Liste
nach Name oder E-Mail. Rückmeldungen des Servers – etwa „E-Mail-Adresse ist
ungültig" oder „Telefonnummer ist bereits vergeben" – werden verständlich
angezeigt. Nach jeder Änderung wird die aktuelle Seite neu geladen, damit
Sortierung und Seitenzahl korrekt bleiben.

#### 4.4.3 Mitarbeiter:innen-Seite

Diese Seite ist nur für Administrator:innen sichtbar. Sie erlaubt das Anlegen und
Bearbeiten von Mitarbeiter:innen inklusive Rolle und Passwort. Das eigene Konto
kann man aus Sicherheitsgründen nicht löschen, nicht deaktivieren und sich nicht
selbst die Administrator-Rolle entziehen – so kann man sich nicht versehentlich
selbst aussperren.

#### 4.4.4 Aufträge-Seite

Die Aufträge-Seite bildet den kompletten Ablauf ab. Beim Anlegen werden Kund:in
und (optional) bearbeitende Person aus Auswahllisten gewählt; dazu kommen
Gegenstand, Hersteller, Titel, Problembeschreibung und ein voraussichtliches
Fertigstellungsdatum. In der Liste lässt sich der Status jedes Auftrags direkt über
ein farbiges Auswahlfeld ändern – die Farbe macht den aktuellen Stand auf einen
Blick erkennbar. Auch hier gibt es Seitennavigation und Suche; sortiert wird nach
dem zuletzt geänderten Auftrag, sodass gerade bearbeitete Aufträge oben stehen.

Über die Schaltfläche „Verlauf" klappt zu jedem Auftrag ein Detailbereich auf. Er
zeigt die zusätzlichen Angaben (Titel, Hersteller, Annahmedatum, voraussichtliche
und tatsächliche Fertigstellung), die Problembeschreibung und die Diagnose sowie
den vollständigen **Statusverlauf** mit Zeitpunkt und bearbeitender Person. Ist ein
Auftrag abgeschlossen und wurde ein Plandatum angegeben, zeigt der Bereich
zusätzlich, ob die Reparatur früher, pünktlich oder später als geplant fertig
wurde (in Tagen).

### 4.5 Testdaten

Für einen realistischen Test wurden über **Mockaroo** mehr als 200 Kund:innen mit
plausiblen Namen, Adressen und eindeutigen Kontaktdaten erzeugt und einmalig über
phpMyAdmin in die Datenbank importiert. Mitarbeiter:innen und einige Aufträge
wurden anschließend direkt in der laufenden Anwendung angelegt. Wichtig ist die
klare Trennung: **Testdaten sind kein Bestandteil des Programmcodes.** Der Code
enthält keine fest eingebauten Datensätze, sondern arbeitet ausschließlich mit den
Daten aus der Datenbank. Das entspricht der Anforderung an eine saubere,
datengetriebene Anwendung.

---

## 5 Ergebnisse und Evaluierung – Enes Haxhaja

Im Rahmen des Teilprojekts wurde eine vollständige, funktionsfähige Webanwendung
zur Stammdaten- und Auftragsverwaltung entwickelt. Das System deckt den gesamten
Ablauf ab: von der Verwaltung der Kund:innen und Mitarbeiter:innen über das Anlegen
und Bearbeiten von Reparaturaufträgen bis hin zur lückenlosen Nachverfolgung des
Status. Anmeldung, Eingabeprüfung sowie Pagination und Suche funktionieren im
Zusammenspiel zuverlässig.

Das Ergebnis umfasst im Überblick:

- **Datenbank:** ein schlankes, normalisiertes Schema aus vier Tabellen, dessen
  Beziehungen die Datenkonsistenz selbst absichern.
- **Backend:** eine PHP-REST-API mit durchgängig abgesicherten Datenzugriffen
  (Prepared Statements), sitzungsbasierter Anmeldung und zwei Rollen.
- **Frontend:** eine übersichtliche React-Oberfläche für Kund:innen,
  Mitarbeiter:innen und Aufträge, inklusive farblicher Statusanzeige und
  aufklappbarem Statusverlauf.
- **Auftragslogik:** jeder Statuswechsel wird in einer Transaktion gemeinsam mit
  einem Verlaufseintrag gespeichert und ist damit vollständig nachvollziehbar.

### Tabellarische Darstellung der Ziele

| Ziel | Beschreibung | Status |
|---|---|---|
| Ziel-M-1 | Datenbankdesign und -implementierung | Erreicht |
| Ziel-M-2 | Backend als REST-API (PHP, PDO) | Erreicht |
| Ziel-M-3 | Stammdatenverwaltung (Kund:innen, Mitarbeiter:innen) | Erreicht |
| Ziel-M-4 | Auftragsverwaltung mit Statusverlauf | Erreicht |
| Ziel-M-5 | Anmeldung mit zwei Rollen und Validierung | Erreicht |
| Ziel-K-1 | Serverseitige Pagination und Suche | Erreicht |
| Ziel-K-2 | Startseite mit Kennzahlen (Cockpit) | Nicht erreicht |

*Tabelle: Zielliste Realisierung Enes Haxhaja*

### Kommentare

Alle Muss-Ziele wurden vollständig erreicht. Das Datenbankdesign bildet die
verlässliche Grundlage; besonders bewährt hat sich die Entscheidung, die
Konsistenz über Fremdschlüssel-Regeln direkt in der Datenbank zu erzwingen. Das
Backend trennt Anmeldung, Datenverarbeitung und Ausgabe sauber und ist durch die
durchgängigen Prepared Statements gegen SQL-Injection geschützt. Im Frontend hat
sich die serverseitige Pagination als richtig erwiesen: Auch mit über 200
Kund:innen bleibt die Anwendung schnell.

Von den Kann-Zielen wurde die Pagination samt Suche umgesetzt. Die Startseite mit
Kennzahlen (Cockpit) wurde in dieser Phase noch nicht realisiert, da der
Schwerpunkt bewusst auf den Kernfunktionen – Stammdaten, Aufträge und Statusverlauf
– lag. Sie ist als sinnvolle nächste Erweiterung vorgesehen.

### Persönliche Reflexion

Die Arbeit an diesem Projekt hat mir vor allem im Zusammenspiel von Datenbank,
Backend und Oberfläche viel gebracht. Am Anfang war die größte Herausforderung, das
richtige, möglichst einfache Datenmodell zu finden – hier zeigt sich schnell, dass
frühe Entscheidungen die gesamte spätere Umsetzung beeinflussen. Als besonders
wichtig hat sich die Regel erwiesen, Statusänderung und Verlaufseintrag in einer
Transaktion zusammenzufassen, damit der Verlauf immer verlässlich bleibt.

Rückblickend bin ich mit dem Ergebnis zufrieden: Das System läuft stabil und
erfüllt die gesetzten Muss-Ziele. Für die Zukunft plane ich, die Startseite mit
Kennzahlen zu ergänzen und die Auswertungen weiter auszubauen.

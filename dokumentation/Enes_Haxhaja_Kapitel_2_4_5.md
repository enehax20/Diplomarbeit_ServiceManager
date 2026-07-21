# Dokumentation – Enes Haxhaja (ServiceManager)

> Entwurf für die Kapitel **2 (Zieldefinition)**, **4 (Umsetzung / Projektverlauf)**
> und **5 (Ergebnisse und Evaluierung)**.
> Die Kapitelnummern (hier 2 / 4 / 5) bitte an die endgültige Position im
> Gesamtdokument anpassen (im Beispiel z. B. 2.3, 4.3, 5.3).
> Sprachniveau bewusst einfach gehalten (ca. B2), damit auch Nicht-Techniker:innen
> nachvollziehen können, was die einzelnen Funktionen tun.

---

## Kurzfassung

Das Teilprojekt **„ServiceManager"** ist eine Webanwendung zur Verwaltung von
Reparaturaufträgen in einem Reparaturbetrieb. Das System erfasst Kund:innen und
Mitarbeiter:innen, verwaltet Reparaturaufträge mit einem lückenlos
nachvollziehbaren Statusverlauf und sichert den Zugang über eine
rollenbasierte Anmeldung ab. Es ist bewusst **branchenübergreifend** gestaltet,
sodass es für KFZ-Werkstätten sowie für Elektronik- und
Haushaltsgeräte­reparaturen gleichermaßen einsetzbar ist. Technisch besteht die
Anwendung aus drei sauber getrennten Schichten: einer MySQL-Datenbank für die
Speicherung, einem PHP-Backend als REST-API für die gesamte Verarbeitungslogik
und einer React-Oberfläche als Benutzeroberfläche. Ergänzend sorgen serverseitige
Pagination mit Suche sowie eine Startseite mit Kennzahlen (Cockpit) dafür, dass
die Lösung auch bei mehreren hundert Datensätzen übersichtlich und schnell bleibt.

---

## 1 Einleitung: Enes Haxhaja

**Ausgangslage:**
In vielen Reparaturbetrieben werden Aufträge noch mit Zetteln, Excel-Listen oder
im Kopf verwaltet. Dadurch gehen Informationen leicht verloren, der aktuelle Stand
eines Auftrags ist unklar, und es lässt sich später nicht mehr nachvollziehen, wer
wann was bearbeitet hat. Gerade wenn ein Betrieb wächst und mehrere
Mitarbeiter:innen an unterschiedlichen Geräten arbeiten, wird diese Art der
Verwaltung schnell unübersichtlich und fehleranfällig.

**Nutzen:**
Durch den ServiceManager wird die Auftragsverwaltung digitalisiert und zentral an
einer Stelle gebündelt. Kund:innen, Mitarbeiter:innen und Aufträge sind jederzeit
abrufbar, und jeder Statuswechsel eines Auftrags wird automatisch protokolliert.
So ist auf einen Blick erkennbar, welche Reparatur sich in welchem Stadium
befindet und wer sie bearbeitet. Das spart Zeit, verhindert Doppelarbeit und macht
den gesamten Ablauf für den Betrieb transparenter und verlässlicher.

**Anforderungen des Auftraggebers:**
Der Auftraggeber erwartet eine schlanke, im Alltag tatsächlich einsetzbare Lösung.
Stammdaten von Kund:innen und Mitarbeiter:innen sollen zuverlässig angelegt,
bearbeitet und gelöscht werden können. Reparaturaufträge müssen sich mit einem
klaren Statusverlauf führen lassen, wobei jede Änderung nachvollziehbar bleibt.
Der Zugang soll durch eine Anmeldung mit zwei Rollen (Administrator:in und
Mitarbeiter:in) geschützt und alle Eingaben sollen geprüft werden. Bewusst **nicht**
gefordert sind branchenspezifische Speziallösungen wie Rechnungswesen,
Lagerverwaltung oder ein Kund:innen-Portal – das System soll klar, überschaubar
und in der vorgegebenen Zeit umsetzbar bleiben.

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

> **📷 Screenshot-Hinweis:** phpMyAdmin im Browser (`localhost:8080`) mit den vier
> Tabellen – oder das Terminal nach `docker compose up -d` mit den gestarteten
> Containern (Datenbank, Backend, phpMyAdmin). Illustriert die laufende
> Docker-Umgebung.

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

> **📷 Screenshot-Hinweis:** ER-Diagramm der vier Tabellen mit ihren Beziehungen
> (aus dem Datenmodell-Kapitel oder der phpMyAdmin-„Designer"-Ansicht).

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

> **📷 Screenshot-Hinweis:** Ordnerstruktur des Backends (`public/index.php`,
> `src/` mit den Controllern und Hilfsklassen) oder die JSON-Antwort der
> API-Startseite mit der Liste aller Endpunkte.

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
Anwendung auch bei vielen Daten schnell. Seite und Suchbegriff werden als
**GET-Parameter** an die Adresse angehängt (z. B. `GET /kunden?page=2&q=mann`) –
das ist die korrekte HTTP-Methode zum reinen Abfragen von Daten.

> **📷 Screenshot-Hinweis:** Beispielantwort von `GET /kunden?page=1&perPage=20`
> im Browser (oder in einem API-Werkzeug) – zeigt das einheitliche Format
> `{ data, total, page, perPage, totalPages }`.

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

Zwei Erweiterungen kamen nach Rücksprache mit dem Betreuer hinzu. Erstens lässt
sich die Auftragsliste über den Parameter `mein` auf die **eigenen Aufträge** der
angemeldeten Person einschränken (`GET /auftraege?mein=1`); das ist die
Standardansicht für Mitarbeiter:innen, sie können aber jederzeit alle Aufträge
einblenden. Zweitens prüft das Backend beim Anlegen und Bearbeiten das
**voraussichtliche Fertigstellungsdatum**: Es muss ein gültiges Datum sein und darf
nicht in der Vergangenheit liegen – ein geplanter Termin, der schon vorbei ist,
ergibt fachlich keinen Sinn.

> **📷 Screenshot-Hinweis:** Einzelabruf `GET /auftraege/{id}` als JSON – zeigt die
> Auftragsdaten samt dem Feld `historie` (der vollständige Statusverlauf).

#### 4.3.5 Kennzahlen für die Startseite

Für die Startseite (Cockpit) liefert ein eigener Endpunkt (`GET /statistik`) die
wichtigsten Zahlen gebündelt: die Gesamtzahl der Kund:innen und Aufträge, die
Anzahl der Aufträge „in Arbeit" (alles außer fertig oder abgeholt), die
persönlichen Zahlen der angemeldeten Person (eigene Aufträge gesamt und davon in
Arbeit) sowie die fünf Kund:innen mit den meisten Aufträgen. So muss die
Oberfläche nur **eine einzige Anfrage** stellen, statt viele Einzelwerte getrennt
zu berechnen.

> **📷 Screenshot-Hinweis:** JSON-Antwort von `GET /statistik` – zeigt die
> gebündelten Kennzahlen inklusive der Liste `topKunden`.

### 4.4 Frontend (Benutzeroberfläche)

#### 4.4.1 Aufbau

Die Oberfläche ist mit React umgesetzt. Beim Start prüft die Anwendung, ob bereits
eine gültige Anmeldung besteht. Ist niemand angemeldet, wird ausschließlich die
Login-Seite gezeigt; nach der Anmeldung erscheint die vollständige Anwendung mit
Kopfzeile, Navigation und dem Namen der angemeldeten Person samt Rolle. Alle
Serveraufrufe erfolgen mit der Einstellung, das Anmelde-Cookie mitzusenden, damit
das Backend die angemeldete Person erkennt. Der Anmeldezustand wird zentral
verwaltet, sodass jede Seite darauf zugreifen kann.

> **📷 Screenshot-Hinweis:** (1) Login-Seite mit Feldern für Benutzername und
> Passwort. (2) Kopfzeile nach der Anmeldung mit Logo, Navigation, Name und Rolle
> sowie Abmelde- und Hilfe-Button (?).

#### 4.4.2 Kund:innen-Seite

Die Kund:innen-Seite zeigt oben ein Formular zum Anlegen bzw. Bearbeiten und
darunter die Liste. Über eine Seitennavigation („Zurück"/„Weiter" mit Anzeige
„Seite X von Y") blättert man durch die Datensätze; ein Suchfeld filtert die Liste
nach Name oder E-Mail. Rückmeldungen des Servers – etwa „E-Mail-Adresse ist
ungültig" oder „Telefonnummer ist bereits vergeben" – werden verständlich
angezeigt. Nach jeder Änderung wird die aktuelle Seite neu geladen, damit
Sortierung und Seitenzahl korrekt bleiben.

Der aktuelle Seiten- und Suchzustand steht dabei in der **Adresszeile** des
Browsers (z. B. `…/kunden?page=2&q=mann`). Dadurch lässt sich eine bestimmte
Ansicht als Lesezeichen speichern oder weitergeben, und die Zurück-Taste des
Browsers funktioniert wie erwartet. Dieselbe Technik wird auch auf der
Aufträge-Seite verwendet.

> **📷 Screenshot-Hinweis:** Kund:innen-Seite mit dem Formular oben, der Liste, dem
> Suchfeld und der Seitennavigation. Die Adresszeile sollte einen Parameter wie
> `?page=2` zeigen.

#### 4.4.3 Mitarbeiter:innen-Seite

Diese Seite ist nur für Administrator:innen sichtbar. Sie erlaubt das Anlegen und
Bearbeiten von Mitarbeiter:innen inklusive Rolle und Passwort. Das eigene Konto
kann man aus Sicherheitsgründen nicht löschen, nicht deaktivieren und sich nicht
selbst die Administrator-Rolle entziehen – so kann man sich nicht versehentlich
selbst aussperren.

> **📷 Screenshot-Hinweis:** Mitarbeiter:innen-Seite (nur als Admin sichtbar) mit
> dem Formular (inkl. Rolle und Passwort) und der Tabelle aller Konten mit
> Rolle und Status.

#### 4.4.4 Aufträge-Seite

Die Aufträge-Seite bildet den kompletten Ablauf ab. Beim Anlegen werden Kund:in
und (optional) bearbeitende Person aus Auswahllisten gewählt; dazu kommen
Gegenstand, Hersteller, Titel, Problembeschreibung und ein voraussichtliches
Fertigstellungsdatum. In der Liste lässt sich der Status jedes Auftrags direkt über
ein farbiges Auswahlfeld ändern – die Farbe macht den aktuellen Stand auf einen
Blick erkennbar. Auch hier gibt es Seitennavigation und Suche; sortiert wird nach
dem zuletzt geänderten Auftrag, sodass gerade bearbeitete Aufträge oben stehen.

Mehrere Details erleichtern die Bedienung. Weil ein Betrieb viele Kund:innen hat,
besitzt das Auswahlfeld für die Kund:in ein eigenes **Suchfeld**: Man tippt einen
Namen und die Auswahl filtert sich sofort. Das Fertigstellungsdatum wird im
europäischen Format **TT/MM/JJJJ** angezeigt, und Tage in der Vergangenheit sind
gesperrt. Standardmäßig sehen Mitarbeiter:innen zunächst nur **ihre eigenen**
Aufträge; über den Schalter „Nur meine Aufträge" lässt sich auf alle Aufträge
umschalten.

> **📷 Screenshot-Hinweis:** (1) Auftragsformular mit der Kund:innen-Suche über dem
> Auswahlfeld und dem Datumsfeld. (2) Auftragsliste mit dem farbigen
> Status-Auswahlfeld und dem Schalter „Nur meine Aufträge".

Über die Schaltfläche „Verlauf" klappt zu jedem Auftrag ein Detailbereich auf. Er
zeigt die zusätzlichen Angaben (Titel, Hersteller, Annahmedatum, voraussichtliche
und tatsächliche Fertigstellung), die Problembeschreibung und die Diagnose sowie
den vollständigen **Statusverlauf** mit Zeitpunkt und bearbeitender Person. Ist ein
Auftrag abgeschlossen und wurde ein Plandatum angegeben, zeigt der Bereich
zusätzlich, ob die Reparatur früher, pünktlich oder später als geplant fertig
wurde (in Tagen).

> **📷 Screenshot-Hinweis:** Ein Auftrag mit aufgeklapptem „Verlauf"-Bereich – zeigt
> die Detailangaben und den farbig gekennzeichneten Statusverlauf mit Zeitpunkten
> und bearbeitender Person.

#### 4.4.5 Startseite (Cockpit)

Nach der Anmeldung – und über das Logo jederzeit wieder erreichbar – erscheint die
Startseite. Sie begrüßt die angemeldete Person und zeigt die wichtigsten Zahlen als
**anklickbare Kacheln**: die eigenen Aufträge (in Arbeit und gesamt), alle Aufträge
(in Arbeit und gesamt) sowie die Anzahl der Kund:innen. Ein Klick auf eine Kachel
führt direkt zur passenden, bereits gefilterten Liste. Darunter steht eine Tabelle
mit den Kund:innen, die die meisten Aufträge haben. Alle Werte stammen gebündelt
vom Endpunkt `GET /statistik`.

> **📷 Screenshot-Hinweis:** Startseite mit den Kennzahl-Kacheln (u. a. „Meine
> Aufträge" und „Aufträge gesamt") und der Tabelle „Kund:innen mit den meisten
> Aufträgen".

#### 4.4.6 Hilfe: geführter Rundgang

Damit sich neue Mitarbeiter:innen schnell zurechtfinden, gibt es in der Kopfzeile
jeder Seite einen **Hilfe-Button (?)**. Ein Klick startet einen geführten Rundgang:
Nacheinander wird jeweils ein Bedienelement hervorgehoben (der Rest des Bildschirms
wird abgedunkelt) und in einem kurzen Text erklärt. Mit „Weiter"/„Zurück" blättert
man durch die Schritte; „Fertig" oder die Esc-Taste beenden den Rundgang. Jede
Seite hat dabei ihre eigenen Erklärungen. Der Rundgang ist bewusst **selbst
programmiert** (ohne Zusatzbibliothek): Die Position des jeweils erklärten Elements
wird im Browser ermittelt und ein hervorhebender Rahmen darübergelegt.

> **📷 Screenshot-Hinweis:** Laufender Rundgang – ein hervorgehobenes Element mit
> abgedunkeltem Hintergrund und dem Erklär-Kästchen samt Schritt­zähler (z. B.
> „2 / 5") und „Weiter"-Button.

### 4.5 Testdaten

Für einen realistischen Test wurden über **Mockaroo** mehr als 200 Kund:innen mit
plausiblen Namen, Adressen und eindeutigen Kontaktdaten erzeugt und einmalig über
phpMyAdmin in die Datenbank importiert. Mitarbeiter:innen und einige Aufträge
wurden anschließend direkt in der laufenden Anwendung angelegt. Wichtig ist die
klare Trennung: **Testdaten sind kein Bestandteil des Programmcodes.** Der Code
enthält keine fest eingebauten Datensätze, sondern arbeitet ausschließlich mit den
Daten aus der Datenbank. Das entspricht der Anforderung an eine saubere,
datengetriebene Anwendung.

> **📷 Screenshot-Hinweis:** Mockaroo mit dem Feld-Schema für die Kund:innen oder
> der Import-Dialog in phpMyAdmin (Tab „Importieren").

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
- **Startseite (Cockpit):** eine Übersichtsseite mit anklickbaren Kennzahlen
  (eigene und gesamte Aufträge, Kund:innen) sowie den Kund:innen mit den meisten
  Aufträgen.
- **Zusätzliche Verbesserungen** nach Rücksprache mit dem Betreuer: persönliche
  Auftragsansicht, Datumsvalidierung samt Anzeige im Format TT/MM/JJJJ, Suchfeld in
  der Kund:innen-Auswahl und ein geführter Hilfe-Rundgang.

### Tabellarische Darstellung der Ziele

| Ziel | Beschreibung | Status |
|---|---|---|
| Ziel-M-1 | Datenbankdesign und -implementierung | Erreicht |
| Ziel-M-2 | Backend als REST-API (PHP, PDO) | Erreicht |
| Ziel-M-3 | Stammdatenverwaltung (Kund:innen, Mitarbeiter:innen) | Erreicht |
| Ziel-M-4 | Auftragsverwaltung mit Statusverlauf | Erreicht |
| Ziel-M-5 | Anmeldung mit zwei Rollen und Validierung | Erreicht |
| Ziel-K-1 | Serverseitige Pagination und Suche | Erreicht |
| Ziel-K-2 | Startseite mit Kennzahlen (Cockpit) | Erreicht |

*Tabelle: Zielliste Realisierung Enes Haxhaja*

### Kommentare

Alle Muss-Ziele wurden vollständig erreicht. Das Datenbankdesign bildet die
verlässliche Grundlage; besonders bewährt hat sich die Entscheidung, die
Konsistenz über Fremdschlüssel-Regeln direkt in der Datenbank zu erzwingen. Das
Backend trennt Anmeldung, Datenverarbeitung und Ausgabe sauber und ist durch die
durchgängigen Prepared Statements gegen SQL-Injection geschützt. Im Frontend hat
sich die serverseitige Pagination als richtig erwiesen: Auch mit über 200
Kund:innen bleibt die Anwendung schnell.

Von den Kann-Zielen wurden **beide** umgesetzt: die serverseitige Pagination samt
Suche sowie die Startseite mit Kennzahlen (Cockpit).

### Zusätzliche Erweiterungen

Nach der Präsentation eines Zwischenstands kamen auf Anregung des Betreuers noch
mehrere Verbesserungen hinzu:

- **Ansicht in der Adresszeile:** Seite und Suchbegriff der Listen stehen als
  GET-Parameter in der URL. So sind Ansichten teilbar bzw. als Lesezeichen
  speicherbar, und die Zurück-Taste des Browsers funktioniert wie erwartet.
- **Datumsvalidierung und -format:** Das voraussichtliche Fertigstellungsdatum muss
  gültig sein und darf nicht in der Vergangenheit liegen; angezeigt wird es
  einheitlich im Format TT/MM/JJJJ.
- **Suchfeld in der Kund:innen-Auswahl:** Beim Anlegen eines Auftrags findet man die
  richtige Kund:in auch bei mehreren hundert Einträgen schnell.
- **Persönliche Auftragsansicht:** Mitarbeiter:innen sehen standardmäßig ihre
  eigenen Aufträge, können aber jederzeit alle einblenden. Passend dazu zeigt die
  Startseite eigene und gesamte Kennzahlen.
- **Geführter Rundgang:** Ein Hilfe-Button (?) erklärt jede Seite Schritt für
  Schritt und hebt die jeweiligen Bedienelemente hervor.

### Persönliche Reflexion

Die Arbeit an diesem Projekt hat mir vor allem im Zusammenspiel von Datenbank,
Backend und Oberfläche viel gebracht. Am Anfang war die größte Herausforderung, das
richtige, möglichst einfache Datenmodell zu finden – hier zeigt sich schnell, dass
frühe Entscheidungen die gesamte spätere Umsetzung beeinflussen. Als besonders
wichtig hat sich die Regel erwiesen, Statusänderung und Verlaufseintrag in einer
Transaktion zusammenzufassen, damit der Verlauf immer verlässlich bleibt.

Rückblickend bin ich mit dem Ergebnis zufrieden: Das System läuft stabil und
erfüllt alle Muss- und Kann-Ziele. Hilfreich war außerdem das Feedback des
Betreuers, aus dem mehrere praktische Verbesserungen entstanden sind – etwa die
persönliche Auftragsansicht und der geführte Hilfe-Rundgang. Für die Zukunft könnte
man die Auswertungen auf der Startseite weiter ausbauen und die Anwendung z. B. um
Druck- oder Exportfunktionen ergänzen.

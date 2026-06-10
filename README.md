# ServiceManager

Branchenübergreifendes Auftragsverwaltungssystem für Reparaturbetriebe
(KFZ, Elektronik, Haushaltsgeräte). HTL-Diplomarbeit (Matura), Enes Haxhaja, 5As, 2025/26.

Kleine Reparaturbetriebe verwalten Aufträge oft über Zettel, Excel oder Kalender.
ServiceManager bietet stattdessen eine zentrale Web-Anwendung für eine einheitliche
**Kunden- und Auftragsverwaltung** mit nachvollziehbarem Statusverlauf, die über
mehrere Branchen hinweg funktioniert.

## Technologie

- **Frontend:** React + Vite, React Router, `fetch`
- **Backend:** PHP (REST-API, strukturiertes plain PHP, PDO + Prepared Statements)
- **Datenbank:** MySQL 8 (per Docker)

## Voraussetzungen

- [Docker Desktop](https://www.docker.com/) (für Datenbank und Backend)
- [Node.js](https://nodejs.org/) 20+ (für das Frontend)

## Starten

**1) Datenbank + Backend (Docker):**

```bash
docker compose up -d
```

- MySQL: `localhost:3307`
- Backend-API: http://localhost:8000
- phpMyAdmin: http://localhost:8080

**2) Frontend:**

```bash
cd frontend
npm install
npm run dev
```

- App: http://localhost:5173

**Stoppen:** `docker compose down` · **Datenbank zurücksetzen** (löscht alle Daten,
Init-Skripte laufen erneut): `docker compose down -v`

## Projektstruktur

```
backend/            PHP-REST-API (public/index.php = Einstiegspunkt, src/, config/)
frontend/           React-App (Vite)
database/init/      schema.sql (Struktur) + seed.sql (Konfigurationsdaten)
docker-compose.yml  MySQL 8 + phpMyAdmin + Backend
datenmodell.md      Beschreibung des Datenmodells (inkl. ER-Diagramm)
```

## Dokumentation

- `datenmodell.md` — relationales Datenmodell, Beziehungen, Normalisierung, ER-Diagramm
- `Diplomarbeitsantrag_ServiceManager.pdf` — Diplomarbeitsantrag

> Hinweis: `CLAUDE.md` (Wurzel) und `database/CLAUDE.md` enthalten Anweisungen und
> Wissen für die KI-Assistenz (Claude Code) und sind nicht Teil der Anwendung.

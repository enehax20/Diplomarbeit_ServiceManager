# CLAUDE.md — ServiceManager

> Read this first, every session. It is the behavioural contract for working on
> this project. Claude Code loads this file automatically.
>
> - Database structure for agents: `database/CLAUDE.md`
> - Full data-model prose + ERD (thesis chapter): `datenmodell.md`
> - Exact, authoritative DDL: `database/init/schema.sql`

## What this is

ServiceManager is a **cross-industry order-management web app for repair shops**
(KFZ / Elektronik / Haushaltsgeräte). It is an Austrian HTL diploma thesis
(Matura), **defended in an oral exam**, so the author must be able to **explain
every line of code himself**. Treat defensibility and clarity as first-class
requirements, not nice-to-haves.

## How to work here (agent behaviour)

- **Build, then explain.** Make a sensible choice, implement it, and afterwards
  give a short, plain-language explanation of what you did and why.
- **Explain for a beginner.** The author is a beginner. Prefer clear,
  conventional code over clever code. Never pull in a library or pattern without
  a one-sentence justification.
- **Stay strictly in scope** (see below) and **do not gold-plate.** Do not add
  features, files, scripts, or data that were not asked for.
- **Do not insert demo/test data on your own.** The database already holds the
  fixed configuration data plus exactly one Betrieb (required by a NOT NULL
  foreign key — see `database/CLAUDE.md`). If more data is needed, ask first.
- **Ask before** large architectural decisions, anything that installs software
  on the machine, or anything hard to reverse.
- **Small, reviewable steps.** Commit only when the author explicitly asks.
- **The schema is the contract.** Never rename tables/columns or restructure the
  database. Match `database/init/schema.sql` exactly.

## Tech stack (fixed — do not substitute)

- **Frontend:** React + Vite, React Router, plain `fetch`. No Next.js, no Redux.
  (TanStack Query only if clearly justified and explained.)
- **Backend:** PHP REST API — structured plain PHP, **PDO with prepared
  statements** for every query. No Laravel/Symfony.
- **Database:** MySQL 8, runs in Docker.
- **Backend runtime:** runs **in Docker** (PHP 8.3 built-in server) because PHP
  is not installed on the dev machine.
- **Auth (M6):** simple login; passwords via `password_hash` / `password_verify`.

## Language & naming

- **Code** (variables, functions, files): **English**.
- **Domain terms stay German**, matching the DB: Kunde, Auftrag,
  Servicegegenstand, Betrieb, Mitarbeiter, Branche, Status.
  e.g. `getKundeById()`, `$auftragId`, `AuftragController`.
- **Comments:** English, with German domain terms where natural.
- **UI text shown to users:** German. **Thesis documentation:** German.

## How to run (local dev)

- **Database + backend (Docker):** `docker compose up -d`
  - MySQL → `localhost:3307`
  - Backend API → `http://localhost:8000`
  - phpMyAdmin → `http://localhost:8080`
- **Frontend:** `cd frontend && npm install && npm run dev` → `http://localhost:5173`
- **Editing backend PHP needs NO rebuild.** The backend source is bind-mounted
  into the container and PHP re-reads files on every request, so saved changes
  are live on the next request. Only run `docker compose up -d --build backend`
  after changing the **Dockerfile** (e.g. adding a PHP extension).

## Project layout

- `backend/` — PHP REST API. `public/index.php` is the single front controller;
  `src/` holds classes (PSR-4-style `App\` autoload); `config/config.php` holds
  DB credentials and is **gitignored** (`config.example.php` is the template).
- `frontend/` — Vite + React app (built incrementally).
- `database/init/` — `schema.sql` (structure) and `seed.sql` (config data + one
  Betrieb). Run automatically on first DB start, alphabetically.
- `docker-compose.yml` — MySQL 8 + phpMyAdmin + the PHP backend.
- `datenmodell.md` — data-model thesis chapter; ER diagram in §9 (Mermaid).

## Scope (defined by the Antrag — stay inside it)

- **DO:** CRUD for Kunde + Servicegegenstand (with dynamic branch fields);
  create/manage Auftrag with status flow + assignments; simple login.
- **DON'T (out of scope):** invoicing/payments, inventory/parts management,
  email/SMS notifications, analytics dashboards, multi-tenant admin,
  internationalisation. Do not add these even if they seem useful.

## Settled design decisions (don't re-litigate)

- **Audience:** internal tool for **one shop's staff**. Customers are *records*,
  not users — there is no customer login/portal. Mitarbeiter log in and operate
  the app; Kunden never do.
- **Kunde identity:** `(vorname, nachname)` is intentionally **not unique** —
  two different people may share a name. Uniqueness is enforced on `kunde.email`
  and `kunde.telefon` only (DB `UNIQUE` + backend check); the real identity is
  the surrogate key `kunde_id`. Empty/NULL email or telefon may repeat.
- **Roles:** keep `mitarbeiter.rolle ENUM('admin','mitarbeiter')`, but do **not**
  build a full permission system. Everyone may manage Kunden / Servicegegenstände
  / Aufträge; only `admin` (owner/manager) may manage staff (create/deactivate
  Mitarbeiter, reset passwords) — and only once login (M6) exists. Simple login first.

## Milestones

- **M1** Concept & setup — DONE.
- **M2** Database (until 27.06) — schema done; seed config data + one Betrieb in place.
- **M3** Backend + frontend skeleton (28.06–18.07) — backend skeleton runs; frontend next.
- **M4** Stammdaten CRUD (19.07–02.08).
- **M5** Auftrag + status flow (03.08–17.08).
- **M6** Login + validation (18.08–24.08).
- **M7** Documentation (25.08–31.08).

**Build order:** get one **vertical slice** working end-to-end first — Kunde:
schema → PHP endpoint → React list + form that saves — then replicate the
pattern for the other entities.

## Current status

- Database: 10 tables created. Seeded config data: 3 Branchen, 5 Auftragsstatus,
  9 Feld-Definitionen, and 1 Betrieb (`Demo Reparatur GmbH`). No demo Kunden/Aufträge.
- Backend: skeleton runs in Docker. Endpoints `GET /kunden` and `POST /kunden`
  work (verified).
- Frontend: not built yet — the next step of the Kunde vertical slice.

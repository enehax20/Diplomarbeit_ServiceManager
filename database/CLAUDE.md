# Database — knowledge for agents (ServiceManager)

> Authoritative DDL (source of truth): `init/schema.sql`.
> Full prose explanation + ER diagram (thesis chapter): `../datenmodell.md`.
> This file is the condensed, actionable version Claude needs while coding.

## Engine & conventions

- MySQL 8, **InnoDB**, charset **utf8mb4** / `utf8mb4_unicode_ci` throughout.
- Every table has a surrogate primary key `*_id` (`INT UNSIGNED AUTO_INCREMENT`).
- `erstellt_am` columns default to `CURRENT_TIMESTAMP`.
- All relationships are enforced with foreign keys; the model is normalized (3NF).

## The model in one paragraph (Antrag-conform, deliberately small)

Per the official Diplomarbeitsantrag the system is a **slim Stammdatenverwaltung**
(Kund:innen + Mitarbeiter:innen) plus a **generic Auftragsverwaltung**. The
repaired object is captured **generically** — a plain text field on the order
(`auftrag.servicegegenstand`, e.g. "VW Golf VII", "iPhone 12") — explicitly
**without branch-specific specialisation**. There is therefore **no** Branche,
no `feld_definition`, no EAV, no `servicegegenstand` table, no `betrieb` table
(single-shop tool, the Betrieb is implicit). **Four tables only.**

## Tables (4)

| Table | Purpose / key columns |
|---|---|
| `mitarbeiter` | Employee **and** login user. `benutzername` (UK), `passwort_hash` (bcrypt), `rolle` (`admin`/`mitarbeiter`), `aktiv`. |
| `kunde` | Customer. `vorname`, `nachname`, optional `telefon`/`email` (both UK), `strasse`/`plz`/`ort`. |
| `auftrag` | The repair order (core). `kunde_id` FK, `mitarbeiter_id` FK (nullable = unassigned), `servicegegenstand` (generic text), `hersteller`, `titel`, `problembeschreibung`, `diagnose`, `status` (ENUM), dates. `auftrag_id` doubles as the eindeutige Auftragsnummer. |
| `auftrag_status_historie` | Full, traceable status log. `auftrag_id` FK, `status` (ENUM), `geaendert_von` FK (nullable), `geaendert_am`, `bemerkung`. |

## Relationships / cardinality

- `kunde` 1—n `auftrag` (the customer of an order is stored **directly** on `auftrag.kunde_id`).
- `mitarbeiter` 1—n `auftrag` (optional handler) and 1—n `auftrag_status_historie` (who changed it).
- `auftrag` 1—n `auftrag_status_historie`.

## Status workflow

`ANGENOMMEN → IN_DIAGNOSE → IN_REPARATUR → FERTIG → ABGEHOLT`

Status is an **ENUM** on `auftrag.status` (and on `auftrag_status_historie.status`).
The five values are fixed domain logic and never edited by users → an ENUM is the
simplest correct modelling (no lookup table needed).

**Rule:** on every status change, write **one transaction** that (a) updates
`auftrag.status` and (b) inserts one row into `auftrag_status_historie`. They must
never drift apart. The history table is the source of truth for the
*nachvollziehbarer Statusverlauf*.

## Delete behaviour (ON DELETE)

- RESTRICT: `auftrag→kunde`, `auftrag→mitarbeiter`. (A Kunde/Mitarbeiter with
  orders cannot be hard-deleted; catch the error and show a clear German message.)
- CASCADE: `auftrag_status_historie→auftrag` (deleting an order removes its history).
- SET NULL: `auftrag_status_historie→mitarbeiter` (`geaendert_von`).

## Practical rules / gotchas

- **`kunde.email` and `kunde.telefon` are `UNIQUE`** → no two Kunden may share a
  non-NULL email or phone (DB constraint + backend pre-check returning a 422
  field error). Empty/NULL may repeat. `(vorname, nachname)` is deliberately
  **not** unique — same-named people are different customers.
- **Mitarbeiter delete:** admins may hard-delete a Mitarbeiter. The FK
  `auftrag.mitarbeiter_id` is `ON DELETE RESTRICT`, so deletion is blocked (DB
  error 23000 → 409) once orders are assigned; in that case **deactivate**
  (`aktiv = 0`) instead. `auftrag_status_historie.geaendert_von` is `SET NULL`,
  so past status entries survive but lose the author attribution.
- Deleting a Kunde is allowed only if no Auftrag links to it; the DB blocks it
  otherwise (RESTRICT) → catch that and show a clear German message.
- Passwords go through `password_hash` / `password_verify` into
  `mitarbeiter.passwort_hash` (bcrypt). Two roles only: `admin` (may manage staff)
  and `mitarbeiter` (everyone may manage Kunden/Aufträge).

## Connection

- **From the backend (inside the Docker network):** host `db`, port `3306`.
- **From host tools (phpMyAdmin / mysql CLI):** `localhost:3307`.
- DB `servicemanager`; user `servicemanager` / `servicemanager_pw` (or `root` / `rootpass`).
- These are **dev-only** credentials. The backend reads them from the gitignored
  `backend/config/config.php`. Never commit secrets.

## Seed data & first login

- There is **no** reference/config seed anymore (no Branchen, status, Betrieb).
  `init/seed.sql` is intentionally empty (kept only so the Docker init order stays intact).
- The first login user is **not** seeded. Create an admin with a hashed password via:
  ```
  docker compose run --rm -v "${PWD}/database/scripts:/scripts" backend php /scripts/create_admin.php <benutzername>
  ```
  The script prints the cleartext password once (only the bcrypt hash is stored).
- Do not add demo/test data unless explicitly asked.

## Reset / drop

- Full reset (recommended): `docker compose down -v && docker compose up -d`
  (drops the volume → `schema.sql` + `seed.sql` re-run).
- `schema.sql` also self-drops the tables at the top, so re-running it alone resets
  the structure. A standalone `database/drop_tables.sql` exists for a manual drop
  without wiping the volume.

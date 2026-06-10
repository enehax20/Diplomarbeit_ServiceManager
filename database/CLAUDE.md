# Database — knowledge for agents (ServiceManager)

> Authoritative DDL (source of truth): `init/schema.sql` — **never change it.**
> Full prose explanation + ER diagram (thesis chapter): `../datenmodell.md`.
> This file is the condensed, actionable version Claude needs while coding.

## Engine & conventions

- MySQL 8, **InnoDB**, charset **utf8mb4** / `utf8mb4_unicode_ci` throughout.
- Every table has a surrogate primary key `*_id` (`INT UNSIGNED AUTO_INCREMENT`).
- `erstellt_am` columns default to `CURRENT_TIMESTAMP`.
- All relationships are enforced with foreign keys. The model is in **3rd normal form**.

## The core idea (thesis core — preserve it)

Different branches have different repairable objects (a car has a Kennzeichen, a
phone an IMEI, an appliance a Seriennummer). Instead of one table per branch:

- Common fields live as real columns on the generic `servicegegenstand`.
- Branch-specific fields are **configured as data**, not schema:
  - `feld_definition` says **which** extra fields a branch has (label, datatype,
    required, order).
  - `servicegegenstand_attribut` stores the **values** (EAV pattern).
- Adding a branch or a field is **data entry, no schema change**. The frontend
  renders the branch-specific part of a form **dynamically** from `feld_definition`.
- Attribute values are stored as text; `feld_definition.datentyp`
  (`text` | `zahl` | `datum` | `boolean`) drives the input widget and the
  **backend** validation/conversion (no native type check at DB level).

## Tables (10)

| Table | Purpose / key columns |
|---|---|
| `betrieb` | The repair shop (tenant). All operational data hangs off a Betrieb. |
| `branche` | Config axis. `code` (UK, e.g. `KFZ`), `bezeichnung`. |
| `auftragsstatus` | Status lookup. `code` (UK), `bezeichnung`, `sortierung`. |
| `mitarbeiter` | Employee **and** login user. `betrieb_id` FK, `benutzername` (UK), `passwort_hash`, `rolle` (`admin`/`mitarbeiter`), `aktiv`. |
| `kunde` | Customer. `betrieb_id` FK, `vorname`, `nachname`, optional `telefon`/`email`/`strasse`/`plz`/`ort`. |
| `feld_definition` | Branch field metadata. `branche_id` FK, `feld_schluessel`, `bezeichnung`, `datentyp`, `pflichtfeld`, `sortierung`, `einheit`. UK `(branche_id, feld_schluessel)`. |
| `servicegegenstand` | Generic repaired object. `kunde_id` FK, `branche_id` FK, `bezeichnung`, `hersteller`. |
| `servicegegenstand_attribut` | EAV values. `servicegegenstand_id` FK, `feld_definition_id` FK, `wert`. UK `(servicegegenstand_id, feld_definition_id)`. |
| `auftrag` | The repair order (core). `servicegegenstand_id` FK, `mitarbeiter_id` FK (nullable = unassigned), `aktueller_status_id` FK, `titel`, `problembeschreibung`, `diagnose`, dates. |
| `auftrag_status_historie` | Full status log. `auftrag_id` FK, `status_id` FK, `geaendert_von` FK (nullable), `geaendert_am`, `bemerkung`. |

## Relationships / cardinality

- `betrieb` 1—n `mitarbeiter`, `betrieb` 1—n `kunde`.
- `kunde` 1—n `servicegegenstand`.
- `branche` 1—n `feld_definition`, `branche` 1—n `servicegegenstand`.
- `servicegegenstand` 1—n `servicegegenstand_attribut` (one per applicable feld_definition).
- `servicegegenstand` 1—n `auftrag`.
- `auftrag` n—1 `mitarbeiter` (optional) and n—1 `auftragsstatus` (current status).
- `auftrag` 1—n `auftrag_status_historie`.
- **The customer of an Auftrag is reached transitively** (`auftrag → servicegegenstand → kunde`).
  It is **not** stored on `auftrag` (avoids a redundant transitive dependency). Show it in the UI.

## Status workflow

`ANGENOMMEN → IN_DIAGNOSE → IN_REPARATUR → FERTIG → ABGEHOLT`
(order via `auftragsstatus.sortierung`).

**Rule:** on every status change, write **one transaction** that (a) inserts a
row into `auftrag_status_historie` and (b) updates `auftrag.aktueller_status_id`.
They must never drift apart. The history table is the source of truth for the
*nachvollziehbarer Statusverlauf*.

## Delete behaviour (ON DELETE)

- RESTRICT: `mitarbeiter→betrieb`, `kunde→betrieb`, `servicegegenstand→kunde`,
  `servicegegenstand→branche`, `feld_definition→branche`,
  `servicegegenstand_attribut→feld_definition`, `auftrag→servicegegenstand`,
  `auftrag→mitarbeiter`, `auftrag→auftragsstatus`, `historie→status`.
- CASCADE: `servicegegenstand_attribut→servicegegenstand`, `historie→auftrag`.
- SET NULL: `auftrag_status_historie→mitarbeiter` (`geaendert_von`).

## Practical rules / gotchas

- **`kunde.betrieb_id` and `mitarbeiter.betrieb_id` are `NOT NULL`** → a Betrieb
  must exist before inserting a Kunde or Mitarbeiter. Exactly one Betrieb is
  seeded; new Kunden attach to it.
- **`kunde.email` and `kunde.telefon` are `UNIQUE`** → no two Kunden may share a
  non-NULL email or phone (DB constraint + backend pre-check returning a 422
  field error). Empty/NULL may repeat. `(vorname, nachname)` is deliberately
  **not** unique — same-named people are different customers.
- **Never hard-delete a Mitarbeiter** → set `aktiv = 0` (keeps history consistent).
- Deleting a Kunde/Servicegegenstand is allowed only if nothing links to it;
  the DB blocks it otherwise (RESTRICT) → catch that and show a clear German message.
- Passwords go through `password_hash` / `password_verify` into `mitarbeiter.passwort_hash` (bcrypt).

## Connection

- **From the backend (inside the Docker network):** host `db`, port `3306`.
- **From host tools (phpMyAdmin / mysql CLI):** `localhost:3307`.
- DB `servicemanager`; user `servicemanager` / `servicemanager_pw` (or `root` / `rootpass`).
- These are **dev-only** credentials. The backend reads them from the gitignored
  `backend/config/config.php`. Never commit secrets.

## Seed data currently present

3 Branchen (`KFZ`, `ELEKTRONIK`, `HAUSHALT`), 5 Auftragsstatus, 9 Feld-Definitionen,
and 1 Betrieb (`Demo Reparatur GmbH`). **No** demo Kunden or Aufträge.
Do not add demo/test data unless explicitly asked.

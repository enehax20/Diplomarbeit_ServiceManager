-- ============================================================================
--  ServiceManager – Datenbankschema (Struktur)
--  Branchenübergreifendes Auftragsverwaltungssystem für Reparaturbetriebe
--  MySQL 8.0 / InnoDB / utf8mb4
--
--  Dieses Skript erzeugt ausschließlich die Struktur (Tabellen, Schlüssel,
--  Beziehungen). Login-Benutzer werden über database/scripts/create_admin.php
--  angelegt; es gibt keine festen Konfigurationsdaten mehr (siehe 02_seed.sql).
--
--  Modell laut Diplomarbeitsantrag: schlanke Stammdatenverwaltung
--  (Kund:innen + Mitarbeiter:innen) und eine GENERISCHE Auftragsverwaltung.
--  Der zu reparierende Gegenstand wird bewusst nur generisch erfasst
--  (Textfeld am Auftrag) – ohne branchenspezifische Spezialisierung.
--  Vier Tabellen: mitarbeiter, kunde, auftrag, auftrag_status_historie.
-- ============================================================================

CREATE DATABASE IF NOT EXISTS servicemanager
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE servicemanager;

-- Vor dem Neuanlegen alte Strukturen entfernen, damit ein erneutes Einspielen
-- dieses Skripts (manueller Reset) ohne Fehler funktioniert. FK-Prüfung kurz
-- aus, damit die Reihenfolge der DROPs egal ist.
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS auftrag_status_historie;
DROP TABLE IF EXISTS auftrag;
DROP TABLE IF EXISTS kunde;
DROP TABLE IF EXISTS mitarbeiter;
-- Tabellen des alten, zu komplexen Modells (falls noch vorhanden) ebenfalls weg:
DROP TABLE IF EXISTS servicegegenstand_attribut;
DROP TABLE IF EXISTS servicegegenstand;
DROP TABLE IF EXISTS feld_definition;
DROP TABLE IF EXISTS auftragsstatus;
DROP TABLE IF EXISTS branche;
DROP TABLE IF EXISTS betrieb;
SET FOREIGN_KEY_CHECKS = 1;

-- ----------------------------------------------------------------------------
-- 1) MITARBEITER – bearbeitende Person UND Login-Benutzer (einfache Anmeldung).
--    Zwei Rollen: 'admin' (darf Mitarbeiter:innen verwalten) und 'mitarbeiter'.
--    Mitarbeiter:innen werden nicht gelöscht, sondern über "aktiv" deaktiviert,
--    damit historische Auftrags-Zuordnungen erhalten bleiben.
-- ----------------------------------------------------------------------------
CREATE TABLE mitarbeiter (
  mitarbeiter_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  vorname        VARCHAR(80)  NOT NULL,
  nachname       VARCHAR(80)  NOT NULL,
  email          VARCHAR(150) NULL,
  benutzername   VARCHAR(60)  NOT NULL,
  passwort_hash  VARCHAR(255) NOT NULL,   -- Ausgabe von PHP password_hash() (bcrypt)
  rolle          ENUM('admin','mitarbeiter') NOT NULL DEFAULT 'mitarbeiter',
  aktiv          TINYINT(1)   NOT NULL DEFAULT 1,
  erstellt_am    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (mitarbeiter_id),
  UNIQUE KEY uq_mitarbeiter_benutzername (benutzername)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 2) KUNDE – Auftraggeber:in (Stammdaten).
--    E-Mail und Telefon sind je eindeutig (UNIQUE), NULL bleibt erlaubt:
--    MySQL lässt mehrere NULL-Werte in einem UNIQUE-Index zu (optionale Felder).
--    (vorname, nachname) ist BEWUSST nicht eindeutig – Namensgleiche sind
--    verschiedene Kund:innen; die Identität ist der Surrogatschlüssel kunde_id.
-- ----------------------------------------------------------------------------
CREATE TABLE kunde (
  kunde_id     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  vorname      VARCHAR(80)  NOT NULL,
  nachname     VARCHAR(80)  NOT NULL,
  telefon      VARCHAR(40)  NULL,
  email        VARCHAR(150) NULL,
  strasse      VARCHAR(150) NULL,
  plz          VARCHAR(10)  NULL,
  ort          VARCHAR(100) NULL,
  erstellt_am  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (kunde_id),
  UNIQUE KEY uq_kunde_email (email),
  UNIQUE KEY uq_kunde_telefon (telefon),
  KEY idx_kunde_name (nachname, vorname)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 3) AUFTRAG – der Reparaturauftrag (fachlicher Kern).
--    Verweist DIREKT auf Kund:in und (optional) auf die bearbeitende Person.
--    Der zu reparierende Gegenstand wird generisch im Textfeld "servicegegenstand"
--    erfasst (z. B. "VW Golf VII", "iPhone 12", "Bosch Waschmaschine"); optional
--    der Hersteller. Der aktuelle Status ist als ENUM materialisiert; der
--    vollständige, nachvollziehbare Verlauf steht in auftrag_status_historie.
--    auftrag_id dient zugleich als eindeutige Auftragsnummer.
-- ----------------------------------------------------------------------------
CREATE TABLE auftrag (
  auftrag_id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  kunde_id               INT UNSIGNED NOT NULL,
  mitarbeiter_id         INT UNSIGNED NULL,        -- NULL = noch nicht zugewiesen
  servicegegenstand      VARCHAR(150) NOT NULL,    -- generischer Gegenstand (Freitext)
  hersteller             VARCHAR(100) NULL,        -- optional, z. B. Marke/Hersteller
  titel                  VARCHAR(150) NULL,
  problembeschreibung    TEXT         NOT NULL,
  diagnose               TEXT         NULL,
  status                 ENUM('ANGENOMMEN','IN_DIAGNOSE','IN_REPARATUR','FERTIG','ABGEHOLT')
                                      NOT NULL DEFAULT 'ANGENOMMEN',
  angenommen_am          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  voraussichtlich_fertig DATE         NULL,
  abgeschlossen_am       DATETIME     NULL,
  erstellt_am            DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  aktualisiert_am        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                                      ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (auftrag_id),
  KEY idx_auftrag_kunde (kunde_id),
  KEY idx_auftrag_mitarbeiter (mitarbeiter_id),
  KEY idx_auftrag_status (status),
  CONSTRAINT fk_auftrag_kunde FOREIGN KEY (kunde_id)
    REFERENCES kunde (kunde_id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_auftrag_mitarbeiter FOREIGN KEY (mitarbeiter_id)
    REFERENCES mitarbeiter (mitarbeiter_id)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 4) AUFTRAG_STATUS_HISTORIE – lückenloser, nachvollziehbarer Statusverlauf.
--    Eine Zeile pro Statuswechsel: wann, welcher Status, von wem, Bemerkung.
--    Quelle der Wahrheit für die HISTORIE; wird gemeinsam mit auftrag.status
--    in EINER Transaktion geschrieben, damit beide nie auseinanderlaufen.
-- ----------------------------------------------------------------------------
CREATE TABLE auftrag_status_historie (
  historie_id   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  auftrag_id    INT UNSIGNED NOT NULL,
  status        ENUM('ANGENOMMEN','IN_DIAGNOSE','IN_REPARATUR','FERTIG','ABGEHOLT') NOT NULL,
  geaendert_von INT UNSIGNED NULL,        -- Mitarbeiter, der den Wechsel auslöste
  geaendert_am  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  bemerkung     TEXT         NULL,
  PRIMARY KEY (historie_id),
  KEY idx_hist_auftrag (auftrag_id),
  KEY idx_hist_mitarbeiter (geaendert_von),
  CONSTRAINT fk_hist_auftrag FOREIGN KEY (auftrag_id)
    REFERENCES auftrag (auftrag_id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_hist_mitarbeiter FOREIGN KEY (geaendert_von)
    REFERENCES mitarbeiter (mitarbeiter_id)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

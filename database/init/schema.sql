-- ============================================================================
--  ServiceManager – Datenbankschema (Struktur)
--  Branchenübergreifendes Auftragsverwaltungssystem für Reparaturbetriebe
--  MySQL 8.0 / InnoDB / utf8mb4
--
--  Dieses Skript erzeugt ausschließlich die Struktur (Tabellen, Schlüssel,
--  Beziehungen). Referenz- und Testdaten siehe 02_seed.sql.
-- ============================================================================

CREATE DATABASE IF NOT EXISTS servicemanager
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE servicemanager;

SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------------------------------------------------------
-- 1) BETRIEB – der Reparaturbetrieb (Mandant). Alle operativen Daten hängen
--    an einem Betrieb, damit das System grundsätzlich mehrere Betriebe trägt.
-- ----------------------------------------------------------------------------
CREATE TABLE betrieb (
  betrieb_id   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name         VARCHAR(150) NOT NULL,
  strasse      VARCHAR(150) NULL,
  plz          VARCHAR(10)  NULL,
  ort          VARCHAR(100) NULL,
  telefon      VARCHAR(40)  NULL,
  email        VARCHAR(150) NULL,
  erstellt_am  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (betrieb_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 2) BRANCHE – die Konfigurationsachse des Systems (KFZ, Elektronik,
--    Haushaltsgeräte). Eine Branche bündelt die für sie gültigen
--    Zusatzattribute (siehe feld_definition).
-- ----------------------------------------------------------------------------
CREATE TABLE branche (
  branche_id   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  code         VARCHAR(40)  NOT NULL,   -- maschinenlesbar, z. B. KFZ
  bezeichnung  VARCHAR(100) NOT NULL,   -- Anzeigename, z. B. KFZ-Werkstatt
  PRIMARY KEY (branche_id),
  UNIQUE KEY uq_branche_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 3) AUFTRAGSSTATUS – Nachschlagetabelle für die Status-Schritte des
--    Workflows. Über "sortierung" ist die Reihenfolge ohne Code-Änderung
--    pflegbar (angenommen -> in Diagnose -> in Reparatur -> fertig -> abgeholt).
-- ----------------------------------------------------------------------------
CREATE TABLE auftragsstatus (
  status_id    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  code         VARCHAR(40)  NOT NULL,   -- z. B. IN_REPARATUR
  bezeichnung  VARCHAR(60)  NOT NULL,   -- Anzeigename, z. B. In Reparatur
  sortierung   INT          NOT NULL,   -- Reihenfolge im Ablauf
  PRIMARY KEY (status_id),
  UNIQUE KEY uq_status_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 4) MITARBEITER – bearbeitende Person UND Login-Benutzer (einfache Anmeldung).
--    Mitarbeiter:innen werden nicht gelöscht, sondern über "aktiv" deaktiviert,
--    damit historische Auftrags-Zuordnungen erhalten bleiben.
-- ----------------------------------------------------------------------------
CREATE TABLE mitarbeiter (
  mitarbeiter_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  betrieb_id     INT UNSIGNED NOT NULL,
  vorname        VARCHAR(80)  NOT NULL,
  nachname       VARCHAR(80)  NOT NULL,
  email          VARCHAR(150) NULL,
  benutzername   VARCHAR(60)  NOT NULL,
  passwort_hash  VARCHAR(255) NOT NULL,   -- Ausgabe von PHP password_hash() (bcrypt)
  rolle          ENUM('admin','mitarbeiter') NOT NULL DEFAULT 'mitarbeiter',
  aktiv          TINYINT(1)   NOT NULL DEFAULT 1,
  erstellt_am    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (mitarbeiter_id),
  UNIQUE KEY uq_mitarbeiter_benutzername (benutzername),
  KEY idx_mitarbeiter_betrieb (betrieb_id),
  CONSTRAINT fk_mitarbeiter_betrieb FOREIGN KEY (betrieb_id)
    REFERENCES betrieb (betrieb_id)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 5) KUNDE – Auftraggeber:in. Gehört genau zu einem Betrieb.
-- ----------------------------------------------------------------------------
CREATE TABLE kunde (
  kunde_id     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  betrieb_id   INT UNSIGNED NOT NULL,
  vorname      VARCHAR(80)  NOT NULL,
  nachname     VARCHAR(80)  NOT NULL,
  telefon      VARCHAR(40)  NULL,
  email        VARCHAR(150) NULL,
  strasse      VARCHAR(150) NULL,
  plz          VARCHAR(10)  NULL,
  ort          VARCHAR(100) NULL,
  erstellt_am  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (kunde_id),
  -- E-Mail und Telefon müssen je Kund:in eindeutig sein. NULL bleibt erlaubt,
  -- da MySQL mehrere NULL-Werte in einem UNIQUE-Index zulässt (optionale Felder).
  UNIQUE KEY uq_kunde_email (email),
  UNIQUE KEY uq_kunde_telefon (telefon),
  KEY idx_kunde_betrieb (betrieb_id),
  KEY idx_kunde_name (nachname, vorname),
  CONSTRAINT fk_kunde_betrieb FOREIGN KEY (betrieb_id)
    REFERENCES betrieb (betrieb_id)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 6) FELD_DEFINITION – Metadaten: welche Zusatzfelder eine Branche besitzt.
--    HIER liegt die Branchen-Konfigurierbarkeit. Eine neue Branche oder ein
--    neues Feld ist reine Dateneingabe – keine Schemaänderung.
--      datentyp: steuert Eingabefeld (Frontend) und Validierung (Backend)
--      pflichtfeld: Pflicht ja/nein
--      sortierung: Reihenfolge in der Eingabemaske
-- ----------------------------------------------------------------------------
CREATE TABLE feld_definition (
  feld_definition_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  branche_id      INT UNSIGNED NOT NULL,
  feld_schluessel VARCHAR(60)  NOT NULL,   -- maschinenlesbar, z. B. kennzeichen
  bezeichnung     VARCHAR(100) NOT NULL,   -- Anzeigename, z. B. Kennzeichen
  datentyp        ENUM('text','zahl','datum','boolean') NOT NULL DEFAULT 'text',
  pflichtfeld     TINYINT(1)   NOT NULL DEFAULT 0,
  sortierung      INT          NOT NULL DEFAULT 0,
  einheit         VARCHAR(20)  NULL,       -- optional, z. B. km
  PRIMARY KEY (feld_definition_id),
  UNIQUE KEY uq_feld_branche_schluessel (branche_id, feld_schluessel),
  KEY idx_feld_branche (branche_id),
  CONSTRAINT fk_feld_branche FOREIGN KEY (branche_id)
    REFERENCES branche (branche_id)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 7) SERVICEGEGENSTAND – der generische zu reparierende Gegenstand.
--    Gemeinsame Felder (für ALLE Branchen sinnvoll) stehen als echte Spalten;
--    branchenspezifische Felder liegen in servicegegenstand_attribut.
-- ----------------------------------------------------------------------------
CREATE TABLE servicegegenstand (
  servicegegenstand_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  kunde_id     INT UNSIGNED NOT NULL,
  branche_id   INT UNSIGNED NOT NULL,
  bezeichnung  VARCHAR(150) NOT NULL,   -- kurzer Anzeigename, z. B. VW Golf VII
  hersteller   VARCHAR(100) NULL,       -- gemeinsames Feld (Marke/Hersteller)
  erstellt_am  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (servicegegenstand_id),
  KEY idx_sg_kunde (kunde_id),
  KEY idx_sg_branche (branche_id),
  CONSTRAINT fk_sg_kunde FOREIGN KEY (kunde_id)
    REFERENCES kunde (kunde_id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_sg_branche FOREIGN KEY (branche_id)
    REFERENCES branche (branche_id)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 8) SERVICEGEGENSTAND_ATTRIBUT – die branchenspezifischen Werte (EAV).
--    Verbindet einen Servicegegenstand mit einer Feld-Definition + Wert.
--    Wird der Gegenstand gelöscht, verschwinden seine Attribute mit (CASCADE).
-- ----------------------------------------------------------------------------
CREATE TABLE servicegegenstand_attribut (
  sg_attribut_id       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  servicegegenstand_id INT UNSIGNED NOT NULL,
  feld_definition_id   INT UNSIGNED NOT NULL,
  wert                 VARCHAR(255) NULL,
  PRIMARY KEY (sg_attribut_id),
  UNIQUE KEY uq_sg_feld (servicegegenstand_id, feld_definition_id),
  KEY idx_sga_feld (feld_definition_id),
  CONSTRAINT fk_sga_sg FOREIGN KEY (servicegegenstand_id)
    REFERENCES servicegegenstand (servicegegenstand_id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_sga_feld FOREIGN KEY (feld_definition_id)
    REFERENCES feld_definition (feld_definition_id)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 9) AUFTRAG – der Reparaturauftrag (fachlicher Kern).
--    Zuordnung zu Servicegegenstand (-> Kund:in transitiv) und bearbeitender
--    Person. "aktueller_status_id" ist der materialisierte aktuelle Status;
--    der vollständige Verlauf steht in auftrag_status_historie.
-- ----------------------------------------------------------------------------
CREATE TABLE auftrag (
  auftrag_id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  servicegegenstand_id   INT UNSIGNED NOT NULL,
  mitarbeiter_id         INT UNSIGNED NULL,        -- NULL = noch nicht zugewiesen
  aktueller_status_id    INT UNSIGNED NOT NULL,
  titel                  VARCHAR(150) NULL,
  problembeschreibung    TEXT         NOT NULL,
  diagnose               TEXT         NULL,
  angenommen_am          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  voraussichtlich_fertig DATE         NULL,
  abgeschlossen_am       DATETIME     NULL,
  erstellt_am            DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  aktualisiert_am        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                                      ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (auftrag_id),
  KEY idx_auftrag_sg (servicegegenstand_id),
  KEY idx_auftrag_mitarbeiter (mitarbeiter_id),
  KEY idx_auftrag_status (aktueller_status_id),
  CONSTRAINT fk_auftrag_sg FOREIGN KEY (servicegegenstand_id)
    REFERENCES servicegegenstand (servicegegenstand_id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_auftrag_mitarbeiter FOREIGN KEY (mitarbeiter_id)
    REFERENCES mitarbeiter (mitarbeiter_id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_auftrag_status FOREIGN KEY (aktueller_status_id)
    REFERENCES auftragsstatus (status_id)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 10) AUFTRAG_STATUS_HISTORIE – lückenloser, nachvollziehbarer Statusverlauf.
--     Eine Zeile pro Statuswechsel: wann, welcher Status, von wem, Bemerkung.
--     Quelle der Wahrheit für die HISTORIE; wird gemeinsam mit
--     auftrag.aktueller_status_id in einer Transaktion geschrieben.
-- ----------------------------------------------------------------------------
CREATE TABLE auftrag_status_historie (
  historie_id   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  auftrag_id    INT UNSIGNED NOT NULL,
  status_id     INT UNSIGNED NOT NULL,
  geaendert_von INT UNSIGNED NULL,        -- Mitarbeiter, der den Wechsel auslöste
  geaendert_am  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  bemerkung     TEXT         NULL,
  PRIMARY KEY (historie_id),
  KEY idx_hist_auftrag (auftrag_id),
  KEY idx_hist_status (status_id),
  KEY idx_hist_mitarbeiter (geaendert_von),
  CONSTRAINT fk_hist_auftrag FOREIGN KEY (auftrag_id)
    REFERENCES auftrag (auftrag_id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_hist_status FOREIGN KEY (status_id)
    REFERENCES auftragsstatus (status_id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_hist_mitarbeiter FOREIGN KEY (geaendert_von)
    REFERENCES mitarbeiter (mitarbeiter_id)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

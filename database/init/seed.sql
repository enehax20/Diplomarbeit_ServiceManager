-- ============================================================================
--  ServiceManager – Referenz- und Startdaten (Seed)
--  Läuft NUR beim ersten Start (leeres Volume) – alphabetisch NACH schema.sql.
--  Enthält die stabilen Konfigurationsdaten (Branchen, Status, Feld-
--  Definitionen) sowie EINEN Demo-Betrieb, an dem die Stammdaten hängen.
--  Transaktionale Testdaten (Kund:innen, Aufträge ...) folgen in M2.
--  Idempotent gehalten, damit erneutes Einspielen keine Duplikate erzeugt.
-- ============================================================================

USE servicemanager;

-- 1) Branchen (Konfigurationsachse: KFZ, Elektronik, Haushaltsgeräte) ---------
INSERT IGNORE INTO branche (code, bezeichnung) VALUES
  ('KFZ',        'KFZ-Werkstatt'),
  ('ELEKTRONIK', 'Elektronik / Handy'),
  ('HAUSHALT',   'Haushaltsgeräte');

-- 2) Auftragsstatus (Workflow-Schritte, Reihenfolge über sortierung) ----------
INSERT IGNORE INTO auftragsstatus (code, bezeichnung, sortierung) VALUES
  ('ANGENOMMEN',   'Angenommen',   10),
  ('IN_DIAGNOSE',  'In Diagnose',  20),
  ('IN_REPARATUR', 'In Reparatur', 30),
  ('FERTIG',       'Fertig',       40),
  ('ABGEHOLT',     'Abgeholt',     50);

-- 3) Feld-Definitionen je Branche (die branchenspezifischen Zusatzfelder) -----
--    branche_id wird über den eindeutigen code aufgelöst.
INSERT IGNORE INTO feld_definition
  (branche_id, feld_schluessel, bezeichnung, datentyp, pflichtfeld, sortierung, einheit) VALUES
  ((SELECT branche_id FROM branche WHERE code='KFZ'),        'kennzeichen',       'Kennzeichen',       'text', 1, 10, NULL),
  ((SELECT branche_id FROM branche WHERE code='KFZ'),        'kilometerstand',    'Kilometerstand',    'zahl', 0, 20, 'km'),
  ((SELECT branche_id FROM branche WHERE code='KFZ'),        'fahrgestellnummer', 'Fahrgestellnummer', 'text', 0, 30, NULL),
  ((SELECT branche_id FROM branche WHERE code='ELEKTRONIK'), 'imei',              'IMEI',              'text', 1, 10, NULL),
  ((SELECT branche_id FROM branche WHERE code='ELEKTRONIK'), 'modell',            'Modell',            'text', 1, 20, NULL),
  ((SELECT branche_id FROM branche WHERE code='ELEKTRONIK'), 'farbe',             'Farbe',             'text', 0, 30, NULL),
  ((SELECT branche_id FROM branche WHERE code='HAUSHALT'),   'geraetetyp',        'Gerätetyp',         'text', 1, 10, NULL),
  ((SELECT branche_id FROM branche WHERE code='HAUSHALT'),   'seriennummer',      'Seriennummer',      'text', 1, 20, NULL),
  ((SELECT branche_id FROM branche WHERE code='HAUSHALT'),   'baujahr',           'Baujahr',           'zahl', 0, 30, NULL);

-- 4) Demo-Betrieb: genau einer. Die Stammdaten (Kund:innen, Mitarbeiter:innen)
--    hängen an einem Betrieb. Nur anlegen, wenn noch keiner existiert.
INSERT INTO betrieb (name, strasse, plz, ort, telefon, email)
SELECT 'Demo Reparatur GmbH', 'Hauptstraße 1', '1010', 'Wien', '+43 1 1234567', 'office@demo-reparatur.at'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM betrieb);

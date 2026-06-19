-- ============================================================================
--  ServiceManager – Referenz- und Startdaten (Seed)
--  Läuft NUR beim ersten Start (leeres Volume) – alphabetisch NACH schema.sql.
--
--  Das vereinfachte Modell (Diplomarbeitsantrag) kennt KEINE festen
--  Konfigurationsdaten mehr:
--    - Branchen / Feld-Definitionen: entfallen (Gegenstand wird generisch erfasst).
--    - Auftragsstatus: als ENUM direkt am Auftrag, daher keine Nachschlagetabelle.
--    - Betrieb: entfällt (Einzelbetriebs-Werkzeug, der Betrieb ist implizit).
--
--  Deshalb gibt es hier nichts einzuspielen. Der erste Login-Benutzer (Rolle
--  'admin') wird bewusst NICHT geseedet, sondern mit einem nachvollziehbaren,
--  gehashten Passwort über das Skript erzeugt:
--
--      docker compose run --rm -v "${PWD}/database/scripts:/scripts" \
--        backend php /scripts/create_admin.php <benutzername>
--
--  (PowerShell: ${PWD} funktioniert ebenso.) Das Skript gibt das Klartext-
--  Passwort einmalig aus. Details siehe database/scripts/create_admin.php.
-- ============================================================================

USE servicemanager;

-- Absichtlich keine Datensätze. Datei bleibt bestehen, damit der Docker-Init
-- (schema.sql -> seed.sql) unverändert funktioniert und der Zweck dokumentiert ist.

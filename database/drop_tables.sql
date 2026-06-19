-- ============================================================================
--  ServiceManager – Vollständiger Tabellen-Drop (Reset-Hilfsskript)
--
--  ACHTUNG: Löscht ALLE Tabellen samt Daten in der Datenbank "servicemanager".
--  Dieses Skript läuft NICHT automatisch (liegt absichtlich außerhalb von
--  database/init/). Manuell einspielen, z. B.:
--
--    - phpMyAdmin (http://localhost:8080) -> SQL-Tab -> Inhalt einfügen, ausführen
--    - oder CLI:
--        docker compose exec -T db \
--          mysql -uroot -prootpass servicemanager < database/drop_tables.sql
--
--  Danach kann die Struktur über database/init/schema.sql neu angelegt werden.
--
--  Hinweis: Der einfachste komplette Reset ist stattdessen
--    docker compose down -v && docker compose up -d
--  Damit wird das DB-Volume verworfen und die Init-Skripte laufen automatisch
--  erneut (schema.sql + seed.sql).
-- ============================================================================

USE servicemanager;

-- FK-Prüfung aus, damit die Lösch-Reihenfolge keine Rolle spielt.
SET FOREIGN_KEY_CHECKS = 0;

-- Aktuelles Modell (4 Tabellen)
DROP TABLE IF EXISTS auftrag_status_historie;
DROP TABLE IF EXISTS auftrag;
DROP TABLE IF EXISTS kunde;
DROP TABLE IF EXISTS mitarbeiter;

-- Altes, zu komplexes Modell (falls noch vorhanden) – ebenfalls entfernen.
DROP TABLE IF EXISTS servicegegenstand_attribut;
DROP TABLE IF EXISTS servicegegenstand;
DROP TABLE IF EXISTS feld_definition;
DROP TABLE IF EXISTS auftragsstatus;
DROP TABLE IF EXISTS branche;
DROP TABLE IF EXISTS betrieb;

SET FOREIGN_KEY_CHECKS = 1;

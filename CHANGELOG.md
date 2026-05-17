# Changelog — Luna V3

Alle relevanten Änderungen an Luna V3 werden in dieser Datei dokumentiert.

Format basiert lose auf Keep a Changelog.

---

## [Unreleased]

### Added

- Mapping Designer für workspace-bezogene Mapping Sets, Feldzuordnungen, Transformationsarten, Value Rules und Validierung
- Audit-Log-Einträge für Mapping-Set-, Mapping-Field-, Value-Rule- und Validierungsänderungen
- `MappingRepository`, `AuditLogRepository` und Mapping-Validierungsservices
- Connection Manager und Schema Explorer für externe MySQL/MariaDB-Datenquellen
- verschlüsselte Speicherung externer Connection-Secrets in `luna_connection_secrets`
- Repository-Grundlage für Workspaces, Connection-Profile und Schema-Metadaten
- Verbindungstest, Tabellen-/Spaltenanalyse, Beispieldaten und Luna-Kommentare für Tabellen und Spalten
- Luna-Systemdatenbank-Grundlage mit initialer SQL-Migration für Workspaces, Connection-Profile, Secrets, Schema-Metadaten, Notes, Mapping-Entwürfe, Value Rules und Audit Log
- PDO-basierte Kapselung der Luna-Systemdatenbank mit `DatabaseConfig`, `PdoConnectionFactory`, `SystemDatabase` und `MigrationRunner`
- `EncryptionService` für versionierte Secret-Verschlüsselung mit AES-256-GCM auf Basis von `APP_KEY`
- CLI-Script `bin/luna` mit `db:test` und `migrate`
- Admin-UI-Grundlage mit Bootstrap-Layout, Navigation und statischen Workbench-Seiten
- `ViewRenderer` für serverseitige Templates aus `resources/views`
- Admin-Routen für Dashboard, Workspaces, Connections, Schema Explorer, Mappings, Jobs und Reports
- kleine ergänzende Styles für die Admin UI unter `public/assets/css/admin.css`
- HTTP-Grundlage mit `Request`, `Response`, `Route`, `RouteCollection` und `Router`
- getrennte Routenregistrierung für Web- und API-Routen über `routes/web.php` und `routes/api.php`
- zentrale Response-Ausgabe über `Application::run()`
- einfache 404- und 500-Antworten für Routing-Grundlage
- Application Core für Luna V3 mit `Application`, `Kernel`, `Paths`, `ServiceRegistry` und zentraler `Config`
- Bootstrap erstellt jetzt die Anwendungslaufzeit aus Pfaden und Luna-Core-Konfiguration
- Public Front Controller delegiert an `$app->run()`
- `.env.example` um `APP_KEY`, Luna-Systemdatenbank-, Mail- und `CRON_SECRET`-Platzhalter erweitert
- Luna V3 als Integrations- und Mapping-Workbench neu definiert
- `docs/PRODUCT_SPEC.md` mit Produktdefinition, Nicht-Zielen und Kernfunktionen ergänzt
- `docs/SECURITY_MODEL.md` mit Secret-, `.env`-, API- und Logging-Regeln ergänzt
- `docs/DATA_MODEL_DRAFT.md` mit Entwurf für die Luna-Systemdatenbank ergänzt
- Roadmap auf Workbench-Meilensteine von 0.1.0 bis 1.0.0 neu ausgerichtet
- Projektziel- und Architekturdokumentation an die Workbench-Ausrichtung angepasst

---

## [0.1.0] - 2026-05-16

### Added

- Projektstruktur für Luna V3 vorbereitet
- Composer-Konfiguration mit PSR-4 Autoloading
- Dotenv als Dependency hinzugefügt
- Public Entry Point vorbereitet
- Bootstrap-Klasse vorbereitet
- `.env.example` mit lokalen Beispielwerten ergänzt
- Initiales Projektfundament
- AGENTS.md für Codex
- ROADMAP.md
- Codex-Protokoll
- CHANGELOG.md

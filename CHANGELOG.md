# Changelog — Luna V3

Alle relevanten Änderungen an Luna V3 werden in dieser Datei dokumentiert.

Format basiert lose auf Keep a Changelog.

---

## [Unreleased]

### Added

- Mapping Designer 1.4.0 unterstützt `mapping_mode = json_endpoint`, optionale Target Connections für Read-Export-Mappings, Lookup Connections pro Feldregel und den Transform `key_value_map_by_prefix`.
- Source-Filter Builder für Mapping-Preview, CLI-Dry-Run und JSON Endpoint Runtime: mehrere Mapping-weite Filter werden zentral über `MappingSourceRowProvider` per AND angewendet und in `luna_mapping_source_filters` gespeichert.
- `key_value_map_by_prefix` wird vor der Mapping-Transformation gebatcht vorab geladen, sodass Prefix-Maps nicht mehr pro Source Row eine eigene Lookup-Abfrage ausführen.
- JSON Endpoint Builder v2 1.4.0 mit Mapping-gebundenen Runtime-Endpunkten unter `/api/endpoints/{slug}`, standardisiertem JSON-Erfolgs-/Fehlerformat, Secret-Modus und vorbereiteten Cache-Feldern.
- Sichere Admin-Löschaktionen für Workspaces, Connections, Mappings und Endpoints mit serverseitiger Abhängigkeitsprüfung.
- Workbench UX 1.1.0 mit dunklem Standard-Theme, Light/Dark-Switch und lokalem `luna_theme`-Cookie
- Workspace-Erstellung und -Bearbeitung über die Admin UI inklusive Slug-Validierung und Audit Events
- dynamische Source-/Target-Tabellenauswahl beim Mapping-Anlegen und -Bearbeiten über `/admin/api/connection-tables?connection_id=ID`; die bisherige JSON-Route `/admin/schema/{connectionId}/tables.json` bleibt als Kompatibilitätsroute erhalten und lädt für Dropdowns nur Tabellennamen
- Externe PDO-Verbindungen lösen Hostnamen intern bevorzugt auf IPv4-A-Records auf, ohne den gespeicherten Connection-Host zu verändern.
- Multi-Connection-Grundlage für 1.2.0 ergänzt: Connection-Rollen `source`, `transfer`, `target`, validierte MySQL/MariaDB-Driver, Source-read-only-Default und CLI-Verbindungstest `php bin/luna connection:test <connection-id>`.
- Connections können über `/admin/connections/{id}/edit` bearbeitet werden, ohne bestehende Secrets bei leerem Passwortfeld zu löschen; Workspace-Create redirectet nach Erfolg zurück auf `/admin/workspaces`.
- Mapping-Tabellenlisten liefern wieder `name` und `label`; das Frontend setzt sichtbare Option-Texte mit Fallback auf den Tabellennamen.
- Lookup Mapping 1.3.0 mit den Feldtypen `source_column`, `static_value` und `lookup_value`, Template-Keys wie `price_group_{{price_group}}`, Multi-Connection-Lookups und Dry-Run-Transfer-Vorschau ohne Secrets.
- Endpoint Builder für einfache public/private API-Endpunkte mit Admin UI
- verschlüsselte Endpoint-Secrets in `luna_endpoint_secrets`
- Endpoint Runtime unter `/api/e/{endpoint_key}` für `static`, `version`, `mapping_dry_run`, `job_status` und `latest_report`
- Audit-Ansicht unter `/admin/audit`
- Betriebs-, Deployment-, API- und Security-Dokumentation für 1.0.0
- zentrale App-Version `1.0.0`
- Jobs, Dry Runs, kontrollierte Mapping-Transfers, Job-Run-Logs und Reports
- CLI-Befehle für `job:run`, `mapping:dry-run` und abgesicherte `mapping:run --force`
- Report-Erzeugung und gekapselter Mailversand ohne neue Dependency
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

### Changed

- Lookup-Mapping-UI 1.3.0 fokussiert jetzt Source-Filter, Lookup Connection/Table, Lookup-Spalten und Lookup-Test; hartcodierte Transfer-Feld-Vorschläge wurden aus der Feldzuordnungsmaske entfernt.
- Feldzuordnungsmaske 1.3.0 behandelt `target_column` nur noch als optionalen Ausgabe-Alias; Lookup Key/Value Columns kommen aus der Lookup-Tabelle.
- CLI-Ausgabe fuer `mapping:dry-run`, `mapping:run --force` und `job:run` zeigt jetzt finalen Run-Status, Zaehler und sichere Fehlermeldungen statt nur die angelegte Run-ID.
- Echte Mapping-Transfers mit `read_only` Target Connection werden eindeutig als fehlgeschlagen blockiert und mit `written_count = 0` protokolliert.

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

# Changelog â€” Luna V3

Alle relevanten Ã„nderungen an Luna V3 werden in dieser Datei dokumentiert.

Format basiert lose auf Keep a Changelog.

---

## [Unreleased]

### Added

- Admin Cleanup Completion v2.7.2.1 ergänzt: Jobs, Reports, Connections, Transfers und WooCommerce-Anbindungen besitzen vollständige POST-Delete-Aktionen ohne 404; Reports können angelegt und bearbeitet werden; der zentrale `DeletionGuard` liefert konkrete Mapping-, Job-, Dataset-, Process-, Run-, Exportprofil- und WooCommerce-Runtime-Blocker ohne Secrets oder rohe PDO-Fehler.
- TransferDB Foundation v2.7.1 ergänzt: Connections können als `transfer_db` markiert und Workspaces als Default-TransferDB zugeordnet werden; TransferDB-Management-Actions testen die Verbindung, prüfen vorhandene/fehlende Tabellen und führen idempotente Setup-/Migration-Schritte gegen die ausgewählte TransferDB aus; Webhook Events und Endpoint Snapshots können secret-frei in die TransferDB geschrieben werden, und Endpoint-Exportpakete enthalten `runtime_storage`-Metadaten ohne Zugangsdaten.
- WooCommerce Runtime Module v2.7.0 ergänzt: WooCommerce-Webhooks laufen über den generischen Trigger Layer, prüfen HMAC-SHA256-Signaturen gegen verschlüsselt gespeicherte Trigger-Secrets, normalisieren Event-Metadaten, stagen Runtime Events, starten normale Process Runs und protokollieren nur sanitizte Payload-Zusammenfassungen ohne WooCommerce-Schreibzugriffe.
- Schema Registry & Validation v2.6.0 ergänzt: `luna_schemas`, `luna_schema_revisions`, versionierte Schema-Definitionen je Workspace, Admin-UI, Validator für verschachtelte Objekte/Arrays, optionaler Endpoint-Schema-Bezug, `schema_validation` Process-Step, `schema:validate` CLI und Schema-Referenzen im Endpoint-Export ohne Secrets.
- Adapter / Target Actions Foundation v2.5.0 ergänzt: `luna_target_actions`, generische Action-Typen `http_get`, `http_post`, `http_put`, `file_export`, `database_insert`, `database_upsert`, Target-Action-Step-Runner, Dry-Run-Schutz, sichere File-/DB-/HTTP-Ausführung und Admin-UI für Target Actions.
- Trigger Layer v2.4.0 ergänzt: `luna_process_triggers`, Trigger-Typen `manual`, `cli`, `api`, `schedule`, `webhook`, Aktivierung/Deaktivierung, Trigger-Konfiguration, generische API-/Webhook-Auslösung, CLI-Trigger-Start und Prozessläufe mit sicherem Trigger-Kontext ohne Fachverarbeitung oder Secret-Leaks.

- Dataset Sources 1.8.0 vorbereitet: `DatasetRegistry` leitet Datasets aus bestehenden Endpoints und JSON-Endpoint-Mappings ab, zeigt Output-Felder, Source Filter und eine limitierte Preview ohne Schreiblogik; Admin-UI und CLI-Diagnose `dataset:list` / `dataset:preview` ergÃ¤nzt.
- Deployment-Runtime 1.7.0 fuer `isr_prices` ergaenzt: Integration-Export erzeugt `README_DEPLOY.md`, `config/config.example.php`, `CHECKSUMS.txt`, Manifest mit `source_commit`, Secret-Policy, Deployment-Metadaten und einen sicheren Healthcheck ueber `?health=1`.
- Minimale Integration-Module-Grundlage fÃ¼r exportierbare Endpunkte ergÃ¤nzt; `isr_prices` ist als erstes Export-Modul mit Manifest, Runtime-Dateiliste, Ausschlussliste und Secret-Policy registriert.
- Fachliche GegenprÃ¼fung fÃ¼r das `isr_prices` Export-Modul ergÃ¤nzt: Dry-Run und Exportvalidierung zeigen enthaltene Dateien, AusschlÃ¼sse, Secret-Policy, verbotene Dateien, lokale Pfadfunde und Secret-Zuweisungsfunde; PHPUnit vergleicht den internen Endpoint-Payload mit der exportierten Runtime-Fixture.
- Endpoint Export Runtime 1.5.0 mit CLI-Befehl `endpoint:export`, deploybarem Runtime-Paket, API-Datei, Runtime-Bootstrap, env-basierter Connection-Konfiguration, `.env.example` und Export-Manifest.
- Endpoint Export Runtime kann mit `--local-env` optional eine lokale `.env` mit entschlÃ¼sselten Runtime-Secrets fÃ¼r Testexports schreiben; Standardexporte bleiben secretfrei und erzeugen nur `.env.example`.
- Endpoint Runtime Exporte kÃ¶nnen Ã¼ber die Admin-Endpoint-Detailseite gestartet werden und nutzen standardmÃ¤ÃŸig `storage/{workspace_slug}/exports/endpoints/{endpoint_key}/`.
- Admin-Endpoint-Exporte erzeugen automatisch ein ZIP-Archiv `{workspace_slug}-{endpoint_key}-runtime.zip`; der CLI-Export kann mit `--zip` ebenfalls ein Archiv erstellen, wobei echte `.env`-Dateien und Secrets ausgeschlossen bleiben.
- Mapping-Transform `first_non_empty` ergÃ¤nzt: Mapping-Regeln kÃ¶nnen mehrere Source Columns kommasepariert prÃ¼fen und den ersten nicht-leeren Wert als berechnetes Output Field fÃ¼r spÃ¤tere Lookup-/Prefix-Templates verwenden.

- Mapping Designer 1.4.0 unterstÃ¼tzt `mapping_mode = json_endpoint`, optionale Target Connections fÃ¼r Read-Export-Mappings, Lookup Connections pro Feldregel und den Transform `key_value_map_by_prefix`.
- Source-Filter Builder fÃ¼r Mapping-Preview, CLI-Dry-Run und JSON Endpoint Runtime: mehrere Mapping-weite Filter werden zentral Ã¼ber `MappingSourceRowProvider` per AND angewendet und in `luna_mapping_source_filters` gespeichert.
- `key_value_map_by_prefix` wird vor der Mapping-Transformation gebatcht vorab geladen, sodass Prefix-Maps nicht mehr pro Source Row eine eigene Lookup-Abfrage ausfÃ¼hren.
- JSON Endpoint Builder v2 1.4.0 mit Mapping-gebundenen Runtime-Endpunkten unter `/api/endpoints/{slug}`, standardisiertem JSON-Erfolgs-/Fehlerformat, Secret-Modus und vorbereiteten Cache-Feldern.
- Sichere Admin-LÃ¶schaktionen fÃ¼r Workspaces, Connections, Mappings und Endpoints mit serverseitiger AbhÃ¤ngigkeitsprÃ¼fung.
- Workbench UX 1.1.0 mit dunklem Standard-Theme, Light/Dark-Switch und lokalem `luna_theme`-Cookie
- Workspace-Erstellung und -Bearbeitung Ã¼ber die Admin UI inklusive Slug-Validierung und Audit Events
- dynamische Source-/Target-Tabellenauswahl beim Mapping-Anlegen und -Bearbeiten Ã¼ber `/admin/api/connection-tables?connection_id=ID`; die bisherige JSON-Route `/admin/schema/{connectionId}/tables.json` bleibt als KompatibilitÃ¤tsroute erhalten und lÃ¤dt fÃ¼r Dropdowns nur Tabellennamen
- Externe PDO-Verbindungen lÃ¶sen Hostnamen intern bevorzugt auf IPv4-A-Records auf, ohne den gespeicherten Connection-Host zu verÃ¤ndern.
- Multi-Connection-Grundlage fÃ¼r 1.2.0 ergÃ¤nzt: Connection-Rollen `source`, `transfer`, `target`, validierte MySQL/MariaDB-Driver, Source-read-only-Default und CLI-Verbindungstest `php bin/luna connection:test <connection-id>`.
- Connections kÃ¶nnen Ã¼ber `/admin/connections/{id}/edit` bearbeitet werden, ohne bestehende Secrets bei leerem Passwortfeld zu lÃ¶schen; Workspace-Create redirectet nach Erfolg zurÃ¼ck auf `/admin/workspaces`.
- Mapping-Tabellenlisten liefern wieder `name` und `label`; das Frontend setzt sichtbare Option-Texte mit Fallback auf den Tabellennamen.
- Lookup Mapping 1.3.0 mit den Feldtypen `source_column`, `static_value` und `lookup_value`, Template-Keys wie `price_group_{{price_group}}`, Multi-Connection-Lookups und Dry-Run-Transfer-Vorschau ohne Secrets.
- Endpoint Builder fÃ¼r einfache public/private API-Endpunkte mit Admin UI
- verschlÃ¼sselte Endpoint-Secrets in `luna_endpoint_secrets`
- Endpoint Runtime unter `/api/e/{endpoint_key}` fÃ¼r `static`, `version`, `mapping_dry_run`, `job_status` und `latest_report`
- Audit-Ansicht unter `/admin/audit`
- Betriebs-, Deployment-, API- und Security-Dokumentation fÃ¼r 1.0.0
- zentrale App-Version `1.0.0`
- Jobs, Dry Runs, kontrollierte Mapping-Transfers, Job-Run-Logs und Reports
- CLI-Befehle fÃ¼r `job:run`, `mapping:dry-run` und abgesicherte `mapping:run --force`
- Report-Erzeugung und gekapselter Mailversand ohne neue Dependency
- Mapping Designer fÃ¼r workspace-bezogene Mapping Sets, Feldzuordnungen, Transformationsarten, Value Rules und Validierung
- Audit-Log-EintrÃ¤ge fÃ¼r Mapping-Set-, Mapping-Field-, Value-Rule- und ValidierungsÃ¤nderungen
- `MappingRepository`, `AuditLogRepository` und Mapping-Validierungsservices
- Connection Manager und Schema Explorer fÃ¼r externe MySQL/MariaDB-Datenquellen
- verschlÃ¼sselte Speicherung externer Connection-Secrets in `luna_connection_secrets`
- Repository-Grundlage fÃ¼r Workspaces, Connection-Profile und Schema-Metadaten
- Verbindungstest, Tabellen-/Spaltenanalyse, Beispieldaten und Luna-Kommentare fÃ¼r Tabellen und Spalten
- Luna-Systemdatenbank-Grundlage mit initialer SQL-Migration fÃ¼r Workspaces, Connection-Profile, Secrets, Schema-Metadaten, Notes, Mapping-EntwÃ¼rfe, Value Rules und Audit Log
- PDO-basierte Kapselung der Luna-Systemdatenbank mit `DatabaseConfig`, `PdoConnectionFactory`, `SystemDatabase` und `MigrationRunner`
- `EncryptionService` fÃ¼r versionierte Secret-VerschlÃ¼sselung mit AES-256-GCM auf Basis von `APP_KEY`
- CLI-Script `bin/luna` mit `db:test` und `migrate`
- Admin-UI-Grundlage mit Bootstrap-Layout, Navigation und statischen Workbench-Seiten
- `ViewRenderer` fÃ¼r serverseitige Templates aus `resources/views`
- Admin-Routen fÃ¼r Dashboard, Workspaces, Connections, Schema Explorer, Mappings, Jobs und Reports
- kleine ergÃ¤nzende Styles fÃ¼r die Admin UI unter `public/assets/css/admin.css`
- HTTP-Grundlage mit `Request`, `Response`, `Route`, `RouteCollection` und `Router`
- getrennte Routenregistrierung fÃ¼r Web- und API-Routen Ã¼ber `routes/web.php` und `routes/api.php`
- zentrale Response-Ausgabe Ã¼ber `Application::run()`
- einfache 404- und 500-Antworten fÃ¼r Routing-Grundlage
- Application Core fÃ¼r Luna V3 mit `Application`, `Kernel`, `Paths`, `ServiceRegistry` und zentraler `Config`
- Bootstrap erstellt jetzt die Anwendungslaufzeit aus Pfaden und Luna-Core-Konfiguration
- Public Front Controller delegiert an `$app->run()`
- `.env.example` um `APP_KEY`, Luna-Systemdatenbank-, Mail- und `CRON_SECRET`-Platzhalter erweitert
- Luna V3 als Integrations- und Mapping-Workbench neu definiert
- `docs/PRODUCT_SPEC.md` mit Produktdefinition, Nicht-Zielen und Kernfunktionen ergÃ¤nzt
- `docs/SECURITY_MODEL.md` mit Secret-, `.env`-, API- und Logging-Regeln ergÃ¤nzt
- `docs/DATA_MODEL_DRAFT.md` mit Entwurf fÃ¼r die Luna-Systemdatenbank ergÃ¤nzt
- Roadmap auf Workbench-Meilensteine von 0.1.0 bis 1.0.0 neu ausgerichtet
- Projektziel- und Architekturdokumentation an die Workbench-Ausrichtung angepasst

### Changed

- Lookup-Mapping-UI 1.3.0 fokussiert jetzt Source-Filter, Lookup Connection/Table, Lookup-Spalten und Lookup-Test; hartcodierte Transfer-Feld-VorschlÃ¤ge wurden aus der Feldzuordnungsmaske entfernt.
- Feldzuordnungsmaske 1.3.0 behandelt `target_column` nur noch als optionalen Ausgabe-Alias; Lookup Key/Value Columns kommen aus der Lookup-Tabelle.
- CLI-Ausgabe fuer `mapping:dry-run`, `mapping:run --force` und `job:run` zeigt jetzt finalen Run-Status, Zaehler und sichere Fehlermeldungen statt nur die angelegte Run-ID.
- Echte Mapping-Transfers mit `read_only` Target Connection werden eindeutig als fehlgeschlagen blockiert und mit `written_count = 0` protokolliert.

---

## [0.1.0] - 2026-05-16

### Added


- Projektstruktur fÃ¼r Luna V3 vorbereitet
- Composer-Konfiguration mit PSR-4 Autoloading
- Dotenv als Dependency hinzugefÃ¼gt
- Public Entry Point vorbereitet
- Bootstrap-Klasse vorbereitet
- `.env.example` mit lokalen Beispielwerten ergÃ¤nzt
- Initiales Projektfundament
- AGENTS.md fÃ¼r Codex
- ROADMAP.md
- Codex-Protokoll
- CHANGELOG.md


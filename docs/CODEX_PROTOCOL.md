# Codex-Protokoll — Luna V3

Dieses Protokoll dokumentiert Arbeitspakete, die mit Codex umgesetzt oder vorbereitet wurden.

## Format

Jeder Eintrag soll enthalten:

- Datum
- Ziel
- Prompt/Aufgabe
- Geänderte Dateien
- Ergebnis
- Offene Punkte
- Commit-Hash, falls vorhanden

## Abschlussprüfung: sichtbare deutsche UI-Texte

Bei jeder Codex-Aufgabe muss am Ende geprüft werden, ob neu geänderte sichtbare deutsche UI-Texte korrekte Umlaute verwenden.

Diese Prüfung gilt ausschließlich für Texte, die Menschen in der Oberfläche sehen.

Dazu gehören:

- Labels
- Button-Texte
- Formularbeschriftungen
- Hilfetexte
- Validierungsmeldungen
- Fehlermeldungen in der UI
- Tabellenüberschriften
- Navigationspunkte
- Statusmeldungen
- Empty-State-Texte
- sichtbare Beschreibungstexte in der Admin-Oberfläche

Diese Prüfung gilt ausdrücklich nicht für technischen Code.

Niemals wegen Umlauten ändern:

- PHP-Klassen
- PHP-Methoden
- PHP-Variablen
- Konstanten
- Enum-Werte
- Array-Keys
- JSON-Keys
- Request-/Response-Felder
- Datenbanktabellen
- Datenbankspalten
- Migrationen
- Dateinamen
- CSS-Klassen
- JavaScript-Identifier
- Routen
- CLI-Befehle
- Config-Keys
- technische Werte wie `source_column`, `lookup_value`, `target_column`, `price_group`, `missing_behavior`

In sichtbaren deutschen UI-Texten müssen Umlaute korrekt geschrieben werden.

Richtig:

- `Bitte wählen`
- `Zurück`
- `Hinzufügen`
- `Löschen`
- `Ändern`
- `Übernehmen`
- `Für dieses Mapping sind keine Felder vorhanden.`
- `Größe`
- `Schlüssel`
- `Verknüpfung`
- `Auflösung`
- `gültig`
- `ungültig`
- `möglich`
- `öffnen`
- `schließen`
- `Prüfung`
- `Ausführung`
- `Zuordnung`

Falsch in sichtbaren UI-Texten:

- `Bitte waehlen`
- `Zurueck`
- `Hinzufuegen`
- `Loeschen`
- `Aendern`
- `Uebernehmen`
- `Fuer dieses Mapping sind keine Felder vorhanden.`
- `Groesse`
- `Schluessel`
- `Verknuepfung`
- `Aufloesung`
- `gueltig`
- `ungueltig`
- `moeglich`
- `oeffnen`
- `schliessen`
- `Pruefung`
- `Ausfuehrung`

Wichtig:

Technische Bezeichner dürfen nicht verändert werden, auch wenn sie deutsch aussehen oder keine Umlaute enthalten. Diese Regel betrifft ausschließlich sichtbare Ausgabetexte für Menschen.

Der Abschlussbericht muss enthalten:

```text
UI-Umlautprüfung: durchgeführt
```

Wenn bewusst ein sichtbarer UI-Text ohne Umlaut bleibt, muss der Grund genannt werden.

---

## 2026-06-02 â€” 1.8.0 Dataset Sources Foundation

### Ziel

Vorhandene Mapping-/Endpoint-Ergebnisse als interne Dataset Sources sichtbar machen, ohne Transfer-, Writer- oder Zielsystemlogik zu bauen.

### Prompt/Aufgabe

`1.8.0` soll eine minimale Dataset Registry bereitstellen, Output-Felder und Source Filter aus bestehenden Endpoints/Mappings ableiten und eine begrenzte Preview über den bestehenden Mapping-Dry-Run ermöglichen.

### Geänderte Dateien

- `src/Dataset/DatasetRegistry.php`
- `src/Core/Application.php`
- `routes/web.php`
- `resources/views/layouts/admin.php`
- `resources/views/admin/datasets/index.php`
- `resources/views/admin/datasets/show.php`
- `bin/luna`
- `tests/Unit/DatasetRegistryTest.php`
- `CHANGELOG.md`
- `docs/CODEX_PROTOCOL.md`

### Ergebnis

Dataset Sources werden aus vorhandenen Endpoint-/Mapping-Konfigurationen abgeleitet. Die Admin-UI zeigt Datasets, Output-Felder, Source Filter und Preview-Zeilen. Die CLI bietet `dataset:list` und `dataset:preview`.

### Offene Punkte

Keine Transfer-Schreiblogik in `1.8.0`; Single-Table-Transfers bleiben für `1.9.0` geplant.

---

---

## 2026-05-16 — Initiale Codex-Struktur

### Ziel

Projekt für strukturierte Codex-Arbeit vorbereiten.

### Aufgabe

AGENTS.md, ROADMAP.md, CHANGELOG.md und Codex-Protokoll anlegen.

### Geänderte Dateien

- AGENTS.md
- ROADMAP.md
- CHANGELOG.md
- docs/CODEX_PROTOCOL.md

### Ergebnis

Offen.

### Offene Punkte

- Erste Roadmap-Version finalisieren
- Version 0.1.0 definieren
- Erstes Codex-Arbeitspaket testen

---

## 2026-05-16 — Meilenstein 0.1.0

### Ziel

Projektfundament technisch sauber abschließen.

### Aufgabe

Meilenstein 0.1.0 aus ROADMAP.md umsetzen: Front Controller, Bootstrap, Dotenv-Laden, Beispielumgebung und Dokumentation prüfen und aktualisieren.

### Geprüfte und geänderte Dateien

- public/index.php
- src/Bootstrap.php
- .env.example
- CHANGELOG.md
- docs/CODEX_PROTOCOL.md

### Ergebnis

Bootstrap lädt Dotenv nur, wenn eine lokale `.env` existiert. Der Public Entry Point bleibt schlank und nutzt den Composer-Autoloader sowie `Luna\Bootstrap`. `.env.example` enthält lokale Beispielwerte ohne echte Secrets.

### Offene Punkte

- Keine.

---

## 2026-05-16 — Meilenstein 0.2.0 neu ausgerichtet

### Ziel

Projektziel und Architekturdefinition vor weiteren PHP-Implementierungen dokumentieren.

### Aufgabe

ROADMAP.md überarbeiten, Meilenstein 0.2.0 von Application und Kernel auf Projektziel und Architekturdefinition ändern, `docs/PROJECT_GOALS.md` und `docs/ARCHITECTURE.md` anlegen sowie Changelog und Protokoll aktualisieren.

### Geänderte Dateien

- ROADMAP.md
- docs/PROJECT_GOALS.md
- docs/ARCHITECTURE.md
- CHANGELOG.md
- docs/CODEX_PROTOCOL.md

### Ergebnis

Luna V3 ist als internes PHP-8.2+-Framework beschrieben. Offene Entscheidungen zu Router, MVC, Modulen, Controller-Lifecycle, Datenbankmodell, Konfiguration und Error Handling sind dokumentiert. Die geplante Laufzeit ist grob skizziert, ohne neue PHP-Klassen zu implementieren.

### Offene Punkte

- Architekturentscheidungen für die folgenden Meilensteine treffen.

---

## 2026-05-16 — Luna V3 als Workbench neu definiert

### Ziel

Luna V3 fachlich von einem generischen internen Framework zu einer Integrations- und Mapping-Workbench neu ausrichten.

### Aufgabe

Produktdokumentation ohne PHP-Code-Änderungen aktualisieren, `docs/PRODUCT_SPEC.md`, `docs/SECURITY_MODEL.md` und `docs/DATA_MODEL_DRAFT.md` anlegen, ROADMAP.md neu ausrichten sowie Changelog und Codex-Protokoll aktualisieren.

### Geänderte Dateien

- ROADMAP.md
- CHANGELOG.md
- docs/CODEX_PROTOCOL.md
- docs/PROJECT_GOALS.md
- docs/ARCHITECTURE.md
- docs/PRODUCT_SPEC.md
- docs/SECURITY_MODEL.md
- docs/DATA_MODEL_DRAFT.md

### Ergebnis

Luna V3 ist als webbasierte PHP-8.2+-Workbench für Integrationsprojekte definiert. Die Dokumentation beschreibt Workspaces, Luna-Systemdatenbank, externe Datenquellen, Connection Manager, verschlüsselte Secrets, Schema Explorer, Mapping Designer, Value Mapping, Transferdatenbank, Job Runner, Report Engine, Endpoint Builder und Audit Log. Sicherheitsregeln und ein erster Datenmodell-Entwurf sind dokumentiert.

### Offene Punkte

- Workbench-Architektur in den kommenden Meilensteinen technisch ausarbeiten.

---

## 2026-05-17 — Meilenstein 0.3.0 Application Core

### Ziel

Eine zentrale Laufzeit für Luna V3 schaffen, ohne Routing, Datenbank, Mapping, Jobs oder UI-Logik zu implementieren.

### Aufgabe

Application- und Kernel-Grundstruktur, zentrale Konfiguration aus der Luna-Core-Umgebung, grundlegende Service-Registrierung und zentrale Pfadverwaltung implementieren. Bootstrap, Anwendungslaufzeit und spätere Fachmodule klar trennen.

### Geänderte Dateien

- public/index.php
- src/Bootstrap.php
- src/Core/Application.php
- src/Core/Kernel.php
- src/Core/Paths.php
- src/Core/ServiceRegistry.php
- src/Config/Config.php
- .env.example
- CHANGELOG.md
- docs/CODEX_PROTOCOL.md

### Ergebnis

`public/index.php` lädt nur den Autoloader, erstellt die Application über `Luna\Bootstrap` und gibt `$app->run()` aus. Bootstrap lädt optional `.env`, erstellt `Paths`, `Config` und `Application`. Der Application Core hält Pfade, Konfiguration, ServiceRegistry und Kernel. Der Kernel erzeugt für 0.3.0 nur eine kontrollierte Startausgabe mit App-Name, Environment und Debug-Status.

### Offene Punkte

- Routing, Request/Response, Controller, Datenbankverbindung, Admin UI, Mapping, Jobs und API-Endpunkte bleiben Folge-Meilensteine.

---

## 2026-05-17 — Meilenstein 0.4.0 HTTP-Grundlage und Routing

### Ziel

HTTP-Verarbeitung für Admin UI, API-Endpunkte und interne Aktionen vorbereiten.

### Aufgabe

Request/Response-Abstraktion, Routing-Grundlage, Fehlerantworten für unbekannte Routen, getrennte Web- und API-Routen sowie zentrale Response-Ausgabe implementieren. Keine Admin UI, kein Login, keine Datenbank, keine Mapping-Logik, keine Jobs und keine API-Secret-Prüfung umsetzen.

### Geänderte Dateien

- public/index.php
- src/Core/Application.php
- src/Core/Kernel.php
- src/Http/Request.php
- src/Http/Response.php
- src/Routing/Route.php
- src/Routing/RouteCollection.php
- src/Routing/Router.php
- routes/web.php
- routes/api.php
- CHANGELOG.md
- docs/CODEX_PROTOCOL.md

### Ergebnis

`Application::handle()` erzeugt eine `Response`, `Application::run()` sendet sie zentral. Der Kernel erstellt den Request aus Globals, lädt Web- und API-Routen und dispatcht über den Router. Es existieren erste Routen für `/`, `/health` und `/api/version`. Unbekannte Routen liefern 404, Exceptions liefern eine einfache 500-Antwort ohne Debug-Details.

### Offene Punkte

- Admin UI, Login, Datenbank, Migrationen, Controller, Templates, Mapping, Jobs, Endpoint Builder und API-Secret-Prüfung bleiben Folge-Meilensteine.

---

## 2026-05-17 — Meilenstein 0.5.0 Admin UI mit Bootstrap

### Ziel

Eine einfache webbasierte Admin-Oberfläche für die Luna Workbench bereitstellen.

### Aufgabe

Bootstrap-basiertes Admin-Layout, Navigation für Workspaces, Connections, Schema Explorer, Mappings, Jobs und Reports, statische Form- und Tabellenkomponenten sowie einen serverseitigen ViewRenderer implementieren. Keine Datenbank, kein Login, keine echte Speicherung und keine Fachlogik umsetzen.

### Geänderte Dateien

- src/View/ViewRenderer.php
- resources/views/layouts/admin.php
- resources/views/admin/dashboard.php
- resources/views/admin/workspaces.php
- resources/views/admin/connections.php
- resources/views/admin/schema.php
- resources/views/admin/mappings.php
- resources/views/admin/jobs.php
- resources/views/admin/reports.php
- public/assets/css/admin.css
- src/Core/Paths.php
- src/Core/Application.php
- routes/web.php
- CHANGELOG.md
- docs/CODEX_PROTOCOL.md

### Ergebnis

Die Admin UI ist als statische serverseitige Oberfläche vorbereitet. `ViewRenderer` rendert Templates aus `resources/views`, `Application` registriert ihn als Service `view`, und Web-Routen liefern Dashboard, Workspaces, Connections, Schema Explorer, Mappings, Jobs und Reports. API-Routen aus 0.4.0 bleiben unverändert nutzbar.

### Offene Punkte

- Datenbank, Login, Rechtesystem, echte Workspace- und Connection-Erstellung, Mapping-Speicherung, Job-Ausführung und Report-Erzeugung bleiben Folge-Meilensteine.

---

## 2026-05-17 — Meilenstein 0.6.0 Luna-Systemdatenbank

### Ziel

Persistenz für Workbench-Metadaten schaffen.

### Aufgabe

Initiales Schema für die Luna-Systemdatenbank, Migration-Grundlage, PDO-Kapselung, CLI-Wartungsbefehle und Secret-Verschlüsselung auf Basis von `APP_KEY` implementieren. Keine echte UI-Verwaltung, keine externen Datenbankverbindungen und keine Fachmodule umsetzen.

### Geänderte Dateien

- database/migrations/2026_05_17_000001_create_luna_system_tables.sql
- src/Database/DatabaseConfig.php
- src/Database/PdoConnectionFactory.php
- src/Database/SystemDatabase.php
- src/Database/MigrationRunner.php
- src/Security/EncryptionService.php
- bin/luna
- .env.example
- src/Core/Application.php
- src/Config/Config.php
- CHANGELOG.md
- docs/CODEX_PROTOCOL.md
- docs/DATA_MODEL_DRAFT.md
- docs/SECURITY_MODEL.md

### Ergebnis

Die Systemdatenbank-Grundlage umfasst Workspaces, Connection-Profile, verschlüsselte Connection-Secrets, Schema-Snapshots, Tabellen- und Spaltennotizen, Mapping-Sets, Mapping-Felder, Value Rules und Audit Log. `SystemDatabase`, `MigrationRunner` und `EncryptionService` sind als Application-Services registriert. `bin/luna` stellt `db:test` und `migrate` bereit.

### Offene Punkte

- Echte Connection-Verwaltung, Schema Explorer, Mapping Designer, Jobs, Reports, Login, externe Datenbankverbindungen und UI-Speicherung bleiben Folge-Meilensteine.

---

## 2026-05-17 — Meilenstein 0.7.0 Connection Manager und Schema Explorer

### Ziel

Externe Datenquellen über die Luna Admin-UI verwalten und analysieren.

### Aufgabe

Connection Manager, verschlüsselte Speicherung externer Secrets, Verbindungstest für MySQL/MariaDB, Schemaanalyse über `information_schema`, Beispieldatenanzeige und Luna-Kommentare für Tabellen und Spalten implementieren. Keine Mapping-, Transfer-, Job-, Report- oder externe API-Logik umsetzen.

### Geänderte Dateien

- routes/web.php
- src/Core/Application.php
- src/Http/Request.php
- src/Routing/Route.php
- src/Routing/Router.php
- src/Repository/WorkspaceRepository.php
- src/Repository/ConnectionProfileRepository.php
- src/Repository/SchemaMetadataRepository.php
- src/Connections/ExternalDatabaseConfig.php
- src/Connections/ExternalPdoConnectionFactory.php
- src/Connections/ConnectionTester.php
- src/Schema/SchemaInspector.php
- src/Schema/TableSchema.php
- src/Schema/ColumnSchema.php
- src/Schema/SampleDataReader.php
- resources/views/admin/connections/index.php
- resources/views/admin/connections/create.php
- resources/views/admin/connections/show.php
- resources/views/admin/schema/index.php
- resources/views/admin/schema/table.php
- CHANGELOG.md
- docs/CODEX_PROTOCOL.md
- docs/SECURITY_MODEL.md
- docs/DATA_MODEL_DRAFT.md

### Ergebnis

Connections können über die Admin UI angelegt, verschlüsselt gespeichert und angezeigt werden, ohne Secrets offenzulegen. Dynamische Routenparameter sind im bestehenden Router verfügbar. Schema Explorer kann aktive Connections listen, Tabellen und Spalten aus `information_schema` lesen, begrenzte Beispieldaten anzeigen und Luna-eigene Tabellen-/Spaltenkommentare speichern.

### Offene Punkte

- Kein Login/Auth, kein Mapping Designer, keine Transfers, keine Jobs, keine Reports und keine API-Endpunkte für externe Daten.

---

## 2026-05-17 — Meilenstein 0.8.0 Mapping Designer

### Ziel

Datenflüsse und Feldzuordnungen visuell verwalten.

### Aufgabe

Mapping Designer für Mapping Sets, Source-/Target-Auswahl, Feldzuordnungen, Transformationsarten, Value Mapping, Validierung und Audit-Log-Einträge implementieren. Keine Transfers, Jobs, Reports, produktiven Zieltabellenänderungen oder Mapping-Ausführung umsetzen.

### Geänderte Dateien

- routes/web.php
- src/Core/Application.php
- src/Repository/MappingRepository.php
- src/Repository/AuditLogRepository.php
- src/Mapping/MappingValidator.php
- src/Mapping/MappingDraft.php
- src/Mapping/MappingValidationResult.php
- src/Mapping/TransformType.php
- resources/views/admin/mappings/index.php
- resources/views/admin/mappings/create.php
- resources/views/admin/mappings/show.php
- resources/views/admin/mappings/fields.php
- resources/views/admin/mappings/value-rules.php
- resources/views/admin/mappings/validation.php
- CHANGELOG.md
- docs/CODEX_PROTOCOL.md
- docs/DATA_MODEL_DRAFT.md
- docs/SECURITY_MODEL.md

### Ergebnis

Mapping Sets sind workspace-bezogen speicherbar. Mapping Fields und Value Rules werden in den bestehenden Luna-Systemtabellen persistiert. Änderungen an Mapping Sets, Feldern, Value Rules und Validierungsläufen werden in `luna_audit_log` protokolliert. Die Validierung liest externe Schemas nur lesend und gibt Fehler, Warnungen und Infos ohne Secrets aus.

### Offene Punkte

- Keine Transferausführung, keine Jobs, keine Reports, kein Login/Auth-System, keine API-Endpunkte zur Mapping-Ausführung und keine automatische KI-Mappinglogik.

---

## 2026-05-18 — Meilenstein 0.9.0 Jobs, Transfers und Reports

### Ziel

Mappings ausführbar machen und Ergebnisse nachvollziehbar berichten.

### Aufgabe

Job- und Report-Tabellen, Job Runner, Dry Run, kontrollierte INSERT-Transfers, Job-Run-Logs, Report-Erzeugung, Mailversand-Kapselung, CLI-Befehle und Admin-UI für Jobs/Runs/Reports implementieren.

### Geänderte Dateien

- database/migrations/2026_05_17_000002_create_luna_jobs_and_reports_tables.sql
- src/Repository/JobRepository.php
- src/Repository/JobRunRepository.php
- src/Repository/ReportRepository.php
- src/Transfer/*
- src/Jobs/*
- src/Reports/*
- resources/views/admin/jobs/*
- resources/views/admin/reports/*
- bin/luna
- routes/web.php
- src/Core/Application.php
- resources/views/admin/mappings/show.php
- .env.example
- CHANGELOG.md
- docs/CODEX_PROTOCOL.md
- docs/DATA_MODEL_DRAFT.md
- docs/SECURITY_MODEL.md

### Ergebnis

Jobs können angelegt und als Dry Run oder echter Transfer gestartet werden. Dry Runs schreiben nicht in Zielsysteme und speichern Preview-Daten begrenzt in der Run-Summary. Echte Transfers sind auf INSERT in die Target Connection beschränkt und werden blockiert, wenn die Target Connection read-only ist. Job Runs, Logs, Audit-Events und Reports werden persistiert.

### Offene Punkte

- Kein Queue-System, kein Cron-UI, kein Login/Auth, kein Webhook-System, kein Endpoint Builder, kein Upsert in 0.9.0 und keine automatische Zieltabellenerstellung.

---

## 2026-05-18 - Fix 0.9.0 Mapping-Transfer-Status und CLI-Ausgabe

### Ziel

Echte Mapping-Transfers duerfen nicht wie ein Erfolg wirken, wenn sie nur einen JobRun anlegen oder durch ein `read_only` Target blockiert werden.

### Aufgabe

Lokalen 0.9.0-Stand pruefen und die Ausgabe von `mapping:dry-run`, `mapping:run --force` und `job:run` so erweitern, dass finaler Status, Dry-Run-Flag, Zaehler und sichere Fehlermeldungen sichtbar sind. `mapping:run` ohne `--force` muss mit Exit Code 2 ablehnen. Read-only Targets muessen echte Transfers mit `failed`, `written_count = 0`, Log und Audit-Event blockieren.

### Geaenderte Dateien

- bin/luna
- src/Jobs/JobRunner.php
- src/Transfer/MappingExecutor.php
- src/Transfer/MappingExecutionResult.php
- src/Repository/JobRunRepository.php
- resources/views/admin/jobs/run.php
- CHANGELOG.md
- docs/CODEX_PROTOCOL.md
- docs/SECURITY_MODEL.md

### Ergebnis

Die CLI zeigt nach Mapping- und Job-Ausfuehrungen den gespeicherten Run-Status mit Source-, Transform-, Written-, Skipped- und Error-Zaehlern. Failed Runs geben eine sichere Message aus. Bei `read_only = 1` auf der Target Connection wird vor dem `TargetWriter` blockiert, der Run als `failed` gespeichert, `written_count` bleibt `0`, und `mapping.transfer.failed` wird auditiert.

### Tests

- `composer dump-autoload`
- `php -l bin/luna`
- `php -l` fuer geaenderte PHP-Dateien
- `php -l` fuer geaenderte Template-Dateien
- `php bin/luna db:test`
- CLI-Smoke-Tests fuer Dry Run, fehlendes `--force` und echten Transfer je nach lokaler Target-Konfiguration

### Offene Punkte

- Echte produktive Transfers bleiben nur gegen bewusst konfigurierte Target-/Transferdatenbanken zulaessig.

---

## 2026-05-19 - Meilenstein 1.0.0 Endpoint Builder und stabile Workbench

### Ziel

Eine stabile erste Workbench-Version fuer Integrationsprojekte von Datenquelle bis Transfer/Report und einfache API-Endpunkte bereitstellen.

### Aufgabe

Endpoint-Tabellen, Endpoint Repository, private Endpoint-Secrets, API Runtime, Admin UI, Audit-Ansicht, Version 1.0.0 und Betriebsdokumentation umsetzen. Bestehende 0.7/0.8/0.9-Flows nicht brechen.

### Geaenderte Dateien

- database/migrations/2026_05_17_000003_create_luna_endpoint_tables.sql
- src/Repository/EndpointRepository.php
- src/Api/EndpointAccessGuard.php
- src/Api/EndpointResponseBuilder.php
- src/Api/EndpointRuntime.php
- src/Core/AppVersion.php
- src/Core/Application.php
- src/Routing/Route.php
- routes/api.php
- routes/web.php
- resources/views/admin/endpoints/*
- resources/views/admin/audit/index.php
- resources/views/layouts/admin.php
- docs/OPERATIONS.md
- docs/DEPLOYMENT.md
- docs/API_ENDPOINTS.md
- docs/SECURITY_CHECKLIST.md
- ROADMAP.md
- CHANGELOG.md
- docs/SECURITY_MODEL.md
- docs/DATA_MODEL_DRAFT.md
- .env.example

### Ergebnis

Vorbereitet. Endpoint-Secrets werden getrennt von Connection-Secrets verschluesselt gespeichert. Private Runtime-Zugriffe pruefen `X-Luna-Endpoint-Secret`, Query-Secrets nur ausserhalb von production. `/api/version` und `version`-Endpoints geben `1.0.0` aus.

### Offene Punkte

- Lokale Smoke Tests und reale Transfer-Sicherheitshaerte haengen von vorhandener Testdatenbank und Testdaten ab.

---

## 2026-05-20 - Meilenstein 1.1.0 Workbench UX, Workspaces und Mapping-Auswahl

### Ziel

Die Luna V3 Workbench in der täglichen Benutzung sauberer, schneller und weniger fehleranfällig machen.

### Aufgabe

Dark/Light Theme-Switch mit Cookie, schwarze Glasoptik, Workspace-Create/Edit und dynamische Mapping-Tabellenauswahl aus vorhandenen Connections umsetzen. Keine neuen Dependencies und keine Änderungen an Transfers, Endpoint Runtime oder Secrets.

### Geänderte Dateien

- src/Core/AppVersion.php
- src/Repository/WorkspaceRepository.php
- routes/web.php
- resources/views/layouts/admin.php
- resources/views/admin/workspaces.php
- resources/views/admin/workspaces/*
- resources/views/admin/mappings/create.php
- resources/views/admin/mappings/show.php
- public/assets/css/admin.css
- public/assets/js/theme.js
- public/assets/js/mapping-tables.js
- CHANGELOG.md
- ROADMAP.md
- docs/OPERATIONS.md
- docs/SECURITY_MODEL.md
- docs/CODEX_PROTOCOL.md

### Ergebnis

Vorbereitet. Das Admin UI nutzt standardmäßig `dark`, der Theme-Switch setzt `luna_theme=dark|light`, Workspaces sind über die UI anlegbar und bearbeitbar, und Mapping-Formulare laden Tabellen per `/admin/api/connection-tables?connection_id=ID`. Die bisherige Route `/admin/schema/{connectionId}/tables.json` bleibt als Kompatibilitätsroute erhalten. Beide JSON-Routen nutzen eine leichte Tabellenlisten-Logik, die für Dropdowns nur Tabellennamen lädt.

## 2026-05-24 - Hotfix 1.1.2 IPv4 Host Resolution fuer externe PDO-Verbindungen

### Aufgabe

IPv6-Fallback-Timeouts bei externen MySQL-Hosts vermeiden, ohne gespeicherte Connection-Hosts zu verändern oder Secrets offenzulegen.

### Ergebnis

`Luna\Network\HostResolver` gibt IP-Adressen unverändert zurück und löst Hostnamen für TCP-Verbindungen bevorzugt auf IPv4-A-Records auf. `ExternalPdoConnectionFactory` verwendet den aufgelösten Host nur intern für den PDO-DSN; `ExternalDatabaseConfig::host()` bleibt der gespeicherte Originalhost.

## 2026-05-24 - Meilenstein 1.2.0 Multi-Connection Integration Foundation

### Aufgabe

Mehrere externe Connections pro Workspace stabilisieren, Connection-Rollen und MySQL/MariaDB-Driver absichern, Source-Connections standardmäßig read-only behandeln und einen sicheren CLI-Verbindungstest bereitstellen.

### Ergebnis

`ConnectionProfileData` zentralisiert Rollen, Driver, Normalisierung und Validierung für Connection-Profile. Mehrere Connections können dieselbe `workspace_id` behalten, ohne Rollen oder Profile zu überschreiben. Source-Connections erhalten ohne explizite Read-only-Angabe den Read-only-Default. `php bin/luna connection:test <connection-id>` testet einzelne Connections mit sicherer Ausgabe ohne Passwort, DSN oder entschlüsselte Secrets. Schema- und Mapping-Tabellenlisten nutzen weiterhin `TableNameReader`; detaillierte Schema-Explorer-Ansichten nutzen weiterhin `SchemaInspector`.

### Ergänzung

Connections sind über `/admin/connections/{id}/edit` bearbeitbar. Das Passwortfeld bleibt leer, wenn das bestehende verschlüsselte Secret unverändert bleiben soll; nur ein neu eingegebenes Passwort wird als Secret-Payload gespeichert. Erfolgreiches Workspace-Anlegen redirectet zurück auf `/admin/workspaces`.

Mapping-Tabellenlisten liefern für Dropdowns `name` und `label`; das JavaScript setzt `option.value` aus `name` und den sichtbaren Text aus `label` mit Fallback auf `name`.

### Offene Punkte

- Browserbasierte Sichtprüfung des Theme-Switches und der dynamischen Selects bleibt bei fehlenden externen Testconnections manuell nachzuholen.

---

## 2026-05-25 - Meilenstein 1.3.0 Multi-Source Lookup Mapping und Value Resolver

### Ziel

Mappings sollen aus einer Primary Source und optional mehreren Lookup Sources einen normalisierten Transfer-Datensatz erzeugen.

### Aufgabe

Feldtypen `source_column`, `static_value` und `lookup_value` implementieren, Lookup-Key-Templates aus Source- und Transfer-Kontext rendern, Lookup-Werte ueber weitere Connections aufloesen, Fallback-/Fehlerverhalten vorbereiten und die Dry-Run-Vorschau um Primary-Source-Werte, Resolver-Ereignisse und Transfer-Datensatz erweitern.

### Geaenderte Dateien

- database/migrations/2026_05_25_000004_add_lookup_mapping_fields.sql
- src/Mapping/*
- src/Transfer/*
- src/Repository/MappingRepository.php
- src/Core/Application.php
- routes/web.php
- resources/views/admin/mappings/*
- tests/Unit/LookupMappingResolverTest.php
- ROADMAP.md
- CHANGELOG.md
- docs/CODEX_PROTOCOL.md
- docs/DATA_MODEL_DRAFT.md
- docs/SECURITY_MODEL.md

### Ergebnis

Vorbereitet. Lookup Mapping nutzt separate Resolver-Services und einen `LookupValueProvider`, sodass mehrere Lookup-Felder dieselbe oder unterschiedliche Connections verwenden koennen. Dry Runs zeigen weiterhin die bestehenden Preview Rows und zusaetzlich `primary_source_preview`, `transfer_preview` und sichere Resolver-Events.

### Offene Punkte

- Browserbasierter Dry-Run mit echten AsfInStockRings-Daten bleibt manuell zu pruefen.

---

## 2026-05-27 - 1.3.0 Feldzuordnung: Transfer-Feldliste statt DB-Spalten

### Aufgabe

Transfer-Feld in der Feldzuordnungsmaske fachlich von Target- und Lookup-Tabellenspalten trennen. `target_column` bleibt technische Persistenzspalte, wird in der UI aber als Mapping-eigenes Transfer-Feld behandelt.

### Geaenderte Dateien

- routes/web.php
- resources/views/admin/mappings/fields.php
- public/assets/js/mapping-tables.js
- src/Mapping/MappingValidator.php
- CHANGELOG.md
- ROADMAP.md
- docs/CODEX_PROTOCOL.md

### Ergebnis

`Transfer-Feld` ist ein Select aus einer minimalen Mapping-Transferfeldliste (`name`, `price_group`, `price`, `pseudo_price`, `product_name`) plus bereits gespeicherten `target_column`-Werten des Mappings. Lookup Key Column und Lookup Value Column werden per Lookup Connection und Lookup Tabelle aus den Lookup-Tabellenspalten geladen. Die Validator-Pruefung vergleicht `target_column` nicht mehr mit Target-DB-Spalten.

---

## 2026-05-28 - 1.3.0 Lookup Mapping UI fachlich korrigiert

### Aufgabe

Die Feldzuordnungsmaske soll eine Lookup-Regel mit echten Source- und Lookup-Selects testbar machen. Hartcodierte Transfer-Feld-Vorschlaege duerfen nicht mehr angezeigt werden.

### Geaenderte Dateien

- routes/web.php
- resources/views/admin/mappings/fields.php
- public/assets/js/mapping-tables.js
- CHANGELOG.md
- docs/CODEX_PROTOCOL.md

### Ergebnis

Die Maske nutzt jetzt einen Source-Filter mit `numerisch > 0`, zeigt maximal 10 passende Primary-Source-Zeilen, laedt Lookup-Tabellen als Select pro Lookup Connection, laedt Lookup Key/Value Columns aus der gewaehlten Lookup-Tabelle und zeigt einen Lookup-Test fuer die aktuellen Beispielzeilen. `target_column` bleibt technisch erhalten, wird in der UI aber nur noch als optionaler Ausgabe-Alias verwendet; leer wird beim Speichern `resolved_value` verwendet.

---

## 2026-05-28 - 1.4.0 JSON Endpoint Builder v2

### Aufgabe

Mapping-gebundene JSON-Endpoints mit Workspace-Bindung, Public Runtime, Secret-Modus, standardisiertem JSON-Format und Admin-Preview umsetzen.

### Geaenderte Dateien

- database/migrations/2026_05_28_000007_add_endpoint_builder_v2_fields.sql
- src/Api/EndpointJsonResponseFactory.php
- src/Api/EndpointSecretPolicy.php
- src/Api/EndpointRunner.php
- src/Api/EndpointAccessGuard.php
- src/Api/EndpointResponseBuilder.php
- src/Api/EndpointRuntime.php
- src/Core/Application.php
- src/Repository/EndpointRepository.php
- routes/api.php
- routes/web.php
- resources/views/admin/endpoints/*
- public/assets/js/mapping-tables.js
- tests/Unit/EndpointBuilderV2Test.php
- CHANGELOG.md
- docs/CODEX_PROTOCOL.md
- docs/DATA_MODEL_DRAFT.md
- docs/SECURITY_MODEL.md

### Ergebnis

Umgesetzt. Endpoints nutzen `source_type = mapping`, sind an Workspace und Mapping gebunden und liefern unter `/api/endpoints/{slug}` ein JSON-Envelope mit `success`, `generated_at`, `count` und `items`. Fehler werden standardisiert ohne Stacktraces oder Secrets ausgegeben. Cache-Felder sind persistiert und in der UI vorbereitet, aber noch nicht als Cache-Layer aktiv.

### Ergänzung: Admin Delete Buttons

Workspaces, Connections, Mappings und Endpoints haben POST-basierte Löschaktionen mit Browser-Bestätigung und serverseitiger Abhängigkeitsprüfung erhalten. Workspaces werden nur leer gelöscht, Connections nur ohne Mapping-/Schema-Abhängigkeiten, Mappings nur ohne Endpoint-/Job-Verweise. Endpoints können gelöscht werden, ohne Mappings oder Workspaces zu entfernen. PHPUnit deckt die Löschregeln und POST-only-Routen ab.
---

## 2026-05-29 - 1.4.0 JSON Endpoint Mapping Designer erweitert

### Aufgabe

JSON-Endpoint-Mappings sollen ohne Target Connection gespeichert werden können und Lookup-/Enrichment-Connections pro Mapping-Regel verwenden.

### Ergebnis

Mappings unterstützen `mapping_mode = transfer` und `mapping_mode = json_endpoint`. Transfer-Mappings verlangen weiterhin Source und Target; JSON-Endpoint-Mappings verlangen nur die Primary Source und laufen im Endpoint-Kontext read-only. Lookup-Felder speichern ihre Lookup Connection pro Regel. Der Transform `key_value_map_by_prefix` rendert ein Prefix-Template, sucht Lookup-Keys per Prefix und entfernt den Prefix aus den Ausgabe-Keys. Public Endpoints verwenden `output_rows`, damit die Runtime nicht still auf 20 Preview-Zeilen limitiert.

### Ergänzung: zentraler Source-Filter

Source-Filter werden am Mapping gespeichert und über `MappingSourceRowProvider` angewendet. Die UI-Preview nutzt denselben Provider wie `MappingExecutor`; dadurch verwenden CLI-Dry-Run, Endpoint Preview und Public Runtime denselben gefilterten Source-Row-Satz. Numerische Filter schließen `NULL`, leere Werte, nichtnumerische Werte und `0` bei `numeric_gt 0` aus.

### Ergänzung: Source Filter Builder

Source-Filter sind als eigene Mapping-weite Liste in `luna_mapping_source_filters` gespeichert. Der Mapping Designer zeigt mehrere Filterzeilen mit Source Column, Operator und Wert; alle Filter werden per AND kombiniert. `MappingSourceRowProvider` unterstützt Text-, Numeric- und Listenoperatoren und liest alte `source_filter_*`-Felder weiter als Fallback, wenn noch keine Filterliste gespeichert ist.

### Ergänzung: Prefix-Lookup-Warmup

`key_value_map_by_prefix` sammelt vor der Row-Transformation alle gerenderten Prefixe pro Mapping-Lauf und lädt sie über `PrefixLookupWarmupProvider` gebündelt vor. `PdoLookupValueProvider` führt dafür Batch-Queries mit eindeutigen Prefix-Parametern und Chunking aus, gruppiert Treffer danach wieder nach Prefix und legt die Ergebnisse im Prefix-Cache ab. `lookupByPrefix()` bleibt als Fallback kompatibel, liest nach einem Warmup aber ohne weitere SQL-Abfrage aus dem Cache.

---

## 2026-05-30 - 1.5.0 Endpoint Export Runtime

### Aufgabe

Bestehende JSON Endpoints als eigenständige Runtime exportieren, damit produktive Consumer nur einen HTTP-JSON-Endpunkt benötigen und keine öffentliche Luna-Workbench betrieben werden muss.

### Geänderte Dateien

- bin/luna
- src/Core/Application.php
- src/Export/EndpointExportArchiveService.php
- src/Export/EndpointRuntimeExporter.php
- tests/Unit/EndpointRuntimeExporterTest.php
- CHANGELOG.md
- docs/CODEX_PROTOCOL.md
- docs/SECURITY_MODEL.md

### Ergebnis

Der CLI-Befehl `php bin/luna endpoint:export <endpoint-key> --target=<directory> [--force]` erzeugt ein deploybares Paket mit `api/{endpoint}.php`, eigenem Runtime-Bootstrap, Runtime-Klassen, `config/endpoint.{endpoint}.php`, `.env.example`, `.htaccess`-Schutzdateien und `manifest.json`. Die exportierte Runtime enthält keine Admin-Routen oder Admin-Templates und referenziert Connection- und Endpoint-Secrets ausschließlich über Env-Variablen. PHPUnit deckt Exportprofil, Manifest, Secret-Ausschluss, `.env.example`, Runtime-Erfolgsausgabe, Runtime-Fehlerformat und Secret-Prüfung ab.

### Ergänzung: lokales Runtime-Env

Der Export-Befehl unterstützt `--local-env`. Standardexporte schreiben weiterhin keine echte `.env`; mit `--local-env` wird zusätzlich eine lokale `.env` aus den vorhandenen Luna-Connection-Profilen und Secret-Stores erzeugt. Klartext-Secrets landen dabei ausschließlich in `.env`, nicht in PHP-Konfiguration, Manifest oder CLI-Ausgabe. `.gitignore` schützt `public/pim/.env`, `storage/exports/*/.env` und `storage/exports/**/*.env`.

### Ergänzung: Admin-Export und Workspace-Storage

Endpoint Runtime Exporte können über die Endpoint-Detailseite per POST gestartet werden. Der Standardpfad wird aus Workspace-Slug und Endpoint-Key berechnet: `storage/{workspace_slug}/exports/endpoints/{endpoint_key}/`. Der CLI-Befehl nutzt denselben Pfad, wenn kein `--target` angegeben ist; `--target` bleibt als Dev-/Expertenoption erhalten. Die Detailseite zeigt an, ob ein Export vorhanden ist, den Exportpfad und `exported_at` aus dem Manifest.

### Ergänzung: ZIP-Archiv und Download

Admin-Endpoint-Exporte erzeugen nach dem Ordnerexport automatisch ein ZIP-Archiv neben dem Endpoint-Ordner, z. B. `storage/asfinstocks/exports/endpoints/asfinstocks-isr_prices-runtime.zip`. Die Endpoint-Detailseite bietet einen berechneten Download-Link über `/admin/endpoints/{id}/export/download`; die Route akzeptiert keine freien Dateipfade. Der CLI-Befehl unterstützt `--zip` für denselben Archivschritt. `EndpointExportArchiveService` nimmt Runtime-Dateien, API-Datei, Config, `.env.example`, Manifest und `.htaccess` auf, schließt aber echte `.env`, alte ZIPs, Logs, temporäre Dateien, VCS-/IDE-Ordner, `node_modules` und `vendor` aus.

---

## 2026-05-31 - 1.6.0 Canonical Stock Model Transform

### Aufgabe

ISR-Sondermodelle sollen ohne Datenbankänderung und ohne ISR-Hardcoding einen lagerkompatiblen Modellschlüssel berechnen können: `stock_model = old_name`, wenn `old_name` gefüllt ist, sonst `customfield_asf_model`.

### Geänderte Dateien

- src/Mapping/TransformType.php
- src/Mapping/MappingFieldResolver.php
- src/Transfer/MappingRowTransformer.php
- src/Export/EndpointRuntimeExporter.php
- resources/views/admin/mappings/fields.php
- routes/web.php
- tests/Unit/LookupMappingResolverTest.php
- tests/Unit/EndpointRuntimeExporterTest.php
- CHANGELOG.md
- docs/CODEX_PROTOCOL.md

### Ergebnis

Der neue Transform `first_non_empty` liest eine kommaseparierte `source_column`-Liste in Reihenfolge und gibt den ersten nicht-leeren Wert zurück; `NULL`, leere Strings und Whitespace gelten als leer. Berechnete Output Fields stehen nachfolgenden Lookup-Key- und Prefix-Templates über den bestehenden Transfer-Row-Kontext zur Verfügung. Das Prefix-Warmup berechnet einfache vorgelagerte Felder wie `stock_model`, bevor Prefixe gesammelt werden, damit gebatchte Prefix-Lookups erhalten bleiben. Die exportierte Runtime unterstützt denselben Transform und dieselbe Template-Reihenfolge.

---

## 2026-06-02 - 1.6.0 Integration Module Foundation

### Aufgabe

Eine minimale Grundlage schaffen, damit ein fachlich definierter Export-Endpunkt wie `isr_prices` als eigenständiges, reproduzierbares Export-Modul beschrieben und geprüft werden kann. Keine generischen Shopware-/WooCommerce-/Afterbuy-Adapter und kein großes Plugin-System.

### Geänderte Dateien

- bin/luna
- src/Core/Application.php
- src/Export/EndpointExportArchiveService.php
- src/Integration/ExportManifest.php
- src/Integration/ExportModuleInterface.php
- src/Integration/ExportModuleRegistry.php
- src/Integration/ExportRuntimeBuilder.php
- src/Integration/Modules/IsrPricesExportModule.php
- tests/Unit/IntegrationExportModuleTest.php
- CHANGELOG.md
- docs/CODEX_PROTOCOL.md

### Ergebnis

`isr_prices` ist als erstes Export-Modul registriert. Das Modul beschreibt Zweck, Version, Endpoint-Key, Runtime-Dateien, ausgeschlossene Dateien, Dateien/Werte, die niemals exportiert werden dürfen, und eine Secret-Policy mit `exports_secrets = false`. Der CLI-Befehl `php bin/luna integration:export isr_prices --dry-run` gibt ein nachvollziehbares Manifest aus; `--zip` markiert im Dry-Run den geplanten Archivschritt. Reale Modul-Exporte nutzen weiterhin die bestehende Endpoint Export Runtime und schreiben zusätzlich `module.isr_prices.manifest.json`. Die ZIP-Erzeugung sortiert die Dateiliste vor dem Schreiben und schließt lokale `.env.*`-Dateien außer `.env.example` aus.

### Ergänzung: fachliche Gegenprüfung

Der Integration-Export-Dry-Run zeigt jetzt zusätzlich `included_files`, `excluded_files`, `validation` und `warnings`. Die Validierung prüft Modulname, Modulmanifest, Vollständigkeit der Runtime-Dateien, aktive Secret-Policy, verbotene Dateien wie `.env`, `.git`, `.idea` und `.phpunit.cache`, lokale absolute Pfade und nicht-leere Secret-Zuweisungen. Echte Exporte schreiben das Validierungsergebnis in `module.isr_prices.manifest.json`. PHPUnit vergleicht den internen JSON-Envelope für die ISR-Fixture mit dem Payload der exportierten Runtime, damit der exportierte Stand fachlich gegen den bestehenden Endpoint-Payload abgesichert ist. Für Produktivdaten bleibt die manuelle Gegenprüfung über Endpoint-Abruf dokumentiert, weil externe Datenquellen nicht in Unit-Tests genutzt werden.

---

## 2026-06-02 - 1.7.0 isr_prices Deployment Runtime

### Ziel

`isr_prices` als reproduzierbares Deployment-Paket mit Manifest, Checksums, Deploy-Doku, Healthcheck und secretfreiem Export absichern.

### Geaenderte Dateien

- src/Integration/ExportRuntimeBuilder.php
- src/Integration/Modules/IsrPricesExportModule.php
- src/Export/EndpointRuntimeExporter.php
- src/Export/EndpointExportArchiveService.php
- tests/Unit/IntegrationExportModuleTest.php
- tests/Unit/EndpointRuntimeExporterTest.php
- CHANGELOG.md
- docs/CODEX_PROTOCOL.md

### Ergebnis

Integration-Export erzeugt `README_DEPLOY.md`, `config/config.example.php`, `CHECKSUMS.txt` und ein 1.7.0-Manifest mit `source_commit`, Secret-Policy, Checksums und Deployment-Metadaten. Die exportierte Runtime stellt `?health=1` bereit und gibt Production-Fehler als generisches JSON ohne Stacktraces, lokale Pfade oder Secrets aus.

### Offene Punkte

Keine. Deployment auf Zielsystem und echte Produktivdaten-Gegenpruefung bleiben Betriebsaufgaben.

### Commit-Hash

Noch nicht committed.

---

## 2026-06-06 - v2.4.0 Trigger Layer

### Ziel

Bestehende Prozesse aus v2.3.0 über definierte Trigger starten, verwalten und nachvollziehbar protokollieren, ohne Fachlogik in Trigger zu verschieben.

### Geänderte Dateien

- database/migrations/2026_06_06_000017_create_process_triggers.sql
- src/Core/Application.php
- src/Process/ProcessRunner.php
- src/Process/ProcessTriggerException.php
- src/Process/ProcessTriggerRunner.php
- src/Process/ProcessTriggerService.php
- src/Process/TriggerConfigValidator.php
- src/Process/TriggerUrlBuilder.php
- src/Repository/ProcessRepository.php
- src/Repository/ProcessRunRepository.php
- src/Repository/ProcessTriggerRepository.php
- routes/api.php
- routes/web.php
- bin/luna
- resources/views/admin/processes/index.php
- resources/views/admin/processes/show.php
- resources/views/admin/processes/run.php
- tests/Unit/ProcessRuntimeTest.php
- tests/Unit/ProcessTriggerLayerTest.php
- CHANGELOG.md
- docs/ROADMAP.md
- docs/CODEX_PROTOCOL.md

### Ergebnis

`luna_process_triggers` speichert Prozess-Trigger mit Typ, Key, Aktiv-Status, JSON-Konfiguration, Secret-Hash und letztem Start. Prozessläufe speichern `trigger_id`, `trigger_source` und sichere Request-Metadaten ohne Authorization-Header, Cookies oder vollständige Payloads. Die Admin-UI verwaltet Trigger pro Prozess, zeigt CLI-/API-/Webhook-URLs über Deployment Targets und erlaubt manuelle Ausführung für sinnvolle Trigger. API- und Webhook-Routen lösen generisch Prozesse aus und enthalten keine WooCommerce-Fachverarbeitung. `process:run <process-id>` bleibt kompatibel und unterstützt zusätzlich `--trigger=<trigger-id-or-key>`; `trigger:list` und `trigger:run` wurden ergänzt.

### Offene Punkte

Produktive Scheduler-/Cron-Runtime und fachliche WooCommerce-/Afterbuy-/ERP-Adapter bleiben bewusst spätere Meilensteine.

### Commit-Hash

Noch nicht committed.

### Abschlussprüfung

UI-Umlautprüfung: durchgeführt

---

## 2026-06-06 - v2.5.0 Adapter / Target Actions Foundation

### Ziel

Prozesse sollen generische Target Actions als Prozess-Schritte ausführen können, ohne Trigger-Logik, WooCommerce-Sonderlogik oder freie Code-/SQL-Ausführung in den Core zu verschieben.

### Geänderte Dateien

- database/migrations/2026_06_06_000018_create_target_actions.sql
- src/Core/Application.php
- src/Process/ProcessRunner.php
- src/Process/ProcessStepContextAwareRunnerInterface.php
- src/Process/TargetActionStepRunner.php
- src/Repository/ProcessRepository.php
- src/Repository/TargetActionRepository.php
- src/TargetAction/NativeTargetActionHttpClient.php
- src/TargetAction/TargetActionConfigValidator.php
- src/TargetAction/TargetActionExecutor.php
- src/TargetAction/TargetActionHttpClientInterface.php
- routes/web.php
- resources/views/admin/target-actions/index.php
- resources/views/admin/target-actions/show.php
- resources/views/admin/processes/show.php
- resources/views/layouts/admin.php
- tests/Unit/TargetActionFoundationTest.php
- CHANGELOG.md
- ROADMAP.md
- docs/ROADMAP.md
- docs/CODEX_PROTOCOL.md

### Ergebnis

`luna_target_actions` speichert workspace-bezogene Target Actions mit Key, Typ, Aktiv-Status und JSON-Konfiguration. Prozesse können Steps vom Typ `target_action` referenzieren. Der neue Step-Runner nutzt einen einfachen Step-Kontext mit `previous_result` und `step_results`. HTTP Actions unterstützen Dry-Run ohne echten Request, File Export schreibt nur unter `storage/`, Database Insert/Upsert nutzt validierte Identifier und Prepared Statements statt freier SQL-Strings. Admin-UI für Target Actions und Prozess-Step-Auswahl wurde ergänzt. Bestehende Prozess-, Trigger-, Endpoint- und WooCommerce-CLI-Kommandos bleiben registriert.

### Offene Punkte

Produktive systemspezifische Adapter für WooCommerce, Afterbuy, ERP und Amazon sowie Schema Registry/Validation bleiben spätere Meilensteine. Retry wird konfigurierbar vorbereitet, aber noch nicht als Backoff-Runtime ausgeführt.

### Commit-Hash

Noch nicht committed.

### Abschlussprüfung

UI-Umlautprüfung: durchgeführt

---

## 2026-06-08 - v2.6.0 Schema Registry & Validation

### Ziel

Schemas sollen als versionierte, workspace-bezogene Artefakte verwaltet, validiert und in Endpoint-Exportpaketen referenziert werden können, ohne vollständige OpenAPI-/JSON-Schema-Komplettabdeckung zu erzwingen.

### Geänderte Dateien

- database/migrations/2026_06_08_000019_create_schema_registry.sql
- src/Core/Application.php
- src/Export/EndpointExportContractService.php
- src/Process/SchemaValidationStepRunner.php
- src/Repository/EndpointRepository.php
- src/Repository/ProcessRepository.php
- src/Repository/SchemaRegistryRepository.php
- src/Schema/SchemaDefinitionValidator.php
- src/Schema/SchemaValidator.php
- routes/web.php
- bin/luna
- resources/views/admin/schemas/index.php
- resources/views/admin/schemas/show.php
- resources/views/admin/endpoints/_form.php
- resources/views/admin/endpoints/show.php
- resources/views/admin/processes/show.php
- resources/views/layouts/admin.php
- tests/Unit/SchemaRegistryValidationTest.php
- CHANGELOG.md
- ROADMAP.md
- docs/ROADMAP.md
- docs/CODEX_PROTOCOL.md

### Ergebnis

`luna_schemas` und `luna_schema_revisions` speichern Schema Key, Version, Status, Definition, Beispiel und Änderungshistorie je Workspace. Der Validator prüft Root-Typen, Pflichtfelder, primitive Typen, verschachtelte Objekte, Arrays und `additional_properties` mit nachvollziehbaren Fehlerpfaden. Endpoints können optional ein Registry-Schema referenzieren; Exportpakete enthalten dann eine Schema-Referenz und das Registry-Schema in `schema.json`. Prozesse können über den Step-Typ `schema_validation` vorherige Step-Ergebnisse validieren. Die Admin-UI verwaltet Schemas und validiert Beispiel-/Payload-JSON. `schema:validate <schema-id> <json-file>` ergänzt die CLI, ohne bestehende Kommandos zu entfernen.

### Offene Punkte

Vollständige OpenAPI-/JSON-Schema-Abdeckung, WooCommerce-Fachlogik, externe Adapter und produktive Scheduler-Erweiterungen bleiben spätere Meilensteine.

### Commit-Hash

Noch nicht committed.

### Abschlussprüfung

UI-Umlautprüfung: durchgeführt
---

## 2026-06-08 - v2.7.0 WooCommerce Runtime Module

### Ziel

WooCommerce-Webhooks sollen Luna als konkrete Runtime-Modul-Anwendung aufrufen können, ohne die generische Trigger-/Process-/Adapter-/Schema-Architektur zu ersetzen.

### Geänderte Dateien

- database/migrations/2026_06_08_000020_create_woocommerce_runtime_events.sql
- src/Core/Application.php
- src/Process/ProcessTriggerRunner.php
- src/Process/TriggerUrlBuilder.php
- src/Repository/ProcessTriggerRepository.php
- src/Repository/WooCommerceRuntimeEventRepository.php
- src/WooCommerce/WooCommerceRuntimeWebhookHandler.php
- src/WooCommerce/WooCommerceWebhookEventNormalizer.php
- src/WooCommerce/WooCommerceWebhookSignatureVerifier.php
- routes/api.php
- resources/views/admin/processes/show.php
- resources/views/admin/processes/run.php
- tests/Unit/WooCommerceRuntimeModuleTest.php
- docs/WOOCOMMERCE_RUNTIME.md
- CHANGELOG.md
- ROADMAP.md
- docs/ROADMAP.md
- docs/CODEX_PROTOCOL.md

### Ergebnis

WooCommerce-Webhooks laufen über `POST /api/webhooks/woocommerce/{trigger_key}`. Der Runtime-Handler lädt aktive Webhook-Trigger mit `provider=woocommerce`, prüft die WooCommerce-HMAC-Signatur über den rohen Request Body, normalisiert Topic/Event/Delivery-ID/Source-Domain und speichert ein internes `luna_woocommerce_runtime_events`-Event. Valide Webhooks starten einen normalen Process Run über den bestehenden Trigger Runner; der Run erhält WooCommerce-Metadaten, Payload-Referenz und sanitizte Payload Summary. Die UI zeigt kopierbare WooCommerce-Delivery-URLs aus Deployment Targets und Run-Details mit Signaturstatus. Trigger-Secrets bleiben verschlüsselt oder gehasht und werden nicht in Logs oder Payload Summaries ausgegeben.

### Offene Punkte

WooCommerce-Schreibaktionen, vollständiger Sync, Afterbuy-/ERP-/Amazon-Adapter, PRO-/Lizenzlogik und Scheduler-Ausbau bleiben bewusst spätere Meilensteine.

### Commit-Hash

Noch nicht committed.

### Abschlussprüfung

UI-Umlautprüfung: durchgeführt

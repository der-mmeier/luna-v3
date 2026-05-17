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

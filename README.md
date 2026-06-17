# Luna V3

> **Idee – Planung – Umsetzung**

Luna V3 ist eine webbasierte PHP-8.2+-Workbench für Integrationsprojekte. Die Anwendung verwaltet Workspaces, externe Datenbankverbindungen, Schema-Metadaten, Mappings, Datasets, Transfers, Prozesse, Trigger, Target Actions, Jobs, Reports, Endpoints, JSON-Schemas, Deployment Targets und Audit-Einträge.

**Dokumentationsstand:** v2.7.3 – Connection Workspace Sharing

---

## Inhaltsverzeichnis

1. [Grundverständnis](#1-grundverständnis)
2. [Welche Datenbank hat welche Aufgabe?](#2-welche-datenbank-hat-welche-aufgabe)
3. [Was passiert beim Löschen aller Luna-Datenbanken?](#3-was-passiert-beim-löschen-aller-luna-datenbanken)
4. [Neuinstallation von GitHub](#4-neuinstallation-von-github)
5. [Luna vollständig leer neu aufbauen](#5-luna-vollständig-leer-neu-aufbauen)
6. [Empfohlene Reihenfolge für eine Integration](#6-empfohlene-reihenfolge-für-eine-integration)
7. [Menüpunkte im Detail](#7-menüpunkte-im-detail)
8. [Durchgängiges einfaches Beispiel](#8-durchgängiges-einfaches-beispiel)
9. [CLI-Kurzreferenz](#9-cli-kurzreferenz)
10. [Sicherheit und Betrieb](#10-sicherheit-und-betrieb)
11. [Fehlersuche](#11-fehlersuche)
12. [Aktuelle Grenzen von v2.7.3](#12-aktuelle-grenzen-von-v273)

---

# 1. Grundverständnis

Luna besteht aus mehreren klar getrennten Schichten:

```text
Externe Quelle
    ↓
Connection
    ↓
Schema Explorer
    ↓
Mapping
    ↓
Dataset
    ↓
Transfer / Process / Endpoint
    ↓
TransferDB oder externes Ziel
```

Zusätzlich können Prozesse über Trigger gestartet und mit Target Actions erweitert werden:

```text
Trigger
    ↓
Process
    ├── Mapping ausführen
    ├── Schema validieren
    └── Target Action ausführen
```

## Die wichtigsten Begriffe

### Workspace

Ein Workspace ist der fachliche Container einer Integration. Mappings, Datasets, Prozesse, Endpoints, Schemas und andere Konfigurationen gehören jeweils zu einem Workspace.

### Connection

Eine Connection beschreibt den Zugriff auf eine externe Datenbank. Sie enthält Host, Port, Datenbankname, Benutzername und verschlüsselt gespeicherte Zugangsdaten.

Seit v2.7.3 besitzt eine Connection:

- genau einen **Owner Workspace**
- optional mehrere **freigegebene Workspaces**

Eine Connection ist in einem Workspace verfügbar, wenn sie:

- diesem Workspace gehört, oder
- ausdrücklich für ihn freigegeben wurde.

### Luna-Systemdatenbank

Die Systemdatenbank speichert die Konfiguration von Luna. Dazu gehören unter anderem:

- Workspaces
- Connection-Definitionen
- verschlüsselte Connection-Secrets
- Mappings
- Jobs und Job Runs
- Reports
- Endpoints
- Prozesse, Schritte und Trigger
- Target Actions
- Schema Registry
- Deployment Targets
- Audit-Einträge

### TransferDB

Eine TransferDB ist eine separate Laufzeit-, Staging- und Transferdatenbank. Sie ist **nicht** die Luna-Systemdatenbank.

Luna legt dort ausschließlich eigene Tabellen mit dem Präfix `luna_` an.

### Exportpaket

Ein Exportpaket beschreibt einen Endpoint und seine Abhängigkeiten. Typischer Inhalt:

```text
manifest.json
endpoint.json
mapping.json
schema.json
checksums.json
README.md
```

Ein Exportpaket ist **kein Backup der Luna-Systemdatenbank** und rekonstruiert nicht automatisch Workspaces, Connections, Prozesse oder Jobs.

---

# 2. Welche Datenbank hat welche Aufgabe?

## 2.1 Luna-Systemdatenbank

Beispielname:

```text
luna_v3
```

Aufgabe:

- Luna-Konfiguration
- Admin-Daten
- verschlüsselte Secrets
- Laufhistorien
- Audit
- interne Metadaten

Die Tabellen werden durch folgende Migrationen erzeugt:

```powershell
php bin/luna migrate
```

## 2.2 TransferDB

Beispielname:

```text
luna_transfer
```

Aufgabe:

- Webhook Events
- Endpoint Snapshots
- normalisierte oder generische Transfer Records
- Transfer Runs und Transfer Logs
- spätere Runtime-Pakete

Mindestens verwaltete Tabellen:

```text
luna_transferdb_migrations
luna_webhook_events
luna_endpoint_snapshots
luna_endpoint_snapshot_records
luna_transfer_runs
luna_transfer_run_logs
luna_transfer_sources
luna_transfer_records
```

Die TransferDB wird über eine normale Luna-Connection mit Verwendung `TransferDB` oder `Mixed` eingerichtet.

## 2.3 Externe Quelldatenbank

Beispiele:

```text
Pimcore
WooCommerce HPOS
Warenwirtschaft
Lagerverwaltung
Preis-Datenbank
```

Luna erstellt diese Datenbanken nicht. Luna liest sie über Connections.

Quellverbindungen sollten grundsätzlich als **Read Only** angelegt werden.

## 2.4 Externe Zieldatenbank

Eine externe Zieldatenbank ist ein bewusst konfiguriertes Schreibziel für Transfers oder Target Actions.

Luna erstellt nicht automatisch das fachliche Datenmodell eines beliebigen Zielsystems. Die benötigten Zieltabellen müssen vorher vorhanden sein, sofern kein spezieller Luna-Mechanismus sie verwaltet.

---

# 3. Was passiert beim Löschen aller Luna-Datenbanken?

## 3.1 Absichtlich komplett leer beginnen

Wenn bewusst keine bestehenden Daten übernommen werden sollen, ist kein Datenexport nötig.

Nach dem Löschen der Luna-Systemdatenbank gehen aber alle in der UI angelegten Konfigurationen verloren:

- Workspaces
- Connections
- Mappings
- Datasets
- Transfers
- WooCommerce-Anbindungen
- Prozesse und Trigger
- Target Actions
- Jobs
- Reports
- Endpoints
- Schemas
- Deployment Targets
- Audit-Historie

Die Migrationen stellen nur die **Tabellenstruktur** wieder her. Sie erzeugen keine früheren Konfigurationen.

## 3.2 Was muss vorher gesichert werden?

### Kein Backup nötig

Kein Backup ist nötig, wenn wirklich alles bewusst leer neu aufgebaut werden soll.

### `.env` sichern, wenn bestehende Secrets erhalten bleiben sollen

Der `APP_KEY` entschlüsselt gespeicherte Secrets.

Wenn die bestehende Luna-Systemdatenbank erhalten oder aus einem Backup wiederhergestellt wird, muss derselbe `APP_KEY` weiterverwendet werden.

Wenn die Systemdatenbank vollständig gelöscht und leer neu aufgebaut wird, kann ein neuer `APP_KEY` erzeugt werden.

### Externe Datenbanken

Luna-Migrationen rekonstruieren keine externen Quell- oder Zieldatenbanken.

Werden diese ebenfalls gelöscht, brauchen sie eigene:

- Migrationen
- SQL-Dumps
- Installationsskripte
- Hersteller-Backups

## 3.3 Exportpakete sind keine System-Backups

Ordner unter `storage/.../exports/` ersetzen kein Backup der Luna-Systemdatenbank.

Sie enthalten technische Beschreibungen einer exportierten Integration, aber nicht zwingend:

- alle Workspaces
- alle Connection-Secrets
- alle Jobs
- alle Prozesse
- alle Audit-Einträge
- alle UI-Einstellungen

---

# 4. Neuinstallation von GitHub

## 4.1 Voraussetzungen

Benötigt werden:

- PHP 8.2 oder neuer
- Composer
- MariaDB oder MySQL
- Git
- PHP-Erweiterung `pdo`
- PHP-Erweiterung `pdo_mysql`
- PHP-Erweiterung `zip`
- PHP-Erweiterung `openssl`

Im Repository sind mindestens folgende Composer-Anforderungen definiert:

```text
php >= 8.2
ext-pdo
ext-zip
vlucas/phpdotenv ^5.6.3
```

Für Entwicklung zusätzlich:

```text
PHPStan
PHPUnit
```

## 4.2 Repository klonen

```powershell
cd C:\Users\Saito\PhpstormProjects
git clone https://github.com/der-mmeier/luna-v3.git
cd luna-v3
git checkout main
```

Der aktuelle `main`-Stand dokumentiert v2.7.3 als abgeschlossen.

## 4.3 Composer-Abhängigkeiten installieren

```powershell
composer install
```

Composer installiert unter anderem `vlucas/phpdotenv`.

### Warum wird `phpdotenv` benötigt?

Luna lädt beim Start die Datei `.env`, sofern sie existiert. `phpdotenv` überträgt die dort definierten Werte in die Laufzeitkonfiguration.

Ohne `vendor/` und ohne `composer install` kann Luna nicht korrekt gestartet werden.

Für eine normale Installation immer:

```powershell
composer install
```

Nicht als Standardinstallation verwenden:

```powershell
composer update
```

`composer update` kann Abhängigkeiten und `composer.lock` verändern. Es ist für gezielte Dependency-Aktualisierungen gedacht.

## 4.4 Leere Systemdatenbank anlegen

Beispiel:

```sql
CREATE DATABASE luna_v3
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
```

Optional mit eigenem DB-Benutzer:

```sql
CREATE USER 'luna'@'localhost' IDENTIFIED BY 'SICHERES_PASSWORT';

GRANT ALL PRIVILEGES
ON luna_v3.*
TO 'luna'@'localhost';

FLUSH PRIVILEGES;
```

## 4.5 `.env` anlegen

Unter Windows PowerShell:

```powershell
Copy-Item .env.example .env
```

Beispiel:

```dotenv
APP_NAME="Luna V3"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

APP_KEY=base64:HIER_DEN_GENERIERTEN_SCHLUESSEL_EINTRAGEN

LUNA_DB_HOST=127.0.0.1
LUNA_DB_PORT=3306
LUNA_DB_DATABASE=luna_v3
LUNA_DB_USERNAME=luna
LUNA_DB_PASSWORD=SICHERES_PASSWORT
LUNA_DB_CHARSET=utf8mb4

MAIL_HOST=127.0.0.1
MAIL_PORT=1025
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM_ADDRESS=
MAIL_FROM_NAME="Luna V3"

CRON_SECRET=
```

Die `.env` darf niemals committed werden.

## 4.6 `APP_KEY` erzeugen

PowerShell:

```powershell
php -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"
```

Die Ausgabe vollständig nach `APP_KEY=` kopieren.

Beispiel:

```dotenv
APP_KEY=base64:ABCDEF...
```

Wichtig:

- vor dem Speichern von Connection-Passwörtern setzen
- nach dem Speichern verschlüsselter Secrets nicht einfach ändern
- bei Verlust können bestehende Secrets nicht mehr entschlüsselt werden

## 4.7 Systemdatenbank prüfen

```powershell
php bin/luna db:test
```

Erwartung:

```text
Luna system database connection successful.
```

## 4.8 Migrationen ausführen

```powershell
php bin/luna migrate
```

Bei einer vollständig leeren Datenbank werden alle Migrationen ausgeführt.

Bei erneutem Aufruf:

```text
No pending migrations.
```

## 4.9 Qualitätssicherung

```powershell
composer check
```

Der Composer-Check führt aus:

- Autoload neu erzeugen
- PHPStan
- PHPUnit
- Composer Audit

## 4.10 Entwicklungsserver starten

Luna enthält einen Development Router.

```powershell
php -S localhost:8000 -t public public/dev-router.php
```

Danach:

```text
http://localhost:8000/admin
```

API-Test:

```powershell
curl http://localhost:8000/api/version
```

## 4.11 Produktiver Webserver

Der DocumentRoot muss auf den Ordner `public/` zeigen.

Nicht öffentlich ausliefern:

```text
.env
vendor/
storage/
database/
docs/
src/
```

In Produktion:

```dotenv
APP_ENV=production
APP_DEBUG=false
```

Die Admin-Oberfläche ist ohne zusätzliche Authentifizierung nur für lokale bzw. anderweitig abgesicherte Umgebungen geeignet.

---

# 5. Luna vollständig leer neu aufbauen

## 5.1 Systemdatenbank löschen und neu erstellen

```sql
DROP DATABASE luna_v3;

CREATE DATABASE luna_v3
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
```

Dann:

```powershell
php bin/luna db:test
php bin/luna migrate
```

Ergebnis:

- alle Luna-Systemtabellen wieder vorhanden
- keine Workspaces
- keine Connections
- keine Mappings
- keine alten Konfigurationen

## 5.2 TransferDB löschen und neu erstellen

```sql
DROP DATABASE luna_transfer;

CREATE DATABASE luna_transfer
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
```

Danach in Luna:

1. Workspace anlegen.
2. TransferDB-Connection anlegen.
3. Verbindung testen.
4. `Check TransferDB schema`.
5. `Install/setup TransferDB schema` oder `Migrate TransferDB schema`.
6. Prüfen, ob alle `luna_`-Tabellen vorhanden sind.

Alternativ per CLI, sobald die Connection in der Systemdatenbank angelegt ist:

```powershell
php bin/luna transferdb:test <connection-id>
php bin/luna transferdb:status <connection-id>
php bin/luna transferdb:migrate <connection-id>
```

## 5.3 Empfohlene Neuaufbau-Reihenfolge

```text
1. Systemdatenbank
2. .env und APP_KEY
3. Systemmigrationen
4. erster Workspace
5. Connections
6. TransferDB Setup
7. Schema Explorer
8. Mappings
9. Datasets
10. Transfers
11. Schemas
12. Endpoints
13. Deployment Targets
14. Prozesse und Trigger
15. Jobs und Reports
16. WooCommerce
```

---

# 6. Empfohlene Reihenfolge für eine Integration

Eine Integration sollte nicht bei einem Prozess oder Webhook beginnen.

Die empfohlene Reihenfolge lautet:

```text
Workspace
→ Connections
→ Schema Explorer
→ Mapping
→ Dataset
→ Schema Registry
→ Endpoint oder Transfer
→ Process
→ Trigger / Job
→ Report / Audit
```

## Beispiel

```text
Workspace: Produktintegration

Source Connection: Produktdatenbank
Target Connection: Zielsystem
TransferDB: Luna Staging

Mapping: Produkte normalisieren
Dataset: Normalisierte Produkte
Schema: product-list v1.0.0
Endpoint: product-list
Transfer: Normalisierte Produkte ins Ziel
Job: nächtlicher Dry Run / Transfer
```

---

# 7. Menüpunkte im Detail

# Dashboard

## Zweck

Das Dashboard zeigt einen Überblick über den Zustand der Workbench.

Typische Informationen:

- Anzahl Workspaces
- Connections
- Mappings
- Jobs
- letzte Runs
- Fehler oder Statushinweise

## Beachten

Das Dashboard ist keine Konfigurationsquelle. Änderungen erfolgen in den jeweiligen Fachbereichen.

## Beispiel

Nach einer frischen Installation sollte das Dashboard überwiegend leere Zähler anzeigen.

---

# Workspaces

## Zweck

Workspaces trennen Integrationsprojekte fachlich voneinander.

## Konfigurierbar

Typischerweise:

- Name
- Key oder Slug
- Beschreibung
- Status

## Muss beachtet werden

- Mappings, Datasets, Prozesse, Endpoints und Schemas sind workspacegebunden.
- Workspaces nicht beliebig für völlig unterschiedliche Projekte wiederverwenden.
- Ein Workspace kann nur gelöscht werden, wenn abhängige Einträge entfernt wurden.
- Fehlermeldungen nennen blockierende Einträge mit Namen.

## Einfaches Beispiel

```text
Name: Demo Produkte
Key: demo-products
Beschreibung: Einfache Produktintegration
Status: aktiv
```

---

# Connections

## Zweck

Connections verwalten Zugänge zu externen Datenbanken.

## Konfigurierbar

- Name
- Connection Key
- Driver
- Host
- Port
- Datenbank
- Benutzername
- Passwort
- Optionen als JSON
- Verwendung/Rolle
- Read Only
- Owner Workspace
- freigegebene Workspaces

## Verwendung

Typische Rollen:

- Quelle
- Ziel
- TransferDB
- Mixed

## Owner und Freigaben

Beispiel:

```text
Owner Workspace: Toolbox
Freigegeben für: Demo Produkte, WooCommerce
```

Dadurch bleibt die Connection eindeutig zugeordnet, kann aber in mehreren Workspaces verwendet werden.

## Muss beachtet werden

- Quellverbindungen möglichst `Read Only`.
- Schreibrechte nur für bewusste Ziel- oder Transferverbindungen.
- Secrets werden verschlüsselt gespeichert.
- Passwörter werden nach dem Speichern nicht wieder angezeigt.
- Eine Connection mit Shares oder Abhängigkeiten kann nicht gelöscht werden.
- Connection-Optionen dürfen keine unnötigen Secrets enthalten.

## Einfaches Beispiel – Source

```text
Name: Demo Source
Key: demo-source
Driver: mysql
Host: 127.0.0.1
Port: 3306
Database: luna_demo_source
Read Only: ja
Owner Workspace: Demo Produkte
```

## Einfaches Beispiel – TransferDB

```text
Name: Demo TransferDB
Key: demo-transferdb
Verwendung: TransferDB
Read Only: nein
Owner Workspace: Toolbox
Freigegeben für: Demo Produkte
```

---

# Schema Explorer

## Zweck

Der Schema Explorer untersucht reale Datenbanktabellen.

Er zeigt typischerweise:

- Tabellen
- Spalten
- Datentypen
- Indizes
- Primärschlüssel
- Beispieldaten
- Kommentare oder Metadaten

## Was kann man machen?

- Connection auswählen
- Tabelle auswählen
- Struktur prüfen
- Beispielwerte ansehen
- Grundlage für Mappings ermitteln

## Muss beachtet werden

- Schema Explorer ist nicht die Schema Registry.
- Er liest die Struktur externer Datenbanken.
- Quellverbindung sollte read-only sein.
- Beispieldaten können personenbezogene oder sensible Inhalte enthalten.

## Einfaches Beispiel

Connection:

```text
Demo Source
```

Tabelle:

```text
products
```

Zu prüfende Spalten:

```text
id
sku
name
price
active
```

---

# Mappings

## Zweck

Mappings transformieren Quellzeilen in eine definierte Zielstruktur.

## Konfigurierbar

- Workspace
- Source Connection
- Source Table
- optional Target Connection/Table
- Mapping-Modus
- Source Filter
- Mapping Fields
- Value Rules
- Lookups
- Zielspalten
- Feldtypen und Schema-Metadaten

## Typische Feldregeln

- direct
- static
- template
- first_non_empty
- lookup
- lookup pattern
- key-value-map

## Muss beachtet werden

- Erst Preview/Dry Run durchführen.
- Keine echte Übertragung starten, bevor die Ausgabe geprüft wurde.
- Lookup-Connections müssen im Workspace verfügbar oder freigegeben sein.
- Tabellen- und Spaltennamen müssen zur realen Datenbank passen.
- Mapping ist Transformation, nicht automatisch Transfer.

## Einfaches Beispiel

Quelle:

```text
products.id
products.sku
products.name
products.price
```

Ausgabe:

```text
external_id  ← id
sku          ← sku
title        ← name
amount       ← price
```

---

# Datasets

## Zweck

Ein Dataset macht ein bestehendes Ergebnis als benannte, wiederverwendbare Datenquelle verfügbar.

Ein Dataset kann beispielsweise auf einem Mapping oder Endpoint basieren.

## Was kann man machen?

- Dataset definieren
- Preview anzeigen
- Datensätze begrenzen
- Dataset als Quelle für Transfers verwenden

## Muss beachtet werden

- Dataset ist normalerweise lesend.
- Ein Dataset schreibt noch nichts.
- Fehler im zugrunde liegenden Mapping erscheinen auch im Dataset.
- Vor einem Transfer immer Dataset Preview prüfen.

## Einfaches Beispiel

```text
Name: demo_products
Workspace: Demo Produkte
Quelle: Mapping "Demo Product Mapping"
```

Preview:

```text
10 normalisierte Produktzeilen
```

---

# Transfers

## Zweck

Transfers schreiben ein Dataset kontrolliert in eine Zieldatenbank.

## Konfigurierbar

- Workspace
- Dataset
- Target Connection
- Target Table
- Zielgruppen oder Child-Gruppen
- Feldzuordnung
- Transfermodus
- Upsert Keys
- Batch-Größe

## Typische Modi

- Insert
- Upsert
- Dry Run

## Muss beachtet werden

- Zielverbindung darf nicht read-only sein.
- Zieltabellen müssen vorhanden und fachlich korrekt sein.
- Erst Dry Run.
- Ein echter Transfer verändert Daten.
- Delete in der UI entfernt die Luna-Transferdefinition, nicht automatisch externe Daten.
- Upsert Keys müssen stabil und eindeutig sein.

## Einfaches Beispiel

```text
Dataset: demo_products
Target Connection: Demo Target
Target Table: products_import
Mode: Upsert
Key: external_id
```

---

# WooCommerce

## Zweck

Der WooCommerce-Bereich verwaltet WooCommerce-spezifische Integrationseinstellungen, HPOS-Verarbeitung, Webhook-Grundlagen, Transfer Queue und Exportprofile.

## Grundprinzip

```text
WooCommerce ruft Luna auf.
Luna prüft den Webhook.
Luna speichert Runtime-/Transferinformationen.
Luna schreibt nicht ungeprüft zurück nach WooCommerce.
```

## Konfigurierbar

Je nach Modulstand:

- WooCommerce Connection
- HPOS-Konfiguration
- Webhook Topic
- Secret
- TransferDB
- Exportprofile
- Runtime Events
- Queue-/Transferinformationen

## Muss beachtet werden

- HPOS ist die maßgebliche Bestellquelle.
- Webhook Secret muss in WooCommerce und Luna identisch sein.
- Webhook-HMAC wird über den rohen Request Body geprüft.
- Secrets werden nicht angezeigt oder exportiert.
- Eine WooCommerce-Anbindung zu löschen verändert keine Bestellungen.
- Die aktuelle vollständige WooCommerce Runtime benötigt eine erreichbare Luna-Runtime.
- Exportierbare eigenständige Webhook-Runtime-Pakete sind als späterer Meilenstein vorgesehen.

## Einfaches Beispiel

```text
Topic: order.updated
Provider: woocommerce
allow_unsigned: false
payload_log_mode: summary
```

---

# Prozesse

## Zweck

Ein Prozess orchestriert mehrere kontrollierte Schritte.

## Unterstützte Step-Typen

```text
mapping_run
target_action
schema_validation
```

## Konfigurierbar

- Workspace
- Name
- Process Key
- Beschreibung
- Status
- Schritte
- Reihenfolge
- Trigger
- Dry Run / Run

## Trigger-Typen

- manual
- cli
- api
- schedule
- webhook

## Muss beachtet werden

- Prozess zunächst manuell oder im Dry Run testen.
- Ein fehlgeschlagener Step kann den gesamten Run auf `failed` setzen.
- Prozesslogs dürfen keine Secrets enthalten.
- Webhook ist nur der Auslöser; die Fachlogik liegt im Prozess.

## Einfaches Beispiel

```text
Process: Demo Product Validation

Step 1: Mapping ausführen
Step 2: Schema validieren
```

---

# Target Actions

## Zweck

Target Actions führen kontrollierte Aktionen gegen Ziele aus.

## Typische Action-Typen

- http_get
- http_post
- http_put
- file_export
- database_insert
- database_upsert

## Konfigurierbar

Die Action-Konfiguration wird als JSON gepflegt.

## Muss beachtet werden

- Schreibende Actions zuerst im Dry Run.
- Keine freien SQL-Kommandos aus Benutzereingaben.
- Zielpfade bei File Export müssen sicher sein.
- Keine Secrets in Config exportieren.
- HTTP-Antworten und Fehler werden gekürzt protokolliert.
- `custom_php` darf nicht als beliebiger frei ausführbarer Code benutzt werden.

## Einfaches Beispiel – HTTP GET

```json
{
  "url": "https://example.test/api/health",
  "method": "GET",
  "timeout_seconds": 10,
  "expected_status": 200,
  "headers": {}
}
```

---

# Jobs

## Zweck

Jobs führen Mappings bzw. Transfers kontrolliert aus und erzeugen Job Runs.

## Konfigurierbar

- Workspace
- Mapping Set
- Name
- Status
- Run Mode
- Transfer Mode
- Batch Size
- Row Limit
- Dry Run Default
- Report aktiv
- Report Recipients
- Notes

## Muss beachtet werden

- `Dry Run Default` sollte beim Einrichten aktiv bleiben.
- Echte Ausführung erst nach geprüftem Dry Run.
- `Read Only`-Ziele blockieren Schreibläufe.
- Alte Testjobs können über die UI gelöscht werden.
- Job Runs und Reports können Abhängigkeiten bilden.

## Einfaches Beispiel

```text
Name: Demo Product Dry Run
Workspace: Demo Produkte
Mapping: Demo Product Mapping
Status: active
Run Mode: manual
Transfer Mode: insert
Batch Size: 100
Row Limit: 10
Dry Run Default: ja
Report aktiv: ja
```

CLI:

```powershell
php bin/luna job:run <job-id> --dry-run
```

---

# Reports

## Zweck

Reports dokumentieren Job Runs oder manuelle technische Informationen.

## Konfigurierbar

- Betreff
- Workspace
- Status
- Typ
- Empfänger
- Inhalt

Automatisch erzeugte Job Reports enthalten unter anderem:

- Job
- Mapping
- Dry-Run-Status
- Run-Status
- Source Rows
- Transformed
- Written
- Skipped
- Errors

## Muss beachtet werden

- Keine Passwörter, Tokens oder DSNs eintragen.
- E-Mail-Versand benötigt funktionierende Mail-Konfiguration.
- Report-Löschung löscht keinen Job Run.
- Reports sind Dokumentation, keine Datenquelle für Transfers.

## Einfaches Beispiel

```text
Betreff: Demo Integration eingerichtet
Workspace: Demo Produkte
Typ: manual
Status: created
Empfänger: admin@example.test
Inhalt: Mapping und Dry Run wurden erfolgreich geprüft.
```

---

# Endpoints

## Zweck

Endpoints stellen Daten aus Luna als JSON bereit.

Runtime-Pfad:

```text
/api/e/{endpoint_key}
```

## Mögliche Source Types

- static
- version
- mapping_dry_run
- job_status
- latest_report

Zusätzlich existiert der erweiterte Endpoint Builder für Mapping-Ausgaben und Exportpakete.

## Secret-Modi

- none
- optional
- required

Für private Endpoints:

```text
X-Luna-Endpoint-Secret
```

In Produktion werden Query-String-Secrets nicht akzeptiert.

## Was kann man machen?

- GET/POST konfigurieren
- Mapping referenzieren
- Preview ausführen
- Schema zuordnen und validieren
- Deployment Target auswählen
- Exportpaket erzeugen
- Snapshot in TransferDB speichern

## Muss beachtet werden

- Public Endpoints nur für bewusst öffentliche Daten.
- Private Endpoints mit Secret schützen.
- Exportpakete enthalten keine Secrets.
- Endpoint Preview vor Export prüfen.
- Endpoint Snapshot benötigt eine im Workspace verfügbare TransferDB.

## Einfachstes Beispiel – statisch

```text
Name: Demo Health
Key: demo-health
Method: GET
Source Type: static
Secret Mode: none
```

Config:

```json
{
  "static_response": {
    "success": true,
    "message": "Luna läuft"
  }
}
```

Aufruf:

```text
http://localhost:8000/api/e/demo-health
```

---

# Schemas

## Zweck

Die Schema Registry verwaltet versionierte JSON-artige Schemas für Mapping-, Endpoint- oder Prozess-Ergebnisse.

## Konfigurierbar

- Workspace
- Schema Key
- Version
- Name
- Beschreibung
- Status
- Schema JSON
- Beispielwerte

## Unterstützte Grundkonzepte

- object
- array
- string
- integer
- number
- boolean
- required
- properties
- verschachtelte Strukturen
- mehrere erlaubte Typen

## Muss beachtet werden

- Schema Explorer und Schema Registry sind verschiedene Funktionen.
- Neue Strukturänderungen sollten eine neue Version erhalten.
- Schema zunächst gegen echte Preview-Daten validieren.
- Leere PHP-Arrays können als `[]` erscheinen, auch wenn bei gefüllten Daten Objekte entstehen.

## Einfaches Beispiel

```json
{
  "type": "object",
  "required": ["success", "items"],
  "properties": {
    "success": {
      "type": "boolean"
    },
    "items": {
      "type": "array",
      "items": {
        "type": "object",
        "required": ["sku", "title"],
        "properties": {
          "sku": {
            "type": "string"
          },
          "title": {
            "type": "string"
          }
        }
      }
    }
  }
}
```

---

# Deployment Targets

## Zweck

Deployment Targets beschreiben, unter welchen öffentlichen URLs exportierte oder bereitgestellte Integrationen erreichbar sein sollen.

## Konfigurierbar

- Name
- Environment
- Workspace
- Public Base URL
- Endpoint Base URL
- Webhook Base URL
- Default Target
- Aktiv
- optionale erweiterte Metadaten

## Beispiel

```text
Name: Toolbox Production
Environment: production
Workspace: Demo Produkte
Public Base URL: https://toolbox.example.test/luna
Endpoint Base URL: https://toolbox.example.test/luna/endpoints/api
Webhook Base URL: https://toolbox.example.test/luna/api/webhooks
Default: ja
Aktiv: ja
```

## Muss beachtet werden

- Production Target darf nicht `localhost` sein.
- Kein Slash-Chaos am URL-Ende.
- Deployment Targets enthalten keine Secrets.
- Das Target beschreibt URLs, es deployt Dateien nicht automatisch.
- Ein Exportpaket muss anschließend bewusst auf die Zielumgebung übertragen werden.

---

# Audit

## Zweck

Audit ist das technische und fachliche Änderungsprotokoll.

Typische Ereignisse:

- Connection angelegt oder geändert
- Secret ersetzt
- Mapping geändert
- Job gestartet oder fehlgeschlagen
- Report erzeugt
- Endpoint aufgerufen oder abgelehnt
- Workspace-Freigabe einer Connection geändert

## Muss beachtet werden

- Audit enthält keine Secrets.
- Audit ist kein Debug-Log für vollständige Payloads.
- Audit-Einträge sollten nicht als Konfigurationsquelle verwendet werden.

---

# 8. Durchgängiges einfaches Beispiel

Dieses Beispiel demonstriert eine kleine Datenbankintegration ohne WooCommerce.

## 8.1 Demo-Datenbanken anlegen

```sql
CREATE DATABASE luna_demo_source
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

CREATE DATABASE luna_demo_target
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
```

Quelltabelle:

```sql
USE luna_demo_source;

CREATE TABLE products (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sku VARCHAR(64) NOT NULL,
  name VARCHAR(190) NOT NULL,
  price DECIMAL(10,2) NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1
);

INSERT INTO products (sku, name, price, active) VALUES
('DEMO-001', 'Demo Produkt 1', 19.99, 1),
('DEMO-002', 'Demo Produkt 2', 29.99, 1);
```

Zieltabelle:

```sql
USE luna_demo_target;

CREATE TABLE products_import (
  external_id INT UNSIGNED NOT NULL PRIMARY KEY,
  sku VARCHAR(64) NOT NULL,
  title VARCHAR(190) NOT NULL,
  amount DECIMAL(10,2) NOT NULL
);
```

## 8.2 Workspace

```text
Name: Demo Produkte
Key: demo-products
Status: aktiv
```

## 8.3 Source Connection

```text
Name: Demo Source
Key: demo-source
Database: luna_demo_source
Read Only: ja
Owner Workspace: Demo Produkte
```

## 8.4 Target Connection

```text
Name: Demo Target
Key: demo-target
Database: luna_demo_target
Read Only: nein
Owner Workspace: Demo Produkte
```

## 8.5 Schema Explorer

- Connection `Demo Source`
- Tabelle `products`
- Spalten und Beispieldaten prüfen

## 8.6 Mapping

```text
Name: Demo Product Mapping
Workspace: Demo Produkte
Source: Demo Source.products
Target: Demo Target.products_import
```

Felder:

```text
external_id ← id
sku         ← sku
title       ← name
amount      ← price
```

Source Filter optional:

```text
active = 1
```

## 8.7 Mapping Dry Run

Preview prüfen:

```json
{
  "external_id": 1,
  "sku": "DEMO-001",
  "title": "Demo Produkt 1",
  "amount": "19.99"
}
```

## 8.8 Dataset

```text
Name: demo_products
Workspace: Demo Produkte
Quelle: Demo Product Mapping
```

Dataset Preview ausführen.

## 8.9 Transfer

```text
Name: Demo Products Transfer
Dataset: demo_products
Target Connection: Demo Target
Target Table: products_import
Mode: Upsert
Key: external_id
```

Zuerst Dry Run.

Erst danach echten Transfer starten.

## 8.10 Schema

```text
Schema Key: demo.products
Version: 1.0.0
```

Schema gegen Preview validieren.

## 8.11 Endpoint

```text
Name: Demo Products API
Key: demo-products
Method: GET
Mapping: Demo Product Mapping
Schema: demo.products 1.0.0
```

Preview prüfen.

## 8.12 Job

```text
Name: Demo Product Job
Mapping: Demo Product Mapping
Dry Run Default: ja
Row Limit: 10
Report aktiv: ja
```

## 8.13 Report

Nach dem Job Run den erzeugten Report prüfen.

## 8.14 Process

```text
Process: Demo Product Process

Step 1: Mapping ausführen
Step 2: Schema Validation
```

Manuell im Dry Run starten.

---

# 9. CLI-Kurzreferenz

## Systemdatenbank

```powershell
php bin/luna db:test
php bin/luna migrate
```

## Connections

```powershell
php bin/luna connection:test <connection-id>
```

## TransferDB

```powershell
php bin/luna transferdb:test <connection-id>
php bin/luna transferdb:status <connection-id>
php bin/luna transferdb:migrate <connection-id>
php bin/luna transferdb:check <workspace-id-or-key>
```

## Mappings

```powershell
php bin/luna mapping:dry-run <mapping-id>
php bin/luna mapping:run <mapping-id> --force
```

## Datasets

```powershell
php bin/luna dataset:list
php bin/luna dataset:preview <dataset-name>
```

## Transfers

```powershell
php bin/luna transfer:dry-run <transfer-id>
php bin/luna transfer:run <transfer-id> --force
```

## Jobs

```powershell
php bin/luna job:run <job-id> --dry-run
```

## Prozesse und Trigger

```powershell
php bin/luna process:run <process-id> --dry-run
php bin/luna trigger:list <process-id>
php bin/luna trigger:run <trigger-id-or-key> --dry-run
```

## Schemas

```powershell
php bin/luna schema:validate <schema-id> <json-or-file>
```

## Endpoint Export

```powershell
php bin/luna endpoint:export <endpoint-id> --target=<environment>
```

## WooCommerce Transfer

```powershell
php bin/luna woocommerce:transfer:run --connection-id=<id> --limit=10
```

## Qualität

```powershell
composer check
```

---

# 10. Sicherheit und Betrieb

## APP_KEY

- vor dem ersten Secret setzen
- sicher sichern
- nicht committen
- nicht unkontrolliert rotieren

## `.env`

- enthält Luna-Core-Konfiguration
- nie committen
- außerhalb von `public/`
- keine fremden Connection-Profile dort pflegen

## Connection-Secrets

- werden verschlüsselt gespeichert
- werden nicht in der UI wieder angezeigt
- dürfen nicht in Logs oder Reports stehen
- werden nicht in Exportpakete geschrieben

## Dry Run

Vor jedem echten Transfer:

1. Mapping Preview
2. Dataset Preview
3. Transfer Dry Run
4. Logs und Counts prüfen
5. erst danach echter Run

## Backups

Vor Updates oder produktiven Transfers:

- Luna-Systemdatenbank sichern
- TransferDB sichern
- externe Zielsysteme nach deren Regeln sichern
- `.env`/APP_KEY sicher verwahren

## Löschen

Luna blockiert Löschungen bei Abhängigkeiten.

Blockermeldungen nennen konkrete Einträge, zum Beispiel:

```text
Connection kann nicht gelöscht werden:
- Mapping "Demo Product Mapping"
- Job "Demo Product Job"
```

Erst Blocker entfernen, dann erneut löschen.

---

# 11. Fehlersuche

## `composer install` fehlt

Symptom:

```text
vendor/autoload.php fehlt
Klassen nicht gefunden
Dotenv nicht gefunden
```

Lösung:

```powershell
composer install
```

## Systemdatenbank nicht erreichbar

```powershell
php bin/luna db:test
```

Prüfen:

- `LUNA_DB_HOST`
- Port
- Datenbank
- Benutzer
- Passwort
- MariaDB läuft
- `pdo_mysql` aktiv

## Keine Tabellen vorhanden

```powershell
php bin/luna migrate
```

## APP_KEY fehlt

Symptom beim Speichern eines Secrets:

```text
APP_KEY is required for encryption.
```

Lösung:

```powershell
php -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"
```

In `.env` eintragen und Anwendung neu starten.

## Connection nicht im Workspace sichtbar

Prüfen:

- Ist der Workspace Owner?
- Ist die Connection für den Workspace freigegeben?
- Wurde die Freigabe gespeichert?
- Ist die Connection aktiv?
- Passt die Verwendung/Rolle?

## TransferDB wird nicht angeboten

Prüfen:

- Verwendung `TransferDB` oder `Mixed`
- Workspace ist Owner oder freigegeben
- Connection erreichbar
- TransferDB-Schema installiert
- alle `luna_`-Tabellen vorhanden

## Endpoint-Snapshot nicht speicherbar

Prüfen:

- Endpoint gehört zu Workspace
- TransferDB ist für Workspace verfügbar
- Endpoint Preview ist gültig
- Schema Validation ist gültig
- TransferDB ist schreibbar

## 404 bei Entwicklungsserver

Server mit Router starten:

```powershell
php -S localhost:8000 -t public public/dev-router.php
```

## 500 in Produktion

- `APP_DEBUG=false` zeigt absichtlich keine Details.
- Server-/PHP-Logs prüfen.
- `composer check` lokal ausführen.
- Migrationen prüfen.
- APP_KEY und `.env` prüfen.

---

# 12. Aktuelle Grenzen von v2.7.3

v2.7.3 unterstützt:

- vollständige Luna-Systemmigrationen
- Owner Workspace und Connection Sharing
- TransferDB Setup und Migration
- Mapping, Dataset und Transfer
- Processes, Trigger und Target Actions
- Schema Registry und Validation
- Endpoint Export Packages
- WooCommerce Runtime-Grundlage
- Admin Cleanup und sichere Löschungen

Noch nicht als eigenständige vollständige Exportfunktion umgesetzt:

```text
Exportable Webhook Runtime Packages
```

Das bedeutet:

- Endpoint-Runtimes können exportiert werden.
- WooCommerce-Webhooks können die Luna-Runtime triggern.
- Ein eigenständiges exportiertes Webhook-Paket, das ohne vollständige Luna-Runtime auf einem separaten Server läuft, ist der nachfolgende Meilenstein v2.8.0.

---

# Weiterführende Projektdokumentation

Im Repository befinden sich zusätzlich:

```text
docs/ARCHITECTURE.md
docs/DEPLOYMENT.md
docs/OPERATIONS.md
docs/SECURITY_MODEL.md
docs/TRANSFERDB.md
docs/WOOCOMMERCE_RUNTIME.md
docs/API_ENDPOINTS.md
docs/export-contract.md
docs/deployment-targets.md
docs/ROADMAP.md
```

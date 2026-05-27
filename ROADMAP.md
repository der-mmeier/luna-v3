# Roadmap — Luna V3

## Zielbild 1.0.0

Luna V3 ist eine webbasierte PHP-8.2+-Workbench für Integrations- und Mapping-Projekte. Sie verbindet Datenquellen, analysiert Datenbankschemata, zeigt Beispieldaten, verwaltet Spaltenkommentare und Mappings, befüllt Transferdatenbanken, führt Jobs aus, erzeugt Reports per E-Mail und stellt einfache API-Endpunkte bereit.

Luna V3 ist ausdrücklich kein CMS, kein Shop-System, kein ERP, kein Laravel-Klon, kein phpMyAdmin-Ersatz und kein vollständiges No-Code-System.

Kernkonzepte:

- Workspaces/Projekte
- Luna-Systemdatenbank
- externe Datenquellen
- Connection Manager
- verschlüsselte Secrets
- Schema Explorer
- Mapping Designer
- Value Mapping
- Transferdatenbank
- Job Runner
- Report Engine
- Endpoint Builder
- Audit Log

---

## 0.1.0 — Projektfundament

Ziel:
Sauberes technisches Grundgerüst herstellen.

Umfang:

- Composer-Projekt
- Namespace `Luna\`
- `public/index.php`
- `src/Bootstrap.php`
- `.env.example`
- `.gitignore`
- AGENTS.md
- ROADMAP.md
- CHANGELOG.md
- docs/CODEX_PROTOCOL.md

Akzeptanzkriterien:

- `composer install` läuft
- `composer dump-autoload` läuft
- `public/index.php` kann `src/Bootstrap.php` verwenden
- `.env` wird bei Vorhandensein geladen
- `.env` wird nicht committed
- `.env.example` ist vorhanden

---

## 0.2.0 — Produktdefinition und Architektur

Ziel:
Luna V3 als Integrations- und Mapping-Workbench fachlich und technisch definieren.

Umfang:

- `docs/PRODUCT_SPEC.md`
- `docs/SECURITY_MODEL.md`
- `docs/DATA_MODEL_DRAFT.md`
- Aktualisierung von Projektziel und Architektur
- Abgrenzung zu CMS, Shop, ERP, Laravel-Klon, phpMyAdmin und vollständigen No-Code-Systemen

Akzeptanzkriterien:

- Produktzweck und Nicht-Ziele sind dokumentiert
- Sicherheitsmodell für `.env`, APP_KEY, externe Secrets und API-Secrets ist beschrieben
- grobes Datenmodell für Workspaces, Connections, Schema-Metadaten, Mappings, Jobs, Reports, Endpoints und Audit Log liegt vor
- keine neuen PHP-Klassen werden für diesen Meilenstein implementiert

---

## 0.3.0 — Application Core

Ziel:
Eine zentrale Laufzeit für die Workbench schaffen.

Umfang:

- Application- und Kernel-Grundstruktur
- zentrale Konfiguration aus Luna-Core-Umgebung
- grundlegende Service-Registrierung
- Trennung von Bootstrap, Anwendungslaufzeit und Fachmodulen

Akzeptanzkriterien:

- `public/index.php` bleibt schlank
- Bootstrap enthält keine Fachlogik
- Application Core ist zentraler Einstiegspunkt für Web- und spätere Job-Verarbeitung

---

## 0.4.0 — HTTP-Grundlage und Routing

Ziel:
HTTP-Verarbeitung für Admin UI, API-Endpunkte und interne Aktionen vorbereiten.

Umfang:

- Request/Response-Abstraktion
- Routing-Grundlage
- Fehlerantworten für unbekannte Routen
- Trennung von UI-Routen und API-Routen

Akzeptanzkriterien:

- Requests werden gekapselt verarbeitet
- Responses werden zentral ausgegeben
- private API-Endpunkte können später mit Secrets abgesichert werden

---

## 0.5.0 — Admin UI mit Bootstrap

Ziel:
Eine einfache webbasierte Oberfläche für Workspaces und Verwaltung bereitstellen.

Umfang:

- Admin-Layout auf Basis von Bootstrap
- Navigation für Workspaces, Connections, Schema Explorer, Mappings, Jobs und Reports
- erste Form- und Tabellenkomponenten
- keine fachliche Tiefenlogik in Templates

Akzeptanzkriterien:

- Admin UI ist über `/public` erreichbar
- Grundnavigation bildet die Workbench-Konzepte ab
- UI bleibt serverseitig einfach wartbar

---

## 0.6.0 — Luna-Systemdatenbank

Ziel:
Persistenz für Workbench-Metadaten schaffen.

Umfang:

- Datenbankschema für Workspaces/Projekte
- externe Connection-Definitionen mit verschlüsselten Secrets
- Schema-Metadaten, Kommentare und Mapping-Entwürfe
- Audit-Log-Grundlage

Akzeptanzkriterien:

- Luna-Systemdatenbank speichert keine Secrets im Klartext
- APP_KEY aus `.env` dient als Schlüsselbasis
- technische und fachliche Metadaten sind getrennt von externen Datenquellen

---

## 0.7.0 — Connection Manager und Schema Explorer

Ziel:
Externe Datenquellen über die UI verwalten und analysieren.

Umfang:

- Connection Manager für externe Datenquellen
- read-only Standard für Quellverbindungen
- Schema-Analyse von Tabellen und Spalten
- Anzeige von Beispieldaten
- Spaltenkommentare und Metadatenpflege

Akzeptanzkriterien:

- externe Verbindungen werden nicht in `.env` gepflegt
- Zugangsdaten werden verschlüsselt gespeichert
- Secrets werden nie geloggt
- Schema Explorer kann Tabellen, Spalten und Beispieldaten anzeigen

---

## 0.8.0 — Mapping Designer

Ziel:
Datenflüsse und Feldzuordnungen visuell verwalten.

Umfang:

- Mapping Designer für Quell- und Zieltabellen
- Spaltenzuordnungen
- einfache Transformationsregeln
- Value Mapping
- Validierung von Mapping-Entwürfen

Akzeptanzkriterien:

- Mappings sind workspace-bezogen speicherbar
- Value Mappings sind getrennt von Connection-Secrets
- Mapping-Änderungen sind nachvollziehbar

---

## 0.9.0 — Jobs, Transfers und Reports

Ziel:
Mappings ausführbar machen und Ergebnisse berichten.

Umfang:

- Transferdatenbank-Anbindung
- Job Runner für manuelle und geplante Läufe
- Job-Protokollierung
- Report Engine
- E-Mail-Reports

Akzeptanzkriterien:

- Jobs schreiben nachvollziehbare Laufprotokolle
- Transferdatenbanken können aus Mappings befüllt werden
- Reports enthalten keine Secrets
- Fehler werden auditierbar gespeichert

---

## 1.0.0 — Endpoint Builder und stabile Workbench

Ziel:
Eine stabile erste Workbench-Version für Integrationsprojekte bereitstellen.

Status:
Umgesetzt im Branch `feature/1.0.0-endpoint-builder-stable-workbench`.

Umfang:

- Endpoint Builder für einfache API-Endpunkte
- private Endpoint-Secrets
- stabile Admin UI
- Connection Manager
- Schema Explorer
- Mapping Designer
- Job Runner
- Report Engine
- Audit Log
- Betriebsdokumentation

Akzeptanzkriterien:

- Luna V3 kann ein Integrationsprojekt von Datenquelle bis Transfer/Report abbilden
- private API-Endpunkte sind abgesichert
- DocumentRoot zeigt auf `/public`
- Secrets werden nie committed, nie im Klartext gespeichert und nie geloggt
- GitHub-Stand ist sauber versioniert

---

## 1.1.0 — Workbench UX, Workspaces und Mapping-Auswahl

Ziel:
Die tägliche Benutzung der Luna V3 Workbench sauberer, schneller und weniger fehleranfällig machen.

Umfang:

- Dark Theme als Standard mit Light/Dark-Switch
- lokaler UI-Präferenzcookie `luna_theme`
- schwarze Glasoptik und stabile mobile Admin UI
- Workspace-Erstellung und -Bearbeitung
- dynamische Source-/Target-Tabellenauswahl beim Mapping

Akzeptanzkriterien:

- Workspaces sind über die Admin UI verwaltbar
- Mapping-Tabellen werden aus der gewählten Connection geladen
- Tabellenlisten-JSON enthält keine Secrets
- bestehende 1.0.0-Flows bleiben rückwärtskompatibel

---

# Luna V3 Roadmap — 1.1.1 bis 1.7.0

Stand: 20.05.2026  
Zielkorridor: Vorbereitung und Umsetzung des ersten realen Integrationsprojekts **AsfInStockRings** bis 29.05.2026.

Luna V3 wird in dieser Phase nicht als öffentliche Plattform verstanden, sondern als interne Integrations-Workbench. Öffentlich erreichbar sollen nur freigegebene oder exportierte Runtime-Endpunkte sein.

---

# Qualitäts-Gate ab 1.1.1

Ab Version 1.1.1 wird ein verbindliches Qualitäts-Gate eingeführt.

Nach jeder Codex-Aufgabe müssen die verfügbaren Checks ausgeführt werden. Ein Task gilt erst dann als abgeschlossen, wenn die Checks erfolgreich durchgelaufen sind oder ein verbleibender Fehler ausdrücklich dokumentiert wurde.

Pflichtreihenfolge nach jeder Codeänderung:

```bash
composer dump-autoload
```

Danach:

```bash
composer analyse
```

Falls `composer analyse` noch nicht vorhanden ist, aber PHPStan installiert ist:

```bash
vendor/bin/phpstan analyse
```

Danach:

```bash
composer test
```

Falls `composer test` noch nicht vorhanden ist, aber PHPUnit installiert ist:

```bash
vendor/bin/phpunit
```

Sobald ein gemeinsames Script existiert, soll bevorzugt dieses ausgeführt werden:

```bash
composer check
```

`composer check` soll mindestens ausführen:

```bash
composer dump-autoload
composer analyse
composer test
```

Wenn neue PHP-Dateien entstehen, müssen diese zusätzlich mit `php -l` geprüft werden, solange noch kein vollständiges automatisches Syntax-Gate existiert.

Tests gehören zur Aufgabe. Wer produktiven Code ändert oder neue Fachlogik ergänzt, muss passende Tests mitliefern oder begründen, warum für diese Änderung kein sinnvoller Test möglich ist.

---

## 1.1.1 — Stabilisierung und Code Quality Foundation

### Ziel

Den aktuellen Stand nach 1.1.0 sauber stabilisieren, bevor echte Integrationslogik für externe Datenquellen und öffentliche Endpunkte ergänzt wird.

Zusätzlich wird in 1.1.1 die verbindliche Grundlage für statische Analyse und automatisierte Tests geschaffen. Ab dieser Version gilt: Nach jeder Codex-Aufgabe müssen die vorhandenen Qualitätschecks ausgeführt werden.

### Umfang

- Bestehende 1.1.0-Flows prüfen
- Admin-Routing prüfen
- `.env.example` aktualisieren
- Migrationsstatus prüfen
- Connection Manager auf Secret-Sicherheit prüfen
- Keine Klartext-Passwörter in Logs, JSON-Ausgaben oder UI
- PHPStan als statische Analyse vorbereiten
- PHPUnit als Testbasis vorbereiten
- Composer-Scripts für Qualitätschecks vorbereiten:
  - `composer analyse`
  - `composer test`
  - `composer check`
- Basistests für:
  - Workspaces
  - Connections
  - Schema Explorer
  - Mapping-Tabellenauswahl
  - Secret-/Config-Sicherheit
  - einfache Template- oder Mapping-Hilfslogik, sobald vorhanden

### Akzeptanzkriterien

- `main` ist stabil
- Branch: `feature/1.1.1-stabilize-before-integration`
- Luna startet sauber mit DocumentRoot `/public`
- Bestehende Admin-Oberfläche bleibt nutzbar
- Keine Secrets erscheinen in Tabellenlisten, Dumps oder Fehlermeldungen
- Bestehende 1.0.0- und 1.1.0-Flows bleiben rückwärtskompatibel
- PHPStan kann installiert und über Composer ausgeführt werden
- PHPUnit kann installiert und über Composer ausgeführt werden
- `composer analyse` ist dokumentiert oder vorbereitet
- `composer test` ist dokumentiert oder vorbereitet
- `composer check` ist als gemeinsames Qualitäts-Gate vorgesehen
- Wenn PHPStan installiert ist, muss PHPStan nach jeder Codex-Aufgabe laufen
- Wenn PHPUnit installiert ist, müssen die PHPUnit-Tests nach jeder Codex-Aufgabe laufen
- Neue produktive Logik erhält passende Tests oder eine dokumentierte Begründung, warum kein sinnvoller Test möglich ist
- Codex darf eine Aufgabe nicht als abgeschlossen melden, ohne die ausgeführten Checks und deren Ergebnis zu nennen

### Branch

```text
feature/1.1.1-stabilize-before-integration
```


---

## 1.2.0 — Multi-Connection Integration Foundation

### Ziel

Luna muss mehrere externe Datenquellen pro Workspace sauber verwalten, testen und im Schema Explorer anzeigen können.

Status:
In Umsetzung im Branch `feature/1.2.0-multi-connection-integration-foundation`.

Für AsfInStockRings werden mindestens zwei Connections benötigt:

- PIMCore-Datenbank
- Preis-/Key-Value-Datenbank

### Umfang

- Connection-Typ `mysql` / `mariadb` stabilisieren
- Mehrere Connections pro Workspace erlauben
- Connection-Test über UI
- Connection-Test über CLI
- Sichere Secret-Speicherung
- Schema Explorer pro Connection
- Tabellenlisten pro Connection
- Beispielzeilen/Sampling pro Tabelle
- Source-Connections standardmäßig read-only behandeln
- Fehlerausgaben ohne sensible Zugangsdaten

### Akzeptanzkriterien

- PIMCore-DB kann als Connection angelegt werden
- Preis-DB kann als zweite Connection angelegt werden
- Beide Connections können unabhängig getestet werden
- Schema Explorer zeigt Tabellen und Spalten pro Connection
- Beispielzeilen können gelesen werden
- Secrets werden verschlüsselt gespeichert
- UI gibt niemals Passwort oder vollständige sensible DSN-Daten aus
- `composer check` läuft nach Abschluss des Meilensteins grün, sobald PHPStan/PHPUnit installiert sind

### Branch

```text
feature/1.2.0-multi-connection-integration-foundation
```

---

## 1.3.0 — Multi-Source Lookup Mapping und Value Resolver

### Ziel

Luna muss Werte nicht nur 1:1 aus einer Datenquelle übernehmen, sondern aus einer Primary Source und optional mehreren Lookup Sources einen normalisierten Transfer-Datensatz erzeugen können.

Wichtig ist die fachliche Trennung:

```text
n Sources → 1 Transfer-Datensatz → n Endpoint-Profile/Targets
```

Für 1.3.0 ist umzusetzen:

```text
n Sources → 1 Transfer-Datensatz
```

Die spätere Ausgabe an mehrere Endpoint-Profile oder Targets muss architektonisch vorbereitet werden, darf aber noch nicht vollständig umgesetzt werden.

Beispiel für AsfInStockRings:

```text
Primary Source: PIMCore Produkt
name = "E001 Carbon Partnerringe"
price_group = 2

Lookup Source: Preis-DB
price_group_2 = 499.00
price_group_2_pseudo = 599.00
```

Daraus soll Luna einen Transfer-Datensatz erzeugen:

```json
{
  "name": "E001 Carbon Partnerringe",
  "price_group": 2,
  "price": 499.00,
  "pseudo_price": 599.00
}
```

Dieser Transfer-Datensatz ist nicht zwangsläufig das finale Target. Er ist die interne, normalisierte Struktur, aus der spätere Endpoint-Profile unterschiedliche Zielsysteme bedienen können.

Beispiel:

```text
Transfer-Datensatz
  → Endpoint Profile: WooCommerce Product API
  → Endpoint Profile: Custom Lager API
  → Endpoint Profile: JSON Feed Export
```

### Umfang

- Mapping-Feldtyp: `source_column`
- Mapping-Feldtyp: `static_value`
- Mapping-Feldtyp: `lookup_value`
- Primary Source als führende Datenquelle eines Mappings
- Lookup Sources als zusätzliche Datenquellen für Resolver
- Mehrere Lookup-Felder pro Mapping
- Mehrere Lookup-Connections pro Mapping vorbereiten und unterstützen
- Transfer-Datensatz als eigene logische Ausgabe des Mappings sichtbar machen
- Endpoint-Profile logisch vom Mapping/Transfer trennen und für spätere Versionen vorbereiten
- Lookup-Regeln:
  - Lookup-Connection wählen
  - Lookup-Tabelle wählen
  - Key-Spalte wählen
  - Value-Spalte wählen
  - Key-Template definieren
  - optionalen Fallback-Wert vorbereiten
  - Verhalten bei fehlenden Lookup-Werten vorbereiten
- Transfer-Felddefinition pro Mapping als eigene Liste, getrennt von Target- und Lookup-Tabellenspalten
- Template-Unterstützung für Lookup-Keys:
  - `price_group_{{price_group}}`
  - `price_group_{{price_group}}_pseudo`
  - weitere Platzhalter aus Source-Zeile oder bereits aufgebautem Transfer-Kontext
- Preview/Dry-Run für Mapping
- JSON-Vorschau des fertigen Transfer-Datensatzes
- Fehleranzeige bei fehlenden Lookup-Keys
- Fehleranzeige bei fehlenden Template-Platzhaltern
- Optionale Fallback-Werte vorbereiten

### Akzeptanzkriterien

- Ein Mapping kann Werte aus einer Primary Source lesen
- Ein Mapping kann `source_column` ausführen
- Ein Mapping kann `static_value` ausführen
- Ein Mapping kann `lookup_value` ausführen
- Ein Mapping kann pro Zeile einen Lookup gegen eine zweite Connection ausführen
- Ein Mapping kann mehrere Lookup-Felder verwenden
- Ein Mapping kann mehrere Lookup-Sources verwenden, wenn unterschiedliche Felder unterschiedliche Lookup-Connections konfigurieren
- `price_group_x` und `price_group_x_pseudo` können aufgelöst werden
- `price_group_{{price_group}}` wird korrekt gerendert
- `price_group_{{price_group}}_pseudo` wird korrekt gerendert
- Der fertige Transfer-Datensatz wird im Dry-Run sichtbar
- Dry-Run zeigt mindestens 10 Beispielzeilen als JSON-Vorschau, sofern mindestens 10 Source-Zeilen vorhanden sind
- Fehlende Preisgruppen werden sichtbar gemeldet
- Fehlende Lookup-Keys werden sichtbar gemeldet
- Fehlende Template-Platzhalter werden sichtbar gemeldet
- Fehler werden nicht still verschluckt
- Optionale Fallback-Werte sind technisch vorbereitet und mindestens im Resolver berücksichtigt
- Keine Secrets werden in Mapping-Preview, Dry-Run, CLI-Ausgaben, Logs oder Fehlerausgaben angezeigt
- Die Datenstruktur blockiert nicht, dass später mehrere Endpoint-Profile aus einem Transfer-Datensatz entstehen
- Bestehende 1.2.0-Flows bleiben rückwärtskompatibel
- Neue Resolver- und Mapping-Logik ist durch PHPUnit-Tests abgedeckt
- `composer check` läuft nach Abschluss des Meilensteins grün

### Branch

```text
feature/1.3.0-lookup-mapping-value-resolver
```


---

## 1.4.0 — JSON Endpoint Builder v2

### Ziel

Aus einem Mapping soll ein API-Endpunkt entstehen können.

Für den ersten realen Anwendungsfall ist der Ziel-Endpunkt:

```text
/pim/api/isr_prices.php
```

Alternativ Luna-intern:

```text
/public/api/endpoints/isr_prices
```

### Umfang

- Endpoint an Workspace binden
- Endpoint an Mapping binden
- HTTP-Methode definieren, initial nur `GET`
- JSON-Ausgabe standardisieren
- Standardfelder:
  - `success`
  - `generated_at`
  - `count`
  - `items`
- Endpoint Secret optional oder verpflichtend konfigurierbar machen
- Fehlerformat standardisieren
- Cache optional vorbereiten
- Preview im Admin
- Public Runtime klar vom Admin trennen

### Beispiel-Zielausgabe

```json
{
  "success": true,
  "generated_at": "2026-05-20T14:30:00+02:00",
  "count": 2,
  "items": [
    {
      "model": "E001",
      "name": "Carbon Partnerringe E001",
      "price_group": 2,
      "price": 499.00,
      "pseudo_price": 599.00
    },
    {
      "model": "E002",
      "name": "Titanium Partnerringe E002",
      "price_group": 3,
      "price": 699.00,
      "pseudo_price": 799.00
    }
  ]
}
```

### Akzeptanzkriterien

- Endpoint kann über UI angelegt werden
- Endpoint kann ein Mapping ausführen
- Endpoint liefert valides JSON
- Endpoint kann mit Secret geschützt werden
- Admin und Runtime sind klar getrennt
- Fehler enthalten keine Secrets, SQL-Zugangsdaten oder Stacktraces
- Endpoint-Preview ist in der Admin UI möglich
- Endpoint-Runner, JSON-Response und Fehlerformat sind durch PHPUnit-Tests abgedeckt
- `composer check` läuft nach Abschluss des Meilensteins grün

### Branch

```text
feature/1.4.0-json-endpoint-builder-v2
```

---

## 1.5.0 — Endpoint Export Runtime

### Ziel

Luna soll einen Endpoint exportieren können, ohne dass die komplette Workbench öffentlich erreichbar sein muss.

Strategisches Ziel:

```text
AsfInStockRings → exportierter Runtime-Endpunkt
```

Nicht:

```text
AsfInStockRings → öffentliche Luna-Workbench
```

### Umfang

- Export eines Endpoint-Profils
- Export als PHP-Runtime-Datei oder Runtime-Konfiguration
- Runtime nutzt dieselben Connection- und Mapping-Regeln
- Keine Admin UI im Export notwendig
- Export enthält keine Klartext-Secrets
- Export kann auf `toolbox.asf.gmbh/pim/api/` deployed werden
- Optionale `.env` für Runtime
- Optionaler Runtime-Bootstrap
- Runtime-Struktur vorbereiten

### Mögliche Zielstruktur

```text
toolbox.asf.gmbh/
└── pim/
    ├── api/
    │   └── isr_prices.php
    ├── runtime/
    │   ├── bootstrap.php
    │   ├── EndpointRunner.php
    │   ├── ConnectionFactory.php
    │   └── MappingExecutor.php
    └── .env
```

### Akzeptanzkriterien

- Luna kann einen Endpoint exportieren
- Exportierter Endpoint läuft ohne Admin-Oberfläche
- Exportierter Endpoint kann auf der Toolbox-Subdomain deployed werden
- Secrets liegen nur in `.env` oder sicher konfiguriert vor
- AsfInStockRings muss nur HTTP-JSON konsumieren
- Exportierte Runtime ist unabhängig von der Admin-Oberfläche nutzbar
- Export-/Runtime-Code ist durch geeignete PHPUnit-Tests abgesichert
- `composer check` läuft nach Abschluss des Meilensteins grün

### Branch

```text
feature/1.5.0-endpoint-export-runtime
```

---

## 1.6.0 — AsfInStockRings Integration Project

### Ziel

Der erste echte Luna-Integrationsfall wird als konkretes Projekt umgesetzt.

### Workspace

```text
AsfInStockRings
```

### Connections

```text
pimcore
price_settings
```

### Mapping

```text
ISR Ring Prices
```

### Endpoint

```text
isr_prices
```

### Ziel-URL

```text
https://toolbox.asf.gmbh/pim/api/isr_prices.php
```

### Umfang

- PIMCore-Beispieldatensatz analysieren
- Ring-Identifikationsspalte bestimmen
- Filter für relevante Ringe definieren
- Felder bestimmen:
  - Modell
  - Name
  - Preisgruppe
  - Material, falls benötigt
  - Bildkennung, falls benötigt
  - Aktiv/Inaktiv, falls vorhanden
- Preisgruppe gegen Preis-DB auflösen
- JSON-Struktur finalisieren
- WordPress-Plugin-kompatible Ausgabe definieren
- Dry-Run mit echten Beispieldaten
- Export oder Runtime-Bereitstellung des Endpoints

### Akzeptanzkriterien

- Endpoint liefert nur relevante InStock-Ringe
- `name`, `price_group`, `price` und `pseudo_price` sind enthalten
- Preisgruppen-Lookup funktioniert
- Fehlende Preise werden sauber gemeldet
- JSON kann direkt von AsfInStockRings konsumiert werden
- Endpoint läuft auf der Toolbox-Subdomain
- Zugang ist abgesichert
- Keine Secrets erscheinen in Response, Logs oder Fehlermeldungen
- ISR-Mapping und Preisgruppen-Lookup sind durch PHPUnit-Tests oder dokumentierte Dry-Run-Prüfungen abgesichert
- `composer check` läuft nach Abschluss des Meilensteins grün

### Branch

```text
feature/1.6.0-asf-in-stock-rings-project
```

---

## 1.7.0 — Hardening, Logging und Betrieb

### Ziel

Der Endpoint und die Luna Runtime sollen betriebssicher werden.

Diese Version ist wünschenswert, aber nicht zwingend kritisch für den ersten Abgabetermin am 29.05.2026.

### Umfang

- Request Logging ohne Secrets
- Endpoint Audit Log
- Fehlerstatistik
- Anzeige der letzten erfolgreichen Ausführung
- Cache-TTL pro Endpoint
- Manuelles Cache-Leeren
- JSON-Healthcheck
- Optional API-Key-Rotation
- Betriebsdokumentation
- Deployment-Dokumentation
- Recovery-Dokumentation
- Debug-Modus klar von Produktivmodus trennen

### Akzeptanzkriterien

- Fehler sind nachvollziehbar
- Endpoint kann geprüft werden
- Cache kann aktiviert und deaktiviert werden
- Luna zeigt letzten Laufstatus
- Keine sensiblen Daten in Logs
- Keine sensiblen Daten in Responses
- Doku reicht aus, um den Endpoint erneut zu deployen
- Produktivbetrieb ist ohne Admin-Zugriff auf die Workbench möglich
- Relevante Betriebs- und Sicherheitslogik ist durch PHPUnit-Tests abgesichert
- `composer check` läuft nach Abschluss des Meilensteins grün

### Branch

```text
feature/1.7.0-endpoint-hardening-operations
```

---

# MVP bis 29.05.2026

Der MVP ist nicht, dass Luna vollständig perfekt ist.

Der MVP ist:

```text
Luna kann den konkreten ISR-Endpoint kontrolliert erzeugen oder betreiben.
```

Minimum bis 29.05.2026:

- Zwei DB-Connections
- Tabellen und Samples ansehen
- Mapping definieren
- Lookup Preisgruppe → Preis/Pseudopreis
- JSON Preview
- Endpoint bereitstellen oder exportieren
- WordPress-Plugin kann den Endpoint abrufen
- Qualitäts-Gate läuft grün: `composer dump-autoload`, PHPStan, PHPUnit bzw. `composer check`

Nicht zwingend bis 29.05.2026:

- Perfekter No-Code-Designer
- Komplexe Transformationssprache
- Scheduler
- Schöne Report Engine
- Vollständige Multi-Tenant-Logik
- Generische Endpoint-Bibliothek für alle Eventualitäten

---

# Zeitliche Priorisierung

| Datum      | Ziel |
|------------|---|
| 20.05.2026 | Roadmap festziehen, 1.1.1 starten |
| 20.05.2026 | 1.1.1 abschließen, 1.2.0 beginnen |
| 20.05.2026 | 1.2.0 fertigstellen, echte PIM-/Preis-Connections testen |
| 21.05.2026 | 1.3.0 Lookup Mapping bauen |
| 21.05.2026 | 1.3.0 Dry-Run/Preview stabilisieren |
| 21.05.2026 | 1.4.0 Endpoint Builder v2 |
| 22.05.2026 | 1.5.0 Export Runtime |
| 22.05.2026 | 1.6.0 AsfInStockRings Projekt konkret umsetzen |
| 22.05.2026 | Test, Debug, Fehlerfälle, Doku |
| 23.05.2026 | Übergabe/Deployment-Puffer |

---

# Strategische Leitlinie

Luna V3 ist eine interne Integrations-Workbench.

Öffentlich erreichbar sind nur:

- freigegebene Runtime-Endpunkte
- exportierte API-Endpunkte
- bewusst abgesicherte JSON-Schnittstellen

Die komplette Workbench soll nicht unnötig öffentlich bereitgestellt werden.

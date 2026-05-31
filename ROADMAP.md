# Roadmap — Luna V3

## Stand nach 1.5.0

Luna V3 ist bis einschließlich **1.5.0** als interne Integrations-Workbench mit exportierbaren Runtime-Endpunkten ausgerichtet.

Der strategisch wichtigste Punkt ist erreicht:

```text
Luna-Workbench intern
→ Endpoint konfigurieren, testen und exportieren
→ exportierte Runtime auf Toolbox deployen
→ Consumer ruft nur HTTP JSON ab
```

Nicht Ziel ist:

```text
öffentliche Luna-Workbench
öffentliche Mapping-Administration
öffentliche Admin-Oberfläche auf der Toolbox-Subdomain
```

Der relevante Produktkern bis einschließlich 1.7.0 sind **exportierbare Endpunkte**.  
Alles andere bleibt nachgeordnet.

Der operative Fokus ist:

```text
Mapping → JSON Endpoint → Export Runtime → ZIP → Deployment → Consumer
```

---

## Zielbild bis 1.7.0

Luna soll konkret und stabil ermöglichen:

1. Workspaces und Connections verwalten.
2. Source-Daten analysieren.
3. Mapping-Regeln definieren.
4. JSON-Endpunkte aus Mappings erzeugen.
5. Endpunkte als eigenständige Runtime exportieren.
6. Exportpakete als ZIP bereitstellen.
7. Exportierte Endpunkte ohne Admin-Workbench betreiben.
8. Betrieb, Fehlerfälle und Deployments nachvollziehbar machen.

Der erste reale Integrationsfall ist:

```text
AsfInStockRings
```

Ziel-URL:

```text
https://toolbox.asf.gmbh/pim/api/isr_prices.php
```

---

## Qualitäts-Gate

Nach jeder Codex-Aufgabe müssen die verfügbaren Checks ausgeführt werden.

Pflicht:

```bash
composer dump-autoload
composer analyse
composer test
```

Bevorzugt:

```bash
composer check
```

Ein Task gilt erst als abgeschlossen, wenn:

- `composer check` grün läuft,
- oder ein verbleibender Fehler ausdrücklich dokumentiert und fachlich akzeptiert wurde.

Neue produktive Logik braucht passende Tests oder eine begründete dokumentierte Ausnahme.

---

# Abgeschlossene Foundation-Meilensteine

## 1.0.0 — Endpoint Builder und stabile Workbench

### Ergebnis

Eine stabile erste Workbench-Version für Integrationsprojekte wurde bereitgestellt.

### Kernpunkte

- Admin UI
- Workspaces
- Connections
- Schema Explorer
- Mapping Designer
- Endpoint Builder
- private Endpoint-Secrets
- saubere Trennung von Workbench und Runtime-Grundlagen

### Branch

```text
feature/1.0.0-endpoint-builder-stable-workbench
```

---

## 1.1.0 — Workbench UX, Workspaces und Mapping-Auswahl

### Ergebnis

Die tägliche Nutzung der Workbench wurde stabilisiert.

### Kernpunkte

- Dark/Light UI
- Workspace-Verwaltung
- dynamische Source-/Target-Tabellenauswahl
- stabile Admin-Oberfläche

---

## 1.1.1 — Stabilisierung und Code Quality Foundation

### Ergebnis

Das verbindliche Qualitäts-Gate wurde vorbereitet und etabliert.

### Kernpunkte

- PHPStan/PHPUnit-Basis
- Composer-Scripts
- `composer check`
- erste Basistests
- keine Secrets in Fehlerausgaben

### Branch

```text
feature/1.1.1-stabilize-before-integration
```

---

## 1.2.0 — Multi-Connection Integration Foundation

### Ergebnis

Mehrere externe Datenquellen können pro Workspace verwaltet und analysiert werden.

### Kernpunkte

- mehrere Connections pro Workspace
- Schema Explorer pro Connection
- Connection-Test über UI/CLI
- verschlüsselte Secrets
- read-only Source-Connections
- keine Klartext-Secrets in UI oder Logs

### Branch

```text
feature/1.2.0-multi-connection-integration-foundation
```

---

## 1.3.0 — Multi-Source Lookup Mapping und Value Resolver

### Ergebnis

Mappings können Werte aus Primary Source und Lookup Connections zu einem Transfer-Datensatz normalisieren.

### Kernpunkte

- `source_column`
- `static_value`
- `lookup_value`
- Key-Templates
- Lookup Connections pro Feldregel
- Transfer-Datensatz im Dry-Run
- Preisgruppen-Lookup vorbereitet
- fehlende Lookup-Werte sichtbar

### Branch

```text
feature/1.3.0-lookup-mapping-value-resolver
```

---

## 1.4.0 — JSON Endpoint Builder v2

### Ergebnis

Aus einem Mapping kann ein JSON-Endpunkt entstehen.

### Kernpunkte

- Endpoint an Workspace und Mapping binden
- HTTP GET Runtime
- Standard-JSON:
  - `success`
  - `generated_at`
  - `count`
  - `items`
- standardisiertes Fehlerformat
- Endpoint Secret
- Admin Preview
- Public Runtime getrennt von Admin
- Read Export Mapping-Modus
- Output Fields
- mehrere Source Filters
- `key_value_map_by_prefix`
- Lookup-/PDO-Cache
- Prefix-Batching
- Delete-Actions für Workspaces, Connections, Mappings und Endpoints

### Branch

```text
feature/1.4.0-json-endpoint-builder-v2
```

---

## 1.5.0 — Endpoint Export Runtime

### Ergebnis

Endpoints können als eigenständige Runtime exportiert werden, ohne die Luna-Workbench öffentlich erreichbar zu machen.

### Strategisches Ergebnis

```text
AsfInStockRings → exportierter Runtime-Endpunkt
```

Nicht:

```text
AsfInStockRings → öffentliche Luna-Workbench
```

### Kernpunkte

- Export eines Endpoint-Profils
- exportierte PHP-Runtime
- Runtime nutzt dieselben Mapping- und Lookup-Regeln
- keine Admin UI im Export
- keine Klartext-Secrets in PHP-Konfigurationen
- `.env.example`
- optionale lokale `.env` für Dev/Test
- Workspace-basierter Exportpfad:
  ```text
  storage/{workspace_slug}/exports/endpoints/{endpoint_key}/
  ```
- Admin-Button für Runtime-Export
- ZIP-Erzeugung
- ZIP-Download
- `.gitignore` für Exportartefakte
- Export kann nach `toolbox.asf.gmbh/pim/api/` deployed werden

### Zielstruktur Deployment

```text
toolbox.asf.gmbh/
└── pim/
    ├── api/
    │   └── isr_prices.php
    ├── runtime/
    ├── config/
    ├── .env
    └── manifest.json
```

### Branch

```text
feature/1.5.0-endpoint-export-runtime
```

---

# Noch offene Projekt- und Betriebsmeilensteine

## 1.6.0 — AsfInStockRings Integration Project

### Ziel

Der erste echte Luna-Integrationsfall wird als konkretes Projekt finalisiert und produktiv validiert.

1.6.0 ist **kein neuer Architekturmeilenstein**.  
Die technische Grundlage ist mit 1.4.0 und 1.5.0 bereits vorhanden.

1.6.0 dient dazu, den konkreten Endpoint **isr_prices** für **AsfInStockRings** fachlich festzuziehen, zu exportieren, zu deployen und vom WordPress-Plugin konsumierbar zu machen.

### Branch

```text
feature/1.6.0-asf-in-stock-rings-project
```

### Projekt

```text
AsfInStockRings
```

### Workspace

Bevorzugter fachlicher Name:

```text
AsfInStockRings
```

Falls der bestehende technische Workspace bereits produktiv genutzt wird, darf er beibehalten werden.  
Dann ist dies zu dokumentieren, statt unnötig umzubenennen.

### Connections

Fachlich benötigte Connections:

```text
pimcore
price_settings
stock / schmucklager
```

Falls bestehende Connection-Namen abweichen, dürfen sie beibehalten werden, solange die Exportkonfiguration korrekt und dokumentiert ist.

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

### Finales Ziel-JSON

Das WordPress-Plugin soll ein direkt konsumierbares JSON erhalten.

Mindeststruktur pro Item:

```json
{
  "model": "W001",
  "name": "Beispiel Ring W001",
  "price_group": "6",
  "price": "115",
  "pseudo_price": "230",
  "dr_quantities": {
    "48": 47,
    "50": 34
  },
  "hr_quantities": {
    "56": 25,
    "58": 31
  }
}
```

Pflichtfelder:

- `model`
- `name`
- `price_group`
- `price`
- `pseudo_price`
- `dr_quantities`
- `hr_quantities`

Optionale Felder, nur falls für AsfInStockRings benötigt:

- `material`
- `image_key`
- `active`
- `sort_order`

### Umfang

- PIMCore-Beispieldatensatz final prüfen
- Ring-Identifikationsspalte bestätigen
- `name`-Feld final bestimmen
- Filter für relevante InStock-Ringe finalisieren
- Preisgruppen-Lookup final prüfen:
  - `pricegroup_{{priceGroup}}`
  - `pricegroup_{{priceGroup}}_pseudo`
- Mengenstruktur final prüfen:
  - `dr_quantities`
  - `hr_quantities`
- Fehlende Preise sauber melden oder dokumentiert behandeln
- JSON-Struktur als Plugin-Vertrag festhalten
- Dry-Run mit echten Beispieldaten dokumentieren
- Endpoint exportieren
- ZIP-Export prüfen
- Runtime auf Toolbox deployen
- `.env` auf Toolbox sicher konfigurieren
- Plugin-Abruf testen
- Zugang absichern
- keine Secrets in Responses, Logs, Configs, Manifest oder ZIP

### Nicht-Ziele

Nicht in 1.6.0 einbauen:

- neue generische Mapping-Architektur
- Scheduler
- neue Runtime
- neue Exportpipeline
- komplexe Transformationssprache
- allgemeine Multi-Tenant-Plattform
- OpenAPI/Swagger
- Reporting-Engine
- öffentliche Workbench

### Akzeptanzkriterien

- Endpoint liefert nur relevante InStock-Ringe
- `model`, `name`, `price_group`, `price`, `pseudo_price`, `dr_quantities` und `hr_quantities` sind enthalten
- Preisgruppen-Lookup funktioniert
- Fehlende Preise werden sauber gemeldet oder dokumentiert
- JSON kann direkt von AsfInStockRings konsumiert werden
- Export-ZIP kann deployed werden
- Endpoint läuft auf:
  ```text
  https://toolbox.asf.gmbh/pim/api/isr_prices.php
  ```
- Zugang ist abgesichert
- keine Secrets erscheinen in Response, Logs, Manifest, ZIP oder Fehlermeldungen
- ISR-Mapping und Preisgruppen-Lookup sind durch PHPUnit-Tests oder dokumentierte Dry-Run-Prüfungen abgesichert
- `composer check` läuft grün

---

## 1.7.0 — Endpoint Hardening, Logging und Betrieb

### Ziel

Der exportierte Endpoint und die Luna-Runtime sollen betriebssicher und nachvollziehbar werden.

1.7.0 ist wünschenswert für den stabilen Betrieb, aber nicht kritisch für die erste fachliche Auslieferung, solange 1.6.0 erfolgreich deployed und getestet ist.

### Branch

```text
feature/1.7.0-endpoint-hardening-operations
```

### Umfang

- Request Logging ohne Secrets
- Endpoint Audit Log
- Fehlerstatistik
- Anzeige der letzten erfolgreichen Ausführung
- Anzeige des letzten fehlgeschlagenen Laufs
- Exportstatus in der Workbench
- Cache-TTL pro Endpoint
- Manuelles Cache-Leeren
- JSON-Healthcheck
- optional API-Key-/Secret-Rotation
- Betriebsdokumentation
- Deployment-Dokumentation
- Recovery-Dokumentation
- Debug-Modus klar von Produktivmodus trennen

### JSON-Healthcheck

Vorgesehener Healthcheck:

```text
/pim/api/health.php
```

oder endpoint-spezifisch:

```text
/pim/api/isr_prices.health.php
```

Minimalantwort:

```json
{
  "success": true,
  "status": "ok",
  "generated_at": "2026-05-31T12:00:00+02:00"
}
```

### Logging-Grundsätze

Logs dürfen enthalten:

- Zeitpunkt
- Endpoint-Key
- HTTP-Status
- Laufzeit
- Item-Count
- Fehlercode
- generische Fehlermeldung

Logs dürfen nicht enthalten:

- Passwörter
- Secrets
- Tokens
- API-Keys
- DSNs
- vollständige SQL-Queries mit sensiblen Werten
- `.env`-Werte
- Stacktraces im Produktivmodus

### Akzeptanzkriterien

- Fehler sind nachvollziehbar
- Endpoint kann per Healthcheck geprüft werden
- Cache kann aktiviert und deaktiviert werden
- Luna zeigt letzten Laufstatus
- keine sensiblen Daten in Logs
- keine sensiblen Daten in Responses
- Doku reicht aus, um den Endpoint erneut zu deployen
- Produktivbetrieb ist ohne Admin-Zugriff auf die Workbench möglich
- relevante Betriebs- und Sicherheitslogik ist durch PHPUnit-Tests abgesichert
- `composer check` läuft grün

---

# MVP-Definition

Der MVP ist nicht, dass Luna vollständig perfekt ist.

Der MVP ist:

```text
Luna kann den konkreten ISR-Endpoint kontrolliert erzeugen, exportieren und betreiben.
```

## MVP erfüllt durch 1.5.0/1.6.0, wenn:

- relevante DB-Connections vorhanden sind
- Tabellen und Samples geprüft wurden
- Mapping definiert ist
- Preisgruppen-Lookup funktioniert
- JSON Preview funktioniert
- Endpoint bereitgestellt oder exportiert ist
- Export-ZIP erzeugt werden kann
- WordPress-Plugin den Endpoint abrufen kann
- Qualitäts-Gate grün läuft:
  ```bash
  composer check
  ```

## Nicht zwingend für MVP

- perfekter No-Code-Designer
- komplexe Transformationssprache
- Scheduler
- schöne Report Engine
- vollständige Multi-Tenant-Logik
- generische Endpoint-Bibliothek für alle Eventualitäten

---

# Strategische Leitlinie ab 1.6.0

Luna wird nicht beliebig weiter vergrößert.  
Bis einschließlich 1.7.0 zählt nur, dass exportierbare Endpunkte stabil, sicher und reproduzierbar funktionieren.

Priorität:

1. `isr_prices` fachlich korrekt
2. exportierbare Runtime stabil
3. ZIP und Deployment reproduzierbar
4. keine Secrets im Export
5. Betrieb nachvollziehbar
6. keine unnötige neue Architektur

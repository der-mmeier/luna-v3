# Roadmap - Luna V3

Luna V3 ist eine interne Integrations-Workbench. Die Workbench bleibt privat; öffentlich erreichbar sind nur bewusst freigegebene Runtime- oder Export-Endpunkte.

Die fachliche Schichtdefinition ist in `docs/ARCHITECTURE.md` dokumentiert. Diese Roadmap beschreibt, welche Schicht in welcher Version umgesetzt wird.

## Qualitäts-Gate

Nach jeder Codex-Aufgabe müssen die verfügbaren Checks ausgeführt werden.

Bevorzugt:

```bash
composer check
```

Wenn `composer check` nicht vollständig verfügbar ist, gelten mindestens:

```bash
composer dump-autoload
composer analyse
composer test
```

Neue produktive Logik braucht passende Tests oder eine begründete dokumentierte Ausnahme.

---

## Aktive Roadmap

### 2.0.0 - WooCommerce HPOS Integration

Status: abgeschlossen

Umfang:

- WooCommerce `>= 10.7.0` validieren
- HPOS authoritative prüfen
- HPOS Data Caching nicht als produktiven Datenpfad verwenden
- Initialimport aus HPOS in Luna-Staging-/Transfer-Tabellen
- Transfer Queue
- Transfer Runner
- Transfer Runs als Betriebsnachweis
- Webhook-Grundlage
- verständliche Webhook-UI
- lokale Secret-Konfiguration
- keine automatische Webhook-Erstellung in WooCommerce
- keine WooCommerce-Schreibzugriffe

Ergebnis:

```text
WooCommerce HPOS -> Luna-Staging -> Transfer Queue / Runs
```

### 2.1.0 - Transferbetrieb + Export Layer

Status: in Umsetzung

Ziel:

Aus dem erfolgreichen WooCommerce-Import wird eine betriebssichere, exportierbare Integrationsschicht.

Umfang Transferbetrieb:

- Job-Ausführung kontrollieren
- Batch-Ausführung
- Retry für fehlgeschlagene Queue-Einträge
- Fehler je Queue/Order sichtbar machen
- Transfer-Historie anzeigen
- letzten erfolgreichen Lauf anzeigen
- idempotente Wiederholung sicherstellen

Umfang Export Layer:

- geschützter Export aus Luna-Staging-/Transferdaten
- kein Export direkt aus WooCommerce HPOS
- keine WooCommerce-REST-API als Exportquelle
- keine Legacy-Quelle über `wp_posts`/`wp_postmeta`
- Exportprofile für WooCommerce-Schichten:
  - `orders`
  - `order_addresses`
  - `order_items`
  - `order_itemmeta_raw`
  - `order_meta_raw`
  - `orders_full`
- Exportläufe protokollieren
- Export-Endpunkte über Token/HMAC schützen
- Watermark-/Delta-Export vorbereiten
- Export-UI und CLI bereitstellen

Ergebnis:

```text
Luna-Staging / TransferDB -> geschützter JSON-Export
```

Nicht-Ziele:

- kein Afterbuy-Adapter
- kein konkreter Zielsystem-Adapter
- keine WooCommerce-Schreibzugriffe
- kein Ändern von WooCommerce-Bestellstatus
- keine produktive Nutzung von HPOS Data Caching
- keine automatische Webhook-Erstellung in WooCommerce
- kein vollständiges API-Writer-System
- keine finale Retourenlogik

### 2.2.0 - Zielsystem-Adapter

Status: geplant

Ziel:

Auf Basis stabiler Datasets, Transfers und Exporte externe Zielsysteme anbinden.

Beispiele:

- Transferdatenbank -> Afterbuy
- Transferdatenbank -> weitere Systeme
- später API-Writer

Wichtige Abgrenzung:

Zielsystem-Adapter werden erst gebaut, wenn Dataset Sources, Transfer Layer, Transferbetrieb und Export Layer stabil sind.

---

## Historie

### 1.5.0 / 1.6.0 - MVP-Fundament

Status: abgeschlossen / Grundlage erfüllt

Kern:

- JSON Endpoint Builder
- Endpoint Export Runtime
- Admin-Export
- ZIP-Erzeugung und Download
- exportierbare Runtime ohne öffentliche Workbench
- AsfInStockRings / `isr_prices` als erster realer Referenzfall

MVP-Definition aus dieser Phase:

```text
Luna kann den konkreten ISR-Endpoint kontrolliert erzeugen, exportieren und betreiben.
```

### 1.7.0 - Stabilisierung bestehender Workbench-Funktionen

Status: historisch / abgeschlossen

Kern:

- exportierbare Endpunkte stabilisieren
- Mapping-/Endpoint-UI absichern
- Export nachvollziehbar machen
- keine unnötige neue Architektur

### 1.7.1 - Layer Roadmap und Versionsplanung

Status: abgeschlossen

Kern:

- Schichten dokumentieren:
  - Connections liefern Rohdaten.
  - Mappings erzeugen Datasets.
  - Endpoints veröffentlichen Datasets.
  - Transfers konsumieren Datasets.
  - Writers schreiben Transfer-Pläne.
  - Jobs führen Transfers nachvollziehbar aus.

### 1.8.0 - Dataset Sources

Status: abgeschlossen

Kern:

- Endpoint-/Mapping-Ergebnisse intern als Dataset Sources verfügbar machen
- Output-Felder sichtbar machen
- Dataset Preview/Dry-Run ermöglichen
- keine Schreiblogik
- keine Parent-/Child-Transfers

### 1.9.0 - Transfer Layer v1: Single Target Table

Status: abgeschlossen

Kern:

- Dataset als Transfer Source verwenden
- Target Connection und Target Table auswählen
- Dataset-Felder Zielspalten zuordnen
- Insert/Update/Upsert
- Upsert Key
- Dry-Run mit Write Plan
- echter Run in eine einzelne Ziel-Tabelle

### 2.0.0 - Transfer Layer v2 / WooCommerce HPOS

Status: abgeschlossen

Die ursprüngliche Planung für Parent/Child-Transfers wurde mit dem WooCommerce-HPOS-Import konkretisiert. Der produktive Schwerpunkt liegt auf WooCommerce-Bestellungen aus HPOS, deren Header, Adressen, Items und Meta in Luna-Staging-Tabellen importiert werden.

---

## Strategische Leitlinie

Luna wird nicht beliebig vergrößert.

Priorität:

1. produktive Integrationsfälle fachlich korrekt lösen
2. Daten über klare Schichten nachvollziehbar bewegen
3. Exporte schützen und protokollieren
4. keine Secrets in Responses, Logs, Manifesten oder Exporten
5. Betrieb wiederholbar und idempotent machen
6. keine unnötige neue Architektur einführen

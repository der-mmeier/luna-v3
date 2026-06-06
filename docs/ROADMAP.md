# Luna V3 Roadmap

## Kurzüberblick

Luna V3 bleibt die zentrale Serverkomponente für Integrationen.

Die aktuelle Planung trennt die Schichten bewusst:

```text
WooCommerce -> Luna Ingestion -> TransferDB/Staging -> Transferbetrieb -> Export -> Zielsystemadapter
```

Die fachliche Architektur ist in `docs/ARCHITECTURE.md` beschrieben.

Wichtige Grundsätze:

- WooCommerce-Webhooks rufen Luna auf.
- Luna verarbeitet Webhook-Events und schreibt in die Luna-TransferDB/Staging-Schicht.
- Export-Endpunkte gehören zu Luna.
- Export liest aus Luna-TransferDB/Staging, nicht direkt aus WooCommerce.
- Webhook und Export haben unterschiedliche Richtungen.
- Es entsteht kein WP-Plugin, das lokale Luna-Transferdaten im Shop speichert.

## Abgeschlossene Versionen

### v2.0.0 - WooCommerce HPOS Integration

Status: abgeschlossen

Umfang:

- WooCommerce `>= 10.7.0` Validierung
- HPOS authoritative Prüfung
- HPOS Data Caching nicht produktiv genutzt
- HPOS Initialimport
- Luna-Staging-/TransferDB-Tabellen für WooCommerce
- Transfer Queue
- Transfer Runner
- Transfer Runs
- Webhook-Grundlage
- Webhook-UI für lokale Prüfkonfiguration und Secrets
- keine WooCommerce-Schreibzugriffe

Abgrenzung:

- kein Zielsystem-Adapter
- kein Afterbuy
- kein generischer Export-Layer
- kein WP-Plugin mit eigener Transferdatenhaltung

## Aktive Version

### v2.1.0 - Roadmap & Architecture Reset

Status: in Umsetzung

Ziel:

Die Architektur und Roadmap werden bereinigt, damit Ingestion, TransferDB, Transferbetrieb, Export und Zielsystemadapter nicht mehr vermischt werden.

Umfang:

- `docs/ROADMAP.md` neu/konsolidiert
- `docs/ARCHITECTURE.md` neu/konsolidiert
- klare Schichtentrennung
- klare Versionsfolge ab v2.0.0
- alte widersprüchliche Roadmap-Abschnitte bereinigt oder in Historie verschoben

Nicht-Ziele:

- keine neuen PHP-Features
- keine Migrationen
- keine UI-Änderungen
- keine Endpoint-Implementierung
- kein Export-Layer in dieser Aufgabe
- kein Transferbetrieb-Ausbau in dieser Aufgabe

## Geplante Versionen

### v2.2.0 - WooCommerce Ingestion Runtime

Status: geplant

Ziel:

Der WooCommerce -> Luna Eingang wird betriebssicher gemacht.

Umfang:

- öffentlicher Luna-Serverendpunkt für WooCommerce-Webhooks
- Delivery URL aus Luna korrekt generieren
- Secrets/HMAC-Signaturprüfung
- Webhook-Events protokollieren
- `order.created`, `order.updated` und `order.deleted` verarbeiten
- Event -> Queue-Eintrag
- Queue-Eintrag -> HPOS-Nachladen -> TransferDB
- Statusänderungen und Zahlungsstatusänderungen zeitnah übernehmen
- lokale Webhook-Konfiguration in Luna
- optionale manuelle Anleitung für WooCommerce-Webhook-Anlage

Wichtige Architekturregel:

```text
WooCommerce ruft Luna auf.
Luna schreibt in die TransferDB.
Es gibt keine Luna-TransferDB im WooCommerce-Plugin.
```

Nicht-Ziele:

- kein Afterbuy
- kein Export nach außen
- kein Schreiben in WooCommerce
- kein WP-Plugin als Pflichtkomponente

### v2.3.0 - Transferbetrieb

Status: geplant

Ziel:

Transfers im Betrieb kontrollieren.

Umfang:

- Job-Ausführung
- Batching
- Retry
- Fehler je Datensatz / Order
- Transfer-Historie
- letzter erfolgreicher Lauf
- idempotente Wiederholung
- Queue-Betrieb
- Runner/Worker-Betrieb
- Monitoring-Ansicht

Abgrenzung:

Transferbetrieb arbeitet auf Luna-Queue und Luna-TransferDB. Transferbetrieb ist nicht der Export-Layer.

### v2.4.0 - Export Layer

Status: geplant

Ziel:

Importierte und stabilisierte Luna-Schichten über geschützte Export-Endpunkte bereitstellen.

Umfang:

- Exportprofile
- JSON-Export
- geschützte Export-Endpunkte
- Token/HMAC-Auth
- Export-Runs
- Export-Historie
- Watermark/Delta-Export
- WooCommerce-Exportprofile:
  - `orders`
  - `order_addresses`
  - `order_items`
  - `order_meta_raw`
  - `order_itemmeta_raw`
  - `orders_full`

Wichtige Architekturregel:

```text
Export liest aus Luna-TransferDB/Staging.
Export liest nicht direkt aus WooCommerce.
Export schreibt nicht nach WooCommerce.
Export ist Luna -> extern.
```

Nicht-Ziele:

- kein Afterbuy-Adapter
- kein aktives Schreiben in externe Systeme
- keine WooCommerce-Webhook-Ingestion

### v2.5.0 - Zielsystem-Adapter

Status: geplant

Ziel:

Auf Basis stabiler TransferDB und Export-Schicht externe Zielsysteme anbinden.

Beispiele:

- TransferDB/Export -> Afterbuy
- TransferDB/Export -> weitere Systeme
- später API-Writer

Abgrenzung:

Zielsystem-Adapter werden erst gebaut, wenn Ingestion, Transferbetrieb und Export stabil sind.

### v2.6.0 - Optionales WooCommerce Helper Plugin

Status: optional / später

Ziel:

Falls nötig, ein kleines WooCommerce-Helfer-Plugin zur einfacheren Konfiguration bereitstellen.

Erlaubt:

- Delivery URL anzeigen
- Secret-Feld verwalten
- Webhook-Anlage im Shop erleichtern
- Status anzeigen

Nicht erlaubt:

- keine eigene TransferDB im Shop
- keine lokale Kopie aller Luna-Transferdaten im Plugin
- keine fachliche Verarbeitung im Shop, die eigentlich nach Luna gehört

Architekturregel:

Das Plugin ist optionaler Konfigurationshelfer. Die zentrale Integration bleibt Luna.

## Historie / archivierte Roadmap-Abschnitte

### v1.5.0 / v1.6.0 - MVP-Fundament

Status: abgeschlossen / Grundlage erfüllt

Kern:

- JSON Endpoint Builder
- Endpoint Export Runtime
- Admin-Export
- ZIP-Erzeugung und Download
- exportierbare Runtime ohne öffentliche Workbench
- AsfInStockRings / `isr_prices` als erster realer Referenzfall

Historische MVP-Definition:

```text
Luna kann den konkreten ISR-Endpoint kontrolliert erzeugen, exportieren und betreiben.
```

### v1.7.0 - Stabilisierung bestehender Workbench-Funktionen

Status: historisch / abgeschlossen

Kern:

- exportierbare Endpunkte stabilisieren
- Mapping-/Endpoint-UI absichern
- Export nachvollziehbar machen
- keine unnötige neue Architektur

### v1.8.0 - Dataset Sources

Status: abgeschlossen

Kern:

- Endpoint-/Mapping-Ergebnisse intern als Dataset Sources verfügbar machen
- Output-Felder sichtbar machen
- Dataset Preview/Dry-Run ermöglichen
- keine Schreiblogik

### v1.9.0 - Transfer Layer v1: Single Target Table

Status: abgeschlossen

Kern:

- Dataset als Transfer Source verwenden
- Target Connection und Target Table auswählen
- Dataset-Felder Zielspalten zuordnen
- Insert/Update/Upsert
- Upsert Key
- Dry-Run mit Write Plan
- echter Run in eine einzelne Ziel-Tabelle

## Nicht-Ziele der aktuellen Roadmap

- keine öffentliche Luna-Workbench
- keine WooCommerce-Schreibzugriffe
- keine produktive Nutzung von HPOS Data Caching
- keine lokale Luna-TransferDB in einem WordPress-Plugin
- keine Vermischung von Webhook-Ingestion und Export
- keine Zielsystemadapter vor stabiler Ingestion, stabilem Transferbetrieb und stabilem Export

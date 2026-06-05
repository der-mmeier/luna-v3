# Luna V3 Architektur

## 1. Überblick

Luna ist die zentrale Integrations-Workbench und Serverkomponente.

Luna liest Quellsysteme, normalisiert Daten in Transfer-/Staging-Schichten, nimmt Webhooks entgegen, stellt geschützte Exporte bereit und kann später Zielsystemadapter betreiben.

Für WooCommerce gilt:

- WooCommerce liefert Rohdaten über HPOS.
- WooCommerce ruft Luna per Webhook auf.
- Luna prüft Webhook-Signaturen.
- Luna lädt betroffene Bestellungen aus HPOS nach.
- Luna schreibt normalisierte Daten in die Luna-TransferDB/Staging-Schicht.
- Luna stellt spätere Exporte aus dieser stabilisierten Schicht bereit.

Luna schreibt nicht in WooCommerce.

## 2. Schichtenmodell

### Source Layer

Der Source Layer beschreibt reale Quellsysteme.

Beispiele:

- WooCommerce HPOS
- Pimcore-Datenbank
- Lagerdatenbank
- externe APIs

Quellsysteme liefern Rohdaten. Sie kennen keine Luna-Zielstruktur.

### Ingestion Layer

Der Ingestion Layer ist der Eingang von außen nach Luna.

Bei WooCommerce bedeutet das:

```text
WooCommerce Webhook
  -> Luna Webhook Endpoint
  -> Signaturprüfung
  -> Event Log
  -> Transfer Queue
```

Ingestion erzeugt keine fachlichen Zielobjekte direkt. Sie erzeugt verifizierte Ereignisse und Queue-Einträge.

### TransferDB / Staging Layer

Die TransferDB/Staging-Schicht ist die Luna-interne stabilisierte Datenhaltung.

Hier landen normalisierte WooCommerce-Daten, zum Beispiel:

- Order Header
- Order Addresses
- Order Items
- Order Meta Raw
- Order Item Meta Raw

Diese Schicht ist die Quelle für Export und spätere Zielsystemadapter.

### Transfer Operations Layer

Der Transferbetrieb steuert Queue, Runner, Retry, Fehler und Historie.

Er beantwortet:

- Welche Queue-Einträge sind offen?
- Welche Läufe waren erfolgreich?
- Welche Order ist fehlgeschlagen?
- Kann ein Lauf idempotent wiederholt werden?

Transferbetrieb ist nicht der Export-Layer.

### Export Layer

Der Export Layer stellt Daten aus Luna heraus bereit.

```text
Luna TransferDB/Staging
  -> geschützter Luna Export Endpoint
  -> externer Verbraucher
```

Export liest nicht direkt aus WooCommerce. Export schreibt nicht nach WooCommerce. Export ist Luna -> extern.

### Target Adapter Layer

Zielsystemadapter schreiben später aktiv in externe Systeme.

Beispiele:

- TransferDB/Export -> Afterbuy
- TransferDB/Export -> weitere Systeme
- später API-Writer

Zielsystemadapter sind nicht Teil des Exports. Sie bauen auf stabiler Ingestion, TransferDB, Transferbetrieb und Export-Schicht auf.

### Optional Helper Plugin Layer

Ein optionales WooCommerce Helper Plugin darf später nur Konfiguration und Bedienung erleichtern.

Erlaubt:

- Delivery URL anzeigen
- Secret-Feld verwalten
- Webhook-Anlage im Shop erleichtern
- Status anzeigen

Nicht erlaubt:

- eigene Luna-TransferDB im Shop speichern
- lokale Kopie aller Luna-Transferdaten im Plugin
- fachliche Verarbeitung im Shop, die nach Luna gehört

Das Plugin ist ein optionaler Konfigurationshelfer. Die zentrale Integration bleibt Luna.

## 3. WooCommerce-Architektur

### Initialimport

```text
WooCommerce HPOS
  -> Luna Initialimport
  -> Luna TransferDB/Staging
```

Der Initialimport liest aus HPOS und schreibt in Luna-Staging-Tabellen. WooCommerce wird nicht beschrieben.

### Laufende Änderungen

```text
WooCommerce Webhook
  -> Luna Webhook Endpoint
  -> Signaturprüfung
  -> Event Log
  -> Transfer Queue
  -> HPOS-Nachladen
  -> TransferDB/Staging
```

WooCommerce-Webhooks zeigen auf Luna. Nach gültiger Signatur schreibt Luna ein Event und einen Queue-Eintrag. Der Runner liest anschließend die betroffene Bestellung frisch aus HPOS.

### Export

```text
Luna Export Endpoint
  -> liest TransferDB/Staging
  -> liefert geschütztes JSON an externe Verbraucher
```

Export-Endpunkte gehören zu Luna. Sie sind keine WooCommerce-Webhooks und keine Zielsystemadapter.

## 4. Was nicht passiert

- Luna schreibt nicht in WooCommerce.
- Export liest nicht direkt aus WooCommerce.
- Webhook ist kein Export.
- Export ist kein Webhook.
- Zielsystemadapter sind nicht Teil des Exports.
- Ein optionales WP-Plugin darf keine eigene TransferDB im Shop speichern.
- Es entsteht kein WP-Plugin, das lokal im Shop eigene Luna-Transferdaten speichert.
- Legacy `wp_posts`/`wp_postmeta` ist keine produktive WooCommerce-Order-Quelle für Luna.

## 5. Begriffsdefinitionen

### Webhook / Ingestion

WooCommerce ruft einen Luna-Serverendpunkt auf. Luna prüft Secret/HMAC, speichert ein Event und erzeugt einen Queue-Eintrag.

### TransferDB / Staging

Luna-interne stabilisierte Datenhaltung. Sie enthält normalisierte Daten aus Quellen wie WooCommerce HPOS.

### Transfer Queue

Warteschlange für konkrete Nachlade- oder Importaufträge. Ein Webhook erzeugt nur Queue-Arbeit, keine Zielobjekte.

### Transfer Run

Protokoll eines ausgeführten Queue-/Importlaufs mit Status, Zählern, Fehlern und Zeiten.

### Transferbetrieb

Betriebssteuerung für Queue, Runner, Retry, Fehler, Historie und Monitoring.

### Export Profile

Konfiguration, welche Staging-Daten in welchem Format und mit welcher Authentifizierung exportiert werden dürfen.

### Export Endpoint

Geschützter Luna-Endpunkt, der Daten aus TransferDB/Staging als JSON bereitstellt.

### Target Adapter

Spätere Schicht, die Daten aktiv in externe Zielsysteme schreibt.

### Optional Helper Plugin

Optionales WooCommerce-Plugin zur Konfigurationserleichterung. Es ersetzt Luna nicht und speichert keine Luna-TransferDB im Shop.

## 6. Sicherheitsmodell

- WooCommerce-Webhooks werden per Secret/HMAC geprüft.
- Export-Endpunkte werden per Token/HMAC geschützt.
- Secrets werden nicht im Klartext angezeigt.
- Secrets werden nicht geloggt.
- Öffentliche Endpunkte dürfen nie ungeschützt sein.
- Unsignierte Webhooks erzeugen keine Transferjobs.
- Export-Endpunkte liefern keine Stacktraces und keine lokalen Pfade.
- WooCommerce-Connections werden für den Import read-only behandelt.

## 7. Versionierte Architekturentscheidungen

### v2.0.0: WooCommerce HPOS Integration

WooCommerce `>= 10.7.0`, HPOS authoritative, Initialimport, Staging-Tabellen, Queue, Runner, Transfer Runs und Webhook-Grundlage.

### v2.1.0: Roadmap/Architecture Reset

Roadmap und Architektur werden bereinigt. Ingestion, TransferDB, Transferbetrieb, Export und Zielsystemadapter werden fachlich getrennt.

### v2.2.0: Ingestion Runtime

WooCommerce -> Luna Webhook-Eingang wird betriebssicher gemacht.

### v2.3.0: Transferbetrieb

Queue, Runner, Retry, Fehler je Order, Historie und Monitoring werden robust betrieben.

### v2.4.0: Export Layer

Luna stellt stabilisierte Daten aus TransferDB/Staging über geschützte Export-Endpunkte bereit.

### v2.5.0: Zielsystem-Adapter

Externe Zielsysteme werden auf Basis stabiler TransferDB-/Export-Schichten angebunden.

### v2.6.0: Optionales WooCommerce Helper Plugin

Optionaler Konfigurationshelfer für WooCommerce. Keine eigene Transferdatenhaltung im Shop.

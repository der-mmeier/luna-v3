# Luna V3 Roadmap

Stand: nach `v2.3.0`  
Ziel dieses Dokuments: Die Versionsfolge sauber ordnen, widersprüchliche alte Planungen bereinigen und die nächsten Schritte so definieren, dass Luna nicht in WooCommerce-, Webhook-, Export- und Transfer-Sonderlogik zerfällt.

---

## 1. Produktprinzip

Luna V3 ist eine Open-Source-Workbench für Integrationen, Mappings, Datenflüsse, Endpunkte und spätere Prozessausführung.

Der Kern ist nicht „ein einzelner Endpoint“ und auch nicht „nur WooCommerce“, sondern:

```text
Idee -> Planung -> Umsetzung
```

Technisch bedeutet das:

```text
Workspace
  -> Connections
  -> Datasets
  -> Mappings
  -> Endpoints
  -> Export Packages
  -> Processes
  -> Triggers
  -> Adapters / Target Actions
```

Ein Endpoint ist nur eine Auslieferungsform. Ein Transfer ist eine Aktion. Ein Webhook ist nur ein externer Trigger. Afterbuy, ERP, WooCommerce, Amazon, PIM und Lager sind Ziel- oder Quellsysteme, aber keine eigenen Architekturwelten.

---

## 2. Grundregeln der Architektur

Diese Regeln gelten für alle kommenden Versionen:

- Luna Core bleibt generisch.
- WooCommerce, Afterbuy, ERP, Amazon, PIM und Lager werden als Adapter, Prozesse oder konkrete Module gedacht.
- Ein Endpoint liefert Daten aus Luna heraus.
- Ein Webhook ruft Luna von außen auf und startet später einen Prozess.
- Ein Prozess beschreibt eine ausführbare Abfolge von Schritten.
- Ein Trigger beschreibt, wodurch ein Prozess gestartet wird.
- Ein Adapter beschreibt, wie Luna mit einem Ziel- oder Quellsystem spricht.
- Exportpakete enthalten keine Secrets.
- Exportpakete beschreiben Konfiguration, Schema, Mapping, Endpoint/Process und Target-Metadaten.
- Deployment Targets beschreiben Umgebungen und öffentliche URLs, aber keine Zugangsdaten.
- PRO-/Lizenzlogik wird höchstens durch Metadaten vorbereitet, aber nicht hart in den Core eingebaut.

---

## 3. Aktueller Versionsstand

| Version | Status | Schwerpunkt |
|---|---|---|
| `v1.4.0` | abgeschlossen | JSON Endpoint Builder v2 |
| `v1.5.0` | abgeschlossen | Mapping-Erweiterungen / `first_non_empty` |
| `v1.6.0` | abgeschlossen | Integration Modules / Export CLI Foundation |
| `v1.7.0` | abgeschlossen | Dataset-Schicht |
| `v1.8.0` | abgeschlossen | Dataset UI / Runtime |
| `v1.9.0` | abgeschlossen | Transfer Layer v1 / Single Target Table / Upsert |
| `v2.0.0` | abgeschlossen | WooCommerce-/Transfer-Grundlagen aus Main |
| `v2.1.0` | abgeschlossen | Roadmap-/Architektur-Bereinigung als Übergang |
| `v2.2.0` | abgeschlossen | Deployment Targets & Endpoint Export Packages |
| `v2.3.0` | abgeschlossen | Process Runtime Foundation |
| `v2.4.0` | nächster Meilenstein | Trigger Layer |

---

## 4. Abgeschlossene Versionen

### v1.4.0 - JSON Endpoint Builder v2

Status: abgeschlossen

Kern:

- Endpoints an Workspaces/Mappings anbinden.
- GET-JSON-Endpunkte erzeugen.
- Endpoint-Ausgabe mit `success`, `generated_at`, `count` und `items`.
- Delete-Funktionen für zentrale Entitäten ergänzen.
- Performance der Endpoint-Ausgabe stabilisieren.
- ISR-Preis-/Bestandsendpoint als realer Referenzfall.

Abgrenzung:

- keine generische Prozesslaufzeit.
- keine Webhook-Verarbeitung.
- keine Zielsystemadapter.

---

### v1.5.0 - Mapping-Erweiterungen

Status: abgeschlossen

Kern:

- `first_non_empty` als Mapping-Regel.
- Berechnete Felder als Template-Quellen nutzbar machen.
- ISR-Sonderfälle abbildbar machen, ohne Quellsysteme zu verändern.

Abgrenzung:

- keine neue Runtime-Schicht.
- keine Zielsystemadapter.

---

### v1.6.0 - Integration Modules / Export CLI Foundation

Status: abgeschlossen

Kern:

- Grundlage für exportierbare Integrationsmodule.
- CLI-Struktur für Integrationsexporte.
- Manifest-Grundlagen.
- Modulregistrierung und Export-Runtime vorbereiten.

Abgrenzung:

- noch keine vollständige Process Runtime.
- noch keine Trigger-Schicht.

---

### v1.7.0 - Dataset-Schicht

Status: abgeschlossen

Kern:

- Dataset-Konzepte einführen.
- Ergebnisse von Mappings/Endpoints intern als Datenquellen nutzbar machen.
- Grundlage für spätere Transfers und Prozesse schaffen.

Abgrenzung:

- keine Schreiblogik als Hauptziel.
- keine Prozess-Orchestrierung.

---

### v1.8.0 - Dataset UI / Runtime

Status: abgeschlossen

Kern:

- Dataset-Endpunkte sichtbar machen.
- Dataset Preview/Dry-Run ermöglichen.
- Output-Felder sichtbar machen.
- Dataset-Ergebnisse besser prüfbar machen.

Abgrenzung:

- keine Zielsystemadapter.
- keine Prozesslaufzeit.

---

### v1.9.0 - Transfer Layer v1: Single Target Table / Upsert

Status: abgeschlossen

Kern:

- Dataset als Transfer Source verwenden.
- Target Connection und Target Table auswählen.
- Dataset-Felder Zielspalten zuordnen.
- Insert/Update/Upsert unterstützen.
- Upsert-Key definieren.
- Dry-Run mit Write Plan.
- echter Run in eine einzelne Ziel-Tabelle.

Abgrenzung:

- kein allgemeiner Prozess-Builder.
- kein mehrstufiger Prozess.
- kein externer API-Adapter.
- keine Webhook Runtime.

---

### v2.0.0 - WooCommerce-/Transfer-Grundlagen aus Main

Status: abgeschlossen

Kern:

- WooCommerce-nahe Export-/Transfer-Grundlagen aus dem Main-Zweig übernehmen.
- Bestehende CLI-Kommandos wie `export:woocommerce:list` und `export:woocommerce:run` berücksichtigen.
- Bestehende Transfer- und Exportlogik nicht durch neue Roadmap-Punkte überschreiben.

Abgrenzung:

- WooCommerce bleibt ein konkreter Anwendungsfall, nicht die Kernarchitektur.
- keine Vermischung von WooCommerce-Sonderlogik mit generischer Luna Runtime.

Hinweis:

Diese Version ist als bestehender Main-Stand zu behandeln. Spätere generische Prozess- und Adapterlogik darf vorhandene WooCommerce-Bausteine integrieren, aber nicht blind ersetzen.

---

### v2.1.0 - Roadmap & Architecture Reset

Status: abgeschlossen

Kern:

- Roadmap und Architektur wieder konsolidieren.
- Widersprüchliche Planungen entfernen.
- Klare Schichtentrennung herstellen.
- WooCommerce, Afterbuy, ERP und Webhooks wieder als Spezialfälle der generischen Luna-Architektur einordnen.

Abgrenzung:

- keine großen PHP-Features.
- keine neue Runtime-Schicht.
- keine PRO-/Lizenzprüfung.

---

### v2.2.0 - Deployment Targets & Endpoint Export Packages

Status: abgeschlossen

Ziel:

Luna kann Endpoints nicht nur ausführen, sondern mit korrekten Ziel-URLs beschreiben und als reproduzierbares, secret-freies Exportpaket ausgeben.

Kern:

- Deployment Targets je Workspace.
- Environment-Typen wie `local`, `staging`, `production`.
- Public Base URL.
- Endpoint Base URL.
- optionale Webhook Base URL als Metadatum.
- Production-Validierung gegen `localhost` und `127.0.0.1`.
- Endpoint-Detailansicht mit Exportpaket-Aktion.
- Target-Auswahl beim Endpoint-Export.
- Exportpaket mit:
  - `manifest.json`
  - `endpoint.json`
  - `mapping.json`
  - `schema.json`
  - `checksums.json`
  - `README.md`
- Exportpakete unter `storage/exports/...`.
- Exportpakete enthalten keine Secrets.
- Exportpakete beschreiben die produktive URL, sofern ein Target gewählt wurde.
- Legacy-/öffentliche Endpoint-Pfade müssen korrekt beschreibbar sein.

Definition of Done:

- `composer check` ist grün.
- Migrationen laufen sauber.
- Deployment Target für den relevanten Workspace kann angelegt werden.
- Production Target enthält kein `localhost`.
- ISR-Endpoint kann mit Target exportiert werden.
- Exportpaket enthält Manifest, Endpoint, Mapping, Schema, Checksums und README.
- Exportpaket enthält keine `.env`-Werte, Passwörter, Tokens oder API-Keys.
- Generierte Exportpakete werden nicht versehentlich committed.

Abgrenzung:

- keine Process Runtime.
- keine Webhook Runtime.
- keine WooCommerce-Schreibzugriffe.
- keine Afterbuy-/ERP-Adapter.
- keine PRO-/Lizenzserver-Prüfung.
- keine zentrale Luna-Hub-Kommunikation.

---

## 5. Abgeschlossene Version

### v2.3.0 - Process Runtime Foundation

Status: abgeschlossen

Ziel:

Luna kann ausführbare Prozesse beschreiben, manuell oder per CLI starten, protokollieren und nachvollziehbar auswerten.

Ein Prozess ist eine kontrollierte Ausführungseinheit. Trigger-Typen wie API, Schedule und Webhook bleiben spätere Konzepte; in v2.3.0 sind nur manuelle Ausführung und CLI-Ausführung produktiv nutzbar.

Umgesetzt:

- Migrationen für `luna_processes`, `luna_process_steps`, `luna_process_runs` und `luna_process_run_logs`.
- Prozessdefinitionen mit Workspace, Name, Key, Status, Beschreibung und Standardmodus.
- Prozess-Schritte mit Position, Step-Typ, Mapping-Referenz, Aktiv-Flag und optionalem `continue_on_error`.
- Erster real ausführbarer Step-Typ: `mapping_run`.
- `mapping_run` nutzt die vorhandene Mapping-Ausführung direkt über Services, nicht über einen HTTP-Call gegen Luna selbst.
- Dry-Run wird an die vorhandene Mapping-Dry-Run-Mechanik durchgereicht.
- Manuelle Prozessausführung über die Admin-UI.
- CLI-Ausführung über `php bin/luna process:run <process-id>` und `--dry-run`.
- Prozessläufe mit Status, Modus, Trigger-Typ, Startzeit, Endzeit, Dauer, Fehlertext und sicherem Kontext.
- Prozess-Logs pro Lauf mit Level, Nachricht und kleinem JSON-Kontext.
- Run-Detailansicht mit chronologischen Logs.
- `bin/luna` Usage enthält weiterhin bestehende Kommandos wie `endpoint:export`, `integration:export`, `export:woocommerce:list` und `export:woocommerce:run`.

Bewusst offen:

- `process_run_items` wurde nicht umgesetzt; Datensatzprotokollierung bleibt optional für spätere Versionen.
- Dataset-/Endpoint-spezifische Step-Typen bleiben spätere Erweiterungen.
- Trigger-Konfiguration und Scheduler sind nicht Teil von v2.3.0.

Nicht-Ziele:

- keine vollständige Webhook-Verarbeitung.
- kein Scheduler/Cron als produktive Runtime.
- kein Afterbuy-Adapter.
- kein ERP-Adapter.
- kein WooCommerce-Schreiben.
- keine externe API-Schreibaktion als Pflichtumfang.
- keine PRO-/Lizenzlogik.

Akzeptanzkriterien:

- `composer check` ist grün.
- Migrationen für Prozess-Tabellen laufen sauber.
- Ein Prozess kann in der UI angelegt werden.
- Ein Prozess kann mindestens einen ausführbaren Schritt besitzen.
- Ein Prozess kann manuell gestartet werden.
- Ein Prozess kann per CLI gestartet werden.
- Jeder Lauf erzeugt einen Run-Eintrag.
- Fehler werden nachvollziehbar gespeichert.
- Ein fehlgeschlagener Prozess zerstört keine bestehende Endpoint-/Mapping-Funktion.
- Bestehende Kommandos wie `endpoint:export`, `integration:export`, `export:woocommerce:list` und `export:woocommerce:run` bleiben erhalten.

---

## 6. Nächster Meilenstein

### v2.4.0 - Trigger Layer

Status: geplant / nächster Meilenstein

Ziel:

Prozesse sollen über definierte Trigger gestartet werden können.

Trigger-Typen:

- `manual`
- `cli`
- `api`
- `schedule`
- `webhook`

Geplanter Scope:

- Trigger einem Prozess zuordnen.
- Trigger aktiv/inaktiv schalten.
- Trigger-Konfiguration speichern.
- API-Trigger vorbereiten.
- Webhook-Trigger als Konzept vorbereiten.
- Webhook Base URL aus Deployment Target ableiten.
- Noch keine komplexe WooCommerce-Webhook-Fachverarbeitung als Pflichtumfang.

Abgrenzung:

- Ein Webhook ist nur ein Trigger, kein eigenes Hauptsystem.
- Fachliche Verarbeitung liegt im Prozess oder Adapter, nicht im Trigger selbst.

---

### v2.5.0 - Adapter / Target Actions Foundation

Status: geplant

Ziel:

Prozesse sollen kontrolliert Aktionen gegen Zielsysteme ausführen können.

Mögliche Action-/Adapter-Typen:

- `http_get`
- `http_post`
- `http_put`
- `file_export`
- `database_insert`
- `database_upsert`
- `custom_php` nur falls wirklich nötig und abgesichert
- später spezifisch:
  - `woocommerce_api`
  - `afterbuy_api`
  - `erp_api`
  - `amazon_sp_api`

Geplanter Scope:

- Adapter-Konfiguration ohne Secrets im Export.
- Target Action als Prozess-Schritt nutzbar machen.
- Dry-Run/Preview soweit möglich.
- Fehler und Antwortdaten protokollieren.
- Retry-Grundlagen vorbereiten.

Nicht-Ziele:

- nicht direkt alle Zielsysteme bauen.
- kein hart codierter Afterbuy-Sonderweg.
- kein ERP-Sonderweg im Core.

---

### v2.6.0 - Schema Registry & Validation

Status: geplant

Ziel:

Luna soll Schemas nicht nur im Exportpaket erzeugen, sondern versioniert verwalten und gegen Ergebnisse validieren können.

Geplanter Scope:

- Schema Registry je Workspace.
- `schema_key` und Versionierung.
- Feldtypen, Pflichtfelder und verschachtelte Strukturen.
- Beispielwerte.
- Validierung von Mapping-/Endpoint-/Process-Ergebnissen gegen ein Schema.
- Schema im Exportpaket referenzieren.
- Schema-Änderungen nachvollziehbar machen.

Abgrenzung:

- Keine vollständige OpenAPI-Generatorpflicht.
- JSON Schema kann vorbereitet werden, aber Luna muss nicht sofort jeden JSON-Schema-Sonderfall vollständig unterstützen.

---

### v2.7.0 - WooCommerce Runtime Module

Status: geplant / nach generischer Runtime

Ziel:

WooCommerce wird als konkretes Modul auf Basis von Process Runtime, Trigger Layer und Adapter Foundation umgesetzt oder bereinigt.

Geplanter Scope:

- WooCommerce-Webhooks als Trigger.
- HMAC-/Secret-Prüfung.
- Order-Events verarbeiten.
- Event -> Prozesslauf.
- Prozesslauf -> TransferDB/Staging oder Exportprofil.
- Statusänderungen nachvollziehbar übernehmen.
- WooCommerce-spezifische Exportprofile ordnen.

Wichtige Regel:

```text
WooCommerce ruft Luna auf.
Luna verarbeitet den Prozess.
WooCommerce-Sonderlogik darf nicht die generische Luna-Architektur ersetzen.
```

Nicht-Ziele:

- keine lokale Luna-TransferDB im WordPress-Plugin.
- kein Pflicht-WP-Plugin.
- keine ungeprüften Schreibzugriffe in WooCommerce.

---

### v2.8.0 - External System Modules: Afterbuy / ERP / weitere Systeme

Status: geplant / später

Ziel:

Afterbuy, ERP und weitere Systeme werden als konkrete Module oder Adapter umgesetzt, sobald Process Runtime, Trigger Layer, Adapter Foundation und Schema Registry stabil sind.

Geplanter Scope:

- Afterbuy als erster externer Zielsystemkandidat.
- ERP-Export oder ERP-Import als weiteres Zielsystem.
- Pro System klare Schemas.
- Pro System klare Adapter-Konfiguration.
- Pro System klare Prozessdefinitionen.
- Exportpakete für diese Integrationen.

Abgrenzung:

- Keine Sonderarchitektur pro Zielsystem.
- Keine Vermischung von Adapter, Trigger und Prozesslogik.

---

### v2.9.0 - Official Modules / Entitlement Metadata Preparation

Status: optional / später

Ziel:

Die spätere PRO-/Service-Ebene wird vorbereitet, ohne den Open-Source-Core hart zu verriegeln.

Mögliche Metadaten:

- `origin`
- `support_status`
- `module_key`
- `requires_entitlement`
- `verified_at`
- `modified_from_official`

Wichtige Regel:

Diese Version ist keine harte Lizenzprüfung. Der Core bleibt lauffähig. Offizielle Module, Supportstatus und spätere Freigaben werden nur sauber beschreibbar.

Nicht-Ziele:

- kein externer Lizenzserverzwang.
- kein Luna-Hub-Zwang.
- kein Blockieren selbst erstellter Mappings.

---

## 7. Dauerhafte Nicht-Ziele

Diese Punkte sollen nicht versehentlich in die falsche Version rutschen:

- keine lokale Luna-TransferDB in einem WordPress-Plugin.
- kein Pflicht-WooCommerce-Plugin für den Core.
- keine Secrets in Exportpaketen.
- keine produktiven Schreibzugriffe ohne Dry-Run/Preview/Bestätigung.
- keine Webhook-Fachlogik ohne Process Runtime.
- keine Zielsystemadapter vor stabiler Prozess- und Adapter-Grundlage.
- keine harte PRO-/Lizenzsperre im Open-Source-Core.
- keine Vermischung von Deployment Target und Modul-/Lizenzstatus.
- keine unklaren `localhost`-URLs in Production-Kontexten.

---

## 8. Commit-/Release-Regeln

Für jeden Meilenstein gilt:

- Branch nach Schema `feature/<version>-<kurzer-name>`.
- `composer check` muss grün sein.
- Migrationen müssen sauber laufen.
- Keine generierten Exportpakete committen.
- Keine `.env` oder Secrets committen.
- Neue CLI-Kommandos müssen in `bin/luna` Usage sichtbar sein.
- Bestehende CLI-Kommandos dürfen bei Merges nicht entfernt werden.
- Nach Merge in `main` kann ein Tag gesetzt werden, z. B. `v2.3.0`.

---

## 9. Nächste konkrete Entscheidung

Der nächste Codex-Prompt sollte auf `v2.4.0 - Trigger Layer` gehen.

Er soll ausdrücklich nicht bauen:

- Afterbuy Adapter,
- ERP Adapter,
- WooCommerce-Schreiblogik,
- PRO-/Lizenzserver,
- externe Schreibaktionen als Hauptumfang.

Er soll bauen:

- Trigger-Definitionen für Prozesse,
- Trigger-Aktivierung und -Deaktivierung,
- erste sichere Trigger-Konfigurationen,
- klare Abgrenzung zwischen Trigger und Prozesslogik,
- Anschluss an die bestehende Process Runtime aus v2.3.0.

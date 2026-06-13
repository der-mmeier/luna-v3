# Luna V3 Roadmap

Stand: nach `v2.7.1`
Ziel dieses Dokuments: Die Versionsfolge sauber ordnen, widersprÃ¼chliche alte Planungen bereinigen und die nÃ¤chsten Schritte so definieren, dass Luna nicht in WooCommerce-, Webhook-, Export- und Transfer-Sonderlogik zerfÃ¤llt.

---

## 1. Produktprinzip

Luna V3 ist eine Open-Source-Workbench fÃ¼r Integrationen, Mappings, DatenflÃ¼sse, Endpunkte und spÃ¤tere ProzessausfÃ¼hrung.

Der Kern ist nicht â€žein einzelner Endpointâ€œ und auch nicht â€žnur WooCommerceâ€œ, sondern:

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

Diese Regeln gelten fÃ¼r alle kommenden Versionen:

- Luna Core bleibt generisch.
- WooCommerce, Afterbuy, ERP, Amazon, PIM und Lager werden als Adapter, Prozesse oder konkrete Module gedacht.
- Ein Endpoint liefert Daten aus Luna heraus.
- Ein Webhook ruft Luna von auÃŸen auf und startet spÃ¤ter einen Prozess.
- Ein Prozess beschreibt eine ausfÃ¼hrbare Abfolge von Schritten.
- Ein Trigger beschreibt, wodurch ein Prozess gestartet wird.
- Ein Adapter beschreibt, wie Luna mit einem Ziel- oder Quellsystem spricht.
- Exportpakete enthalten keine Secrets.
- Exportpakete beschreiben Konfiguration, Schema, Mapping, Endpoint/Process und Target-Metadaten.
- Deployment Targets beschreiben Umgebungen und Ã¶ffentliche URLs, aber keine Zugangsdaten.
- PRO-/Lizenzlogik wird hÃ¶chstens durch Metadaten vorbereitet, aber nicht hart in den Core eingebaut.

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
| `v2.1.0` | abgeschlossen | Roadmap-/Architektur-Bereinigung als Ãœbergang |
| `v2.2.0` | abgeschlossen | Deployment Targets & Endpoint Export Packages |
| `v2.3.0` | abgeschlossen | Process Runtime Foundation |
| `v2.4.0` | abgeschlossen | Trigger Layer |
| `v2.5.0` | abgeschlossen | Adapter / Target Actions Foundation |
| `v2.6.0` | abgeschlossen | Schema Registry & Validation |
| `v2.7.0` | abgeschlossen | WooCommerce Runtime Module |
| `v2.7.1` | abgeschlossen | TransferDB Foundation & Runtime Storage |
| `v2.7.2` | abgeschlossen | Admin Cleanup, Deletion Safety & Missing CRUD Routes |
| `v2.7.2.1` | abgeschlossen | Admin Cleanup Completion |
| `v2.7.3` | nächster Meilenstein | Connection Workspace Sharing |
| `v2.8.0` | geplant | Exportable Webhook Runtime Packages |

---

## 4. Abgeschlossene Versionen

### v1.4.0 - JSON Endpoint Builder v2

Status: abgeschlossen

Kern:

- Endpoints an Workspaces/Mappings anbinden.
- GET-JSON-Endpunkte erzeugen.
- Endpoint-Ausgabe mit `success`, `generated_at`, `count` und `items`.
- Delete-Funktionen fÃ¼r zentrale EntitÃ¤ten ergÃ¤nzen.
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
- ISR-SonderfÃ¤lle abbildbar machen, ohne Quellsysteme zu verÃ¤ndern.

Abgrenzung:

- keine neue Runtime-Schicht.
- keine Zielsystemadapter.

---

### v1.6.0 - Integration Modules / Export CLI Foundation

Status: abgeschlossen

Kern:

- Grundlage fÃ¼r exportierbare Integrationsmodule.
- CLI-Struktur fÃ¼r Integrationsexporte.
- Manifest-Grundlagen.
- Modulregistrierung und Export-Runtime vorbereiten.

Abgrenzung:

- noch keine vollstÃ¤ndige Process Runtime.
- noch keine Trigger-Schicht.

---

### v1.7.0 - Dataset-Schicht

Status: abgeschlossen

Kern:

- Dataset-Konzepte einfÃ¼hren.
- Ergebnisse von Mappings/Endpoints intern als Datenquellen nutzbar machen.
- Grundlage fÃ¼r spÃ¤tere Transfers und Prozesse schaffen.

Abgrenzung:

- keine Schreiblogik als Hauptziel.
- keine Prozess-Orchestrierung.

---

### v1.8.0 - Dataset UI / Runtime

Status: abgeschlossen

Kern:

- Dataset-Endpunkte sichtbar machen.
- Dataset Preview/Dry-Run ermÃ¶glichen.
- Output-Felder sichtbar machen.
- Dataset-Ergebnisse besser prÃ¼fbar machen.

Abgrenzung:

- keine Zielsystemadapter.
- keine Prozesslaufzeit.

---

### v1.9.0 - Transfer Layer v1: Single Target Table / Upsert

Status: abgeschlossen

Kern:

- Dataset als Transfer Source verwenden.
- Target Connection und Target Table auswÃ¤hlen.
- Dataset-Felder Zielspalten zuordnen.
- Insert/Update/Upsert unterstÃ¼tzen.
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

- WooCommerce-nahe Export-/Transfer-Grundlagen aus dem Main-Zweig Ã¼bernehmen.
- Bestehende CLI-Kommandos wie `export:woocommerce:list` und `export:woocommerce:run` berÃ¼cksichtigen.
- Bestehende Transfer- und Exportlogik nicht durch neue Roadmap-Punkte Ã¼berschreiben.

Abgrenzung:

- WooCommerce bleibt ein konkreter Anwendungsfall, nicht die Kernarchitektur.
- keine Vermischung von WooCommerce-Sonderlogik mit generischer Luna Runtime.

Hinweis:

Diese Version ist als bestehender Main-Stand zu behandeln. SpÃ¤tere generische Prozess- und Adapterlogik darf vorhandene WooCommerce-Bausteine integrieren, aber nicht blind ersetzen.

---

### v2.1.0 - Roadmap & Architecture Reset

Status: abgeschlossen

Kern:

- Roadmap und Architektur wieder konsolidieren.
- WidersprÃ¼chliche Planungen entfernen.
- Klare Schichtentrennung herstellen.
- WooCommerce, Afterbuy, ERP und Webhooks wieder als SpezialfÃ¤lle der generischen Luna-Architektur einordnen.

Abgrenzung:

- keine groÃŸen PHP-Features.
- keine neue Runtime-Schicht.
- keine PRO-/LizenzprÃ¼fung.

---

### v2.2.0 - Deployment Targets & Endpoint Export Packages

Status: abgeschlossen

Ziel:

Luna kann Endpoints nicht nur ausfÃ¼hren, sondern mit korrekten Ziel-URLs beschreiben und als reproduzierbares, secret-freies Exportpaket ausgeben.

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
- Exportpakete beschreiben die produktive URL, sofern ein Target gewÃ¤hlt wurde.
- Legacy-/Ã¶ffentliche Endpoint-Pfade mÃ¼ssen korrekt beschreibbar sein.

Definition of Done:

- `composer check` ist grÃ¼n.
- Migrationen laufen sauber.
- Deployment Target fÃ¼r den relevanten Workspace kann angelegt werden.
- Production Target enthÃ¤lt kein `localhost`.
- ISR-Endpoint kann mit Target exportiert werden.
- Exportpaket enthÃ¤lt Manifest, Endpoint, Mapping, Schema, Checksums und README.
- Exportpaket enthÃ¤lt keine `.env`-Werte, PasswÃ¶rter, Tokens oder API-Keys.
- Generierte Exportpakete werden nicht versehentlich committed.

Abgrenzung:

- keine Process Runtime.
- keine Webhook Runtime.
- keine WooCommerce-Schreibzugriffe.
- keine Afterbuy-/ERP-Adapter.
- keine PRO-/Lizenzserver-PrÃ¼fung.
- keine zentrale Luna-Hub-Kommunikation.

---

## 5. Abgeschlossene Version

### v2.3.0 - Process Runtime Foundation

Status: abgeschlossen

Ziel:

Luna kann ausfÃ¼hrbare Prozesse beschreiben, manuell oder per CLI starten, protokollieren und nachvollziehbar auswerten.

Ein Prozess ist eine kontrollierte AusfÃ¼hrungseinheit. Trigger-Typen wie API, Schedule und Webhook bleiben spÃ¤tere Konzepte; in v2.3.0 sind nur manuelle AusfÃ¼hrung und CLI-AusfÃ¼hrung produktiv nutzbar.

Umgesetzt:

- Migrationen fÃ¼r `luna_processes`, `luna_process_steps`, `luna_process_runs` und `luna_process_run_logs`.
- Prozessdefinitionen mit Workspace, Name, Key, Status, Beschreibung und Standardmodus.
- Prozess-Schritte mit Position, Step-Typ, Mapping-Referenz, Aktiv-Flag und optionalem `continue_on_error`.
- Erster real ausfÃ¼hrbarer Step-Typ: `mapping_run`.
- `mapping_run` nutzt die vorhandene Mapping-AusfÃ¼hrung direkt Ã¼ber Services, nicht Ã¼ber einen HTTP-Call gegen Luna selbst.
- Dry-Run wird an die vorhandene Mapping-Dry-Run-Mechanik durchgereicht.
- Manuelle ProzessausfÃ¼hrung Ã¼ber die Admin-UI.
- CLI-AusfÃ¼hrung Ã¼ber `php bin/luna process:run <process-id>` und `--dry-run`.
- ProzesslÃ¤ufe mit Status, Modus, Trigger-Typ, Startzeit, Endzeit, Dauer, Fehlertext und sicherem Kontext.
- Prozess-Logs pro Lauf mit Level, Nachricht und kleinem JSON-Kontext.
- Run-Detailansicht mit chronologischen Logs.
- `bin/luna` Usage enthÃ¤lt weiterhin bestehende Kommandos wie `endpoint:export`, `integration:export`, `export:woocommerce:list` und `export:woocommerce:run`.

Bewusst offen:

- `process_run_items` wurde nicht umgesetzt; Datensatzprotokollierung bleibt optional fÃ¼r spÃ¤tere Versionen.
- Dataset-/Endpoint-spezifische Step-Typen bleiben spÃ¤tere Erweiterungen.
- Trigger-Konfiguration und Scheduler sind nicht Teil von v2.3.0.

Nicht-Ziele:

- keine vollstÃ¤ndige Webhook-Verarbeitung.
- kein Scheduler/Cron als produktive Runtime.
- kein Afterbuy-Adapter.
- kein ERP-Adapter.
- kein WooCommerce-Schreiben.
- keine externe API-Schreibaktion als Pflichtumfang.
- keine PRO-/Lizenzlogik.

Akzeptanzkriterien:

- `composer check` ist grÃ¼n.
- Migrationen fÃ¼r Prozess-Tabellen laufen sauber.
- Ein Prozess kann in der UI angelegt werden.
- Ein Prozess kann mindestens einen ausfÃ¼hrbaren Schritt besitzen.
- Ein Prozess kann manuell gestartet werden.
- Ein Prozess kann per CLI gestartet werden.
- Jeder Lauf erzeugt einen Run-Eintrag.
- Fehler werden nachvollziehbar gespeichert.
- Ein fehlgeschlagener Prozess zerstÃ¶rt keine bestehende Endpoint-/Mapping-Funktion.
- Bestehende Kommandos wie `endpoint:export`, `integration:export`, `export:woocommerce:list` und `export:woocommerce:run` bleiben erhalten.

---

## 6. Abgeschlossene Version

### v2.4.0 - Trigger Layer

Status: abgeschlossen

Ziel:

Prozesse sollen Ã¼ber definierte Trigger gestartet werden kÃ¶nnen.

Trigger-Typen:

- `manual`
- `cli`
- `api`
- `schedule`
- `webhook`

Umgesetzt:

- Trigger-Tabelle und Trigger-Verwaltung für Prozesse.
- Trigger-Typen `manual`, `cli`, `api`, `schedule` und `webhook`.
- Trigger aktiv/inaktiv schalten.
- Trigger-Konfiguration speichern und anzeigen.
- API-/Webhook-URL-Vorschau über Deployment Targets.
- Webhook Base URL wird bevorzugt verwendet, sonst Public Base URL mit `/api/webhooks/{trigger_key}`.
- Generische API- und Webhook-Auslösung ohne Fachverarbeitung.
- Prozessläufe speichern Trigger-Kontext, Trigger-Quelle und sichere Request-Metadaten.
- CLI-Ausführung bleibt über `process:run <process-id>` kompatibel und unterstützt zusätzlich `--trigger=<trigger-id-or-key>`.
- Schedule-Trigger werden konfiguriert, aber noch nicht automatisch produktiv ausgeführt.

Abgrenzung:

- Ein Webhook ist nur ein Trigger, kein eigenes Hauptsystem.
- Fachliche Verarbeitung liegt im Prozess oder Adapter, nicht im Trigger selbst.

---

## 7. Abgeschlossene Version

### v2.5.0 - Adapter / Target Actions Foundation

Status: abgeschlossen

Ziel:

Prozesse sollen kontrolliert Aktionen gegen Zielsysteme ausfÃ¼hren kÃ¶nnen.

MÃ¶gliche Action-/Adapter-Typen:

- `http_get`
- `http_post`
- `http_put`
- `file_export`
- `database_insert`
- `database_upsert`
- `custom_php` nur falls wirklich nÃ¶tig und abgesichert
- spÃ¤ter spezifisch:
  - `woocommerce_api`
  - `afterbuy_api`
  - `erp_api`
  - `amazon_sp_api`

Umgesetzt:

- Adapter-Konfiguration ohne Secrets im Export.
- Target Action als Prozess-Schritt nutzbar machen.
- Dry-Run für HTTP-, File- und Database-Actions.
- Fehler und Antwortdaten protokollieren.
- Retry-Grundlagen vorbereiten.
- Target-Action-Tabelle und Admin-UI.
- Generische Action-Typen `http_get`, `http_post`, `http_put`, `file_export`, `database_insert`, `database_upsert`.
- File Export nur in erlaubte Storage-Pfade.
- Database Insert/Upsert ohne freie SQL-Strings aus UI-Konfiguration.
- Step-Kontext mit `previous_result` und `step_results`.
- Spätere systemspezifische Adapter bleiben nur vorbereitet und nicht fachlich implementiert.

Nicht-Ziele:

- nicht direkt alle Zielsysteme bauen.
- kein hart codierter Afterbuy-Sonderweg.
- kein ERP-Sonderweg im Core.

---

## 8. Abgeschlossene Version

### v2.6.0 - Schema Registry & Validation

Status: abgeschlossen

Ziel:

Luna soll Schemas nicht nur im Exportpaket erzeugen, sondern versioniert verwalten und gegen Ergebnisse validieren kÃ¶nnen.

Umgesetzt:

- Schema Registry je Workspace.
- `schema_key` und Versionierung.
- Feldtypen, Pflichtfelder und verschachtelte Strukturen.
- Beispielwerte.
- Validierung von Mapping-/Endpoint-/Process-Ergebnissen gegen ein Schema.
- Schema im Exportpaket referenzieren.
- Schema-Ã„nderungen nachvollziehbar machen.
- Admin-UI für Schema-Liste, Bearbeitung und JSON-Validierung.
- Optionaler Endpoint-Bezug auf Registry-Schemas.
- Process-Step `schema_validation` zur Validierung vorheriger Step-Ergebnisse.
- CLI-Befehl `schema:validate <schema-id> <json-file>`.
- Bestehende generierte Endpoint-Schemas ohne Registry-Referenz bleiben kompatibel.

Abgrenzung:

- Keine vollstÃ¤ndige OpenAPI-Generatorpflicht.
- JSON Schema kann vorbereitet werden, aber Luna muss nicht sofort jeden JSON-Schema-Sonderfall vollstÃ¤ndig unterstÃ¼tzen.

---

### v2.7.0 - WooCommerce Runtime Module

Status: abgeschlossen / gemerged

Ziel:

WooCommerce wird als konkretes Modul auf Basis von Process Runtime, Trigger Layer und Adapter Foundation umgesetzt oder bereinigt.

Umgesetzt:

- WooCommerce-Webhooks als konkrete Trigger-Anwendung auf Basis des generischen Trigger Layers.
- Delivery URL aus Deployment Targets mit `/api/webhooks/woocommerce/{trigger_key}`.
- HMAC-/Secret-Prüfung nach WooCommerce-Prinzip mit Base64-HMAC-SHA256 über den rohen Request Body.
- Order-Topics wie `order.created` und `order.updated` werden generisch normalisiert.
- Valide Webhooks erzeugen normale Prozessläufe mit WooCommerce-Metadaten.
- Event-Metadaten enthalten Provider, Topic/Event, Delivery ID, Source Domain, Empfangszeit und Signaturstatus.
- Payloads werden nur zusammengefasst und sanitizt protokolliert.
- WooCommerce Runtime Events werden intern gestaged und mit Process Runs verknüpft.
- Run-Details zeigen WooCommerce-Metadaten und Payload Summary.
- Bestehende WooCommerce-Export- und Process-Kommandos bleiben erhalten.

Wichtige Regel:

```text
WooCommerce ruft Luna auf.
Luna verarbeitet den Prozess.
WooCommerce-Sonderlogik darf nicht die generische Luna-Architektur ersetzen.
```

Nicht-Ziele:

- keine lokale Luna-TransferDB im WordPress-Plugin.
- kein Pflicht-WP-Plugin.
- keine ungeprÃ¼ften Schreibzugriffe in WooCommerce.

---

### v2.7.1 - TransferDB Foundation & Runtime Storage

Status: abgeschlossen

Ziel:

TransferDB je Workspace konfigurieren, eigene `luna_` Runtime-/Transfer-Tabellen anlegen und Webhook-/Endpoint-/Process-Ergebnisse strukturiert speichern.

Umgesetzt:

- Connection-Rolle `transfer_db` und `mixed`.
- Workspace-Default-TransferDB-Connection.
- TransferDB-Status und Migration über Workspace-UI, Connection-Detailseite und CLI.
- TransferDB-Management-Actions: `Test connection`, `Check TransferDB schema`, `Install/setup TransferDB schema` und `Migrate TransferDB schema`.
- TransferDB-Schema mit `luna_transferdb_migrations`, `luna_webhook_events`, `luna_endpoint_snapshots`, `luna_endpoint_snapshot_records`, `luna_transfer_runs`, `luna_transfer_run_logs` sowie internen `luna_` Hilfstabellen.
- Writer-Services für Sources, Batches, Records, Logs, Webhook Events und Endpoint Snapshots.
- WooCommerce Runtime spiegelt valide Webhooks optional in die TransferDB, ohne bei fehlender TransferDB den bestehenden Flow zu blockieren.
- Endpoint-Detailseite kann Endpoint-Ergebnisse als Snapshot in die TransferDB schreiben.
- Endpoint-Exportpakete enthalten secret-freie `runtime_storage`-Metadaten.

Hinweis:

WooCommerce Runtime in der Luna-App ist vorbereitet; echter Exportbetrieb benötigt TransferDB und später exportierbare Runtime-Pakete.

Nicht-Ziele:

- kein vollständiger exportierbarer Webhook-Receiver.
- kein Pflicht-Deployment der kompletten Luna-Admin-App.
- keine WooCommerce-Schreibaktionen.

---

### v2.7.2.1 - Admin Cleanup Completion

Status: abgeschlossen

Umgesetzt:

- Jobs Delete ergänzt.
- Reports CRUD/Delete ergänzt.
- Connection Delete Guard um konkrete Mapping-, Job-, Dataset-, Transfer-, Process- und WooCommerce-Blocker erweitert.
- Transfers Delete-Routen und datierte Run-Blocker ergänzt.
- WooCommerce Delete-Routen und konkrete Luna-Konfigurations-/Runtime-Blocker ergänzt.
- Delete-Routen-404 für Jobs, Reports, Connections, Transfers und WooCommerce bereinigt.
- Destruktive Aktionen auf POST beschränkt und konkrete deutsche Blocker-Meldungen ergänzt.

Abgrenzung:

- Keine externen Daten werden gelöscht.
- Keine WooCommerce-API-Schreibzugriffe.
- Keine Änderungen an TransferDB-CLI-Semantik, Endpoint Export oder Process Runtime.

---

### v2.7.3 - Connection Workspace Sharing

Status: geplant / nächster Meilenstein

Connection Workspace Sharing bleibt der nächste geplante Meilenstein und ist nicht Teil von v2.7.2.1.

---

### v2.8.0 - Exportable Webhook Runtime Packages

Status: geplant

Ziel:

Webhook Trigger als deploybares Runtime-Paket exportieren, damit öffentliche Subdomains Webhooks empfangen können, ohne die komplette Luna-Admin-App öffentlich zu betreiben.

Geplanter Scope:

- Exportpaket für Webhook Trigger.
- Minimaler öffentlicher Receiver mit Secret-/HMAC-Prüfung.
- TransferDB-Schreibzugriff über `.env.example`-Konfiguration.
- Secret-freie Manifest- und README-Dokumentation.
- Keine Luna-Admin-Oberfläche im öffentlichen Runtime-Paket.

Abgrenzung:

- Keine Sonderarchitektur pro Zielsystem.
- Keine Vermischung von Adapter, Trigger und Prozesslogik.

---

### v2.9.0 - Official Modules / Entitlement Metadata Preparation

Status: optional / spÃ¤ter

Ziel:

Die spÃ¤tere PRO-/Service-Ebene wird vorbereitet, ohne den Open-Source-Core hart zu verriegeln.

MÃ¶gliche Metadaten:

- `origin`
- `support_status`
- `module_key`
- `requires_entitlement`
- `verified_at`
- `modified_from_official`

Wichtige Regel:

Diese Version ist keine harte LizenzprÃ¼fung. Der Core bleibt lauffÃ¤hig. Offizielle Module, Supportstatus und spÃ¤tere Freigaben werden nur sauber beschreibbar.

Nicht-Ziele:

- kein externer Lizenzserverzwang.
- kein Luna-Hub-Zwang.
- kein Blockieren selbst erstellter Mappings.

---

## 9. Dauerhafte Nicht-Ziele

Diese Punkte sollen nicht versehentlich in die falsche Version rutschen:

- keine lokale Luna-TransferDB in einem WordPress-Plugin.
- kein Pflicht-WooCommerce-Plugin fÃ¼r den Core.
- keine Secrets in Exportpaketen.
- keine produktiven Schreibzugriffe ohne Dry-Run/Preview/BestÃ¤tigung.
- keine Webhook-Fachlogik ohne Process Runtime.
- keine Zielsystemadapter vor stabiler Prozess- und Adapter-Grundlage.
- keine harte PRO-/Lizenzsperre im Open-Source-Core.
- keine Vermischung von Deployment Target und Modul-/Lizenzstatus.
- keine unklaren `localhost`-URLs in Production-Kontexten.

---

## 10. Commit-/Release-Regeln

FÃ¼r jeden Meilenstein gilt:

- Branch nach Schema `feature/<version>-<kurzer-name>`.
- `composer check` muss grÃ¼n sein.
- Migrationen mÃ¼ssen sauber laufen.
- Keine generierten Exportpakete committen.
- Keine `.env` oder Secrets committen.
- Neue CLI-Kommandos mÃ¼ssen in `bin/luna` Usage sichtbar sein.
- Bestehende CLI-Kommandos dÃ¼rfen bei Merges nicht entfernt werden.
- Nach Merge in `main` kann ein Tag gesetzt werden, z. B. `v2.3.0`.

---

## 11. Nächste konkrete Entscheidung

Der nächste Codex-Prompt sollte auf `v2.7.3 - Connection Workspace Sharing` gehen.

Er soll ausdrücklich nicht bauen:

- keine Exportable Webhook Runtime implementieren,
- keine WooCommerce-Schreibaktionen,
- keine TransferDB-/Endpoint-/Process-/Schema-Funktionen regressieren.

Er soll bauen:

- Connection Workspace Sharing gemäß eigenem v2.7.3-Scope.




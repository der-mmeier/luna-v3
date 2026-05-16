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

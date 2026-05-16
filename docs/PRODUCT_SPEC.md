# Product Spec — Luna V3

## Produktdefinition

Luna V3 ist eine webbasierte PHP-8.2+-Workbench für Integrationsprojekte. Sie soll Datenquellen verbinden, Datenbanktabellen analysieren, Beispieldaten anzeigen, Spalten kommentieren, Mappings visuell verwalten, Transferdatenbanken befüllen, Jobs ausführen, Reports per E-Mail erzeugen und einfache API-Endpunkte bereitstellen.

## Zielgruppe

Luna V3 richtet sich an interne Entwickler und technische Projektverantwortliche, die wiederkehrende Datenintegrationen strukturiert vorbereiten, dokumentieren und betreiben müssen.

## Nicht-Ziele

Luna V3 ist ausdrücklich kein:

- CMS
- Shop-System
- ERP
- Laravel-Klon
- phpMyAdmin-Ersatz
- vollständiges No-Code-System

## Kernfunktionen

### Workspaces/Projekte

Workspaces bündeln Verbindungen, Schema-Analysen, Mappings, Jobs, Reports und Endpoints für ein Integrationsprojekt.

### Luna-Systemdatenbank

Die Luna-Systemdatenbank speichert interne Metadaten, Konfigurationen, verschlüsselte Connection-Secrets, Mapping-Definitionen, Job-Läufe und Audit-Einträge.

### Externe Datenquellen

Externe Datenquellen sind Quell- oder Zielsysteme außerhalb der Luna-Systemdatenbank. Sie werden über den Connection Manager angelegt und verwaltet.

### Connection Manager

Der Connection Manager verwaltet Verbindungsdefinitionen, Verbindungstypen, read-only-Einstellungen und verschlüsselte Zugangsdaten.

### Schema Explorer

Der Schema Explorer analysiert Datenbanktabellen, zeigt Spalten, Typen, Indizes, Beispieldaten und gepflegte Kommentare.

### Mapping Designer

Der Mapping Designer verwaltet Feldzuordnungen zwischen Quelle, Transformation und Ziel. Er soll Mappings visuell verständlich machen und versionierbar vorbereiten.

### Value Mapping

Value Mapping übersetzt Quellwerte in Zielwerte, zum Beispiel Statuscodes, Kategorien oder externe Schlüssel.

### Transferdatenbank

Transferdatenbanken sind Zielbereiche für bereinigte oder transformierte Integrationsdaten. Sie werden durch Jobs befüllt.

### Job Runner

Der Job Runner führt Mapping- und Transferprozesse manuell oder geplant aus und protokolliert Laufstatus, Fehler und Statistiken.

### Report Engine

Die Report Engine erzeugt einfache Reports und kann Ergebnisse per E-Mail versenden.

### Endpoint Builder

Der Endpoint Builder stellt einfache private API-Endpunkte bereit, um Daten aus definierten Quellen, Mappings oder Transferbereichen abrufbar zu machen.

### Audit Log

Das Audit Log dokumentiert sicherheits- und fachrelevante Ereignisse wie Connection-Änderungen, Mapping-Änderungen, Job-Läufe und Endpoint-Zugriffe.

## Qualitätsziele

- verständliche Integrationsmodelle
- sichere Secret-Verwaltung
- nachvollziehbare Änderungen
- robuste Job-Ausführung
- einfache Betriebsfähigkeit
- klare Trennung von Luna-Core, externen Quellen und Transferzielen

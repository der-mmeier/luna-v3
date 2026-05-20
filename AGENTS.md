# AGENTS.md — Luna V3

## Projektkontext

Dieses Repository enthält Luna V3, eine PHP-8.2+-Workbench für Integrations- und Mapping-Projekte.

Luna V3 verbindet Datenquellen, analysiert Datenbankschemata, verwaltet Workspaces, Connections, Mappings, Jobs, Reports und API-Endpunkte. Das Projekt ist ein privates Framework-/Workbench-Projekt von Marcel Meier.

Aktueller Fokus:
Luna V3 befindet sich in Version 1.1.1 und wird gezielt auf den ersten realen Integrationsfall **AsfInStockRings** vorbereitet. Dafür werden Multi-Connection-Fähigkeit, Lookup-Mapping, JSON-Endpunkte und später eine exportierbare Runtime benötigt.

Luna V3 ist ausdrücklich keine öffentliche API-Plattform. Die Workbench bleibt intern. Öffentlich erreichbar sollen nur bewusst freigegebene oder exportierte Runtime-Endpunkte sein.

## Technische Basis

- PHP >= 8.2
- Composer
- PSR-4 Autoloading
- Namespace: `Luna\`
- Source Root: `src/`
- Public Entry Point: `public/index.php`
- DocumentRoot zeigt auf `/public`
- Environment-Konfiguration über `vlucas/phpdotenv`
- `.env` darf niemals committed werden
- `.env.example` muss aktuell gehalten werden
- Secrets dürfen niemals im Klartext gespeichert, geloggt oder in JSON-/HTML-Ausgaben angezeigt werden

## Arbeitsweise

Arbeite immer in kleinen, nachvollziehbaren Schritten.

Vor jeder größeren Änderung:

1. Bestehende Struktur prüfen
2. Kurz erklären, welche Dateien geändert werden sollen
3. Nur die angeforderten Änderungen umsetzen
4. Keine unnötigen Architekturänderungen einführen
5. Keine Fremdframeworks ergänzen, wenn sie nicht ausdrücklich gefordert sind
6. Bestehende Flows rückwärtskompatibel halten, sofern nichts anderes verlangt wurde

## Git-Regeln

- Keine Änderungen an `vendor/`
- Keine Änderungen an `.env`
- Keine IDE-Dateien committen
- Neue Dateien müssen sinnvoll benannt und einsortiert werden
- Nach Änderungen muss `git status` geprüft werden
- Branches sollen nach Roadmap-Version benannt werden, z. B.:
    - `feature/1.1.1-stabilize-before-integration`
    - `feature/1.2.0-multi-connection-integration-foundation`
    - `feature/1.3.0-lookup-mapping-value-resolver`

## Coding Style

- Strikte Typisierung verwenden: `declare(strict_types=1);`
- Klassen mit Namespace `Luna\...`
- Sichtbarkeiten immer explizit angeben
- Konstruktoren und Methoden sauber typisieren
- Rückgabewerte explizit typisieren, wenn sinnvoll möglich
- Keine globalen Helper-Funktionen ohne Notwendigkeit
- Keine gemischte HTML/PHP-Logik im Framework-Kern
- Framework-Code gehört nach `src/`
- Öffentlich erreichbare Dateien gehören nach `public/`
- Runtime-spezifischer Export-Code muss klar von Admin-/Workbench-Code getrennt bleiben

## Architekturprinzipien

- `public/index.php` ist der zentrale öffentliche Einstiegspunkt der Workbench
- Bootstrap initialisiert Umgebung, Pfade und Kernel
- Framework-Kern bleibt unabhängig von konkreten Anwendungen
- Konfiguration kommt aus `.env`, Config-Klassen oder verschlüsselt gespeicherten Workbench-Definitionen
- Erweiterungen sollen versioniert und dokumentiert werden
- Admin UI und Public Runtime sind klar zu trennen
- Externe Source-Connections sind standardmäßig read-only zu behandeln
- Mapping-, Lookup- und Endpoint-Logik muss testbar gekapselt werden

## Dokumentationspflicht

Wenn eine Änderung architektonisch relevant ist:

- `ROADMAP.md` prüfen und ggf. aktualisieren
- `CHANGELOG.md` aktualisieren
- bei Codex-Arbeit `docs/CODEX_PROTOCOL.md` fortschreiben
- neue Composer-Scripts, CLI-Befehle oder Qualitätschecks dokumentieren
- relevante Sicherheitsannahmen dokumentieren

## Verbindliches Qualitäts-Gate nach jeder Codex-Aufgabe

Ab Version 1.1.1 gilt:

Eine Codex-Aufgabe ist erst abgeschlossen, wenn die verfügbaren Checks ausgeführt wurden und ihr Ergebnis genannt wurde.

Pflichtreihenfolge nach jeder Codeänderung:

```bash
composer dump-autoload
```

Wenn PHPStan installiert ist, muss danach PHPStan laufen.

Bevorzugt:

```bash
composer analyse
```

Fallback, falls das Composer-Script noch nicht existiert:

```bash
vendor/bin/phpstan analyse
```

Wenn PHPUnit installiert ist, müssen danach die PHPUnit-Tests laufen.

Bevorzugt:

```bash
composer test
```

Fallback, falls das Composer-Script noch nicht existiert:

```bash
vendor/bin/phpunit
```

Sobald vorhanden, soll das gemeinsame Qualitäts-Gate bevorzugt verwendet werden:

```bash
composer check
```

`composer check` soll mindestens ausführen:

```bash
composer dump-autoload
composer analyse
composer test
```

Wenn neue oder geänderte PHP-Dateien nicht durch ein vollständiges Script abgedeckt sind, müssen sie zusätzlich mit `php -l` geprüft werden.

Beispiel:

```bash
php -l public/index.php
php -l src/Bootstrap.php
```

Für neu erstellte PHP-Dateien gilt:

```bash
php -l pfad/zur/datei.php
```

## Testpflicht

Tests gehören zur Umsetzung.

Wer produktiven Code ändert oder neue Fachlogik ergänzt, muss passende PHPUnit-Tests mitliefern oder ausdrücklich dokumentieren, warum für diese Änderung kein sinnvoller Test möglich ist.

Besonders testpflichtig sind:

- Secret- und Config-Handling
- Connection-Konfigurationen
- Schema-Explorer-Services
- Mapping- und Template-Resolver
- Lookup-Resolver
- JSON-Response- und Fehlerformate
- Endpoint-Runner
- Export-/Runtime-Code
- Sicherheitslogik gegen Secret-Leaks

Nicht jede HTML-/Bootstrap-Änderung benötigt eigene PHPUnit-Tests. Wenn eine UI-Änderung aber Backend-Logik, Routing, Persistenz oder JSON-Ausgaben betrifft, müssen Tests ergänzt werden.

## Definition of Done für Codex-Aufgaben

Eine Aufgabe gilt nur dann als abgeschlossen, wenn:

- die angeforderten Änderungen umgesetzt sind
- keine unnötigen Architekturänderungen eingeführt wurden
- neue produktive Logik getestet wurde oder eine Begründung fehlt, warum kein Test sinnvoll ist
- `composer dump-autoload` ausgeführt wurde
- PHPStan ausgeführt wurde, falls installiert
- PHPUnit ausgeführt wurde, falls installiert
- `composer check` ausgeführt wurde, sobald vorhanden
- relevante neue/geänderte PHP-Dateien syntaktisch geprüft wurden, falls nicht anderweitig abgedeckt
- `git status` geprüft wurde
- geänderte Dokumentation genannt wurde
- verbleibende Risiken oder offene Punkte genannt wurden

## Verhalten bei fehlschlagenden Checks

Wenn PHPStan, PHPUnit oder ein Syntaxcheck fehlschlägt:

1. Fehler analysieren
2. Fehler beheben, sofern er zur Aufgabe gehört
3. Check erneut ausführen
4. Wenn der Fehler nicht im Scope liegt, ausdrücklich dokumentieren:
    - welcher Check fehlgeschlagen ist
    - welche Datei oder welcher Test betroffen ist
    - warum er nicht im Scope behoben wurde

Fehlschlagende Checks dürfen nicht still übergangen werden.

## Verhalten bei Unsicherheit

Wenn eine Anforderung unklar ist:

- keine große Annahme treffen
- kurz Rückfrage formulieren
- alternativ einen minimalen, reversiblen Vorschlag machen
- bei Zeitdruck oder klarer Roadmap-Richtung die kleinste sichere Umsetzung wählen

## Aktueller Projektstand

Luna V3 steht bei Version 1.1.1.

Aktuelles Ziel:
Stabilisierung nach 1.1.0 und Einführung einer verbindlichen Code-Quality-Grundlage mit PHPStan und PHPUnit. Danach folgen Multi-Connection-Fähigkeit, Lookup Mapping, JSON Endpoint Builder v2, Endpoint Export Runtime und das konkrete AsfInStockRings-Integrationsprojekt.

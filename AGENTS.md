# AGENTS.md — Luna V3

## Projektkontext

Dieses Repository enthält Luna V3, ein eigenes PHP-Framework auf Basis von PHP 8.2+.

Das Projekt ist ein privates Framework-Projekt von Marcel Meier.
Ziel ist ein sauberes, nachvollziehbares MVC-Framework mit Composer-Autoloading, klarer Bootstrap-Struktur und späterer Erweiterung um Routing, Controller, Datenbankzugriff, Models und Module.

## Technische Basis

- PHP >= 8.2
- Composer
- PSR-4 Autoloading
- Namespace: `Luna\`
- Source Root: `src/`
- Public Entry Point: `public/index.php`
- Environment-Konfiguration über `vlucas/phpdotenv`
- `.env` darf niemals committed werden
- `.env.example` muss aktuell gehalten werden

## Arbeitsweise

Arbeite immer in kleinen, nachvollziehbaren Schritten.

Vor jeder größeren Änderung:

1. Bestehende Struktur prüfen
2. Kurz erklären, welche Dateien geändert werden sollen
3. Nur die angeforderten Änderungen umsetzen
4. Keine unnötigen Architekturänderungen einführen
5. Keine Fremdframeworks ergänzen, wenn sie nicht ausdrücklich gefordert sind

## Git-Regeln

- Keine Änderungen an `vendor/`
- Keine Änderungen an `.env`
- Keine IDE-Dateien committen
- Neue Dateien müssen sinnvoll benannt und einsortiert werden
- Nach Änderungen soll `git status` geprüft werden
- Wenn möglich, sollen PHP-Syntaxchecks ausgeführt werden

## Coding Style

- Strikte Typisierung verwenden: `declare(strict_types=1);`
- Klassen mit Namespace `Luna\...`
- Sichtbarkeiten immer explizit angeben
- Konstruktoren und Methoden sauber typisieren
- Keine globalen Helper-Funktionen ohne Notwendigkeit
- Keine gemischte HTML/PHP-Logik im Framework-Kern
- Framework-Code gehört nach `src/`
- Öffentlich erreichbare Dateien gehören nach `public/`

## Architekturprinzipien

- `public/index.php` ist der einzige öffentliche Einstiegspunkt
- Bootstrap initialisiert Umgebung, Pfade und später den Kernel
- Framework-Kern bleibt unabhängig von konkreten Anwendungen
- Konfiguration kommt aus `.env` und später aus Config-Klassen
- Erweiterungen sollen versioniert und dokumentiert werden

## Dokumentationspflicht

Wenn eine Änderung architektonisch relevant ist:

- `ROADMAP.md` prüfen und ggf. aktualisieren
- `CHANGELOG.md` aktualisieren
- bei Codex-Arbeit `docs/CODEX_PROTOCOL.md` fortschreiben

## Tests und Checks

Nach Änderungen nach Möglichkeit ausführen:

```bash
composer dump-autoload
php -l public/index.php
php -l src/Bootstrap.php
```

Wenn neue PHP-Dateien entstehen, sollen sie ebenfalls mit `php -l` geprüft werden.

## Verhalten bei Unsicherheit

Wenn eine Anforderung unklar ist:

- keine große Annahme treffen
- kurz Rückfrage formulieren
- alternativ einen minimalen, reversiblen Vorschlag machen

## Aktueller Projektstand

Initiales Luna-v3-Projekt mit Composer, Dotenv, `public/index.php` und `src/Bootstrap.php`.

Aktuelles Ziel:
Luna V3 schrittweise von Version 0.1.0 bis 1.0.0 aufbauen.

# Roadmap — Luna V3

## Zielbild 1.0.0

Luna V3 soll ein kleines, sauberes PHP-Framework für eigene Webprojekte werden.

Kernziele:

- Composer-basiertes PHP-8.2+-Projekt
- PSR-4 Autoloading
- Public Front Controller
- Bootstrap-Schicht
- Environment-Konfiguration
- Routing
- Request/Response-Abstraktion
- Controller-Lifecycle
- MVC-Grundstruktur
- Datenbankanbindung per PDO
- Basismodell-Schicht
- Modulstruktur für Frontend, Backend und API
- Fehlerbehandlung
- Logging
- einfache Erweiterbarkeit

---

## 0.1.0 — Projektfundament

Ziel:
Sauberes Grundgerüst herstellen.

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
- `.env` wird geladen
- `.env` wird nicht committed
- `.env.example` ist vorhanden
- GitHub-Repository ist sauber

---

## 0.2.0 — Projektziel und Architekturdefinition

Ziel:
Das technische Zielbild von Luna V3 klären, bevor weitere Laufzeitklassen entstehen.

Umfang:

- `docs/PROJECT_GOALS.md`
- `docs/ARCHITECTURE.md`
- Entscheidungspunkte für Routing, MVC, Module und Controller-Lifecycle sammeln
- Datenbankmodell, Konfiguration und Error Handling grob einordnen
- technische Laufzeit skizzieren, ohne konkrete Klassen vorwegzunehmen

Akzeptanzkriterien:

- Projektziel ist als internes PHP-8.2+-Framework dokumentiert
- offene Architekturfragen sind explizit benannt
- geplante Runtime ist grob nachvollziehbar
- keine neuen PHP-Klassen werden für diesen Meilenstein implementiert

---

## 0.3.0 — Request und Response

Ziel:
HTTP-Verarbeitung vorbereiten.

Umfang:

- `src/Http/Request.php`
- `src/Http/Response.php`
- Request aus Superglobals erzeugen
- Response mit Statuscode, Headers und Body
- Kernel gibt Response zurück

Akzeptanzkriterien:

- Kein direktes Echo im Kernel
- Response kann später erweitert werden
- Request kapselt `$_GET`, `$_POST`, `$_SERVER`

---

## 0.4.0 — Routing

Ziel:
Ein einfacher Router.

Umfang:

- `src/Routing/Router.php`
- `src/Routing/Route.php`
- GET-Routen registrieren
- Route anhand von Pfad und Methode finden
- 404-Response bei unbekannter Route

Akzeptanzkriterien:

- `/` liefert Startseite
- unbekannte URL liefert 404
- Routen sind zentral registrierbar

---

## 0.5.0 — Controller-Grundlage

Ziel:
MVC-Controller einführen.

Umfang:

- `src/Controller/AbstractController.php`
- Controller Lifecycle:
  - `init()`
  - `preDispatch()`
  - Action
  - `postDispatch()`
- Beispielcontroller

Akzeptanzkriterien:

- Controller können Responses erzeugen
- Lifecycle ist nachvollziehbar
- Keine Framework-Magie ohne Dokumentation

---

## 0.6.0 — Konfiguration

Ziel:
Saubere Config-Schicht.

Umfang:

- `src/Config/Config.php`
- `.env`-Werte zentral lesbar machen
- App-Umgebung erkennen: local, dev, production
- Debug-Modus konfigurierbar

Akzeptanzkriterien:

- Keine direkten `$_ENV`-Zugriffe überall im Code
- Fehlende Pflichtwerte werden sauber gemeldet

---

## 0.7.0 — Datenbank

Ziel:
PDO-Datenbankanbindung.

Umfang:

- `src/Database/Connection.php`
- PDO Factory oder Singleton-ähnlicher Zugriff
- Konfiguration über `.env`
- Fehlerbehandlung bei Verbindungsproblemen

Akzeptanzkriterien:

- DB-Zugang ist zentral gekapselt
- Keine Zugangsdaten im Code
- Verbindung wird lazy aufgebaut

---

## 0.8.0 — Model-Basis

Ziel:
Grundlage für spätere Models.

Umfang:

- `src/Model/AbstractModel.php`
- einfache Tabellenmetadaten
- Basisfunktionen für find/findAll vorbereiten
- keine übertriebene ORM-Magie

Akzeptanzkriterien:

- Modelle sind erweiterbar
- Datenbanklogik bleibt gekapselt
- spätere information_schema-Generierung bleibt möglich

---

## 0.9.0 — Module Frontend, Backend, API

Ziel:
Projektstruktur für mehrere Bereiche.

Umfang:

- `src/Module/Frontend`
- `src/Module/Backend`
- `src/Module/Api`
- Routing kann Modul-Kontext erkennen
- Basiscontroller je Modul

Akzeptanzkriterien:

- `/api/...` kann separat behandelt werden
- `/backend/...` kann separat behandelt werden
- Frontend bleibt Default

---

## 1.0.0 — Erste stabile Framework-Version

Ziel:
Ein minimal produktiv nutzbares Luna V3 Framework.

Umfang:

- Bootstrap
- Application
- Kernel
- Request/Response
- Routing
- Controller
- Config
- Database
- Model-Basis
- Modulstruktur
- Fehlerseiten
- README-Dokumentation

Akzeptanzkriterien:

- Neue kleine Anwendung kann mit Luna V3 gestartet werden
- Struktur ist dokumentiert
- Basisverhalten ist getestet
- GitHub-Stand ist sauber versioniert

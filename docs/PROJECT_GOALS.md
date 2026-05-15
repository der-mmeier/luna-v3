# Projektziele — Luna V3

Luna V3 ist als internes PHP-8.2+-Framework für eigene Webprojekte geplant. Das Framework soll klein, nachvollziehbar und wartbar bleiben. Es soll wiederkehrende Grundlagen wie Bootstrap, Konfiguration, Routing, Controller-Struktur, Datenbankzugriff und Fehlerbehandlung bündeln, ohne unnötig viel Framework-Magie einzuführen.

Der Fokus liegt auf Projekten, bei denen klare Struktur wichtiger ist als maximale Allgemeingültigkeit. Luna V3 soll kein öffentliches Universalframework ersetzen, sondern eine kontrollierte interne Basis für wiederkehrende Anwendungen bilden.

## Leitlinien

- PHP 8.2+ und Composer bleiben die technische Basis.
- Der Einstiegspunkt bleibt ein schlanker Public Front Controller.
- Konfiguration und Secrets werden über Umgebung und lokale `.env`-Dateien getrennt.
- Architekturentscheidungen werden dokumentiert, bevor sie in Klassen gegossen werden.
- Externe Dependencies sollen nur genutzt werden, wenn sie echten Wartungsaufwand reduzieren.

## Offene Entscheidungsfragen

### Eigener Router vs. Slim

- Soll Luna V3 einen eigenen kleinen Router erhalten?
- Oder soll Slim für Routing und HTTP-Fluss genutzt werden?
- Wie viel Kontrolle über Request-Matching, Middleware und Fehlerantworten wird benötigt?
- Wie schwer wiegt langfristige Wartung gegenüber externer Stabilität?

### MVC-Struktur

- Soll Luna V3 ein klassisches MVC-Modell erzwingen oder nur nahelegen?
- Wo liegen Controller, Views, Models und Module im Projektbaum?
- Wie stark soll die View-Schicht vom Framework vorgegeben werden?
- Gibt es später unterschiedliche Regeln für HTML-Seiten und API-Endpunkte?

### Frontend/Backend/API-Module

- Werden Frontend, Backend und API als feste Module behandelt?
- Soll das Routing den Modulkontext automatisch aus dem Pfad ableiten?
- Wie werden gemeinsame Services und geteilte Controller-Basislogik organisiert?
- Gibt es getrennte Fehlerseiten, Layouts und Authentifizierung pro Modul?

### Controller-Lifecycle

- Soll ein Controller feste Lifecycle-Hooks wie `init()`, `preDispatch()` und `postDispatch()` erhalten?
- Welche Verantwortung bleibt beim Controller, welche wandert in Middleware oder Services?
- Soll jede Action eine Response zurückgeben müssen?
- Wie wird verhindert, dass Lifecycle-Hooks versteckte Logik erzeugen?

### Datenbankmodell

- Bleibt PDO die zentrale Datenbankbasis?
- Soll es nur eine Connection-Schicht geben oder auch eine Model-Basis?
- Wie viel Abstraktion ist sinnvoll, ohne ein eigenes ORM zu bauen?
- Wie werden Tabellenmetadaten, spätere Generatoren und Migrationen eingeordnet?

### Konfiguration

- Wie werden `.env`-Werte in eine zentrale Konfigurationsstruktur überführt?
- Welche Werte sind Pflichtwerte und wann werden sie validiert?
- Gibt es getrennte Konfigurationen für local, dev und production?
- Wie werden sensible Werte von Beispiel- und Defaultwerten getrennt?

### Error Handling

- Soll es eine zentrale Exception- und Error-Handling-Schicht geben?
- Wie unterscheiden sich Debug-Ausgaben lokal von produktiven Fehlerseiten?
- Wie werden 404, 500 und Datenbankfehler behandelt?
- Wird Logging direkt im Framework vorbereitet oder erst später angebunden?

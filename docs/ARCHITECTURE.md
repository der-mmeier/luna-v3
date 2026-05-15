# Architektur — Luna V3

Dieses Dokument skizziert die geplante technische Laufzeit von Luna V3. Es beschreibt bewusst noch keine konkreten Klassenverträge, damit Architekturentscheidungen vor der Implementierung geklärt werden können.

## Geplante Laufzeit

Ein Request erreicht zuerst den öffentlichen Einstiegspunkt im `public`-Verzeichnis. Dieser Einstiegspunkt lädt den Composer-Autoloader, startet die Bootstrap-Phase und übergibt die weitere Verarbeitung später an die zentrale Laufzeit des Frameworks.

Die Bootstrap-Phase bereitet nur die technische Umgebung vor. Dazu gehört vor allem das optionale Laden lokaler Umgebungswerte. Fachlogik, Routing-Entscheidungen und Response-Erzeugung gehören nicht in diese Phase.

Nach dem Bootstrap soll eine zentrale Anwendungslaufzeit entstehen. Sie wird später den HTTP-Request erfassen, Konfiguration bereitstellen, Routing ausführen, Controller oder Handler aufrufen und eine HTTP-Response ausgeben.

## Grober Ablauf

1. Public Front Controller wird aufgerufen.
2. Composer-Autoloader wird geladen.
3. Bootstrap lädt technische Umgebung.
4. Laufzeit erzeugt oder kapselt den HTTP-Request.
5. Routing entscheidet über Zielmodul und Zielaktion.
6. Controller oder Handler verarbeitet den Request.
7. Response wird zentral zurückgegeben und ausgegeben.
8. Fehler werden zentral behandelt und passend zur Umgebung dargestellt.

## Architekturgrenzen

- Der Front Controller bleibt möglichst dünn.
- Bootstrap bleibt auf technische Initialisierung begrenzt.
- Routing, Controller-Lifecycle, Konfiguration und Fehlerbehandlung werden getrennt betrachtet.
- Datenbankzugriffe laufen später über eine zentrale PDO-basierte Schicht.
- Frontend, Backend und API sollen strukturell trennbar sein, ohne gemeinsame Basislogik zu duplizieren.

## Noch nicht festgelegt

- Ob Routing intern umgesetzt oder über Slim angebunden wird.
- Welche konkrete MVC-Verzeichnisstruktur verbindlich wird.
- Ob Module feste Framework-Konzepte oder Projektkonventionen werden.
- Wie Controller-Hooks konkret heißen und wann sie laufen.
- Wie stark Datenbankmodelle abstrahiert werden.
- Wie Konfigurationswerte validiert und bereitgestellt werden.
- Wie Error Handling und Logging technisch zusammenspielen.

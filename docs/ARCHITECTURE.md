# Architektur — Luna V3

Dieses Dokument skizziert die geplante technische Laufzeit von Luna V3 als Integrations- und Mapping-Workbench. Es beschreibt die Architektur grob und legt noch keine konkreten Klassenverträge fest.

## Laufzeitidee

Ein Request erreicht den Public Front Controller unter `/public`. Dort wird der Composer-Autoloader geladen und die technische Bootstrap-Phase gestartet. Die Bootstrap-Phase lädt nur die Luna-Core-Konfiguration, zum Beispiel Umgebung, Debug-Modus, Systemdatenbank-Zugang und `APP_KEY`.

Externe Datenquellen werden nicht über `.env` konfiguriert. Sie werden später über die Admin UI angelegt, verschlüsselt in der Luna-Systemdatenbank gespeichert und über den Connection Manager genutzt.

## Geplante Hauptbereiche

- Application Core für zentrale Laufzeit, Konfiguration und Service-Zugriff
- Admin UI für Workspaces, Connections, Schema Explorer, Mappings, Jobs, Reports und Endpoints
- Luna-Systemdatenbank für interne Metadaten und verschlüsselte Connection-Secrets
- Connection Manager für externe Datenquellen
- Schema Explorer für Tabellen, Spalten, Kommentare und Beispieldaten
- Mapping Designer für Feldzuordnungen, Transformationsregeln und Value Mapping
- Job Runner für Transfers und geplante Verarbeitung
- Report Engine für Auswertungen und E-Mail-Versand
- Endpoint Builder für einfache private API-Endpunkte
- Audit Log für sicherheits- und fachrelevante Ereignisse

## Grober Ablauf eines UI-Requests

1. Public Front Controller wird aufgerufen.
2. Composer-Autoloader wird geladen.
3. Bootstrap lädt Luna-Core-Konfiguration aus `.env`.
4. Application Core verarbeitet den HTTP-Request.
5. Routing wählt Admin-, API- oder Systemaktion.
6. Fachkomponente nutzt Systemdatenbank oder externe read-only Connection.
7. Response wird zentral erzeugt und ausgegeben.
8. Fehler und relevante Änderungen werden auditierbar protokolliert.

## Grober Ablauf eines Jobs

1. Job Runner erhält einen manuellen oder geplanten Auftrag.
2. Mapping- und Connection-Metadaten werden aus der Luna-Systemdatenbank geladen.
3. Benötigte Secrets werden nur im Arbeitsspeicher entschlüsselt.
4. Quellverbindungen werden standardmäßig read-only genutzt.
5. Daten werden transformiert und in eine Transferdatenbank geschrieben.
6. Laufstatus, Fehler und Statistiken werden protokolliert.
7. Optional erzeugt die Report Engine einen E-Mail-Report.

## Architekturgrenzen

- Luna V3 verwaltet keine externen Datenbanken wie ein phpMyAdmin-Ersatz.
- Die Workbench soll Integrationsflüsse beschreiben und ausführen, nicht beliebige Anwendungen generieren.
- Secrets dürfen nicht in Logs, Reports oder Fehlermeldungen erscheinen.
- API-Endpunkte bleiben einfache Integrationsschnittstellen und ersetzen keine vollständige API-Plattform.

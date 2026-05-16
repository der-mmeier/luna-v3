# Security Model — Luna V3

## Grundsätze

Luna V3 verarbeitet Zugangsdaten zu externen Systemen und muss diese strikt von Code, Logs, Reports und Beispielkonfigurationen trennen. Secrets werden nur dort gespeichert, wo sie benötigt werden, und nur verschlüsselt persistiert.

## `.env`

- `.env` enthält nur Luna-Core-Konfiguration.
- `.env` wird nie committed.
- `.env.example` enthält ausschließlich Beispielwerte.
- Externe Verbindungen werden nicht in `.env` gepflegt.
- `APP_KEY` aus `.env` dient als Schlüsselbasis für die Verschlüsselung gespeicherter Secrets.

Typische Luna-Core-Werte:

- App-Name
- Umgebung
- Debug-Modus
- Luna-Systemdatenbank-Verbindung
- `APP_KEY`
- Mail-Grundkonfiguration, falls sie zur Luna-Laufzeit gehört

## Externe Verbindungen

- Externe Verbindungen werden über die UI angelegt.
- Zugangsdaten externer Verbindungen werden verschlüsselt in der Luna-Systemdatenbank gespeichert.
- Secrets werden nur zur Laufzeit entschlüsselt, wenn eine Verbindung hergestellt werden muss.
- Quellverbindungen sollen standardmäßig read-only sein.
- Schreibzugriffe müssen bewusst als Ziel- oder Transferverbindung konfiguriert werden.

## Web-Deployment

- Der DocumentRoot soll auf `/public` zeigen.
- Code, Konfiguration, Vendor-Dateien und interne Dokumentation dürfen nicht direkt über den Webserver erreichbar sein.
- Fehlerausgaben unterscheiden zwischen lokaler Entwicklung und produktiver Umgebung.

## API-Endpunkte

- Private API-Endpunkte brauchen ein Secret.
- API-Secrets werden nicht im Klartext gespeichert.
- Ungültige oder fehlende Secrets führen zu einer generischen Fehlerantwort.
- Endpoint-Zugriffe werden auditierbar protokolliert, ohne Secrets zu speichern.

## Logging und Reports

- Secrets dürfen nie geloggt werden.
- Secrets dürfen nicht in Reports, Fehlermeldungen, Stacktraces oder Audit-Details erscheinen.
- Connection-Strings müssen vor Ausgabe maskiert werden.
- Job-Logs enthalten technische Laufdaten, aber keine Zugangsdaten.

## Audit Log

Das Audit Log dokumentiert sicherheitsrelevante Ereignisse, ohne sensible Werte preiszugeben:

- Connection angelegt, geändert oder deaktiviert
- Secret ersetzt
- Mapping geändert
- Job gestartet, beendet oder fehlgeschlagen
- Report erzeugt oder versendet
- API-Endpoint aufgerufen oder abgelehnt

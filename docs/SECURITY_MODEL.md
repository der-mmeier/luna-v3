# Security Model — Luna V3

## Grundsätze

Luna V3 verarbeitet Zugangsdaten zu externen Systemen und muss diese strikt von Code, Logs, Reports und Beispielkonfigurationen trennen. Secrets werden nur dort gespeichert, wo sie benötigt werden, und nur verschlüsselt persistiert.

## `.env`

- `.env` enthält nur Luna-Core-Konfiguration.
- `.env` wird nie committed.
- `.env.example` enthält ausschließlich Beispielwerte.
- Externe Verbindungen werden nicht in `.env` gepflegt.
- `APP_KEY` aus `.env` dient als Schlüsselbasis für die Verschlüsselung gespeicherter Secrets.
- `APP_KEY` darf nicht rotiert werden, solange verschlüsselte Secrets existieren, außer diese Secrets werden kontrolliert re-encrypted.

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
- Externe Credentials liegen nicht im Klartext in der Luna-Systemdatenbank.
- Passwörter und API-Secrets externer Connections liegen verschlüsselt in `luna_connection_secrets`.
- Secrets werden in der Admin UI nie im Klartext angezeigt.
- Secrets dürfen weder geloggt noch in Exceptions oder Formularwerten ausgegeben werden.
- Secrets werden nur zur Laufzeit entschlüsselt, wenn eine Verbindung hergestellt werden muss.
- Quellverbindungen sollen standardmäßig read-only sein.
- Schreibzugriffe müssen bewusst als Ziel- oder Transferverbindung konfiguriert werden.
- Ohne Login/Auth ist die Connection-Admin-UI nur für lokale Entwicklung vorgesehen und darf nicht öffentlich betrieben werden.

## Web-Deployment

- Der DocumentRoot soll auf `/public` zeigen.
- Code, Konfiguration, Vendor-Dateien und interne Dokumentation dürfen nicht direkt über den Webserver erreichbar sein.
- Fehlerausgaben unterscheiden zwischen lokaler Entwicklung und produktiver Umgebung.

## API-Endpunkte

- Private API-Endpunkte brauchen ein Secret.
- API-Secrets werden nicht im Klartext gespeichert.
- Endpoint-Secrets werden getrennt von Connection-Secrets in `luna_endpoint_secrets` gespeichert.
- Endpoint-Secrets werden mit `EncryptionService` auf Basis von `APP_KEY` verschlüsselt.
- Private Endpoints akzeptieren `X-Luna-Endpoint-Secret`; Query-Secret ist nur außerhalb von production für lokale Tests erlaubt.
- Ungültige oder fehlende Secrets führen zu einer generischen Fehlerantwort.
- Endpoint-Zugriffe werden auditierbar protokolliert, ohne Secrets zu speichern.

## Logging und Reports

- Secrets dürfen nie geloggt werden.
- Secrets dürfen nicht in Reports, Fehlermeldungen, Stacktraces oder Audit-Details erscheinen.
- Connection-Strings müssen vor Ausgabe maskiert werden.
- Job-Logs enthalten technische Laufdaten, aber keine Zugangsdaten.

## Mapping-Daten

- Mapping Sets, Mapping Fields und Value Rules enthalten keine Connection-Secrets.
- Value Mappings sind fachliche Übersetzungsregeln und keine Credentials.
- Externe Datenbanken werden für Mapping-Validierung nur lesend über Schema-Metadaten abgefragt.
- Mapping-Validierung darf keine Zieltabellen beschreiben oder verändern.

## Transfers und Reports

- Dry Run ist der Standardmodus für Transfers.
- Echte Transfers schreiben ausschließlich in die Target-/Transfer-Connection des Mapping Sets.
- Target Connections mit `read_only = 1` blockieren echte Schreiboperationen eindeutig vor dem `TargetWriter`; der Run wird als `failed` mit `written_count = 0` und sicherer Fehlermeldung protokolliert.
- Für 0.9.0 sind nur kontrollierte INSERT-Transfers vorgesehen.
- Fehler aus Job Runs werden auditierbar gespeichert.
- Reports enthalten keine DB-Passwörter, API-Keys, `APP_KEY` oder vollständige DSNs mit Zugangsdaten.

## Audit Log

Das Audit Log dokumentiert sicherheitsrelevante Ereignisse, ohne sensible Werte preiszugeben:

- Connection angelegt, geändert oder deaktiviert
- Secret ersetzt
- Mapping geändert
- Job gestartet, beendet oder fehlgeschlagen
- Report erzeugt oder versendet
- API-Endpoint aufgerufen oder abgelehnt

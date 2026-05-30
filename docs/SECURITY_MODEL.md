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

- Exportierte Endpoint-Runtimes enthalten keine Admin-Routen oder Admin-Templates. Connection- und Endpoint-Secrets werden nicht in PHP-Konfigurationen oder Manifesten exportiert, sondern nur als `.env`-Variablennamen referenziert. Die exportierte `.env.example` enthält ausschließlich leere Platzhalter.
- Der Export-Flag `--local-env` schreibt entschlüsselte Runtime-Secrets ausschließlich in die lokal erzeugte `.env`. Diese Datei ist für lokale Tests gedacht, wird nicht im Manifest aufgeführt und ist über `.gitignore` für `public/pim/.env` und `storage/exports/**/.env` geschützt.
- Workspace-basierte Runtime-Exporte landen standardmäßig unter `storage/{workspace_slug}/exports/endpoints/{endpoint_key}/`; diese Export-Artefakte sind in `.gitignore` ausgeschlossen.
- Export-ZIP-Archive enthalten `.env.example`, Manifest, API-, Runtime- und Config-Dateien, schließen echte `.env`-Dateien aber immer aus. ZIP-Downloads werden aus Endpoint und Workspace berechnet und akzeptieren keine frei übergebenen Dateipfade.
- API-Endpunkte können den Secret-Modus `none`, `optional` oder `required` nutzen.
- API-Secrets werden nicht im Klartext gespeichert.
- JSON Endpoint Builder v2 speichert neue Endpoint-Secrets zusätzlich als Hash in `luna_endpoints.secret_hash`.
- Endpoint-Secrets werden getrennt von Connection-Secrets in `luna_endpoint_secrets` gespeichert.
- Endpoint-Secrets werden mit `EncryptionService` auf Basis von `APP_KEY` verschlüsselt.
- Runtime Endpoints akzeptieren `X-Luna-Endpoint-Secret`; `?secret=` bleibt als initialer Fallback möglich.
- Ungültige oder fehlende Secrets führen zu einer generischen Fehlerantwort.
- Endpoint-Zugriffe werden auditierbar protokolliert, ohne Secrets zu speichern.
- Public Runtime-Fehler nutzen ein standardisiertes JSON-Format ohne Stacktraces, SQL-Queries, DSNs, Secret-Hashes oder Token-Fragmente.
- Admin-Löschaktionen laufen nur per POST mit Bestätigung und prüfen Abhängigkeiten serverseitig. Fehlermeldungen nennen keine Secrets, DSNs, Passwörter oder Secret-Hashes.

## UI-Präferenzen

- `luna_theme` ist ein lokaler UI-Präferenzcookie für `dark` oder `light`.
- Der Cookie wird nicht für Tracking, Authentifizierung oder Rechteprüfung genutzt.
- Dynamische Tabellenlisten für Mapping-Formulare liefern nur Tabellenmetadaten und keine Secrets.

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
- Lookup Mapping speichert nur nicht-sensitive Lookup-Metadaten wie Connection-ID, Tabellenname, Spaltennamen, Key-Template und Fallback-Verhalten.
- Lookup Pattern Matching speichert nur Match-/Result-Modi und Limits. LIKE-Patterns werden aus Templates gerendert und als gebundene SQL-Werte verwendet; Tabellen- und Spaltennamen werden weiterhin validiert.
- Lookup Key-Value Maps speichern nur die Result-Key-Spalte, den Key-Transform und ein optionales Prefix-Template. Result Keys und Values stammen aus validierten Lookup-Spalten; Duplikate werden nicht still überschrieben.
- Lookup Resolver duerfen keine Passwoerter, entschluesselten Secrets oder vollstaendigen DSNs in Preview, JSON, CLI-Ausgaben, Logs oder Fehlermeldungen ausgeben.
- Dry-Run- und Transfer-Previews maskieren sensitive Kontextschluessel und zeigen den normalisierten Transfer-Datensatz getrennt von spaeteren Endpoint-Profilen.

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

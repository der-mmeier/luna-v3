# TransferDB Foundation

Luna kann pro Workspace eine TransferDB konfigurieren. Diese Datenbank ist nicht die Luna-App-Datenbank, sondern speichert Runtime-, Staging- und Transferdaten aus Webhooks, Endpoints, Prozessen und später exportierten Runtime-Paketen.

## Grundregeln

- Eine TransferDB wird über eine Connection mit Verwendung `transfer_db` oder `mixed` bereitgestellt.
- Ein Workspace kann genau eine Default-TransferDB-Connection auswählen.
- Luna legt in der TransferDB ausschließlich eigene Tabellen mit Präfix `luna_` an.
- Bestehende Kundentabellen werden nicht verändert.
- Die TransferDB kann produktive Payloads und personenbezogene Daten enthalten. Der Betreiber ist für Absicherung, Zugriffsschutz, Backups und Datenschutz verantwortlich.

## CLI

```bash
php bin/luna transferdb:check <workspace-id-or-key>
php bin/luna transferdb:migrate <workspace-id-or-key>
```

`transferdb:check` prüft Connection, Erreichbarkeit und Tabellenstatus. `transferdb:migrate` legt fehlende `luna_` Tabellen an oder aktualisiert sie auf die aktuelle TransferDB-Schemaversion.

## Tabellen

v2.7.1 verwaltet diese Tabellen in der TransferDB:

- `luna_transfer_schema_migrations`
- `luna_transfer_sources`
- `luna_transfer_batches`
- `luna_transfer_records`
- `luna_transfer_webhook_events`
- `luna_transfer_endpoint_snapshots`
- `luna_transfer_logs`

## Manuelle Prüfung

1. Connection mit Verwendung `TransferDB` anlegen und Verbindungstest ausführen.
2. Workspace öffnen und die TransferDB-Connection auswählen.
3. Im Workspace `TransferDB prüfen` ausführen.
4. Falls Tabellen fehlen, `TransferDB Tabellen anlegen/aktualisieren` ausführen.
5. Endpoint öffnen und `Snapshot in TransferDB speichern` ausführen.
6. WooCommerce-Testwebhook senden und prüfen, dass Event, Batch und Record in der TransferDB entstehen.

Secrets, Passwörter, Tokens und Authorization-/Cookie-Header dürfen nicht im Klartext in TransferDB-Logs oder Exportpaketen erscheinen.

# TransferDB Foundation

Luna kann pro Workspace eine TransferDB konfigurieren. Diese Datenbank ist nicht die Luna-App-Datenbank, sondern speichert Runtime-, Staging- und Transferdaten aus Webhooks, Endpoints, Prozessen und später exportierten Runtime-Paketen.

## Grundregeln

- Eine TransferDB wird über eine Connection mit Verwendung `transfer_db` oder `mixed` bereitgestellt.
- Ein Workspace kann genau eine Default-TransferDB-Connection auswählen.
- Luna legt in der TransferDB ausschließlich eigene Tabellen mit Präfix `luna_` an.
- Setup und Migration laufen gegen die ausgewählte TransferDB-Connection, nicht gegen die Luna-Systemdatenbank.
- Bestehende Kundentabellen werden nicht verändert.
- Setup und Migration sind idempotent und dürfen bestehende TransferDB-Daten nicht löschen.
- Die TransferDB kann produktive Payloads und personenbezogene Daten enthalten. Der Betreiber ist für Absicherung, Zugriffsschutz, Backups und Datenschutz verantwortlich.

## UI-Aktionen

Bei TransferDB-Connections stehen diese Aktionen zur Verfügung:

- `Test connection`
- `Check TransferDB schema`
- `Install/setup TransferDB schema`
- `Migrate TransferDB schema`

Die Connection-Detailseite zeigt Erreichbarkeit, Migrationsversion, vorhandene Tabellen, fehlende Tabellen und kontrollierte Fehlermeldungen.

## CLI

```bash
php bin/luna transferdb:test <connection-id>
php bin/luna transferdb:status <connection-id>
php bin/luna transferdb:migrate <connection-id>
php bin/luna transferdb:check <workspace-id-or-key>
```

`transferdb:test` prüft nur die Verbindung. `transferdb:status` prüft Erreichbarkeit und Tabellenstatus. `transferdb:migrate` legt fehlende `luna_` Tabellen in der ausgewählten TransferDB an oder aktualisiert sie. `transferdb:check` bleibt als Workspace-basierte Statusprüfung erhalten.

## Tabellen

v2.7.1 verwaltet mindestens diese Tabellen in der TransferDB:

- `luna_transferdb_migrations`
- `luna_webhook_events`
- `luna_endpoint_snapshots`
- `luna_endpoint_snapshot_records`
- `luna_transfer_runs`
- `luna_transfer_run_logs`

Zusätzlich nutzt Luna intern eigene `luna_` Tabellen für technische Quellen und generische Records:

- `luna_transfer_sources`
- `luna_transfer_records`

## Manuelle Prüfung

1. Connection mit Verwendung `TransferDB` anlegen und Verbindungstest ausführen.
2. Connection-Detailseite öffnen und `Check TransferDB schema` ausführen.
3. Falls Tabellen fehlen, `Install/setup TransferDB schema` oder `Migrate TransferDB schema` ausführen.
4. Status prüfen: alle Pflicht-Tabellen müssen unter vorhandene Tabellen erscheinen.
5. Workspace öffnen und die TransferDB-Connection auswählen.
6. Endpoint öffnen und `Snapshot in TransferDB speichern` ausführen.
7. WooCommerce-Testwebhook senden und prüfen, dass Event, Transfer Run und Record in der TransferDB entstehen.

Secrets, Passwörter, Tokens und Authorization-/Cookie-Header dürfen nicht im Klartext in TransferDB-Logs oder Exportpaketen erscheinen.
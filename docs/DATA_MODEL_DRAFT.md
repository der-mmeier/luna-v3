# Data Model Draft — Luna V3

Dieses Dokument skizziert das geplante Datenmodell der Luna-Systemdatenbank. Es ist ein Entwurf und beschreibt Entitäten und Beziehungen, keine fertigen Migrationen.

## Workspaces

Workspaces repräsentieren Integrationsprojekte.

Mögliche Felder:

- `id`
- `name`
- `slug`
- `description`
- `status`
- `created_at`
- `updated_at`

Beziehungen:

- ein Workspace hat viele Connections
- ein Workspace hat viele Mapping-Definitionen
- ein Workspace hat viele Jobs, Reports und Endpoints

## Connections

Connections beschreiben externe Datenquellen, Transferziele oder Systemverbindungen.

Mögliche Felder:

- `id`
- `workspace_id`
- `name`
- `type`
- `role`
- `host`
- `port`
- `database_name`
- `username`
- `encrypted_secret`
- `is_read_only`
- `status`
- `created_at`
- `updated_at`

Hinweise:

- `encrypted_secret` enthält verschlüsselte Zugangsdaten oder Token.
- Quellverbindungen sind standardmäßig read-only.
- Secrets werden nicht im Klartext gespeichert.

## Schema-Metadaten

Schema-Metadaten speichern analysierte Tabellen und Spalten externer Verbindungen.

Mögliche Tabellen:

- `schema_tables`
- `schema_columns`
- `schema_samples`

Mögliche Felder für Tabellen:

- `id`
- `connection_id`
- `schema_name`
- `table_name`
- `table_type`
- `comment`
- `last_scanned_at`

Mögliche Felder für Spalten:

- `id`
- `table_id`
- `column_name`
- `data_type`
- `is_nullable`
- `default_value`
- `ordinal_position`
- `comment`

## Mappings

Mappings beschreiben Datenflüsse von Quellen zu Zielen.

Mögliche Tabellen:

- `mappings`
- `mapping_fields`
- `mapping_rules`

Mögliche Felder für Mappings:

- `id`
- `workspace_id`
- `name`
- `source_connection_id`
- `target_connection_id`
- `source_table_id`
- `target_table_name`
- `status`
- `created_at`
- `updated_at`

Mapping-Felder verbinden Quellspalten, Transformationen und Zielspalten.

## Value Mappings

Value Mappings übersetzen einzelne Quellwerte in Zielwerte.

Mögliche Tabellen:

- `value_maps`
- `value_map_entries`

Mögliche Felder:

- `id`
- `workspace_id`
- `name`
- `source_value`
- `target_value`
- `description`

## Jobs und Transfers

Jobs führen Mappings aus und schreiben in Transferdatenbanken.

Mögliche Tabellen:

- `jobs`
- `job_runs`
- `job_run_logs`
- `transfer_targets`

Mögliche Felder für Job-Läufe:

- `id`
- `job_id`
- `status`
- `started_at`
- `finished_at`
- `rows_read`
- `rows_written`
- `error_message`

Fehlermeldungen dürfen keine Secrets enthalten.

## Reports

Reports definieren Auswertungen und E-Mail-Ausgaben.

Mögliche Tabellen:

- `reports`
- `report_runs`
- `report_recipients`

Mögliche Felder:

- `id`
- `workspace_id`
- `name`
- `type`
- `schedule`
- `last_sent_at`
- `status`

## Endpoints

Endpoints stellen einfache private API-Zugriffe bereit.

Mögliche Tabellen:

- `endpoints`
- `endpoint_secrets`
- `endpoint_access_logs`

Mögliche Felder:

- `id`
- `workspace_id`
- `path`
- `method`
- `source_type`
- `source_id`
- `encrypted_secret`
- `status`

Private Endpoint-Secrets werden verschlüsselt oder gehasht gespeichert und nie geloggt.

## Audit Log

Audit-Einträge dokumentieren relevante Änderungen und Zugriffe.

Mögliche Felder:

- `id`
- `workspace_id`
- `actor_type`
- `actor_id`
- `event_type`
- `entity_type`
- `entity_id`
- `metadata_json`
- `created_at`

Audit-Metadaten dürfen keine Secrets enthalten.

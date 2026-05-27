# Data Model Draft — Luna V3

Dieses Dokument beschreibt den Tabellenentwurf der Luna-Systemdatenbank für Meilenstein 0.6.0. Es ist weiterhin ein technischer Entwurf, aber mit der initialen Migration abgeglichen.

## Migrationen

`luna_migrations` protokolliert ausgeführte SQL-Migrationen.

Wichtige Felder:

- `migration`
- `batch`
- `executed_at`

## Workspaces

`luna_workspaces` speichert Projekte bzw. Arbeitsbereiche.

Wichtige Felder:

- `slug`
- `name`
- `description`
- `status`
- `created_at`
- `updated_at`

## Connection-Profile

`luna_connection_profiles` speichert Metadaten externer Verbindungen, aber keine Secrets im Klartext.

Wichtige Felder:

- `workspace_id`
- `name`
- `type`
- `driver`
- `host`
- `port`
- `database_name`
- `username`
- `read_only`
- `is_active`
- `config_json`
- `notes`

`luna_connection_secrets` speichert Secret-Werte getrennt und verschlüsselt.

Wichtige Felder:

- `connection_profile_id`
- `secret_key`
- `secret_value_encrypted`
- `encryption_version`

Die 0.7.0-Repositories nutzen `luna_connection_profiles` für nicht-sensitive Verbindungsmetadaten und `luna_connection_secrets` für verschlüsselte Secret-Werte. Ein leeres Secret-Feld in der UI darf bestehende Secrets nicht überschreiben.

## Schema-Metadaten und Notizen

`luna_schema_snapshots` speichert analysierte Schema-Snapshots als JSON inklusive Checksumme.

`luna_table_notes` speichert Tabellenkommentare.

`luna_column_notes` speichert Spaltenkommentare und optionale Beispielwerte.

Diese Tabellen referenzieren Connection-Profile und optional Workspaces.

Kommentare sind Luna-Metadaten. Sie werden nicht in externe Datenbanken geschrieben.

## Mapping-Entwürfe

`luna_mapping_sets` beschreibt Mapping-Entwürfe zwischen Quelle und Ziel.

`luna_mapping_fields` beschreibt Feldzuordnungen und einfache Transformationshinweise.

`luna_mapping_value_rules` bildet die Grundlage für Value Mapping einzelner Quell- und Zielwerte.

Für 0.8.0 nutzt der Mapping Designer diese Tabellen direkt:

- `luna_mapping_sets` speichert Workspace, Source-/Target-Connection, Source-/Target-Table und Status.
- `luna_mapping_fields` speichert Source Column, optionalen JSON Path, Target Column, Transform Type, Default Value, Required-Hinweis, Notizen und Sortierung.
- Fuer Lookup Mapping 1.3.0 kann `luna_mapping_fields` zusaetzlich `lookup_connection_id`, `lookup_table`, `lookup_key_column`, `lookup_value_column`, `lookup_key_template`, `fallback_value` und `missing_behavior` speichern.
- Fuer Lookup Pattern Matching 1.3.1 ergaenzt `luna_mapping_fields` additiv `lookup_match_mode`, `lookup_result_mode` und `lookup_result_limit`. Ohne gesetzte Werte bleiben bestehende Regeln kompatibel mit `exact` und `first`.
- Lookup-Metadaten verweisen auf weitere Connection-Profile, speichern aber keine Zugangsdaten. Der Transfer-Datensatz bleibt die normalisierte Mapping-Ausgabe und ist nicht identisch mit einem spaeteren Endpoint-Profil oder Zielsystem.
- `luna_mapping_value_rules` speichert fachliche Übersetzungsregeln für `enum_map`.
- Connection-Secrets werden nicht in Mapping-Tabellen gespeichert.
- Änderungen werden über `luna_audit_log` nachvollziehbar gemacht.

## Audit Log

`luna_audit_log` speichert sicherheits- und fachrelevante Ereignisse.

Wichtige Felder:

- `workspace_id`
- `actor_type`
- `actor_id`
- `action`
- `entity_type`
- `entity_id`
- `message`
- `context_json`
- `ip_address`
- `user_agent`
- `created_at`

Audit-Kontext darf keine Secrets enthalten.

## Endpoints

`luna_endpoints` speichert einfache API-Endpunkte für Integrationsprojekte.

Wichtige Felder:

- `workspace_id`
- `name`
- `endpoint_key`
- `method`
- `visibility`
- `status`
- `response_type`
- `source_type`
- `mapping_set_id`
- `job_id`
- `config_json`
- `rate_limit_per_minute`

`luna_endpoint_secrets` speichert Endpoint-Secrets getrennt von Connection-Secrets und nur verschlüsselt.

Wichtige Felder:

- `endpoint_id`
- `secret_key`
- `secret_value_encrypted`
- `encryption_version`

## Noch nicht enthalten

- Benutzer/Login/Rechte
- Queue-System
- komplexer Cron-Manager

## Jobs, Runs und Reports

`luna_jobs` speichert manuelle Mapping-Transfer-Jobs mit Dry-Run-Standard, Transfermodus, Row Limit und optionalen Report-Empfängern.

`luna_job_runs` speichert einzelne Ausführungen mit Status, Dry-Run-Kennung, Zählern, Summary und Fehlermeldung.

`luna_job_run_logs` speichert nachvollziehbare Logs pro Lauf. Context JSON darf keine Secrets enthalten.

`luna_reports` speichert erzeugte Job-Run-Reports und den Mailstatus. Reports enthalten keine Credentials.

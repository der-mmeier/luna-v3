# Deployment Targets

Deployment Targets beschreiben, unter welcher Ziel-URL eine Luna-Installation oder ein Endpoint in einer Umgebung erreichbar ist.

Beispiele:

```text
local       http://localhost/luna-v3/public
staging     https://staging.example.com/luna
production  https://toolbox.example.com/luna
```

Ein Deployment Target ist keine Datenbank-Connection und enthält keine Secrets. Es speichert nur URL- und Umgebungsmetadaten.

## Lokale URL und Target URL

Die lokale oder aktuelle URL entsteht aus dem laufenden Request. Sie ist hilfreich für Entwicklung und Tests, aber nicht die Quelle der Wahrheit für produktive Dokumentation.

Deployment Targets liefern die geplanten Ziel-URLs für Staging, Production oder eigene Umgebungen. Endpoint-Detailseiten zeigen deshalb beides:

```text
Aktuelle URL
Konfigurierte Ziel-URLs
```

## Felder

- `name`: sprechender Name des Targets
- `environment`: `local`, `staging`, `production` oder `custom`
- `workspace_id`: optionaler Workspace-Bezug, `NULL` bedeutet global
- `public_base_url`: öffentliche Basis-URL der Luna-Installation
- `endpoint_base_url`: optionale explizite Basis-URL für Endpoints
- `webhook_base_url`: optionale Basis-URL für Webhooks
- `license_server_url`: vorbereitetes Metadatum für spätere Dienste
- `is_default`: Default-Target je Workspace und Environment
- `is_active`: Target ist aktiv nutzbar

## URL-Bildung

Wenn `endpoint_base_url` gesetzt ist, wird diese URL verwendet:

```text
endpoint_base_url + "/" + endpoint_slug
```

Wenn `endpoint_base_url` leer ist, leitet Luna die URL aus der Public Base URL ab:

```text
public_base_url + "/api/endpoints/" + endpoint_slug
```

Trailing Slashes werden entfernt. Erlaubt sind nur `http` und `https`. URLs mit eingebetteten Zugangsdaten wie `https://user:pass@example.com` werden abgelehnt.

## Keine Secrets

Deployment Targets enthalten keine Zugangsdaten, Tokens oder Passwörter. Sie dürfen daher in Endpoint-Dokumentation und Exportpaketen referenziert werden.

## Lizenz-Metadaten

`license_server_url`, `module_key`, `origin`, `support_status` und `requires_entitlement` sind in 2.2.0 nur neutrale Metadaten. Luna führt damit keine Lizenzprüfung aus und kontaktiert keinen externen Server.

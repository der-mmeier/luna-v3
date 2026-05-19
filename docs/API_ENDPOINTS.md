# API Endpoints - Luna V3

## Version

```bash
curl "http://localhost:8000/api/version"
```

Antwort enthaelt `app`, `version`, `environment` und `status`.

## Endpoint Runtime

Endpoints werden unter folgendem Pfad bereitgestellt:

```text
/api/e/{endpoint_key}
```

Unterstuetzt werden `GET` und `POST`, je nach Endpoint-Konfiguration.

## Public und Private

Public Endpoints brauchen kein Secret.

Private Endpoints brauchen den Header:

```bash
curl -H "X-Luna-Endpoint-Secret: <endpoint-secret>" "http://localhost:8000/api/e/example"
```

In lokaler Entwicklung ist `?secret=<endpoint-secret>` fuer Tests erlaubt. In `APP_ENV=production` wird Query-Secret nicht akzeptiert.

## Source Types

- `static`: gibt `static_response` aus `config_json` zurueck oder `{ "status": "ok" }`.
- `version`: gibt App-Name, Version, Environment und Status zurueck.
- `mapping_dry_run`: fuehrt einen begrenzten Dry Run mit maximal 25 Preview-Zeilen aus.
- `job_status`: gibt Job-Status und die letzten Runs begrenzt aus.
- `latest_report`: gibt den letzten Report fuer Job oder Workspace gekuerzt aus.

Die API fuehrt in 1.0.0 keine echten Transfers aus und gibt keine unkontrollierten externen Datenbankdaten aus.

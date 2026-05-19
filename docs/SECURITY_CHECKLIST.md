# Security Checklist - Luna V3

- `.env` ist nicht committed.
- `APP_KEY` ist gesetzt und gesichert.
- DocumentRoot zeigt auf `public/`.
- Secrets werden verschluesselt gespeichert.
- Secrets erscheinen nicht in Logs, Reports, Audit-Kontext, CLI-Ausgaben oder Views.
- Source Connections bleiben standardmaessig `read_only`.
- Echte Transfers laufen nur gegen Target-/Transfer-Connections.
- `mapping:run` wird nur mit `--force` ausgefuehrt.
- Private Endpoints haben ein Secret.
- Private Endpoints pruefen `X-Luna-Endpoint-Secret`.
- Query-Secret ist in production deaktiviert.
- GitHub-Stand vor Release auf versehentliche Secrets pruefen.

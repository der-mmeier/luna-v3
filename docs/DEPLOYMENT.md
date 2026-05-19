# Deployment - Luna V3

## Webserver

- Der DocumentRoot muss auf `public/` zeigen.
- `.env` liegt ausserhalb von `public/`.
- `vendor/`, `storage/`, `database/`, `docs/`, `src/` und interne Dateien duerfen nicht direkt oeffentlich ausgeliefert werden.
- `.env` niemals committen.

## Secrets

- `APP_KEY` in der echten `.env` setzen und sichern.
- `APP_KEY` nach gespeicherten Secrets nicht ohne kontrollierte Re-Encryption aendern.
- Keine echten Secrets in `.env.example`, Doku, Tests, Templates oder Code.

## Updates

- Vor Updates DB-Backup erstellen.
- Nach Deploy `composer dump-autoload` und `php bin/luna migrate` ausfuehren.
- Danach `/api/version` und die Admin-Smoke-Routen pruefen.

## Apache-Hinweise

- VirtualHost `DocumentRoot` auf den absoluten Pfad zu `public/` setzen.
- `AllowOverride` nur aktivieren, wenn lokale Rewrite-Regeln benoetigt werden.
- Directory Listing deaktivieren.
- Fehlerausgabe in production ueber `APP_DEBUG=false` abschalten.

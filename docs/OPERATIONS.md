# Operations - Luna V3

## Lokale Entwicklung

- PHP 8.2+ und Composer installieren.
- `.env.example` als Vorlage fuer eine lokale `.env` verwenden.
- `APP_KEY` in der echten `.env` setzen, bevor Secrets gespeichert werden.
- DocumentRoot oder PHP Built-in Server auf `public/` ausrichten.

## Migrationen

Systemdatenbank pruefen:

```bash
php bin/luna db:test
```

Migrationen ausfuehren:

```bash
php bin/luna migrate
```

## CLI-Befehle

```bash
php bin/luna db:test
php bin/luna migrate
php bin/luna mapping:dry-run <id>
php bin/luna mapping:run <id> --force
php bin/luna job:run <id> --dry-run
```

Vor jedem echten Transfer muss ein Dry Run ausgefuehrt und geprueft werden. Echte Transfers sind nur fuer bewusst konfigurierte Target-/Transfer-Connections vorgesehen.

## Betriebskontrolle

- Job Runs in `/admin/jobs` pruefen.
- Reports in `/admin/reports` pruefen.
- Audit-Eintraege in `/admin/audit` pruefen.
- Logs, Reports und Audit-Kontext duerfen keine Secrets enthalten.

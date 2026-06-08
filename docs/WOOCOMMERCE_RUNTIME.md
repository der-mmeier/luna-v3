# WooCommerce Runtime Module

WooCommerce ruft Luna auf. Luna validiert den Webhook und startet einen bestehenden Prozess. WooCommerce-Fachlogik bleibt Modul/Adapter und ersetzt nicht die generische Trigger-, Process-, Target-Action- und Schema-Architektur.

## Delivery URL

WooCommerce-Trigger verwenden die generische Webhook-Basis des Deployment Targets:

```text
{webhook_base_url}/woocommerce/{trigger_key}
```

Wenn keine Webhook Base URL gesetzt ist, wird die Public Base URL mit `/api/webhooks` verwendet:

```text
{public_base_url}/api/webhooks/woocommerce/{trigger_key}
```

Die URL enthält keine Secrets und wird in WooCommerce unter `Einstellungen > Erweitert > Webhooks` als Delivery URL eingetragen.

## Trigger-Konfiguration

Ein WooCommerce-Webhook-Trigger ist ein normaler Trigger vom Typ `webhook` mit WooCommerce-Konfiguration:

```json
{
  "provider": "woocommerce",
  "topic": "order.updated",
  "allow_unsigned": false,
  "payload_log_mode": "summary",
  "max_payload_log_length": 4000
}
```

Das Webhook Secret muss identisch in WooCommerce und Luna hinterlegt sein. Luna nutzt das Secret für die WooCommerce-HMAC-Prüfung und speichert es verschlüsselt. Secrets werden nicht in Run-Logs, Payload Summaries oder Exportpakete geschrieben.

## Verarbeitung

Die Route lautet:

```text
POST /api/webhooks/woocommerce/{trigger_key}
```

Bei einem gültigen Webhook:

1. Luna lädt den Trigger über den `trigger_key`.
2. Luna prüft Aktiv-Status, Provider und optional das konfigurierte Topic.
3. Luna prüft `X-WC-Webhook-Signature` per Base64-HMAC-SHA256 über den rohen Request Body.
4. Luna normalisiert Provider, Topic, Event, Delivery ID, Shop-Domain, Empfangszeit und Payload Summary.
5. Luna schreibt ein internes WooCommerce Runtime Event.
6. Luna startet den zugeordneten Prozess über den generischen Trigger Runner.
7. Der Process Run erhält WooCommerce-Metadaten und einen referenzierten Payload-Kontext.

Ungültige Signaturen oder inaktive Trigger starten keinen Prozesslauf.

## Manuelles Testen

Beispiel-Payload:

```json
{"id":10001,"status":"processing","total":"99.95"}
```

Signatur erzeugen:

```powershell
$secret = "webhook-secret"
$body = '{"id":10001,"status":"processing","total":"99.95"}'
$hmac = New-Object System.Security.Cryptography.HMACSHA256
$hmac.Key = [Text.Encoding]::UTF8.GetBytes($secret)
$signature = [Convert]::ToBase64String($hmac.ComputeHash([Text.Encoding]::UTF8.GetBytes($body)))
```

Request senden:

```powershell
Invoke-RestMethod `
  -Method Post `
  -Uri "https://example.test/luna/api/webhooks/woocommerce/wc-order-updated" `
  -Headers @{
    "X-WC-Webhook-Signature" = $signature
    "X-WC-Webhook-Topic" = "order.updated"
    "X-WC-Webhook-Resource" = "order"
    "X-WC-Webhook-Event" = "updated"
    "X-WC-Webhook-Delivery-ID" = "manual-test-1"
  } `
  -ContentType "application/json" `
  -Body $body
```

Danach den Process Run in Luna öffnen und die WooCommerce-Metadaten, Signaturstatus und Payload Summary prüfen.

## Abgrenzung

v2.7.0 führt keine ungeprüften Schreibzugriffe in WooCommerce aus. Statusänderungen werden als Runtime Event und Process-Kontext nachvollziehbar, aber nicht automatisch zurückgeschrieben.

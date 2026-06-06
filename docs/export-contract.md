# Export Contract

Der Export Contract beschreibt einen Endpoint reproduzierbar und secret-frei. Er ist kein Runtime-Export und keine Lizenzsperre.

In Luna 2.2.0 werden ausschließlich Endpoints als Export Contract exportiert. Das Format ist so angelegt, dass später auch andere Artefakt-Typen wie `process` beschrieben werden können.

## Exportpaket

Ein Endpoint-Exportpaket enthält:

```text
manifest.json
schema.json
endpoint.json
mapping.json
README.md
checksums.json
```

Optional können später Beispielantworten oder Changelogs ergänzt werden.

## manifest.json

Das Manifest beschreibt das Exportpaket:

- Contract-Version
- Artefakt-Typ
- Exportzeitpunkt
- Luna-Version
- Workspace
- Endpoint
- Mapping
- gewähltes Deployment Target, falls vorhanden
- Dateiliste
- Sicherheitsmetadaten

`security.contains_secrets` muss immer `false` sein.

## schema.json

`schema.json` beschreibt die JSON-Antwort des Endpoints. Luna-Endpunkte nutzen den Wrapper:

```json
{
  "success": true,
  "generated_at": "...",
  "count": 0,
  "items": []
}
```

Die Felder unter `items[]` werden aus den Mapping-Feldern erzeugt. Optionale Schema-Metadaten an Mapping-Feldern können den Typ konkretisieren. Wenn kein sicherer Typ bekannt ist, nutzt Luna eine konservative Ableitung.

Mengenobjekte wie `dr_quantities` und `hr_quantities` können als `object` mit numerischen Werten beschrieben werden.

## endpoint.json

`endpoint.json` beschreibt den Endpoint ohne Secrets:

- ID
- Name
- Slug
- Methode
- Pfad
- Response Wrapper
- Mapping-Referenz
- Cache-Metadaten
- Status

## mapping.json

`mapping.json` beschreibt die fachliche Mapping-Konfiguration:

- Mapping-Set
- Source-Tabelle
- Source-Filter
- Output-Felder
- Transform-Typen
- Lookup-Konfigurationen
- Schema-Metadaten

Connections werden nur als Referenzen exportiert. Hostnamen, Usernamen, Passwörter, DSNs, Tokens und andere Secrets werden nicht exportiert.

## Secret-Schutz

Der Export läuft durch einen zentralen Sanitizer. Sensitive Keys werden rekursiv entfernt, unter anderem:

```text
password
passwd
pwd
secret
token
access_token
refresh_token
api_key
apikey
app_key
private_key
client_secret
dsn
username
```

Secrets dürfen nicht in JSON-Dateien, README-Dateien, Checksums oder Testsnapshots landen.

## Grenzen in 2.2.0

- Es gibt keine PRO-Sperre.
- Es gibt keine Online-Lizenzprüfung.
- Es gibt keinen externen Lizenzserver-Call.
- Es wird keine Process Runtime gebaut.
- Es werden keine Webhook-Runtimes gebaut.
- Es werden keine Zielsystemadapter gebaut.
- Der Export beschreibt nur Endpoints.

# Projektziele — Luna V3

Luna V3 ist eine webbasierte PHP-8.2+-Workbench für Integrations- und Mapping-Projekte. Sie soll Teams dabei unterstützen, externe Datenquellen kontrolliert anzubinden, Datenbankschemata zu verstehen, Mappings zu pflegen, Transferdatenbanken zu befüllen, Jobs auszuführen, Reports zu erzeugen und einfache API-Endpunkte bereitzustellen.

Luna V3 ist eine interne Arbeitsplattform für wiederkehrende Integrationsaufgaben. Der Schwerpunkt liegt auf nachvollziehbaren Datenflüssen, sicher verwalteten Verbindungen und auditierbaren Änderungen.

## Nicht-Ziele

Luna V3 ist ausdrücklich kein:

- CMS
- Shop-System
- ERP
- Laravel-Klon
- phpMyAdmin-Ersatz
- vollständiges No-Code-System

## Leitlinien

- PHP 8.2+ und Composer bleiben die technische Basis.
- Der DocumentRoot zeigt auf `/public`.
- `.env` enthält nur Luna-Core-Konfiguration.
- Externe Verbindungen werden über die UI angelegt.
- Secrets werden verschlüsselt in der Luna-Systemdatenbank gespeichert.
- Quellverbindungen sind standardmäßig read-only.
- Jobs, Transfers, Reports und API-Endpunkte sind nachvollziehbar protokolliert.

## Zentrale Konzepte

- Workspaces/Projekte
- Luna-Systemdatenbank
- externe Datenquellen
- Connection Manager
- verschlüsselte Secrets
- Schema Explorer
- Mapping Designer
- Value Mapping
- Transferdatenbank
- Job Runner
- Report Engine
- Endpoint Builder
- Audit Log

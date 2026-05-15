# Codex-Protokoll — Luna V3

Dieses Protokoll dokumentiert Arbeitspakete, die mit Codex umgesetzt oder vorbereitet wurden.

## Format

Jeder Eintrag soll enthalten:

- Datum
- Ziel
- Prompt/Aufgabe
- Geänderte Dateien
- Ergebnis
- Offene Punkte
- Commit-Hash, falls vorhanden

---

## 2026-05-16 — Initiale Codex-Struktur

### Ziel

Projekt für strukturierte Codex-Arbeit vorbereiten.

### Aufgabe

AGENTS.md, ROADMAP.md, CHANGELOG.md und Codex-Protokoll anlegen.

### Geänderte Dateien

- AGENTS.md
- ROADMAP.md
- CHANGELOG.md
- docs/CODEX_PROTOCOL.md

### Ergebnis

Offen.

### Offene Punkte

- Erste Roadmap-Version finalisieren
- Version 0.1.0 definieren
- Erstes Codex-Arbeitspaket testen

---

## 2026-05-16 — Meilenstein 0.1.0

### Ziel

Projektfundament technisch sauber abschließen.

### Aufgabe

Meilenstein 0.1.0 aus ROADMAP.md umsetzen: Front Controller, Bootstrap, Dotenv-Laden, Beispielumgebung und Dokumentation prüfen und aktualisieren.

### Geprüfte und geänderte Dateien

- public/index.php
- src/Bootstrap.php
- .env.example
- CHANGELOG.md
- docs/CODEX_PROTOCOL.md

### Ergebnis

Bootstrap lädt Dotenv nur, wenn eine lokale `.env` existiert. Der Public Entry Point bleibt schlank und nutzt den Composer-Autoloader sowie `Luna\Bootstrap`. `.env.example` enthält lokale Beispielwerte ohne echte Secrets.

### Offene Punkte

- Keine.

# studio-kit

Lokale Werkbank für Rive-Reels: Dateien mit Fassungen, Mappen, Musik unterlegen.

    bin/up                          # bauen und starten → http://localhost:8090
    bin/dc down                     # stoppen
    bin/dc exec app composer test   # Tests
    bin/dc exec -e APP_ENV=test app composer ci   # alle Prüfungen wie in CI

Alle Docker-Befehle über `bin/dc`, nie direkt `docker compose` – der Wrapper setzt den Datenpfad.

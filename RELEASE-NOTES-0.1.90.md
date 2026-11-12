# CORE 0.1.90 — Deterministic System World Generation

## Scope

- Added `WorldGenerator`, which derives 1–9 worlds per system from the universe seed and stable system ID.
- Each world has a stable ID, display name, orbital slot, classification, and eight-character Traveller-inspired Universal World Profile (UWP).
- Exposes structured starport, size, atmosphere, hydrographics, population, government, law-level, and tech-level values.
- Gameplay boot data includes generated world profiles for the assigned universe.
- Selecting a star system displays its generated worlds and UWP summaries.
- Generation is deterministic and does not modify persisted universe JSON, preserving existing universe and character associations without migration.
- Workbench CSS and JavaScript cache-busting now read the central release version rather than hard-coded version strings.
- Added CLI and browser QA regression coverage.

## Compatibility and data safety

No existing universe, character, account, or other player record is rewritten. No `storage/`, secrets, logs, backups, or Git metadata are included.

## Validation performed against this exact patch

- CLI suite: 84 passed, 0 failed.
- Browser QA endpoint: 143 passed, 0 failed (HTTP 200).
- PHP syntax: all project PHP files passed.
- JavaScript syntax: all project JavaScript files passed.
- Player-facing prohibited-term scan: clean.

User installation QA remains pending until confirmed.

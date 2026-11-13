CORE 0.1.90 — Deterministic System World Generation

PATCH CONTENTS
This focused release adds deterministic Traveller-inspired world profiles to each generated star system and displays those profiles when a player selects a system in the gameplay map.

WORLD PROFILES
- Each system receives 1–9 deterministic world profiles derived from the universe seed and stable system ID.
- Each profile has a stable world ID, name, orbital slot, classification, and eight-character Universal World Profile (UWP).
- UWP components are exposed as structured fields: starport, size, atmosphere, hydrographics, population, government, law level, and technology level.
- Repeated generation with the same seed and system ID produces identical profiles.
- Existing universe JSON is not modified; worlds are derived at runtime, so no player-data migration is required.

INSTALLATION
1. Back up the current CORE source.
2. Extract this patch into the application root, preserving directory paths.
3. Do not replace or merge storage/, .git/, or player data.
4. Confirm config/version.php reports 0.1.90.
5. Run php tests/run.php and the browser QA endpoint from the trusted development/private network.

This patch contains code and documentation only. It does not include account, character, universe, log, backup, secret, or Git data.

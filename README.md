# CORE PHP Foundation 0.1.17

CORE 0.1.17 adds a character location model and revision-checked movement between systems belonging to the character's own universe.

## What's new

- New characters begin in the first system of their selected universe.
- Character records expose `current_system_id` and a `location` object with a system ID and UTC update timestamp.
- `POST /api/characters/location` moves a character using `universe_id`, `character_id`, `system_id`, and `expected_revision`.
- Movement validates the target system against the selected universe and validates the character's universe association.
- Character movement is idempotent when the character is already at the requested destination: the response reports `already_at_destination` and the revision does not increment.
- Character-specific exploration history records the starting system and each newly visited system once, with the first-visit timestamp and revision.
- Returning to a previously visited system updates location and revision but does not duplicate its exploration record.
- The Workbench displays each selected character's current system and unique explored-system list.
- Four new isolated QA tests cover idempotent movement, starting-system exploration, visit history, and duplicate prevention.

## Install

Copy the extracted package contents over the existing CORE application. Preserve existing `storage/data/` and `storage/logs/` contents.

Run **Automated QA → Run automated tests** in the Workbench after installation.

## Security

Character endpoints remain development-only and are restricted to local/private-network clients. Authentication, ownership permissions, and public deployment hardening are not implemented. Do not expose CORE publicly.

## Verification

Local PHP syntax checks and isolated CLI tests should be run against this package before installation. The browser QA suite uses temporary directories and does not modify saved universe records.

## Verification

- Browser-based QA: 34 passed, 0 failed (run against a local PHP development server).
- CLI regression suite: 9 passed, 0 failed.
- PHP syntax checks: all project PHP files parse.

These are local package checks. Run the browser QA from the Workbench after installing to verify this release in your environment.


## 0.1.17 — Basic navigation rules

- Derives a deterministic minimum-spanning-tree route network from each universe's system coordinates; existing saved baselines do not need migration.
- Character movement is limited to the current system or a directly connected neighboring system.
- Adds the development/private-network-only `GET /api/navigation?universe_id=...` endpoint.
- The Workbench marks the current and reachable systems and disables destinations that are not directly connected.
- Run browser QA in the Workbench after installation. Preserve existing `storage/data/` and `storage/logs/` contents.

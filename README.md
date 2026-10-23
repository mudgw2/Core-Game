# CORE PHP Foundation 0.1.15

CORE 0.1.15 adds a character location model and revision-checked movement between systems belonging to the character's own universe.

## What's new

- New characters begin in the first system of their selected universe.
- Character records expose `current_system_id` and a `location` object with a system ID and UTC update timestamp.
- `POST /api/characters/location` moves a character using `universe_id`, `character_id`, `system_id`, and `expected_revision`.
- Movement validates the target system against the selected universe and validates the character's universe association.
- Character revisions increment on movement; stale movement requests return HTTP 409.
- The Workbench includes character and destination-system selectors and a Move character action.
- Four new isolated QA tests cover starting location, valid movement, cross-universe rejection, and stale revisions.

## Install

Copy the extracted package contents over the existing CORE application. Preserve existing `storage/data/` and `storage/logs/` contents.

Run **Automated QA → Run automated tests** in the Workbench after installation.

## Security

Character endpoints remain development-only and are restricted to local/private-network clients. Authentication, ownership permissions, and public deployment hardening are not implemented. Do not expose CORE publicly.

## Verification

Local PHP syntax checks and isolated CLI tests should be run against this package before installation. The browser QA suite uses temporary directories and does not modify saved universe records.

# CORE Foundation 0.1.24 — Discovery Investigation

## Changes

- Added a revision-checked investigation service and development-only API endpoint for character-owned scanned discoveries.
- Added per-signal investigated status, UTC timestamp, and descriptive outcome to the saved character scan record.
- Added idempotent repeat handling; existing investigation results are returned without changing the character revision.
- Requires the character to be in the system where the discovery was recorded.
- Rejects stale revisions, cross-universe character IDs, unknown discoveries, and remote-system investigations; rejected requests do not change saved state.
- Added Workbench investigation buttons, visible outcomes, and activity logging in Scanning & Discoveries.
- Updated health and QA version metadata to 0.1.24.

## Data compatibility

Existing character scan records remain valid. Previously saved discoveries without a status are treated as uninvestigated. The feature only updates a character record when a discovery is successfully investigated.

## Verification

Run Workbench → Operations & QA → Run automated tests. Expected result: **PASS — 62 tests passed**.

## Update safety

Back up before installing. Preserve existing `storage/data/` and `storage/logs/` contents. Do not expose development APIs publicly before authentication and access controls are implemented.

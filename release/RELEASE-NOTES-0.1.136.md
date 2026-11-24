# CORE 0.1.136 — Persisted Planetary Geography Baselines

## Scope
Corrects the 0.1.135 geography endpoint so the first generated geography baseline is stored and reused for each stable world ID.

## Behavior
- The authenticated geography API now uses atomic `createIfAbsent` storage in `storage/world_geography/`.
- The first successful generation becomes the world's persistent geographic baseline.
- Subsequent requests return the stored baseline instead of regenerating or silently changing it after a generator update.
- Stored records retain universe, system, and world ownership identifiers; identity mismatches are rejected with HTTP 409.
- Existing player/account/universe records are not modified.
- The API response shape remains `{"ok":true,"geography":...}`.

## Future player-selection direction
The world map and the character's current hex can later be presented in Character Selection alongside the existing location/orbit preview. This release does not add that UI, character location changes, map rendering, or new location data. It establishes persistent world geography as a stable data source for that future work.

## Validation
The source archive's baseline suite passed before this change: 128 passed, 0 failed. Updated validation against this patch: 129 passed, 0 failed. PHP syntax validation is included in the suite.

## Installation
Extract the patch into the CORE project root, preserving relative paths. Do not overwrite `storage/`, player records, accounts, credentials, backups, logs, or `.git/`. The `world_geography` collection is created automatically when the API first generates a world baseline.
